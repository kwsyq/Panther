<?php
/*  ajax/helpajax.php

    INPUT $_REQUEST['helpId']: supported values are:
        * 'timeSheet'
        * 'workOrder'
    NOTE that the input is case-sensitive. On any other input, apparently returns a PHP error message, because $text will be undefined.    

    As of 2019-05 this is mainly a placeholder: a place to write
    help of whatever sort, available via AJAX. Content is echoed as simple text.
*/

$helpId = isset($_REQUEST['helpId']) ? $_REQUEST['helpId'] : ''; 

if ($helpId == 'timeSheet') {
$text = <<<EOF
<p style="text-align:left;font-size:90%;">
Click a box to enter time into it.<br>
A window pops up to allow you to increase<br>
and decrease the time by a quarter or full<br>
hour at a time.<br>
These are sent to the server as you press<br>
them so there is no need to press anything<br>
else to commit these updates.
<br>The "tally" DOES need a button press to<br>
submit the data! (arrow button)</p>
EOF;
}

if ($helpId == 'workOrder'){
$text = <<<EOF
<p style="text-align:left;font-size:90%;">
Some basic info about interacting with<br>
the stuff on the work order page<br></p>
EOF;
}

echo $text;
die();
?>