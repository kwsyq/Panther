<?php 
/*  _admin/private/index.php

    EXECUTIVE SUMMARY: PAGE to manage one-off private URLs that let outsiders look at particular data
    drawn from the system.
    
    No primary input: unless we are taking an action, we are basically just displaying a "generic" form.

    Other INPUT: Optional $_REQUEST['act'], only supported value is:
        * 'genurl': takes additional inputs 
            * $_REQUEST['privateTypeId']
            * $_REQUEST['id'].
            
    >>>00032: AS OF 2019-05, this apparently isn't really being used, but it's expected to "revive"        
*/

include '../../inc/config.php';
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php
if ($act == 'genurl') {
    $privateTypeId = isset($_REQUEST['privateTypeId']) ? intval($_REQUEST['privateTypeId']) : 0;
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if ($privateTypeId && $id) {
        /* use our own code (rather than relying on the DB system to auto-increment) to get 
           a privateId exactly one higher than the highest one currently used in DB table private. */
        $db = DB::getInstance();        
        $token = 0;        
        $query  = " select max(privateId) as maxid from " . DB__NEW_DATABASE . ".private ";        
        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $token = $row['maxid'];
                }
            }
        } // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance
        
        /* Insert into DB table private, with the privateId we just inserted and the user-chosen privateTypeId and id. 
           Sign the request, creating a hash for a one-off URL, then echo that URL 
           http://domain/c/job.php?hash. This URL is good for a limited time, driven by how we build the hash. */
        $token += rand(10,50);        
        $query = " insert into " . DB__NEW_DATABASE . ".private (privateId, privateTypeId, id) values (";
        $query .= " " . intval($token) . " ";
        $query .= " ," . intval($privateTypeId) . " ";
        $query .= " ," . intval($id) . ") ";
        
        $db->query($query);
        
        $params = array();
        $params['e'] = time() + 30;
        $params['t'] = $token;
        
        $qs = signRequest($params, PRIVATE_HASH_KEY);
                    
        if ($privateTypeId == PRIVTYPE_OPEN_WO) {
            echo REQUEST_SCHEME . '://' . HTTP_HOST . '/c/job.php?' . $qs . "<br /><br />\n";
        }
    }
    // Drop through to re-display the form.
}

/* self-submitting form that uses a table for formatting.
    * (hidden) act='genurl'
    * "Type": HTML SELECT name="privateTypeId"
        * initial OPTION value is an empty string, display is -- choose type --
        * An OPTION for each PRIVTYPE (defined in /config.php, currently only PRIVTYPE_OPEN_WO = 'Open Work Orders'); 
            value is integer defined in /config.php, for PRIVTYPE_OPEN_WO this is 1; display is text, i.e. 'Open Work Orders'.
        * "Id": text input 
    * submit button labeled "generate". 
*/
echo '<form name="gen" action="" method="POST">' . "\n";
    echo '<input type="hidden" name="act" value="genurl">' . "\n";
    echo '<table border="0" cellpadding="4" cellspacing="0">' . "\n";
        echo '<tr>' . "\n";
            echo '<td>Type</td>' . "\n";
            echo '<td>' . "\n";
            echo '<select name="privateTypeId"><option value="">-- choose type --</option>' . "\n";
            foreach ($privTypes as $pkey => $value) {
                echo '<option value ="' . $pkey . '">' . $value . '</option>' . "\n";
            }
            echo '</select>' . "\n";
            echo '</td>' . "\n";
        echo '</tr>' . "\n";
        
        echo '<tr>' . "\n";
            echo '<td>Id</td>' . "\n";
            echo '<td>' . "\n";
                echo '<input type="text" name="id" value="">' . "\n";
            echo '</td>' . "\n";
        echo '</tr>' . "\n";
        echo '<tr>' . "\n";
            echo '<td colspan="2"><input type="submit" value="generate" border="0"></td>' . "\n";
        echo '</tr>' . "\n";
    echo '</table>' . "\n";
echo '</form>' . "\n";

?>
</body>
</html>
