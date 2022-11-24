<?php
/* inc/classes/CompanyPerson.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the CompanyPerson table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well,
especially for managing contacts.
CompanyPerson is something of an auxiliary table (more precisely, a cross table) itself, so this
also provides access to the related Company and Person objects, and

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular company.
* Public functions:
** __construct($id = null, User $user = null)
** getName()
** getCompanyPersonId()
** getCompanyId()
** getPersonId()
** getArbitraryTitle()
** getBlockException()
** getCompany()
** getPerson()
** getContacts()
** update($val)
** static deleteCompanyPerson()
** static addCompanyPerson()
** addBlock($val, &$errCode=false)
** getBillingBlocks(&$errCode=false)
** public static function validate($companyPersonId, $unique_error_id=null)
*/
class CompanyPerson extends SSSEng {
    // The following correspond exactly to the columns of DB table CompanyPerson
    // See documentation of that table for further details
    private $companyPersonId;
    private $personId;
    private $companyId;
    private $arbitraryTitle;
    private $blockException;

    // Plus two objects
    private $company;
    private $person;

    // INPUT $id: May be either of the following:
    //  * a companyPersonId from the CompanyPerson table
    //  * an associative array which should contain an element for each columnn
    //    used in the CompanyPerson table, corresponding to the private variables
    //    just above.
    //  >>>00016: JM 2019-02-20: should certainly validate this input, only validation here
    //    is that we check companyPersonId before constructing company and person objects.
    // INPUT $user: User object, typically current user.
    //  >>>00023: JM 2019-02-18: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI).
    public function __construct($id = null,User $user = null) {
        parent::__construct($user);
        $this->load($id);

        if (intval($this->getCompanyPersonId())) {
            // Build Company & Person objects
            $this->company = new Company($this->getCompanyId());
            $this->person = new Person($this->getPersonId());
        }
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {
        if (is_numeric($val)) {
            // Read row from DB table CompanyPerson
            $query = "SELECT companyPersonId, companyId, personId, arbitraryTitle, blockException ";
            $query .= "FROM " . DB__NEW_DATABASE . ".companyPerson ";
            $query .= "WHERE companyPersonId = " . intval($val) . ";";

            $result = $this->db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.

                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();

                    $this->setCompanyPersonId($row['companyPersonId']);
                    $this->setCompanyId($row['companyId']);
                    $this->setPersonId($row['personId']);
                    $this->setArbitraryTitle($row['arbitraryTitle']);
                    $this->setBlockException($row['blockException']);
                } else {
                    $this->logger->errorDb('1594218072', "Invalid companyPersonId", $this->db);
                }
            } else {
                $this->logger->errorDb('1594218162', "Hard DB error", $this->db);
            }
              // haven't noted each instance.
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from
            //  input associative array
            $this->setCompanyPersonId($val['companyPersonId']);
            $this->setCompanyId($val['companyId']);
            $this->setPersonId($val['personId']);
            $this->setArbitraryTitle($val['arbitraryTitle']);
            $this->setBlockException($val['blockException']);
        }
    } // END private function load

    // RETURN a formatted name string (company + person)
    // Reworked 2020-01-14 JM & again 2020-10-14
    public function getName() {
        if ($this->company && $this->person) {
            return $this->company->getCompanyName() . '&nbsp;&nbsp;/&nbsp;&nbsp;' . $this->person->getFormattedName();
        } else if ($this->company) {
            $this->logger->error2('637285188542295356', "Person object is null for company ". $this->company->getCompanyName() .
                ', companyPersonId ' . $this->getCompanyPersonId());
            return $this->company->getCompanyName() . '&nbsp;&nbsp;/&nbsp;&nbsp;INVALID PERSON';
        } else if ($this->person) {
            $this->logger->error2('637285188542295357', "Company object is null for person ". $this->person->getFormattedName().
                ', companyPersonId ' . $this->getCompanyPersonId());
            return ', INVALID COMPANY&nbsp;&nbsp;/&nbsp;&nbsp;' . $this->person->getFormattedName();
        } else {
            $this->logger->error2('637285188542295358', "Company and person objects are null, companyPersonId " . $this->getCompanyPersonId());
            return ', INVALID COMPANY&nbsp;&nbsp;/&nbsp;&nbsp;INVALID PERSON';
        }
    }

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getCompanyPersonId();
    }

    // ------ private "set" functions should be largely self-explanatory ------
    // >>>00016, >>>00002: all probably should validate (legitimate foreign keys,
    //  strings of legal length) & log if invalid
    // INPUT $val is primary key
    private function setCompanyPersonId($val) {
        if(($val != null) && (is_numeric($val)) && ($val >=1)){
            $this->companyPersonId = intval($val);
        } else {
            $this->logger->error2("637286928189098666", "Invalid input for companyPersonId : [$val]" );
        }
    }

    // INPUT $val is foreign key into Company table
    private function setCompanyId($val) {
        if( Company::validate($val)) { // condition clarified 2020-10-14 JM
            $this->companyId = intval($val);
        } else {
            $this->logger->error2("637286934189827467", "Invalid input for companyId : [$val]" );
        }

    }

    // INPUT $val is foreign key into Person table
    private function setPersonId($val) {
        if( Person::validate($val) ) {  // condition clarified 2020-10-14 JM
            $this->personId = intval($val);
        } else {
            $this->logger->error2("637286935836207262", "Invalid input for personId : [$val]" );
        }
    }

    private function setArbitraryTitle($val) {
        $val = truncate_for_db($val, 'ArbitraryTitle', 64, '637286940598656972'); // truncate for db.
        $this->arbitraryTitle = $val;
    }

    // blockException is a comma-separated list of Job Numbers (so it's a major
    //  denormalization to handle it within the table). If DB table billingBlock
    //  says not to bill this companyPerson, we can use this to track individual
    //  jobs that *should* be billed.
    private function setBlockException($val) {
        if ($val) {
            $blockException = $val;
            $blockException = preg_replace('/\s+/', '', $blockException); // Remove all whitespace (including tabs and line ends).
            // NOTE that we do not truncate (so that we won't accidentally clip this in the middle of a Job Number). In practice,
            //  this should never be even 200 bytes, but if somehow it was over 1024 bytes, we'll just call that an error and refuse it.

            if (strlen($blockException) < 1024) {

                $blockException = explode(",", $blockException);

                foreach($blockException as $key => $block) {
                    if (!preg_match('/^s[0-9]{7}$/', trim($block))) { // bad length and bad format.
                        $this->logger->error2("637286951740116006", "Invalid input for BlockException, not a Job number : [$blockException[$key]]" );
                        // >>>00001 JM 2020-11-13: The next line is certainly wrong, to the point where I have no idea what even was intended. Please
                        // fix & I'll review again.
                        $blockException = [];
                        break;
                        // Cristi 2020-11-24 - In case one of the jobs is not in the correct format all of them are refused.
                        //      The break was lost during the merge process.
                    }
                }

                $blockException = implode(",", $blockException); //string again.
                $this->blockException = $blockException; // if empty array, means empty string after implode. Default database.

            } else {
                $this->logger->error2("637400157197711981", "Invalid input for BlockException. Limit of 1024 characters exceeded. Length: " . strlen($blockException));
                $this->blockException = ""; //empty string, default database
            }
        } else {
            $this->blockException = $val;
        }
    }

    // RETURN primary key
    public function getCompanyPersonId() {
        return $this->companyPersonId;
    }

    // RETURN foreign key to Company table
    public function getCompanyId() {
        return $this->companyId;
    }

    // RETURN foreign key to Person table
    public function getPersonId() {
        return $this->personId;
    }

    // RETURN arbitraryTitle (e.g. "President", "Owner", "Receptionist")
    public function getArbitraryTitle() {
        return $this->arbitraryTitle;
    }

    // RETURN comma-separated list of Job Numbers (so it's a major
    //  denormalization to handle it within the table). If DB table billingBlock
    //  says not to bill this companyPerson, we can use this to track individual
    //  jobs that *should* be billed.
    public function getBlockException() {
        return $this->blockException;
    }

    // RETURN Company object corresponding to companyId
    public function getCompany() {
        return $this->company;
    }

    // RETURN Person object corresponding to personId
    public function getPerson() {
        return $this->person;
    }

    /**
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array of associative arrays, each of which has elements:
    * 'type': "Phone", "Location", or "Email".
    * 'dat': phone number, formatted location address, or email address.
    * 'typeError' : If there was an error, this lets us say what the error was in.
                    Lets caller give a possibly more meaningful error message.
    **/
    public function getContacts(&$errCode=false) {
        $errCode = false;
        $ret = array();

        $query = "SELECT ";
        $query .= "cpc.companyPersonContactId";
        $query .= ", cpc.companyPersonId";
        $query .= ", cpc.companyPersonContactTypeId";
        $query .= ", cpc.id";
        $query .= ", cpct.typeName ";
        $query .= "FROM " . DB__NEW_DATABASE . ".companyPersonContact cpc ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".companyPersonContactType cpct ON cpc.companyPersonContactTypeId = cpct.companyPersonContactTypeId ";
        $query .= "WHERE companyPersonId = " . intval($this->getCompanyPersonId()) . ";";

        $contacts = array();

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $contacts[] = $row;
            }
        } else {
            $errCode=true;
            $this->logger->errorDb('1594218307', "Hard DB error", $this->db);
        }

        // NOTE that denormalization in DB means that $contacts[$i]['id'] is
        //  meaningful only in conjunction with $contacts[$i]['typeName'] to say
        //  what table it refers to.
        foreach ($contacts as $contact) {
            $query = '';
            $type = '';
            $typeError = ''; // used for different query failures. Useful for user messages.

            if ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILPERSON) {
                $query = "SELECT emailAddress " .
                         "FROM " . DB__NEW_DATABASE . ".personEmail " .
                         "WHERE personEmailId = " . intval($contact['id']) . ";";

                $type = 'Email';
                $dat = '';

                $result = $this->db->query($query);
                if ($result) {
                    // Can only be one row: personEmailId is primary key
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $dat = $row['emailAddress'];
                    } else {
                        $this->logger->errorDb('1594218728', "Invalid personEmailId", $this->db);
                    }
                } else {
                    $typeError = 'Person Email';
                    $this->logger->errorDb('1594218788', "Hard DB error", $this->db);
                }
            } else if ($contact['companyPersonContactTypeId'] == CPCONTYPE_LOCATION) {
                $query = "SELECT locationId " .
                         "FROM " . DB__NEW_DATABASE . ".location " .
                         "WHERE locationId = " . intval($contact['id']) . ";";

                $type = 'Location';
                $dat = '';

                $result = $this->db->query($query);
                if ($result) {
                    // Can only be one row: locationId is primary key
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $location = new Location($row['locationId']);
                        $dat = $location->getFormattedAddress();
                    } else {
                        $this->logger->errorDb('1594218999', "Bad locationId", $this->db);
                    }
                } else {
                    $typeError = 'Location';
                    $this->logger->errorDb('1594219007', "Hard DB error", $this->db);
                }
            } else if ($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONEPERSON) {
                $query = "SELECT phoneNumber " .
                         "FROM " . DB__NEW_DATABASE . ".personPhone " .
                         "WHERE personPhoneId = " . intval($contact['id']). ";";

                $type = 'Phone';
                $dat = '';

                $result = $this->db->query($query);
                if ($result) {
                    // Can only be one row: personPhoneId is primary key
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $dat = $row['phoneNumber'];
                    } else {
                        $this->logger->errorDb('1594219143', "Invalid personPhoneId", $this->db);
                    }
                } else {
                    $typeError = 'Person Phone';
                    $this->logger->errorDb('1594219163', "Hard DB error", $this->db);
                }
            } else if ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILCOMPANY) {
                $query = "SELECT emailAddress " .
                         "FROM " . DB__NEW_DATABASE . ".companyEmail " .
                         "WHERE companyEmailId = " . intval($contact['id']) . ";";

                $type = 'Email';
                $dat = '';

                $result = $this->db->query($query);
                if ($result) {
                    // Can only be one row: companyEmailId is primary key
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $dat = $row['emailAddress'];
                    } else {
                        $this->logger->errorDb('1594219222', "Invalid companyEmailId", $this->db);
                    }
                } else {
                    $typeError = 'Company Email';
                    $this->logger->errorDb('1594219253', "Hard DB error", $this->db);
                }
            } else if ($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONECOMPANY) {
                $query = "SELECT phoneNumber " .
                         "FROM " . DB__NEW_DATABASE . ".companyPhone " .
                         "WHERE companyPhoneId = " . intval($contact['id']) . ";";

                $type = 'Phone';
                $dat = '';

                $result = $this->db->query($query);
                if ($result) {
                    // Can only be one row: companyPhoneId is primary key
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $dat = $row['phoneNumber'];
                    } else {
                        $this->logger->errorDb('1594219419', "Invalid companyPhoneId", $this->db);
                    }
                } else {
                    $typeError = 'Company Phone';
                    $this->logger->errorDb('1594219478', "Hard DB error", $this->db);
                }
            }

            // Regardless of how we got $type & $dat, save them for return.
            if (strlen($type)) {
                $contact['type'] = $type;
                $contact['typeError'] = $typeError;
                $contact['dat'] = $dat;
                $ret[] = $contact;
            }
        }

        return $ret;
    } // END public function getContacts

    // Despite the generic name, this just updates 'blockException'.
    // INPUT $val - associative array whose only significant element is:
    //   * 'blockException': a comma-separated list of Job Numbers. Any blanks
    //     before & after commas will be trimmed before insertion.
    // RETURN true on success, false on failure
    public function update($val) {
        if (is_array($val)) {
            if (isset($val['blockException'])) {
                // We take the comma-separated list apart, strip any white space, and rebuild the list before saving,
                // is done in the Setter.
                $this->setBlockException($val['blockException']);
            }
            if (isset($val['arbitraryTitle'])) {
                $this->setArbitraryTitle($val['arbitraryTitle']);
            }

            return $this->save();

        } else {
            $this->logger->error2('637285227446049618', 'update => expected array as input, got something not an array ');
            return false;
        }
    } // END public function update

    // UPDATEs same field handled by public function update
    // RETURN true on success, false on failure
    private function save() {
        $query = "UPDATE " . DB__NEW_DATABASE . ".companyPerson SET ";
        $query .= "blockException = '" . $this->db->real_escape_string($this->getBlockException()) . "' ";
        $query .= ", arbitraryTitle = '" . $this->db->real_escape_string($this->getArbitraryTitle()) . "' ";
        $query .= " WHERE companyPersonId = " . intval($this->getCompanyPersonId()) . ";";

        $result = $this->db->query($query);

        if(!$result) {
            $this->logger->errorDb('637285973512534337', 'update CompanyPerson: Hard DB error', $this->db);
            return false;
        }
        return true;
    }


     /**
    * George ADDED 2020-06-12.
    * Static method.
    * Add association between a Company and Person, entry in table companyPerson.
    *
    * @param integer $companyId, primary key in company table;
    * @param integer $personId, primary key in person table;
    * @return boolean true on success, false on failure.
    *
    */

    public static function addCompanyPerson($companyId, $personId) {
        // George ADDED 2020-04-29. We use global db, if instance doesn't exist make a new one.
        global $db, $logger;
        CompanyPerson::loadDB($db);

        $query = " INSERT INTO " . DB__NEW_DATABASE . ".companyPerson (companyId, personId) VALUES (";
        $query .= " " . intval($companyId) . " ";
        $query .= " ," . intval($personId) . ") ";

        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb('637275571968872331', "Hard error", $db);
            return false;
        }

        return true;
    }

     /**
    * George IMPROVED 2020-04-29.
    * Static method.
    * Deletes association between a Company and Person entry in table companyPerson.
    *
    * @param integer $companypersonId, primary key in companyPerson table;
    * @param string $entity depends on where this method is called, expected values: "company" or "person";
    * @param string $name actual name of the company or person;
    * @return array $ret the first index is a Boolean (true on success, false on failure). The second is text and contains an error message on failure
    *    and a description of what we did on success.
    *  So a typical call to this should be of the form
    *      list($success, $errorMsg) = CompanyPerson::deleteCompanyPerson($companypersonId, $entity, $name);
    */
    public static function deleteCompanyPerson($companypersonId, $entity, $name="") {
        global $db, $logger;
        CompanyPerson::loadDB($db);

        $success = false;
        $error = "";

        // Error message if companypersonId not positive integer.
        if (!is_int($companypersonId) || $companypersonId <= 0) {
            $logger->error2('637286900395142013', 'deleteCompanyPerson => companypersonId not valid: ' . $companypersonId);
            $error = "Incorrect parameter";
        } else if ( !canDelete('companyPerson', 'companypersonId', $companypersonId) ) {
            $logger->error2('637286900395142014', "Could not delete companypersonId $companypersonId because this relationship is still in use.");
            $error = "Could not delete '" . $name . "' because this relationship is still in use.";
        } else {
        /* We don't have any incoming referecnse to this companyPersonId value, so it's safe to delete the association of this
           person to this company. */
            $query = "DELETE ";
            $query .= "FROM " . DB__NEW_DATABASE . ".companyPerson ";
            $query .= "WHERE companyPersonId = " . intval($companypersonId) . " ";

            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('637217665790457962', 'deleteCompanyPerson', $db);
                $error = 'deleteCompanyPerson method failled';
            } else {
                $error = $name . " is no longer linked to this " . $entity;
                $success = true;
            }
        }

        $ret[] = $success;
        $ret[] = $error;
        return $ret;  //($success, $errorMsg).
    } // END public function deleteCompanyPerson


    /**
    * add billingBlock in billingBlock table.
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @param array $val : associative array with the following members; inserts a new row in DB table billingBlock accordingly:
    *  'billingBlockTypeId': NOTE that this can be BILLBLOCK_TYPE_REMOVEBLOCK, so this call can effectively be an "unblock" rather than a "block".
    *  'note'
    *  'personId' // the current logged-in user as the person inserting this.
    * @return boolean true on success.
    *
    * NOTE that this relies on caller to have already validated $val.
    */
    public function addBlock($val, &$errCode=false) {
        $errCode = false;

        if (!is_array($val)) {
            $this->logger->error2('637384591098246885', 'addBlock => Value is not an array: ' . $val);
            return false;
        }

        if (!Person::validate(intval($val['personId']))) {
            $this->logger->error2('637393088437212086', 'addBlock => \$val[\'personId\'] not integer: ' .
                (is_scalar($val['personId']) ? $val['personId'] : 'not scalar'));
            return false;
        }

        $note = isset($val['note']) ? $val['note'] : '';
        $note = truncate_for_db ($note, 'Billing Block note', 1024, '637321400347087464'); //  handle truncation when an input is too long for the database.

        $billingBlockTypeId = isset($val['billingBlockTypeId']) ? intval($val['billingBlockTypeId']) : 0;

        if (($billingBlockTypeId != BILLBLOCK_TYPE_NONPAY_PREVIOUS) && ($billingBlockTypeId != BILLBLOCK_TYPE_REMOVEBLOCK)) {
            $this->logger->error2('637322206625102713', 'addBlock => billingBlockTypeId must be either ' .
                'BILLBLOCK_TYPE_NONPAY_PREVIOUS (' . BILLBLOCK_TYPE_NONPAY_PREVIOUS . ') or ' .
                'BILLBLOCK_TYPE_REMOVEBLOCK (' . BILLBLOCK_TYPE_REMOVEBLOCK . '); we got incorrect value: ' . $billingBlockTypeId);
            return false;
        }

        $query = "INSERT INTO  " . DB__NEW_DATABASE . ".billingBlock (billingBlockTypeId, companyPersonId, note, personId) VALUES(";
        $query .= " " . intval($billingBlockTypeId);
        $query .= ", " . intval($this->getCompanyPersonId());
        $query .= ", '" . $this->db->real_escape_string($note) . "'";
        $query .= ", " . intval($val['personId']) . ");";

        $result = $this->db->query($query);

        if (!$result)  {
            $this->logger->errorDb('637322199816002978', "addBlock => Hard DB error", $this->db);
            $errCode=true;
        }

        return true;
    }

    /**
    * Get billingBlocks from billingBlock table, in reverse chronological order.
    * @param bool $errCode, variable pass by reference. Default value is false.
    * @return array $blocks. Associative array with the following members; selected from DB table billingBlock accordingly:
        *'billingBlockId'
        *'billingBlockTypeId': NOTE that this can be BILLBLOCK_TYPE_REMOVEBLOCK, so this call can effectively be an "unblock" rather than a "block".
        *'companyPersonId'
        *'note'
        *'personId' - who inserted
        *'inserted' - when inserted
    *
    */
    public function getBillingBlocks(&$errCode=false) {
        $errCode = false;
        $blocks = array();

        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".billingBlock ";
        $query .= "WHERE companyPersonId = " . intval($this->getcompanyPersonId()) . " ";
        $query .= "ORDER BY inserted DESC;";

        $result = $this->db->query($query);

        if (!$result)  {
            $this->logger->errorDb('637384655843575280', "Select billingBlocks => Hard DB error", $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $blocks[] = $row;
            }
        }

        return $blocks;
    }



    // Return true if the id is a valid companyPersonId, false if not
    // INPUT $companyPersonId: companyPersonId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($companyPersonId, $unique_error_id=null) {
        global $db, $logger;
        CompanyPerson::loadDB($db);

        $query = "SELECT companyPersonId " .
                 "FROM " . DB__NEW_DATABASE . ".companyPerson " .
                 "WHERE companyPersonId=$companyPersonId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578686486', "Hard error", $db);
            return false;
        }

        return !!($result->num_rows); // convert to boolean
    }
}

?>
