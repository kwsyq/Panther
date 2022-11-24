<?php
/* /inc/classes/Person.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the Person table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular person.
* Public functions:
** __construct($id = null, User $user = null)
** setFirstName($val)
** setMiddleName($val)
** setLastName($val)
** getPersonId()
** getCustomerId()
** getUserName()
** getFirstName()
** getMiddleName()
** getLastName()
** getPermissionString()
** getLegacyInitials()  George 2020-11-19. We need to removed this from this class. CustomerPerson class has the logic.
** getSmsPerms()
** getFormattedName()
** getName()
** getEmail()
** getEmails()
** getPhone($personPhoneId) //not used in the system
** addEmail($emailAddress)
** updateEmail($val)
** updateLocationType($val)
** getPhones()
** addPhone($val)
** updatePhone($val)
** getCompanyPersons
** getCompanies(&$errCode = false)
** deleteCompanyPerson()
** getLocations()
** getLocation($personLocationId = 0)
** update($val)
** toArray()

** public static function validate($personId, $unique_error_id=null)
** public static function errorToText($errCode)
*/


class Person extends SSSEng {
    // The following correspond exactly to the columns of DB table Person
    // See documentation of that table for further details.
    private $personId;
    private $customerId;
    private $username;
    private $firstName;
    private $middleName;
    private $lastName;
    private $permissionString;
    private $smsPerms;

    // NOT handled here:
    // * pass (encrypted password)
    // * salt (for encrypting password)
    // * resetCode (for resetting password)
    // * crumbs
    //private $legacyInitials; // [Martin comment] still not sure about how to deal with this.
                             // JM remarks 2019-02-26: this is in customerPerson, which is probably
                             //  where it belongs. So it is only meaningful in the context of
                             //  customer + person. Will all fall out when we confine this class to
                             //  deal with one customer at a time; right now SSS is the only customer.
                             // George 2020-11-19. We need to removed this from this class. CustomerPerson class has the logic.

// private $customer; // commented out by Martin some time before 2019, but we may want to revive it
                       // as a local copy of the global $customer.

