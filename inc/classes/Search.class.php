<?php
/* inc/classes/Search.class.php

   EXECUTIVE SUMMARY:
   This class supports the search that is more or less "at the front" of Panther.

   NOTE >>>00004 makes no attempt to look at 'customer'

* Public functions:
** __construct($type = 'mypage', User $user = null)
** search ($q = '', User $user = null)
** searchLocations($q = '', &$errCode = false)

* Private functions:
** searchJobs($q)
** persons($q)
** jobNumber($q)
** jobAncillary($q)
** jobName($q)
** companys($q)
*/

class Search {
    private $type;
    private $db;
    //private $localLogger; // added 2019-11-13 JM
    private $user;
    private $logger;

    // INPUT $type
    //  [Martin comment]: "the type might not make sense going forward"
    //  JM comment: default 'mypage', no idea what other values make any sense,
    //    & I don't think any others are used
    // INPUT $user, a User object that defaults to null, which can in principle
    //    be used to make objects returned by the various functions use the context
    //    of a different user than the one currently logged in.
    //    In practice as of 2019-02:
    //     * panther.php passes the current user
    //     * index.php?act=search which is probably not even used passes an apparently undefined $person
    //     & there are no other calls.
    // Constructor just saves these & gets DB access.
    public function __construct($type = 'mypage', User $user = null) {
        $this->logger = Logger2::getLogger("main"); // added 2020-09-18 George.

        $this->type = $type;
        $this->user = $user;
        $this->db = DB::getInstance();
    }

    // INPUT $q (query)
    // INPUT $user: User object, appears to be ignored in favor of the one in the constructor;
    //       >>>00007 not desirable, but harmless.
    // User (from the constructor) and $type=='mypage' (also from the constructor) must both
    //   be set to get a non-empty return.
    //
    // Calls several methods of this class to fill out the return.
    // Returns an associative array, whose members are:
    //  * 'jobNumbers': an array of Job objects; three successive groupings.
    //    * For the first grouping, some portion of job.number matches the query string.
    //      These are in (job.name, job.number) order.
    //    * For the second grouping, some portion of searchable ancillary data matches the query string.
    //      These are also in (job.name, job.number) order, after all the objects from the first grouping.
    //    * For the third grouping, some portion of job.name matches the query string.
    //      These are also in (job.name, job.number) order, after all the objects from the first grouping.
    // * 'persons': an array of Person objects.
    //    * If $q appears to be a phone number, then we strip it down to digits before attempting a match.
    //      The array will consist only of persons with matching phone numbers (could, in theory, have some extra
    //      digits at beginning or end), in (lastName, firstName) order.
    //    * If $q appears NOT to be a phone number, then we try to match on all or part of the first or last name.
    //      Again, return is in (lastName, firstName) order.
    // * 'companys': very analogous to persons. Name match is on companyName, and returned array of
    //      Company objects is ordered by companyName.
    // * 'locations' added 2019-11-18 JM, search is based on formatted form of location address
    public function search($q = '', User $user = null) {
        $results = array();
        $q = trim($q);
        if ($this->user) {
            if ($this->type == 'mypage') {
                // BEGIN ADDED 2019-12-11 JM
                // Return quickly on empty 'q' instead of doing a fruitless search
                if ($q) {
                // END ADDED 2019-12-11 JM
                    $results['jobNumbers'] = $this->searchJobs($q);
                    $results['persons'] = $this->persons($q);
                    $results['companys'] = $this->companys($q);
                    $results['locations'] = $this->searchLocations($q);
                // BEGIN ADDED 2019-12-11 JM
                // Return quickly on empty 'q' instead of doing a fruitless search
                } else {
                    $results['jobNumbers'] = array();
                    $results['persons'] = array();
                    $results['companys'] = array();
                    $results['locations'] = array();
                }
                // END ADDED 2019-12-11 JM
            }
        }

        return $results;
    } // END public function search

    // Abstracted 2019-11-18 JM
    // INPUT $q (query)
    //
    // Calls several methods of this class to fill out the return.
    // Returns an array of Job objects; three successive groupings.
    //    * For the first grouping, some portion of job.number matches the query string.
    //      These are in (job.name, job.number) order.
    //    * For the second grouping, some portion of searchable ancillary data matches the query string.
    //      These are also in (job.name, job.number) order, after all the objects from the first grouping.
    //    * For the third grouping, some portion of job.name matches the query string.
    //      These are also in (job.name, job.number) order, after all the objects from the first grouping.
    private function searchJobs($q) {
        $results = $this->jobNumber($q);

        $ancillarys = $this->jobAncillary($q);
        foreach ($ancillarys as $ancillary) {
            $results[] = $ancillary;
        }

        $names = $this->jobName($q);
        foreach ($names as $name) {
            $results[] = $name;
        }
        return $results;
    }

