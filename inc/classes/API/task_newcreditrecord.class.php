<?php 
/* inc/classes/API/task_newcreditrecord.class.php

    EXECUTIVE SUMMARY: an API class. Inserts a new credit record and saves the file that documents it with a 
     copy of a check or similar. >>>00001 May not be fully functional 2019-03.

    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_newcreditrecord extends API {

	private $saveDir;
	
	/* Make an entry in the creditRecord DB table
	INPUT $_REQUEST['amount'] - US currency, will be stored with 2 digits past the decimal point
	INPUT $_REQUEST['number'] - check number, PayPal account, whatever. Anything other than digits will be ignored.
	
	>>>00026: As of 2019-03, this entry in the DB table will always get a personId of 1. I would think it would get
	the personId that was passed to the constructor. JM 2019-03-05.
	
	>>>00026: As of 2019-03, 'amount' and 'number' seem to be ignored. Just inserting a dummy row.
	
	If insertion is successful, returns the new creditRecordId. If not, returns 0.	
	*/
	private function makeEntry() {		
		$db = DB::getInstance();
		
		// Martin comment: later on the front end should decide if this is a check or some other kind of payment receipt

		$amount = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
		$number = isset($_REQUEST['number']) ? $_REQUEST['number'] : '';

		if (is_numeric($amount)){
			$amount = $amount + 0;
		} else {
			$amount = 0;
		}
		
		$number = preg_replace("/[^0-9]/", "", $number); // >>>00002 silently remove anything other than digits
		
		// >>>00001: Joe believes something like Martin's commented-out query here needs to come back to life.
		
		// BEGIN commented out by Martin some time before 2019
		//$query = " insert into " . DB__NEW_DATABASE . ".creditRecord(paymentArrived,payMethodId,reference,amount,personId) values(";
		//$query .= " now() ";
		//$query .= " ," . intval(PAYMETHOD_CHECK) . " ";
		//$query .= " ,'" . $db->real_escape_string($number) . "' ";  // check number
		//$query .= " ," . $db->real_escape_string($amount) . " ";   // check amount
		//$query .= " ,1 )"; // preson id
		// END commented out by Martin some time before 2019	
	
		$query = " insert into " . DB__NEW_DATABASE . ".creditRecord(personId) values(";
		$query .= "  " . intval(1) . " )"; // person id=1 - >>>00001: but why a personId of 1? JM 2019-03-05
		$db->query($query);
		
		$id = $db->insert_id;
		
		if (intval($id)){
			return $id;
		}
				
		return 0;
		
	} // END private function makeEntry
	
    // Inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
    // Typically constructed for current user, but no default to make it so. 
    // Constructor can optionally take a personId & a customer object to set user.
    // INPUT $personId: unsigned integer, primary key into DB table Person.
    // INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		parent::__construct($personId, $customer);
	}
	
	/* Uses makeEntry to return a presumably appropriate row in DB table CreditRecord and return its ID.
	
	   >>>00017 If makeEntry fails, use api_error to report "problem with making credit entry" and return silently, not even a status. 

	   The following inputs are used by private function makeEntry; implicitly passed by 
	    leaving the superglobals in place and calling the function.
	    >>>00001 As of 2019-03, makeEntry doesn't really seem to be using these.
       INPUT $_REQUEST['amount'] - US currency, will be stored with 2 digits past the decimal point
       INPUT $_REQUEST['number'] - check number, PayPal account, whatever. Anything other than digits will be ignored.
	   
	   Assuming makeEntry succeeds:
	     * use the last digit "$tail" of the returned ID to determine a directory, 
	       build the directory if needed (use api_error to report if it doesn't 
	       exist or isn't writable; again in these cases we bail out with no return).
       Assuming we are OK so far: 
         * verify that the file uploaded correctly, and is < 2MB. If either of these fails, 
           as of 2018-06 we fail silently, no return, no call to api_error.
       Assuming we are OK so far: 
         * We set a target file name "id.png", based on the id returned from makeEntry. 
         * If somehow that file already exists in the relevant directory we return a 403 
           (shouldn't ever happen). 
         * Otherwise, we upload the passed-in file to "../ssseng_documents/uploaded_checks/$tail/id.png", 
           update the row in DB table creditRecord with "$tail/id.png", rotate the image 90 degrees (>>> JM: why?), 
           set status="success" and set data creditRecordData=id (the id returned by makeEntry).

       So: we do that early insertion in DB table CreditRecord so we can calculate $tail, 
       but we don't put in the meaningful data until the end of the process.
       >>>00026: I (JM) would expect to update personId in the CreditRecord
       
       EFFECTIVE RETURN is via setStatus and setData, but seems not to be
       set at all unless we succeed.
       
       On success, status='success'.
       On success sets data as a key-value pair (that is, an associative array with one element):
         * 'creditRecordId': creditRecordId for the newly inserted row.       
	*/
	public function run() {
		$id = $this->makeEntry();		
		if (!intval($id)) {				
			$this->api_error("problem with making credit entry");
			// >>>00002 reported the error but what about $this->setStatus?
		} else {
			$tail = substr($id, -1);
			
			/* OLD CODE REMOVED 2019-02-15 JM
			$sep = DIRECTORY_SEPARATOR;
			$this->saveDir = '..' . $sep . 'ssseng_documents' . $sep . 'uploaded_checks' . $sep . $tail . $sep;
			*/
			// BEGIN NEW CODE 2019-02-15 JM
			// >>>00026 JM: I'd expect this to start with BASEDIR, as of 2019-03 it doesn't. 
			//  So this had better be running in that BASEDIR directory!)
			$this->saveDir = '../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/' . $tail . '/';
			// END NEW CODE 2019-02-15 JM
				
			$str = "";
				
			// BEGIN commented out by Martin some time before 2019
			//foreach ($_REQUEST as $key => $value){					
			//	$str .= $key . " = " . $value . "\n";					
			//}				
			//file_put_contents("/tmp/crapola.txt", $str);
			// END commented out by Martin some time before 2019
				
			$sizeLimit = 2097152;
				
			/* OLD CODE REMOVED 2019-02-15 JM
			$sep = DIRECTORY_SEPARATOR;
			*/
			
			// Make the directory, if not already there
			if (!file_exists($this->saveDir)){
				@mkdir($this->saveDir);
			}
			
			// Verify that the directory exists and is writeable
			if (!file_exists($this->saveDir)){
				$this->api_error->setError("Save Dir Doesnt Exist");
			}				
			if (!is_writable($this->saveDir)){
				$this->api_error->setError("Save Dir Not writable");
				// >>>00002 reported the error but what about $this->setStatus?
			} else {
			    // Verify that the file uploaded correctly, and is < 2MB.
			    // >>>00006: surely this could be written more clearly - JM
				if ( ! (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error']))) {		
					if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {							
						if ($_FILES['file']['size'] <= $sizeLimit) {
						    // BEGIN Martin comment
							//and maybe dispense with this
							// would imagine there will be files much larger than this
							// so need to adjust configs
							// END Martin comment
							$fileName = $id . ".png"; // Martin comment: $_FILES['file']['name'];
							$saveName = sprintf('%s%s', $this->saveDir, $fileName);		
							$parts = explode(".", $fileName);
		
							if (count($parts)) {									
								$ext = strtolower(end($parts));
									
								//if (!in_array($ext, $disallowedExtensions)){ // Commented out by Martin some time before 2019
									
								if (file_exists($saveName)) {		
									header('HTTP/1.0 403 Forbidden');
									echo "That File Already Exists"; //>>>00002 also logging as such would be good here.
									die();		
								} else {
								    // put the file where we want it
								    // >>>00019 Next line relies on a side effect of a returned value inside "if" statement, 
								    //  may want to rewrite and make that return more explicit.
									if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {									    
										$success = true;										
										$db = DB::getInstance();										
										$query = " update " . DB__NEW_DATABASE . ".creditRecord ";
										/* OLD CODE REMOVED 2019-02-15 JM
										$query .= " set fileName = '" . $db->real_escape_string($tail . $sep . $fileName) . "' ";
										*/
										// BEGIN NEW CODE 2019-02-15 JM
										$query .= " set fileName = '" . $db->real_escape_string($tail . '/' . $fileName) . "' ";
										// END NEW CODE 2019-02-15 JM
										$query .= " where creditRecordId = " . intval($id) . " ";
										// >>>00017 JM: I'd expect also to set personId here (or somewhere!), probably 
										//  some other values
										
										$db->query($query); // >>>00002: not checking for DB failure here
										
										// Martin comment: Load the image 
										$source = imagecreatefrompng($saveName);
								
										// Martin comment: Rotate
										// >>>00001 JM: Why?
										$rotate = imagerotate($source, 90 , 0);
										
										// Martin comment: and save it on your server...
										imagepng($rotate,$saveName);										
										
										if (file_exists($saveName)) {										    
										    $this->setStatus('success');
										    $this->setData(array('key' => 'creditRecordId', 'value' => $id));										    
										}
										
										// BEGIN commented out by Martin some time before 2019
										//			$caption = isset($_REQUEST['caption']) ? $_REQUEST['caption'] : '';											
										//			file_put_contents($saveName . '.txt', $caption);
										// END commented out by Martin some time before 2019											
									}		
								}									
								//} // Commented out by Martin some time before 2019									
							}		
						}							
					}		
				}
			}	
		}
	} // END public function run
}

?>