    // INPUT $id: May be either of the following:
    //  * a personId from the Person table
    //  * an associative array which should contain an element for each columnn
    //    used in the Person table, corresponding to the private variables
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
    // INPUT $user: User object, typically current user.
    //  >>>00023: JM 2019-02-18: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI).
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->load($id);
    }

    /* [BEGIN MARTIN COMMENT]
     *
     *  do checks here to make sure that only people from a certain customer are viewed or whatever needs to happen here
     *
     * [END MARTIN COMMENT]
     *
     * >>>00004 JM 2019-02-26: the above seems to be a statement that this still needs to be done, not an assertion that
     *     it has been done. NOTE that once we want this to be customer-aware, there is always global $customer set in inc/config.php.
     */

    // INPUT $val here is input $id for constructor.
    private function load($val) {

        if (is_numeric($val)) {
            // Read row from DB table Person
            $query = "SELECT p.* ";
            $query .= "FROM " . DB__NEW_DATABASE . ".person p ";
            //$query .= " where p.customerId = " . intval($this->customer->getCustomerId()); // commented out by Martin before 2019
                                                            // JM: Looks like something he started thinking about, then backed off of.
            $query .= "WHERE p.personId = " . intval($val) . ";";

            $result = $this->db->query($query); // George 2020-06-16. Rewrite if statement.

            if(!$result) {
                $this->logger->errorDb('637280031904551517', 'load: Hard DB error', $this->db);
                return false;
            }

            if ($result->num_rows > 0) {
                // Since query used primary key, we know there will be exactly one row.

                // Set all of the private members that represent the DB content
                $row = $result->fetch_assoc();

                $this->setPersonId($row['personId']);
                $this->setCustomerId($row['customerId']);
                $this->setUsername($row['username']);
                $this->setFirstName($row['firstName']);
                $this->setMiddleName($row['middleName']);
                $this->setLastName($row['lastName']);
                $this->setPermissionString($row['permissionString']);
                $this->setSmsPerms($row['smsPerms']);
            } else {
                $this->logger->errorDb('637284228477187547', "No rows found", $this->db);
            }

        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from
            //  input associative array
            $this->setPersonId($val['personId']);
            $this->setCustomerId($val['customerId']);
            $this->setUsername($val['username']);
            $this->setFirstName($val['firstName']);
            $this->setMiddleName($val['middleName']);
            $this->setLastName($val['lastName']);
            $this->setPermissionString($val['permissionString']);
            $this->setSmsPerms($val['smsPerms']);
            // George 2020-11-19. We need to removed this from this class. CustomerPerson class has the logic.
            /*if (isset($val['legacyInitials'])) {
                $this->setLegacyInitials($val['legacyInitials']);
            }*/
            }
        }

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getPersonId();
    }

    // Set primary key
    // INPUT $val: primary key (personId)
    private function setPersonId($val) {
        if ( ($val != null) && (is_numeric($val)) && ($val >=1)) {
        $this->personId = intval($val);
        } else {
            $this->logger->error2("637411358230743844", "Invalid input for personId : [$val]" );
    }
    }

    // Set customerId
    // INPUT $val: foreign key to Customer table; as of 2019-02, only customer is SSS
    private function setCustomerId($val) {
        if (Customer::validate($val)) {
        $this->customerId = intval($val);
        } else {
            $this->logger->error2("637411358684841626", "Invalid input for CustomerId : [$val]" );
    }
    }

    // Set username
    // INPUT $val: username, normally an email address
    private function setUsername($val) {
        $val = truncate_for_db($val, 'UserName', 128, '637279988990508806'); // truncate for db.
        $this->username = $val;
    }

    // INPUT $val: person's first name
    public function setFirstName($val) {
        $val = truncate_for_db($val, 'FirstName', 128, '637279984035695537');
        $this->firstName = $val;
    }

    // INPUT $val: person's middle name
    public function setMiddleName($val) {
        $val = truncate_for_db($val, 'MiddleName', 128, '637279994501471753');
        $this->middleName = $val;
    }

    // INPUT $val: person's last name
    public function setLastName($val) {
        $val = truncate_for_db($val, 'LastName', 128, '637279994967212124');
        $this->lastName = $val;
    }

    // INPUT $val: permission string.  A string of decimal digits that indicate
    //  different permissions by position. Although the strings use the
    //  full 64 bytes, only the first few are currently (as of 2019-02)
    //  significant and the rest are for future use. These correspond positionally
    //  to the values in permission.permissionId. Values are the "PERMLEVEL" constants
    //  in inc/config.php.
    // JM 2020-10-22: >>>00001: I suspect that the proper validation here is not simply to
    //  truncate, but to make sure (1) this is composed entirely of decimal digits, maybe
    //  limiting further to only those that have an associated PERMLEVEL in inc/config.php
    //  and (2) probably should require that there are *exactly* 64 characters, or at least
    //  note it as an ERROR, not just INFO, if the length is different.
    //
    //  I (JM) wonder whether permissions might be better implemented by
    //  one or more database tables and a class than hardcoding values in inc/config.php and
    //  using positionally-dependent values in a bitstring. Not urgent to address that, though.
    private function setPermissionString($val) { // it's not used outside the class so we make it private
        $this->permissionString = $val;
    }

    // INPUT $val : >>>00014 Apparently related to SMS (text messaging).
    //  Used by SMS class (all other occurrences in code seem to be
    //  basically bookkeeping). As of 2020-02-19, the bitflags are:
    //  SMS_PERM_PING, SMS_PERM_HELP, SMS_PERM_OPEN, SMS_PERM_JOBS, SMS_PERM_OTHER
    private function setSmsPerms($val) { // it's not used outside the class so we make it private
        $this->smsPerms = intval($val);
    }

    // INPUT $val: initials that can function as a shorthand name for an employee of the customer
    //  (so for these to matter, there must be a corresponding row in customerPerson for this
    //  person & the present customer).
    //  >>> 00017: this class does NOT hook this to the database, just allows caller to set them
    //  and later get them back.
    // George 2020-11-19. We need to removed this from this class. CustomerPerson class has the logic.
    /*public function setLegacyInitials($val) {
        $val = trim($val);
        $val = substr($val, 0, 8);  // >>>00002: truncates silently.
        $this->legacyInitials = $val;
    }*/

    // RETURN primary key
    public function getPersonId() {
        return $this->personId;
    }

    // RETURN foreign key to Customer table; as of 2019-02, only customer is SSS
    public function getCustomerId() {
        return $this->customerId;
    }

    // RETURN username, normally an email address
    public function getUsername() {
        return $this->username;
    }

    // RETURN person's first name
    public function getFirstName() {
        return $this->firstName;
    }

    // RETURN person's middle name
    public function getMiddleName() {
        return $this->middleName;
    }

    // RETURN person's last name
    public function getLastName() {
        return $this->lastName;
    }

    // RETURN permission string.  A string of decimal digits that indicate
    //  different permissions by position. Although the strings use the
    //  full 64 bytes, only the first few are currently (as of 2019-02)
    //  significant and the rest are for future use. These correspond positionally
    //  to the values in permission.permissionId. Values are the "PERMLEVEL" constants
    //  in inc/config.php.
    public function getPermissionString() {
        return $this->permissionString;
    }

    // RETURN: >>>00014 Apparently related to SMS (text messaging).
    //  Used by SMS class (all other occurrences in code seem to be
    //  basically bookkeeping). As of 2020-02-19, the bitflags are:
    //  SMS_PERM_PING, SMS_PERM_HELP, SMS_PERM_OPEN, SMS_PERM_JOBS, SMS_PERM_OTHER
    public function getSmsPerms() {
        return $this->smsPerms;
    }

    // RETURN initials that can function as a shorthand name for an employee of the customer
    //  (so for these to matter, there must be a corresponding row in customerPerson for this
    //  person & the present customer).
    //  >>> 00017: this class does NOT hook this to the database, just allows caller to set them
    //  and later get them back.
    //  >>> 00017: as of 2019-02, looks like this can return an undefined value, since these are
    //  not reliably initialized in the constructor
    // George 2020-11-19. We need to removed this from this class. CustomerPerson class has the logic.
    /*public function getLegacyInitials() {
        return $this->legacyInitials;
    }*/

    // RETURN formatted person name
    // INPUT $flip: treated as a Boolean:
    //   true: return FIRST NAME + space + LAST NAME
    //   false: return LAST NAME + comma + non-breaking space + FIRST NAME
    // NOTE that if either first or last name is empty, the $flip==true case
    //  will just return the one existing name; the $flip==true will still
    //  have a space before last name or after first name.
    public function getFormattedName($flip = false) {
        $f = trim($this->getFirstName());
        $l = trim($this->getLastName());

        $comma = (strlen($f) && strlen($l)) ? ',&nbsp;' : '';

        if ($flip) {
            return $this->firstName . ' ' . $this->lastName;
        }

        return $this->lastName . $comma . $this->firstName;
    }

    // synonym for getFormattedName(false). Apparently Crumbs class needs this.
    public function getName() {
        // just for crumbs
        return $this->getFormattedName();
    }

    // INPUT $personEmailId: foreign key into DB table PersonEmail
    // Verifies that the input value designates a valid email for the person this class describes.
    // If valid, RETURNs an associative array that is the canonical representation
    //  of the appropriate row in DB table PersonEmail; 'emailAddress' column content is trimmed.
    // If invalid, RETURNs false.
    public function getEmail($personEmailId) {
        $query  = "SELECT pe.* ";
        $query .= "FROM " . DB__NEW_DATABASE . ".personEmail pe ";
        $query .= "WHERE pe.personId = " . intval($this->getPersonId()) . " ";
        $query .= "AND pe.personEmailId = " . intval($personEmailId) . ";";

        $result = $this->db->query($query);
        if(!$result){
            $this->logger->errorDb('637278339528629438', 'getEmail: Hard DB error', $this->db);
            return false;
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc(); // There can only be one: we passed in the personEmailId.
            $row['emailAddress'] = trim($row['emailAddress']);
            return $row;
        }

        return false;

    }

    // Return information about all email addresses for this person.
    // RETURNs an array of associative arrays, each of which is the canonical representation
    //  of a row in DB table PersonEmail corresponding to this person; 'emailAddress' column content is trimmed.
    //  Array is effectively ordered by when these were added to the DB, oldest first.
    public function getEmails(&$errCode=false) {
        $errCode=false;
        $emails = array();

        $query  = "SELECT pe.* ";
        $query .= "FROM " . DB__NEW_DATABASE . ".personEmail pe ";
        $query .= "WHERE pe.personId = " . intval($this->getPersonId()) . " ";
        $query .= "ORDER BY pe.personEmailId;";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('637278338601309430', 'getEmails: Hard DB error', $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['emailAddress'] = trim($row['emailAddress']);
                $emails[] = $row;
            }
        }

        return $emails;
    }

    // INPUT $personPhoneId: foreign key into DB table PersonPhone
    // Verifies that the input value designates a valid phone number for the person this class describes.
    // If valid, RETURNs an associative array that is the canonical representation
    //  of the appropriate row in DB table PersonPhone; 'phoneNumber' column content is trimmed.
    // If invalid, RETURNs false.
    public function getPhone($personPhoneId) { // George 2020-11-12. It's not used in the system.
        $query  = "SELECT pp.* ";
        $query .= "FROM " . DB__NEW_DATABASE . ".personPhone pp ";
        $query .= "WHERE pp.personId = " . intval($this->getPersonId()) . " ";
        $query .= "AND pp.personPhoneId = " . intval($personPhoneId);

        $result = $this->db->query($query);

        if(!$result){
            $this->logger->errorDb('637278337335896632', 'getPhone: Hard DB error', $this->db);
            return false;
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['phoneNumber'] = trim($row['phoneNumber']);
            return $row;
        }

        return false;
    }

    // Add another email address for this person (in DB table personEmail)
    // INPUT $emailAddress typically comes from $_REQUEST.
    //  A string with an email address.
    // Code checks for whether this email address is already there for this customer,
    //  avoids redundant INSERT.
    // George IMPROVED 2020-04-30. Method returns a boolean true on success, false on failure.
    // Log messages on failure.
    public function addEmail($emailAddress) {
        /* George 2020-11-18. Removed.
        if (!is_array($val)) {
            // JM 2020-05-19: this was just a warning, but it really should be an error: bad call by our code.
            $this->logger->error2('637224706687763872', 'addEmail => input value is not an array ');
            return false;
        }

        // BEGIN ADDED 2020-05-19 JM: clearly we need to check this, too.
        if (!isset($val['emailAddress'])) {
            $this->logger->error2('1589902301', 'array passed to person::addEmail had no index \'emailAddress\'');
            return false;
        }
        // END ADDED 2020-05-19 JM

        //$emailAddress = $val['emailAddress'];
        George 2020-11-18. End.
        */
        // BEGIN ADDED 2020-05-19 JM: clearly we need to check this, too. It's an error, not just a warning,
        // because we shouldn't have gotten this far without validating.
        if ( !filter_var($emailAddress, FILTER_VALIDATE_EMAIL) ) {
            $this->logger->error2('1589902333', "$emailAddress is not a valid email address.");
            return false;
        }
        // END ADDED 2020-05-19 JM

        $query  = "SELECT personId "; // George 2020-04-30 checking for existence.
        $query .= "FROM " . DB__NEW_DATABASE . ".personEmail  ";
        $query .= "WHERE personId = " . intval($this->getPersonId()) . " ";
        $query .= "AND emailAddress = '" . $this->db->real_escape_string($emailAddress) . "';";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('158765945', 'addEmail', $this->db);
            return false;
        }

        if ($result->num_rows > 0) {
            // Already exists, consider that success
            // JM 2020-05-19 NOTE in the next line I added personId, the warning isn't very informative without that.
            $this->logger->warn2('637224706687763873', "addEmail => email $emailAddress already exists for personId ". $this->getPersonId());
        } else {
            $query = "INSERT INTO  " . DB__NEW_DATABASE . ".personEmail (personId, emailAddress) VALUES (";
            $query .= " " . intval($this->getPersonId()) . " ";
            $query .= " ,'" . $this->db->real_escape_string($emailAddress) . "') ";

            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDb('637224706261757907', 'addEmail: Hard DB error', $this->db);
                return false;
            }
        }

        return true;
    } // END public function addEmail



    /**
    * Update a given email address for this person
    * @param array $val, this input typically comes from $_REQUEST. An associative array containing
    * the following elements:
    *   'emailAddress' - can be blank to delete email address.
    *   'personEmailId' - identifies email address to replace.
    * @param bool $integrity, variable pass by reference. Default value is false.
    * $integrity is True if no reference to the primary key of this row is found in the database.
    * @return bool true on success, false on failure.
    **/
    public function updateEmail($val, &$integrity=false) {
        // George 2020-05-20. ADDED.
        if (!is_array($val)) {
            $this->logger->error2('637255871370056363', 'updateEmail => input value is not an array ');
            return false;
        }

        if (!isset($val['emailAddress'])) {
            $this->logger->error2('637255877716244086', 'array passed to person::updateEmail had no index \'emailAddress\'');
            return false;
        }

        if (!isset($val['personEmailId'])) {
            $this->logger->error2('637255906856683323', 'array passed to person::updateEmail had no index \'personEmailId\'');
            return false;
        }
        // END ADDED.

        // BEGIN REPLACEMENT CODE 2019-12-02 JM
        $emailAddress  =  $val['emailAddress'];
        $personEmailId =  $val['personEmailId'];
        // END REPLACEMENT CODE 2019-12-02 JM

        // George. ADDED 2020-05-20.
        if ( !filter_var($emailAddress, FILTER_VALIDATE_EMAIL) ) {
            $this->logger->error2('637255881528426183', "$emailAddress is not a valid email address.");
        }
        // END ADDED.

        $query  = "SELECT personId "; // George 2020-04-30. Checking for existence.
        $query .= "FROM " . DB__NEW_DATABASE . ".personEmail  ";
        $query .= "WHERE personId = " . intval($this->getPersonId()) . " ";
        $query .= "AND personEmailId = " . intval($personEmailId);

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637224708877757219', 'updateEmail: Hard DB error', $this->db);
            return false;
        }

        if ($result->num_rows > 0) {
            // Yes, this person owns this personEmailId.
            $emailAddress = trim($emailAddress);

            if (strlen($emailAddress)) {

                // make sure we have only one emailAddress for this person.
                $query  = "SELECT emailAddress "; // Checking for existence.
                $query .= "FROM " . DB__NEW_DATABASE . ".personEmail  ";
                $query .= "WHERE personId = " . intval($this->getPersonId()). " ";
                $query .= "AND emailAddress = '" . $this->db->real_escape_string($emailAddress). "';";

                $result = $this->db->query($query);

                if (!$result) {
                    $this->logger->errorDb('637412293694913839', 'Hard DB error ', $this->db);
                    return false;
                }
                // This emailAddress is already associated with this person.
                if ($result->num_rows > 0) {
                    $this->logger->warn2('637412293862993872', "This emailAddress $emailAddress is already associated with this person: ". $this->getPersonId());
                    return true; // only Log an warn message. No message for User.
                } else {

                $query = "UPDATE " . DB__NEW_DATABASE . ".personEmail SET ";
                $query .= "emailAddress = '" . $this->db->real_escape_string($emailAddress) . "' ";
                $query .= "WHERE personEmailId = " . intval($personEmailId) . ";";

                $result = $this->db->query($query);
                if (!$result) {
                    $this->logger->errorDb('637224709520793372', 'updateEmail: Hard DB error', $this->db);
                    return false;
                }
                return true;
                }
            } else {
                // check for database Integrity issues.
                $query = "SELECT personEmailId FROM " . DB__NEW_DATABASE . ".personEmail WHERE personEmailId = $personEmailId; ";

                $result = $this->db->query($query);
                if(!$result) {
                    $this->logger->errorDb('637333635914429028', 'Select personEmailId: Hard DB error', $this->db);
                    return false;
                }

                //if($result->num_rows > 0){ // bad. Because is not condition $row['personEmailId'] doesn't exists.
                    $row = $result->fetch_assoc();

                //}
                // Issues with third argument, are Logged in the function: not a number, zero or is negative.
                $integrityTest = canDelete('personEmail', 'personEmailId', $row['personEmailId']);

                // if True, No reference to the primary key of this row is found in the database.
                if ($integrityTest == true) {
                    $query = "DELETE FROM " . DB__NEW_DATABASE . ".personEmail  ";
                    $query .= "WHERE personEmailId = " . intval($personEmailId) . ";";

                    $result = $this->db->query($query); // Rewrite George 2020-04-14.
                    if(!$result) {
                        $this->logger->errorDb('637224710032599637', 'updateEmail: Hard DB error', $this->db);
                        return false;
                    }
                    return true;
                } else {
                    $integrity = true; // At least one reference to this row exists in the database, violation of database integrity.
                    $this->logger->warn2('637224710032599637', 'update personEmail: Delete Email not possible! At least one reference to this row exists in the database, violation of database integrity.');
                }
            }
        }  else { // No row found for personEmailId
            $this->logger->warn2('637334400916900531', 'updateEmail => No row found for personEmailId ' . $personEmailId);
            return false;
        }

        return true;
    }  // END public function updateEmail($val)


    // Return information about all phone numbers for this person.
    // RETURNs an array of associative arrays, each of which is the canonical representation
    //  of a row in DB table PersonPhone corresponding to this person; 'typeName' and 'phoneNumber' column content is trimmed.
    //  Array is effectively ordered by when these were added to the DB, oldest first.
    public function getPhones(&$errCode=false) {
        $errCode=false;
        $phones = array();

        $query  = "SELECT pp.*,pt.typeName ";
        $query .= "FROM " . DB__NEW_DATABASE . ".personPhone pp ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".phoneType pt ON pp.phoneTypeId = pt.phoneTypeId ";
        $query .= "WHERE pp.personId = " . intval($this->getPersonId()) . " ";
        $query .= "ORDER BY pp.personPhoneId ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637278340501830296', 'getPhones: Hard DB error', $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['typeName'] = trim($row['typeName']);
                $row['phoneNumber'] = trim($row['phoneNumber']);
                $phones[] = $row;
            }
        }

        return $phones;
    }

    // Add a phone number for this person
    // INPUT $val typically comes from $_REQUEST. An associative array containing the following elements:
    //  * 'phoneNumber' - should be 10-digit string, North American dialing with no initial '1'.
    //    OK if some other characters are there, they will be stripped, so for example '(206)555-1212'
    //    as input means '2065551212'
    //  * 'phoneTypeId' - foreign key into DB table PhoneType
    // Code checks for whether this phone number is already there for this company, avoids redundant INSERT.
    // George IMPROVED 2020-04-28. Method returns a boolean true or false.
    public function addPhone($val) {
        // George ADDED 2020-05-22
        if (!is_array($val)) {
            $this->logger->error2('637223871620707380', 'addPhone => expected array as input, got something not an array!');
            return false;
        }

        if (!isset($val['phoneNumber'])) {
            $this->logger->error2('637256895981523280', 'array passed to person::addPhone had no index \'phoneNumber\'');
            return false;
        }

        if (!isset($val['phoneTypeId'])) {
            $this->logger->error2('637256896698503424', 'array passed to person::addPhone had no index \'phoneTypeId\'');
            return false;
        }
        // END ADDED

        // George 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
        if (!preg_match("/^[- ()0-9]*$/", $val['phoneNumber'])) {
            $this->logger->error2("637412231604167504", "Invalid characters in phoneNumber, input given: " . $val['phoneNumber']);
            return false;
        } else {
        $phoneNumber = $val['phoneNumber'];
        }

        $phoneTypeId = $val['phoneTypeId'];

        // Get all phoneTypes, and single out the phoneType with typeName "Other"
        $other = false;
        $phoneTypes = Person::getPhoneTypes();

        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['typeName'] == 'Other') {
                $other = $phoneType['phoneTypeId'];
            }
        }

        // Validate input phone type
        $ok = false;
        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['phoneTypeId'] == $phoneTypeId) {
                $ok = true;
            }
        }
        // If no valid phone type in input, use 'Other'.
        if (!$ok) {
            if ($other) {
                $phoneTypeId = $other;
                $ok = true;
            }
        } else if ($ok) {
            // only digits
            $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
            // George 2020-11-17. Check if we have exactly 10 digits! Log if not.
            if (strlen($phoneNumber) != PHONE_NADS_LENGTH ) {
                $this->logger->warn2('637236868759896229', 'addPhone => Input phone number, not 10 digits!');
            }
            // NOTE that the select here ignores phone type
            $query  = "SELECT personId "; // reworked 2020-04-23 JM, Was " select * " but we are really just checking for existence.
            $query .= "FROM " . DB__NEW_DATABASE . ".personPhone  ";
            $query .= "WHERE personId = " . intval($this->getPersonId()) ." ";
            $query .= "AND phoneNumber = '" . $this->db->real_escape_string($phoneNumber) . "';";

            $result = $this->db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $this->logger->warn2('637223870880859767', "addPhone - input value already exists : " . $phoneNumber);
                    return true; // only Log an warn message. No message for User.
                }
            } else {
                $this->logger->errorDb('637223869646023101', 'addPhone: Hard DB error', $this->db);
                return false;
            }

            $query = "INSERT INTO  " . DB__NEW_DATABASE . ".personPhone (phoneTypeId, personId, phoneNumber) VALUES (";
            $query .= intval($phoneTypeId);
            $query .= ", " . intval($this->getPersonId());
            $query .= ", '" . $this->db->real_escape_string($phoneNumber) . "');";

            $result = $this->db->query($query);
            if(!$result) {
                $this->logger->errorDb('637223870434240926', 'addPhone: Hard DB error', $this->db);
                return false;
            }
            return true;

        } else {
            $this->logger->warn2('637223870880859766', $phoneType['phoneTypeId'] .
            " is not an identifiable phone type, and there is no phone type 'Other' in DB table phoneTypes");
            /* End IMPROVEMENT */
            return false;
        }
    } // END public function addPhone

    // Update a phone number for this person
    // INPUT $val typically comes from $_REQUEST. An associative array containing the following elements:
    //  * 'phoneNumber' - new phone number; should be 10-digit string, North American dialing with no initial '1'.
    //    Can be blank to delete.
    //  * 'phoneTypeId' - key into DB table PhoneType, desired phone type
    //  * 'personPhoneId' - key into DB table PersonPhone, row to update.
    // George IMPROVED 2020-04-30. Method returns a boolean true on success, false on failure.
    // Log messages on failure.
    public function updatePhone($val) {

        if (!is_array($val)) {
            $this->logger->error2('637223897171457786', 'updatePhone => expected array as input, got something not an array');
            return false;
        }

        if (!isset($val['phoneNumber'])) {
            $this->logger->error2('637256948335706406', 'array passed to person::updatePhone had no index \'phoneNumber\'');
            return false;
        }

        if (!isset($val['phoneTypeId'])) {
            $this->logger->error2('637256948434396681', 'array passed to person::updatePhone had no index \'phoneTypeId\'');
            return false;
        }

        if (!isset($val['personPhoneId'])) {
            $this->logger->error2('637256949111608557', 'array passed to person::updatePhone had no index \'personPhoneId\'');
            return false;
        }

        if (!isset($val['ext1'])) {
            $this->logger->error2('637257429613571201', 'array passed to person::updatePhone had no index \'ext1\'');
            return false;
        }

        // George 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
        if(!preg_match("/^[- ()0-9]*$/", $val['phoneNumber'])) {
            $this->logger->error2("637412245162777144", "Invalid characters in phoneNumber, input given: " . $val['phoneNumber']);
            return false;
        } else {
            $phoneNumber = $val['phoneNumber'];
        }

        if( strlen($val['ext1']) <= 5 ) {
            $ext1 = $val['ext1'];
        } else {
            $this->logger->error2("637413097647943022", "Invalid extension for this phoneNumber, input given: " . $val['ext1']);
            return false;
        }

        // BEGIN REPLACEMENT CODE 2019-12-02 JM // George Updated 2020-05-22
        $phoneTypeId = $val['phoneTypeId'];
        $personPhoneId = $val['personPhoneId'];
        // END REPLACEMENT CODE 2019-12-02 JM // End Update

        // Get all phoneTypes, and single out the phoneType with typeName "Other"
        $other = false;
        $phoneTypes = Person::getPhoneTypes();
        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['typeName'] == 'Other') {
                $other = $phoneType['phoneTypeId'];
            }
        }

        // Validate input phone type
        $ok = false;
        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['phoneTypeId'] == $phoneTypeId) {
                $ok = true;
            }
        }

        // If no valid phone type in input, use 'Other'
        if (!$ok) {
            if ($other) {
                $phoneTypeId = $other;
                $ok = true;
            }
        }

        if ($ok) {
            // make sure $personPhoneId matches this person
            $query  = " SELECT personId "; // George reworked 2020-04-30. Checking for existence.
            $query .= " FROM " . DB__NEW_DATABASE . ".personPhone  ";
            $query .= "WHERE personId = " . intval($this->getPersonId()). " ";
            $query .= " AND personPhoneId = " . intval($personPhoneId);

            $result = $this->db->query($query);

            if (!$result) {
                $this->logger->errorDb('637223886283156396', 'updatePhone: Hard DB error ', $this->db);
                return false;
            }


            if ($result->num_rows > 0) { //Entry exists.
                $phoneNumber = trim($phoneNumber);
                if (strlen($phoneNumber)) {
                    // only digits
                    $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
                     // George 2020-11-17. Check if we have 10 digits! Log warning if not.
                    if (strlen($phoneNumber) != PHONE_NADS_LENGTH) {
                        $this->logger->warn2('637238570399560217', 'updatePhone => Input phone number, not 10 digits!');
                    }
                    // make sure we have only one phoneNumber for this person.
                    $query  = "SELECT phoneNumber "; // Checking for existence.
                    $query .= "FROM " . DB__NEW_DATABASE . ".personPhone  ";
                    $query .= "WHERE personId = " . intval($this->getPersonId()). " ";
                    $query .= "AND phoneNumber = '" . $this->db->real_escape_string($phoneNumber). "' ";
                    $query .= "AND personPhoneId <> " . intval($personPhoneId). " ;";

                    $result = $this->db->query($query);

                    if (!$result) {
                        $this->logger->errorDb('637412265165691653', 'select phoneNumber: Hard DB error ', $this->db);
                        return false;
                    }
                    // This phoneNumber is already associated with this person.
                    if ($result->num_rows > 0) {
                        $this->logger->warn2('637412264285474432', "This phoneNumber $phoneNumber is already associated with this person: ". $this->getPersonId());
                        return true; // only Log an warn message. No message for User.
                    } else {
                    $query = "UPDATE " . DB__NEW_DATABASE . ".personPhone SET ";
                    $query .= "phoneNumber = '" . $this->db->real_escape_string($phoneNumber) . "'";
                    $query .= ", ext1 = '" . $this->db->real_escape_string($ext1) . "'";

                    $query .= ", phoneTypeId = " . intval($phoneTypeId) . " ";
                    $query .= "WHERE personPhoneId = " . intval($personPhoneId) . ";";

                    $result = $this->db->query($query); // Rewrite George 2020-04-13.
                    if (!$result) {
                        $this->logger->errorDb('637223886806329219', 'updatePhone: Hard DB error', $this->db);
                        return false;
                    }
                    return true;
                    }

                } else {
                    $query = " DELETE FROM " . DB__NEW_DATABASE . ".personPhone  ";
                    $query .= " WHERE personPhoneId = " . intval($personPhoneId) . " ";

                    $result = $this->db->query($query); // Rewrite George 2020-04-13.
                    if (!$result) {
                        $this->logger->errorDb('637223887790565608', 'updatePhone: Hard DB error', $this->db);
                        return false;
                    }
                    return true;
                }
            }  // George Added 2020-04-30.
            else {
                $this->logger->warn2('637238574175033674', "Didn't find a phone to match this person (personId = {$this->getPersonId()})");
                return false;
            } // End Add
        }
        else {
            $this->logger->warn2('637223897062735982', $phoneType['phoneTypeId'] . " is not an identifiable phone type, and there is no phone type 'Other' in DB table phoneTypes");
            return false;
        }
    } // END public function updatePhone

    // [Martin comment, paraphrased] "This gets all the companyPersons for this personId,
    //   thus getting all companys/companies associated with the person."
    //
    // RETURNs an array of CompanyPerson objects, one for each company this person is associated with.
    // Returned array is in no particular order.
    public function getCompanyPersons(&$errCode=false) {
        $errCode=false;
        $ret = array();

        $query = "SELECT companyPersonId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".companyPerson ";
        $query .= "WHERE personId = " . intval($this->getPersonId()) . " ";
        $query .= "AND companyId != 0 ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637278333166219246', 'getCompanyPersons: Hard DB error', $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $cp = new CompanyPerson($row['companyPersonId']);
                $ret[] = $cp;
            }
        }

        return $ret;
    } // END public function getCompanyPersons



    /**
    * @param bool $errCode, variable pass by reference. Default value is false.
    *  $errCode is True on query failed.
    * @return array $companies. RETURNs an array of associative arrays, one for each company this person is associated with.
    * Returned array is in no particular order. Each associative array consists of the canonical
    * representation of a row from DB table Company (indexes correspond to columns), plus another
    * index 'companyPersonId' for an element containing the primary key in DB table companyPerson.
    */

    public function getCompanies(&$errCode = false) {
        $errCode = false;
        $companies = array();

        $query = " SELECT c.*,cp.companyPersonId  ";
        $query .= " FROM " . DB__NEW_DATABASE . ".companyPerson cp ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".company c ON cp.companyId = c.companyId ";
        $query .= " WHERE cp.personId = " . intval($this->getPersonId());

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637261089461008169', 'getCompanies: Hard DB error', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $company = new Company($row, $this->user);
                $company->companyPersonId = $row['companyPersonId']; // added in because needed when assigning people and people from a company to a role in a  workorders etc
                $companies[] = $company;
            }
        }

        return $companies;
    }


    // RETURNs an array of associative arrays, one for each location this person is associated with.
    //  Returned array is effectively in chronological order by when this location was associated with
    //  this person. Each associative array contains the canonical representation of DB data for
    //  personLocation and Location (>>> JM: or almost canonical, since it's a little unusual to use
    //  SELECT * on two joined tables). // Reworked 2020-11-19.
    public function getLocations(&$errCode=false) {
        $errCode=false;
        $ret = array();

        $query = " SELECT pl.locationId, pl.personLocationId  ";
        $query .= " FROM " . DB__NEW_DATABASE . ".personLocation pl ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".location l ON pl.locationId = l.locationId ";
        $query .= " WHERE pl.personId = " . intval($this->getPersonId());
        $query .= " ORDER BY pl.personLocationId ";

        $result = $this->db->query($query); // George 2020-06-15. Rewrite if statement.

        if(!$result){
            $this->logger->errorDb('637278334935618736', 'getLocations: Hard DB error', $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $ret[] = $row;
            }
        }

        return $ret;
    }

    // INPUT $personLocationId: foreign key into DB table PersonLocation. DEFAULT 0 means "take the first one,
    //  we don't care which". That will always be the chronologically first location associated with this person.
    // Verifies that the input value designates a valid location for the person this class describes.
    // If valid, RETURNs Location object for the location specified by the input.
    // If invalid, RETURNs false.
    public function getLocation($personLocationId = 0) {
        $query  = "SELECT locationId "; // JM 2020-03-13: was "select *" for no good reason, all we use is locationId.
        $query .= "FROM " . DB__NEW_DATABASE . ".personLocation ";
        $query .= "WHERE personId = " . intval($this->getPersonId()) . " ";
        if (intval($personLocationId)) {
            $query .= "AND personLocationId = " . intval($personLocationId) . " ";
        } // else just take the first one.
        $query .= "ORDER BY personLocationId ASC;";

        // $found = false; // REMOVED 2020-03-13 JM

        $result = $this->db->query($query); // George 2020-06-15. Rewrite if statement.

        if(!$result){
            $this->logger->errorDb('637278336067217708', 'getLocation: Hard DB error', $this->db);
            return false;
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return new Location($row['locationId']);
        }

        return false;
    }  // END public function getLocation

    // Update several values for this person
    // INPUT $val typically comes from $_REQUEST.
    //  An associative array containing the following elements (unlike addresses, emails, etc,
    //   these are all directly in DB table person, and a person has only one of each):
    //   * 'username' - username is tested for uniqueness, and is not used if it matches any other username.
    //   * 'firstName'
    //   * 'middleName'
    //   * 'lastName'
    //   * 'smsPerms'
    //   Any or all of these may be present. >>>00016: Maybe more validation?
    // Added Boolean return: true on success, false on failure
    public function update($val) {
        if (!is_array($val)) {
            $this->logger->warn2('637244455409075030', 'update Person => expected array as input, got something not an array');
            return false;
        }
        if (isset($val['username']) && trim($val['username']) != '') {
            // JM 2020-10-22: >>>00001: don't we hava a possible truncation issue here? If it will be truncated in Person::setUsername,
            //  don't we need to do our test here on the truncated version?
            $username = truncate_for_db($val['username'], 'UserName', 128, '637413173129680126'); // truncate for db.
            if (strlen($username)) {
                $query  = "SELECT * ";
                $query .= "FROM " . DB__NEW_DATABASE . ".person ";
                $query .= "WHERE customerId = " . intval($this->getCustomerId()) . " ";
                $query .= "AND username = '" . $this->db->real_escape_string($username) . "' ";
                $query .= "AND personId != " . intval($this->getPersonId()) . ";";

                $result = $this->db->query($query);
                if (!$result) {
                    $this->logger->errorDb('637238596379588127', 'update: Hard DB error', $this->db);
                    return false;
                }
                if ($result->num_rows == 0) {
                    $this->setUsername($username);
                } else {
                    // We have a conflicting username, so warn and fail.
                    $row = $result->fetch_assoc();
                    $this->logger->warn2('1589903612', "Username '$username' already used for personId {$row['personId']}, cannot be applied to personId " . $this->getPersonId());
                    return false;
                }
            }
        }

        // George 2020-11-18. Yes this is not used in the save. So we need to remove 'permissionString' from here.
        /*
        if (isset($val['permissionString']) && trim($val['permissionString'])) {
            // >>>00016: JM 2020-05-19: No rush on this one, but we ought to add a function somewhere for validating the permission string.
            // At the moment it is, indeed, confined to being exactly 64 numeric characters, but that could easily change.
            // No action item right now, but someone may want at some point to look through for these and get that test into a single
            // place so it can easily be changed. (Conversely, '0', '4', '6', and '8' are NOT currently valid permission values and
            // we're not testing that here. If we got this in one place, we'd be able to do it correctly in that one place.)
            // JM 2020-10-22: >>>00001: we have validation duties kind of oddly divided between here and public function setPermissionString.
            //  I think setPermissionString should probably do the validation work (and return false on invalid) because all setting of the value
            //  has to come through there. BY THE WAY, once you are confident of having this right, please, test to make sure our assumptions
            //  are correct. But I'm now wondering whether this is even used: see my remarks below on fb/personpermissions.php.

            $permissionString = trim($val['permissionString']);
            if ((strlen($permissionString) == 64) && is_numeric($permissionString)) {
                $this->setPermissionString($val['permissionString']);
            } else {
                $this->logger->error2('1589903648', "Invalid permission string for personId" . $this->getPersonId());
                return false;
            }
        }*/


        if (isset($val['smsPerms'])) {
            // >>>00016: JM 2020-05-19: No rush on this one, but we ought to add a function somewhere for validating this permission string, too.
            if (is_numeric($val['smsPerms'])) { // The validation and logic is done in the appellant code in person.php
                $this->setSmsPerms($val['smsPerms']);
            }
        } else {
            if (isset($val['firstName']) && trim($val['firstName'])) {
                $this->setFirstName($val['firstName']);
            } else {
                $this->setFirstName(""); //we can delete First Name
            }

            if (isset($val['middleName']) && trim($val['middleName'])) {
                $this->setMiddleName($val['middleName']);
            } else {
                $this->setMiddleName(""); //we can delete Middle Name
            }

            if (isset($val['lastName']) && trim($val['lastName'])) {
                $this->setLastName($val['lastName']);
            } else {
                $this->setLastName(""); //we can delete Last Name
            }
        }

        return $this->save();

    } // END public function update

    // JM 2020-10-22: >>>00014 I don't see permissionString in save at all. So is it only an illusion that we can change it via update?
    // I glanced at fb/personpermissions.php, and it looks to me like it doesn't use this class at all to make the changes! It handles
    //  permissions directly, making its own DB calls. George, please expand the scope of what you are looking into to include that. Thanks.

    // >>>00017 kind of weird for this to be private when setFirstName,
    //  setMiddleName, setLastName, setPermissionString, and setSmsPerms are
    //  public. Caller needs to do some sort of "end run" with public function update
    // to get those values to "take".
    // UPDATEs same fields handled by public function update.
    // RETURN true or false;
    private function save() {
        $query = "UPDATE " . DB__NEW_DATABASE . ".person SET ";
        $query .= " username = '" . $this->db->real_escape_string($this->getUsername()) . "'";
        $query .= ", firstName = '" . $this->db->real_escape_string($this->getFirstName()). "'";
        $query .= ", middleName = '" . $this->db->real_escape_string($this->getMiddleName()) . "'";
        $query .= ", lastName = '" . $this->db->real_escape_string($this->getLastName()) . "'";
        $query .= ", smsPerms = " . intval($this->getSmsPerms()) . " ";
        $query .= "WHERE personId = " . intval($this->getPersonId()) . ";";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('637244451797920542', 'save: Hard DB error', $this->db);
            return false;
        }

        return true;
        // END ADDED
    }

    // RETURN an associative array containing certain private values from this class object.
    public function toArray() {
        return array(
            'personId' => $this->getPersonId(),
            'username' => $this->getUsername(),
            'firstName' => $this->getFirstName(),
            'middleName' => $this->getMiddleName(),
            'lastName' => $this->getLastName(),
            'formattedName' => $this->getFormattedName()
        );
    }

    /**
    * @param integer $personId: personId to validate, should be an integer but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the id is a valid personId, false if not.
    */
    public static function validate($personId, $unique_error_id=null) {
        global $db, $logger;
        Person::loadDB($db);

        $query = "SELECT personId FROM " . DB__NEW_DATABASE . ".person WHERE personId=$personId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578692801', "Hard DB error", $db);
            return false;
        }

        return !!($result->num_rows); // convert to boolean
    }


    public static function errorToText($errCode) {
        $error = '';
        $errorId = 0;

        if($errCode == 0) {
            $errorId = '1574878345';
            $error = 'addPerson method failed.';
        } else if($errCode == DB_GENERAL_ERR) {
            $errorId = '637144657192945309';
            $error = 'Database error.';
        } else if($errCode == DB_ROW_ALREADY_EXIST_ERR) {
            $errorId = '637147237170105392';
            $error = "Error input parameters, username already in use";
        } else if($errCode == EMAIL_PATTERN_MISMATCH) {
            // this point should not be reached.
            $error = "Error input parameters, please fix them and try again";
            $errorId = "637172315231457285";
        } else {
            // this point should not be reached.
            $error = "Unknown error, please fix them and try again";
            $errorId = "637172316020296367";
        }

        return array($error, $errorId);
    }
}

?>