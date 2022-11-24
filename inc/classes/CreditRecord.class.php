<?php
/* inc/classes/CreditRecord.class.php

>>>00001, >>>00017 At least as of 2018-05, various _admin/ajax/cred_*.php functions just ignore this class and change 
   values in the DB directly. We may want to rework those classes, and possibly rework this class to give them
   what they need. (Some of that work has been done, including eliminating some of those files - JM 2020-02-07.)
   
EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the CreditRecord table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular company.
* Public functions:
** __construct($id = null, User $user = null)
** getCreditRecordInvoices()
** getBalance()
** getPaymentsTotal()
** getPayments()
** getCreditRecordId()
** getCreditRecordTypeId()
** getReferenceNumber()
** getAmount()
** getCreditDate()
** getDepositDate()
** getReceivedFrom()
** getFileName()
** getName() - added 2019-11-15 JM; something of a placeholder, but includes/footer.php needs this.
** getNotes()
** public static function creditRecordTypes()
** update($val)
** save()
** toArray()

** public static function validate($creditRecordId, $unique_error_id=null)
*/

class CreditRecord extends SSSEng {
    // The following correspond are a subset the columns of DB table Company,
    //  but they are not exhaustive of the columns in that table.
    // Other columns in that table as of 2019-02-21 are:
    //  - arrivalTime
    //  - personId - SSS (or other customer) employee who inserted the row.
    //  - inserted - TIMESTAMP
    // Presumably the reason for those omissions is that those are created on
    //  insertion using default values, and are never changed.
    // >>>00017 JM 2019-02-21: however, I'd expect "get" methods to read these.
    //
    // See documentation of that table for further details.
    private $creditRecordId;
    private $creditRecordTypeId;
    private $referenceNumber;
    private $amount;   // decimal 10,2
    private $creditDate; 
    private $depositDate;	
    private $receivedFrom;
    private $fileName;
    private $notes;
    
    private $payments; // caches return of public function getPayments	
    
