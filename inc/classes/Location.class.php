<?php
/* inc/classes/Location.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the Location table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular job.
* Public functions:
** __construct($id = null, User $user = null)
** public static function addLocation($location_array)
** getServiceLoad(&errCode = false)
** setName($val)
** setAddress1($val)
** setAddress2($val)
** setSuite($val)
** setCity($val)
** setState($val)
** setCountry($val)
** setPostalCode($val)
** setLatitude($val)
** setLongitude($val)
** setGoogleGeo($val)
** getLocationId()
** getId()
** getCustomerId()
** getName()
** getAddress1()
** getAddress2()
** getSuite()
** getCity()
** getState()
** getCountry()
** getPostalCode()
** getLatitude()
** getLongitude()
** getGoogleGeo()
** getFormattedAddress()
** update($val)
** getCompanyUsage(&$errCode = false)
** getPersonUsage(&$errCode = false)
** getJobUsage(&$errCode = false)
** cloneLocation() - added 2019-11-21 JM

** public static function validate($locationId, $unique_error_id=null)
*/

require_once dirname(__FILE__).'/../config.php'; // ADDED 2019-02-13 JM

class Location extends SSSEng {
    // The following correspond exactly to the columns of DB table Location.
    // See documentation of that table for further details.
    private $locationId;
    private $customerId;
    private $name;
    private $address1;
    private $address2;
    private $suite;
    private $city;
    private $state;
    private $country;
    private $postalCode;
    private $latitude;
    private $longitude;
    private $googleGeo;
    // (Omits county, >>>00001 is appears not to be used 2019-12)
    // (Omits created, >>>00001 is that ever used?)

