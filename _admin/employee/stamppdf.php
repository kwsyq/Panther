<?php
/* _admin/employee/stamppdf.php

    Create a simple PDF just to show a stamp.
    Intended to be used in Admin | Employees.
    
    INPUT $_REQUEST['stamp']: name of file, within BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/'
*/

include '../../inc/config.php';
// >>>00038: might want to guarantee only admins can get in here
// >>>00002, >>>00016: should validate input, probably guarantee that extension is 'PDF' or 'pdf'. Probably could use a lot more misc checking.

$pdf = new PDF('P', 'mm', 'Letter');
$pdf->AddFont('Tahoma', '', 'Tahoma.php');
$pdf->AddFont('Tahoma', 'B', 'TahomaB.php');
$pdf->AliasNbPages();
$pdf->AddPage();

$rightmargin = 10;
$leftmargin = 10;
$topmargin = 10;
$pdf->SetMargins($leftmargin, $topmargin, $rightmargin);

$stamp = $_REQUEST['stamp'];

$num_pages = $pdf->setSourceFile2(BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/' . $stamp);
$template_id = $pdf->importPage(1); // Martin comment: if the grafic is on page 1
$size = $pdf->getTemplateSize($template_id);

$pdf->useTemplate($template_id, 0, 0, $size['width']*1, $size['height']*1);
$pdf->Rect(0, 0, $size['width']*1, $size['height']*1);

$pdf->Output('', 'I');
?>