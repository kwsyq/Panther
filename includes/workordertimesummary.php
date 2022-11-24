<?php
/* includes/workordertimesummary.php

   This file is intended to be included by workorder.php, invoice.php, and possibly
   other files in the future. It contains a single function: insertWorkOrderTimeSummary,
   which produces a Work Order time summary section: time summary for a single workOrder.
   
   Longtime users may know this as the "TXN" section.
   Available only if user has admin-level invoice permission.
   
   Basically, this gives the customer (as of 2020-01, always SSS) a way to 
   eyeball how well they did in business terms for a particular workOrder.
   
   We provide an easy way to hide this, because it isn't something they'd always want someone
   to be able to see "over their shoulder". Hidden by default.
   
*/
require_once BASEDIR . '/inc/config.php';
require_once BASEDIR . '/inc/perms.php';

// Return HTML to show/hide Workorder Time Summaries. We only want one of these, even if there are multiple Workorder Time Summaries.
// work-order-time-summary-heading in the following is in support of ajax/workordertimesummary.php, which wraps this and adds more content.
// NOTE that we can have more than one of these, for top & bottom of section
function workOrderTimeSummaryShowHide() {
    return '<button class="show-work-order-time-summary btn btn-secondary btn-sm mb-4 mt-2">Show</button>' . "\n" .
    '<button class="hide-work-order-time-summary btn btn-secondary btn-sm mb-4 mt-2" style="display:none">Hide</button>' . "\n" .
    '<script>' . "\n" .
    '$(function() {' . "\n" .
    '    $(document).off(".timesummary");' . "\n" . // clean up anything from a prior use
    '    $(document).on("click.timesummary", ".show-work-order-time-summary", function() {' . "\n" .
    '        $(".hide-work-order-time-summary, .work-order-time-summary-body, .work-order-time-summary-heading").show();' . "\n" .
    '        $(".show-work-order-time-summary").hide();' . "\n" .
    '    });' . "\n" .
    '    $(document).on("click.timesummary", ".hide-work-order-time-summary", function() {' . "\n" .
    '        $(".hide-work-order-time-summary, .work-order-time-summary-body, .work-order-time-summary-heading").hide();' . "\n" .
    '        $(".show-work-order-time-summary").show();' . "\n" .
    '    });' . "\n" .
    '})' . "\n" .
    '</script>' . "\n";
}

// Alternative to function workOrderTimeSummaryShowHide: instead of providing show/hide buttons, 
// provide a 'close' button that deletes it outright.
// NOTE that (1) among other things this removes itself, (2) we can have more than one of these, for top & bottom of section,
//  and (3) caller can add class 'kill-with-work-order-time-summary' to anything they want to have be deleted along with this.
function workOrderTimeSummaryCloseButton() {
    return '<button class="kill-work-order-time-summary">Close</button>' . "\n" .
    '<script>' . "\n" .
    '$(function() {' . "\n" .
    '    $(document).off(".timesummary");' . "\n" . // clean up anything from a prior use
    '    $(document).on("click.timesummary", ".kill-work-order-time-summary", function() {' . "\n" .
    '        $(".kill-work-order-time-summary, .work-order-time-summary-body, .work-order-time-summary-heading, .kill-with-work-order-time-summary").remove();' . "\n" .
    '    });' . "\n" .
    '})' . "\n" .
    '</script>' . "\n";
}

