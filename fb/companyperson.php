<?php
/*  fb/companyperson.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to allow various edits to the companyPersonContact table.
    
    PRIMARY INPUT: $_REQUEST['companyPersonId'].

    OPTIONAL INPUT $_REQUEST['act']. Only possible value: 'update' uses any of several optional values: 
        * $_REQUEST['phoneId']
        * $_REQUEST['emailId']
        * $_REQUEST['locationId']
        
    >>>00001 JM: I'll admit I haven't studied this as closely as some: it's long, but doesn't look like there 
    is much tricky going on, so I didn't drill in too deeply.
*/

include '../inc/config.php';
include '../inc/access.php';
include '../includes/header_fb.php';
$companyPersonId = isset($_REQUEST['companyPersonId']) ? intval($_REQUEST['companyPersonId']) : 0;
$companyPerson = new CompanyPerson($companyPersonId);

if ($act == 'update') {
    $db = DB::getInstance();
    
    $query = " delete from " . DB__NEW_DATABASE . ".companyPersonContact ";
    $query .= " where companyPersonId = " . intval($companyPersonId) . " ";
    $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
    
    if (isset($_REQUEST['phoneId'])) {
        if (is_array($_REQUEST['phoneId'])) {
            foreach ($_REQUEST['phoneId'] as $phone) {
                $parts = explode("_", $phone);
                if (count($parts) == 2) {
                    $query = " insert into " . DB__NEW_DATABASE . ".companyPersonContact (companyPersonId, companyPersonContactTypeId, id) values (";
                    $query .= " " . intval ($companyPersonId) . " ";
                    $query .= " ," . intval ($parts[0]) . " ";
                    $query .= " ," . intval ($parts[1]) . ") ";
                    $db->query($query);
                } // >>> 00002 else bad format, should log; similarly for parallel cases below.
            }
        } // >>> 00002 else bad format, should log; similarly for parallel cases below.
    }
    if (isset($_REQUEST['emailId'])) {
        if (is_array($_REQUEST['emailId'])) {
            foreach ($_REQUEST['emailId'] as $email) {
                $parts = explode("_", $email);
                if (count($parts) == 2) {
                    $query = " insert into " . DB__NEW_DATABASE . ".companyPersonContact (companyPersonId, companyPersonContactTypeId, id) values (";
                    $query .= " " . intval ($companyPersonId) . " ";
                    $query .= " ," . intval ($parts[0]) . " ";
                    $query .= " ," . intval ($parts[1]) . ") ";
                    $db->query($query);
                }
            }
        }
    }
    if (isset($_REQUEST['locationId'])) {
        if (is_array($_REQUEST['locationId'])) {
            foreach ($_REQUEST['locationId'] as $location) {
                $parts = explode("_", $location);
                if (count($parts) == 2) {
                    $query = " insert into " . DB__NEW_DATABASE . ".companyPersonContact (companyPersonId, companyPersonContactTypeId, id) values (";
                    $query .= " " . intval ($companyPersonId) . " ";
                    $query .= " ," . intval ($parts[0]) . " ";
                    $query .= " ," . intval ($parts[1]) . ") ";
                    $db->query($query);
                }
            }
        }
    }

?>
    <script>
        parent.$.fancybox.close();
    </script>
<?php
    die();
} // END if ($act == 'update')