    // INPUT $id: May be either of the following:
    //  * a creditRecordId from the CreditRecord table
    //  * an associative array which should contain an element for each columnn
    //    used in the CreditRecord table, corresponding to the private variables
    //    just above:
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
    // INPUT $user: User object, typically current user. 
    //  >>>00023: JM 2019-02-18: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI). 
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->load($id);
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {
        if (is_numeric($val)) {
            // Read row from DB table CreditRecord 
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".creditRecord ";
            $query .= " where creditRecordId = " . intval($val);

            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.
                        
                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();

                    $this->setCreditRecordId($row['creditRecordId']);
                    $this->setCreditRecordTypeId($row['creditRecordTypeId']);
                    $this->setReferenceNumber($row['referenceNumber']);
                    $this->setAmount($row['amount']);
                    $this->setCreditDate($row['creditDate']);
                    $this->setDepositDate($row['depositDate']);
                    $this->setReceivedFrom($row['receivedFrom']);
                    $this->setFileName($row['fileName']);
                    $this->setNotes($row['notes']);
                } // >>>00002 else ignores that we got a bad creditRecordId!
            } // >>>00002 else ignores failure on DB query! Does this throughout file, 
              // haven't noted each instance.
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from 
            //  input associative array
            $this->setCreditRecordId($val['creditRecordId']);
            $this->setCreditRecordTypeId($val['creditRecordTypeId']);
            $this->setReferenceNumber($val['referenceNumber']);
            $this->setAmount($val['amount']);
            $this->setCreditDate($val['creditDate']);
            $this->setDepositDate($val['depositDate']);
            $this->setReceivedFrom($val['receivedFrom']);
            $this->setFileName($val['fileName']);
            $this->setNotes($val['notes']);
        }
    } // END private function load

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getCreditRecordId();
    }	
    
    // NOTE that all of the following "set" functions are private: we use this only as
    //  part of our own load and save mechanisms, not to be used from outside the class.
    
    // Set primary key
    // INPUT $val: primary key (creditRecordId)
    private function setCreditRecordId($val) {
        $this->creditRecordId = intval($val);
    }

    // Set credit record type
    // INPUT $val: foreign key to CreditRecordType table
    private function setCreditRecordTypeId($val) {
        $this->creditRecordTypeId = intval($val);
    }
    
    // Set reference number
    // INPUT $val: reference number such as a check #, PayPal payment number, etc. 
    private function setReferenceNumber($val) {
        $val = trim($val);
        $val = substr($val, 0, 64); // >>>00002 truncates but does not log
        $this->referenceNumber = $val;
    }
    
    // Set amount of the credit
    // INPUT $val: U.S. currency, stored in DB as DECIMAL(10,2).
    private function setAmount($val) {
        if (!is_numeric($val)) {
            // >>>00002 zeroes but does not log
            $val = 0;
        }
        $this->amount = $val;
    }
    
    // INPUT $val is a DATETIME in 'Y-m-d H:i:s' format. 
    private function setCreditDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->creditDate = $val;
        } else {
            // >>>00002 zeroes date but does not log
            $this->creditDate = '0000-00-00 00:00:00';
        }
    }
    
    // INPUT $val is a DATETIME in 'Y-m-d H:i:s' format.
    private function setDepositDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->depositDate = $val;
        } else {
            // >>>00002 zeroes date but does not log
            $this->depositDate = '0000-00-00 00:00:00';
        }
    }
    
    // INPUT $val: Typically what is written on a check, name of a PayPal 
    //  account, etc. Typically a company name, but it's free-form human-written text. 
    private function setReceivedFrom($val) {
        $val = trim($val);
        $val = substr($val, 0, 255); // >>>00002 truncates but does not log
        $this->receivedFrom = $val;
    }
    
    // INPUT $val: Typical values are 1/41.png, 2/42.png. E.g. a scan of a check.
    //   Files should be in WEBROOT/../CUSTOMER_DOCUMENTS/uploaded_checks, 
    //    e.g. sssnew.com/../ssseng_documents/uploaded_checks.
    //   Can be null.  
    private function setFileName($val) {
        $val = trim($val);
        $val = substr($val, 0, 128); // >>>00002 truncates but does not log
        $this->fileName = $val;
    }
    
    // INPUT $val: string 
    private function setNotes($val) {
        $val = trim($val);
        $val = substr($val, 0, 1024); // >>>00002 truncates but does not log
        $this->notes = $val;
    }	
    
    // [Martin comment] the meta data collected from credit record during data entry
    /*
        RETURNs an array of associative arrays, each representing an invoice associated with this creditRecord.
          Each associative array will include the canonical values to represent the content 
          of a row from the invoice table.
    */    
    public function getCreditRecordInvoices() {  
        $ret = array();
        
        /* BEGIN REPLACED 2020-02-03 JM
        // Based on the current creditRecordId, join via DB table creditRecordInvoice to DB table invoice.        
        $query = " select cri.invoiceId as criinvoiceid, i.* from " . DB__NEW_DATABASE . ".creditRecordInvoice cri ";
        $query .= "  join " . DB__NEW_DATABASE . ".invoice i on cri.invoiceId = i.invoiceId ";
        $query .= " where cri.creditRecordId = " . intval($this->getCreditRecordId()) . " ";
        // $query .= " and i.committed = 1 "; // COMMENTED OUT BY MARTIN BEFORE 2019
        // END REPLACED 2020-02-03 JM
        */

        // BEGIN REPLACEMENT 2020-02-03 JM; eliminated criinvoiceid in return 2020-02-07 
        // Also (2020-02-11) added DISTINCT because we do not want the same invoice twice if a payment is reversed.
        // Based on the current creditRecordId, join via DB table invoicePayment to DB table invoice.
        //  (For version 2020-02, invoicePayment completely supersedes creditRecordInvoice instead of having
        //  duplicated information.)
        $query = "SELECT DISTINCT i.* FROM " . DB__NEW_DATABASE . ".invoicePayment cri ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".invoice i ON cri.invoiceId = i.invoiceId ";
        $query .= "WHERE cri.creditRecordId = " . intval($this->getCreditRecordId()) . ";";
        // END REPLACEMENT 2020-02-03 JM
        
        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $ret[] = $row;
                }
            }
        }
            
        return $ret;		
    } // END public function getCreditRecordInvoices	
    
    /*  public function getBalance uses function getPayments to identify all 
        relevant invoices and creditMemos, and effectively subtracts 
        the amount of all invoices, and adds the amount of all creditMemos, 
        then returns the result. 
        
        (As of 2018-10, creditMemo.amount is always negative, so adding that 
        reduces the total; Martin says we might be introducing other credit memo 
        types where that isn't the case.) 
    */
    public function getBalance() {
        /* BEGIN REPLACED 2020-01-31 JM
        $pay = 0;
        $payments = $this->getPayments();
        
        foreach ($payments as $pkey => $type) {
            // As of 2019-02, this next test will effective skip over the $pkey == 'total' case, 
            // which is good, but maybe we should instead explicitly call out the
            // $type values that we care about.
            if (is_array($type)) {
                foreach ($type as $tkey => $payment) { // $tkey never used, could be just foreach ($type as $payment)
                    if (is_numeric($payment['amount'])) {						
                        if ($pkey == 'creditmemos') {
                            $pay += ($payment['amount'] * -1);
                        } else {
                            $pay += $payment['amount'];								
                        }						
                    }						
                }				
            }			
        }	
        
        return ($this->getAmount() - $pay);		
		// END REPLACED 2020-01-31 JM 
		*/
		// BEGIN REPLACEMENT 2020-01-31 JM: rewritten for clarity
        $pay = $this->getPaymentsTotal();
        return ($this->getAmount() - $pay);		
        // END REPLACEMENT 2020-01-31 JM    
    } // END public function getBalance
    
    /* public function getPaymentsTotal uses function getPayments to identify 
       all relevant invoices and creditMemos, than adds the amounts of the invoices, 
       subtracts the amount of the creditMemos, and returns the total. 
       
       (As of 2018-10, creditMemo.amount is always negative, so adding that 
       reduces the total; Martin says we might be introducing other credit memo
       types where that isn't the case.)
   */
    public function getPaymentsTotal() {	
        /* BEGIN REPLACED 2020-01-31 JM
        $total = 0;	
        $payments = $this->getPayments();
        foreach ($payments as $pkey => $type) {		
            // As of 2019-02, this next test will effective skip over the $pkey == 'total' case, 
            // which is good, but maybe we should instead explicitly call out the
            // $type values that we care about.
            if (is_array($type)) {
                foreach ($type as $tkey => $payment) { // $tkey never used, could be just foreach ($type as $payment)
                    if (is_numeric($payment['amount'])) {
                        if ($pkey == 'creditmemos') {
                            $total += ($payment['amount'] * -1);
                        } else {
                            $total += $payment['amount'];								
                        }
                    }				
                }
            }		
        }
		// END REPLACED 2020-01-31 JM 
		*/
		// BEGIN REPLACEMENT 2020-01-31 JM: rewritten for clarity
        $total = 0;	
        $allPayments = $this->getPayments();
        foreach ($allPayments as $paymentType => $payments) {		
            // As of 2019-02, this next test will effective skip over the $paymentType == 'total' case, 
            // which is good, but >>>00017 maybe we should instead explicitly call out the
            // $type values that we care about.
            if (is_array($payments)) {
                foreach ($payments as $payment) {
                    if (is_numeric($payment['amount'])) {
                        if ($paymentType == 'creditmemos') {
                            $total += ($payment['amount'] * -1);
                        } else {
                            $total += $payment['amount'];								
                        }
                    }				
                }
            }		
        }
        return $total;	
		// END REPLACEMENT 2020-01-31 JM    
    }
    
