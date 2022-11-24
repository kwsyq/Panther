<?php 
/*  inc/classes/PDF.class.php
    Includes FPDF (see https:www.fpdf.org) and FPDI (see https://github.com/Setasign/FPDI) extends the FPDI class.
    
    >>>00001: I have not studied this file at all closely, just done some formal cleanup. 
    Could doubtless use some documentation work. - JM 2019-02-26
*/

include (BASEDIR . "/../fpdf182/fpdf.php");
//include (BASEDIR . "/../fpdi/Fpdi.php");
require_once (BASEDIR. "/../fpdi/src/autoload.php");
use setasign\Fpdi\Fpdi;
require_once dirname(__FILE__).'/../config.php'; // ADDED 2019-02-13 JM

class PDF extends FPDI {	
    private $doFooter = true;
    private $extraPages = 0;
    private $documentType = '';	
    private $doHeader = false;
    private $headerText = '';

    function Rotate($angle, $x=-1, $y=-1) {	
        if ($x==-1) {
            $x=$this->x;
        }
        if ($y==-1) {
            $y=$this->y;
        }

        if ($angle!=0) {
            $angle*=M_PI/180;
            $c=cos($angle);
            $s=sin($angle);
            $cx=$x*$this->k;
            $cy=($this->h-$y)*$this->k;
    
            $this->_out(sprintf('q %.5f %.5f %.5f %.5f %.2f %.2f cm 1 0 0 1 %.2f %.2f cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
        }
    }	
    
    function RotatedText($x,$y,$txt,$angle) {
        // [Martin comment] Text rotated around its origin
        $this->Rotate($angle,$x,$y);
        $this->Text($x,$y,$txt);
        $this->Rotate(90);
    }	
    
    function SetDash($black=null, $white=null) {
        if($black!==null) {
            $s=sprintf('[%.3F %.3F] 0 d',$black*$this->k,$white*$this->k);
        } else {
            $s='[] 0 d';
        }
        $this->_out($s);
    }
    
    public function doMulti($data) {		
        $total_string_width = $this->GetStringWidth($data['text']);
        $number_of_lines = ($total_string_width - 1) / $data['width'];		
        $number_of_lines = ceil( $number_of_lines );  // [Martin comment] Round it up.
        
        $line_height = $data['height'];

        $height_of_cell = $number_of_lines * $line_height;
        $height_of_cell = ceil( $height_of_cell );    // [Martin comment] Round it up.
        
        $this->MultiCell(intval($data['width']), $line_height,$data['text'], 0,'L');
    }
    
    public function doColumn($data) {
        $xPos = $this->x;
        $yPos = $this->y;
        
        $rows = $data['rows'];	
        foreach ($rows as $row) {
            $incs = array();
            $incs[] = 0;
            $incs[] = 0;
                
            foreach ($row as $rrkey => $col) {				
                if ($rrkey) {
                    // not the first one
                    $font = $data['fonts'][1];
                    $width = $data['widths'][1];
                    $text = $col[1];

                    // [BEGIN MARTIN COMMENT]
                    // some weird shit here.  setting font before getting width was
                    // fucked because it seemed to not calculate with the new font specified here.
                    // so doing the calculation based on when the font was bold.
                    // so it seems to get the correct number of lines.
                    // [END MARTIN COMMENT]

                    $this->SetFont($font[0], $font[1], $font[2]);
                    $total_string_width = $this->GetStringWidth($text);
                    
                    $number_of_lines = ($total_string_width - 1) / $width;
                    $number_of_lines = ceil( $number_of_lines );  // [Martin comment] Round it up.					
// [BEGIN MARTIN COMMENT]
//					echo $width;
//					echo '<p>';
//echo $number_of_lines;
//echo '<p>';
//$number_of_lines++;
// [END MARTIN COMMENT]						
                    $line_height = $col[0];                       // [Martin comment] Whatever your line height is.
                    $height_of_cell = $number_of_lines * $line_height;
                    $height_of_cell = ceil( $height_of_cell );    // [Martin comment] Round it up.
                    
                    $this->MultiCell(intval($width), $line_height,$text, 0,'L');					
                    
                    if (  ($number_of_lines *  $col[0]) >= $incs[1]) {
                        $incs[1] = intval(($number_of_lines *  $col[0])) ;
                    }				
                } else {
                    // the first one
                    if ($col[0] >= $incs[0]) {
                        $incs[0] = $col[0];
                    }
                        
                    $font = $data['fonts'][0];
                    $width = $data['widths'][0];

                    $this->SetFont($font[0], $font[1], $font[2]);					
                    $this->cell($width, $col[0], $col[1], 0,'L');						
                }				
            }
    
            if ($incs[0] > $incs[1]) {
                $yPos += $incs[0];
            } else {
                $yPos += $incs[1];
            }
                
            $incs[0] = 0;
            $incs[1] = 0;
            
            $this->setY($yPos);			
        }		
    } // END public function doColumn	
    
    function setHeaderText($value) {
        $this->headerText = $value;
    }

    function setDoFooter($state) {
        $this->doFooter = $state;
    }
    
    function setDoHeader($state) {
        $this->doHeader = $state;
    }
    
    function setExtraPages($extraPages) {
        $this->extraPages = $extraPages;
    }
    
    function setDocumentType($val) {

        $this->documentType = $val;
    }	
    
    // Page footer
    function Footer() {
        global $contract_return_instructions; // ADDED 2019-02-04 JM
        //if ($this->documentType == 'contract') {				
        if ($this->doFooter) {                
            $h = $this->h;
            $w = $this->w;
                
            // [BEGIN commented out by Martin some time before 2019]
            //  $this->Line(20, $h-18, $w-20, $h-18); // 20mm from each edge
            //  $this->Line(50, 45, 210-50, 45); // 50mm from each edge
            // [END commented out by Martin some time before 2019]
            
            // [Martin comment] Position at 1.5 cm from bottom
            $this->SetY($h - 15);
            $this->SetX($w - 45);
                
            //	$this->Rect(10,275,190,1,"F"); //commented out by Martin some time before 2019
            $this->SetFont('Tahoma','',7);
            //	$this->Cell(0,8,"INITIAL : _____________",1,1,'C'); // commented out by Martin some time before 2019
            
            //$this->MultiCell(0,4, "\nINITIAL:_____________", 1,'L');
            $h = $this->h;
            $w = $this->w;
            
            $this->Line(20, $h-18, $w-20, $h-18); // 20mm from each edge
//          $this->Line(50, 45, 210-50, 45); // 50mm from each edge  // commented out by Martin some time before 2019           
            
        // [Martin comment] Position at 1.5 cm from bottom
        $this->SetY(-15);
    //  $this->Rect(10,275,190,1,"F"); // commented out by Martin some time before 2019
        $this->SetFont('Tahoma','',7);
            $this->Cell(0, 3, 'Page '.$this->PageNo() . '/{nb}', 0, 0, 'C');
        }
        
        if (1 == 3) { // JM: which is to say: never			
            $h = $this->h;
            $w = $this->w;
            
            $this->Line(20, $h-18, $w-20, $h-18); // 20mm from each edge
//			$this->Line(50, 45, 210-50, 45); // 50mm from each edge  // commented out by Martin some time before 2019			
            
        // [Martin comment] Position at 1.5 cm from bottom
        $this->SetY(-15);
    //	$this->Rect(10,275,190,1,"F"); // commented out by Martin some time before 2019
        $this->SetFont('Tahoma','',7);
        
        /*
        OLD CODE removed 2019-02-04 JM
        $this->Cell(0,3,'Return a signed copy to: Sound Structural Solutions, Inc',0,1,'C');
        $this->Cell(0,3,'24113 56th Ave W | Mountlake Terrace, Washington 98043',0,1,'C');
        $this->Cell(0,3,'ph 425-778-1023 | inbox@ssseng.com',0,1,'C');
        */
        // BEGIN NEW CODE 2019-02-04 JM
        foreach ($contract_return_instructions AS $contract_return_instruction_line) {
            $this->Cell(0, 3, $contract_return_instruction_line, 0, 1, 'C');
        }
        // END NEW CODE 2019-02-04 JM
        
        // [Martin comment] Page number
        $this->Cell(0, 3, 'Page '.$this->PageNo() . '/{nb}', 0, 0, 'C');		
        }
    }
        // Page footer
    
    function Header() {

        if($this->PageNo() != 1){
            if ($this->doHeader) {                
                    
                $this->SetFont('Arial','',10);
                // Move to the right
                $this->Cell(2);
                
                $this->Cell(0,10,$this->headerText,0,0,'L');
                $this->SetFont('Arial','I',10);
                $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'R');
                // Line break
                $this->Ln(20);
            }
        }
        
    }
    // Get the input file PDF version
    // INPUT $filename: string; 
    public function pdfVersion($filename)
    { 
        $fp = @fopen($filename, 'rb');
        
        if (!$fp) {
            return 0;
        }
        
        /* Reset file pointer to the start */
        fseek($fp, 0);
        /* Read 20 bytes from the start of the PDF */
        preg_match('/\d\.\d/',fread($fp,20),$match);
        
        fclose($fp);
        
        if (isset($match[0])) {
            return $match[0];
        } else {
            return 0;
        }
    } 

    // Extends setSourceFile from FPDI library
    // Introduced 2020-08 by Cristian Pantea to address part of http://bt.dev2.ssseng.com/view.php?id=193; also some tweaks by Joe. 
    // Test the pdf version and, if different from 1.4, convert the file to version 1.4.
    // >> NOTE that more often this is going to take something to an EARLIER version of PDF, rather than a later version. <<
    //    We are confined to what our third-party PDF libraries can handle.
    // Conversion uses the ghostscript tool via exec.
    // INPUT $filename: string, should be a PDF; includes path
    // RETURN the number of pages in the document
    public function setSourceFile2 ($filename) {        
        global $logger;
        // >>00002 >>>00016 Shoudl validate that the file extension is PDF, log & fail if it is not

        /* [CP] I prefer to convert all other versions of PDF to 1.4. As available in Wikipedia 
           https://en.wikipedia.org/wiki/History_of_the_Portable_Document_Format_(PDF)
           the 1.4 version specs are from 2001 with Acrobat 5.0. We are talking about a really old version, even is the maximum we can manage.
           That's why I prefer to convert to 1.4 all pdf's even older versions. Anyway, the probability that any  
           tools SSSEng uses today generate an older PDF version than 1.4 is very remote. 
        */
        $original_file_version = $this->pdfVersion($filename); 
        if ($original_file_version != "1.4") {  
            $d1 = new Datetime();            
            $dateString = $d1->format('YmdHis');
            $tmpFileName = str_replace(".pdf", "_".$dateString.".pdf", $filename);
            $backupFileName = str_replace(".pdf", "_".$dateString."_backup.pdf", $filename);
            exec("gs -dSAFER -dBATCH -dNOPAUSE -dNOCACHE -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile='$tmpFileName' '$filename'", $output, $return_res);
            if(($return_res !== 0) || (!file_exists($tmpFileName))){
                $logger->error2("1596563396", "Unable to change the pdf file version to 1.4 using GhostScript. pdf file name: [".$filename."]");
            } else {
                rename("$filename", "$backupFileName");
                rename("$tmpFileName", "$filename");            
                $logger->info2("1596564814", "file [".$filename."] converted from version $original_file_version to 1.4. " . 
                    "Old file backed up as [".$backupFileName."]");
            }
        }

        // call FPDI::setSourceFile, which will return number of pages
        return parent::setSourceFile($filename);
    }
}

?>