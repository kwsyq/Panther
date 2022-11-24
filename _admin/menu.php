<?php
/* _admin/menu.php

     EXECUTIVE SUMMARY: Used in _admin/index.php. Opens admin pages in the bottom frame of index.php. 
*/

require_once('../inc/config.php');

if (LIVE_SYSTEM) {
    $bgcolor = "#cccccc";
} else {
    $bgcolor = "#e8cccc";
}
?>

<html>
    <head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    </head>
    <body bgcolor="<?php echo $bgcolor; ?>">
<style>
a:hover {
  color: hotpink;
}

</style>
        <table border="1" cellpadding="2" cellspacing="0" width="100%">
            <tr>
                <td colspan="11" style="height: 40px; padding-left: 10px"><a href="/" target="_top" style="font-family: Verdana;text-decoration: none;  border-radius: 5px; margint-left: 45px; background-color: #333; color: #fff;padding: 7px 15px;">Back 2 Panther</a></td>
            </tr>
            <tr>
                <td width="9%" align="center"><a target="bottom_frame" href="media/index.php">Media</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="private/index.php">Private</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="perm/index.php">Permissions</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="flextasks/index.php">Tasks</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="taskpackage/index.php">Task Packages</a></td>	
                <td width="9%" align="center"><a target="bottom_frame" href="teamposition/index.php">Team Positions</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="wrkorderdesctype/index.php">Wrk.Ord.Desc.Type</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="workorderstatus/index.php">Work Order Status</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="descriptor/descriptor2.php">Descriptor</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="serviceload/index.php">Service Load</a></td>
                <td width="9%" align="center"></td>
            </tr>
            <tr>
            
                <td width="9%" align="center"><a target="bottom_frame" href="phone/phone.php">Phone</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="ancillarydata/index.php">Ancillary Data</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="time/time.php">Time</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="holiday/index.php">Holidays</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="contractlanguage/index.php">Contract Language</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="descriptorpackage/index.php">Descriptor Package</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="extrahours/index.php">Hourly Rate</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="employee/index.php">Employee</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="creditrecord/cr.php">Credit Record</a></td>
                <td width="9%" align="center"><a target="bottom_frame" href="invoicestatus/index.php">Invoice Status</a></td>
                <td width="9%" align="center"><?php
                    if (OFFER_BUG_LOGGING) {
                        echo '<a href="'.BUG_LOG.'" target="_blank"><b>LOG A BUG</b></a><br/>'."\n";
                    }
                ?></td>
            </tr>
        </table>
        <script>
        $("a[target='bottom_frame']").click(function() {
            let $this = $(this);
            $("a[target='bottom_frame']").attr('style', 'color:black');
            $this.attr('style', 'color:red'); 
            window.parent.document.title = 'Admin: ' + $this.text();           
        });
        </script>
    </body>
</html>
