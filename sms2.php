<?php
/*  sms2.php

    JM: Committed as-is 2019-02-11, revisited for some cleanup 2019-04-04
    This appears to have been Martin's work in progress late December 2018/early January 2019.
    Status unknown. Presumably intended as an eventual replacement for sms.php.
    Looks like it was on a reasonable track, so someone will presumably want to 
    look at both.

    EXECUTIVE SUMMARY: Page that displays all inbound SMSs (per database table inboundSms). Martin described 
    this in 2018 as "a draft version that will later have some filtering" but he never got back to it. 
    I (JM) was told at that time not to bother looking at all closely at this, but 2019-04
    I'm taking at least a cursory shot at it, since it appears to be part of the production system.
    
    No inputs.
   
*/

include './inc/config.php';
include './inc/access.php';

?>

<?php
include BASEDIR . '/includes/header.php';

$crumbs = new Crumbs(null, $user);
?>
<style>
.convotable td {
    font-size: 120%;
}
</style>

<div id="container" class="clearfix">
    <div class="main-content">
        <div class="full-box clearfix">
            <h2 class="heading">SMS</h2>
            <br>    
            <?php
            $db = DB::getInstance();
            $convos = array();  // JM: Presumably "conversations"

            // For each distinct source of inbound SMS messages (didFrom),
            //  count how many messages there are from that source.
            // >>>00004 NOTE that this does not consider customer, so once
            //  we go beyond supporting just SSS, this has to be revisited.
            $query  = " select count(inboundSmsId) as messagecount, didFrom ";
            $query .= " from " . DB__NEW_DATABASE . ".inboundSms ";
            $query .= " group by didFrom order by didFrom ";
                
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $convos[] = $row;                        
                    }
                }
            } // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
			
			echo '<table border="0" class="convotable">';
                echo '<tr>';
                    echo '<th nowrap>DID From</th>';
                    echo '<th nowrap>Msg Count</th>';
                    echo '<th nowrap>Who</th>';
                    echo '<th width="100%">&nbsp;</th>';
                echo '</tr>';
                foreach ($convos as $ckey => $convo) {
                    $search = trim($convo['didFrom']);
                    
                    // If it's an 11-character phone number, drop leading digit.
                    // Obviously, that is based on NADS, but >>>00026 really ought
                    //  to make sure the dropped first digit is "1" or "0"
                    if (strlen($search) == 11) {
                        $search = substr($search,1);
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
                    
                    $bgcolor = ($ckey % 2) ? '#eeeeee' : '#cccccc';  // Alternate background colors			    
                    
                    echo '<tr bgcolor="' . $bgcolor . '">';
                        // "DID From":
                        // >>>00001 someone will want to sort out that regex, I didn't look closely - JM 2019-04-04
                        $num =  preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $convo['didFrom']);
                        echo '<td nowrap>' . $num . '</td>';
                        
                        // "Msg Count"
                        echo '<td>' . $convo['messagecount'] . '</td>';
                        
                        // "Who"
                        echo '<td nowrap>';
                            // >>>00026 looks like persons & companies may be conflated in a bad way here.
                            //  I (JM) doubt the "foreach ($companies..." really belongs under "if (count($persons))...". 
                            if (count($persons)) {
                                echo '<table border="0" cellpadding="0" cellspacing="0">';
                                foreach ($persons as $person) {
                                    $p = new Person($person['personId']);
                                    echo '<tr>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>[p]</td>'; // 'p' for person
                                    // Name, linked to person page
                                    echo '<td width="100%"><a href="' . $p->buildLink() . '">' . $p->getFormattedName(1) . '</a></td>';
                                    // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                    //echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $p->getPersonId() . '&from=' . rawurlencode($convo['didTo']) . '&inboundSmsId=' . intval($convo['inboundSmsId']) . '&to=' . rawurlencode($convo['didFrom']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                    // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                                    echo '</tr>';
                                }
                                foreach ($companies as $ckey => $company){
                                    $c = new Company($company['companyId']);
                                    echo '<tr>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>[c]</td>'; // 'c' for company
                                    // Name, linked to company page
                                    echo '<td width="100%"><a href="' . $c->buildLink() . '">' . $c->getName() . '</a></td>';
                                    // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                    //echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?companyId=' . $c->getCompanyId() . '&from=' . rawurlencode($inbound['didTo'])  . '&inboundSmsId=' . intval($convo['inboundSmsId']) . '&to=' . rawurlencode($convo['didFrom']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                    // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                                    echo '</tr>';
                                }
                                 
                                echo '</table>';
                            }
                        echo '</td>';
                        
                        // >>>00001 column currently always empty, intent unclear, but see sms.php
                        echo '<td>&nbsp;</td>';
                    echo '</tr>';
                } // END foreach ($convos...
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