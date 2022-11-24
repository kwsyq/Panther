<?php
/*  _admin/serviceload/serviceload.php

    EXECUTIVE SUMMARY: PAGE to display all serviceloads, allow a serviceload to be opened in the serviceloadvar frame.
    
    No primary input because this looks at all serviceloads.
*/

include '../../inc/config.php';

?>
<html>
<head>
</head>
<body bgcolor="#ffffff">
    <script type="text/javascript">
        <?php /* make sure sibling frame serviceLoadVar is loaded */ ?>
        parent.frames['serviceLoadVar'].location.href = 'serviceloadvar.php';
    </script>
    <h2>Service Load</h2>
    <?php
    $serviceLoads = getServiceLoads();
    foreach ($serviceLoads as $serviceLoad) {    
        echo '<a target="serviceLoadVar" href="serviceloadvar.php?t=' . time() . 
             '&serviceLoadId=' . $serviceLoad->getServiceLoadId() . '">' . $serviceLoad->getLoadName(). '</a>';
             echo '<br>';
    }
?>

</body>
</html>