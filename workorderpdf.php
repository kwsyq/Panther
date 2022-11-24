<?php
/* workorderpdf.php

   EXECUTIVE SUMMARY: Uses an extended version of FPDF to prepare a PDF report about a workOrder.

   PRIMARY INPUT: $_REQUEST['workOrderId']
   
   OPTIONAL INPUT: $_REQUEST['stampId'] : overrides default stamp. Added for v2020-3. 
   
*/

include './inc/config.php';
include './inc/access.php';

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//ini_set('display_errors',1);
//error_reporting(-1);
// END COMMENTED OUT BY MARTIN BEFORE 2019

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (!intval($workOrderId)) {
    // Invalid workOrderId => out of here
    header("Location: /");
}

$stampId = isset($_REQUEST['stampId']) ? intval($_REQUEST['stampId']) : null;

$workOrder = new WorkOrder($workOrderId, $user);
$job = new Job($workOrder->getJobId());

$pdf = new PDF('P', 'mm', 'Letter');
$pdf->AddFont('Tahoma', '', 'Tahoma.php');
$pdf->AddFont('Tahoma', 'B', 'TahomaB.php');
$pdf->AliasNbPages();
$pdf->AddPage();

$w = $pdf->GetPageWidth();
$h = $pdf->GetPageHeight();

// Set up margins for 2-column report, including gutter down the middle
$rightmargin = 10;
$leftmargin = 10;
$topmargin = 10;
$centerspace = 20;

$maxcolwidth = ($w/2) - ($centerspace/2) - $leftmargin;

$pdf->SetMargins($leftmargin, $topmargin, $rightmargin);

$pdf->setDoFooter(0);

// ============ 
// JOB NUMBER

$pdf->SetFont('Tahoma', '', 10);
$pdf->cell(0, 5, 'Job', 0);
$pdf->SetY($pdf->GetY() + 5);
$pdf->Line($leftmargin, $pdf->GetY(), $w - $rightmargin, $pdf->GetY());
$keep = $pdf->GetY();
$pdf->SetY($pdf->GetY() + 3);

$pdf->SetY($pdf->GetY() + 5);

$pdf->SetFont('Tahoma', 'B', 40);
$pdf->cell(0, 5, $job->getNumber(), 0);

$joby = $pdf->GetY() + 10; // size of font

// ============ 
// JOB NAME

$pdf->SetY($keep + 2);
$pdf->SetX(($w/2) + ($centerspace/2));

$text = $job->getName(); // Martin comment: . " then we can see it all and then blah blah blah and a bit extra to test some wrap then a bit more to see what happens and then what happens";

// BEGIN MARTIN COMMENT 
// some weird shit here.  setting font before getting width was
// fucked because it seemed to not calculate with the new font specified here.
// so doing the calculation based on when the font was bold.
// so it seems to get the correct number of lines.
// END MARTIN COMMENT

$pdf->SetFont('Tahoma', 'B', 17);
$total_string_width = $pdf->GetStringWidth($text);

$number_of_lines = ($total_string_width - 1) / $maxcolwidth;
$number_of_lines = ceil( $number_of_lines );  // Martin comment: Round it up.

$line_height = 5;                             // Martin comment: Whatever your line height is.
$height_of_cell = $number_of_lines * $line_height;
$height_of_cell = ceil( $height_of_cell );    // Martin comment: Round it up.

$pdf->MultiCell(intval($maxcolwidth), $line_height,$text, 0, 'L');

$namey = $pdf->GetY() + 1;
if ($namey > $joby){
    $pdf->SetY($namey + 1);
} else {
    $pdf->SetY($joby);
}

$pdf->Line($leftmargin, $pdf->GetY(), $w - $rightmargin, $pdf->GetY());

// ============ 
// WORK ORDER

$pdf->SetY($pdf->GetY() + 10);
$topOfWorkOrder = $pdf->GetY(); // added 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82

$pdf->SetFont('Tahoma', '', 10);
$pdf->cell(0, 5, 'Work Order', 0);
$pdf->SetY($pdf->GetY() + 5);
$pdf->Line($leftmargin, $pdf->GetY(), ($w/2) - ($centerspace/2), $pdf->GetY());
$pdf->SetY($pdf->GetY() + 3);