if (intval($companyPerson->getCompanyPersonId())) {    
    $contacts = $companyPerson->getContacts();
    $setEmails = array();
    $setPhones = array();
    $setLocations = array();
    
    // BEGIN MARTIN COMMENT
    // biz rules say only one of each can be set.
    // but maybe legacy data has scenarios has more than one set 
    // END MARTIN COMMENT
    
    foreach ($contacts as $contact) {        
        if (($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILPERSON)) {
            $setEmails[] = CPCONTYPE_EMAILPERSON . '_' . $contact['id'];
        }
        if ( ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILCOMPANY)) {
            $setEmails[] = CPCONTYPE_EMAILCOMPANY . '_' . $contact['id'];
        }		
        if (($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONECOMPANY)) {
            $setPhones[] = CPCONTYPE_PHONECOMPANY . '_' . $contact['id'];
        }
        if (($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONEPERSON)) {
            $setPhones[] = CPCONTYPE_PHONEPERSON . '_' . $contact['id'];
        }
        if ($contact['companyPersonContactTypeId'] == CPCONTYPE_LOCATION) {
            $setLocations[] = CPCONTYPE_LOCATION . '_' . $contact['id'];
        }
    }

    echo '<h2>' . $companyPerson->getCompany()->getCompanyName() . '/' . $companyPerson->getPerson()->getFormattedName(0) . '</h2>' . "\n"; // added 2020-02-20 JM
    echo '<center>';
        /*  Table/form with 3 columns, for phone, email, and location, respectively. 
            Icon at the top of each column. Data in columns may be drawn from data for company or person 
            (all person values precede all company values), and is parenthetically labeled accordingly. 
            Radio buttons let you make a selection in each column as to which is to be current for this companyPerson. 
            Once there is a selected value in any given column, there is no way to entirely unselect it 
            (might not be intentional, but Martin says its OK, 2018-03-08.) 
            Submit button is called "Update".
        */
        echo '<form name="updateform" id="updateForm" action="companyperson.php" method="POST">';
            echo '<input type="hidden" name="act" value="update">';
            echo '<input type="hidden" name="companyPersonId" value="' . intval($companyPersonId) . '">';
            echo '<table border="0" cellpadding="5" cellspacing="1" width="95%">';
                echo '<tr>';
                    echo '<td>';
                        echo '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_person_phone.png" />';
                    echo '</td>';
                    echo '<td>';
                        echo '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_person_mail.png" />';
                    echo '</td>';
                    echo '<td>';
                        echo '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_person_location.png" />';
                    echo '</td>';
                echo '</tr>';
                
                echo '<tr>';
                    echo '<td valign="top">';
                        echo '<table border="0" cellpadding="2" cellspacing="1">';            
                            $personphones = $companyPerson->getPerson()->getPhones();
                            $companyphones = $companyPerson->getCompany()->getPhones();
                            
                            foreach ($personphones as $phone) {
                                echo '<tr>';
                                    $checked = ( in_array(CPCONTYPE_PHONEPERSON . '_' .  $phone['personPhoneId']   , $setPhones)   ) ? " checked " : "";
                                    // Here and in parallel cases below, note that HTML INPUT name attribute is set to an array, which
                                    // means that at least in principle it is possible to pass multiple values (though the use of radio buttons
                                    // means that there is no way to newly set multiple values). I (JM) believe this is to cover the case where the DB
                                    // may have old multi-value content that it would not now be legal to set.
                                    echo '<td><input type="radio" id="personPhone'.$phone['personPhoneId'].'" name="phoneId[]" value="' . CPCONTYPE_PHONEPERSON . '_' . $phone['personPhoneId'] . '" ' . $checked . '></td><td>' . $phone['phoneNumber'] . '&nbsp;(Person)</td>';
                                echo '</tr>';
                            }
                            foreach ($companyphones as $phone) {
                                echo '<tr>';
                                    $checked = ( in_array(CPCONTYPE_PHONECOMPANY . '_' .  $phone['companyPhoneId']   , $setPhones)   ) ? " checked " : "";
                                    echo '<td><input type="radio" id="companyPhone'.$phone['companyPhoneId'].'" name="phoneId[]" value="' .  CPCONTYPE_PHONECOMPANY . '_' . $phone['companyPhoneId'] . '" ' . $checked . '></td><td>' . $phone['phoneNumber'] . '&nbsp;(Company)</td>';
                                echo '</tr>';
                            }                        
                        echo '</table>';
                    echo '</td>';
                    
                    echo '<td valign="top">';
                        echo '<table border="0" cellpadding="2" cellspacing="1">';
                            $personemails = $companyPerson->getPerson()->getEmails();
                            $companyemails = $companyPerson->getCompany()->getEmails();
                            
                            foreach ($personemails as $email) {
                                echo '<tr>';
                                    $checked = ( in_array(CPCONTYPE_EMAILPERSON . '_' .  $email['personEmailId']  , $setEmails)   ) ? " checked " : "";
                                    echo '<td><input type="radio" id="personEmail'. $email['personEmailId'] .'"   name="emailId[]" value="' . CPCONTYPE_EMAILPERSON . '_' . $email['personEmailId'] . '" ' . $checked . '></td><td>' . $email['emailAddress'] . '&nbsp;(Person)</td>';
                                echo '</tr>';
                            }
                            foreach ($companyemails as $email) {
                                echo '<tr>';
                                    $checked = ( in_array(CPCONTYPE_EMAILCOMPANY . '_' .  $email['companyEmailId']  , $setEmails)   ) ? " checked " : "";
                                    echo '<td><input type="radio" id="companyEmail'. $email['companyEmailId'] .'"    name="emailId[]" value="' . CPCONTYPE_EMAILCOMPANY . '_' . $email['companyEmailId'] . '" ' . $checked . '></td><td>' . $email['emailAddress'] . '&nbsp;(Company)</td>';
                                echo '</tr>';
                            }
                        echo '</table>';
                    echo '</td>';	
                    
                    echo '<td valign="top">';
                        echo '<table border="0" cellpadding="2" cellspacing="1">';
                        $personlocations = $companyPerson->getPerson()->getLocations();
                        $companylocations = $companyPerson->getCompany()->getLocations();
                            
                        foreach ($personlocations as $location) {
                            echo '<tr>';
                                $loc = new Location($location['locationId']);
                                $checked = ( in_array(CPCONTYPE_LOCATION . '_' .  $location['locationId']  , $setLocations)   ) ? " checked " : "";
                                echo '<td><input type="radio"  id="personLocation'. $location['locationId'] .'"   name="locationId[]" value="' . CPCONTYPE_LOCATION . '_' . $location['locationId'] . '" ' . $checked . '></td><td>' . $loc->getFormattedAddress() . '&nbsp;(Person)</td>';
                            echo '</tr>';
                        }
                        foreach ($companylocations as $location) {
                            echo '<tr>';
                                $loc = new Location($location['locationId']);                                
                                $checked = ( in_array(CPCONTYPE_LOCATION . '_' .  $location['locationId']  , $setLocations)   ) ? " checked " : "";
                                echo '<td><input type="radio"  id="companyLocation'. $location['locationId'] .'"  name="locationId[]" value="' . CPCONTYPE_LOCATION . '_' . $location['locationId'] . '" ' . $checked . '></td><td>' . $loc->getFormattedAddress() . '&nbsp;(Company)</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    echo '</td>';
                echo '</tr>';
                echo '<tr>';
                    echo '<td colspan="2" align="center"><input type="submit" id="updateContacts" value="update" border="0">';
                echo '</tr>';            
            echo '</table>';	
        echo '</form>';
    echo '</center>';        
}

include '../includes/footer_fb.php';
?>