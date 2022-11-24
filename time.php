<?php
/*  time.php

    EXECUTIVE SUMMARY: Employee timesheets, for the employee themself to fill out.

    There is a RewriteRule in the .htaccess to allow this to be invoked as just:
        * "time/foo" rather than "time.php?displayType=foo".
        * "time/foo/bar" rather than "time.php?displayType=foo&start=bar".

    PRIMARY INPUTS:
        * $_REQUEST['displayType']: display type of page, e.g. 'incomplete' (default), 'workWeek'
            JM added 'workWeekAndPrior' 2020-06-29: this displays a workweek, and uses $_REQUEST['start'] for that workweek,
            but also show back to the beginning of the relevant pay period.
            This is a bit of a mess, and some things Martin started into were never well-supported: 'payperiod', 'range'.
        * $_REQUEST['start']: the date the week (or other period) starts ... e.g. '2016-10-20'. Optional and meaningless for 'incomplete';
            optional at least for 'workWeek', 'workWeekAndPrior' (defaults to the latest Monday); JM hasn't researched the others,
            which appear to be unused.

    Optional input $_REQUEST['act']. Only supported value, 'changeot', takes additional input $_REQUEST['new'].
    For 'changeot' to do anything, we must also have $_REQUEST['displayType']='workWeek'.

    >>>00002, 00016: needs to validate inputs
*/

include './inc/config.php';
include './inc/access.php';

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Timesheet - ".str_replace("'", "\'", CUSTOMER_NAME)."';\n</script>\n";

$displayType = isset($_REQUEST['displayType']) ? $_REQUEST['displayType'] : ''; // [Martin comment:] display type of page e.g. 'range' etc.
                                                               // JM: this deserves further study and possibly changes. It looks like the possible values are:
                                                               //  'incomplete' - specific to current workweek, starting on Monday. Ignores $_REQUEST['start'].
                                                               //  'workWeek' - allows specification of a particular week by using $_REQUEST['start'].
                                                               //     Defaults to the current workWeek.
                                                               //  'payperiod' - JM believes this hasn't been well tested. Allows viewing, but not editing,
                                                               //     data for a pay period. Defaults to the current pay period
                                                               //     Allows specification of a particular pay period by using $_REQUEST['start'].
                                                               //     >>>00001 As of v2020-3, will fire a bunch of errors if $_REQUEST['start'] is supplied
                                                               //     and is not either the first or 16th day of the month.
$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';  // the date the week/period starts ... i.e. 2016-10-20

if (!strlen($displayType)){
	$displayType = 'incomplete'; // default
}

$crumbs = new Crumbs(null, $user);

$db = DB::getInstance();

// Moved this up from below 2020-07-02 JM
$query = "SELECT customerPersonId ";
$query .= "FROM " . DB__NEW_DATABASE . ".customerPerson ";
$query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
$query .= "AND personId = " . $user->getUserId() . ";";
$result = $db->query($query);
if (!$result) {
    $logger->errorDB('1593066035', 'Hard DB error selecting customerPersonId', $db);
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">Cannot get customerPersonId for logged-in user.</div>";
    include BASEDIR . '/includes/footer.php';
    die();
}
$row = $result->fetch_assoc();
$customerPersonId = intval($row['customerPersonId']);


$query = "SELECT max(periodBegin) as lastTimeSheet ";
$query .= "FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
$query .= "WHERE customerPersonId = $customerPersonId";

$result = $db->query($query);
if (!$result) {
    $logger->errorDB('1593066035', 'Hard DB error selecting customerPersonId', $db);
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">Cannot get customerPersonId for logged-in user.</div>";
    include BASEDIR . '/includes/footer.php';
    die();
}
$row = $result->fetch_assoc();
$lastTimeSheet = new DateTime($row['lastTimeSheet']);
// Time constructor does more work than most, calculating end of period, start of next period, etc.
//  and fills in an array providing two written forms of all of the dates in the week/period.
// Unlike most of our classes, it exposes most of its properties directly, rather than through methods.
// If you are working in here, you will almost certainly want to be familiar with that class.
$time = new Time($user, $start, $displayType);
$start=$time->begin; // [CP] 20210218 for a fresh load of the page the $start variable is empty, so we need to initialise it
$editable = ($time->initialSignoffTime === null && $time->adminSignedPayrollTime === null) || $time->reopenTime !== null; // Introduced 2020-10-07 JM
?>
<script>
$(function() {
    $('body').addClass('<?= $editable ? 'editable' : 'not-editable' ?>');
});
</script>
<?php

$workordertasks = $time->getWorkOrderTasksByDisplayType();  // The structure here is very similar to the "gold" structure in class WorkOrder
                                                            // (but as of 2020-10-06 not identical: see discussion following).

// $workordertasks give us all PTO (paid time off) and workOrderTask info for this person, for the relevant timeframe.
// It includes any required "fake" workOrderTasks that are not explicitly represented in the DB, but are implied by the presence of their children.

//  The main difference between this and the "gold" structure in class WorkOrder is that each entry here for a 'real' function
//   places $wot contents one level higher in the array than the WorkOrder approach.
//  That is, $gold[$i]['data'][FOO] in the WorkOrder approach will be $workordertasks[$i][FOO] here.
//  >>>00001 We might do well to modify code here and in the Time class to make the structure identical to the "gold" structure in class WorkOrder.
///  But the stuff in workOrder may also deserve some changes, so make sure if you plunge into this you think it through.
//  >>>00001 JM 2020-10-05: Also, I'm not sure how this relates to the multiple "elements" (buildings, etc.) of a job. It may or may not be analogous to the similar
//   WorkOrder function in that respect. Deserves study.
//
// The structure is a flat numerically-indexed array, even though it implicitly represents a hierarchy.
// The order it goes through the hierarchy is known as "pre-order traversal".
//  For each reconstructed internal node that doesn't have an explicit workOrderTask, we will have:
//   * 'type' => 'fake'
//   * 'level' => $level
//   * 'data' => a key from input $array, e.g. 'a210' corresponding to taskId 210
//  For each leaf node and any explicit internal node, we will have a WorkOrderTask object enhanced by the following properties:
//   * 'type' => 'real'
//   * 'level' => $level
?>

<style>
.kludgetable  table .kludgetable  caption, .kludgetable  tbody, .kludgetable  tfoot, .kludgetable  thead, .kludgetable  tr, .kludgetable  th, .kludgetable  td {
    margin: 0;
    padding: 0;
    border: 0;
    outline: 0;
    font-size: 100%;
    vertical-align: baseline;
    background: transparent;
}

.datarow td {
    border-bottom-style: solid;
    border-bottom-color: #cccccc;
}
.totalcell {
    background-color:#ddddff;
    text-align:center;
}
.diagopen {
    text-align:center;
}

input.cantfocus {
    background-color:#f5f5f5;
}

th.date-context {
    background-color:#b0b0ff;
    border: 1px solid #8080ff;
}

tr.has-hours th {
    background-color:#800000;
}

th.date-context.first-day-of-new-period, .datarow td.first-day-of-new-period, tr.total td.first-day-of-new-period {
    border-left-style: solid;
    border-left-color: red;
}

</style>

<script type="text/javascript">

$(function() {
    $("#expanddialog" ).dialog({autoOpen: false, width:10, height:20});

    <?php /* BEGIN REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
    $(".expandtally").mouseenter(function() {
	    $( "#expanddialog" ).dialog({
		    position: { my: "center bottom", at: "center top", of: $(this) },
		    autoResize:true ,
	    	open: function(event, ui) {
                $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
    	    }
	    });

        var workOrderTaskId = $(this).attr('data-work-order-task-id');

        var date = $(this).attr('name');

        $("#expanddialog").dialog("open").html(
            // Here and elsewhere in the file, we show the ajax_loader.gif while waiting for the AJAX to return
            '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').dialog({height:'45', width:'auto'})
            .load('/ajax/personstasktally.php?workOrderTaskId=' + escape(workOrderTaskId), function(){
                $('#expanddialog').dialog({height:'auto', width:'auto'});
            });

    });  // END $(".expandtally" ).mouseenter(function()

    $( ".expandtally" ).mouseleave(function() {
        $( "#expanddialog" ).dialog("close");
    });
    // END REMOVED 2020-09-23 JM
    */
    ?>

    $( ".expandopen" ).mouseenter(function() {
        $( "#expanddialog" ).dialog({
            position: { my: "center bottom", at: "center top", of: $(this) },
            autoResize:true ,
            open: function(event, ui) {
                $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
            }
        })

        var workOrderTaskId = $(this).attr('data-work-order-task-id');
        var date = $(this).attr('name');

	    $("#expanddialog").dialog("open").html(
            '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').dialog({height:'45', width:'auto'})
            .load('/ajax/personstasktime.php?date=' + escape(date) + '&workOrderTaskId=' + escape(workOrderTaskId), function(){
                $('#expanddialog').dialog({height:'auto', width:'auto'});

               });
    }); // END $( ".expandopen" ).mouseenter(function()

    $( ".expandopen" ).mouseleave(function() {
  	    $( "#expanddialog" ).dialog("close");
    });

});

</script>

<?php /* expanddialog is a placeholder for a popped-up dialog */ ?>
<div id="expanddialog">
</div>

<style>
#vacationdiv{
display: none;
}
#showvacationdiv:hover #vacationdiv{
display : block;
}
</style>

<style>
/* sticky added 2020-06-25 JM */
.sticky-header {
    position:webkit-sticky; /* for Safari */
    position:sticky;
    top:0;
}

.sticky-footer {
    position:webkit-sticky; /* for Safari */
    position:sticky;
    bottom:0;
}

.sticky-left {
    position:webkit-sticky; /* for Safari */
    position:sticky;
    left:0;
    background-color: #f8f0ff;
}
</style>


<script>
var createCookie = function(name, value, days) {
    var expires;
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
    }
    else {
        expires = "";
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}

function toggleDisplay(jobId) {
    var x = document.getElementById("jobDiv_" + jobId);
    if (x.style.display === "none") {
        x.style.display = "block";
		createCookie('lastJobId', jobId, 10);
    } else {
        x.style.display = "none";
    }
}
</script>