$text = $workOrder->getName();

// BEGIN MARTIN COMMENT 
// some weird shit here.  setting font before getting width was
// fucked because it seemed to not calculate with the new font specified here.
// so doing the calculation based on when the font was bold.
// so it seems to get the correct number of lines.
// END MARTIN COMMENT 

$pdf->SetFont('Tahoma', 'B', 17);
$total_string_width = $pdf->GetStringWidth($text);

$number_of_lines = ($total_string_width - 1) / $maxcolwidth;
$number_of_lines = ceil( $number_of_lines );  // Martin comment: Round it up.


$line_height = 5;                             // Martin comment: Whatever your line height is.
$height_of_cell = $number_of_lines * $line_height;
$height_of_cell = ceil( $height_of_cell );    // Martin comment: Round it up.

$pdf->MultiCell(intval($maxcolwidth), $line_height,$text, 0, 'L');

$pdf->SetY($pdf->GetY() + $height_of_cell);

// ============ 
// ELEMENT(S)

/* BEGIN REPLACED 2020-10-30 JM: http://bt.dev2.ssseng.com/view.php?id=260 says all we really want here
   is a list of elements, independent of tasks.
$elementgroups = $workOrder->getWorkOrderTasksTree();
$plural = 's';

if (count($elementgroups) == 1) {
    $plural = '';
}
// END REPLACED 2020-10-30 JM
*/
// BEGIN REPLACEMENT 2020-10-30 JM
$elements = $job->getElements();
$plural = 's';

$plural = count($elements) == 1  ? '' : 's'; // A bit specific to the English language, but we can get away with it for the foreseeable future
// END REPLACEMENT 2020-10-30 JM

$pdf->SetY($pdf->GetY() + 10);

$pdf->SetFont('Tahoma', '', 10);
$pdf->cell(0, 5, 'Element' . $plural, 0);
$pdf->SetY($pdf->GetY() + 5);
$pdf->Line($leftmargin, $pdf->GetY(), ($w/2) - ($centerspace/2), $pdf->GetY());
$pdf->SetY($pdf->GetY() + 3);

/* BEGIN REPLACED 2020-10-30 JM: http://bt.dev2.ssseng.com/view.php?id=260 says all we really want here
   is a list of elements, independent of tasks.
foreach ($elementgroups as $elementgroup) {
    $en = '';
    if (isset($elementgroup['elementId']) && (intval($elementgroup['elementId']) == PHP_INT_MAX)) { // PHP_INT_MAX should no longer occur in v2020-4. 
        $en = $elementgroup['elementName'];
    } else {
        // JM 2020-10-28:
        // http://bt.dev2.ssseng.com/view.php?id=260 (Print error for stamp PDF (workOrder PDF)) is because the following was broken
        // $en = ( intval($elementgroup['element']->getElementId()) ) ? $elementgroup['element']->getElementName() : 'General';
        // Here is a quick and dirty fix, but we need to discuss the actual intention here.
        $en = $elementgroup['elementName'] ? $elementgroup['elementName'] : 'General';
    }
// END REPLACED 2020-10-30 JM
*/
// BEGIN REPLACEMENT 2020-10-30 JM
foreach ($elements as $element) {
    $en = $element->getElementName();
// END REPLACEMENT 2020-10-30 JM
    
    if (strlen($en)) {
        $text = $en;

        $pdf->SetFont('Tahoma', 'B', 17);
        $total_string_width = $pdf->GetStringWidth($text);

        $number_of_lines = ($total_string_width - 1) / $maxcolwidth;

        $number_of_lines = ceil( $number_of_lines );  // Martin comment: Round it up.

        $line_height = 5;                             // Martin comment: Whatever your line height is.
        $height_of_cell = $number_of_lines * $line_height;
        $height_of_cell = ceil( $height_of_cell );    // Martin comment: Round it up.

        $pdf->MultiCell(intval($maxcolwidth), $line_height,$text, 0, 'L');
        $pdf->SetY($pdf->GetY() + $height_of_cell);
    }
}

