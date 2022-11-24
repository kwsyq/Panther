<?php 
/*  _admin/taskpackage/packagelist.php

    EXECUTIVE SUMMARY: PAGE to list (and manage) all taskPackages. 
    
    No primary input because it shows all taskPackages.
    
    INPUT: : Optional $_REQUEST['act'], possible values are:
        * 'deletepackage' takes additional input:
            * $_REQUEST['taskPackageId'].
        * 'addtaskpackage' takes additional input:
            * $_REQUEST['packageName']. 
*/

include '../../inc/config.php';

$db = DB::getInstance();

if ($act == 'deletepackage') {
    $taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;
    
    // >>>00028: it would make more sense for the two SQL queries here to be in a single transaction. 
    $query = " delete from " . DB__NEW_DATABASE . ".taskPackageTask ";
    $query .= " where taskPackageId = " . intval($taskPackageId) . " ";    
    $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance
    
    $query = " delete from " . DB__NEW_DATABASE . ".taskPackage ";
    $query .= " where taskPackageId = " . intval($taskPackageId) . " ";
    $db->query($query);
    
    ?>
    <script type="text/javascript">
        <?php /* reload the other two frames. Random numbers are to prevent caching. */ ?>
        parent.window.frames['packageItems'].location = 'packageitems.php?taskPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
        parent.window.frames['packageTaskSelect'].location = 'packagetaskselect.php?taskPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
    </script>    
    <?php     
}

if ($act == 'addtaskpackage') {
    // Brand new taskPackage.
    $packageName = isset($_REQUEST['packageName']) ? $_REQUEST['packageName'] : '';
    $packageName = trim($packageName);
    
    if (strlen($packageName)) {        
        $query = " insert into " . DB__NEW_DATABASE . ".taskPackage (packageName) values (";
        $query .= " '" . $db->real_escape_string($packageName) . "') ";
        
        $db->query($query);

        ?>
        <script type="text/javascript">
            <?php /* reload the other two frames. Random numbers are to prevent caching. */ ?>
            parent.window.frames['packageItems'].location = 'packageitems.php?taskPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
            parent.window.frames['packageTaskSelect'].location = 'packagetaskselect.php?taskPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;        
        </script>
        <?php
    }
}

$packages = array();

$query = " select * from  " . DB__NEW_DATABASE . ".taskPackage order by packageName ";
if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
    }
}

?>
<html>
<head>
    <script type="text/javascript">
        // Just load a particular taskPackage into frames
        // INPUT taskPackageId: primary key to DB table TaskPackage
        var seePackage = function (taskPackageId) {
            <?php /* reload the other two frames. Random numbers are to prevent caching. */ ?>
            parent.window.frames['packageItems'].location = 'packageitems.php?taskPackageId=' + escape(taskPackageId);
            parent.window.frames['packageTaskSelect'].location = 'packagetaskselect.php?taskPackageId=' + escape(taskPackageId) + '&t=' + Math.random() * (20000000 - 10000000) + 10000000;
        }
    </script>
</head>
<body bgcolor="#eeeeee">
    <?php
    // Table, no headings, one row for each taskPackage
    echo '<table border="1" cellpadding="5" cellspacing="0" width="100%">';    
        foreach ($packages as $package) {            
            echo '<tr>';
                // Link, displays package name. Clicking loads the other frames.
                echo '<td width="100%"><a href="javascript:seePackage(' . intval($package['taskPackageId']) . ')">' . $package['packageName'] . '</a></td>';
                
                // Link, displays "[del]", clicking deletes the taskPackage entirely
                // >>>00032: I (JM) would expect a confirmation here.
                echo '<td>[<a href="packagelist.php?act=deletepackage&taskPackageId=' . intval($package['taskPackageId']) . '">del</a>]</td>';
            echo '</tr>';            
        }    
    echo '</table>';
    
    echo '<hr>';
    
    // Form to add a taskPackage. User gives it a name & clicks "add task package".
    // Side effect: loads the other two frames accordingly.
    // >>>00012 form name has nothing to do with what the form does.
    echo '<form name="adddescriptorcategory" action="packagelist.php" method="post">';
        echo '<input type="hidden" name="act" value="addtaskpackage">';
        echo '<input type="text" name="packageName" value="">';
        echo '<input type="submit" value="add task package">';
    echo '</form>';
    ?>
</body>
</html>