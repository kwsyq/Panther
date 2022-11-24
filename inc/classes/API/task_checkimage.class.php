<?php 
/* task_checkimage.class.php

    EXECUTIVE SUMMARY: an API class, part of downloading a check image. 
    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_checkimage extends API {
	
    // Inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
	// Typically constructed for current user, but no default to make it so. 
	// Constructor can optionally take a personId & a customer object to set user.
	// INPUT $personId: unsigned integer, primary key into DB table Person.
	// INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		$this->customer = $customer;		
		parent::__construct($personId, $customer);	
	}	
	
	// INPUT $_REQUEST['f'] - filename
	//
    // ACTION: Looks in uploaded_checks folder for a matching filename.  Assuming 
    //   the file exists, builds appropriate headers to download that file.
    // SIDE EFFECT: actually writes those headers.
	// 
	// EFFECTIVE RETURN is via setStatus and setData.
	// Sets status to 'success' if there is such a file, 'fail' if there is not.
	// On success, sets data as set of name-value pairs as follows:
    // * 'type': always 'download'
    // * 'headers': string, headers to download this file
    // * 'downloadfile: pathname to file, relative to domain root. 		
	public function run() {
		$this->setStatus('fail');		

		/* OLD CODE REMOVED 2019-02-15 JM
		$sep = DIRECTORY_SEPARATOR;
		$fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '..' . $sep . 'ssseng_documents' . $sep . 'uploaded_checks' . $sep;
		*/
		// BEGIN NEW CODE 2019-02-15 JM
		$fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/';
		// END NEW CODE 2019-02-15 JM
		
		if (file_exists($fileDir)) {		
			$filename = isset($_REQUEST['f']) ? $_REQUEST['f'] : null;		
			if ($filename) {		
				if (file_exists($fileDir . $filename)) {
		
				    // BEGIN Martin comment
					// we should test the reliabilty of this (mime detection).
					// otherwise manually map extensions ot mime types
					// and allow certain ones etc.  or maybe just generic binary type
					// END Martin comment
					$finfo = new finfo(FILEINFO_MIME_TYPE);
					$mime = $finfo->file($fileDir . $filename);
		
					header('Content-Description: File Transfer');
					header('Content-Type: ' . $mime);
					header('Content-Disposition: attachment; filename="' . $filename . '"');
					header('Content-Transfer-Encoding: binary');
					header('Expires: 0');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					header('Content-Length: ' . filesize($fileDir . $filename));

					$headers = array('Content-Description: File Transfer',
							'Content-Type: ' . $mime,
							'Content-Disposition: attachment; filename="' . $filename . '"',
							'Content-Transfer-Encoding: binary',
							'Expires: 0',
							'Cache-Control: must-revalidate, post-check=0, pre-check=0',
							'Pragma: public',
							'Content-Length: ' . filesize($fileDir . $filename)
							);
					
					$this->setStatus('success');
					$this->setData(array('key' => 'type', 'value' => 'download'));					
					$this->setData(array('key' => 'headers', 'value' => $headers));
					$this->setData(array('key' => 'downloadfile', 'value' => $fileDir . $filename));		
				}		
			}		
		}		
	}	
}

?>