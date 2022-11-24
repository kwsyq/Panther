<?php
/*  _admin/descriptorpackage/packagelist.php

    EXECUTIVE SUMMARY: PAGE to list (and manage) all taskPackages. 
    
    No primary input because it shows all taskPackages.
    
    OTHER INPUT: Optional $_REQUEST['act'], possible values are:
        * 'deletepackage' takes additional input:
            * $_REQUEST['descriptorPackageId'].
        * 'adddescriptorpackage' takes additional input:
            * $_REQUEST['packageName']. 
*/

include '../../inc/config.php';

$db = DB::getInstance();
$error = '';

if ($act == 'deletepackage') {
    $descriptorPackageId = isset($_REQUEST['descriptorPackageId']) ? intval($_REQUEST['descriptorPackageId']) : 0;
    
    if ( ! DescriptorPackage::delete($descriptorPackageId) ) {
        $error = "Deleting DescriptorPackage $descriptorPackageId failed"; 
    }
    
    // Redisplay the other two frames as "no package selected", then fall through to redisplay this frame.
    ?>
    <script type="text/javascript">
        parent.window.frames['packageItems'].location = 'packageitems.php?descriptorPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
        parent.window.frames['packageSubSelect'].location = 'packagesubselect.php?descriptorPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
    </script>
    
    <?php
}

if ($act == 'adddescriptorpackage') {
    $packageName = isset($_REQUEST['packageName']) ? $_REQUEST['packageName'] : '';
    $packageName = trim($packageName);
    
    if (strlen($packageName)) {
        if ( ! DescriptorPackage::add($packageName) ) {
            $error = "Adding DescriptorPackage '$packageName' failed";
        }

        // Redisplay the other two frames as "no package selected", then fall through to redisplay this frame.
        ?>
        <script type="text/javascript">        
            parent.window.frames['packageItems'].location = 'packageitems.php?descriptorPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
            parent.window.frames['packageSubSelect'].location = 'packagesubselect.php?descriptorPackageId=0&t=' + Math.random() * (20000000 - 10000000) + 10000000;
        </script>
        <?php 
    }
}

$packages = DescriptorPackage::getAll();
if ($packages === false) {
    $error = "Getting all packages failed";
}
?>
<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript">
    // INPUT descriptorPackageId
    // Load the other two frames, based on this descriptorPackageId 
    var seePackage = function (descriptorPackageId) {
        parent.window.frames['packageItems'].location = 'packageitems.php?descriptorPackageId=' + escape(descriptorPackageId);
        
        // For packagesubselect.php, we append a random string to prevent caching.
        parent.window.frames['packageSubSelect'].location = 
            'packagesubselect.php?descriptorPackageId=' + escape(descriptorPackageId) + '&t=' + Math.random() * (20000000 - 10000000) + 10000000;    
    }
    </script>
</head>
<body bgcolor="#eeeeee">
<?php
    if ($error) {
        echo "<p>$error</p>\n";
    }

    /* Table with a row for each descriptorPackage, in package name order, using the following columns (no headers):
        * Display packageName with a link to call local function seePackage(descriptorPackageId). 
          If clicked, loads the other two frames, using this ID. 
        * Display '[del]', with a link to self-submit using GET method to delete the package corresponding to this row. 
    */
    echo '<table border="1" cellpadding="5" cellspacing="0" width="100%">' . "\n";    
        foreach ($packages as $package) {        
            echo '<tr>' . "\n";
                echo '<td width="100%"><a href="javascript:seePackage(' . intval($package->getDescriptorPackageId()) . ')">' . 
                    $package->getPackageName() . '</a></td>' . "\n";
                echo '<td>[<a href="packagelist.php?act=deletepackage&descriptorPackageId=' . intval($package->getDescriptorPackageId()) . '">del</a>]</td>' . "\n";
            echo '</tr>' . "\n";
        }
        
        echo '</table>' . "\n";
    echo '<hr>' . "\n";
    
    /* form to self-submit (using a POST) to add a new descriptorPackage: */
    echo '<form name="adddescriptorcategory" action="packagelist.php" method="post">' . "\n";
        echo '<input type="hidden" name="act" value="adddescriptorpackage">' . "\n";
        echo '<input type="text" name="packageName" value="">' . "\n";
        echo '<input type="submit" value="add descriptor package">' . "\n";
    echo '</form>' . "\n";
?>
</body>
</html>