<?php
/* inc/classes/Customer.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the Customer table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.
NOTE: as of 2019-02 there is only one customer, SSS itself.

* Extends SSSEng.
* Public functions:
** __construct($id = null)
** setCustomerName($val)
** setDomain($val)
** setShortName($val)
** setCompanyId($val)
** genCode($len = 0) - implicitly public
** getCustomerId()
** getParentId(
** getCustomerName()
** getDomain()
** getShortName()
** getCompanyId()
** buildLink($urionly = false)
** addCompany($companyName)
** addCompanyWithOptions($companyNameSrc, $renameName, $paddingLeft, $paddingRight)
** addPerson($username)
** addJob()
** isEmployee($personId)
** getEmployees($filter = 0)
** getAllEors()
** getCustomerPersonFromInitials($initials)
** getCustomerPersonFromPersonId($personId)
** usernameExists($username)
** companyNameExists($companyName)
** getInvoiceApprovers()

** public static function validate($customerId, $unique_error_id=null)

*/

class Customer extends SSSEng {
    // The following correspond exactly to the columns of DB table Customer
    // See documentation of that table for further details.
    private $customerId;
    private $parentId;
    private $customerName;
    private $domain;
    private $shortName;
    private $companyId; /* [begin Martin comment]
                            This was a bit of a kludge to get a companyId associated with a customer
                             for the purpose of auto-assigning team members to a task .. see about it in WorkOrderTask.class.php.
                            Also using it when autocompleting a person list for the company associated with the customer.
                           [end Martin comment]
                        */

    /* Constructor optionally takes identification of customer,
       which can take one of three forms:
       1) just a customerId
       2) an associative array containing members corresponding to
          the columns in the customer DB table.
       3) a string representing the domain associated with this customer.
       Doesn't really make any sense to let $id default to null, you get a
       useless object if you let that happen.
    */
    public function __construct($id = null, $user = null) {
        parent::__construct($user);
        $this->load($id);
    }