    // >>>00001 it would be nice to have a prose explanation of the regex, I haven't taken the time. - JM 2019-02
    private static function isPhoneNumber($str) {
        if ( preg_match( '/^[+]?([\d]{0,3})?[\(\.\-\s]?([\d]{3})[\)\.\-\s]*([\d]{3})[\.\-\s]?([\d]{4})$/', $str)) {
            return true;
        }
        return false;
    }

    // INPUT $q: search string. We return an empty array unless it is at least two characters, after trimming.
    // RETURN:
    //    * If $q appears to be a phone number, then we strip it down to digits before attempting a match.
    //      The array will consist only of Person objects for persons with matching phone numbers (could, in theory, have some extra
    //      digits at beginning or end), in (lastName, firstName) order.
    //    * If $q appears NOT to be a phone number, then we try to match on all or part of the first or last name.
    //      Again, return is Person objects in (lastName, firstName) order.
    private function persons($q) {
        $ret = array();
        $q = trim($q);
        if (strlen($q) > 2) {
            if (self::isPhoneNumber($q)) {
                $ph = preg_replace('/[^0-9]/', '', $q);

                $query = "select p.personId "; // CP - 2020-11-30 replace * with personId
                $query .= " from " . DB__NEW_DATABASE . ".personPhone pp ";
                $query .= " join " . DB__NEW_DATABASE . ".person p on pp.personId = p.personId ";
                $query .= " where pp.phoneNumber like '%" . $this->db->real_escape_string($ph) . "%' ";
                $query .= " order by p.lastName asc, p.firstName asc ";
                $result = $this->db->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $person = new Person($row['personId'], $this->user); // CP - 2020-11-30 -
                        $ret[] = $person;
                    }
                } else {
                    $this->logger->errorDb('1569443168',  'Search class persons method (self::isPhoneNumber($q) ==  true) => Select: Hard DB error', $this->db);
                }
            } else {
                $query = "select personId ";
                $query .= " from " . DB__NEW_DATABASE . ".person ";
                $query .= " where firstName like '%" . $this->db->real_escape_string($q) . "%' ";
                $query .= " or lastName like '%" . $this->db->real_escape_string($q) . "%' ";
                $query .= " order by lastName asc, firstName asc ";
                $result = $this->db->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $person = new Person($row['personId'], $this->user);
                        $ret[] = $person;
                    }
                } else {
                    $this->logger->errorDb('1569443169',  'Search class persons method (self::isPhoneNumber($q) !=  true) => Select: Hard DB error', $this->db);
                }
            }
        }

        return $ret;
    } // END private function persons

    // INPUT $q: search string.
    // RETURN an array that consists of Job objects for jobs where
    //   some portion of Job Number (e.g. 's1903087') matches the query string.
    //   These are in (job.name, job.number) order.
    private function jobNumber($q) {
        $ret = array();

        $query  = "SELECT jobId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".job ";
        $query .= "WHERE number LIKE '%" . $this->db->real_escape_string($q) .  "%'";
        $query .= " ORDER BY name, number;";

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $job = new Job($row['jobId'], $this->user);
                $ret[] = $job;
            }
        } else {
            // logging added 2019-11-13 JM
            $this->logger->errorDb('1573622573', "Error in Search::jobNumber('$q')", $this->db);
        }

        return $ret;
    }

    // Added 2019-11-13 JM.
    // >>>00006	Quite possibly we could move this (or an abstraction) to AncillaryData class - JM 2019-11-12
    // INPUT $q: search string.
    // RETURN an array that consists of Job objects for jobs where
    //   some ancillary data matches the query string.
    //   These are in (job.name, job.number) order.
    private function jobAncillary($q) {
        $ret = array();

        $query = "SELECT job.jobId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".job ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".jobAncillaryData ON jobAncillaryData.jobId = job.jobId ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".jobAncillaryDataType ON jobAncillaryData.jobAncillaryDataTypeId = jobAncillaryDataType.jobAncillaryDataTypeId ";
        $query .= "WHERE jobAncillaryDataType.searchable = 1 ";
        $query .= "AND jobAncillaryData.deleted IS NULL ";
        $query .= "AND (";
        $query .=     "(jobAncillaryDataType.underlyingdataTypeId = " . AncillaryData::UNDERLYING_DATA_TYPE_STRING . " ";
        $query .=     "AND jobAncillaryData.stringValue LIKE '%" . $this->db->real_escape_string($q) .  "%') ";
        $query .=     "OR (jobAncillaryDataType.underlyingdataTypeId = " . AncillaryData::UNDERLYING_DATA_TYPE_DATETIME . " ";
        $query .=     "AND jobAncillaryData.datetimeValue LIKE '%" . $this->db->real_escape_string($q) .  "%') ";
        if ($q == '0' || intval($q)) {
            $query .=     "OR (jobAncillaryDataType.underlyingdataTypeId = " . AncillaryData::UNDERLYING_DATA_TYPE_SIGNED_INTEGER . " ";
            $query .=     "AND jobAncillaryData.signedIntegerValue = " . intval($q). ") ";
            $query .=     "OR (jobAncillaryDataType.underlyingdataTypeId = " . AncillaryData::UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER . " ";
            $query .=     "AND jobAncillaryData.unsignedIntegerValue = " . intval($q). ")";
        }
        $query .= ")";
        $query .= " ORDER BY name, number;";

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $job = new Job($row['jobId'], $this->user);
                $ret[] = $job;
            }
        } else {
            $this->logger->errorDb('1573625017', "Error in Search::jobAncillary('$q')", $this->db);
        }

        return $ret;
    } // END private function jobAncillary

    // INPUT $q: search string.
    // RETURN an array that consists of Job objects for jobs where
    //   some portion of job name matches the query string.
    //   These are in (job.name, job.number) order.
    private function jobName($q) {
        $ret = array();

        $query  = " select jobId ";
        $query .= " from " . DB__NEW_DATABASE . ".job ";
        $query .= " where name like '%" . $this->db->real_escape_string($q) .  "%'";
        $query .= " order by name, number ";
        $result = $this->db->query($query);
        if ($result) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            while ($row = $result->fetch_assoc()) {
                $job = new Job($row['jobId'], $this->user);
                $ret[] = $job;
            }
        } else {
            $this->logger->errorDb('1573622573', "Error in Search::jobName('$q')", $this->db);
        }

        return $ret;
    }


    // INPUT $q: search string. We return an empty array unless it is at least two characters, after trimming.
    //    * If $q appears to be a phone number, then we strip it down to digits before attempting a match.
    //      The array will consist only of Company objects for persons with matching phone numbers (could, in theory, have some extra
    //      digits at beginning or end), in (companyName) order.
    //    * If $q appears NOT to be a phone number, then we try to match on all or part of the companyName.
    //      Again, return is Person objects in (companyName) order.
    private function companys($q) {
        $ret = array();
        $q = trim($q);
        if (strlen($q) > 2) {
            if (self::isPhoneNumber($q)) {
                $ph = preg_replace('/[^0-9]/', '', $q);

                $query = "select c.companyId ";
                $query .= " from " . DB__NEW_DATABASE . ".companyPhone cp ";
                $query .= " join " . DB__NEW_DATABASE . ".company c on cp.companyId = c.companyId ";
                $query .= " where cp.phoneNumber like '%" . $this->db->real_escape_string($ph) . "%' ";
                $query .= " order by c.companyName asc ";
                $result = $this->db->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $company = new Company($row['companyId'], $this->user);
                        $ret[] = $company;
                    }
                } else {
                    $this->logger->errorDb('1573622573', "Error in Search::companys('$q') self::isPhoneNumber==true", $this->db);
                }
            } else {
                $query = "select companyId ";
                $query .= " from " . DB__NEW_DATABASE . ".company ";
                $query .= " where companyName like '%" . $this->db->real_escape_string($q) . "%' ";
                $query .= " order by companyName asc ";

                $result = $this->db->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $company = new Company($row['companyId'], $this->user);
                        $ret[] = $company;
                    }
                } else {
                    $this->logger->errorDb('1573622573', "Error in Search::companys('$q') self::isPhoneNumber==false", $this->db);
                }
            }
        }

        return $ret;

    } // END private function companys

    // INPUT $q: search string. We return an empty array unless it is at least two characters, after trimming.
    // We look at address1, city, and name
    // This is public, because we don't have any other reasonable way to look for a location.
    public function searchLocations($q) {
        $ret = array();
        $q = strtolower(trim($q));

        $query = "SELECT locationId "; // JM 2020-03-17: was SELECT *, but we are trying to move away from ever passing a whole array to Location constuctor.
        $query .= " FROM " . DB__NEW_DATABASE . ".location ";
        $query .= " where address1 like '%" . $this->db->real_escape_string($q) . "%' ";
        // George 09-03-2021. Removed. Only by address1.
        //$query .= " OR city like '%" . $this->db->real_escape_string($q) . "%' ";
        //$query .= " OR name like '%" . $this->db->real_escape_string($q) . "%' ";
        $query .= " order by address1 asc ";

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ret[] = new Location(intval($row['locationId'])); // JM 2020-03-17: was new Location($row), but we are trying to move away from ever passing a whole array to Location constuctor.
            }
        } else {
            $this->logger->errorDb('1573622573', "Error in Search::searchLocations('$q') ", $this->db);
        }

        return $ret;
    } // END public function locations
}

?>