<div id="container" class="clearfix">
    <div class="main-content">
        <?php /* Clicking the icon_info.gif here uses code in header.php to open a jQuery dialog using DIV "helpdialog",
                 call /ajax/helpajax.php?helpId='timeSheet', and show the result. */ ?>
        <img class="helpopen" id="timeSheet" src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_info.gif" width="20" height="20" border="0">
        <a href=""></a> <?php /* >>>00014 JM not sure of the purpose of this empty link. Martin said 2018-11 he'd look into it, but never did.  */ ?>
        <?php

		// This table is sort of a preamble: vacation summary, some navigation, etc.
        echo '<table border="0" cellpadding="1" width="100%">';
		    /* The first row consists of a single cell that spans 3 columns, and has id="vacation_summary".
		       Within that is a DIV with id="showvacationdiv", containing 'Vacation: ' followed by yet another
		       nested DIV, with id="vacationdiv" and content 'Remain: ', and the amount of remaining vacation in
		       hours, with two digits past the decimal point.
		       The "vacationdiv" is originally hidden, and is seen only if you hover over it. This is so no one sees
		       it if they happen to look over your shoulder.
		    */
		    echo '<tr>';
		        echo '<td colspan="3" style="text-align:right;" id="vacation_summary">';
                    $remain = 0;
                    // $total = number_format((float)intval($user->getTotalVacationTime())/60, 2, '.', ''); // removed, because never used. JM 2019-10-09
                    // $used = number_format((float)intval($user->getVacationUsed())/60, 2, '.', ''); // removed, because never used. JM 2019-10-09

                    $remain = number_format((float)(intval($user->getTotalVacationTime(Array('currentonly'=>true))) - intval($user->getVacationUsed()))/60, 2, '.', '');

                    if ($remain < 0){
                        $remain = '(' . $remain . ')';
                    }

                    echo '<div id="showvacationdiv">';
                        echo '<b>Vacation:</b>&nbsp;<div id="vacationdiv">Remain:&nbsp;' . $remain . '</div>';
                    echo '</div>';
                echo '</td>';
            echo '</tr>';

            /* The second row also span 3 columns, with a cell with no id.
               This shows two words in square brackets: "[INCOMPLETE] [WEEKS]".
               If displayType is 'workWeek', then:
                 * "[INCOMPLETE]" is a link to "/time/incomplete"
                 * "[WEEKS"] is not a link.
                 * "[WEEKS (show period)"] is a link to /time/workWeekAndPrior.
               If displayType is 'workWeekAndPrior', then:
                 * "[INCOMPLETE]" is a link to "/time/incomplete"
                 * "[WEEKS]" is a link to "/time/workWeek"
                 * "[WEEKS (show period)"] is not a link
               If displayType is 'incomplete', then:
                 * "[INCOMPLETE]" is not a link
                 * "[WEEKS]" is a link to "/time/workWeek"
                 * "[WEEKS (show period)"] is a link to /time/workWeekAndPrior.
                These URLs are rewritten by .htaccess, as described in the header comment of this file.

            */echo '<tr>';
                echo '<td colspan="3">';
                if ($displayType == 'incomplete') {
                    echo '[INCOMPLETE]&nbsp;&nbsp;';
                } else {
                    echo '[<a id="incomplete" href="/time/incomplete">INCOMPLETE</a>]&nbsp;&nbsp;';
                }
                if ($displayType == 'workWeek') {
                    echo '[WEEKS]&nbsp;&nbsp;';
                } else if ($displayType == 'incomplete') {
                    echo '[<a id="weeks" href="/time/workWeek">WEEKS</a>]&nbsp;&nbsp;';
                } else {
                    echo '[<a id="startWeeks" href="/time/workWeek/' . $start. '">WEEKS</a>]&nbsp;&nbsp;';
                }
                if ($displayType == 'workWeekAndPrior') {
                    echo '[WEEKS (show period)]';
                } else if ($displayType == 'incomplete') {
                    echo '[<a id="weeksShowPeriod" href="/time/workWeekAndPrior">WEEKS (show period)</a>]';
                } else {
                    // http://bt.dev2.ssseng.com/view.php?id=187 fixed in the following line by
                    //  adding the slash after workWeekAndPrior. JM 2020-07-23
                    echo '[<a id="startWeeksShowPeriod" href="/time/workWeekAndPrior/' . $start. '">WEEKS (show period)</a>]';
                }
                echo '</td>';
            echo '</tr>';

            if ($displayType == 'workWeek' || $displayType == 'workWeekAndPrior') {
                /* Here, interspersed with the table code (>>>00006 JM: WHY? It would be much clearer if we had this somewhere else),
                   we handle the action for $_REQUEST['displayType']='workWeek' AND $_REQUEST['act']= 'changeot'.

                   * To have any effect, $_REQUEST['new'] (hereafter, $new) must have one of the two values '4x10' or '5x8'.
                   * We used a SQL query above to get the customerPersonId for the currently logged-in user.
                   * if $new == '4x10', we update the row in DB table customerPersonPayWeekInfo for this customerPersonId
                      and the current $time->begin (that would be the current work week) to have dayOT=12.
                   * if $new == '5x8', we update the row in DB table customerPersonPayWeekInfo for this customerPersonId
                     and the current $time->begin (that would be the current work week) to have dayOT=10.
                   * In short, for someone working a 5-day, 40-hour week overtime for a given day starts after hour 10.
                     For someone working a 4-day, 40-hour week overtime for a given day starts after hour 12.
                */
                if ($act == 'changeot') {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $new = isset($_REQUEST['new']) ? $_REQUEST['new'] : '';
                        $dayOT = 0; // initialize to a bogus sentinel value
                        if ($new == '4x10') {
                            $dayOT = 12;
                        } else if ($new == '5x8') {
                            $dayOT = 10;
                        } else {
                            $logger->error2('1593066044', "Unrecognized workweek plan ". print_r($new, true));
                        }

                        $query = "UPDATE " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo SET ";
                        $query .= "dayOT = $dayOT ";
                        $query .= "WHERE customerPersonId = $customerPersonId ";
                        $query .= "AND periodBegin = '" . $db->real_escape_string(date("Y-m-d", strtotime($time->begin))) . "';";

                        $result = $db->query($query);
                        if ($result) {
                            if (! $db->affected_rows) {
                                $logger->errorDB('1593066061', 'Setting dayOT apparently did not affect any rows', $db);
                            }
                        } else {
                            $logger->errorDB('1593066077', 'Hard DB error setting dayOT', $db);
                        }
                        unset($dayOT);
                    }
                } // END if ($act == 'changeot')

            // Code for $displayType == 'workWeek', once we are done with any 'changeot'

            // Blank row spanning all 3 columns
            echo '<tr><td colspan="3">&nbsp;</td></tr>';

            /* Span all 3 columns with a cell with no id. We query to determine whether
               dayOT for this user and for the week in question is 10 or 12 and write
               one of the following depending on the answer:
               * dayOT = 10: 'For this period : 5x8's (change to 4x10's)'. The parenthesized phrase is linked to
                 /time/workWeek/date?act=changeot&new=4x10, where date is in Y-m-d form.
               * dayOT = 12: 'For this period : 4x10's (change to 5x8's)'. The parenthesized phrase is linked to
                 /time/workWeek/date?act=changeot&new=5x8, where date is in Y-m-d form.
             */
            // [Martin comment:] get this into a class
            $query = "SELECT dayOT FROM " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo ";
            /* BEGIN REPLACED 2020-07-02 JM
            $query .= "WHERE customerPersonId = (";
            $query .= "    SELECT customerPersonId ";
            $query .= "    FROM " . DB__NEW_DATABASE . ".customerPerson ";
            $query .= "    WHERE customerId = " . intval($customer->getCustomerId()) . " ";
            $query .= "    AND personId = " . $user->getUserId();
            $query .= ") ";
            // END REPLACED 2020-07-02 JM
            */
            // BEGIN REPLACEMENT 2020-07-02 JM
            $query .= "WHERE customerPersonId = $customerPersonId ";
            // END REPLACEMENT 2020-07-02 JM
            $query .= "AND periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "';";
            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0){
                    $row = $result->fetch_assoc();
                    if ($row['dayOT'] == 10){
                        echo '<tr><td colspan="3">For this period : 5x8\'s (<a id="dayOT10" href="/time/'.$displayType.'/' .
                                rawurlencode(date("Y-m-d", strtotime($time->begin))) .
                                '?act=changeot&new=4x10">change to 4x10\'s</a>)</td></tr>';
                    } else if ($row['dayOT'] == 12){
                        echo '<tr><td colspan="3">For this period : 4x10\'s (<a id="dayOT12" href="/time/'.$displayType.'/' .
                                rawurlencode(date("Y-m-d", strtotime($time->begin))) .
                                '?act=changeot&new=5x8">change to 5x8\'s</a>)</td></tr>';
                    } else {
                        $logger->error2('1593066133', 'unrecognized dayOT value (expect 10 or 12, got '. $row['dayOT'] .')' );
                    }
                }
            } else {
                $logger->errorDB('1593066137', 'Hard DB error', $db);
            }

            /* Another empty row */
            echo '<tr><td colspan="3">&nbsp;</td></tr>';

            /* Finally, a row that makes use of the fact that we have three columns in this table!
               This row allows us to navigate to the previous or next week. In the three columns, respectively:
               * the previous week's start date (displayed in 'M j, Y' form, like "January 7, 2021"), with a
                 left angle quote (like a "<<") on either side of it. Links to /time/workWeek/date , for the relevant date.
               * Bolded: 'Week Starting ' followed by this week's start date (displayed in 'M j, Y' form, like "January 14, 2021"). Not linked
               * the next week's start date (displayed in 'M j, Y' form, like "January 21, 2021"), with a
                 right angle quote (like a ">>") on either side of it. Links to /time/workWeek/date , for the relevant date.
            */
            echo '<tr>';
                echo '<td style="text-align:left">&#171;<a id="timePrevious" href="/time/'.$displayType.'/' .  $time->previous . '">' . date("M j, Y", strtotime($time->previous)) . '</a>&#171;</td>';
                echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">Week Starting ' . date("M j, Y", strtotime($time->begin)) . '</span></td>';
                echo '<td style="text-align:right">&#187;<a id="timeNext" href="/time/'.$displayType.'/' . $time->next . '">' . date("M j, Y", strtotime($time->next)) . '</a>&#187;</td>';
            echo '</tr>';
        } // END if ($displayType == 'workWeek' || $displayType == 'workWeekAndPrior')

        /* 2020-07-30 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=200 issue 3: the following "signoff code" was originally
           specific to 'workWeekAndPrior', but is now independent of $displayType. */
        $parts = explode('-', $time->beginIncludingPrior);
        if ($parts[2] == 1) {
            $parts[2] = 15;
        } else {
            $month = $parts[1];
            if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
                $parts[2] = 30;
            } else if ($month == 2) {
                $year = $parts[0];
                // simplifying the rule, because this will be good until 2099
                $parts[2] = ($year % 4) ? 28 : 29;
            } else {
                $parts[2] = 31;
            }
        }
        $payPeriodEnd = implode('-', $parts);

        // This next condition added 2020-07-30 as the main part of addressing http://bt.dev2.ssseng.com/view.php?id=200 issue 3:
        //  which pages show the signoff button.
        // Also, some code was moved around to address http://bt.dev2.ssseng.com/view.php?id=200 issue 1 (making the button available,
        //  but grayed, if $time->readyForSignoff is not set)
        if ($displayType == 'workWeekAndPrior' || $displayType == 'payperiod' ||
                ( ($displayType == 'workWeek' || $displayType == 'incomplete') &&
                  ($payPeriodEnd <= $time->end)
                )
            )
        {
            $displayPayPeriodStart = date("M j, Y", strtotime($time->beginIncludingPrior));
            $displayPayPeriodEnd = date("M j, Y", strtotime($payPeriodEnd));

            // Add more table rows related to signing off time
            // reopenTime and adminSignedPayrollTime introduced 2020-10-06 JM for v2020-4
            echo '<tr><td colspan="3">&nbsp;</td></tr>';
            if ($editable) {
                echo '<tr><td colspan="3">';
                // Signoff button is available even before notification goes out, and we show it even in "WEEKS" view,
                // even though we are not showing data for the whole period.
                echo '<button id="signing-timesheet" data-periodstart="' . $displayPayPeriodStart . '" ' .
                     'data-periodend="' . $displayPayPeriodEnd . '" ' .
                     ($time->readyForSignoff ? 'style="background-color:red;" ' : '') .
                     '>';
                echo 'Click to sign off your hours for the pay period from ' .
                    $displayPayPeriodStart . ' to ' .
                    $displayPayPeriodEnd . '</button>';
                echo '</td></tr>';
                ?>
                <div id="signing-timesheet-dialog" style="display:none"></div>
                <script>
                $(function() {
                    $('#signing-timesheet').click(function() {
                        // Set up the dialog initially with a "loader" GIF. We will fill in dialog content AFTER opening dialog.
                        $('#signing-timesheet-dialog').html('<img src="/cust/<?= $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
                        $('#signing-timesheet-dialog').show();
                        $('#signing-timesheet-dialog').dialog({
                            title: "Sign off timesheet",
                            autoopen: true,
                            width: 800,
                            maxWidth: 0.8 * $(window).width(),
                            close: function() {
                                $('#signing-timesheet-dialog').dialog('destroy');
                                $('#signing-timesheet-dialog').hide();
                            },
                            buttons: {
                                "Sign off": function() {
                                    $.ajax({
                                        url: '/ajax/signoffperiodtime.php',
                                        data:{
                                            customerPersonId: <?= $customerPersonId ?>,
                                            periodBegin: '<?= date("Y-m-d", strtotime($time->beginIncludingPrior)) ?>'
                                        },
                                        async: false,
                                        type: 'post',
                                        success: function(data, textStatus, jqXHR) {
                                            if (data['status']) {
                                                if (data['status'] == 'success') {
                                                    <?php
                                                    // Theoretically we could do something like
                                                    // $('body').addClass('not-editable').removeClass('editable');
                                                    // If we did that, we'd have to make sure that the Javascript for both the
                                                    //  editable & non-editable cases made it into the client code, and that
                                                    //  we displayed info about signing accordingly as well. For now it is simpler
                                                    //  to just refresh the page.
                                                    if ($start) {
                                                    ?>
                                                        location.href = 'time.php?displayType=<?= $displayType ?>&start=<?= $start ?>';
                                                    <?php
                                                    } else {
                                                    ?>
                                                        location.href = 'time.php?displayType=<?= $displayType ?>';
                                                    <?php
                                                    }
                                                    ?>
                                                } else {
                                                    alert('Server-side error signing off timesheet, please ask an administrator or developer to check the logs.');
                                                }
                                            } else {
                                                console.log('data', data);
                                                alert('Server-side error signing off timesheet, no status returned, please ask an administrator or developer to check the logs.');
                                            }
                                            $('#signing-timesheet-dialog').dialog('close');
                                        },
                                        error: function(jqXHR, textStatus, errorThrown) {
                                            alert('AJAX error signing off timesheet, please ask an administrator or developer to check the logs.');
                                            $('#signing-timesheet-dialog').dialog('close');
                                        }
                                    });
                                },
                                "Cancel": function() {
                                    $('#signing-timesheet-dialog').dialog('close');
                                }
                            }
                        });

                        // Fill in dialog content!
                        $.ajax({
                            url: '/ajax/getsignofftimedialog.php',
                            data:{
                                displayType: '<?= $displayType ?>',
                                start: '<?= $start ?>'
                            },
                            async: false,
                            type: 'post',
                            success: function(data, textStatus, jqXHR) {
                                if (data['status']) {
                                    if (data['status'] == 'success') {
                                        $('#signing-timesheet-dialog').html(data['html']);
                                    } else {
                                        alert('Server-side error opening signing-timesheet-dialog, please ask an administrator or developer to check the logs.');
                                        $('#signing-timesheet-dialog').dialog('close');
                                    }
                                } else {
                                    console.log('data', data);
                                    alert('Server-side error opening signing-timesheet-dialog, no status returned, please ask an administrator or developer to check the logs.');
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                alert('AJAX error opening signing-timesheet-dialog, please ask an administrator or developer to check the logs.');
                                $('#signing-timesheet-dialog').dialog('close');
                            }
                        });
                    });
                });
                </script>
                <?php
            } else {
                // This case completely reworked for v2020-4. JM 2020-10-07.
                $warning = '';
                if ($time->adminSignedPayrollTime) {
                    $warning = 'You have already signed off your time for this period and <b>a manager has reviewed your time.</b><br/>';
                    $warning .= 'If you make further changes that alter your totals, you may want to email or otherwise contact a manager to explain the discrepancy.';
                } else {
                    $warning = 'You have already signed off your time for this period, but it has not been reviewed.';
                }
                echo '<tr><td colspan="3">' . $warning . '</td></tr>';

                echo '<tr><td colspan="3">';
                // "Reopen" button.
                echo '<button id="reopen-timesheet" data-periodstart="' . $displayPayPeriodStart . '" ' .
                     'data-periodend="' . $displayPayPeriodEnd . '" ' .
                     '>';
                echo 'Click to modify your hours for the pay period from ' .
                    $displayPayPeriodStart . ' to ' .
                    $displayPayPeriodEnd . '</button>';
                echo '</td></tr>';

                // dialog to launch by clicking 'reopen-timesheet'
                $periodDisplay = 'this period';
                if ($displayPayPeriodStart && $displayPayPeriodEnd) {
                    $periodDisplay = "the period $displayPayPeriodStart - $displayPayPeriodEnd";
                } else if ($displayPayPeriodStart) {
                    $periodDisplay = "the period beginning $displayPayPeriodStart";
                } else if ($displayPayPeriodEnd) {
                    $periodDisplay = "the period ending $displayPayPeriodEnd";
                }
                echo '<div id="reopen-timesheet-dialog" style="display:none">' . "\n";
                echo $warning;
                echo '</div>' . "\n";
                ?>
                <script>
                $(function() {
                    $('#reopen-timesheet').click(function() {
                        $('#reopen-timesheet-dialog').show();
                        $('#reopen-timesheet-dialog').dialog({
                            title: "Reopen timesheet",
                            autoopen: true,
                            width: 800,
                            maxWidth: 0.8 * $(window).width(),
                            close: function() {
                                $('#reopen-timesheet-dialog').dialog('destroy');
                                $('#reopen-timesheet-dialog').hide();
                            },
                            buttons: {
                                "Reopen": function() {
                                    $.ajax({
                                        url: '/ajax/reopenperiod.php',
                                        data:{
                                            customerPersonId: <?= $customerPersonId ?>,
                                            periodBegin: '<?= date("Y-m-d", strtotime($time->beginIncludingPrior)) ?>'
                                        },
                                        async: false,
                                        type: 'post',
                                        success: function(data, textStatus, jqXHR) {
                                            if (data['status']) {
                                                if (data['status'] == 'success') {
                                                    <?php
                                                    // Theoretically we could do something like
                                                    // $('body').addClass('not-editable').removeClass('editable');
                                                    // If we did that, we'd have to make sure that the Javascript for both the
                                                    //  editable & non-editable cases made it into the client code, and that
                                                    //  we displayed info about signing accordingly as well. For now it is simpler
                                                    //  to just refresh the page.
                                                    if ($start) {
                                                    ?>
                                                        location.href = 'time.php?displayType=<?= $displayType ?>&start=<?= $start ?>';
                                                    <?php
                                                    } else {
                                                    ?>
                                                        location.href = 'time.php?displayType=<?= $displayType ?>';
                                                    <?php
                                                    }
                                                    ?>
                                                } else {
                                                    alert('Server-side error, please ask an administrator or developer to check the logs.');
                                                }
                                            } else {
                                                console.log('data', data);
                                                alert('Server-side error, no status returned, please ask an administrator or developer to check the logs.');
                                            }
                                            $('#signing-timesheet-dialog').dialog('close');

                                            <?php
                                            // Theoretically we could do something like
                                            // $('body').addClass('not-editable').removeClass('editable');
                                            // If we did that, we'd have to make sure that the Javascript for both the
                                            //  editable & non-editable cases made it into the client code, and that
                                            //  we displayed info about signing accordingly as well. For now it is simpler
                                            //  to just refresh the page.
                                            if ($start) {
                                            ?>
                                                location.href = 'time.php?displayType=<?= $displayType ?>&start=<?= $start ?>';
                                            <?php
                                            } else {
                                            ?>
                                                location.href = 'time.php?displayType=<?= $displayType ?>';
                                            <?php
                                            }
                                            ?>
                                        },
                                        error: function(jqXHR, textStatus, errorThrown) {
                                            alert('AJAX error, please ask an administrator or developer to check the logs.');
                                            $('#reopen-timesheet-dialog').dialog('close');
                                        }
                                    });
                                },
                                "Cancel": function() {
                                    $('#reopen-timesheet-dialog').dialog('close');
                                }
                            }
                        });
                    });
                });
                </script>
                <?php
            }
        }
        echo '</table>';

        // BEGIN 2020-02-24 JM gathered together a bunch of PHP code that was previously down below after we start the workOrderTasks table
        function isWeekend($date) {
            $inputDate = DateTime::createFromFormat("Y-m-d", $date);
            return $inputDate->format('N') >= 6;
        }

        // BEGIN REWORKED 2020-07-30 JM to accommodate changes for http://bt.dev2.ssseng.com/view.php?id=200 issue 1 & issue 3,
        // described above. For issue 3 (show signoff button even in "WEEKS" view, extrapolated to "INCOMPLETE" view as well)
        // we had to change the meaning of $time->beginIncludingPrior.

        /*
        // SO... this was the code as of 2020-06-29, changed 2020-07-30
        $date0 = new DateTime($time->beginIncludingPrior); // added JM 2020-06-29
        $date1 = new DateTime($time->begin);
        $date2 = new DateTime($time->end);

        // $daysinperiod = $date2->diff($date1)->format("%a") + 1; // replaced JM 2020-06-29 by next line
        $daysinperiod = $date2->diff($date0)->format("%a") + 1;
        */
        // BEGIN REPLACEMENT 2020-07-30 JM
        $date1 = new DateTime($time->begin);
        $date2 = new DateTime($time->end);
        if ($displayType == 'workWeekAndPrior') {
            $date0 = new DateTime($time->beginIncludingPrior);
            $daysinperiod = $date2->diff($date0)->format("%a") + 1;
        } else {
            $daysinperiod = $date2->diff($date1)->format("%a") + 1;
        }
        // END REPLACEMENT 2020-07-30 JM

        $currentJobId = -1;
        $currentWorkOrderId = 0;
        // $currentWorkOrderTaskCategoryId = 0; // REMOVED 2020-07-27 JM for v2020-4: initialized & never used.

        $totarray = array();  // array of minutes worked + PTO each day in period.
        $worked_totarray = array();  // just worked, not PTO/holiday
        $pto_totarray = array();  // just PTO/holiday
        for ($i = 0; $i < $daysinperiod; $i++) {
            $totarray[$i] = 0;
            $worked_totarray[$i] = 0;
            $pto_totarray[$i] = 0;
        }

        // How many days back can this user change time? Default is 3. Effective
        // maximum is 28, based on code below.
        $query = "SELECT daysBack FROM " . DB__NEW_DATABASE . ".customerPerson ";
        $query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
        $query .= "AND personId = " . $user->getUserId() . ";";
        $daysback = 3;
        $result = $db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $daysback = $row['daysBack'];
            } else {
                $logger->warnDB('1593066199', 'No customerPerson row found', $db);
            }
        } else {
            $logger->errorDB('1593066202', 'Hard DB error selecting daysBack', $db);
        }

        // $datesinperiod will be an array of DateTime objects; $datesinperiod[0] represents
        //  the start of the period, and successive indexes represent successive days.
        $datesinperiod = array();
        $tmpdate = $date1;
        for ($i = 0; $i < $daysinperiod; $i++) {
            $datesinperiod[] = clone $tmpdate;
            $tmpdate->modify('+1 day');
        }

        // $datesinperiod will be an array of DateTime objects; $prevdays[0] represents
        //  today, and successive indexes represent successively EARLIER days.
        $today = new DateTime();
        $tmpdate = $today;
        $prevdays = array();
        for ($i = 0; $i < 28; ++$i){
            $prevdays[] = clone $tmpdate;
            $tmpdate->modify('-1 day');
        }

        $first_day_of_new_period_index = -1;
        /*
        // BEGIN REPLACED 2020-07-30 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=200 (issue 2 in that bug:
        //  In "WEEKS" view, show a red line for beginning & end of period like we do for end of period in "WEEKS (show period)" view.)
        //  Doing this for "INCOMPLETE" view as well.
        //  More properly, we show it to the left of the day that begins a new period, so if the week ends on the last day of the period,
        //  that won't result in a red line.
        if ($displayType == 'workWeekAndPrior') {
        // END REPLACED 2020-07-30 JM
        */
        // BEGIN REPLACEMENT 2020-07-30 JM
        if ($displayType == 'incomplete' || $displayType == 'workWeek' || $displayType == 'workWeekAndPrior') {
        // END REPLACEMENT 2020-07-30 JM
            for ($i = 1; $i < $daysinperiod; ++$i) { // $i = 1 because we never want to do this for the first day
                $dayOfMonth = explode('/', $time->dates[$i]['short'])[1];
                if ($dayOfMonth == 1 || $dayOfMonth == 16) {
                    $first_day_of_new_period_index = $i;
                    break;
                }
            }
        }

        // Array of DATETIME objects for days for which user may modify time.
        // Skips holidays, weekends.
        $actuals = array();

        // Initially, we fill in $actuals 28 days back (apparently an effective maximum for $daysback)
        // >>>00027 Query for holidays is rather inefficient, 28 separate queries instead of using a range and
        //  returning multiple values.
        foreach ($prevdays as $prevday) {
            if (!isWeekend($prevday->format('Y-m-d'))) { // No PTO for weekends.
                $isholiday = false;

                $query = "SELECT ptoId FROM " . DB__NEW_DATABASE . ".pto "; // just looking for existence of row
                $query .= "WHERE personId = " . intval($user->getUserId()) . " ";
                $query .= "AND day = '" . $db->real_escape_string($prevday->format('Y-m-d')) . "' ";
                $query .= "AND ptoTypeId = " . intval(PTOTYPE_HOLIDAY) . ";";
                $result = $db->query($query);
                if ($result) {
                    $isholiday = $result->num_rows > 0;
                } else {
                    $logger->errorDB('1593066222', 'Hard DB error selecting pto data', $db);
                }

                if (!$isholiday) {
                    $actuals[] = clone $prevday;
                }
            }
        } // END foreach ($prevdays...

        // By default, $daysback = 3, so typically this would be the 3 most recent days ($actuals runs chronologically backward)
        $actuals = array_slice($actuals, 0, $daysback);
        // Picking from the end of this array means getting the LEAST RECENT day within the $daysback period
        $furthest = array_values(array_slice($actuals, -1))[0];

        // Arrange workOrderTasks by job.
        // Build associative array $wotsForJobs of workOrderTasks, sorted by job. First index is jobId, second is workOrderId.
        $wotsForJobs = array();
        foreach ($workordertasks as $wot) { // >>>00012: why a different variable name for the same thing ($wot vs. $workordertask)?
            if (isset($wot['jobId'])) {
                if ($wot['type'] == 'real') {
                    if (!isset($wots[$wot['jobId']])) {
                        $wots[$wot['jobId']] = array();
                    }
                    if (!isset($wots[$wot['jobId']][$wot['workOrderId']])) {
                        $wots[$wot['jobId']][$wot['workOrderId']] = array();
                    }
                    $wotsForJobs[$wot['jobId']][$wot['workOrderId']][] = $wot;
                }
            }
        }

        //  Now group all workOrderTasks for the same job together.
        $workordertasksGroupedByJob = array();
        foreach ($wotsForJobs as $wotsForJob) {
            foreach ($wotsForJob as $wotsForWorkorder) {
                foreach ($wotsForWorkorder as $workordertask) {
                    $workordertasksGroupedByJob[] = $workordertask;
                }
            }
        }
        unset($wotsForJobs);

        echo '<br />';
        echo '<div class="full-box clearfix">';
            echo '<h2 class="heading">';  // >>>00001 looks like an empty heading, what's going on here?
            echo '</h2>';
            echo 'click job name to expand';
            /* JM reworked this table 2020-06

            Structure:

            For each job, spanning the entire width:
            Job row 1
            * workOrderTask name, linked as a toggle for the time rows for that workOrderTask
            * job number, linking to open the job in a different page
            Job row 2: "STICKY HEADER"
            * 5 blank columns
            * 1 col: "TASK TOTAL"
            * 1 column for each day in the period, containing date (e.g. "06/22") and day of week (e.g. "Mon")
            * 1 blank column
            * 1 col: "TALLY" (REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.)

            Then a row for each workOrder
            * workorder name, spanning the whole table. Passive display
            Followed by a row for each workOrderTask in the workOrder:

                For "fake" tasks that are ancestors of a workOrderTask:
                * 1 col: icon or blank
                * the rest: task description

                For a "real" workOrderTask:
                * 1 col: (1) icon or blank
                * 1 col: (2) task description, indented to show level
                * 1 col: (3) commma-separated workOrderTaskElements
                * 1 col: (4) task type (or blank if none). Blank for administrative.
                * 1 col: (5): toggle task status, whether it is completed. Blank for administrative.
                * 1 col: (6): running total hours for this workOrderTask, regardless of period. Blank for administrative.
                * 1 col for each day in period, with ability to see other people's time & see/modify yours.
                * 1 col for your total hours in period on this task
                * 1 col for tally; REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.

            Last row for workOrder:
            * 5 blank columns
            * total time for workOrder
            * 1 blank column for each day in the period
            * 2 more blank columns

            At end of job (but first in the code):
            * 5 blank columns
            * 1 col: running total hours for job
            * 1 blank column for each day in the period
            * 2 more blank columns
            */
            echo '<div id="contain-stick-footer">';
            echo '<table border="0" cellpadding="0" cellspacing="0">'; // BEGIN workOrderTasks table
                                        // There are no overall headers for the columns of this table.
                                        // Also, it looks like we are inconsistent in some rows about the number of columns,
                                        //  but that is presumably harmless because browsers will usually fill in anything missing with blanks.

                // "Fake" tasks are always ancestors (lower level number) of real tasks .
                // The way we use $fakes below presumes that as we go through $workordertasksGroupedByJob we will
                //   always hit ancestor "fake" task *before* real task. Specifically, code assumes that the hierarchy of workordertasks
                //   within a workorder will be in pre-order traversal order (https://en.wikipedia.org/wiki/Tree_traversal#Pre-order).
                //   (JM 2020-10-06) believe the wa that works is that $workordertasks above is in the desired order, and the
                //   way we build $workordertasksGroupedByJob preserves the order where relevant. What is a little odder to me (JM 2020-10-06)
                //   is that we don't seem to break things down by job element, and I'm not sure how, if the same task occurs more than once
                //   within the workorder, employees know which related workordertask to allocate their time to.
                $fakes = array();
                $runtot = 0;  // running total time for current job. This is independent of period, that is indeed the intention.

                $job_row_has_hours = Array();
                $job_row_html_index = 0;

                foreach ($workordertasksGroupedByJob as $wotkey => $workordertask) {
                    if ($workordertask['type'] == 'real') {
                        if (($workordertask['jobId'] != $currentJobId)) {
                            // Transitioning from one job to abother.
                            if ($wotkey) {
                                // NOT first workOrderTask, so we already had another job above.
                                echo '<tr>';
                                    // 5 blank columns
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';

                                    // Total time time for just-completed job: hours, with two digits past the decimal point.
                                    $dispval = (intval($runtot)) ? number_format((float)intval($runtot)/60, 2, '.', '') : '';
                                    echo '<td style="font-weight:bold">' . $dispval . '</td>';

                                    // One blank column for each day in the period
                                    for ($i = 0; $i < $daysinperiod; $i++) {
                                        echo '<td>&nbsp;</td>';
                                    }
                                    // 2 more blank columns
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                echo '</tr>'. "\n";
                                echo '</tbody>'. "\n"; // close the TBODY for workOrderTasks table from last time through the foreach ($workordertasksGroupedByJob) loop.
                                                 // workOrderTasks table has multiple TBODYs (which is unusual but legal).
                            } // END if ($wotkey): that is, not first workOrderTask

                            $runtot = 0; // New job, clear this.

                            // The following was considerably simplified by JM 2020-06-26:
                            //  6 columns (before the days in period; 6th column is "ALL", total on this workOrderTask regardless of period) +
                            //  # of days in period, which is a variable +
                            //  1 total for period
                            $spans = 6 + $daysinperiod + 1;

                            $jo = new Job($workordertask['jobId']);  // Job object

                            // Assuming $workordertask['number'] is defined, build a link to open relevant job page in new window/tab, displaying Job Number.
                            // JM 2020-06-25: The reason $workordertask['number'] would fail to be defined is for PTO and maybe some other similar
                            //  non-job case.
                            $number = (strlen($workordertask['number'])) ? '&nbsp;&nbsp;[<a id="job-number-link'.$jo->getJobId(). $workordertask['number'] . '" style="color:white;" target="_blank" ' .
                                      'class="job-number-link" ' .  // added 2020-06-26 JM
                                      'href="' . $jo->buildLink() . '">' . $workordertask['number'] . '</a>]' : '';
                            unset($jo);

                            // * $workordertask['name'] in a really wide column (probably meant to be whole table, but I -- JM -- don't think
                            //   that always works); click it for toggleDisplay to toggle display of further detail for this job, and to set
                            //   'lastJobId' cookie
                            // * Despite the array name $workordertask, $workordertask['name'] is actually job name
                            // * Followed in same cell by $number as described above.
                            echo '<tr id="job-row-' . ++$job_row_html_index . ' ">';
                                echo '<th colspan="' . $spans . '" style="text-align:left;" onClick="toggleDisplay(' . $workordertask['jobId'] . ');">' .
                                      $workordertask['name'] . $number . '</th>';
                            echo '</tr>'. "\n";

                            // Start TBODY for this job
                            // NOTE that this is normally initially hidden.
                            // Exception: we try to identify a current job and open that (code for this is actually at the bottom of time.php).
                            echo '<tbody id="jobDiv_' . $workordertask['jobId'] . '" style="display:none">';  // start workOrderTasks table TBODY
                                echo '<tr class="sticky-header">';
                                    // 5 blank columns
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<td>&nbsp;</td>';
                                    echo '<th class="date-context">TASK TOTAL</th>';

                                    // Heading for each day in the period: three-letter representation of day of week & short date (e.g. "Mon 06/22"
                                    for ($i = 0; $i < $daysinperiod; $i++) {
                                        $x = new DateTime($time->dates[$i]['position']);

                                        $additionalClasses = '';
                                        if ($i == $first_day_of_new_period_index) {
                                            $additionalClasses = ' first-day-of-new-period';
                                        }
                                        echo '<th class="date-context' . $additionalClasses . '">' . $time->dates[$i]['short'] . '<br />' . $x->format("D") . '</th>';
                                    }
                                    echo '<td>&nbsp;</td>';
                                    /* BEGIN REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
                                    if (intval($workordertask['workOrderTaskId']) > 0) {
                                        echo '<th class="date-context">TALLY</th>';
                                    } else {
                                        echo '<td style="background-color:white;"><div style="width:70px; background-color:white;">&nbsp;</div></td>'; // Administrative
                                    }
                                    // END REMOVED 2020-09-23 JM
                                    */
                                echo '</tr>'. "\n";
                             // Note that TBODY is still open
                        } // END if (($workordertask['jobId'] != $currentJobId))

                        if (($workordertask['workOrderId'] != $currentWorkOrderId)) {
                            // New workorder; may or may not be first of job
                            $wo = new WorkOrder($workordertask['workOrderId']); // WorkOrder object
                            // workOrder name spanning the whole table. Passive display.
                            echo '<tr>';
                                echo '<td colspan="' . $spans . '" style="text-align:left;font-weight:bold;">' . $wo->getName() . '</td>';
                            echo '</tr>'. "\n";
                        }

                        if (count($fakes)) {
                            // We found "fake" ancestor workOrderTasks on our way down here. Now is when we display them.
                            foreach ($fakes as $fake) {
                                echo '<tr>';
                                    // $fake should be $workordertask['data'] for the "fake" workOrderTask. It looks like
                                    //  somewhere in Time::getWorkOrderTasksByDisplayType we assigned the data where
                                    //  the letter "a" followed by a taskId becomes the value here.
                                    // There is similar code in function elementGroupsToArrayTree in inc/functions.php.
                                    $t = new Task(str_replace("a", "", $fake));

                                    // First column: icon for this task (if available) or blank (if not, but that should never arise)
                                    if (strlen($t->getIcon())) {
                                        // BEGIN REPLACED 2020-07-21 JM by the following line
                                        // This is part of addressing http://bt.dev2.ssseng.com/view.php?id=183
                                        // echo  '<td><img src="/cust/' . $customer->getShortName() . '/img/icons_task/' . $t->getIcon() . '" width="18" height="18" border="0"></td>';
                                        // END REPLACED 2020-07-21 JM
                                        echo '<td><img src="' . getFullPathnameOfTaskIcon($t->getIcon(), '1595358045') . '" width="18" height="18" border="0"></td>';
                                    } else {
                                        echo '<td>&nbsp;</td>';
                                    }
                                    // Then we use the rest of the columns for task description.
                                    echo '<td colspan="' . ($spans - 1) . '">' . $t->getDescription() . '</td>';
                                echo '</tr>'. "\n";
                            }
                            $fakes = array(); // Clear $fakes array
                        }

                        // Back to dealing with the current, real workOrderTask
                        $wot = new WorkOrderTask($workordertask['workOrderTaskId']);
                        $workordertaskpersons = $wot->getWorkOrderTaskPersons();

                        $hours_class = isset($workordertask['ptoitems']) ? 'pto-hours' : 'work-hours';

                        echo '<tr class="datarow ' . $hours_class . '">';
                            // First column: icon for this task (if available) or blank (if not)
                            if (strlen($workordertask['icon'])) {
                                // BEGIN REPLACED 2020-07-21 JM by the following line
                                // This is part of addressing http://bt.dev2.ssseng.com/view.php?id=183
                                // echo  '<td><img src="/cust/' . $customer->getShortName() . '/img/icons_task/' . $workordertask['icon'] . '" width="18" height="18" border="0"></td>';
                                // END REPLACED 2020-07-21 JM
                                echo '<td><img src="' . getFullPathnameOfTaskIcon($workordertask['icon'], '1595358187') . '" width="18" height="18" border="0"></td>';
                            } else {
                                echo '<td>&nbsp;</td>';
                            }
                            // Second column: spaces that relate to levels, then the task description.
                            // Top-level task gets one prefixed space; each successive level gets two more, so, for example,
                            //  level-3 task description is prefixed with 6 nonbreaking spaces.
                            $spaces = '&nbsp;';
                            if (intval($workordertask['level'])) {
                                for ($i = 0; $i < $workordertask['level']; $i++) {
                                    $spaces .= "&nbsp;&nbsp;";
                                }
                            }
                            echo '<td width="40%">' . $spaces . $workordertask['taskDescription'] . '</td>';

                            // Third column: If there are any associated workOrderTaskElements, the element names are listed, comma-separated, no space
                            //  after the comman.
                            //  Most browsers will display an HTML EM element with italics.
                            echo '<td width="30%">';
                                $elements = $wot->getWorkOrderTaskElements();
                                if (count($elements)) {
                                    echo '<em>';
                                    foreach ($elements as $ekey =>$element) {
                                        if ($ekey) {
                                            // Not the first
                                            echo ',&nbsp;';
                                        }
                                        echo $element->getElementName();
                                    }
                                    echo '</em>';
                                } else {
                                    echo '&nbsp;';
                                }
                            echo '</td>';

                            //  If the WorkOrderTaskId is positive (not administrative, such as PTO)...
                            if (intval($workordertask['workOrderTaskId']) > 0) {
                                // Fourth column: we instantiate the appropriate Task object, and echo the taskType name (or blank if none).
                                echo '<td>';
                                    $tsk = new Task($workordertask['taskId']);
                                    $tt = $tsk->getTaskType();

                                    if ($tt){
                                        echo $tt['typeName'];
                                    } else {
                                        echo '&nbsp;';
                                    }
                                echo '</td>';

                                /* Fifth column:
                                   * If 'taskStatusId' == 9 (completed), cell contains a link to local function setTaskStatusId(workOrderTaskId, 1)
                                     and displays the icon for an inactive task.
                                   * If 'taskStatusId' == 1 (active, the only other possibility), cell contains a link to local function
                                     setTaskStatusId(workOrderTaskId, 9) and displays the icon for an active task

                                   So those two, between them amount to a toggle of taskStatus.
                                   >>>00001 setTaskStatusId has some side effects, not at all obvious; see documentation of that function,
                                    this deserves further study. Among other things this can pop up a dialog. Tried getting more from Martin
                                    2018-11, but never really got answers.
                                */
                                // [Martin comment:] legacy shit where 9 was "completed"
                                $active = ($workordertask['taskStatusId'] == 9) ? 0 : 1;
                                $newstatusid = ($workordertask['taskStatusId'] == 9) ? 1 : 9;
                                echo  '<td id="statuscell_' . intval($workordertask['workOrderTaskId']) . '"><a href="javascript:setTaskStatusId(' . $workordertask['workOrderTaskId'] . ',' . intval($newstatusid) . ')">'.
                                      '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_active_' . intval($active) . '_24x24.png" width="16" height="16" border="0"></a></td>';

                                // Sixth column: We get the time in minutes from the workOrderTaskTime DB table row for this workOrderTaskId
                                //  and the logged-in user's personId, display it as hours with two digits past the decimal point,
                                //  and add it to the running total minutes for this workOrderTask. NOTE that these numbers are completely independent
                                //  of the period we are looking at.
                                echo  '<td id="runningcell_' . $workordertask['workOrderTaskId'] . '">';

                                // $db = DB::getInstance(); // removed, redundant 2020-02-24 JM

                                $mins = 0; // time on task. This is independent of period, that is indeed the intention.

                                $query = "SELECT minutes FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
                                $query .= "WHERE workOrderTaskId = " . intval($workordertask['workOrderTaskId']) . " ";
                                $query .= "AND personId = " . intval($user->getUserId()) . ";";
                                $result = $db->query($query);
                                if ($result) {
                                    while ($row = $result->fetch_assoc()) {
                                        $mins += intval($row['minutes']);
                                    }
                                } else {
                                    $logger->errorDB('1593066294', 'Hard DB error reading workOrderTaskTime', $db);
                                }
                                if (intval($mins)) {
                                    $dispval = (intval($mins)) ? number_format((float)intval($mins)/60, 2, '.', '') : '';
                                    echo $dispval;
                                    $runtot +=  $mins;
                                }
                                echo '</td>';
                            } else {
                                // [Martin comment:] for PTO the workOrderTaskId's are less than zero
                                echo '<td>&nbsp;</td>'; //  Fourth column: blank taskType name
                                echo '<td>&nbsp;</td>'; //  Fifth column: blank workOrderTask status
                                echo '<td>&nbsp;</td>'; //  Sixth column: blank time
                            }

                            $ranges = array();
                            $isPTO = false;

                            // JM: The 'ptoitems' vs. 'regularitems' distinction comes from the Time class. We may not really
                            //  need the distinction here at all. When I asked Martin around October 2018 his reply was
                            //  that it just came down to the order in which stuff got done, and was a "bump on a bump" that he'd never
                            //  gone back & cleaned up.

                            if (isset($workordertask['ptoitems'])) {
                                $ranges = $workordertask['ptoitems'];
                                $isPTO = true;
                            } else if (isset($workordertask['regularitems'])) {
                                $ranges = $workordertask['regularitems'];
                            }
                            $tot = 0;               // (initialize) minutes for this workOrderTask for period

                            // One column per day in period.
                            for ($i = 0; $i < $daysinperiod; ++$i) {
                                $val = 0;           // (initialize) minutes for this workOrderTask for this day
                                $timecount = 1;
                                // $ranges[$time->dates[$i]['position']] is an array, and if there is more than one
                                //  element in that array it apparently means that there is more than one person with
                                //  time for this task on the day (but it doesn't work that way for PTO,
                                //  so the code is different).
                                if (isset($ranges[$time->dates[$i]['position']])) {
                                    $arr = $ranges[$time->dates[$i]['position']];
                                    if ($isPTO) {
                                        $val = $arr['minutes'];
                                    } else {
                                        $timecount = count($arr); // $timecount == 1 would be the case where exactly this one person has time for this task.
                                        foreach ($arr as $akey => $item) {
                                            if ($item['personId'] == $user->getUserId()){
                                                $val = $item['minutes'];
                                                // >>>00006 JM: I assume a break would be in order here, and that there should be only one
                                                // value for the current person, not that there could be several and we want the last!
                                            }
                                        }
                                    }
                                }
                                if (intval($val)) {
                                    $totarray[$i] += $val; // [Martin comment:] $ranges[$time->datePeriodPosition[$i]]['minutes'];
                                    if ($isPTO) {
                                        $pto_totarray[$i] += $val;
                                    } else {
                                        $worked_totarray[$i] += $val;
                                    }
                                    $job_row_has_hours[$job_row_html_index] = true;
                                }

                                $dispval = (intval($val)) ? number_format((float)intval($val)/60, 2, '.', '') : ''; // convert to hours, 2 digits past the decimal point.
                                $class = (intval($workordertask['editable'])) ? 'diagopen' : 'cantfocus'; // >>>00001 JM: If I read correctly, the only thing that will
                                                                                                          // not show as 'editable' here is holidays.

                                $classes = '';
                                if ($i == $first_day_of_new_period_index) {
                                    $classes = ' class="first-day-of-new-period"';
                                }
                                echo '<td style="text-align:center;"' . $classes . '>';
                                if ($timecount > 1) {
                                    // Someone else also has time for this workOrderTask for this day.
                                    // Show icon_expand.gif; hovering over this opens a stripped-down
                                    // jQuery dialog just above it, using DIV expanddialog.
                                    // Loads the return of /ajax/personstasktime.php, passed date and workOrderTaskId.
                                    // 2019-10-16 JM: introduce HTML data attributes. See discussion above and http://bt.dev2.ssseng.com/view.php?id=35.
                                    // OLD CODE removed 2019-10-16 JM:
                                    //echo '<img name="' . $time->dates[$i]['position'] . '" id="' . $workordertask['workOrderTaskId'] .
                                    // BEGIN REPLACEMENT
                                    echo '<img name="' . $time->dates[$i]['position'] . '" data-work-order-task-id="' . $workordertask['workOrderTaskId'] .
                                    // END REPLACEMENT
                                    '" class="expandopen" style="margin:auto;" src="/cust/' . $customer->getShortName() . '/img/icons/icon_expand.gif" width="16" height="9" border="0">';
                                }

                                //[Martin comment:] $furthest
                                $thisdate = $datesinperiod[$i];

                                if ($thisdate > $furthest) {
                                    // within the time range user can still modify, so the display needs to be done
                                    //  in such a way that the user can modify it. Assuming it wasn't a holiday,
                                    //  $class here gives us 'diagopen'.
                                    echo '<span id="cell_' . $workordertask['workOrderTaskId'] . '_' . $i . '">'.
                                        '<input class="' . $class . '" type="text" '.
                                            'id="' . $workordertask['workOrderTaskId'] . '_' . $i . '" '.
                                            'data-workOrderTaskId="' . $workordertask['workOrderTaskId'] . '" ' . // added JM 2019-06-27
                                            'data-dayInPeriod="' . $i . '" ' .                                    // added JM 2019-06-27
                                            'data-isEditable="'.($thisdate>=$lastTimeSheet?1:0).'" '.
                                            'value="' . $dispval . '"'.
                                            'size="2" />'.
                                    '</span>';
                                } else {
                                    // Just display the value
                                    echo '<span>' . $dispval . '</span>';
                                }
                                ///////

                                if ($timecount > 1) {
                                    // Someone else also has time for this workOrderTask for this day
                                    // display trans_32x32.png image. >>>00001 not active in any way, and
                                    /// from what I (JM) can see, it's just a plain white blank, what is this about?.
                                    echo '<img style="margin:auto;" src="/cust/' . $customer->getShortName() . '/img/trans_32x32.png" width="16" height="9" border="0">';
                                }
                                echo '</td>';

                                $tot += intval($val);
                            } // END for ($i = 0; $i < $daysinperiod; ++$i)

                            // Another column after all the daily hours in period, displaying total hours for period (two places past decimal point)
                            // ID lets it be found if we need to adjust total for this workordertask.
                            echo '<td class="totalcell" id="totalcell_' . $workordertask['workOrderTaskId'] . '">' . number_format((float)$tot/60, 2, '.', '') . '</td>';

                            /* BEGIN REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
                            // Final column: Tally, which is a nested table.
                            // Query DB table TaskTally for this workOrderTask, and examine the result to determine
                            //  the number of people for whom there is a tally/
                            // Tally is a floating point number. Especially germane when working off-contract.
                            //  It's a way to keep track of the reality of how many of something there were compared to when workOrder
                            //  was written up. Particularly useful looking back to see if things were estimated well.
                            //  This is for things other than sheer time.
                            //  Examples:
                            //    * "Gravity simple" (ditto for "gravity complex"): counting how many beams you had to do a calculation for.
                            //    * Number of custom details.
                            if (intval($workordertask['workOrderTaskId']) > 0) {
                                $tallyValue = '';
                                $personCount = 0;

                                $query = "SELECT personId, tally FROM " . DB__NEW_DATABASE . ".taskTally ";
                                $query .= "WHERE workOrderTaskId = " . intval($workordertask['workOrderTaskId']) . ";";
                                $result = $db->query($query);
                                if ($result) {
                                    if ($result->num_rows > 0){
                                        $personCount = $result->num_rows;
                                        while ($row = $result->fetch_assoc()){
                                            if ($row['personId'] == $user->getUserId()){
                                                $tallyValue = $row['tally'];
                                                // >>>00006 JM: I assume a break would be in order here, and that there should be only one
                                                // value for the current person, not that there could be several and we want the last!
                                            }
                                        }

                                    }
                                } else {
                                    $logger->errorDB('1593066321', 'Hard DB error reading taskTally', $db);
                                }
                                // END REMOVED 2020-09-23 JM

                                // BEGIN REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
                                /*  If more than one person has a tally for this task, we embed a nested table in this cell:
                                        * The first row has a single column spanning both rows of the table. It displays the icon_expand.gif
                                          as an image that until 2019-10-16 had id="nn" where nn is workOrderTaskId; because that is really
                                          not an appropriate HTML ID, JM changed that to data-work-order-task-id = "nn"
                                          class="expandtally".
                                             * If the user hovers the mouse over that image, we display a stripped-down jQuery dialog just above that,
                                               using DIV expanddialog. That shows the return of /ajax/personstasktally.php for this workOrderTaskId
                                        * The second row (with a medium gray background) has two columns:
                                             * A cell with id="tallycell_nn" where nn is workOrderTaskId, containing an HTML INPUT element with
                                               id="tally_nn", blank name, and displaying the tally value.
                                             * An image input that displays icon_submit_64x64.png and effectively constitutes a button.
                                               On click this calls local function setTally(workOrderTaskId). That affects the tallyCell to its left, using
                                               /ajax/settally.php, passing workOrderTaskId and the current tally value. On success, it appropriately fills
                                               in that cell with the new tally value, again in a similarly structured text input.
                                     If only the current user has a tally for this task, then this is somewhat simplified: we skip the
                                     first row of the embedded table; otherwise it's the same.
                                // END REMOVED 2020-09-23 JM
                                /* BEGIN REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
                                if ($personCount > 1) {
                                    echo '<td>' .
                                             '<table style="border-collapse: collapse;">' .
                                                 '<tr>' .
                                                     '<td colspan="2" style="text-align:center;">' .
                                                         // 2019-10-16 JM: introduce HTML data attributes. See discussion above and http://bt.dev2.ssseng.com/view.php?id=35.
                                                         // OLD CODE removed 2019-10-16 JM:
                                                          //'<img id="' . $workordertask['workOrderTaskId'] . '" class="expandtally" style="margin:auto;" src="/cust/' . $customer->getShortName() . '/img/icons/icon_expand.gif" width="16" height="9" border="0">' .
                                                          // BEGIN REPLACEMENT
                                                          '<img data-work-order-task-id="' . $workordertask['workOrderTaskId'] . '" '.
                                                          'class="expandtally" style="margin:auto;" '.
                                                          'src="/cust/' . $customer->getShortName() . '/img/icons/icon_expand.gif" '.
                                                          'width="16" height="9" border="0">' .
                                                          // END REPLACEMENT
                                                     '</td>' .
                                                 '</tr>' .
                                                 '<tr bgcolor="#aaaaaa">' .
                                                     '<td style="border: none;" id="tallycell_' . $workordertask['workOrderTaskId'] . '">' .
                                                         '<input id="tally_' . $workordertask['workOrderTaskId'] . '" type="text" name="" value="' . htmlspecialchars($tallyValue) . '" size="3">' .
                                                     '</td>' .
                                                     '<td style="border: none;">' .
                                                        '<input onClick="setTally(' . $workordertask['workOrderTaskId'] . ')" type="image" src="/cust/' . $customer->getShortName() . '/img/icons/icon_submit_64x64.png" height="20" width="20">' .
                                                     '</td>' .
                                                 '</tr>' .
                                             '</table>'.
                                         '</td>';
                                // [Martin comment] just this guy
                                } else {
                                    echo '<td>' .
                                             '<table style="border-collapse: collapse;">' .
                                                 '<tr bgcolor="#aaaaaa">' .
                                                    '<td style="border: none;" id="tallycell_' . $workordertask['workOrderTaskId'] . '">' .
                                                        '<input id="tally_' . $workordertask['workOrderTaskId'] . '" type="text" name="" value="' . htmlspecialchars($tallyValue) . '" size="3">' .
                                                    '</td>' .
                                                    '<td style="border: none;">' .
                                                        '<input onClick="setTally(' . $workordertask['workOrderTaskId'] . ')" type="image" src="/cust/' . $customer->getShortName() . '/img/icons/icon_submit_64x64.png" height="20" width="20">' .
                                                    '</td>' .
                                                '</tr>' .
                                            '</table>' .
                                        '</td>';
                                }
                            } else {
                                echo '<td>&nbsp;</td>';
                            }
                            // END REMOVED 2020-09-23 JM
                            */

                            echo '</tr>'. "\n";
                        } else { // not ($workordertask['type'] == 'real')
                            // It's a "fake", just note it to use on subsequent loop.
                            $fakes[] = $workordertask['data'];
                        }

                        /* BEGIN REMOVED 2020-10-06 JM
                        if ($wotkey == (count($workordertasksGroupedByJob) - 1)) {
                            // 00007 if this is a no-op, why is it here?
                            // Or is there something we should do?
                        }
                        // END REMOVED 2020-10-06 JM
                        */

                        // Context for next time through loop, so we can see if we
                        // have a transition of job and/or workOrder
                        if (isset($workordertask['jobId'])) {
                            $currentJobId = $workordertask['jobId'];
                        }
                        if (isset($workordertask['workOrderId'])) {
                            $currentWorkOrderId = $workordertask['workOrderId'];
                        }
                    } // END foreach ($workordertasksGroupedByJob...

                    // Summary/total row FOR LAST JOB because it didn't get handled inside the loop.
                    // >>>00006 Should share common code with the analogous line inside loop for all other jobs.
                    echo '<tr>';
                        // 5 blank columns
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';

                        $dispval = (intval($runtot)) ? number_format((float)intval($runtot)/60, 2, '.', '') : ''; // convert minutes to hours w/ 2 digits past the decimal point

                        // Total time time for last workOrder: hours, with two digits past the decimal point.
                        echo '<td style="font-weight:bold">' . $dispval . '</td>';
                        // Blank column for each day in period
                        for ($i = 0; $i < $daysinperiod; $i++){
                            echo '<td>&nbsp;</td>';
                        }
                        // Blank column for per-task totals
                        echo '<td>&nbsp;</td>';
                        /* REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
                        echo '<td>&nbsp;</td>';
                        */
                    echo '</tr>'. "\n";
                echo '</tbody>'; // Close the last TBODY for workOrderTasks table
            echo '</table>'; // END workOrderTasks table
            ?>

            <script>
            $(function() {
            <?php
                // >>>00001 2020-06-26 JM: the following is generating the code I'd expect, but it doesn't seem to be working.
                foreach ($job_row_has_hours AS $job_row => $has_hours) {
                    if ($has_hours) {
                        ?>
                        $('#job-row-<?= $job_row ?>').addClass('has-hours');
                        <?php
                    }
                }
            ?>
            });
            </script>
            <?php

            // NOW FOR SOME TOTALS.

            /* Now, if there were any workOrderTasks (and there always should be, unless
               we are just starting a brand new 'incomplete' time period), we start a new table,
               with a column for each day in the period, plus two more columns.
               The table has a single row.
                   * For each day, we have a column whose id reflects the zero-based index of the day within the period.
                        * The column takes the number of minutes $totarray[ii] and displays it in hours, with two digits past the decimal point;
                          blank if $totarray[ii] is not set).
                   * Then comes a column, id="gtcell", with the grand total time, also written in hours, with two digits past the decimal point.
        */
            if (count($workordertasksGroupedByJob)) {
                echo '<table class="sticky-footer" border="0" cellpadding="2" cellspacing="1">';
                    // Worked hours (excludes PTO/holiday). Added 2020-06-26 JM.
                    $workTotal = 0;
                    echo '<tr class="total work-total">';
                    echo '<td style="text-align:right; background-color:white;" width="100%">Worked</td>';
                    for ($i = 0; $i < $daysinperiod; ++$i) {
                        $additionalClasses = '';
                        if ($i == $first_day_of_new_period_index) {
                            $additionalClasses = ' first-day-of-new-period';
                        }

                        if (isset($worked_totarray[$i])) {
                            $workTotal += intval($worked_totarray[$i]);
                            echo '<td id="workedcolcell_' . $i . '" class="workeddailytotalcell' . $additionalClasses .
                                '" style="background-color:lightblue"><div style="width:38px">' .
                                number_format((float)intval($worked_totarray[$i])/60, 2, '.', '') . '</div></td>';
                        } else {
                            echo '<td id="workedcolcell_' . $i . '"><div style="width:38px">&nbsp;</div></td>';
                        }
                    }
                    echo '<td style="font-weight:+1; background-color:white;" id="workedtotalcell">' . number_format((float)$workTotal/60, 2, '.', '') . '</td>';
                    // echo '<td style="background-color:white;"><div style="width:70px; background-color:white;">&nbsp;</div></td>'; // REMOVED 2020-09-23 JM
                    echo '</tr>'. "\n";

                    $ptoTotal = 0;
                    echo '<tr class="total pto-total">';
                    echo '<td style="text-align:right; background-color:white;" width="100%">PTO/Holiday</td>';
                    for ($i = 0; $i < $daysinperiod; ++$i) {
                        $additionalClasses = '';
                        if ($i == $first_day_of_new_period_index) {
                            $additionalClasses = ' first-day-of-new-period';
                        }

                        if (isset($pto_totarray[$i])){
                            $ptoTotal += intval($pto_totarray[$i]);
                            echo '<td id="ptocolcell_' . $i . '" class="ptodailytotalcell' . $additionalClasses .
                                '" style="background-color:lightblue"><div style="width:38px">' .
                                number_format((float)intval($pto_totarray[$i])/60, 2, '.', '') . '</div></td>';
                        } else {
                            echo '<td id="ptocolcell_' . $i . '"><div style="width:38px">&nbsp;</div></td>';
                        }
                    }
                    echo '<td style="font-weight:+1; background-color:white;" id="ptototalcell">' . number_format((float)$ptoTotal/60, 2, '.', '') . '</td>';
                    // echo '<td style="background-color:white;"><div style="width:70px; background-color:white;">&nbsp;</div></td>'; // REMOVED 2020-09-23 JM
                    echo '</tr>'. "\n";

                    $grandTotal = 0;
                    echo '<tr class="total overall-total">';
                        echo '<td style="text-align:right; font-weight:bold; background-color:white;" width="100%">Total</td>';
                        for ($i = 0; $i < $daysinperiod; ++$i) {
                            $additionalClasses = '';
                            if ($i == $first_day_of_new_period_index) {
                                $additionalClasses = ' first-day-of-new-period';
                            }

                            if (isset($totarray[$i])){
                                $grandTotal += intval($totarray[$i]);
                                echo '<td id="colcell_' . $i . '" class="totalcell' . $additionalClasses .
                                '"><div style="width:38px">' .
                                    number_format((float)intval($totarray[$i])/60, 2, '.', '') . '</div></td>';
                            } else {
                                echo '<td id="colcell_' . $i . '"><div style="width:38px">&nbsp;</div></td>';
                            }
                        }

                        echo '<td style="font-weight:bold; background-color:white;" id="gtcell">' . number_format((float)$grandTotal/60, 2, '.', '') . '</td>';
                        // echo '<td style="background-color:white;"><div style="width:70px; background-color:white;">&nbsp;</div></td>'; // REMOVED 2020-09-23 JM
                    echo '</tr>'. "\n";

                    // BEGIN Added 2020-07-30 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=200 (issue 4 in that bug, put date
                    //  below totals)
                    echo '<tr>'. "\n";
                        // no label needed, obvious that this is date.
                        echo '<td style="text-align:right; font-weight:bold; background-color:white;" width="100%">&nbsp</td>';
                        for ($i = 0; $i < $daysinperiod; ++$i) {
                            // In case you want it, $x->format("D") would give you the name of the day.
                            echo '<td style="font-weight:600; background-color:white;">' . $time->dates[$i]['short'] . '</td>';
                        }
                        echo '<td style="background-color:white;">&nbsp;</td>';
                        // echo '<td style="background-color:white;">&nbsp;</td>'; // REMOVED 2020-09-23 JM
                    echo '</tr>'. "\n";
                    // END Added 2020-07-30 JM

                echo '</table>'. "\n";
            }
            echo '</div>'; //  id="contain-stick-footer"
        ?>
        </div>
    </div>