// So we can get back where we want for "TEAM"
$topOfTeam = $pdf->GetY() + 25; // introduced 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82

// ==============
// EOR/stamp 
// (Section moved up from below 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82)
// Reworking this again for v2020-3
/* BEGIN REPLACED 2020-04-27 JM 
$positions = $workOrder->getTeamPosition(TEAM_POS_ID_EOR, true); // $positions is really EORs 
if (count($positions)) {
    $pos = $positions[0];
    $cp = new CompanyPerson($pos['companyPersonId']);
    $stamps = $eor_stamp; // from inc/config.php
    
    if (isset($stamps[$cp->getPersonId()])) {
        $num_pages = $pdf->setSourceFile2(BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/' . $stamps[$cp->getPersonId()] . '');
        $template_id = $pdf->importPage(1); // Martin comment: if the grafic is on page 1
        $size = $pdf->getTemplateSize($template_id);

        $pdf->useTemplate($template_id, $w-$rightmargin-$size['w'], $topOfWorkOrder, $size['w']*1, $size['h']*1);  // introduced 2020-01-21 JM
        $pdf->Rect($w-$rightmargin-$size['w'], $topOfWorkOrder, $size['w']*1, $size['h']*1);
    }
}
// END REPLACED 2020-04-27 JM
*/

// BEGIN REPLACEMENT 2020-04-27 JM
$stampFilename = '';
$stampIsEor = false;
if ($stampId) {
    $stamp = new Stamp($stampId);
    if ($stamp) {
        $stampFilename = $stamp->getFilename();
        if (!$stampFilename) {
            $logger->error2('1588027229', "stampId $stampId: blank filename, will attempt fallbacks");
        }
        $stampIsEor = $stamp->getIsEorStamp();
    } else {
        $logger->error2('1588027228', "stampId $stampId: cannot build class, will attempt fallbacks"); 
    }
    unset($stamp);
}
if (!$stampFilename) {
    $logger->info2('1588027728', "No stamp overtly passed, trying to calculate");
    $eorTeamRows = $workOrder->getTeamPosition(TEAM_POS_ID_EOR, true); // $positions is really EORs 
    if (count($eorTeamRows)) {
        $eorTeamRow = $eorTeamRows[0]; // if somehow we have more than one, use the first
        $companyPersonId = $eorTeamRow['companyPersonId'];
        $eorPersonId = null;
        if ($companyPersonId) {
            $companyPerson = new CompanyPerson($companyPersonId);
            if ($companyPerson) {
                $eorPersonId = $companyPerson->getPersonId();
            } else {
                $logger->error2('1588028184', "WorkOrder $workOrderId no valid personId for companyPersonId $companyPersonId");
            }
        } else {
            $logger->warn2('1588028165', "WorkOrder $workOrderId no valid companyPersonId for EOR on team row {$eorTeamRows[0]['teamId']}");
        }        
        
        if ($eorPersonId) {
            $criteria = Array();
            $criteria['customerId'] = $customer->getCustomerId();
            $criteria['active'] = 'yes';
            $criteria['eor'] = 'yes';
            $locations = $job->getLocations();
            $state = '';
            if ($locations) {
                $state = $locations[0]->getState();
            }
            if (!$state) {
                $state = HOME_STATE;
            }
            $criteria['state'] = $state;
            unset($locations, $state);
            
            $stamps = Stamp::getStamps($criteria);
            if (!$stamps) {
                $logger->warn2('1588028150', 'no stamps!'); 
            } else {        
                foreach ($stamps AS $stamp) {
                    if ($stamp->getEorPersonId() == $eorPersonId) {
                        $stampFilename = $stamp->getFilename();
                        $stampIsEor = true;
                        break;
                    }
                }
            }
        }
    }
    unset($stamps, $stamp, $eorTeamRows, $eorTeamRow, $companyPersonId, $companyPersonId, $eorPersonId, $criteria);
}