/*  public function getPayments: NOTE that this function caches its result. 
     If called a second time on the same object, it does not recalculate. 
    
    Does two similar selects, one from DB table invoicePayment and one from 
     DB table creditMemo. In both cases, it selects rows that have the current 
     creditRecord ID, and orders by the primary key of the table in question.
     It then returns an associative array with the folowing elements:
    * 'invoices': array of associative arrays, each representing a row from DB table invoicePayment in the canonical manner.
    * 'creditmemos': array of associative arrays, each representing a row from DB table creditMemo in the canonical manner.
    * 'total': the sum of the invoice amounts, minus the creditMemo amounts. 
*/
    public function getPayments() {	
        if (isset($this->payments)) {
            return $this->payments;
        }
        
        $total = 0;
        
        $payments = array();
        $payments['invoices'] = array();
        $payments['creditmemos'] = array();		
    
        $query  = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".invoicePayment ";
        $query .= " where creditRecordId = " . intval($this->getCreditRecordId()) . " ";
        $query .= " order by invoicePaymentId ";
    
        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {					
                    if (is_numeric($row['amount'])) {
                        $total += $row['amount'];
                    }
                    
                    $payments['invoices'][] = $row;
                }
            }
        }
        
        $query  = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".creditMemo ";
        $query .= " where id = " . intval($this->getCreditRecordId()) . " ";
        $query .= " order by creditMemoId ";
        
        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {

                    if (is_numeric($row['amount'])) {
                        $total += ($row['amount'] * -1);
                    }
                        
                    $payments['creditmemos'][] = $row;
                }
            }
        }

        $payments['total'] = $total;		
        $this->payments = $payments;  // cache it
        
        return $payments;	
    }

    // RETURN primary key
    public function getCreditRecordId() {
        return $this->creditRecordId;
    }

    // RETURN credit record type, foreign key to CreditRecordType table 
    public function getCreditRecordTypeId() {
        return $this->creditRecordTypeId;
    }
    
    // RETURN reference number such as a check #, PayPal payment number, etc.
    public function getReferenceNumber() {
        return $this->referenceNumber;
    }
    
    // RETURN amount of the credit. U.S. currency, stored as DECIMAL(10,2), 
    //  so this will return as a float.
    public function getAmount() {
        return $this->amount;
    }
    
    // RETURN credit date in 'Y-m-d H:i:s' format.
    public function getCreditDate() {
        return $this->creditDate;
    }

    // RETURN deposit date in 'Y-m-d H:i:s' format.
    public function getDepositDate() {
        return $this->depositDate;
    }
    
    // RETURN is free-form human-written text, typically what is written on a check,  
    //  name of a PayPal account, etc. 
    public function getReceivedFrom() {
        return $this->receivedFrom;
    }
    
    // RETURN: Typical values are 1/41.png, 2/42.png. E.g. a scan of a check.
    //   Files should be in WEBROOT/../CUSTOMER_DOCUMENTS/uploaded_checks, 
    //    e.g. sssnew.com/../ssseng_documents/uploaded_checks.
    //   Can be null.
    public function getFileName() {
        return $this->fileName;
    }
    
    // added 2019-11-15 JM; something of a placeholder, but includes/footer.php needs this.
    // RETURN something we can use as a name for this.
    public function getName() {
        return 'CR' . $this->getCreditRecordId();
    }    
    
    // RETURN notes as string.
    public function getNotes() {
        return $this->notes;
    }	
    
    // RETURNs an associative array that maps from CRED_REC_TYPES defined in 
    //  inc/config.php to their display names.
    public static function creditRecordTypes() {	
        $ret = array();
        $ret[CRED_REC_TYPE_CHECK] = array('name' => 'Check');
        $ret[CRED_REC_TYPE_PAYPAL] = array('name' => 'Paypal');
        $ret[CRED_REC_TYPE_CASH] = array('name' => 'Cash');
        $ret[CRED_REC_TYPE_CC] = array('name' => 'Credit Card');
        $ret[CRED_REC_TYPE_WIRE] = array('name' => 'Wire Transfer');

        return $ret;	    
    }
    
    // Update several values for this credit record
    // INPUT $val typically comes from $_REQUEST.
    //  An associative array containing the following elements (unlike payments, invoices, etc,  
    //   these are all directly in DB table CreditRecord):
    //   * 'creditRecordTypeId' - this one gets well validated 2019-02
    //   * 'referenceNumber'
    //   * 'amount'
    //   * 'creditDate'
    //    * This is NOT expected not in 'Y-m-d H:i:s' form; oddly, we don't even *allow*
    //      that form, although it is what we will build here. Instead, we want it in 'm/d/Y' form, and if it
    //      isn't input in that form, we will fall back to '0000-00-00 00:00:00'.
    //    * >>>00016: On the other hand, we don't properly validate that the month, day, and year are numbers,
    //      let alone in a sane range. We just take intval, so '42/2b3/99' would turn into an insane
    //      '0099-42-02' and make it into an UPDATE.
    //   * 'receivedFrom'
    //   * 'notes'
    //   Any or all of these may be present. >>>00016: Maybe more validation? What validation there is seems 
    //     to be pretty arbitrarily split between this function and the "set" functions.
    public function update($val) {
        if (is_array($val)) {
            if (isset($val['creditRecordTypeId'])) {
                if (trim($val['creditRecordTypeId']) != '') {
                    // >>>00007 isset test in following line is redundant to test already made, as are analogous ones 
                    //  for other elements of the associative array.
                    $creditRecordTypeId = isset($val['creditRecordTypeId']) ? intval($val['creditRecordTypeId']) : null;

                    $types = self::creditRecordTypes();

                    if (array_key_exists($creditRecordTypeId, $types)) {
                        $this->setCreditRecordTypeId($creditRecordTypeId);
                    }
                }
            }

            if (isset($val['referenceNumber'])) {
                $referenceNumber = isset($val['referenceNumber']) ? $val['referenceNumber'] : '';
                $this->setReferenceNumber($referenceNumber);
            }

            if (isset($val['amount'])) {
                $amount = isset($val['amount']) ? $val['amount'] : '';
                $this->setAmount($amount);
            }

            if (isset($val['creditDate'])) {
                // >>>00006: code like this appears in a lot of different classes,
                // should certainly have potential for common code elimination.
                $creditDate = '0000-00-00 00:00:00';

                $parts = explode("/", $val['creditDate']);
                if (count($parts) == 3) {
                    $creditMonth = intval($parts[0]);
                    $creditDay = intval($parts[1]);
                    $creditYear = intval($parts[2]);

                    $creditMonth = str_pad($creditMonth,2,'0',STR_PAD_LEFT);
                    $creditDay = str_pad($creditDay,2,'0',STR_PAD_LEFT);
                    $creditYear = str_pad($creditYear,4,'0',STR_PAD_LEFT);

                    $creditDate = $creditYear . '-' . $creditMonth . '-' . $creditDay . ' 00:00:00';
                }

                $this->setCreditDate($creditDate);
            }

            if (isset($val['receivedFrom'])) {
                $receivedFrom = isset($val['receivedFrom']) ? $val['receivedFrom'] : '';
                $this->setReceivedFrom($receivedFrom);
            }

            if (isset($val['notes'])) {
                $notes = isset($val['notes']) ? $val['notes'] : '';
                $this->setNotes($notes);
            }
            
            $this->save();
        } // >>>00002: invalid input should certainly log.
    } // END public function update


    public function save() {
        $query = " update " . DB__NEW_DATABASE . ".creditRecord  set ";
        $query .= " creditRecordTypeId = " . intval($this->getCreditRecordTypeId()) . " ";
        $query .= " ,referenceNumber = '" . $this->db->real_escape_string($this->getReferenceNumber()) . "' ";
        $query .= " ,amount = " . $this->db->real_escape_string($this->getAmount()) . " ";
        $query .= " ,creditDate = '" . $this->db->real_escape_string($this->getCreditDate()) . "' ";
        $query .= " ,receivedFrom = '" . $this->db->real_escape_string($this->getReceivedFrom()) . "' ";
        $query .= " ,fileName = '" . $this->db->real_escape_string($this->getFileName()) . "' ";
        $query .= " ,notes = '" . $this->db->real_escape_string($this->getNotes()) . "' ";
        $query .= " where creditRecordId = " . intval($this->getCreditRecordId()) . " ";

        $this->db->query($query);		
    } // END public function save

    // RETURNs an associative array containing most (but oddly not all) of the 
    //  class-private variables that represent content of the data row. 
    //  As of 2019-02, omits depositDate, >>>00017 which JM strongly suspects should 
    //  be part of the return. Also, it's not clear why arrivalTime, 
    //   personId, and inserted are neither here nor in the private variables
    //   that represent content of the data row.
    public function toArray() {
        return array (
            'creditRecordId' => $this->getCreditRecordId(),
            'creditRecordTypeId' => $this->getCreditRecordTypeId(),
            'referenceNumber' => $this->getReferenceNumber(),
            'amount' => $this->getAmount(),
            'creditDate' => $this->getCreditDate(),
            'receivedFrom' => $this->getReceivedFrom(),
            'fileName' => $this->getFileName(),
            'notes' => $this->getNotes()
        );
    } // END public function toArray

    /*
	// This method was moved into the base class.
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}*/
	
    // Return true if the id is a valid creditRecordId, false if not
    // INPUT $creditRecordId: creditRecordId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($creditRecordId, $unique_error_id=null) {
        global $db, $logger;
        CreditRecord::loadDB($db);
        
        $ret = false;
        $query = "SELECT creditRecordId FROM " . DB__NEW_DATABASE . ".creditRecord WHERE creditRecordId=$creditRecordId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578686603', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}
?>