</div>
<?php /* By my count (JM 2019-04-08) we are completely outside of any DIV at this point, as we should be. */ ?>

<script>
    <?php /* INPUT workOrderTaskId
             Puts the ajax_loader.gif in the tallyCell for this workOrderTask, then calls /ajax/settally.php,
             passing workOrderTaskId and the current tally value. On success, it appropriately fills in that
             cell with the new tally value, again in a similarly structured text input.
             Failures lead to alerts, but it really doesn't clean up, leaving ajax_loader.gif in the tallyCell. */ ?>
    <?php /* BEGIN REMOVED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100: tally will have nothing to do with timesheet.
    var setTally = function(workOrderTaskId) {
        var tally = document.getElementById('tally_' + workOrderTaskId);
        var formData = "workOrderTaskId=" + escape(workOrderTaskId) + "&tally=" + escape(tally.value);
        var cell = document.getElementById("tallycell_" + workOrderTaskId);
        cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';

        $.ajax({
            url: '/ajax/settally.php',
            data:formData,
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016]
                        var tally = '';
                        if (data['tally']) {
                            tally = data['tally'];
                        }

                        var html = '<input id="tally_' + workOrderTaskId + '" type="text" name="" value="' + escape(tally) + '" size="3">';
                        cell.innerHTML = html;
                    } else {
                        alert('error not success');
                    }
                } else {
                    alert('error no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    } // END setTally
    // END REMOVED 2020-09-23 JM
    */ ?>

    $(".cantfocus" ).click(function() {
        $(this).blur();
    });