    // INPUT $id: May be either of the following:
    //  * a locationId from the Location table
    //  * DEPRECATED: an associative array which should contain an element for each columnn
    //    used in the Location table, corresponding to the private variables
    //    just above.
    //    >>>00016: JM 2019-02-25: should certainly validate this input, doesn't. But that's probably moot:
    //    let's be getting rid of it, not fixing it.
    //  Either way, in practice, SOME OF THIS INPUT MAY BE IMMEDIATELY OVERWRITTEN,
    //    because the loading of these values is immediately followed by a call to private
    //    function (method) getGeo. NOTE that this actually writes to the database table.
    // INPUT $user: User object, typically current user (which is the effective default via parent constructor).
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->load($id);
        $this->getGeo(); // see function description
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {
        if (is_numeric($val)) {
            // Read row from DB table Location
            $query = " SELECT l.* ";
            $query .= " FROM " . DB__NEW_DATABASE . ".location l ";
            $query .= " WHERE l.locationId = " . intval($val);

            $result = $this->db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    // Since query used primary key, we know there will be exactly one row.

                    // Set all of the private members that represent the DB content
                    $this->setLocationId($row['locationId']);
                    $this->setCustomerId($row['customerId']);
                    $this->setName($row['name']);
                    $this->setAddress1($row['address1']);
                    $this->setAddress2($row['address2']);
                    $this->setSuite($row['suite']);
                    $this->setCity($row['city']);
                    $this->setState($row['state']);
                    $this->setCountry($row['country']);
                    $this->setPostalCode($row['postalCode']);
                    $this->setLatitude($row['latitude']);
                    $this->setLongitude($row['longitude']);
                    $this->setGoogleGeo($row['googleGeo']);
                } else {
                    $this->logger->errorDb('637406141067277968', "Invalid locationId", $this->db);
                }
            } else {
                $this->logger->errorDb('637406140635329584', "Hard DB error", $this->db);
            }
        } else if (is_array($val)) {
            // BEGIN ADDED 2020-03-17 JM
            // >>>00032 Trying to get rid of this case. We believe we've eliminated it for version 2020-2, but we
            //  aren't certain; log if it comes up. (And after we later kill this case, leave the logging in: it won't be
            //  less of an error because we stopped handling the case!)
            $this->logger->error2('1584482596', 'Deprecated: Location::load called, presumably by Location::_construct, with an array instead of just a locationId.');
            // END ADDED 2020-03-17 JM
            // Set all of the private members that represent the DB content, from
            //  input associative array
            $this->setLocationId($val['locationId']);
            $this->setCustomerId($val['customerId']);
            $this->setName($val['name']);
            $this->setAddress1($val['address1']);
            $this->setAddress2($val['address2']);
            $this->setSuite($val['suite']);
            $this->setCity($val['city']);
            $this->setState($val['state']);
            $this->setCountry($val['country']);
            $this->setPostalCode($val['postalCode']);
            $this->setLatitude($val['latitude']);
            $this->setLongitude($val['longitude']);
            $this->setGoogleGeo($val['googleGeo']);
        }
    } // END private function load

    // INSERT a location based on $location_array values.
    // >>>00002, >>>00016 truncates any overlong values without logging; makes sure
    //  latitude & longitude are numbers; does no other validation
    //  (e.g. will accept a state or postal code that doesn't exist,
    //  or an invalid googleGeo).
    // RETURNs new Location object on success, false on failure.
    public static function addLocation($location_array) {
        global $customer; //static, so it needs this
        global $logger; // static, so it needs this.
        if (!is_array($location_array)) {
            return false;
        }
        $db = DB::getInstance(); // static, so it needs this.

        $name = isset($location_array['name']) ? $location_array['name'] : '';
        $address1 = isset($location_array['address1']) ? $location_array['address1'] : '';
        $address2 = isset($location_array['address2']) ? $location_array['address2'] : '';
        $suite = isset($location_array['suite']) ? $location_array['suite'] : '';
        $city = isset($location_array['city']) ? $location_array['city'] : '';
        $state = isset($location_array['state']) ? $location_array['state'] : '';
        $country = isset($location_array['country']) ? $location_array['country'] : '';
        $postalCode = isset($location_array['postalCode']) ? $location_array['postalCode'] : '';
        $latitude = isset($location_array['latitude']) ? $location_array['latitude'] : 0;
        $longitude = isset($location_array['longitude']) ? $location_array['longitude'] : 0;
        //$googleGeo = isset($location_array['googleGeo']) ? $location_array['googleGeo'] : ''; Unused.
        $customerId = isset($location_array['customerId']) ? $location_array['customerId'] : ''; // customerId - added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52

        //at least addlocation.php call this without customerId in $location_array, so we take it from $customer
        if (!$customerId){
            $customerId = $customer->getCustomerId();
        }

        // >>>00002: all of this trimming should report if there was anything other than whitespace trimmed; also, nothing should end in whitespace.
        // Reworked. George 2020-09-15.
        $name = truncate_for_db($name, "AddLocation => Location name", 128, "637357831630308686");
        $address1 = truncate_for_db($address1, "AddLocation => Location address1", 128, "637357840599860766");
        $address2 = truncate_for_db($address2, "AddLocation => Location address2", 128, "637357840913076659");
        $suite = truncate_for_db($suite, "AddLocation => Location suite", 16, "637357841217006223");
        $city = truncate_for_db($city, "AddLocation => Location city", 64, "637357841452182102");
        $state = truncate_for_db($state, "AddLocation => Location state", 64, "637357841714226116");
        $country = truncate_for_db($country, "AddLocation => Location country", 2, "637357842471628417");
        $postalCode = truncate_for_db($postalCode, "AddLocation => Location postalCode", 64, "637357842531519093");

        //$latitude = preg_replace("/[^0-9\.-]/", "", $latitude);
        if (!preg_match('/^[+-]?(([1-8]?[0-9])(\.[0-9]{1,8})?|90(\.0{1,8})?)$/', $latitude)) {
            $logger->warn2('637357855937058240', "AddLocation => Invalid Latitude format ");
        }

        //$longitude = preg_replace("/[^0-9\.-]/", "", $longitude);
        if (!preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $longitude)) {
            $logger->warn2('637358517739538397', "AddLocation => Invalid Longitude format ");
        }
        // End Reworked. George 2020-09-15.
        if (!strlen($latitude)){
            $latitude = 0;
        }
        if (!strlen($longitude)){
            $longitude = 0;
        }

        //$googleGeo = trim($googleGeo); //Unused
        $customerId = intval($customerId); // customerId - added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52


        $query = " INSERT INTO " . DB__NEW_DATABASE . ".location (name, address1, address2, suite, city, state, country, postalCode, ".
            "latitude, longitude, customerId) VALUES (";
        $query .= " '" . $db->real_escape_string($name) . "' ";
        $query .= " ,'" . $db->real_escape_string($address1) . "' ";
        $query .= " ,'" . $db->real_escape_string($address2) . "' ";
        $query .= " ,'" . $db->real_escape_string($suite) . "' ";
        $query .= " ,'" . $db->real_escape_string($city) . "' ";
        $query .= " ,'" . $db->real_escape_string($state) . "' ";
        $query .= " ,'" . $db->real_escape_string($country) . "' ";
        $query .= " ,'" . $db->real_escape_string($postalCode) . "' ";
        $query .= " ," . $db->real_escape_string($latitude) . " ";  // $db->real_escape_string here and on other numerics is probably unnecessary, but should be harmless.
        $query .= " ," . $db->real_escape_string($longitude) . " ";
        //$query .= " ,'" . $db->real_escape_string($googleGeo) . "' "; //Unused
        $query .= " ," . $db->real_escape_string($customerId); // customerId - added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52
        $query .= ");";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1574789099', "Insert failed", $db);
            return false;
        }

        $id = $db->insert_id;

        if (intval($id)) {
            return new Location($id);
        }

        $logger->errorDb('1574789120', "Insert failed: no insert_id resulted", $db);
        return false;
    } // END public static function addLocation

    /**
    * @param bool $errCode, variable pass by reference. Default value is false, is True on query failed.
    * @return array $ret. RETURNs an array of associative arrays, each representing a
        *service load variable for this location.
        *Returned array is ordered by (serviceLoadId, serviceLoadVarId). Each associative array contains members:
            * 'locationServiceLoadId'
            * 'locationId' (identical for all)
            * 'serviceLoadVarId'
            * 'varValue'
            * 'serviceLoadId'
            * 'loadVarName'
            * 'loadVarData'
            * 'loadVarType'
            * 'wikilink'
            * 'loadName'
    */
    public function getServiceLoad(&$errCode = false) {
        $errCode = false;
        $ret = array();

        $query  = " SELECT * FROM " . DB__NEW_DATABASE . ".locationServiceLoad lsl ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".serviceLoadVar slv ON lsl.serviceLoadVarId = slv.serviceLoadVarId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".serviceLoad sl ON slv.serviceLoadId = sl.serviceLoadId ";
        $query .= " WHERE lsl.locationId = " . intval($this->getLocationId());
        $query .= " ORDER BY sl.serviceLoadId, slv.serviceLoadVarId ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637363752931162148', "getServiceLoad for this location failed.", $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $ret[] = $row;
            }
        }
        return $ret;
    }


    /** >>>00028: NOT CURRENTLY TRANSACTIONAL, presumably should be.
    * Delete all serviceLoad data for the locationId,
        *then loop over the POSTed varValue_ServiceLoadVarId values, Inserting
        *(locationId, serviceLoadVarId, varValue) into table locationServiceLoad for each.
    * @param array $val. INPUT $val typically comes from $_REQUEST.
    * @return bool true on success, false otherwise.
    */
    public function addServiceLoad($val) {

        if (!is_array($val)) {
            $this->logger->error2('637366290145037297', 'addServiceLoad => input value is not an array ');
            return false;
        }
        // CP - 2020-11-30 before delete start a transaction after disabling autocommit
        $this->db->autocommit(false);
        $this->db->begin_transaction();
        $query = " DELETE FROM " . DB__NEW_DATABASE . ".locationServiceLoad WHERE locationId = " . intval($this->locationId);
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('637366288670849066', 'addServiceLoad => Delete: Hard DB error', $this->db);
            return false;
        } else {
            foreach ($val as $key => $value) {
                $parts = explode("_", $key);
                if (count($parts) == 2) {
                    if ($parts[0] == 'varValue') {
                        if (intval($parts[1])) {
                            $varValue = truncate_for_db($value, 'addServiceLoad => varValue', 64, '637366281506989845');

                            if (strlen($varValue)) {

                                $query = " INSERT INTO " . DB__NEW_DATABASE . ".locationServiceLoad (locationId, serviceLoadVarId, varValue) VALUES (";
                                $query .= " " . intval($this->locationId) . " ";
                                $query .= " ," . intval($parts[1]) . " ";
                                $query .= " ,'" . $this->db->real_escape_string($varValue) . "') ";

                                $result = $this->db->query($query);
                                if (!$result) {
                                    $this->logger->errorDb('637366291096562760', 'addServiceLoad => Insert: Hard DB error', $this->db);
                                    // CP - 2020-11-30 If error just rollback and the initial delete is reversed. Of course reset after the autocommit variable
                                    $this->db->rollback();
                                    $this->db->autocommit(true);
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
        }
        // CP - 2020-11-30 If all OK commit all the queries. Of course reset after the autocommit variable
        $this->db->commit();
        $this->db->autocommit(true);
        return true;
    }


    // Part of initialization
    //  If address1 is at least 5 characters, and either latitude or longitude is NOT set,
    //   then this uses http://maps.google.com/maps/api/geocode/json to get and update
    //   googleGeo, latitude, and longitude.
    private function getGeo() {
        if ((($this->getLatitude() == 0) || ($this->getLongitude() == 0)) && (strlen($this->getAddress1()) > 5)) {
            $gloc = $this->getAddress1()  .  ", " . $this->getCity() . ", " . $this->getState() . ", " . $this->getPostalCode();
            $prep = urlencode($gloc);//str_replace(' ', '+', $gloc);

            // The following was already commented out by Martin some time before 2019.
            // NOTE that it has a different JSON KEY. In the unlikely event that this is to be revived, presumably use the new key! - JM
            //echo 'https://maps.google.com/maps/api/geocode/json?key=AIzaSyBrJIHI9F1xaqVLaYvcPOC5e2-xP43IAuI&address=' . $prep . '&sensor=false';

            //die();

            /*
            OLD CODE removed 2019-02-04 JM
            $geocode = file_get_contents('https://maps.google.com/maps/api/geocode/json?key=AIzaSyCdeV7tQmb2Z9rWCis5qAhRdBYVocNtAaI&address=' . $prep . '&sensor=false');
            */
            // BEGIN NEW CODE 2019-02-04 JM
            $geocode = file_get_contents('https://maps.google.com/maps/api/geocode/json?key='.CUSTOMER_GOOGLE_JSON_KEY.'&address=' . $prep . '&sensor=false');
            // END NEW CODE 2019-02-04 JM

            //echo "<p>";
            //echo strlen($geocode);
            //echo '<p>';
            /*
            OLD CODE (already commented out) removed 2019-02-04 JM
            //echo 'https://maps.google.com/maps/api/geocode/json?key=AIzaSyCdeV7tQmb2Z9rWCis5qAhRdBYVocNtAaI&address=' . $prep . '&sensor=false';
            */
            // BEGIN NEW CODE 2019-02-04 JM
            //echo 'https://maps.google.com/maps/api/geocode/json?key='.CUSTOMER_GOOGLE_JSON_KEY.'&address=' . $prep . '&sensor=false';
            // END NEW CODE 2019-02-04 JM
            $output= json_decode($geocode, 1);

            $up = array();
            // Navigate down the object that was returned as JSON, to find latitude & longitude.
            // Put those in $up, and use that to update the DB table.
            if (is_array($output)){
                if (isset($output['results'])){
                    $results = $output['results'];
                    if (is_array($results)){
                        if (count($results)){
                            $address = $results[0];
                            if (is_array($address)){
                                if (isset($address['geometry'])){
                                    $geometry = $address['geometry'];
                                    if (is_array($geometry)){
                                        if (isset($geometry['location'])){
                                            $loc = $geometry['location'];
                                            if (is_array($loc)){
                                                if (isset($loc['lat']) & isset($loc['lng'])){
                                                    $up['latitude'] = $loc['lat'];
                                                    $up['longitude'] = $loc['lng'];

                                                    /* JM 2020-03-17
                                                       We've been reworking this function 2020-03 for version 2020-2.
                                                       Prior to that rework this had
                                                        $_REQUEST['latitude'] = $loc['lat'];
                                                        $_REQUEST['longitude'] = $loc['lng'];
                                                       Obviously, manipulating a superglobal like that is not generally a good idea, and
                                                       it is not immediately clear whether this was necessary or even useful, but
                                                       it strongly suggests that there at least WAS tight coupling with code elsewhere that
                                                       has to be fixed so that this can be replaced with a cleaner equivalent. Unfortunately, this is being
                                                       done while our Google API account is broken, so we cannot do completely realistic testing.
                                                       JM did a bunch of investigation 2020-03-17, and is reasonably convinced he has fixed all
                                                       cases where it could be relevant, but >>>00013 when the Google API comes back to life
                                                       expect to test this carefully.
                                                   */
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $up['googleGeo'] = trim($geocode);
                        $this->update($up);
                    }
                }
            }
        }
    } // END private function getGeo

    // Set primary key
    // INPUT $val: primary key (locationId)
    private function setLocationId($val) {
        if(($val != null) && (is_numeric($val)) && ($val >=1)){
            $this->locationId = intval($val);
        } else {
            $this->logger->error2("637406047993250617", "Invalid input for locationId : [$val]" );
        }
    }
    // Set customerId
    // INPUT $val: foreign key to Customer table; as of 2019-02, only customer is SSS
    private function setCustomerId($val) {
        if (Customer::validate($val)) {
            $this->customerId = intval($val);
        } else {
            $this->logger->error2("637406055787936414", "Invalid input for CustomerId : [$val]" );
        }
    }

    // Set location
    // INPUT $val: arbitrary location name.
    public function setName($val) {
        $val = truncate_for_db($val, 'Location name', 128, '637356983090935938');
        $this->name = $val;
    }
    // INPUT $val: first line of street address
    public function setAddress1($val) {
        $val = truncate_for_db($val, 'Location Address1', 128, '637356994189032733');
        $this->address1 = $val;
    }
    // INPUT $val: second line of street address
    public function setAddress2($val) {
        $val = truncate_for_db($val, 'Location Address2', 128, '637356994512434330');
        $this->address2 = $val;
    }
    // INPUT $val: suite number if multi-suite building
    public function setSuite($val) {
        $val = truncate_for_db($val, 'Location Suite', 16, '637357750274291588');
        $this->suite = $val;
    }
    // INPUT $val: city name
    public function setCity($val) {
        $val = truncate_for_db($val, 'Location City', 64, '637357750725088810');
        $this->city = $val;
    }
    // INPUT $val: U.S. state (oddly, not restricted to 2-digit codes)
    public function setState($val) {
        $val = truncate_for_db($val, 'Location State', 64, '637357751147683784'); // varchar(64) in Location table.
        $this->state = $val;
    }
    // INPUT $val: country (oddly, restricted to 2-digit codes)
    public function setCountry($val) {
        $val = truncate_for_db($val, 'Location Country', 2, '637357753814452103'); // char(2) in Location table.
        $this->country = $val;

    }
    // INPUT $val: Postal code. 64 chars allows for a lot besides U.S.
    public function setPostalCode($val) {
        $val = truncate_for_db($val, 'Location Postal Code', 64, '637357754286184248');
        $this->postalCode = $val;
    }
    // INPUT $val: decimal latitude
    public function setLatitude($val) {
        if (preg_match('/^[+-]?(([1-8]?[0-9])(\.[0-9]{1,8})?|90(\.0{1,8})?)$/', $val)) {
            $this->latitude = $val;
        } else {
            $this->latitude = 0;
            $this->logger->warn2('637357777984084534', "Invalid Latitude format ");
        }

        //$val = preg_replace("/[^0-9.-]/", "", $val); // >>>00002: silently removes any "bad" characters, doesn't log
                                                     // on removal, and still lets through crap like "8.8.8.8".
    }
    // INPUT $val: decimal longitude
    public function setLongitude($val) {
        if (preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $val)) {
            $this->longitude = $val;
        } else {
            $this->longitude = 0;
            $this->logger->warn2('637357803960921152', "Invalid Longitude format ");
        }
        //$val = preg_replace("/[^0-9.-]/", "", $val); // >>>00002: silently removes any "bad" characters, doesn't log
                                                     // on removal, and still lets through crap like "8.8.8.8".
    }

    // INPUT $val: a big hunk of JSON returned by Google
    public function setGoogleGeo($val) {
        $val = trim($val);
        $this->googleGeo = $val;
    }

    // RETURNS primary key
    public function getLocationId() {
        return $this->locationId;
    }
    // The same, known to SSSEng class
    public function getId() {
        return $this->locationId;
    }
    // RETURNS foreign key into DB table customer; as of 2019-02, only customer is SSS
    public function getCustomerId() {
        return $this->customerId;
    }
    // RETURNS location name
    public function getName() {
        return $this->name;
    }
    // RETURNS first line of street address
    public function getAddress1() {
        return $this->address1;
    }
    // RETURNS second line of street address
    public function getAddress2() {
        return $this->address2;
    }
    // RETURNS suite number if multi-suite building
    public function getSuite() {
        return $this->suite;
    }
    // RETURNS city
    public function getCity() {
        return $this->city;
    }
    // RETURNS U.S. state: apparently no guarantee of this being 2-digit code.
    public function getState() {
        return $this->state;
    }
    // RETURNS country
    public function getCountry() {
        return $this->country;
    }
    // RETURNS postal code
    public function getPostalCode() {
        return $this->postalCode;
    }
    // RETURNs latitude as decimal number, DECIMAL(11,8)
    public function getLatitude() {
        return $this->latitude;
    }
    // RETURNs longitude as decimal number, DECIMAL(11,8)
    public function getLongitude() {
        return $this->longitude;
    }
    // RETURNs big hunk of JSON returned by Google
    public function getGoogleGeo() {
        return $this->googleGeo;
    }
    // RETURNs formatted address: >>>00001 someone can feel free to document
    //  details, but it's pretty much what it says.
    public function getFormattedAddress() {
        $line1 = '';

        if (strlen(trim($this->getAddress1()))){
            $line1 = trim($this->getAddress1());
        }
        if (strlen(trim($this->getAddress2()))){
            if (strlen($line1)){
                $line1 .= "\n";
            }
            $line1 .= trim($this->getAddress2());
        }
        if (strlen(trim($this->getSuite()))){
            if (strlen($line1)){
                $line1 .= " ";
            }
            $line1 .= trim($this->getSuite());
        }
        if (strlen(trim($this->getCity()))){
            if (strlen($line1)){
                $line1 .= "\n";
            }
            $line1 .= trim($this->getCity());
        }
        if (strlen(trim($this->getState()))){
            if (strlen($line1)){
                if ($this->getCity()){
                    $line1 .= ", ";
                } else {
                    $line1 .= " ";
                }
            }
            $line1 .= trim($this->getState());
        }
        if (strlen(trim($this->getPostalCode()))){
            if (strlen($line1)){
                $line1 .= " ";
            }
            $line1 .= trim($this->getPostalCode());
        }

        return $line1;
    }


    /** Update several values for this location.
    * @param array $val. INPUT $val typically comes, at least indirectly, from $_REQUEST.
    *  An associative array containing the following elements
    * 'name'
    * 'address1'
    * 'address2'
    * 'suite'
    * 'city'
    * 'state'
    * 'country'
    * 'postalCode'
    * 'latitude'
    * 'longitude'
    * 'googleGeo'
    * 'customerId' - added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52
    *  Any or all of these may be present.
    * @return bool true on success, false otherwise.
    */
    public function update($val) {

        $verifySave = false;
        if (is_array($val)) {
            if (isset($val['name'])) {
                $this->setName($val['name']);
            }
            if (isset($val['address1'])) {
                $this->setAddress1($val['address1']);
            }
            if (isset($val['address2'])) {
                $this->setAddress2($val['address2']);
            }
            if (isset($val['suite'])) {
                $this->setSuite($val['suite']);
            }
            if (isset($val['city'])) {
                $this->setCity($val['city']);
            }
            if (isset($val['state'])) {
                $this->setState($val['state']);
            }
            if (isset($val['country'])) {
                $country = isset($val['country']) ? $val['country'] : '';
                $this->setCountry($country);
            }
            if (isset($val['postalCode'])) {
                $this->setPostalCode($val['postalCode']);
            }
            if (isset($val['latitude'])) {
                $this->setLatitude($val['latitude']);
            }
            if (isset($val['longitude'])) {
                $this->setLongitude($val['longitude']);
            }
            if (isset($val['googleGeo'])) {
                $this->setGoogleGeo($val['googleGeo']);
            }
            if (isset($val['customerId'])) {
                $this->setCustomerId($val['customerId']);
            }
            $verifySave = $this->save();
        } else {
            $this->logger->error2('637215231463016445', "Location::update Input data not array");
        }

        return $verifySave;
    } // END public function update

    // NOTE that it's unusual that this is private; means that anything that uses
    //  a public set function must do something like call public function update
    //  with an empty array to trigger the actual save.
    // RETURN true on success, false otherwise
    private function save() {
        $verify = false;

        $query = " UPDATE " . DB__NEW_DATABASE . ".location  SET ";
        $query .= " name = '" . $this->db->real_escape_string($this->getName()) . "' ";
        $query .= ", address1 = '" . $this->db->real_escape_string($this->getAddress1()) . "' ";
        $query .= ", address2 = '" . $this->db->real_escape_string($this->getAddress2()) . "' ";
        $query .= ", suite = '" . $this->db->real_escape_string($this->getSuite()) . "' ";
        $query .= ", city = '" . $this->db->real_escape_string($this->getCity()) . "' ";
        $query .= ", state = '" . $this->db->real_escape_string($this->getState()) . "' ";
        $query .= ", country = '" . $this->db->real_escape_string($this->getCountry()) . "' ";
        $query .= ", postalCode = '" . $this->db->real_escape_string($this->getPostalCode()) . "' ";
        // BEGIN 2019-11-26 JM: removed quotes around decimal values
        // $query .= ", latitude = '" . $this->db->real_escape_string($this->getLatitude()) . "' ";
        // $query .= ", longitude = '" . $this->db->real_escape_string($this->getLongitude()) . "' ";
        $query .= ", latitude = " . $this->db->real_escape_string($this->getLatitude());
        $query .= ", longitude = " . $this->db->real_escape_string($this->getLongitude());
        // END 2019-11-26 JM: removed quotes around decimal values
        $query .= ", googleGeo = '" . $this->db->real_escape_string($this->getGoogleGeo()) . "' ";
        $query .= ", customerId = " . $this->getCustomerId();  // customerId added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52
        $query .= " WHERE locationId = " . intval($this->getLocationId()) . " ";

        $result = $this->db->query($query);

        if (!$result)  {
            $this->logger->errorDb('637215221413140216', "Save location failed ", $this->db);
        } else {
            $this->logger->info2('637371541637081179', "saveLocation => action success. Affected rows: ". $this->db->affected_rows);
            $verify = true;
        }
        //return boolean for Update
        return $verify;
    }


    /**
    * @param bool $errCode, variable pass by reference. Default value is false.
    *  $errCode is True on query failed.
    * @return array $ret. RETURNs array of returned rows (associative arrays) from JOIN described in code (>>>00001 a bit sloppy:
    *  you'd have to go through the various tables to say exactly what's there); each row also has an additional
    *  array element 'obj', which is a Location object for the current locationId. Ordered by companyId.
    */
    public function getCompanyUsage(&$errCode = false) {
        $ret = array();
        $errCode = false;

        // Use the obvious join to chain from DB table company via companyLocation
        // to location, where the row in location matches the current locationId.
        // Order by companyId.
        // >>>00022 SELECT * on a SQL JOIN, not really a good idea

        /* Cristi 2020-24-09. Comment out.
        $query = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".company c ";

        $query .= " right join " . DB__NEW_DATABASE . ".companyLocation cl on c.companyId = cl.companyId ";
        $query .= " join " . DB__NEW_DATABASE . ".location l on cl.locationId = l.locationId ";
        $query .= " where cl.locationId = " . intval($this->getLocationId()) . " ";
        $query .= " order by c.companyId ";
        // End commented Code.
        */

        // Cristi 2020-24-09. Begin replacement
        $query = " SELECT c.companyId, c.companyName, cl.locationId ";
        $query .= " FROM " . DB__NEW_DATABASE . ".company c ";
        $query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".companyLocation cl ON c.companyId = cl.companyId ";
        $query .= " WHERE cl.locationId = " . intval($this->getLocationId()) . " ";
        $query .= " ORDER BY c.companyId ";
        // End replacement.

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637356856769772353', 'getCompanyUsage => Hard DB error', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['obj'] = new Location($row['locationId']);
                $ret[] = $row;
            }
        }

        return $ret;
    } // END public function getCompanyUsage



    /**
    * @param bool $errCode, variable pass by reference. Default value is false.
    *  $errCode is True on query failed.
    * @return array $ret. ETURNs array of returned rows (associative arrays) from JOIN described in code (>>>00001 a bit sloppy:
    *  you'd have to go through the various tables to say exactly what's there); each row also has an additional
    *  array element 'obj', which is a Location object for the current locationId. Ordered by personId.
    */
    public function getPersonUsage(&$errCode = false) {
        $ret = array();
        $errCode = false;

        // Use the obvious join to chain from DB table person via personLocation
        // to location, where the row in location matches the current locationId.
        // Order by personId.
        $query = " SELECT p.personId, p.firstName, p.lastName, pl.locationId "; // Before was Select *
        $query .= " FROM " . DB__NEW_DATABASE . ".person p ";
        $query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".personLocation pl ON p.personId = pl.personId ";
        //$query .= " join " . DB__NEW_DATABASE . ".location l on pl.locationId = l.locationId "; //Removed George 2020-24-09.
        $query .= " WHERE pl.locationId = " . intval($this->getLocationId()) . " ";
        $query .= " ORDER BY p.personId ";

        $result = $this->db->query($query);

        if(!$result) {
            $this->logger->errorDb('637356868264490414', 'getPersonUsage => Hard DB error', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['obj'] = new Location($row['locationId']);
                $ret[] = $row;
            }
        }

        return $ret;
    } // END public function getPersonUsage



    /**
    * @param bool $errCode, variable pass by reference. Default value is false.
    *  $errCode is True on query failed.
    * @return array $ret. RETURNs array of returned rows (associative arrays) from JOIN described in code (>>>00001 a bit sloppy:
    *  you'd have to go through the various tables to say exactly what's there); job.name is available as 'jobname',
    *  so 'name' will be location.name. Each row also has an additional
    *  array element 'obj', which is a Location object for the current locationId.
    */
    public function getJobUsage(&$errCode = false) {
        $ret = array();
        $errCode = false;

        // Use the obvious join to chain from DB table job via jobLocation
        // to location, where the row in location matches the current locationId; also
        // throw in jobLocationType.
        // Order by jobLocationType.sortorder, which may not produce a completely deterministic
        //  order on the returned array.
        // >>>00022 SELECT * on a SQL JOIN, not really a good idea. At least the conflict on
        //  columns named "name" is dealt with.
        /* BEGIN REPLACED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
        $query = " select *,j.name as jobname ";
        $query .= " from " . DB__NEW_DATABASE . ".job j ";
        $query .= " right join " . DB__NEW_DATABASE . ".jobLocation jl on j.jobId = jl.jobId ";
        $query .= " join " . DB__NEW_DATABASE . ".location l on jl.locationId = l.locationId ";
        $query .= " join " . DB__NEW_DATABASE . ".jobLocationType jlt on jl.jobLocationTypeId = jlt.jobLocationTypeId ";
        $query .= " where jl.locationId = " . intval($this->getLocationId()) . " ";
        $query .= " order by jlt.sortOrder ";
        // END REPLACED JM 2020-05-11
        */
        // BEGIN REPLACEMENT JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
        $query = "SELECT j.locationId, j.jobId, j.name AS jobname, j.number "; // Before was Select *
        $query .= "FROM " . DB__NEW_DATABASE . ".location l ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".job j ON j.locationId = l.locationId ";
        $query .= "WHERE j.locationId = " . intval($this->getLocationId()) . ";";
        // END REPLACEMENT JM 2020-05-11

        // IMPROVED CODE. George 2020-09-14.
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637356818946292897', 'getJobUsage => Hard DB error', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['obj'] = new Location($row['locationId']);
                $ret[] = $row;
            }
        }
        // END IMPROVMENT George 2020-09-14

        return $ret;
    }  // END public function getJobUsage

    // INSERT a location based on the current location object.
    // RETURNs new LocationId on success, false on failure.
    public function cloneLocation() {
        $this->db = DB::getInstance();

        $name = $this->getName();
        $address1 = $this->getAddress1();
        $address2 = $this->getAddress2();
        $suite = $this->getSuite();
        $city = $this->getCity();
        $state = $this->getState();
        $country = $this->getCountry();
        $postalCode = $this->getPostalCode();
        $latitude = $this->getLatitude();
        $longitude = $this->getLongitude();
        $googleGeo = $this->getGoogleGeo();
        $customerId = $this->getCustomerId();  // customerId added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52

        // No need to trim because we already know these are properties of a location.

        if (!strlen($latitude)){
            $latitude = 0;
        }
        if (!strlen($longitude)){
            $longitude = 0;
        }

        $query = " INSERT INTO " . DB__NEW_DATABASE . ".location (name, address1, address2, suite, city, state, country, postalCode, " .
            "latitude, longitude, googleGeo, customerId) VALUES (";
        $query .= " '" . $this->db->real_escape_string($name) . "'";
        $query .= ", '" . $this->db->real_escape_string($address1) . "'";
        $query .= ", '" . $this->db->real_escape_string($address2) . "'";
        $query .= ", '" . $this->db->real_escape_string($suite) . "'";
        $query .= ", '" . $this->db->real_escape_string($city) . "'";
        $query .= ", '" . $this->db->real_escape_string($state) . "'";
        $query .= ", '" . $this->db->real_escape_string($country) . "'";
        $query .= ", '" . $this->db->real_escape_string($postalCode) . "'";
        $query .= ", " . $latitude;
        $query .= ", " . $longitude;
        $query .= ", '" . $this->db->real_escape_string($googleGeo) . "'";
        $query .= ", " . $customerId;  // customerId added 2019-11-26 JM as partial fix to http://bt.dev2.ssseng.com/view.php?id=52
        $query .= ");";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1574459755', "Insert failed cloning location", $this->db);
            return false;
        }

        $locationId = $this->db->insert_id;

        if (intval($locationId)) {
            return $locationId; // THIS IS THE RETURN ON SUCCESS
        }

        $this->logger->errorDb('1574789145', "Insert failed: no insert_id resulted", $this->db);
        return false;
    } // END public function cloneLocation


    /**
    * @param integer $locationId: locationId to validate, should be an integer but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the id is a valid locationId, false if not.
    */
    public static function validate($locationId, $unique_error_id=null) {
        global $db, $logger; //static, so it needs this
        Location::loadDB($db);

        $ret = false;
        $query = "SELECT locationId FROM " . DB__NEW_DATABASE . ".location WHERE locationId=$locationId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578692554', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>