    private function load($val) {
        // INPUT $val here is input $id for constructor.
        if (is_numeric($val)) {
            // Read row from DB table Customer
            $query = " select c.* ";
            $query .= " from " . DB__NEW_DATABASE . ".customer c  ";
            $query .= " where c.customerId = " . intval($val);

            $result = $this->db->query($query);

            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.
                    // Set all of the private members that represent the DB content.
                    $row = $result->fetch_assoc();

                    $this->setCustomerId($row['customerId']);
                    $this->setParentId($row['parentId']);
                    $this->setCustomerName($row['customerName']);
                    $this->setDomain($row['domain']);
                    $this->setShortName($row['shortName']);
                    $this->setCompanyId($row['companyId']);
                } else {
				    $this->logger->errorDb('637383670895564183', "Invalid customerId", $this->db);
				}
            } else {
			    $this->logger->errorDb('637383670608449468', "Hard DB error", $this->db);
			}
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from
            //  input associative array
            $this->setCustomerId($val['customerId']);
            $this->setParentId($val['parentId']);
            $this->setCustomerName($val['customerName']);
            $this->setDomain($val['domain']);
            $this->setShortName($val['shortName']);
            $this->setCompanyId($val['companyId']);
        } else {
            // Read row from DB table Customer, based on domain rather than primary key.
            $query = " select c.* ";
            $query .= " from " . DB__NEW_DATABASE . ".customer c ";
            $query .= " where c.domain = '" . $this->db->real_escape_string($val) . "'";

            $result = $this->db->query($query);

            if ($result) {
                // Since the domain should be unique, we know there will be exactly one row.
                // Set all of the private members that represent the DB content.
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();

                    $this->setCustomerId($row['customerId']);
                    $this->setParentId($row['parentId']);
                    $this->setCustomerName($row['customerName']);
                    $this->setDomain($row['domain']);
                    $this->setShortName($row['shortName']);
                    $this->setCompanyId($row['companyId']);
                } else {
				    $this->logger->errorDb('637383672467741538', "Invalid domain", $this->db);
				}
            } else {
			    $this->logger->errorDb('637383672252481149', "Hard DB error", $this->db);
			}
        }
    } // END private function load

    // INPUT $val: primary key
    private function setCustomerId($val) {
        if(($val != null) && (is_numeric($val)) && ($val >=1)){
            $this->customerId = intval($val);
        } else {
            $this->logger->error2("637383745963073383", "Invalid input for CustomerId : [$val]" );
        }
    }

    // INPUT $val: primary key of any parent customer, 0 otherwise
    private function setParentId($val) {
        $this->parentId = intval($val);
    }

    // INPUT $val: string, customer name, e.g. "Sound Structural Solutions"
    public function setCustomerName($val) {
        $val = truncate_for_db($val, 'CustomerName', 128, '637383673241492458'); // truncate for db.
		$this->customerName = $val;
    }

    // INPUT $val: string, domain name, e.g. 'ssseng.com'
    public function setDomain($val) {
        $val = truncate_for_db($val, 'Domain', 128, '637383673672268065'); // truncate for db.
		$this->domain = $val;
    }

    // INPUT $val: string, shorter variant of customer name, e.g. 'sss'
    public function setShortName($val) {
        $val = truncate_for_db($val, 'ShortName', 32, '637383674104885876'); // truncate for db.
        $this->shortName = $val;
    }

    // INPUT $val: foreign key into DB table Company, the row there representing
    //  this customer as a company.
    private function setCompanyId($val) {
        if(Company::validate($val)) {
            $this->companyId = intval($val);
        } else {
            $this->logger->error2("637383744812087779", "Invalid input for companyId : [$val]" );
        }
    }

    // RETURN primary key
    public function getCustomerId() {
        return $this->customerId;
    }

    // RETURN primary key of any parent customer, 0 otherwise
    public function getParentId() {
        return $this->parentId;
    }

    // RETURN full customer name
    public function getCustomerName() {
        return $this->customerName;
    }

    // RETURN customer domain (e.g. 'ssseng.com')
    public function getDomain() {
        return $this->domain;
    }

    // RETURN short customer name (e.g. 'sss')
    public function getShortName() {
        return $this->shortName;
    }

    // RETURN foreign key into DB table Company, the row there representing
    //  this customer as a company.
    public function getCompanyId() {
        return $this->companyId;
    }

    // >>>000029 Implicitly public; should be static, probably private, certainly explicitly public or private in any case.
    // Generate a pseudorandom alphanumeric string (capital letters only) of specified length (default 5).
    // This is the pseudorandom salt for a hash.
    function genCode($len = 0) {
        if (!intval($len)){
            $len = 5;
        }

        $str = '';

        for ($i = 0; $i < $len; ++$i){
            $chars = explode(",","A,C,D,E,F,G,H,J,K,L,M,N,P,Q,R,T,U,V,W,X,Y,Z,2,3,4,5,6,7,8,9");
            shuffle($chars);
            $str .= $chars[0];
        }

        return $str;
    }

    // Preventing and Log an error if someone will try to use this function.
    public function buildLink($urionly = false) {
        $this->logger->error2('637395778027854732', "We can not used this method. We don't have an 'customer' Url to redirect to.");
        return REQUEST_SCHEME . '://' . HTTP_HOST . '/';
    }

    // INPUT $companyName. If this company does not already exist in the DB
    //  for this customer, insert it. Then return its ID. Returns 0 on failure.
    public function addCompany($companyName) {
        return $this->addCompanyWithOptions($companyName, false, '', '');
    }

    // Abstracted from addCompany for release 2020-2, and a bunch of capabilities added.
    // INPUT $companyName, $renameName, $paddingLeft, $paddingRight
    // If this company does not already exist in the DB for this customer, insert it.
    // Else, if there is a company with the initial $companyName and if $renameName is true,
    // we add a numerical suffix to avoid collisions.
    // Optionally, the initial $companyName will be padded left and/or right with the given
    // input parameters; we use this for the creation of bracket companies.
    // RETURN: On successful insert in the DB, return the ID of the new company. Returns 0 on failure.
    //  >>>00001 JM 2020-03-12 apparently can also now return error codes, this should be documented! but let's wait till we discuss the issue.
    //  IN GENERAL, though, the returns a function can give should be ACCURATELY documented in the function header

    // Special case added 2020-06-05 JM for http://bt.dev2.ssseng.com/view.php?id=164 (Don't allow adding or removing a person from a bracket company):
    // If $paddingLeft == '[' and $paddingRight == ']', then we assume this is a "bracket company" (see http://sssengwiki.com/Person%2C+Employee%2C+User%2C+and+all+that
    //  for an explanation of that term.)
    public function addCompanyWithOptions($companyNameSrc, $renameName, $paddingLeft, $paddingRight) {
        $companyId = 0;

        $companyNameSrc = truncate_for_db ($companyNameSrc, 'company Name Src', 128, '637310111209348966'); // CP - 2020-11-30 replace substr with truncate for db

        // Determine whether we already have a row in the Company table for this customer + company.
        // >>>00001 Presumes $companyName is a sufficiently unique identifier, I wonder about that decision. - JM 2019-02-20
        // 2020-03-12: Seems we now at least partly address it with $renameName, assuming this is used under
        //  the correct discipline.

        // We have to make sure this will not collide with another company
        // 2 is the smallest integer we will append for disambiguation.
        $suffix = 1; // but inside the loop, if it's 1, we will skip using it.
        $companyId = 0;
        do {
            $companyName = $companyNameSrc;
            if ($suffix > 1) {
                $companyName = "$companyName $suffix";
            }
            if ($paddingLeft != '') {
                $companyName = "$paddingLeft$companyName";
            }
            if ($paddingRight != '') {
                $companyName = "$companyName$paddingRight";
            }

            $result = $this->companyNameExists($companyName);
            if ($result === NULL) {
                $companyId = DB_EXECUTION_ERR;
                break;
            } else if ($result) {
                if (!$renameName) {
                    $companyId = DB_ROW_ALREADY_EXIST_ERR;
                    break;
                } else {
                    // already using this company name
                    ++$suffix;
                }
            }
        } while ($result); // NOTE that we can also exit this loop via a break

        if ($companyId == 0) {
            // only way $companyId can be nonzero at this point is to represent an error

            // $bracketCompany introduced 2020-06-05 JM for http://bt.dev2.ssseng.com/view.php?id=164
            $bracketCompany = $paddingLeft == '[' && $paddingRight == ']' ? 1 : 0;
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".company (customerId, companyName, isBracketCompany";
            $query .= ") VALUES (";
            $query .= intval($this->getCustomerId()) . ", ";
            $query .= "'" . $this->db->real_escape_string($companyName) . "', ";
            $query .= $bracketCompany;
            $query .= ");";

            $result = $this->db->query($query);
            if ($result) {
                $companyId = $this->db->insert_id;
            } else {
                $companyId = DB_EXECUTION_ERR;
            }
        }

        return intval($companyId);
    } // END public function addCompanyWithOptions

    /**
     * check if username already exists in database, for the current customer
     *
     * @param string $username name of the user
     * @return bool true is username exists, false otherwise or null if database error
     */
    public function usernameExists($username){
        return entityExists(
                            "person",
                            "username", $username,
                            "customerId = " . $this->customerId);
    }

    /**
     * check if companyname already exists in database, for the current customer
     *
     * @param string $companyname name of the company
     * @return bool true is companyname exists, false otherwise or null if database error
     */
    public function companyNameExists($companyName){
        return entityExists(
                            "company",
                            "companyName", $companyName,
                            "customerId = " . $this->customerId);
    }


    // INPUT $userName, must be an email address.
    // INPUT $firstName (optional)
    // INPUT $lastName (optional)
    // If this person does not already exists in the DB for this customer, insert it. Then return its ID. Returns 0 on failure.
    public function addPerson($username, $firstName='', $lastName='') {
        $personId = 0; // in happy case, will be personId; can be error code.
        $jobs = array();
        $username = trim($username);
        $username = strtolower($username);
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        // Determine whether we already have a row in the Company table for this customer + email address.
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            // >>>00002 fails silently on invalid email address
            $personId = EMAIL_PATTERN_MISMATCH;
        } else {
            $exists = $this->usernameExists($username);

            if($exists === NULL) {
                $personId = DB_GENERAL_ERR;
            } else if (!$exists) {
                $secure = new SecureHash();
                $salt = '';

                // The following usefully generates a pseudorandom salt per person.
                // Prior to v2020-3, we were also placing the return of this in column 'password'
                //  of DB table 'person'. However, we never used that value, we always used column 'pass'.
                // 2020-05-04 JM: removing irrelevant DB column person.password.
                $encrypted = $secure->create_hash($this->genCode(10), $salt);

                // $query = " insert into " . DB__NEW_DATABASE . ".person (customerId, username, password, salt" . // REMOVED 2020-05-04 JM
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".person (customerId, username, salt" . // ADDED 2020-05-04 JM
                ($firstName ? ', firstName' : '') .
                ($lastName ? ', lastName' : '') .
                ") values (";
                $query .= " " . intval($this->getCustomerId());
                $query .= " ,'" . $this->db->real_escape_string($username) . "'";
                // $query .= " ,'" . $this->db->real_escape_string($encrypted) . "'";  // REMOVED 2020-05-04 JM
                $query .= " ,'" . $this->db->real_escape_string($salt) . "'";
                $query .= $firstName ? ( " ,'" . $this->db->real_escape_string($firstName) . "'") : '';
                $query .= $lastName ? ( " ,'" . $this->db->real_escape_string($lastName) . "'") : '';
                $query .= ');';

                $result = $this->db->query($query);
                if ($result) {
                    $personId = $this->db->insert_id;
                } else {
                    $personId = DB_EXECUTION_ERR;
                }
            } else {
                $personId = DB_ROW_ALREADY_EXIST_ERR;
            }
        }

        return intval($personId);
    } // END public function addPerson

    // Adds a new job for this customer. To that end, construct a
    //  Job Number (e.g. 'S0806003': always 'S', the first 4 digits are year & month,
    //  and it has to not conflict with other job numbers) and a
    //  "code" (e.g. 'J3F4V5N9'). Looks like we now have job number double
    //  as rwname, although historically they have been distinct.
    // RETURNs jobId. Can return a negative value on failure; if so, caller
    //  should pass that to Job::errorToText for interpretation.
    public function addJob() {
        $jobId = 0;

        // Job Number ---------
        // >>>00021 Scalability issue: the above is going to run into trouble if there are more than 999 jobs in a month.
        //  Especially an issue if SSS isn't the only customer! (The way this is written, Job Number is unique across
        //  customers; could rework that.)
        $year = date("y");
        $month = date("m");
        $monthlyJobNumber = 0;

        $query  = " select number ";
        $query .= " from ". DB__NEW_DATABASE . ".job ";
        $query .= " where number like 'S" . $year . $month  . "%' ";
        $query .= " and length(number) = 8 ";
        $query .= " order by number desc";
        $query .= " limit 1 "; // This line restored by JM 2020-03-12. We want the highest job number for this month. No reason to make
                               // PHP loop through a bunch of rows.

        $result = $this->db->query($query);
        if (!$result) {
            $jobId = DB_EXECUTION_ERR;
        } else {
            if ($row = $result->fetch_assoc()) { // JM 2020-03-12 turned this from "while" to "if", there should be only one jobNumber we care about: the latest.
                $n = substr($row['number'], -3); // extract only the last 3-digits representing the last used monthlyJobNumber
                $n = preg_replace("/[^0-9]/", "", $n);
                if (strlen($n) == 3) {
                    $monthlyJobNumber = intval($n);
                }
                else {
                    // else the record is corrupted, log it, >>>00032 maybe we should delete the bad record?
                    $this->logger->errorDb("637173740350339060",
                                        "Bad record in ". DB__NEW_DATABASE . ".job table with number: ". $row['number'] . ".",
                                        $this->db);
                    // JM 2020-03-12: I know the following isn't at all pretty >>>00032 (and we probably should revisit it)
                    //  but if we somehow ended up here, the database needs attention from a developer or other expert,
                    //  and we should not let them continue to create a new job in the corrupted DB.
                    $jobId = DB_CORRUPTED_DB;
                }
            } // else there are no records in this month and it remains 0

            if ($monthlyJobNumber < Job::MAX_JOBS_IN_MONTH) {
                $monthlyJobNumber++;
                $number = 's' . $year . $month . str_pad($monthlyJobNumber, 3, "0", STR_PAD_LEFT);
            } else {
                // if it ever reaches this point it's a big problem because the threshold for jobs in a month has been reached
                $jobId = NOT_AVAILABLE_VALUE;
            }
        }

        // "Code" ----------
        $code = '';
        if (!$jobId) {
            // Only way $jobId can be nonzero at this point is to represent an error.

            // anca: >>>00021 In time, the already used codes will increase the probability of collisions =>
            //       we should avoid the rare case of a loop with too many steps (like a pseudoinfinite loop)
            do {
                $code = generateCodeJobAndWorkOrder(7); // a pseudorandom "code" fitting a rather specific pattern, see called function for more.
                $code = 'J' . $code;
                $exists =  entityExists(
                    "job",
                    "code", $code);
                if ($exists === NULL) {
                    $jobId = DB_EXECUTION_ERR;
                }
                else if (!$exists) {
                    break; // happy case
                }
            } while (!$jobId); // that is, break on execution eror
        }

        // Now INSERT it -------
        if (!$jobId) {
            // Only way $jobId can be nonzero at this point is to represent an error.

            $query = "insert into " . DB__NEW_DATABASE . ".job (customerId, number, rwname, code) values (";
            $query .= " " . intval($this->getCustomerId()) . " ";
            $query .= " ,'" . $this->db->real_escape_string($number) . "' ";
            $query .= " ,'" . $this->db->real_escape_string($number) . "' ";
            $query .= " ,'" . $this->db->real_escape_string($code) . "') ";

            $result = $this->db->query($query);
            if ($result) {
                $jobId = $this->db->insert_id;
            } else {
                $jobId = DB_EXECUTION_ERR;
            }
        }

        return $jobId;

    } // END public function addJob

    // INPUT $personId
    // RETURN Boolean, true if this person is an employee of this customer (that is,
    //  if we can trace customer -> customerPerson -> person, starting from this
    //  customer and ending up at this person). Ignores termination date.
    public function isEmployee($personId) {
        $query  = "SELECT cp.customerPersonId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".customerPerson cp ";
        // $query .= "JOIN " . DB__NEW_DATABASE . ".person p ON cp.personId = p.personId "; // REMOVED 2020-07-03 JM, did nothing useful!
        $query .= "WHERE cp.customerId = " . intval($this->getCustomerId()) . " ";
        $query .= "AND cp.personId = " . intval($personId) . ";";

        $result = $this->db->query($query);
        if ($result) {
            if ($result->num_rows > 0){
                return true;
            }
        } else {
            $this->logger->errorDb('1593805774', 'Hard DB error', $this->db);
        }

        return false;
    } // END public function isEmployee

    // INPUT $filter: Optional. Can be set to 1 to limit this to *current* employees.
    // RETURNs an array of User objects for these users, ordered by (lastName, firstName);
    //  we play a bit fast and loose with the User objects, adding some properties that
    //  are not normally part of that class:
    //  * legacyInitials (perfectly current as of 2019 despite its name, and no plans to remove it)
    //  * terminationDate (distant future date for current employees)
    //  * employeeId
    //  * workerId
    //  * smsPerm - >>>00001 some sort of permissions thing for SMS messages, not well understood 2019-02-20 JM
    public function getEmployees($filter = 0, &$errCode=false) {
        $errCode=false;
        $ret = array();

        /* BEGIN REPLACED 2020-07-06 JM
        // SELECT * on a SQL JOIN, not really a good idea
        // Looks like we are looking for a specific set of columns, could just select for those.
        $query  = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".customerPerson cp ";
        $query .= " join " . DB__NEW_DATABASE . ".person p on cp.personId = p.personId ";
        $query .= " where cp.customerId = " . intval($this->getCustomerId()) . " ";
        if ($filter == 1) {
            $query .= " and cp.terminationDate > now() ";
        }
        $query .= " order by p.LastName, p.firstName ";
        END REPLACED 2020-07-06 JM
        */
        // BEGIN REPLACEMENT 2020-07-06 JM
        // The join with person allows us to order by bame
        // Also would mean we get no row if there was a failure of referential integrity for cp.personId
        $query  = "SELECT cp.personId, cp.legacyInitials, cp.terminationDate, cp.employeeId, cp.workerId, cp.smsPerm ";
        $query .= "FROM " . DB__NEW_DATABASE . ".customerPerson cp ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".person p ON cp.personId = p.personId ";
        $query .= "WHERE cp.customerId = " . intval($this->getCustomerId()) . " ";
        if ($filter == 1) {
            $query .= "AND cp.terminationDate > now() ";
        }
        $query .= "ORDER BY p.LastName, p.firstName;";
        // END REPLACEMENT 2020-07-06 JM. The code that follows is also cleaned up but specific changes aren't noted.

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $user = new User($row['personId'], new Customer($this->getCustomerId()));
                $user->legacyInitials = $row['legacyInitials'];
                $user->terminationDate = $row['terminationDate'];
                $user->employeeId = $row['employeeId'];
                $user->workerId = $row['workerId'];
                $user->smsPerm = $row['smsPerm'];

                $ret[] = $user;
            }
        } else {
            $this->logger->errorDB('1594058152', 'Hard error', $this->db);
            $errCode = true;
        }

        return $ret;
    } // END public function getEmployees

    // RETURN an array of the EORs for the customer, providing customerpersonId, personId, and
    //  first and last name, sorted by first and last name.
    // RETURNs false on error
    public function getAllEors() {
        $db = DB::getInstance();

        $query = "SELECT cp.customerPersonId, cp.personId, p.firstName, p.lastName " .
                 "FROM " . DB__NEW_DATABASE . ".customerPerson AS cp " .
                   "JOIN " . DB__NEW_DATABASE . ".person AS p " .
                   "ON cp.personId = p.personId " .
                 "WHERE cp.customerId = {$this->getCustomerId()} ".
                   "AND cp.isEor <> 0 " .
                 "ORDER BY p.firstName, p.lastName;";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1587060658', "Hard DB error getting EORs", $this->db);
            return false;
        }

        $ret = Array();
        while ($row = $result->fetch_assoc()) {
            $ret[] = $row;
        }

        return $ret;
    }


    // INPUT $initials - in the database these are called legacyInitials, but there is nothing "legacy" about them.
    // RETURN:
    //  * If there is no such customerPerson (employee) for this customer, return FALSE.
    //  * If there is exactly one match return customerPersonId (primary key in DB table customerPerson).
    //  * If there is more than one match -- shouldn't happen, but at least as of 2019-07-08 there is no constraint
    //    in the DB to prevent it -- return an array of customerPersonId (primary key in DB table customerPerson).
    public function getCustomerPersonFromInitials($initials) {
        $query = "SELECT customerPersonId FROM customerPerson WHERE legacyInitials = '$initials' AND customerId = $this->customerId";
        $result = $this->db->query($query);
        if ( ! $result ) {
            $this->logger->errorDb('637435540815040117', "Hard DB error", $this->db);
            return false;
        } else {
            $num_rows = $result->num_rows;
            if ($num_rows == 0) {
                return false;
            } else if ($num_rows == 1) {
                $row = $result->fetch_assoc();
                return $row['customerPersonId'];
            } else {
                $ret = Array();
                while ($row = $result->fetch_assoc()) {
                    $ret[] = $row['customerPersonId'];
                }
            }
            return $ret;
        }
    } // END public function getCustomerPersonFromInitials

    // INPUT $personId - Primary key into DB table ;person'
    // RETURN:
    //  * If there is no such customerPerson (employee) for this customer, return FALSE.
    //  * If there is exactly one match return customerPersonId (primary key in DB table customerPerson).
    //  * Any other case is an error; log it and return false.
    public function getCustomerPersonFromPersonId($personId) {
        $query = "SELECT customerPersonId FROM customerPerson WHERE personId = '$personId' AND customerId = $this->customerId;";
        $result = $this->db->query($query);
        if ( ! $result ) {
            $this->logger->errorDb('637435541309559456', "Hard DB error", $this->db);
            return false;
        } else {
            $num_rows = $result->num_rows;
            if ($num_rows == 0) {
                return false;
            } else if ($num_rows == 1) {
                $row = $result->fetch_assoc();
                return $row['customerPersonId'];
            } else {
                // >>>00002 should log error here
                return false;
            }
        }
    } // END public function getCustomerPersonFromPersonId


	// In v2020-3, we introduce the concept of certain people having permission to approve invoices.
	// This returns an array of customerPersonIds for those people; false on hard error
    public function getInvoiceApprovers() {
        $customerPersonIds = Array();
        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".permission ";
        $query .= "WHERE permissionIdName = 'PERM_APPROVE_INVOICES';";
        $result = $this->db->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row) {
                $approveInvoicePermissionId = $row['permissionId'];
            }
            $query = "SELECT customerPersonId, permissionString FROM " . DB__NEW_DATABASE . ".customerPerson ";
            $query .= "JOIN " . DB__NEW_DATABASE . ".person ON customerPerson.personId = person.personId ";
            $query .= "WHERE customerPerson.customerId = " . $this->getCustomerId() . ";";
            $result = $this->db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $permissionString = $row['permissionString'];
                    // We could validate that, but we're presuming a good $permissionString from the DB

                    // Look at the correct position in the permission string
                    $permissionLevel = substr($permissionString, $approveInvoicePermissionId, 1);

                    if ($permissionLevel == PERMLEVEL_ADMIN) {
                        $customerPersonIds[] = $row['customerPersonId'];
                    }
                }
            } else {
                $this->logger->errorDB('1590183458', 'Hard DB error', $this->db);
                return false;
            }
        } else {
            $this->logger->errorDB('1590183459', 'Hard DB error', $this->db);
            return false;
        }

        return $customerPersonIds;
    } // END public function getInvoiceApprovers


    // Return true if the id is a valid customerId, false if not
    // INPUT $customerId: customerId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($customerId, $unique_error_id=null) {
        global $db, $logger;
        Customer::loadDB($db);

        $ret = false;
        $query = "SELECT customerId FROM " . DB__NEW_DATABASE . ".customer WHERE customerId=$customerId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578691361', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }

} // END class Customer

?>