</script>
<?php
// BEGIN essentially rewritten JM 2019-06-27
//  * Introduced TimeAdjustWidget
//  * Cleaned up a lot of poorly done code
//  * timeAdjust is still about half Martin's

/* Device to add or remove given amount of time (in minutes) from a particular value */
/* The following is a "device" to adjust a time: icons for -4 hours, -1 hour, -15 mins, +15 mins, + 1 hour, + 4 hours. */
$timeAdjustWidget = new TimeAdjustWidget(
        Array(
            'callback' => 'timeAdjust', // JavaScript function where the real work gets done
            'customer' => $customer
        )
);

// NOTE that the following call just builds inline HTML, CSS, JavaScript etc.
echo $timeAdjustWidget->getDeclarationHTML();   // declare/define the widget
?>
<script>
{
    // The following two variables define the time to be adjusted. They should are set
    //  in the "click" function, so they will be available to the callback function timeAdjust.
    let workOrderTaskId = 0;
    let dayInPeriod = -1;

    // When clicking an editable time...
    // This delegates event handling so that as we add and remove ".diagopen" elements,
    //  the handler continues to work without any special code to attach it to new elements.
    // workOrderTaskId and dayInPeriod are deliberately global to this handler.
    $("body").on("click", ".diagopen", function () {
        var isEditable=$(this).attr('data-isEditable');
        if ($('body').hasClass('editable') || isEditable==1) {
            workOrderTaskId = $(this).attr('data-workOrderTaskId');
            dayInPeriod = $(this).attr('data-dayInPeriod');

            // NOTE that the following call just builds inline JavaScript etc.
            // When the user clicks on a time to change it, this pops up the "device" to adjust a time
            //  and saves off what workOrderTask we are adjusting */
            <?php echo $timeAdjustWidget->getOpenJS(); ?>
        }
    });

    // Prevent typing directly into diagopen inputs
    // Allow tabbing; turn ENTER into a click
    $("body").on("keypress", ".diagopen", function (event) {
        var $this = $(this);
        if (event.which == 13) {
            // ENTER
            event.preventDefault();
            $this.click();
            return false;
        } else if (event.which == 16 || event.which == 9) {
            // shift, tab: accept these, do normal behavior
            return true;
        } else {
            // just ignore it
            event.preventDefault();
            return false;
        }
    });

    <?php
    // json_encode & then dumping straight into the JavaScript is a bit obscure,
    //  but judging by code below, this gives us an array with 0-based integer indexes,
    //  corresponding to the days in the period; for each index, the value is
    //  the date in form 'YYYY-MM-DD'.
    $js_dates = json_encode($time->dates);
    echo "var dates = ". $js_dates . ";\n";
    ?>
    // Change the time for this user associated with a particular workOrderTask and period.
    // INPUT increment: amount to change time by
    // Implicit INPUT workOrderTaskId, dayInPeriod: workOrderTaskId and dayInPeriod are deliberately global to this function.
    // For example, if we are adjusting time downward by an hour for workOrderTask 9879, and that date is dayInPeriod 2,
    //  formData will be "workOrderTaskId=9879&increment=-60&day=2020-06-03".
    //  cell will be "cell_9879_2".
    //
    // Shows AJAX loader in the edited cell; makes synchronous POST to ajax/timeadjust.php; alerts on any failure. On success, updates cell
    //  with new value and adjusts all affected totals.
    // _admin/ajax/timeadjust.php is just a wrapper for ajax/timeadjust.php so that it can identify the logged-in admin.
    function timeAdjust (increment) {
        let formData = "workOrderTaskId=" + workOrderTaskId + "&increment=" + encodeURIComponent(increment) + "&day=" +
                        encodeURIComponent(dates[dayInPeriod]['position']);
        let cell = document.getElementById("cell_" + workOrderTaskId + '_' + dayInPeriod);

        // BEGIN ADDED 2020-06-26 JM
        let cellIsPTO = $(cell).closest('tr').hasClass('pto-hours');
        // END ADDED 2020-06-26 JM

        let savedHTML = cell.innerHTML;
        cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';

        $.ajax({
            url: '/ajax/timeadjust.php',
            data: formData,
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016]
                        let html = '<input class="diagopen" type="text" '+
                                   'id="' + workOrderTaskId + '_' + dayInPeriod  + '" ' +
                                   'data-workOrderTaskId="' + workOrderTaskId + '" ' + // added JM 2019-06-27
                                   'data-dayInPeriod="' + dayInPeriod + '" ' +         // added JM 2019-06-27
                                   '" value="' + data['minutes'] + '" size="2">';

                        // other cells that will need adjustment
                        let totcell = document.getElementById("totalcell_" + workOrderTaskId);
                        let gtcell = document.getElementById("gtcell");
                        let $colcell = $("#colcell_" + dayInPeriod).find('div');   // Need to handle this one a little differently,
                                                                                   // because there is a DIV inside the cell.
                        let increment = Number(data['hourincrement']);
                        if (document.getElementById("runningcell_" + workOrderTaskId)) {
                            let runningcell = document.getElementById("runningcell_" + workOrderTaskId);

                            // Adding 2 numbers which had been written as decimals with 2 digits past the decimal point
                            let rt = Number(runningcell.innerHTML) + increment;

                            if (rt <= 0) {
                                rt = 0;
                            }

                            // Convert back to number with 2 digits past the decimal point
                            runningcell.innerHTML = rt.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                        }

                        // If the conversions here are at all confusing, see comments above where we do this for runningcell.
                        {
                            let n = Number(totcell.innerHTML) + increment;
                            if (n <= 0){
                                n = 0;
                            }
                            totcell.innerHTML = n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                        }

                        {
                            let n = Number(gtcell.innerHTML) + increment;
                            if (n <= 0){
                                n = 0;
                            }
                            gtcell.innerHTML = n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                        }

                        {
                            let n = Number($colcell.html()) + increment;
                            if (n <= 0){
                                n = 0;
                            }
                            $colcell.html(n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,'));
                        }

                        // BEGIN ADDED 2020-06-26 JM
                        if (cellIsPTO) {
                            let pto_total_cell = document.getElementById("ptototalcell");
                            let $pto_colcell = $("#ptocolcell_" + dayInPeriod).find('div');
                            {
                                let n = Number(pto_total_cell.innerHTML) + increment;
                                if (n <= 0){
                                    n = 0;
                                }
                                pto_total_cell.innerHTML = n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                            }
                            {
                                let n = Number($pto_colcell.html()) + increment;
                                if (n <= 0){
                                    n = 0;
                                }
                                $pto_colcell.html(n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,'));
                            }
                        } else {
                            let worked_total_cell = document.getElementById("workedtotalcell");
                            let $worked_colcell = $("#workedcolcell_" + dayInPeriod).find('div');
                            {
                                let n = Number(worked_total_cell.innerHTML) + increment;
                                if (n <= 0){
                                    n = 0;
                                }
                                worked_total_cell.innerHTML = n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                            }
                            {
                                let n = Number($worked_colcell.html()) + increment;
                                if (n <= 0){
                                    n = 0;
                                }
                                $worked_colcell.html(n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,'));
                            }
                        }
                        // END ADDED 2020-06-26 JM


                        // Now assign for the main cell we are working on
                        cell.innerHTML = html;

                        if (data['vacationError']) {
                            alert('Sorry, that puts you over your available amount of vacation hours');
                        }
                        if (workOrderTaskId < 0) {
                            // negative workOrderTaskId means PTO
                            vacationSummary();
                        }
                    } else {
                        alert('error not success (returned from ajax/timeadjust.php)');
                        cell.innerHTML = savedHTML; // added 2020-10-06 JM; message also clarified
                    }
                } else {
                    alert('error no \'status\' in data returned from ajax/timeadjust.php');
                    cell.innerHTML = savedHTML; // added 2020-10-06 JM; message also clarified
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/timeadjust.php'); // message clarified 2020-10-06 JM;
            }
        });
    } // END timeAdjust
}
    // END essentially rewritten JM 2019-06-27 -----------------------------

    // Added 2020-06-26: prevent click on job-number-link from bubbling up, so this link can open a
    //  Job page, and the whole rest of the containing cell can do a toggle.
    $(function() {
        $('.job-number-link').click(function(event) {
             event.stopPropagation();
        });
    });

    <?php /* INPUT: workOrderTaskId: primary key of a workOrderTask
       INPUT: taskStatusId: task status to toggle TO (1 or 9).
       Display ajax_loader.gif in the relevant cell, make the appropriate synchronous AJAX call to /ajax/task_active_toggle.php to change
       the workOrderTask status, and on success fix the cell back up to match the new value.
    */ ?>
    var setTaskStatusId = function(workOrderTaskId, taskStatusId) {
        var  formData = "workOrderTaskId=" + escape(workOrderTaskId) + "&taskStatusId=" + escape(taskStatusId);
        var cell = document.getElementById("statuscell_" + workOrderTaskId);
        cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';

        $.ajax({
            url: '/ajax/task_active_toggle.php',
            data:formData,
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016]
                        var html = '<a id="taskStatusId'+ workOrderTaskId +'" href="javascript:setTaskStatusId(' + workOrderTaskId + ',' + data['linkTaskStatusId'] + ')">' +
                                   '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_active_' + data['taskStatusId'] + '_24x24.png" ' +
                                   'width="16" height="16" border="0"></a>';
                        cell.innerHTML = html;
                    } else {
                        alert('error not success');
                    }
                } else {
                    alert('error no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    } // END setTaskStatusId

    // Refresh vacation info in document
    var vacationSummary = function() {
        var  formData = "";
        var cell = document.getElementById("vacation_summary");
        cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
        $.ajax({
            url: '/ajax/vacationsummary.php',
            data:formData,
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016]
                        <?php /* The following line was changed 2019-10-10 JM to match how Damon asked for this at http://bt.dev2.ssseng.com/view.php?id=33#c73 */?>
                        <?php /* revised 2019-10-16 JM because I didn't get that quite right! */ ?>
                        cell.innerHTML = '<b>Available PTO: ' + data['remain'] + ' hours';
                    } else {
                        alert('error not success');
                    }
                } else {
                    alert('error no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    } // END vacationSummary

<?php /* BEGIN CODE TO IDENTIFY A CURRENT JOB ON STARTUP */

    /* As noted above, exception to jobDiv_ being hidden: We try to identify a current job and open that.
        * If $_SERVER['HTTP_REFERER'] contains the substring '/job/', then we were referred here from a Job page (job.php)
          and if '/job/' is followed in the REFERER string by a valid jobId, then that is the job whose section we want to display.
        * If $_SERVER['HTTP_REFERER'] contains the substring '/workorder/', then we were referred here from a WorkOrder page (workorder.php)
          and if '/workorder/' is followed in the REFERER string by a valid workOrderId, then the job of which that workOrder is part is the
          job whose section we want to display.
        * Failing that, the cookie 'lastJobId' may give us the job whose section we want to display.
        * If we wish to display a job, we do so by setting display = "block" for the relevant "jobDiv_jobId".
    */

    $jobId = 0;
    // BEGIN ADDED 2019-10-16 JM as a matter of prudence, can't presume $_SERVER['HTTP_REFERER'] will always have a value.
    if (array_key_exists ('HTTP_REFERER', $_SERVER)) {
    // END ADDED 2019-10-16 JM
        $pos = strpos($_SERVER['HTTP_REFERER'],'/job/');
        if ($pos !== false) {
            $parts = explode("/job/", $_SERVER['HTTP_REFERER']);
            if (count($parts) == 2) {
                $jobId = 0;
                $rwname = $parts[1];
                // $db = DB::getInstance(); // removed, redundant 2020-02-24 JM
                $query = "SELECT jobId FROM " . DB__NEW_DATABASE . ".job ";
                $query .= "WHERE rwname = '" . $db->real_escape_string($rwname) . "' ";
                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0){
                        $row = $result->fetch_assoc();
                        $jobId = intval($row['jobId']);
                    } else {
                        $logger->errorDB('1593066451', "Failed to find jobId for rwname $rwname", $db);
                    }
                } else {
                    $logger->errorDB('1593066453', 'Hard DB error reading job', $db);
                }
            }
        }
        $pos = strpos($_SERVER['HTTP_REFERER'],'/workorder/');
        if ($pos !== false) {
            $parts = explode("/workorder/", $_SERVER['HTTP_REFERER']);
            if (count($parts) == 2) {
                $workOrderId = intval($parts[1]);
                $wo = new WorkOrder($workOrderId);
                if (intval($wo->getWorkOrderId())) {
                    $jobId = $wo->getJobId();
                }
            }
        }
    // BEGIN ADDED 2019-10-16 JM
    }
    // END ADDED 2019-10-16 JM
    ?>

    function getCookie(c_name) {
        if (document.cookie.length > 0) {
            c_start = document.cookie.indexOf(c_name + "=");
            if (c_start != -1) {
                c_start = c_start + c_name.length + 1;
                c_end = document.cookie.indexOf(";", c_start);
                if (c_end == -1) {
                    c_end = document.cookie.length;
                }
                return unescape(document.cookie.substring(c_start, c_end));
            }
        }
        return "";
    }

    $( document ).ready(function() {
        <?php
        // [Martin comment:] some duplicated shit here .. but who cares !!!
        if (intval($jobId)){
            echo " var x = " . intval($jobId) . ";\n\n\n\n\n";
        } else {
        ?>
            var x = getCookie('lastJobId');
        <?php
        }
        ?>


        if (x) {
            if (x > 0) {
                if (document.getElementById("jobDiv_" + x)){
                    var z = document.getElementById("jobDiv_" + x);
                    z.style.display = "block";
                    createCookie('lastJobId', x, 10); // so that, if this didn't originally come from a cookie, navigating backward or forward in time won't lose jobId
                } else {
                    var y = getCookie('lastJobId');
                    if (y) {
                        if (y > 0) {
                            var z = document.getElementById("jobDiv_" + y);
                            z.style.display = "block";
                        }
                    }
                }
            }
        }
    });
</script>

<?php /* END CODE TO IDENTIFY A CURRENT JOB ON STARTUP */ ?>

<?php
include BASEDIR . '/includes/footer.php';
?>
