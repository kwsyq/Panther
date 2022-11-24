<?php
/*  _admin/phone/phone.php

    EXECUTIVE SUMMARY: Manage employee phones. 
    
    No primary input, because it manages all employee phones.

    Optional INPUT $_REQUEST['act'] has possible values: 
        * 'addextension' takes additional inputs:
            * $_REQUEST['personId']
            * $_REQUEST['phoneExtensionTypeId']
        * 'updaterecord' takes additional inputs: 
            * $_REQUEST['phoneExtensionId']
            * $_REQUEST['extension']
            * $_REQUEST['description']

    Significantly reorganized 2019-12 JM, mostly to clean up some awful HTML.
    I did not leave remarks about most specific changes because there were so many.
*/

include '../../inc/config.php';
include '../../inc/access.php';

$db = DB::getInstance();    
    
if ($act == 'addextension') {
    // Insert a row into DB table phoneExtension with the specified personId and phoneExtensionTypeId.    
    $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
    $phoneExtensionTypeId = isset($_REQUEST['phoneExtensionTypeId']) ? intval($_REQUEST['phoneExtensionTypeId']) : 0;    

    $query = " insert into " . DB__NEW_DATABASE . ".phoneExtension (personId, phoneExtensionTypeId) values (";
    $query .= " " . intval($personId) . " ";
    $query .= " ," . intval($phoneExtensionTypeId) . ") ";

    // echo $query; // REMOVED 2019-12-09 JM: presumably was debug but probably messes up the return.
    $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance    
}

/*  INPUT $personId should be primary key in DB table Person for an employee of the current customer.
    Return an array representing all phone extensions for that person; for each extension, we return an
    associative array containing:
        * phoneExtensionTypeId: primary key in DB table phoneExtensionType 
        * extensionType (string internal ID for extension type, used in code)
        * extensionTypeDisplay
        * displayOrder (within phone extension types)
        * phoneExtensionId: primary key in DB table phoneExtension
        * extension: digits of extension
        * description: straight text, arbitrary
      */
function getExtensions($personId) {    
    global $db;
    
    $extensions = array();
    
    $query = " select pe.phoneExtensionTypeId, pet.extensionType, pet.extensionTypeDisplay, pet.displayOrder, pe.phoneExtensionId, pe.extension, pe.description ";
    $query .= " from " . DB__NEW_DATABASE . ".phoneExtension pe ";
    $query .= " join " . DB__NEW_DATABASE . ".phoneExtensionType pet on pe.phoneExtensionTypeId = pet.phoneExtensionTypeId ";
    $query .= " where pe.personId = " . intval($personId);
    $query .= " order by pet.displayOrder asc, pe.phoneExtensionId ";
    
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $extensions[] = $row;
            }
        }
    }    
    return $extensions;
}

if ($act == 'updaterecord') {
    // If the extension has non-zero length, we update the row in DB table phoneExtension with the 
    //  relevant phoneExtensionId, setting extension and description. 
    // If the extension is blank, then we simply delete the row in DB table phoneExtension with the relevant phoneExtensionId.
    $phoneExtensionId = isset($_REQUEST['phoneExtensionId']) ? intval($_REQUEST['phoneExtensionId']) : 0;
    $extension = isset($_REQUEST['extension']) ? $_REQUEST['extension'] : '';
    $description = isset($_REQUEST['description']) ? $_REQUEST['description'] : '';
    
    $extension = trim($extension);
    $extension = substr($extension, 0, 16); // >>>00002 truncates silently
    $description = trim($description);
    $description = substr($description, 0, 128); // >>>00002 truncates silently
    
    if (strlen($extension)) {
        $query = " update " . DB__NEW_DATABASE . ".phoneExtension  set ";
        $query .= " extension = '" . $db->real_escape_string($extension) . "' ";
        $query .= " ,description = '" . $db->real_escape_string($description) . "' ";
        $query .= " where phoneExtensionId = " . intval($phoneExtensionId);
        
    } else {
        $query = " delete from  " . DB__NEW_DATABASE . ".phoneExtension  ";
        $query .= " where phoneExtensionId = " . intval($phoneExtensionId);
    }
    
    $db->query($query);   
}

$types = array();
$employees = array();

/* Get a full list of employees, past and present (alphabetical by last-name-first order) 
   for the current customer (as of 2019-05, always SSS). */
$es = getEmployees($customer);

/* For each employee, get all phone extensions; for each extension, we identify the extensionType. */
foreach ($es as $employee) {
    $extensions = getExtensions($employee['personId']);
    foreach ($extensions as $exkey => $extension) {        
        $types[$extension['extensionTypeDisplay']] = $extension['extensionType'];
    }
    $employee['extensions'] = $extensions;    
    $employees[] = $employee;
}
ksort($types); // Make the order predictable - ADDED JM 2019-12-10

// Rearrange $employees to put all current employees before all past employees,
//  otherwise maintaining alphabetical order.
$current_employees = array();
$past_employees = array();

foreach ($employees as $e) {    
    $term_date = new DateTime($e['terminationDate']);
    $current_date = new DateTime();
    
    if ($current_date > $term_date) {
        $past_employees[] = $e;
    } else {
        $current_employees[] = $e;
    }        
}

