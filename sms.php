<?php
/*  sms.php
    
    EXECUTIVE SUMMARY: Page that displays all inbound SMSs (per database table inboundSms). Martin described 
    this in 2018 as "a draft version that will later have some filtering" but he never got back to it. 
    I (JM) was told at that time not to bother looking at all closely at this, but 2019-04
    I'm taking at least a cursory shot at it, since it appears to be part of the production system.
    
    No inputs.
*/    

include './inc/config.php';
include './inc/access.php';

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Inbound SMSs';\n</script>\n";

$crumbs = new Crumbs(null, $user);
?>

<div id="container" class="clearfix">
    <div class="main-content">
        <div class="full-box clearfix">
            <h2 class="heading">SMS</h2>
            <br>
            <?php
            $db = DB::getInstance();
            
            // Obviously work in progress given the following:
            echo 'how do we deal with numbers being lost from people and possibly picked up by other people ??';
            
            echo '<table border="0" >';
                echo '<tr>';
                    echo '<th>&nbsp;</th>';
                    echo '<th>To</th>';
                    echo '<th width="30%">From</th>';
                    echo '<th width="60%">Body</th>';
                    echo '<th>Attach</th>';
        
                echo '</tr>';
    
                // Get all inbound SMS messages in reverse chronological order
                // (Relies on inboundSmsId increasing monotonically over time.)
                // >>>00004 NOTE that this does not consider customer, so once
                //  we go beyond supporting just SSS, this has to be revisited.
                $query = " select * from " . DB__NEW_DATABASE . ".inboundSms ";
                $query .= " order by inboundSmsId desc ";
        
                if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                    if ($result->num_rows > 0){
                        while ($row = $result->fetch_assoc()) {
                            $inbounds[] = $row;
                        }
                    }
                } // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
    
                foreach ($inbounds as $ikey => $inbound) {
                    $bgcolor = ($ikey % 2) ? '#eeeeee' : '#cccccc'; // Alternate background colors
                    
                    echo '<tr bgcolor="' . $bgcolor . '">';    
                        $east = new DateTimeZone("America/New_York");
                        /* OLD CODE REPLACED 2019-02-13
                        if (trim(`hostname`) != 'devssseng'){
                        */
                        // BEGIN NEW CODE 2019-02-13
                        if (environment() == ENVIRONMENT_PRODUCTION) {
                        // END NEW CODE 2019-02-13
                            $east = new DateTimeZone("UTC");  // >>>00012: so variable is poorly named
                        }
                        $pacific = new DateTimeZone("America/Los_Angeles");
                        $date = new DateTime($inbound['inserted'], $east );
                        $date->setTimezone($pacific);
        
                        // (no header): formatted date
                        echo '<td>' . $date->format('m/d/Y H:i:s') . '</td>';
                        
                        // "To": phone number
                        echo '<td>' . $inbound['didTo'] . '</td>';
        
                        $search = trim($inbound['didFrom']);
                        // If it's an 11-character phone number, drop leading digit.
                        // Obviously, that is based on NADS, but >>>00026 really ought
                        //  to make sure the dropped first digit is "1" or "0"
                        if (strlen($search) == 11) {
                            $search = substr($search, 1);
                        }    
    
                        // Get all known phone numbers for persons & companies
                        // >>>00004 NOTE that this does not consider customer, so once
                        //  we go beyond supporting just SSS, this has to be revisited.
                        //  ALSO scaling issue in doing it this way AND we are repeating
                        //  this global query inside a for-loop!
                        
                        // [Martin comment:] clean this shit up !!        
                        $persons = array();
                        $companies = array();
    
                        $db = DB::getInstance(); // >>>00007 completely redundant, already set.
        
                        // >>>00022 SELECT * on a JOIN is not a good idea, should clean this up to specific
                        //  columns we care about.
                        $query  = " select * ";
                        $query .= " from " . DB__NEW_DATABASE . ".personPhone pp ";
                        $query .= " join " . DB__NEW_DATABASE . ".person p on pp.personId = p.personId ";
                        $query .= " where pp.phoneNumber  = '" . $db->real_escape_string($search) . "' ";
        
                        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {        
                                    $persons[] = $row;
                                }
                            }
                        }
        
                        // >>>00022 SELECT * on a JOIN is not a good idea, should clean this up to specific
                        //  columns we care about.
                        $query  = " select * ";
                        $query .= " from " . DB__NEW_DATABASE . ".companyPhone cp ";
                        $query .= " join " . DB__NEW_DATABASE . ".company c on cp.companyId = c.companyId ";
                        $query .= " where cp.phoneNumber  = '" . $db->real_escape_string($search) . "' ";
        
                        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {        
                                    $companies[] = $row;
                                }
                            }
                        }
        
                        // "From": sender phone number, followed by a nested table
                        //   of who that might be.
                        echo '<td>' . $inbound['didFrom'];
                            if (count($persons)){
                                echo '<table border="0" cellpadding="0" cellspacing="0">';
                                    foreach ($persons as $person) {
                                        $p = new Person($person['personId']);
                                        echo '<tr>';
                                            echo '<td>&nbsp;</td>';
                                            echo '<td>[p]</td>'; // 'p' for person
                                            // Name, linked to person page
                                            echo '<td width="100%"><a href="' . $p->buildLink() . '">' . $p->getFormattedName(1) . '</a></td>';
                                            // Link to open iframe to send an SMS response
                                            echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $p->getPersonId() . 
                                                '&from=' . rawurlencode($inbound['didTo']) . '&inboundSmsId=' . intval($inbound['inboundSmsId']) . 
                                                '&to=' . rawurlencode($inbound['didFrom']) . '">' .
                                                '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                        echo '</tr>';
                                    }
                                    foreach ($companies as $company) {
                                        $c = new Company($company['companyId']);
                                        echo '<tr>';
                                            echo '<td>&nbsp;</td>';
                                            echo '<td>[c]</td>'; // 'c' for company
                                            // Name, linked to company page
                                            echo '<td width="100%"><a href="' . $c->buildLink() . '">' . $c->getName() . '</a></td>';
                                            // Link to open iframe to send an SMS response
                                            echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?companyId=' . $c->getCompanyId() . 
                                                '&from=' . rawurlencode($inbound['didTo'])  . '&inboundSmsId=' . intval($inbound['inboundSmsId']) . 
                                                '&to=' . rawurlencode($inbound['didFrom']) . '">' .
                                                '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                        echo '</tr>';
                                    }        
                                echo '</table>';
                            }        
                        echo '</td>';
                        
                        // "Body": body of the incoming message and a table of all prior responses sent.
                        echo '<td>';                            
                            echo $inbound['body'];
                        
                            echo '<table border="1">';                            
                                // [Martin comment:] Get all 
                                $query = " select * ";
                                $query .= " from " . DB__NEW_DATABASE . ".outboundSms ";
                                $query .= " where inboundSmsId = " . intval($inbound['inboundSmsId']) . " ";
                                $query .= " order by outboundSmsId desc ";
        
                                if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                    if ($result->num_rows > 0){
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<tr>';
                                                echo '<td>';
                                                    echo $row['body'];
                                                echo '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                }
                            echo '</table>';
                        echo '</td>';
                        
                        // "Attach": any attached media
                        // >>>00001 Not closely studied - JM 2019-04-04
                        echo '<td>';
                            $media = unserialize($inbound['media']);        
                            if (is_array($media)) {
                                foreach ($media as $m) {
                                    //echo '<img src="' . $m['url'] . '">'; // [COMMENTED OUT BY MARTIN BEFORE 2019]
                                    if (isset($m['attachment'])) {
                                        $attachment = $m['attachment'];
                                        if (($m['mimetype'] == 'image/png') || ($m['mimetype'] == 'image/jpg') || ($m['mimetype'] == 'image/jpeg') ||  
                                            ($m['mimetype'] == 'image/pjpeg') || ($m['mimetype'] == 'image/gif') || ($m['mimetype'] == 'image/bmp')) 
                                        {        
                                            $token = rand(10000000, 90000000);
                                            $params = array();
                                            $params['e'] = time() + 600;
                                            $params['name'] = $attachment['name'];        
                                            $qs = signRequest($params, SMSMEDIA_HASH_KEY);        
                                            $url = REQUEST_SCHEME . '://' . HTTP_HOST . '/i/smsthumb.php?' . $qs;        
                                            $token = rand(10000000, 90000000);
                                            $params = array();
                                            $params['e'] = time() + 30;
                                            $params['name'] = $attachment['thumbname'];        
                                            $qs = signRequest($params, SMSMEDIA_HASH_KEY);        
                                            $thumburl = REQUEST_SCHEME . '://' . HTTP_HOST . '/i/smsthumb.php?' . $qs;        
                                            echo '<a target="_blank" href="' . $url . '"><img src="' . $thumburl . '" width="' . intval($attachment['thumbx']) . '" height="' . intval($attachment['thumby']) . '"></a>';        
                                        } else {
                                            $token = rand(10000000, 90000000);
                                            $params = array();
                                            $params['e'] = time() + 600;
                                            $params['name'] = $attachment['name'];        
                                            $qs = signRequest($params, SMSMEDIA_HASH_KEY);        
                                            $url = REQUEST_SCHEME . '://' . HTTP_HOST . '/i/smsthumb.php?' . $qs;        
                                            echo '<a target="_blank" href="' . $url . '">' . $m['filename'] . '</a>';        
                                        }
                                    }
                                }
                            } // END if (is_array($media))
                        echo '</td>';
                    echo '</tr>';
                } // END foreach ($inbounds...    
            echo '</table>';
    
            /*
            // [BEGIN MARTIN COMMENT]
    
            [attachment] => Array
        (
            [name] => 29_0.jpg
            [x] => 1536
            [y] => 2048
            [thumbname] => 29_0_thumb.jpg
            [thumbx] => 90
            [thumby] => 120
        )
            // [END MARTIN COMMENT]
    
            */    
    
            ?>
        </div>
    </div>
</div>
<?php
include BASEDIR . '/includes/footer.php';
?>