// Return HTML for the WorkOrder Time Summary for one workOrder  
//   INPUT $workOrder - WorkOrder object, mandatory
//   INPUT $elementgroups - the return of $workOrder->getWorkOrderTasksTree(); optional, because
//      we can easily enough call to get that, but it can take some time to fetch and we are likely already
//      to have it around.
//   INPUT $initiallyHidden - Boolean, default true
function workOrderTimeSummaryBody($workOrder, $elementgroups=null, $initiallyHidden=true) {
    global $userPermissions, $customer;
    $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);
    if ( ! $checkPerm ) {
        return '';
    }
    
    $ret = '<div class="work-order-time-summary-body" ' . ($initiallyHidden ? 'style="display:none">' : '') . "\n";
    // ===================
    // 2-column table, one row per elementgroup, no headers. Second column contains subtable.
    // Re: "one row per elementgroup":
    //    More precisely, note that the elementgroup has to meet certain conditions to get a row; 
    //    $elementgroup['elementId'] can be any of the following
    //     * an elementId
    //      * 0 or '' meaning "general, no element".
    //      * (This should no longer occur in v2020-4 or later) PHP_INT_MAX meaning "multiple elements"
    //      * (Introduced in v2020-4) a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements"
    $ret .= '<table border="1" cellpadding="0" cellspacing="0">' . "\n";
    $gtMinutes = 0;
    $gtCost = 0;
    $individuals = array();
    if ($elementgroups===null) {
        $elementgroups = $workOrder->getWorkOrderTasksTree();
    }
    foreach ($elementgroups as $elementgroup) {
        // >>>00014 NOTE that we test for the 'element' member being set, but then 
        //  presume members 'elementId' and 'gold' will be set. A bit weird. Why trust one 
        //  and not the other?
        if (isset($elementgroup['element']) || ($elementgroup['elementId'] == PHP_INT_MAX)) {                                                                             
            $element = $elementgroup['element'];
            if ($element || ($elementgroup['elementId'] == PHP_INT_MAX)) {
                $elementMinutes = 0;
                $elementCost = 0;
                $ret .= '<tr>' . "\n";
                // ===================
                // column 1: display the element name; apparently there is a case where this would
                //  be blank; not sure whether that case really exists.
                // ===================
                $en = '';
                if ($element) {
                    /* BEGIN REPLACED 2020-09-09 JM
                    $en = $element->getElementName();
                    // END REPLACED 2020-09-09 JM
                    */
                    // BEGIN REPLACEMENT 2020-09-09 JM
                    if (is_string($element) && strpos($element, ',') !== false) {
                        // new style multi-element introduced in v2020-4. Should have a name string available, parallel to the Id.
                        $en = $elementgroup['elementName'];
                    } else {                    
                        $en = $element->getElementName();
                    }
                    // END REPLACEMENT 2020-09-09 JM
                } // >>>00014 is the else case here something "normal"? What would it be about? 
                                
                $ret .= '<td>' . $en . '</td>' . "\n";
                                
                // ===================
                // column 2:
                //  * If $elementgroup does not have member 'gold', then this column is blank.
                //  * If $elementgroup has member 'gold', then $elementgroup['gold'] is an array, 
                //    each of whose elements corresponds to a task (more precisely, to a workOrderTask).  
                //    Column 2 is a nested table as follows; each row but the last corresponds to a task, 
                //    with the last row being a summary. 
                //    We only list "real" tasks (overtly represented in the DB); "fake" tasks are omitted.
                //
                // Within this table:
                //  * Column 1: Task description
                //  * Column 2: We display person name, time in hours (two digits past the decimal point), cost in dollars 
                //    ('$' and two digits past the decimal point), then date in parentheses in 'm/d/y' format (e.g. '2/14/19', 
                //    leading zeroes on month & day, 2-digit year).
                //      * Display for each associated workOrderTaskTime will be separated by semicolons if there is more than one) 
                //  * Column 3: If any time was associated with this task, total time in hours (two digits past the decimal point);
                //    otherwise blank
                //  * Column 4: If any time was associated with this task, total cost in dollars ('$' and dollar amount 
                //    with two digits past the decimal point); otherwise blank 
                // Also, as we loop, for each associated row from DB table workOrderTaskTime, we call 
                //    WorkOrderTask::getWorkOrderTaskTimeWithRates, which does quite a bit of work for us, 
                //    including grabbing relevant data from DB table customerPersonPayPeriodInfo, and  
                //    calculating actual cost of the relevant time for either an hourly or salaried employee.
                //    We accumulate various totals, including $individuals[personId]['totalMinutes'] and 
                //    $individuals[personId]['totalCost'] for each distinct personId we encounter.
                $ret .= '<td>' . "\n";
                if (isset($elementgroup['gold'])) {
                    $gold = $elementgroup['gold'];
                    $ret .= '<table border="0" cellpadding="0" cellspacing="0">' . "\n";
                    foreach ($gold as $task) {
                        if ($task['type'] == 'real') {
                            $ret .= '<tr>' . "\n";
                            $wot = $task['data'];

                            //  * Column 1: Task description
                            $ret .= '<td>' . $wot->getTask()->getDescription() . '</td>' . "\n";
                            
                            $totalMinutes = 0;
                            $totalCost = 0;
                            
                            // Martin comment: NOTE :: read in method about passing customer here .. its a kludge
                            $times = $wot->getWorkOrderTaskTimeWithRates($customer);
                            
                            // * Column 2: Per-person time, cost, date; see note above for more detailed description 
                            $ret .= '<td>' . "\n";
                            foreach ($times as $tkey => $time) {
                                if ($tkey) {
                                    // not the first one
                                    $ret .= ';';	
                                }
                                    
                                $p = new Person($time['personId']);
                                
                                if (!isset($individuals[$time['personId']])) {
                                    $individuals[$time['personId']] = array('totalMinutes' => 0, 'totalCost' => 0);
                                }
                            
                                $individuals[$time['personId']]['totalMinutes'] += intval($time['minutes']);
                                $individuals[$time['personId']]['totalCost'] += intval($time['cost']);
                                    
                                $rate = 0;
                                $ret .= $p->getName() . ' ' . $time['minutes']/60 . 'hr @ $' . number_format($time['hourly']/100, 2) . ' = $' . number_format($time['cost'], 2);
                                    
                                $ret .= '(' . (date("m/d/y", strtotime($time['day']))) . ')';
                                    
                                $totalMinutes += intval($time['minutes']);
                                $totalCost += intval($time['cost']);
                                $elementMinutes += intval($time['minutes']);
                                $elementCost += intval($time['cost']);
                                $gtMinutes += intval($time['minutes']);
                                $gtCost += intval($time['cost']);
                            } // END foreach ($times
                            
                            $ret .= '</td>' . "\n";
                            
                            //  * Column 3: If any time was associated with this task, total time in hours 
                            //    (two digits past the decimal point); otherwise blank.
                            //  * Column 4: If any time was associated with this task, total cost in dollars 
                            //    ('$' and dollar amount with two digits past the decimal point); otherwise blank. 
                            if (intval($totalMinutes)) {
                                $ret .= '<td>' . number_format(intval($totalMinutes) / 60, 2) . 'hr' . '</td>' . "\n";
                                $ret .= '<td>$' . number_format($totalCost, 2) . '</td>' . "\n";
                            } else {
                                $ret .= '<td>&nbsp;</td>' . "\n";
                                $ret .= '<td>&nbsp;</td>' . "\n";
                            }
                            $ret .= '</tr>' . "\n";
                        }
                    } // END foreach ($gold...
                                            
                    // Totals for time & money
                    $ret .= '<tr>' . "\n";
                        $ret .= '<td>&nbsp;</td>' . "\n";
                        $ret .= '<td>&nbsp;</td>' . "\n";
                        $ret .= '<td>' . number_format($elementMinutes/60, 2) . 'hr</td>' . "\n";
                        $ret .= '<td>$' . number_format($elementCost, 2) . '</td>' . "\n";
                    $ret .= '</tr>' . "\n";
                    $ret .= '</table>' . "\n";
                } // END if (isset($elementgroup['gold']))
                $ret .= '</td>' . "\n";
                $ret .= '</tr>' . "\n";
            } // END ($element || ($elementgroup['elementId'] == PHP_INT_MAX))
        } // END if (isset($elementgroup['element']) || ($elementgroup['elementId'] == PHP_INT_MAX))
    } // END foreach ($elementgroups...
    $ret .= '</table>' . "\n";
            
    /*      
        3-column table, one row per person who worked on any of these tasks and a last row for Total; columns (with headers) are:
           * Name: formatted person name except for last row 'Total'
           * Total Time: time in hours (two digits past the decimal point)
           * Total Cost: cost in dollars (dollar amount with two digits past the decimal point)
    */
    $ret .= '<table border="1" cellpadding="0" cellspacing="0">' . "\n";
        $ret .= '<tr>' . "\n";
        $ret .= '<th>Name</th>' . "\n";
        $ret .= '<th>Total Time</th>' . "\n";
        $ret .= '<th>Total Cost</th>' . "\n";
    $ret .= '</tr>' . "\n";
    
    foreach ($individuals as $ikey => $individual) {
        $ret .= '<tr>' . "\n";
            $p = new Person($ikey);
            $ret .= '<td>' . $p->getFormattedName(1) . '</td>' . "\n"; 
            $ret .= '<td>' . number_format($individual['totalMinutes']/60, 2) . '</td>' . "\n";
            $ret .= '<td>$' . number_format($individual['totalCost'], 2) . '</td>' . "\n";
        $ret .= '</tr>' . "\n";
    }
    $ret .= '<tr>' . "\n";
        $ret .= '<td>Total</td>' . "\n";
        $ret .= '<td>' . number_format($gtMinutes/60, 2) . '</td>' . "\n";
        $ret .= '<td>' . number_format($gtCost, 2) . '</td>' . "\n"; // >>>00026 JM: oddly no dollar sign on the "Total" line, I'm guessing that's a cosmetic error
    $ret .= '</tr>' . "\n";
        
    $ret .= '</table>' . "\n";

    /*      
        If there are any invoices for this workOrder:
          * We display a total of these invoices, "Inv. Total: nn.nn" where nn.nn is dollar amount with two digits 
            past the decimal point. If the relevant row in DB table Invoice has a nonzero 'totalOverride' value we 
            use that, otherwise we use 'Total'. 
            (>>>00001 JM what if there was a legitimate override that brought the invoice total down to zero? How do we handle that?)
          * We display "Mult : nn.nn" where nn.nn is a number with two digits past the decimal point. If the "Total Cost" reported above 
            was 0, then nn.nn is just 0.00. Otherwise, it is a ratio obtained by dividing "Inv. Total" immediately above by "Total Cost".
    */
    $invs = $workOrder->getInvoices();
    if (count($invs)) {
        $inv = $invs[count($invs) - 1];
        $invtotal = $inv->getTotal();
        $invTotaloverride = $inv->getTotalOverride();
        
        if (!is_numeric($invtotal)) {
            $invtotal = 0;	
        }
        if (!is_numeric($invTotaloverride)){
            $invTotaloverride = 0;
        }
            
        $it = 0;
        
        if ($invTotaloverride > 0){
            $it = $invTotaloverride;
        } else {
            $it = $invtotal;
        }

        $ret .= 'Inv Total : ' . $it . '<br /><br />' . "\n";  // >>>00026 JM: oddly no dollar sign on the "Total" line, I'm guessing that's a cosmetic error
        $mult = ($gtCost > 0) ? $it / ($gtCost) : 0;
        $ret .= 'Mult : ' . number_format($mult, 2) . '<br /><br />' . "\n";
    }
    $ret .= '</div>' . "\n";
    
    return $ret; 
} // END function workOrderTimeSummaryBody 

//   INPUT $workOrder - WorkOrder object, mandatory
//   INPUT $elementgroups - the return of $workOrder->getWorkOrderTasksTree(); optional, because
//      we can easily enough call to get that, but it can take some time to fetch and we are likely already
//      to have it around.
function insertWorkOrderTimeSummary($workOrder, $elementgroups=null) {
    global $userPermissions, $customer;
    $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);
    if ($checkPerm) {
    ?>
        <div class="full-box clearfix">
            <h2 class="heading">Work Order time summary</h2>
            <?php 
                echo workOrderTimeSummaryShowHide();
                echo workOrderTimeSummaryBody($workOrder, $elementgroups, true);
                // sneak in an extra "hide" button at the bottom
                echo '<button class="hide-work-order-time-summary bnt btn-secondary btn-sm mb-4 mt-2" style="display:none">Hide</button>'. "\n";
            ?>
        </div>
    <?php
    } // END if ($checkPerm)
} // END function insertWorkOrderTimeSummary
?>