$employees = array();

foreach ($current_employees as $employee) {
    $employees[] = $employee;
}
foreach ($past_employees as $employee) {
    $employees[] = $employee;
}

?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php
echo 'NOTE : to remove an extension blank the extension box and press update.<p>' . "\n";

echo '<hr>' . "\n";

$extensionTypes = array();

$query = " select * ";
$query .= " from " . DB__NEW_DATABASE . ".phoneExtensionType ";
$query .= " order by displayOrder ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $extensionTypes[] = $row;
        }
    }
}

// Self-submitting form as follows:
//   * (hidden) act='addextension'
//   * HTML SELECT element "personId"; initially no person selected, has value="0" and displays "-- choose person --". 
//     For each current employee there is an OPTION with value=personId and display "lastName, firstName".
//   * HTML SELECT element "phoneExtensionTypeId"; initially no extensionType selected, has value="0" and displays "-- choose type --". 
//     For each extensionType there is an OPTION with value=extensionType and display extensionTypeDisplay.
//   * submit button labeled 'add'. 
echo 'Add a record:<br />' . "\n";    
echo '<form name="newentry" method="POST" action="">' . "\n";
    echo '<input type="hidden" name="act" value="addextension">' . "\n";
    echo '<select name="personId"><option value="0">-- choose person --</option>' . "\n";
        foreach ($current_employees as $employee) {
            echo '<option value="' . $employee['personId'] . '">' . $employee['lastName'] . ", " . $employee['firstName'] . '</option>' . "\n";
        }
    echo '</select>' . "\n";
    
    echo '<select name="phoneExtensionTypeId"><option value="0">-- choose type --</option>' . "\n";
    foreach ($extensionTypes as $extensionType) {
        echo '<option value="' . $extensionType['phoneExtensionTypeId'] . '">' . $extensionType['extensionTypeDisplay'] . '</option>' . "\n";
    }
    echo '</select>' . "\n";
    echo '<input type="submit" value="add" border="0">' . "\n";    
echo '</form>' . "\n";
echo '<hr>' . "\n";

echo '<table border="1" cellpadding="4" cellspacing="0">' . "\n";
    echo '<tr>';
        echo '<th>Name</th>';
        // One column for each extension type
        foreach ($types as $tkey => $type) {
            echo '<th>' . $tkey . '</th>';
        }
    echo '</tr>' . "\n";
    
    /*
        (Column-headers were written above.)
        A row for each employee, past or present, with a muted green background for current employees and a muted red background for past employees.

        First column "Name": firstName lastName
        All further columns are based on what extension types are defined in DB table phoneExtensionType. As of 2019-05 they are:
         "Hard", "Soft Mobile", "Soft Computer", "Intercom". Each of these columns is potentially multi-valued for a single employee, 
            which is handled through embedded tables. For each extension, we have a self-submitting form:
            * (hidden) act='updaterecord'
            * (hidden) phoneExtensionId
            * (hidden) phoneExtensionTypeId
            * text input "extension" (5 characters)
            * text input "description" (20 characters)
            * submit button labeled 'update' 
    */
    foreach ($employees as $employee) {
        $extensions = $employee['extensions'];
    
        $term_date = new DateTime($employee['terminationDate']);
        $current_date = new DateTime();
        $bgcolor="";
    
        if ($current_date > $term_date) {
            $bgcolor = "#cc9999"; // muted red background for past employees.
        } else {
            $bgcolor = "#66cc66"; // muted green background for current employees 
        }   
    
        echo '<tr bgcolor="' . $bgcolor . '" >' . "\n";
            echo '<td>';
                echo $employee['firstName'];
                echo '&nbsp;';
                echo $employee['lastName'];
            echo '</td>' . "\n";		

            foreach ($types as $tkey => $type) {
                echo '<td>';
                    // Nested table. 
                    echo '<table border="0" cellpadding="3" cellspacing="0">' . "\n";            
                        foreach ($employee['extensions'] as $extension) {                        
                            if ($extension['extensionTypeDisplay'] == $tkey) {
                                // NOTE that there is no officially clean way to mix rows and forms, but all known browsers should cope just fine. 
                                echo '<tr>' . "\n";
                                echo '<form name="ff_' . $extension['phoneExtensionId'] . '" action="" method="post">' . "\n";
                                    echo '<input type="hidden" name="act" value="updaterecord">' . "\n";
                                    echo '<input type="hidden" name="phoneExtensionId" value="' . $extension['phoneExtensionId'] . '">' . "\n";
                                    echo '<input type="hidden" name="phoneExtensionTypeId" value="' . $extension['phoneExtensionTypeId'] . '">' . "\n";
                                        echo '<td><input type="text" name="extension" value="' . $extension['extension'] . '" size="5"></td>' . "\n";
                                        echo '<td><input type="text" name="description" value="' . $extension['description'] . '" size="20"></td>' . "\n";
                                        echo '<td><input type="submit" value="update" border="0"></td>' . "\n";
                                echo '</form>' . "\n";
                                echo '</tr>' . "\n";
                            }
                        }
                    echo '</table>';
                echo '</td>' . "\n";
            }
        echo '</tr>' . "\n";
    }

echo '</table>' . "\n";

?>
</body>
</html>