$limiting_stamp_area = false; // ADDED 2020-08-05 JM to address http://bt.dev2.ssseng.com/view.php?id=210 (We'd like the stamp to import 
                              // onto the work order printout sheet at its actual size). I'm adding this as a variable so we can go back
                              // to the earlier policy if needed, but the idea when this is false is that if the stamp fits on the page,
                              // we use it "at size" and we don't worry about overwriting other content.

if ($stampFilename) {
    $num_pages = $pdf->setSourceFile2(BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/' . $stampFilename . '');
    $template_id = $pdf->importPage(1); // if the graphic is on page 1
    $size = $pdf->getTemplateSize($template_id);
    
    // $limiting_stamp_area == false case ADDED 2020-08-05 JM to address http://bt.dev2.ssseng.com/view.php?id=210
    $max_desired_stamp_width = $limiting_stamp_area? $maxcolwidth : $w;
    $max_desired_stamp_height = $limiting_stamp_area? 50.8 : $h; // 50.8 determined heuristically by examining EOR stamps.
    
    // For what it's worth, EOR stamps should be square, and should be already be 50.8 x 50.8, but let's 
    // make sure we get sane behavior in other cases.
    // 1.0 in the following effectively means 'never scale up'
    $stamp_scale = min(1.0, $max_desired_stamp_width / $size['width'], $max_desired_stamp_height / $size['height']);
    
    $pdf->useTemplate($template_id, $w-$rightmargin-$size['width']*$stamp_scale, $topOfWorkOrder, $size['width']*$stamp_scale, $size['height']*$stamp_scale);
    if ($stampIsEor) {
        $pdf->Rect($w-$rightmargin-$size['width']*$stamp_scale, $topOfWorkOrder, $size['width']*$stamp_scale, $size['height']*$stamp_scale);
    }
}
// END REPLACEMENT 2020-04-27 JM

// ============ 
// TEAM
// $pdf->SetY($pdf->y + 25); // removed 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82
$pdf->SetY($topOfTeam); // introduced 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82
$workordermembers = $workOrder->getTeam(1);
$jobmembers = $job->getTeam(1);

$members = array();
foreach ($workordermembers as $mem) {
    $mem['disp'] = 'WO';
    $members[] = $mem;
}

foreach ($jobmembers as $mem) {
    $mem['disp'] = 'J';
    $members[] = $mem;
}

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$plural = 's';

if (count($members) == 1){
    $plural = '';
}

$pdf->SetY($pdf->y + 10);

$pdf->SetFont('Tahoma','',10);
$pdf->cell(0, 5, 'Team Member' . $plural, 0);
$pdf->SetY($pdf->y + 5);
$pdf->Line($leftmargin, $pdf->y, ($w/2) - ($centerspace/2),$pdf->y);
$pdf->SetY($pdf->y + 3);
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

$pdf->setY($pdf->GetY() + 5);
$pdf->SetFont('Tahoma', 'B', 10);
$pdf->setX($leftmargin);
$pdf->Cell(50, 4, 'Team Member', 0, 0, 'L');
$pdf->Cell(75, 4, 'Company', 0, 0, 'L');
$pdf->Cell(45, 4, 'Position', 0, 0, 'L');
$pdf->setY($pdf->GetY() + 5);
//$pdf->Line($leftmargin, $pdf->y, ($w/2) - ($centerspace/2),$pdf->y); // COMMENTED OUT BY MARTIN BEFORE 2019
$pdf->Line($leftmargin, $pdf->GetY() , $pdf->GetPageWidth() - $rightmargin, $pdf->GetY());

$pdf->setY($pdf->GetY() + 2);
foreach ($members as $member) {
    $pdf->SetFont('Tahoma', 'B', 10);
    $pdf->setX($leftmargin);
    $pdf->Cell(50, 4, '(' . $member['disp'] . ') ' . $member['firstName'] . " " . $member['lastName'], 0, 0, 'L');
    $pdf->Cell(75, 4, $member['companyName'], 0, 0, 'L');
    $pdf->Cell(45, 4,$member['name'], 0, 0, 'L');

    $pdf->setY($pdf->GetY() + 5);
}

// ============ 
// QR CODE

$dat = "workOrderId=" . $workOrder->getWorkOrderId() . "&date=" . date("Y-m-d");
$dat = $job->getNumber() . "&&&&&" . date("Y-m-d") . "&&&&&";

/*
OLD CODE removed 2019-02-05 JM
$pdf->Image('http://sssuser:sonics^100@' . HTTP_HOST . '/other/phpqrcode.php?codeData=' . urlencode($dat), $w - 60, $h - 77, 50, 50,'png');
*/
// BEGIN NEW CODE 2019-02-05 JM
// This was failing 2019-10-04 JM. See http://bt.dev2.ssseng.com/view.php?id=32
//  I verified 2019-10-04 that my code here does exactly what Martin's did, so that's not the issue; the issue is
//  presumably either that the string we pass to $pdf->Image was bad in the first place (wouldn't surprise me:
//  what's this stuff about "sssuser:sonics^100"?, and what does this have to do with the details subsystem), 
//  or there is a problem in other/phpqrcode.php or something it calls.
/* SO JUST KILL IT: Get rid of QR codes 2020-01-16 JM per http://bt.dev2.ssseng.com/view.php?id=74
$pdf->Image('http://sssuser:'.DETAILS_BASICAUTH_PASS.'@'.HTTP_HOST . '/other/phpqrcode.php?codeData=' . urlencode($dat), $w - 60, $h - 77, 50, 50, 'png');
*/
// END NEW CODE 2019-02-05 JM

// ============ 
// EOR section used to be here, moved up 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$pdf->SetFillColor(240);
$stamptext = $job->getNumber();
$pdf->setY($pdf->h - 20 - ($size['h'] / 2) - 1);
$pdf->SetFont('Tahoma','B',10);
$stamptextwidth = $pdf->GetStringWidth($stamptext);
$pdf->Cell(($size['w']/2) + 3,0,"",0,0,'L');
$pdf->Cell($stamptextwidth + 1,5,$stamptext,0,0,'L',1);
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

$pdf->setY($pdf->GetPageHeight() - 30 );
$pdf->SetX($pdf->GetPageWidth() - 45);

$pdf->SetFont('Tahoma', '', 7);
$pdf->cell(0, 5, "Page Printed : " . date("Y-m-d"), 0);

$pdf->SetFont('Tahoma', 'B', 9);
/*
OLD CODE REMOVED 2020-01-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82
REPLACEMENT IS ABOVE
$pdf->RotatedText(16, $pdf->h - 31, $workOrder->getWorkOrderId(), 315);
*/
// BEGIN NEW CODE INTRODUCED 2020-02-21 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=82; further reworked later to allow non-EOR stamps
if ($stampIsEor) {
    $size = $pdf->getTemplateSize($template_id);
    $workorder_offset_from_corner = 4;
    $ad_hoc_fudge_factor = $workOrderId > 1000000 ? 12 : 
                           ($workOrderId > 100000 ? 10 :
                           ($workOrderId > 10000 ? 8 : 6 ));
    
    $pdf->RotatedText($w-$rightmargin-$size['width']*$stamp_scale+$workorder_offset_from_corner, $topOfWorkOrder + $size['height']*$stamp_scale - $workorder_offset_from_corner - $ad_hoc_fudge_factor, $workOrderId, 315);
} else {
    $ad_hoc_fudge_factor = $workOrderId > 1000000 ? 16.5 : 
                           ($workOrderId > 100000 ? 14.5 :
                           ($workOrderId > 10000 ? 13.5 : 11.5 ));
    // Not actually rotated: rotation is zero, but this lets us put it there without using $pdf->setX, $pdf->setY
    $pdf->RotatedText($w-$rightmargin-$ad_hoc_fudge_factor, $topOfWorkOrder + 4.3, $workOrderId, 0);
}
// END NEW CODE 2020-02-21 JM

$pdf->SetCompression(false);

$pdf->Output('CoverSheet_' . $job->getNumber() . '-' . $workOrder->getWorkOrderId() . '.pdf', 'D');

?>