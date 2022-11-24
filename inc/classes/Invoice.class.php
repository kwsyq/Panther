<?php
/* inc/classes/Invoice.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the Company table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular invoice.
* Public methods
** __construct($id = null, User $user = null)
** public static function getInvoiceStatusDataArray()
** pay($type, $creditRecordId, $part = 0)
** setNameOverride($val)
** setInvoiceDate($val)
** setCommitNotes($val)
** setCommitPersonId($val)
** setInvoiceNotes($val) // added for v2020-4
** setClientMultiplier($val)
** setPersonNameOverride($val)
** getPayments()
** getInvoiceId()
** getWorkOrderId()
** getContractId()
** getNameOverride()
** getInvoiceDate()
** getTermsId()
** setEditCount($val)
** setAddressOverride($val)
** getCommitted()
** getCommittedNew()
** getCommittedTime()
** getInserted()
** getInvoiceStatusId()
** getData()
** getCommitNotes()
** getCommitPersonId()
** getInvoiceNotes() // added for v2020-4
** getClientMultiplier()
** getPersonNameOverride()
** getPersonLocationOverride($val)
** getTotal()
** getTotalOverride() // totalOverride is not used going forward now that we have invoiceAdjustment, but is there for thousands of old invoices.
** getTriggerTotal()
** getEditCount()
** getAddressOverride()
** deleteAdjustment($invoiceAdjustId)
** getAdjustments()
** addAdjustment($invoiceAdjustTypeId, $amount,$invoiceAdjustNote = "")
** getBillingProfiles()
** storeTotal()
** update($val)
** save($incrementEditCount)
** getStatusName()
** getStatusInserted()
** getStatusCustomerPersonIds()
** setStatus($invoiceStatusId, $customerPersonIds, $note)

** public static function getNonSentInvoiceStatuses()
** public static function getNonSentInvoiceStatusesAsString()
** public static function getInvoiceStatusIdFromUniqueName($uniqueName) {
** public static function validate($invoiceId, $unique_error_id=null)
** public static function validateInvoiceStatus($invoiceStatusId, $unique_error_id=null)
*/

class Invoice extends SSSEng {
    // The following correspond exactly to the columns of DB table Company
    // See documentation of that table for further details.

    // >>>00001 Several columns from the table do not have variables here. Those look to me
    //  (JM 2019-02-25) like a mix of vestigial stuff that should be out of the
    //  table and stuff that might be "half implemented". Definitely some cleanup
    //  to be done here.

    private $invoiceId;
    private $workOrderId;
    private $contractId;
    private $nameOverride;
    private $invoiceDate;
    private $termsId;
    private $committed;
    private $committedTime;
    private $data;
    private $commitNotes;
    private $commitPersonId;
    private $personNameOverride;
    private $personLocationOverride;
    private $clientMultiplier;
    private $total;
    private $totalOverride; // >>>00007 JM: Martin said dead as of 2018-10. Presumably should remove this.
    private $inserted;
    // Missing: $invoiceTermsId: Comment from http://sssengwiki.com/Documentation+V3.0#invoice:
        /* Presumably foreign key into table terms or a not-yet-built table invoiceTerms, but as of 2017-12-06
           always the default value 0, which is a placeholder. MARTIN thinks it might be something static, not in a table.
           JM can't find anywhere in the code this is set 2018-10, so probably not used. */

    // >>>00014 ----- MISSING BUT REFERENCED IN THE CODE, presumably should be declared:
    // $addressOverride: Comment from http://sssengwiki.com/Documentation+V3.0#invoice:
        /* Nullable, null for older rows. I believe this is only meaningful if editCount>0. Overrides information from
           shadow billing profile and should be a concatenation of any of the following that are defined, with '::' as a
           separator between them: formatted name of companyPerson, person email address, company email address, person
           location (commas instead of newlines), company location (commas instead of newlines).
           This one is definitely active as of 2018-10, but still might be going away. */

    private $editCount;
    // Missing $fromFake: Comment from http://sssengwiki.com/Documentation+V3.0#invoice:
        /* Not nullable, default 0. >>>Q JM: is this for a representation of something that was actually invoiced a different way?
           Or is it something else? (Martin and Damon don't know for sure offhand 2018-10. Maybe something for reconciliation,
           especially for old data.)*/
    // Missing $note: Comment from http://sssengwiki.com/Documentation+V3.0#invoice:
        /* Nullable, default null. I believe this is intended for programmatic use, not note by a human. */
    private $invoiceStatusId;
    // REMOVED 2020-08-10 JM for v2020-4 // private $extra; // vestigial in v2020-3
    private $invoiceStatusTimeId; // added in v2020-3
    private $triggerTotal;
    // MISSING: $textOverride
        /* Nullable. Idea is to be able to write pretty much any content into the invoice. A "band-aid".
           Overrides the whole portion about what's being billed. */

    private $invoiceNotes; // added for v2020-4
    private $updateCommittedTime; // This one is not directly in the DB table: it's a Boolean so that we know whether to update committedTime on save.
                                  // >>>00018 Why isn't this just a local in the update method?

    // JM 2019-02-25: Martin had already commented out code here and elsewhere in the file
    //  related to contractLanguage, which was persumably vestigial in this context.
    //  I've now removed that code entirely. Feel free to remove this comment.

    // INPUT $id: Should be a invoiceId from the Invoice table.
    // INPUT $user: User object, typically current user; defaults to current user (which can by NULL if running fron CLI).
    // >>>00014: 'addressOverride' is referenced here, but no private variable for it above.
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->updateCommittedTime = false;
        $this->load($id);
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {
        if (is_numeric($val)) {
            // Read row from DB table Invoice
            $query = "SELECT i.* ";
            $query .= "FROM " . DB__NEW_DATABASE . ".invoice i ";
            $query .= "WHERE i.invoiceId = " . intval($val) . ";";

            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.

                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();
                    $this->setInvoiceId($row['invoiceId']);
                    $this->setworkOrderId($row['workOrderId']);
                    $this->setContractId($row['contractId']);
                    $this->setNameOverride($row['nameOverride']);
                    $this->setInvoiceDate($row['invoiceDate']);
                    $this->setTermsId($row['termsId']);
                    $this->setCommitted($row['committed']);
                    $this->setCommittedTime($row['committedTime']);
					/* BEGIN REPLACED 2020-09-09 JM
					$this->setData($row['data']);
					// END REPLACED 2020-09-09 JM
					*/
					// BEGIN REPLACEMENT 2020-09-09 JM; further adapted 2020-09-28 JM
					if (is_null($row['data2'])) {
                        $this->setData($row['data']); // which has the side effect of changing the data to the new version introduced in v2020-4.
                        $query = "UPDATE " . DB__NEW_DATABASE . ".invoice SET ";
                        $query .= "data2 = '" . $this->db->real_escape_string(json_encode($this->getData())) . "' ";
                        $query .= "WHERE invoiceId = " . intval($this->invoiceId) . ";";

                        $result = $this->db->query($query);
                        if (!$result) {
                            $this->logger->errorDb('1599605038', "Hard DB error establishing data2 for invoiceId " . $this->invoiceId, $this->db);
                            // >>>00026 I don't think this should ever arise, but if it does we probably mess something up with any further
                            //  save of this Invoice object. Need to think about what best to do here. JM 2020-09-09.
                        }
					} else {
					    $this->setData($row['data2']);
					}
					// END REPLACEMENT 2020-09-09 JM
                    $this->setCommitNotes($row['commitNotes']);
                    $this->setCommitPersonId($row['commitPersonId']);
                    $this->setPersonNameOverride($row['personNameOverride']);
                    $this->setPersonLocationOverride($row['personLocationOverride']);
                    // $this->setSavePersonId($row['savePersonId']); // REMOVED 2020-03-13 JM
                    // $this->setSavePersonLocationId($row['savePersonLocationId']); // REMOVED 2020-03-13 JM
                    // $this->setSaveLocationId($row['saveLocationId']); // REMOVED 2020-03-13 JM
                    $this->setClientMultiplier($row['clientMultiplier']);
                    $this->setTotal($row['total']);
                    $this->setTotalOverride($row['totalOverride']); // >>>00007 JM: Martin said dead as of 2018-10. Presumably should remove this.
                    $this->setInserted($row['inserted']);
                    $this->setAddressOverride($row['addressOverride']); // >>>00014 $addressOverride NOT DECLARED ABOVE
                    $this->setEditCount($row['editCount']);
                    $this->setInvoiceStatusId($row['invoiceStatusId']);
                    $this->setInvoiceStatusTimeId($row['invoiceStatusTimeId']); // added for v2020-3
                    // REMOVED 2020-08-10 JM for v2020-4 // $this->setExtra($row['extra']); // vestigial in v2020-3
                    $this->setTriggerTotal($row['triggerTotal']);
                    $this->setInvoiceNotes($row['invoiceNotes']); // added for v2020-4
                } // >>>00002 else ignores that we got a bad invoiceId!
            } // >>>00002 else ignores failure on DB query! Does this throughout file,
              // haven't noted each instance.
        } else if (is_array($val)) {
            /* BEGIN REMOVED AS A BAD IDEA 2020-09-09 JM
            // Set all of the private members that represent the DB content, from
            //  input associative array
            $this->setInvoiceId($val['invoiceId']);
            $this->setworkOrderId($val['workOrderId']);
            $this->setContractId($val['contractId']);
            $this->setNameOverride($val['nameOverride']);
            $this->setNumber($val['number']);
            $this->setInvoiceDate($val['invoiceDate']);
            $this->setTermsId($val['termsId']);
            $this->setCommitted($val['committed']);
            $this->setCommittedTime($val['committedTime']);
            $this->setInserted($val['inserted']);
            $this->setAddressOverride($val['addressOverride']); // >>>00014 $addressOverride NOT DECLARED ABOVE
            $this->setEditCount($val['editCount']);
            $this->setInvoiceStatusId($val['invoiceStatusId']);
            $this->setInvoiceStatusTimeId($val['invoiceStatusTimeId']);  // added for v2020-3
            // REMOVED 2020-08-10 JM for v2020-4 // $this->setExtra($val['extra']); // vestigial in v2020-3
            $this->setData($val['data']);
            $this->setCommitNotes($val['commitNotes']);
            $this->setCommitPersonId($val['commitPersonId']);
            $this->setClientMultiplier($val['clientMultiplier']);
            $this->setPersonNameOverride($val['personNameOverride']);
            $this->setPersonLocationOverride($val['personLocationOverride']);
            // $this->setSavePersonId($val['savePersonId']); // REMOVED 2020-03-13 JM
            // $this->setSavePersonLocationId($val['savePersonLocationId']); // REMOVED 2020-03-13 JM
            // $this->setSaveLocationId($val['saveLocationId']); // REMOVED 2020-03-13 JM
            $this->setTotal($val['total']);
            $this->setTotalOverride($val['totalOverride']); // >>>00007 JM: Martin said dead as of 2018-10. Presumably should remove this.
            $this->setTriggerTotal($val['triggerTotal']);
            // END REMOVED AS A BAD IDEA 2020-09-09 JM
            */
            // and just in case this was somehow used (doesn't appear to have been - 2020-09-09 JM)
            $this->logger->error2('1599605464', "Invoice constructor called with array instead of invoiceId");
        }
    } // END private function load

    /* Beginning with v2020-3, RETURNs an array of associative arrays representing invoiceStatutes,
        in displayOrder. As of v2020-3, DB table invoiceStatus is "flat", and this is much simpler
        than it was before. (Any invoiceStatus with a parent is vestigial.)
       Each value in the associative array represents the full row content from DB table
         InvoiceStatus corresponding to the relevant uniquename index, in its canonical
         representation as an associative array.
       For each element indexed by the uniqueName of an invoice status, the value is an
         associative array whose indexes include all columns of DB table invoiceStatus:
         * 'invoiceStatusId'
         * 'parentId' (always 0 for this top level; vestigial as of v2020-3)
         * 'uniqueName' (always identical to the associative array index for this top level)
         * 'statusName': Display name
         * 'displayOrder': started actively using this in v2020-3
         * 'color': Hex RGB color used for backgrounds in display and or reports.
    */
    public static function getInvoiceStatusDataArray() {
        $db = DB::getInstance();
        $ret = array();

        // Get top-level (no parent) invoice statuses
        // $iss will be a numerically indexed array
        $query  = "SELECT * ";
        $query .= "FROM " . DB__NEW_DATABASE . ".invoiceStatus ";
        // $query .= "WHERE parentId = 0 "; // REMOVED 2020-08-10 JM, no more invoiceStatus.parentId
        $query .= "ORDER BY displayOrder, invoiceStatusId;"; // displayOrder brought into this 2020-05-26 JM

        $ret = array();
		$result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ret[] = $row;
            }
        } else {
            // >>>00002 needs to report error.
        }

        return $ret;
    } // END public static function getInvoiceStatusDataArray

    // Register a payment on this invoice
    // INPUT $type - this is really just about validation. Prior to version 2020-02, this had to be
    //   one of 'payFull', 'payBal' or 'payPart'; in version 2020-02 JM added 'reversePay' so that this
    //   allows a reversal, where we move money back from an invoice to a creditRecord. We may yet want other validation scenarios.
    // INPUT $creditRecordId is primary key in DB table creditRecord, the
    //  credit used for the payment
    // INPUT $part (mandatory for 'payPart' or 'reversePay', ignored otherwise) indicates
    //  the amount to pay (in dollars, 2 digits past the decimal point), so
    //  this is effectively a float. SHOULD BE NEGATIVE FOR $type='reversePay'.
    // NOTE that when we use a CreditRecord to make a payment on an Invoice,
    //  we create only a single record, in DB table invoicePayment, to indicate
    //  the transaction. That tracks both the fact that the invoice has been
    //  paid down and that all or part of the credit has been spent.
    // >>>00025: In some ways this is good -- it guarantees that these two things will
    //  always be in sync -- but I wonder if it is completely in line with GAAP,
    //  because a single entry indicates the transaction on both the credit and
    //  debit accounts. Is this just the equivalent of a register, in which case
    //  that is OK, or might we have an accounting issue here?
    // RETURN: 'OK' on success, error status otherwise.
    public function pay($type, $creditRecordId, $part = 0) {
        /* BEGIN REPLACED 2020-02-05
        // Calculate how much has already been paid on this invoice
        $invoicePaid = 0;
        $payments = $this->getPayments();
        foreach ($payments as $pkey => $payment) { // 00035 $pkey never used, could be just foreach ($payments as $payment)
            if (is_numeric($payment['amount'])) {
                $invoicePaid += $payment['amount'];
            }
        }

        // Calculate how much has already been paid out of this creditRecord
        $creditRecord = new CreditRecord($creditRecordId);
        $creditRecordPaid = 0;
        $payments = $creditRecord->getPayments();  // 00012 NOTE multiplexing of $payments to mean something
                                                   // completely different that above.
        $pays = array();
        if (isset($payments['invoices'])) {
            $pays = $payments['invoices'];
        }
        foreach ($pays as $pkey => $payment) { // 00035 $pkey never used, could be just foreach ($pays as $payment)
            if (is_numeric($payment['amount'])) {
                $creditRecordPaid += $payment['amount'];
            }
        }

        if (intval($creditRecord->getCreditRecordId())) {
            if (is_numeric($creditRecord->getAmount())) {
                $ia = $this->getTriggerTotal();

                if (is_numeric($ia)) {
                    $creditRecordBalance = $creditRecord->getAmount() - $creditRecordPaid;
                    $invoiceBalance = $ia - $invoicePaid;

                    if (($creditRecordBalance > 0) && ($invoiceBalance > 0)) {
                        if ($type == 'payFull') {
                            // Record a payment for the full amount of this creditRecord.
                            // 00002, 000016: NOTE that if the $creditRecordBalance MORE THAN
                            //  covers the invoice, instead of paying off the invoice & still having a
                            //  balance on the credit record, we fail silently. Also, here and below,
                            //  as elsewhere, we fail silently on any DB error.
                            if ($invoiceBalance >= $creditRecordBalance) {
                                $query = " insert into " . DB__NEW_DATABASE . ".invoicePayment(creditRecordId, invoiceId, amount, personId) values (";
                                $query .= " " . intval($creditRecord->getCreditRecordId()) . " ";
                                $query .= " ," . intval($this->getInvoiceId()) . " ";
                                $query .= " ," . $this->db->real_escape_string($creditRecord->getAmount()) . " ";
                                $query .= " ," . intval($this->getUser()->getUserId()) . ") ";

                                $this->db->query($query);
                            }
                        }

                        if ($type == 'payBal') {
                            // Apply the remaining balance of this creditRecord
                            // 00002, 000016: NOTE that if the $creditRecordBalance MORE THAN
                            //  covers the invoice, instead of paying off the invoice & still having a
                            //  balance on the credit record, we fail silently.
                            if ($invoiceBalance >= $creditRecordBalance) {
                                $query = " insert into " . DB__NEW_DATABASE . ".invoicePayment(creditRecordId, invoiceId, amount, personId) values (";
                                $query .= " " . intval($creditRecord->getCreditRecordId()) . " ";
                                $query .= " ," . intval($this->getInvoiceId()) . " ";
                                $query .= " ," . $this->db->real_escape_string($creditRecordBalance) . " ";
                                $query .= " ," . intval($this->getUser()->getUserId()) . ") ";

                                $this->db->query($query);
                            }
                        }

                        if ($type == 'payPart') {
                            // Pay a portion of the invoice (specified by $part), using all or part of the
                            //  remaining balance on this creditRecord.
                            // 00026 Oddly, refuses to do this if the creditRecord balance would not be enough to
                            //  fully pay off the invoice. So, for example, if there is $5000 left on the creditRecord
                            //  and $10000 left on the invoice, this will NOT allow us to apply $2500 from the creditRecord to
                            //  the invoice. JM 2019-02-25. Ron agrees this is a bug.
                            // 00002, 000016: And, as in the other cases, if we cannot apply it, we fail silently.
                            if (is_numeric($part)) {
                                if ($creditRecordBalance >= $invoiceBalance) {
                                    if (($invoiceBalance - $part) >= 0) {
                                        $query = " insert into " . DB__NEW_DATABASE . ".invoicePayment(creditRecordId, invoiceId, amount, personId) values (";
                                        $query .= " " . intval($creditRecord->getCreditRecordId()) . " ";
                                        $query .= " ," . intval($this->getInvoiceId()) . " ";
                                        $query .= " ," . $this->db->real_escape_string($part) . " ";
                                        $query .= " ," . intval($this->getUser()->getUserId()) . ") ";

                                        $this->db->query($query);
                                    }
                                }
                            }
                        }
                    } // 00002 else either the invoice or creditRecord lacks a positive balance,
                      // seems like something to log.
                } // 00002 else null triggerTotal, I (JM) would think we should validate
                  // that sooner, and also that it merits logging.
            } // 00002 else null creditRecord amount, I (JM) would think we should validate
              // that sooner, and also that it merits logging.
        } // 00002 else invalid creditRecordId, I (JM) would think we should validate
          // that sooner, and also that it merits logging.
        // END REPLACED 2020-02-05
        */
        // BEGIN REPLACEMENT 2020-02-05
        global $logger; // >>>00002, 000016 Adding logging on an ad hoc basis 2020-02-10 JM because I need it for debugging. This may be worth refining.
                        // In particular, we may want to know exactly what test failed, not just AND them together like we do now.

        $ok = true;
        $error = 'Error paying invoice ' . $this->getInvoiceId() . ", payment type $type, creditRecordId $creditRecordId, part $part: ";
        // Calculate how much has already been paid on this invoice
        $invoicePaid = 0;
        $invoicePayments = $this->getPayments();
        foreach ($invoicePayments as $invoicePayment) {
            if (is_numeric($invoicePayment['amount'])) {
                $invoicePaid += $invoicePayment['amount'];
            }
        }

        // Calculate how much has already been paid out of this creditRecord
        $creditRecord = new CreditRecord($creditRecordId);
        if ( ! intval($creditRecord->getCreditRecordId())) {
            $ok = false;
            $error .= "creditRecordId $creditRecordId is not an integer";
        } else if ( ! is_numeric($creditRecord->getAmount())) {
            $ok = false;
            $error .= "amount {$creditRecord->getAmount()} is not an numeric";
        }

        if ($ok) {
            $adjustedInvoiceTotal = $this->getTriggerTotal();
            if ( ! is_numeric($adjustedInvoiceTotal)) {
                $ok = false;
                $error .= "adjustedInvoiceTotal $adjustedInvoiceTotal is not an numeric";
            }
        }

        if ($ok) {
            $creditRecordAmount = $creditRecord->getAmount();
            $creditRecordBalance = $creditRecord->getBalance();

            $invoiceBalance = $adjustedInvoiceTotal - $invoicePaid;

            if ($type == 'payFull') {
                // Record a payment for the full amount of this creditRecord.
                // Modified 2020-02-11 JM so that if invoice balance is larger than $creditRecordBalance,
                //  we apply the smaller of the two.
                $ok = $creditRecordBalance > 0 && $invoiceBalance > 0;
                if ($ok) {
                    $amount = min($creditRecordBalance, $invoiceBalance);
                } else {
                    $error .= "Failed test for valid payFull with \$creditRecordBalance = $creditRecordBalance and \$invoiceBalance $invoiceBalance";
                }
            } else if ($type == 'payBal') {
                // Apply the remaining balance of this creditRecord. As of 2020-02-11, semantics are really the same as 'payFull'
                $ok = $creditRecordBalance > 0 &&  $invoiceBalance > 0;
                if ($ok) {
                    $amount = min($creditRecordBalance, $invoiceBalance);
                } else {
                    $error .= "Failed test for valid payBal with \$creditRecordBalance = $creditRecordBalance and \$invoiceBalance $invoiceBalance";
                }
            } else if ($type == 'payPart') {
                // Pay a specified portion of the invoice (specified by $part), using all or part of the
                //  remaining balance on this creditRecord.
                $ok = is_numeric($part) &&
                    $creditRecordBalance > 0 &&
                    $invoiceBalance > 0 &&
                    $creditRecordBalance - $part >= 0 &&
                    $invoiceBalance - $part >= 0;
                if ($ok) {
                    $amount = $part;
                } else {
                    $error .= "Failed test for valid payPart with \$creditRecordBalance = $creditRecordBalance, \$invoiceBalance $invoiceBalance, " .
                        "\$part $part";
                }
            } else if ($type='reversePay') {
                $ok = is_numeric($part) &&
				    $part < 0 &&
                    $creditRecordBalance - $part <= $creditRecordAmount;

                if ($ok) {
                    // Further check: make sure there is actually at least the amount we intend to reverse
                    // that currently comes from this creditRecord AND is applied to this invoice
                    $applied = 0;
                    foreach ($invoicePayments as $invoicePayment) {
                        if ($invoicePayment['creditRecordId'] == $creditRecordId && is_numeric($invoicePayment['amount'])) {
                            $applied += $invoicePayment['amount'];
                        }
                    }
                    $ok = $applied + $part >= 0;
                    if (!$ok) {
                        $error .= "Failed test 2 for valid reversePay with \$creditRecordBalance = $creditRecordBalance and \$invoiceBalance $invoiceBalance." .
                            "Trying to make reverse payment $part, but total paid is $applied.";
                    }
                }  else {
                    $error .= "Failed test 1 for valid reversePay with \$creditRecordBalance = $creditRecordBalance and \$invoiceBalance $invoiceBalance";
                }

                if ($ok) {
                    $amount = $part;
                }
            } else {
                $ok = false;
                $error .= "invalid type $type";
            }
        }
        if ($ok) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoicePayment(creditRecordId, invoiceId, amount, personId) VALUES (";
            $query .= intval($creditRecord->getCreditRecordId());
            $query .= ", " . intval($this->getInvoiceId());
            $query .= ", " . $this->db->real_escape_string($amount);
            $query .= ", " . intval($this->getUser()->getUserId()) . ");";

            $result = $this->db->query($query);
            if (!$result) {
                $logger->errorDb('1581384444', 'Insert failed', $db);
            } else {
                $this->adjustInvoiceStatus(); // Added 2020-09-30 JM
            }
        } else {
            $logger->error2('1581384390', $error); // JM 2020-02-10 This is ad hoc, some of these should probably better have their own error log number
        }
        return $ok ? 'OK' : $error;
        // END REPLACEMENT 2020-02-05
    } // END public function pay

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getInvoiceId();
    }

    // >>>00016, >>>00002: the "set" functions here presumably should validate & should log on error

    // Set primary key
    // INPUT $val: primary key (InvoiceId)
    private function setInvoiceId($val) {
        $this->invoiceId = intval($val);
    }

    // Set workOrderId
    // INPUT $val: foreign key to WorkOrder table
    private function setWorkOrderId($val) {
        $this->workOrderId = intval($val);
    }

    // Set ContractId
    // INPUT $val: foreign key to Contract table
    private function setContractId($val) {
        $this->contractId = intval($val);
    }

    // INPUT $val is invoice name. Apparently we *always* use this: start from
    //  Job Name, via contract name, but when we create invoice we copy into the override
    //  right away.
    public function setNameOverride($val) {
        $val = trim($val);
        $val = substr($val, 0, 75); // >>>00002 truncates but does not log
        $this->nameOverride = $val;
    }

    // INPUT $val is a DATETIME in 'Y-m-d H:i:s' format.
    //  >>>00016, >>>00002: time portion should always be 00:00:00, but
    //  does not validate or log.
    public function setInvoiceDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->invoiceDate = $val;
        } else {
            $this->invoiceDate = '0000-00-00 00:00:00';
        }
    }

    // INPUT $val is foreign key into DB table Terms.
    private function setTermsId($val) {
        $this->termsId = intval($val);
    }

    // JM: Per discussion with Martin 2018-10, logic here may be somewhat broken,
    //  but the intent is that this will eventually really mean that the invoice is committed.
    // INPUT $val: Boolean as to whether invoice is committed. Once committed, it should
    //  stay committed. Since this is a private method, we know that the only place it is
    //  called is in the constructor and in public function update.
    private function setCommitted($val) {
        $this->committed = intval($val);
    }

    // INPUT $val is a DATETIME in 'Y-m-d H:i:s' format.
    // As of 2019-02-20, a bit of a mess here. This probably should be a TIMESTAMP, not (as it is)
    // a DATETIME: only time this should change is when 'committed' goes from 0 to 1 (it should never
    // go back)
    // >>>00001: probably deserves further thought/study 2019-02-25 JM
    private function setCommittedTime($val) {
        $this->committedTime = $val;
    }

    // INPUT $val is a TIMESTAMP in 'Y-m-d H:i:s' format.
    // When row was created, never should be modified, so it's a bit fast & loose
    // that we let this be passed in as part of $val in constructor.
    // >>>00001: probably deserves further thought/study 2019-02-20 JM, Maybe
    //  *always* read from DB & get value there? Maybe don't care because this
    //  never gets *written* to DB.
    private function setInserted($val) {
        $this->inserted = $val;
    }

    // This should only be used by the constructor.
    // Trigger invstatustime_after_insert writes this, keeping it sync'd with the latest
    //  value in table invoiceStatusTime for matching invoiceId.
    //  See documentation of table invoiceStatusTime for details.
    // INPUT $val
    private function setInvoiceStatusId($val) {
        $this->invoiceStatusId = intval($val);
    }

    // Added for v2020-3
    private function setInvoiceStatusTimeId($val) {
        $this->invoiceStatusTimeId = intval($val);
    }
    // There is no getInvoiceStatusTimeId. Just read the value of $this->invoiceStatusTimeId, which
    // is not public in any case.

    /* BEGIN REMOVED 2020-08-10 JM for v2020-4
    // This should only be used by the constructor.
    // Trigger invstatustime_after_insert writes this, keeping it sync'd with the latest
    //  value in table invoiceStatusTime for matching invoiceId.
    //  See documentation of table invoiceStatusTime for details.
    // INPUT $val
    private function setExtra($val) {
        $this->extra = intval($val);
    }
    // END REMOVED 2020-08-10 JM for v2020-4
    */

	// INPUT $val encodes information about workOrderTasks that constitute the body of the invoice.
	// It may be either a string or an array, as discussed below.
	//
	// In the DB, invoice.data has existed for a long time; invoice.data2 is introduced in Panther v2020-4,
	// and probably in its final form as of 2020-09-28. In the database:
	// * invoice.data is a serialized, base-64-encoded version of the JSON representation of a multi-level array
	//   structure combining associative and numerically indexed arrays. If non-null, it represents the return
	//   of function overlay as it stood up to and including Panther v2020-3. We are no longer actively maintaining
	//   this column.
	// * invoice.data2 is the straight JSON representation of a multi-level array structure combining
	//   associative and numerically indexed arrays. If non-null, it represents the return
	//   of function overlay as it stands in Panther v2020-4. We actively maintain this column.
	// As of 2020-09-09, the only difference between the two JSON structures is how we handle workOrderTasks that
	//   are associated with a combination of elements rather than with a single element. The principal
	//   difference is in the level of this array that represents elementgroups:
	// Index
	// 0	                                                     "General" (no particular element)
	// elementId  (any single integer except PHP_INT_MAX)        That elementId
	// PHP_INT_MAX (only in 'data')                              Any combination of two or more elements
	// comma-separated list of integer values (only in 'data2')  Two or more specific elements, as listed
	//
	// There are also some differences within the data for elements that link to two or more elements,
	//  mainly insofar as they include elementId and elementName.
	//
	// Going forward, we want the data2 form, and that is all we will maintain.
	//
	// INPUT $val may be either in the form of an array, analogous to the output of function overlay, or
	//  may be the string form stored in the database. If the latter, we decode & unserialize it: object member
	//  $this->data is always in the form of the array (which is a complicated hierarchy of
	//  arrays, associative and otherwise).
    private function setData($val) {
	    /* BEGIN REPLACED 2020-09-09 JM
		if (!is_array($val) && strlen($val)){
			$t = base64_decode($val);
			$t = unserialize($t);
			if (is_array($t)){
				$this->data = $t;
			} else {
				$this->data = array();
			}
		} else if (is_array($val)){
			$this->data = $val;
		} else {
			$this->data = array();
		}
		// END REPLACED 2020-09-09 JM
		*/
		// BEGIN REPLACEMENT 2020-09-09 JM, further adapted 2020-09-28
		// >>>00006 this probably belongs somewhere as common code with Contract.class.php
		if (is_string($val) && strlen($val)) {
		    $val2 = json_decode($val, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                // It's JSON, which is what we write in the DB now
                $val = $val2;
            } else {
                // Presumably the old way we encrypted this as a string before v2020-4.
                $t = base64_decode($val);
                $val = unserialize($t); // overwrite INPUT $val: we have decrypted it from a string
            }
		}
		// At this point, if it's not an array, it's junk so we'll substitute an empty array
		if (!is_array($val)) {
			$val = array();
		}
		// Now transform to the new version adopted in v2020-4.
		// * If there is an old-style multi-element entry, we need to transform it
		// * If there is a new-style multi-element entry, we must be in the new version, and no tranform is needed
		// * If there is neither, then the issue doesn't arise for this particular data, and we're fine.

		$transformNeeded = false;
		foreach ($val AS $elementGroup => $elementGroupData) {
		    if (is_string($elementGroup) && strpos($elementGroup, ',') !== false) {
		        break; // new-style multi-element, nothing to do
		    } else if ($elementGroup == PHP_INT_MAX) {
		        // old-style multi-element
		        $transformNeeded = true;
		        break;
		    }
		}

		if ($transformNeeded) {
		    foreach ($val AS $elementGroup => $elementGroupData) {
                if ($elementGroup == PHP_INT_MAX) {
                    // old-style multi-element
                    $newElementGroups = Array();
                    foreach($elementGroupData['tasks'] as $workOrderTaskData) {
                        $workOrderTaskId = $workOrderTaskData['workOrderTaskId'];
                        if (!WorkOrderTask::validate($workOrderTaskId)) {
                            $error = "invalid workOrderTaskId $workOrderTaskId in data";
                            // >>>00006 if we pull out common code, the context on failure probably needs to be provided by the caller.
                            if (isset($this->invoiceId)) {
                                $error .= " for invoice {$this->invoiceId}";
                            }
                            $this->logger->error2('1599605640', $error);
                            // We have a mess. Just fail.
                            $this->data = Array();
                            return;
                        }
                        $workOrderTask = new WorkOrderTask($workOrderTaskId);
                        $newElements = $workOrderTask->getWorkOrderTaskElements(); // We rely here on these coming in a predictable order, by elementId.
                        $newElementGroupId = '';
                        $newElementGroupName = '';
                        foreach($newElements as $element) {
                            if ($newElementGroupId) {
                                $newElementGroupId .= ','; // NO space after comma
                            }
                            if ($newElementGroupName) {
                                $newElementGroupName .= ', '; // space after comma
                            }
                            $newElementGroupId .= $element->getElementId();
                            $newElementGroupName .= $element->getElementName();
                        }
                        if (!array_key_exists($newElementGroupId, $newElementGroups)) {
                            $newElementGroups[$newElementGroupId] = Array();
                            $newElementGroups[$newElementGroupId]['element'] =
                                Array("elementId" => $newElementGroupId, "elementName" => $newElementGroupName);
                            $newElementGroups[$newElementGroupId]['tasks'] = Array();
                        }
                        $newElementGroups[$newElementGroupId]['tasks'][] = $workOrderTaskData;
                        unset($newElementGroupId);
                    }
                    foreach ($newElementGroups as $newElementGroupId => $newElementGroup) {
                        $val[$newElementGroupId] = $newElementGroup;
                    }
                    unset($val[PHP_INT_MAX]);
                    break;
                }
            }
		}
		$this->data = $val;
		// END REPLACEMENT 2020-09-09 JM
    } // END private function setData

    // INPUT $val: note (string)
    public function setCommitNotes($val) {
        $val = trim($val);
        $val = substr($val, 0, 1024); // >>>00002 truncates but does not log
        $this->commitNotes = $val;
    }

    // INPUT $val: foreign key into DB table Person
    public function setCommitPersonId($val) {
        $this->commitPersonId = intval($val);
    }

    // INPUT $val: note (string)
    // Added for v2020-4
    public function setInvoiceNotes($val) {
        $val = trim($val);
        $val = substr($val, 0, 65535); // >>>00002 truncates but does not log
        $this->commitNotes = $val;
    }

    // INPUT $val: floating point number, most often 1.0
    public function setClientMultiplier($val) {
        if(filter_var($val, FILTER_VALIDATE_FLOAT) !== false) {
            $this->clientMultiplier = $val;
        } else {
            // >>>00002 does not log validation failure.
            // >>>00001 why isn't default 1.0? - JM 2019-02-20
            $this->clientMultiplier = 0;
        }
    }

    // $val: Overrides formatted person name associated with companyPersonId in shadow billing profile
    public function setPersonNameOverride($val) {
        $val = trim($val);
        $val = substr($val, 0, 256); // >>>00002 truncates but does not log
        $this->personNameOverride = $val;
    }

    // $val: Overrides formatted person location associated with companyPersonId in shadow billing profile
    private function setPersonLocationOverride($val) {
        $val = trim($val);
        $val = substr($val, 0, 256); // >>>00002 truncates but does not log
        $this->personLocationOverride = $val;
    }

    /* BEGIN REMOVED 2020-03-13 JM
    private function setSavePersonId($val) {
        $this->savePersonId = intval($val);
    }
    // END REMOVED 2020-03-13 JM
    */

    /* BEGIN REMOVED 2020-03-13 JM
    private function setSavePersonLocationId($val) {
        $this->savePersonLocationId = intval($val);
    }
    // END REMOVED 2020-03-13 JM
    */

    /* BEGIN REMOVED 2020-03-13 JM
    private function setSaveLocationId($val) {
        $this->saveLocationId = intval($val);
    }
    // END REMOVED 2020-03-13 JM
    */

    // $val: Invoice total in U.S. currency. Initial amount, but compare triggerTotal which reflects payments that have been made.
    private function setTotal($val) {
        $val = preg_replace("/[^0-9.+-]/","", $val); // >>>00002: rather than just remove invalid characters,
                                                     // we should log if input isn't as expected.
        $this->total = $val;
    }

    // >>>00007 JM: Martin said dead as of 2018-10. Presumably should remove this.
    private function setTotalOverride($val) {
        $val = preg_replace("/[^0-9.+-]/","", $val);
        $this->totalOverride = $val;
    }

    // Total, taking into account any InvoiceAdjustments.
    // This should only be used by the constructor.
    // DB value is maintained by several triggers.
    // INPUT $val
    private function setTriggerTotal($val) {
        $val = preg_replace("/[^0-9.+-]/","", $val);
        $this->triggerTotal = $val;
    }

    // Added 2020-09-30 JM for v2020-4
    // To be called after any change to a relevant invoiceAdjustment or invoicePayment:
    // adjust invoiceStatus accordingly. Previously, this had to be done manually.
    private function adjustInvoiceStatus() {
        $currentStatusId = $this->getInvoiceStatusId();
        if (in_array($currentStatusId, self::getNonSentInvoiceStatuses())) {
            // Leave the non-sent status alone: don't adjust it automatically.
        } else {
            $payments = $this->getPayments();
            $triggerTotal = $this->getTriggerTotal();
            $desiredStatusId = self::getInvoiceStatusIdFromUniqueName('awaitingpayment'); // shouldn't ever fall back to this initialization, but being super-safe
            if ($triggerTotal == 0) {
                // adjustments have brought this down to zero, so it is necessarily closed. Payments are not germane
                $desiredStatusId = self::getInvoiceStatusIdFromUniqueName('closed');
            } else {
                $paymentsTotal = 0;
                foreach ($payments as $payment) {
                    $paymentsTotal += $payment['amount'];
                }

                if ($paymentsTotal > $triggerTotal) {
                    // We will consider this closed, but also note this in the log, shouldn't happen
                    $this->logger->error2('1601493933', "Invoice " . $this->getInvoiceId() .
                            ", total payments $paymentsTotal exceeds trigger total $triggerTotal");
                }
                if ($paymentsTotal < 0) {
                    // We will consider this awaitingpayment, but also note this in the log, shouldn't happen
                    $this->logger->error2('1601494006', "Invoice " . $this->getInvoiceId() .
                            ", total payments $paymentsTotal less than zero!");
                }

                if ($paymentsTotal >= $triggerTotal) {
                    $desiredStatusId = self::getInvoiceStatusIdFromUniqueName('closed');
                } else if ($paymentsTotal <= 0) {
                    // either no payments have been made or positive and negative have cancelled
                    $desiredStatusId = self::getInvoiceStatusIdFromUniqueName('awaitingpayment');
                } else {
                    $desiredStatusId = self::getInvoiceStatusIdFromUniqueName('partiallypaid');
                }
            }
            if ($desiredStatusId != $currentStatusId) {
                $customerPersonIds = $this->getStatusCustomerPersonIds();
                $this->setStatus($desiredStatusId, $customerPersonIds, 'auto-set');
            }
        }
    } // END private function adjustInvoiceStatus

    // RETURNs an array of associative arrays, each representing a payment;
    //  each associative array is the canonical representation of a row in
    //  InvoicePayments, mapping column name to value.
    public function getPayments() {
        // Selects all rows from DB table InvoicePayments that match the current invoice.
        $payments = array();
        $query  = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".invoicePayment ";
        $query .= " where invoiceId = " . intval($this->getInvoiceId()) . " ";
        $query .= " order by invoicePaymentId ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $payments[] = $row;
                }
            }
        }

        return $payments;
    } // END public function getPayments

    // RETURN primary key.
    public function getInvoiceId() {
        return $this->invoiceId;
    }

    // RETURN foreign key to WorkOrder table
    public function getWorkOrderId() {
        return $this->workOrderId;
    }

    // RETURN foreign key to Contract table
    public function getContractId() {
        return $this->contractId;
    }

    // RETURN invoice name. Apparently this is always set, no need to fall back
    //  to contract, job.
    public function getNameOverride() {
        return $this->nameOverride;
    }

    // RETURN nominal date of invoice
    public function getInvoiceDate() {
        return $this->invoiceDate;
    }

    // RETURN foreign key into DB table Terms
    public function getTermsId() {
        return $this->termsId;
    }

    // Added for v2020-4
    public function getInvoiceNotes() {
        return $this->commitNotes;
    }

    // $val editCount; we mostly care whether this is nonzero.
    public function setEditCount($val) {
        $this->editCount = intval($val);
    }

    // >>>00014 $addressOverride NOT DECLARED ABOVE
    // $val can be null, definitely is null for older rows. I (JM) believe
    //  this is only meaningful if editCount>0. Overrides information from shadow
    //  billing profile and should be a concatenation of any of the following
    //  that are defined, with '::' as a separator between them:
    //   * formatted name of companyPerson
    //   * person email address
    //   * company email address
    //   * person location (commas instead of newlines)
    //   * company location (commas instead of newlines).
    // This is definitely active as of 2018-10, but still might be going away.
    // >>>00001: Seems a bit of a kluge.
    public function setAddressOverride($val) {
        $val = trim($val);
        $val = substr($val, 0, 255); // >>>00002 truncates but does not log
        $this->addressOverride = $val;
    }

    // >>>00007: presumably from the code here, this is on its way out & should go away.
    //  But then there is Martin's remark elsewhere that eventually Invoice.committed should
    //  be maintained.
    // >>>00001 We need to make decisions going forward.
    public function getCommitted() {
        try {
            /* OLD CODE REMOVED 2019-02-18
            $error = 'committed in invoice being used.  TELL MARTIN!';
            */
            // BEGIN NEW CODE 2019-02-18
            // >>>00002 Should also log an error with more of an
            //  indication of what happened here.
            $error = 'committed in invoice being used.  TELL DEV!';
            // END NEW CODE 2019-02-18
            throw new Exception($error);
        } catch (Exception $e) {
            echo $e->getMessage() . '<p>';
        }

        return intval($this->committed);
    } // END public function getCommitted

    // RETURN Boolean, true if this invoice is committed, false otherwise.
    // Basically, it is committed if it doesn't have a status saying that it is
    //  being held up.
    public function getCommittedNew() {
        if (in_array($this->getInvoiceStatusId(), self::getNonSentInvoiceStatuses())) {
            return 0;
        } else {
            return 1;
        }
    }

    // RETURN committed time, apparently as of 2019-02-25 a DATETIME (not a timestamp) in 'Y-m-d H:i:s' format.
    public function getCommittedTime() {
        return $this->committedTime;
    }

    // RETURN inserted time, apparently as of 2019-02-25 a TIMESTAMP in 'Y-m-d H:i:s' format.
    public function getInserted() {
        return $this->inserted;
    }

    // Trigger invstatustime_after_insert writes this, keeping it sync'd with the latest
    //  value in table invoiceStatusTime for matching invoiceId.
    // RETURN: See documentation of table invoiceStatusTime for details.
    public function getInvoiceStatusId() {
        return $this->invoiceStatusId;
    }

    /* REMOVED 2020-05-22 JM for v2020-3. This was not being called, and doesn't fit in with our new approach to invoice status
    // Trigger invstatustime_after_insert writes this, keeping it sync'd with the latest
    //  value in table invoiceStatusTime for matching invoiceId.
    // RETURN: See documentation of table invoiceStatusTime for details.
    public function getExtra() {
        return $this->extra;
    }
    */

	// RETURN encodes the information about workOrderTasks in the invoice.
	// The return (a complicated hierarchy of arrays, associative and otherwise)
	//  is identical to the return of function 'overlay' in inc/functions.php, and is
	//  documented there.
    public function getData() {
        return $this->data;
    }

    // RETURNs a string
    public function getCommitNotes() {
        return $this->commitNotes;
    }
    // RETURN foreign key into DB table Person, indicating who committed the contract
    public function getCommitPersonId() {
        return $this->commitPersonId;
    }
    // RETURN clientMultiplier (float)
    public function getClientMultiplier() {
        return $this->clientMultiplier;
    }

    // RETURN, if non-null & nonempty, overrides formatted person name associated with companyPersonId in shadow billing profile
    public function getPersonNameOverride() {
        return $this->personNameOverride = $val;
    }

    // RETURN, if non-null & nonempty, overrides person location associated with companyPersonId in shadow billing profile
    private function getPersonLocationOverride($val) {
        return $this->personLocationOverride = $val;
    }

    /* BEGIN REMOVED 2020-03-13 JM
    private function getSavePersonId($val) {
        return $this->savePersonId;
    }
    // END REMOVED 2020-03-13 JM
    */

    /* BEGIN REMOVED 2020-03-13 JM
    private function getSavePersonLocationId($val) {
        return $this->savePersonLocationId;
    }
    // END REMOVED 2020-03-13 JM
    */

    /* BEGIN REMOVED 2020-03-13 JM
    private function getSaveLocationId($val) {
        return $this->saveLocationId;
    }
    // END REMOVED 2020-03-13 JM
    */

    // RETURN: U.S. currency, original unadjusted total for invoice.
    public function getTotal() {
        return $this->total;
    }

    // We no longer actively set totalOverride -- now we use adjustments -- but
    // thousands of old invoices use this.
    public function getTotalOverride() {
        return $this->totalOverride;
    }

    // RETURN: U.S. currency, current adjusted total for invoice.
    public function getTriggerTotal() {
        return $this->triggerTotal;
    }

    // RETURN integer editCount; we mostly care whether this is nonzero.
    public function getEditCount() {
        return $this->editCount;
    }

    // >>>00014 $addressOverride NOT DECLARED ABOVE
    // RETURN can be null, definitely is null for older rows. I (JM) believe
    //  this is only meaningful if editCount>0. Overrides information from shadow
    //  billing profile and should be a concatenation of any of the following
    //  that are defined, with '::' as a separator between them:
    //   * formatted name of companyPerson
    //   * person email address
    //   * company email address
    //   * person location (commas instead of newlines)
    //   * company location (commas instead of newlines).
    // This is definitely active as of 2018-10, but still might be going away.
    // >>>00001: Seems a bit of a kluge.
    public function getAddressOverride() {
        return $this->addressOverride;
    }

    // Deletes the specified row from the DB table invoiceAdjust; if there is no such row,
    //  or if invoiceAdjust.invoiceId doesn't match the current invoice, it silently does nothing
    //  >>>00002 but presumably should log its failure to act.
    public function deleteAdjustment($invoiceAdjustId) {
        $query = " delete ";
        $query .= " from " . DB__NEW_DATABASE . ".invoiceAdjust ";
        $query .= " where invoiceId = " . intval($this->getInvoiceId()) . " ";
        $query .= " and invoiceAdjustId = " . intval($invoiceAdjustId);

        $this->db->query($query);

        $this->adjustInvoiceStatus(); // Added 2020-09-30 JM
    }


    //  RETURN an array of associative arrays, each representing an adjustment for this invoice;
    //   each associative array is the canonical representation of a row in invoiceAdjust,
    //   mapping column name to value.
    public function getAdjustments() {
        // Select all rows from DB table invoiceAdjust that match the current invoice.
        $ret = array();
        $query = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".invoiceAdjust ";
        $query .= " where invoiceId = " . intval($this->getInvoiceId());

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $ret[] = $row;
                }
            }
        }

        return $ret;
    }

    // Adjust this invoice (up or down)
    // INPUT $invoiceAdjustTypeId, foreign key into DB table invoiceAdjustType
    // INPUT $amount: amount of adjustment, U.S. currency in dollars with up to two digits past
    //  the decimal.
    // INPUT $invoiceAdjustNote: a note for the new InvoiceAdjust row.
    public function addAdjustment($invoiceAdjustTypeId, $amount, $invoiceAdjustNote = "") {
        // Make sure that $invoiceAdjustTypeId can be found in DB table invoiceAdjustType
        $query = "SELECT invoiceAdjustTypeId ".
                 "FROM " . DB__NEW_DATABASE . ".invoiceAdjustType ";
        $query .= "WHERE invoiceAdjustTypeId = " . intval($invoiceAdjustTypeId) . ";";

        $exists = false;
        $result = $this->db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                $exists = true;
            }
            if ($exists) {
                if (is_numeric($amount)) {
                    // make the obvious insertion in DB table invoiceAdjust
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceAdjust (invoiceAdjustTypeId, invoiceId, amount, invoiceAdjustNote";
                    $query .= ") VALUES (";
                    $query .= intval($invoiceAdjustTypeId);
                    $query .= ", " . intval($this->getInvoiceId());
                    $query .= ", " . $this->db->real_escape_string($amount);
                    $query .= ", '" . $this->db->real_escape_string($invoiceAdjustNote) . "'";
                    $query .= ");";
                    $result = $this->db->query($query);
                    if ($result) {
                        $this->adjustInvoiceStatus(); // Added 2020-09-30 JM
                    } else {
                        $this->logger->errorDB('1605828654', "Hard DB error", $this->db);
                    }
                }
            } else {
                $this->logger->error2('1605828933', "Invalid invoiceAdjustTypeId '$invoiceAdjustTypeId'");
            }
        } else {
            $this->logger->errorDB('1605828757', "Hard DB error", $this->db);
        }
    } // END public function addAdjustment

    // Despite the plural in this function name, it gets a single row from DB table InvoiceBillingProfile
    //  (not as the function name might suggest from DB table BillingProfile).
    // RETURNs a one-element array containing an associative array representing a
    //  single row from DB table invoiceBillingProfile, the most recent row with
    //  a matching invoiceId (relying on a trick to get the most recent: it assumes
    //  that the higher invoiceBillingProfileId always represents the more recent entry
    //  in invoiceBillingProfile). The associative array is used in the canonical manner to represent a row.
    public function getBillingProfiles() {
        $ret = array();
        $query = "SELECT * ";
        $query .= "FROM " . DB__NEW_DATABASE . ".invoiceBillingProfile  ";
        $query .= "WHERE invoiceId = " . intval($this->getInvoiceId()) . " ";
        $query .= "ORDER BY invoiceBillingProfileId DESC LIMIT 1;";

        $result = $this->db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $ret[] = $row;
            }
        } else {
            $this->logger->errorDB('1605829392', "Hard DB error", $this->db);
        }

        return $ret;
    }

    // Updates the total for the relevant row in DB table invoice. A bit tricky.
    // Calculates the total from the clientMultiplier and the content of the 'data' property.
    public function storeTotal() {
        $data = $this->getData();
        $clientMultiplier = $this->getClientMultiplier();

        // 00016, 00002: JM I'd like to see more validation on clientMultiplier,
        //  here and/or where it gets set, also logging if we need to tweak it. In
        //  particular, if it is zero I suspect we should force it to 1.
        if (filter_var($clientMultiplier, FILTER_VALIDATE_FLOAT) === false) {
            $clientMultiplier = 1;
        }

        $tot = 0;

        // Using the content of the 'data' property, loop over element groups
        foreach ($data as $elementgroup) {
            // Within element group, look at tasks, concerning ourselves only with those for
            //  which type='real'; JM: I believe these are really workOrderTasks, not tasks
            if (isset($elementgroup['tasks'])) {
                if (is_array($elementgroup['tasks'])) {
                    $tasks = $elementgroup['tasks'];
                    // for each task (really workOrderTask) we add $estQuantity * $estCost * $clientMultiplier toward the total.
                    foreach ($tasks as $task) {
                        if ($task['type'] == 'real') {
                            $taskTypeId = $task['task']['taskTypeId'];
                            $wot = new WorkOrderTask($task['workOrderTaskId']);
                            /* BEGIN REPLACED 2020-08-11 JM
                            // Replaced because we apparently somehow had cases where $task['task']['estQuantity']
                            // was set but not numeric.
                            $estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;
                            $estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;
                            // END REPLACED 2020-08-11 JM
                            */
                            // BEGIN REPLACEMENT 2020-08-11 JM
                            $estQuantity = 0;
                            if (isset($task['task']['estQuantity'])) {
                                if (is_numeric($task['task']['estQuantity'])) {
                                    $estQuantity = $task['task']['estQuantity'];
                                } else {
                                    $this->logger->error2('1597176213', 'For workOrderTaskId ' . $task['workOrderTaskId'] .
                                        ', $task[\'task\'][\'estQuantity\'] is set but not numeric.');
                                    // and let $estQuantity remain 0.
                                }
                            }

                            $estCost = 0;
                            if (isset($task['task']['estCost'])) {
                                if (is_numeric($task['task']['estCost'])) {
                                    $estCost = $task['task']['estCost'];
                                } else {
                                    $this->logger->error2('1597176374', 'For workOrderTaskId ' . $task['workOrderTaskId'] .
                                        ', $task[\'task\'][\'estCost\'] is set but not numeric.');
                                    // and let $estCost remain 0.
                                }
                            }
                            // END REPLACEMENT 2020-08-11 JM

                            // >>>00007 Martin said in a conversation in autumn 2019 that there is nothing significant
                            //  here where the code tests whether $taskTypeId == TASKTYPE_HOURLY and then does the same
                            //   thing either way. There used to be a difference, now there isn't.)
                            if ($taskTypeId == TASKTYPE_HOURLY) {
                                $tot += ($estQuantity * $estCost * $clientMultiplier);
                            } else {
                                $tot += ($estQuantity * $estCost * $clientMultiplier);
                            }
                        }
                    }
                } // else >>>00002 I (JM) suspect this would be a structual error that merits logging
            } // else >>>00002 I (JM) suspect this would be a structual error that merits logging
        }

        $tot = preg_replace("/[^0-9.+-]/","", $tot); // >>>00001: what's going on here? How could it not be a well-formed number?
                                                     // >>>00002; And if it's not, shouldn't we log that?!

        $query = " update " . DB__NEW_DATABASE . ".invoice set ";
        $query .= " total = " . $this->db->real_escape_string($tot) . " ";
        $query .= " where invoiceId = " . intval($this->getInvoiceId()) . " ";

        $this->db->query($query); // >>>00002 as elsewhere, we fail to note any DB error; in this case
                                  // that seems particularly pernicious because we call setTotal method regardless.

        $this->setTotal($tot); // maintain our local copy
    } // END public function storeTotal
    public function getInvoiceTotal(){

        $dataInvoice=$this->getData();

        $treeArray=[];

        foreach($dataInvoice[4] as $key=>$value)
        {
            $treeArray[$value['id']]['parentId']=$value['parentId'];
            $treeArray[$value['id']]['key']=$key;
            $treeArray[$value['id']]['lev1']=0;
            $treeArray[$value['id']]['type']="N";

            if($value['elementId']==$value['id']) // element => initial zero
            {
                $dataInvoice[4][$key]['totCost']=0;
                $treeArray[$value['id']]['type']="E";
            }
            if($value['parentTaskId']==$value['elementId'] && $value['hasChildren']) // level 1 task
            {
                $dataInvoice[4][$key]['totCost']=0;
                $treeArray[$value['id']]['type']="F";
            }
        }
        foreach($dataInvoice[4] as $key=>$value)
        {
            $taskId=$value['id'];

            if($treeArray[$taskId]['type']!="N"){
                continue;
            } else {
                $wType=$treeArray[$taskId]['type'];
                $parent=$treeArray[$taskId]['parentId'];
                $i=0;
                $currTask=$taskId;
                while($treeArray[$currTask]['type']=="N" && $i<5){
                    $currTask=$treeArray[$currTask]['parentId'];
                    $i++;
                }
                $treeArray[$taskId]['levelOne']=$currTask;
            }

        }

        foreach($treeArray as $key=>$value)
        {
            //echo $key."<br><br>";
            //print_r($value);
            if($value['type']=="N")
            {
                $dataInvoice[4][$value['key']]['totCost']=(float)($dataInvoice[4][$value['key']]['quantity'])*intval($dataInvoice[4][$value['key']]['cost']);
                $dataInvoice[4][$treeArray[$value['levelOne']]['key']]['totCost']+=(float)($dataInvoice[4][$value['key']]['quantity'])*intval($dataInvoice[4][$value['key']]['cost']);
                if($treeArray[$value['levelOne']]['type']=="F"){
                    $keyL1=$treeArray[$treeArray[$value['levelOne']]['parentId']]['key'];
                    $dataInvoice[4][$keyL1]['totCost']+=(float)($dataInvoice[4][$value['key']]['quantity'])*intval($dataInvoice[4][$value['key']]['cost']);
                }
            }
        }

        $sumInvoice=0;
        foreach($dataInvoice[4] as $key=>$value)
        {
            $taskId=$value['id'];
            if($value['elementId']==$value['id']) // element => initial zero
            {
                $sumInvoice+=$dataInvoice[4][$key]['totCost'];
            }
        }

        $this->setTotal($sumInvoice);

        $this->update(array(
            'data' => $dataInvoice
        ));

        return $sumInvoice;
    }

    // INPUT $val is an associative array that can have any or all of the following elements:
    //  * 'termsId' - this and most of the others can best be understood by looking at the "set" methods above.
    //  * 'nameOverride'
    //  * 'addressOverride'
    //  * 'committed'
    //  * 'data'
    //  * 'commitNotes'
    //  * 'invoiceDate'
    //    * This is NOT expected not in 'Y-m-d H:i:s' form; oddly, we don't even *allow*
    //      that form, although it is what we will build here. Instead, we want it in 'm/d/Y' form, and if it
    //      isn't input in that form, we will fall back to '0000-00-00 00:00:00'.
    //    * >>>00016: On the other hand, we don't properly validate that the month, day, and year are numbers,
    //      let alone in a sane range. We just take intval, so '42/2b3/99' would turn into an insane
    //      '0099-42-02' and make it into an UPDATE.
    //  * 'clientMultiplier'
    //  * 'IncrementEditCount': note unusual capitalization. This is effectively a Boolean, passed to
    //    public function save to indicate whether or not to increment the edit count.


    public function update($val) {
        if (is_array($val)) {
            if (isset($val['termsId'])) {
                // >>>00007 isset test in following line is redundant to test already made, as are analogous ones
                //  for other elements of the associative array.
                $termsId = isset($val['termsId']) ? intval($val['termsId']) : 0;
                $this->setTermsId($termsId);
            }

            if (isset($val['nameOverride'])) {
                $nameOverride = isset($val['nameOverride']) ? $val['nameOverride'] : '';
                $this->setNameOverride($nameOverride);
            }

            // >>>00014 $addressOverride NOT DECLARED ABOVE
            if (isset($val['addressOverride'])) {
                $addressOverride = isset($val['addressOverride']) ? $val['addressOverride'] : '';
                $this->setAddressOverride($addressOverride);
            }

            // [BEGIN Martin comment]
            // removed 2018-08-08 during trigger shit
            //if (isset($val['totalOverride'])){
            //
            //	$totalOverride = isset($val['totalOverride']) ? $val['totalOverride'] : '';
            //	$this->setTotalOverride($totalOverride);
            //
            //}
            // [END Martin comment]

            if (isset($val['committed'])) {
                $committed = isset($val['committed']) ? $val['committed'] : 0;
                $this->setCommitted($committed);
                if ($committed) {
                    $this->updateCommittedTime = true;
                }
            }

            if (isset($val['data'])) {
                $data = isset($val['data']) ? $val['data'] : false;
                $this->setData($data);
            }

            if (isset($val['commitNotes'])) {
                $commitNotes = isset($val['commitNotes']) ? $val['commitNotes'] : '';
                $this->setCommitNotes($commitNotes);
            }

            // BEGIN Added for v2020-4
            if (isset($val['invoiceNotes'])) {
                $invoiceNotes = isset($val['invoiceNotes']) ? $val['invoiceNotes'] : '';
                $this->setInvoiceNotes($invoiceNotes);
            }
            // END Added for v2020-4

            if (isset($val['invoiceDate'])) {
                // >>>00006: code like this appears in a lot of different classes,
                // should certainly have potential for common code elimination.
                $invoiceDate = '0000-00-00 00:00:00';

                $parts = explode("/", $val['invoiceDate']);
                if (count($parts) == 3) {
                    $invoiceMonth = intval($parts[0]);
                    $invoiceDay = intval($parts[1]);
                    $invoiceYear = intval($parts[2]);

                    $invoiceMonth = str_pad($invoiceMonth,2,'0',STR_PAD_LEFT);
                    $invoiceDay = str_pad($invoiceDay,2,'0',STR_PAD_LEFT);
                    $invoiceYear = str_pad($invoiceYear,4,'0',STR_PAD_LEFT);

                    $invoiceDate = $invoiceYear . '-' . $invoiceMonth . '-' . $invoiceDay . ' 00:00:00';
                }

                $this->setInvoiceDate($invoiceDate);
            }

            if (isset($val['clientMultiplier'])) {
                $this->setClientMultiplier($val['clientMultiplier']);
            }

            $IncrementEditCount = 0;
            if (isset($val['IncrementEditCount'])) {
                $IncrementEditCount = isset($val['IncrementEditCount']) ? intval($val['IncrementEditCount']) : 0;
            }

            if (intval($IncrementEditCount)) {
                $this->save(1);
            } else {
                $this->save(0);
            }
        } // >>>00002 else input had wrong form, probably worth logging
    } // END public function update

    // save function is important here, because public set functions won't affect the DB until save is also called.
    // >>>00001: it might be worth studying whether anyone is using those public set functions, or just the update function. JM 2019-02-25.
    // INPUT $incrementEditCount: effectively a Boolean, if true we increment the edit count.
    public function save($incrementEditCount) {
        // [BEGIN MARTIN COMMENT]
        // never tdo triggerTotal here as it is done through triggers
        // never tdo triggerTotal here as it is done through triggers
        // never tdo triggerTotal here as it is done through triggers
        // [END MARTIN COMMENT]

        // >>>00014 $addressOverride NOT DECLARED ABOVE
        $query = " update " . DB__NEW_DATABASE . ".invoice  set ";
        $query .= " nameOverride = '" . $this->db->real_escape_string($this->getNameOverride()) . "' ";
        $query .= " ,addressOverride = '" . $this->db->real_escape_string($this->getAddressOverride()) . "' ";
        $query .= " ,invoiceDate = '" . $this->db->real_escape_string($this->getInvoiceDate()) . "' ";
        $query .= " ,termsId = " . intval($this->getTermsId()) . " ";

        // [BEGIN MARTIN COMMENT]
        // removed when moving away from committed methodology to invoicestatus time methodology
        //$query .= " ,committed = " . intval($this->getCommitted()) . " ";
        // [END MARTIN COMMENT]

        /* BEGIN REPLACED 2020-09-09 JM
        $query .= " ,data = '" . $this->db->real_escape_string(    base64_encode(serialize($this->getData()))    ) . "' ";
        // END REPLACED 2020-09-09 JM
        */
        // BEGIN REPLACEMENT 2020-09-09 JM, further tweaked 2020-09-28
        $query .= ", data2 = '" . $this->db->real_escape_string(json_encode($this->getData())) . "' ";
        // END REPLACEMENT 2020-09-09 JM
        $query .= ", clientMultiplier = '" . $this->db->real_escape_string( $this->getClientMultiplier() ) . "'";

        $query .= ", invoiceNotes = '" . $this->db->real_escape_string( $this->getInvoiceNotes() ) . "'"; // Added for v2020-4

        if ($this->updateCommittedTime) {
            $query .= ", committedTime = now() ";
                        $query .= ", commitNotes = '" . $this->db->real_escape_string($this->getCommitNotes()) . "' ";
                        $query .= ", commitPersonId = " . intval($this->user->getUserId()) . " ";
        }

        $tot = 0;
        if (is_numeric($this->getTotal())) {
            $tot = $this->getTotal();
        }
        $query .= ", total = " . $this->db->real_escape_string($tot) . " ";

        // [BEGIN MARTIN COMMENT]
        // removed 2018-08-08 during trigger shit
        //$query .= " ,totalOverride = '" . $this->db->real_escape_string($this->getTotalOverride()) . "' ";
        // never tdo triggerTotal here as it is done through triggers
        // [END MARTIN COMMENT]

        if (intval($incrementEditCount)) {
            $query .= ", editCount = (editCount+1) ";
        }

        $query .= " where invoiceId = " . intval($this->getInvoiceId()) . " ";

        //	$this->updateCommittedTime = false; // Commented out by Martin some time before 2019
        // >>>00001 Joe is surprised to see it commented out, would have expected that to be cleared.

        $this->db->query($query);
    } // END public function save

    /* REMOVED for v2020-3, JM 2020-05-22. Replaced by the more specific functions that follow.
    // Get the latest status for this invoice.
    // (relying on a trick to get the most recent status: it assumes
    //  that the higher invoiceStatusTimeId always represents the more recent entry
    //  in invoiceStatusTime.)
    // RETURNs an associative array representing the full content of a row from the JOIN
    //  of these two tables: canonical representation of a DB row, except to the slightly
    //  weird thing of doing this on a JOIN with SELECT *.
    public function getStatusData() {
        $status = array();

        // >>>00022 SELECT * on a SQL JOIN, not really a good idea
        $query = "select * from " . DB__NEW_DATABASE . ".invoiceStatusTime ist ";
        $query .= " join " . DB__NEW_DATABASE . ".invoiceStatus s on ist.invoiceStatusId = s.invoiceStatusId ";
        $query .= " where invoiceId = " . intval($this->getInvoiceId()) . " ";
        $query .= " order by ist.invoiceStatusTimeId desc limit 1 ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $status = $row;
            }
        }

        return $status;
    }
    */

    // Added for v2020-3, JM 2020-05-22
    // RETURN the statusName for the relevant invoiceStatus
    // RETURN 'Problem getting status' on error
    public function getStatusName() {
        $query = "SELECT statusName FROM " . DB__NEW_DATABASE . ".invoiceStatus ";
        $query .= "WHERE invoiceStatusId = " . $this->invoiceStatusId . ";";
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1590171389', 'Hard DB error', $this->db);
            return 'Problem getting status';
        }
        $row = $result->fetch_assoc();
        return ($row?$row['statusName']:"Error Status");
    }

    // Added for v2020-3, JM 2020-05-22
    // RETURN a PHP Date Time object for the 'inserted' time of latest invoiceStatusTime
    // RETURNs false on error
    public function getStatusInserted() {
        // BEGIN ADDED 2020-08-20 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=111#c977
        if (!$this->invoiceStatusTimeId) {
            $this->logger->warn2('1597940139', "No invoiceStatusTimeId indicated for invoice " . $this->invoiceId . " (null or zero)");
            return false;
        }
        // END ADDED 2020-08-20 JM

        $query = "SELECT inserted FROM " . DB__NEW_DATABASE . ".invoiceStatusTime ";
        $query .= "WHERE invoiceStatusTimeId = " . $this->invoiceStatusTimeId . ";";
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1590171419', 'Hard DB error', $this->db);
            return false;
        }
        $row = $result->fetch_assoc();
        /* BEGIN REPLACED 2020-08-20 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=111#c977
        return DateTime::createFromFormat('Y-m-d H:i:s', $row['inserted']); // which will also return false on failure
        // END REPLACED 2020-08-20 JM
        */
        // BEGIN REPLACEMENT 2020-08-20 JM as part of addressing http://bt.dev2.ssseng.com/view.php?id=111#c977
        if ($row) {
            return DateTime::createFromFormat('Y-m-d H:i:s', $row['inserted']); // which will also return false on failure
        } else {
            $this->logger->warnDb('1597940358', "No invoiceStatusTime for invoiceStatusTimeId=" . $this->invoiceStatusTimeId .
                ", invoice " . $this->invoiceId, $this->db);
            return false;
        }
        // END REPLACEMENT 2020-08-20 JM
    }

    // Added for v2020-3, JM 2020-05-22
    // RETURN an array of all customerPersonIds associated with the current invoiceStatusTime
    // RETURNs false on error
    function getStatusCustomerPersonIds() {
        $query = "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".istCustomerPerson ";
        $query .= "WHERE invoiceStatusTimeId = " . $this->invoiceStatusTimeId . ";";
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1590185899', 'Hard DB error', $this->db);
            return false;
        }
        $customerPersonIds = Array();
        while ($row = $result->fetch_assoc()) {
            $customerPersonIds[] = $row['customerPersonId'];
        }
        return $customerPersonIds;
    }

    // Auxiliary function for setStatus.
    // Insert a row into DB table invoiceStatusTime; uses the passed arguments, plus current invoiceId and userId.
    // INPUT $invoiceStatusId, $note: correspond to columns in DB table invoiceStatusTime.
    // INPUT $customerPersonIds (formerly $extra) reworked 2020-05-22 JM for v2020-3.
    //  Used to use a bitflag approach (bad idea for several reasons, see http://sssengwiki.com/EORs%2C+stamps%2C+etc),
    //  now a customerPersonId or an array of customerPersonIds to associate with this status. As of 2020-05-22 these
    //  are expected to be managers, but for some statuses others might make sense, too.
    //  Ignored if not "truthy", so (for example) you can pass 0, null, or false rather than an empty array
    // RETURN true on success, false on failure.
    private function setInvoiceStatusTime($invoiceStatusId, $customerPersonIds, $note) {
        $note = trim($note);
        $note = substr($note, 0, 255); // >>>00002 truncates silently

        // BEGIN NEW CODE  2020-05-22 JM
        if ($customerPersonIds) {
            if (!is_array($customerPersonIds)) {
                $customerPersonIds = Array($customerPersonIds);
            }
            foreach ($customerPersonIds AS $customerPersonId) {
                if (!CustomerPerson::validate($customerPersonId)) {
                    $this->logger->error2('1590178388', "Invalid customerPersonId $customerPersonId");
                    return false;
                }
            }
        }

        $query = "START TRANSACTION;";
        $result = $this->db->query($query);
        if (!$result)  {
            $this->logger->errorDb('1590178392', "Hard error", $this->db);
            return false;
        }

        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceStatusTime(";
        $query .= "invoiceStatusId, invoiceId, personId, note ";
        //REMOVED 2020-08-10 JM for v2020-4 //$query .= "extra";  // vestigial in v2020-3
        $query .= ") VALUES (";
        $query .= intval($invoiceStatusId);
        $query .= ", " . intval($this->getInvoiceId());
        $query .= ", " . intval($this->getUser()->getUserId());
        $query .= ", '" . $this->db->real_escape_string($note) . "'";
        //REMOVED 2020-08-10 JM for v2020-4 //$query .= ", 0";  // 'extra' vestigial in v2020-3
        $query .= ");";

        $result = $this->db->query($query);
        if (!$result)  {
            $this->logger->errorDb('1590178394', "Hard error", $this->db);
            $query = "ROLLBACK;";
            $this->db->query($query); // no point to looking at the result, nothing we can do to recover if this fails
            return false;
        }

        $invoiceStatusTimeId = $this->db->insert_id;

        if ($customerPersonIds) {
            foreach($customerPersonIds as $customerPersonId) {
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".istCustomerPerson (";
                $query .= "invoiceStatusTimeId, customerPersonId";
                $query .= ") VALUES (";
                $query .= "$invoiceStatusTimeId, $customerPersonId";
                $query .= ");";

                $result = $this->db->query($query);
                if (!$result)  {
                    $this->logger->errorDb('1590178414', "Hard error", $this->db);
                    $query = "ROLLBACK;";
                    $this->db->query($query); // no point to looking at the result, nothing we can do to recover if this fails
                    return false;
                }
            }
        }

        $query = "COMMIT;";
        $result = $this->db->query($query);
        if (!$result)  {
            $this->logger->errorDb('1590178532', "Hard error", $this->db);
            $query = "ROLLBACK;";
            $this->db->query($query); // no point to looking at the result, nothing we can do to recover if this fails
            return false;
        }
        return true;

        // END NEW CODE  2020-05-22 JM

        /* OLD CODE REPLACED 2020-05-22 JM
        $query = " insert into " . DB__NEW_DATABASE . ".invoiceStatusTime(invoiceStatusId, invoiceId, extra,personId,note) values(";
        $query .= " " . intval($invoiceStatusId) . " ";
        $query .= " ," . intval($this->getInvoiceId()) . " ";
        $query .= " ," . intval($extra) . " ";
        $query .= " ," . intval($this->getUser()->getUserId()) . " ";
        $query .= " ,'" . $this->db->real_escape_string($note) . "') ";

        $this->db->query($query);
        */
    } // END private function setInvoiceStatusTime

    // Validates that $invoiceStatusId can be found in DB table invoiceStatus, fails if not.
    // Then calls setInvoiceStatusTime to insert a row into DB table invoiceStatusTime.
    // INPUT $invoiceStatusId, $note: correspond to columns in DB table invoiceStatusTime.
    // INPUT $customerPersons (formerly $extra) reworked 2020-05-22 JM for v2020-3.
    //  Used to use a bitflag approach (bad idea for several reasons, see http://sssengwiki.com/EORs%2C+stamps%2C+etc),
    //  now an array of customerPersons to associate with this status. As of 2020-05-22 these
    //  are expected to be managers, but for some statuses others might make sense, too.
    //  Ignored if not "truthy", so (for example) you can pass 0, null, or false rather than an empty array
    // RETURN true on success, false on failure.
    public function setStatus($invoiceStatusId, $customerPersonIds, $note) {
        // BEGIN NEW CODE  2020-05-22 JM
        if (self::validateInvoiceStatus($invoiceStatusId)) {
            return $this->setInvoiceStatusTime($invoiceStatusId, $customerPersonIds, $note);
        } else {
            $this->logger->error2('1593545003', "Invoice::setStatus called with invalid invoiceStatusId $invoiceStatusId");
            return false;
        }
        // END NEW CODE  2020-05-22 JM

        /* OLD CODE REPLACED 2020-05-22 JM
        if (intval($invoiceStatusId)) { // 00027 Could just use "select invoiceStatusId", that's all we use.
            $query  = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".invoiceStatus ";
            $query .= " where invoiceStatusId = " . intval($invoiceStatusId);

            $workOrderStatusId = null; // 00026 this is set and never used, I (JM) strongly suspect
                                       // Martin intended $invoiceStatusId = null.

            if ($result = $this->db->query($query)) { // 00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $invoiceStatusId = $row['invoiceStatusId'];
                }
            }

            // 00026: the way this is written as of 2019-02, I (JM) don't think the following test ever fails.
            //  This is why I conjecture that above Martin intended $invoiceStatusId = null.
            if (intval($invoiceStatusId)) {
                $this->setInvoiceStatusTime($invoiceStatusId, $extra, $note);
            } // 00002 else $invoiceStatusId is invalid, ought to log.
        }
        */
    } // END public function setStatus

    /*
	// This method was moved into the base class.
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance();
	    }
	}*/

    // On success, RETURNs an array of invoice statuses that indicate that the invoice has not been sent.
    //  These are primary keys in DB table InvoiceStatus.
    // RETURNS false on failure.
    public static function getNonSentInvoiceStatuses() {
        global $db, $logger;
        Invoice::loadDB($db);

        $query = "SELECT invoiceStatusId FROM invoiceStatus ";
        $query .= "WHERE sent=0;";

        $result = $db->query($query);
        if (!$result)  {
            $logger->errorDb('1589997324', "Hard DB error", $db);
            return false;
        } else {
            $ids = Array();
            while ($row = $result->fetch_assoc()) {
                $ids[] = $row['invoiceStatusId'];
            }
            return $ids;
        }
    }

    // On success, RETURNs a comma-separated string of invoice statuses that indicate that the invoice has not been sent.
    //  These are primary keys in DB table InvoiceStatus.
    // RETURNS false on failure.
    public static function getNonSentInvoiceStatusesAsString() {
        $ids = self::getNonSentInvoiceStatuses();
        if ($ids === false) {
            return false;
        }

        $idstring = '';
        foreach ($ids as $id) {
            if (strlen($idstring)) {
                $idstring .= ',';
            }
            $idstring .= $id;
        }

        return $idstring;
    }

    // INPUT $uniqueName: uniqueName of an invoiceStatus
    // RETURN (on success:) corresponding invoiceStatusId
    //        (on failure:) false
    public static function getInvoiceStatusIdFromUniqueName($uniqueName) {
        global $db, $logger;
        Invoice::loadDB($db);

        $query = "SELECT invoiceStatusId FROM invoiceStatus ";
        $query .= "WHERE uniqueName='" . $db->real_escape_string($uniqueName) . "';";

        $result = $db->query($query);

        if (!$result) {
            $logger->errorDb('1590079026', "Hard DB error", $db);
            return false;
        } else if ($result->num_rows == 0) {
            $logger->errorDb('1590079028', "No match for uniquename", $db);
            return false;
        } else if ($result->num_rows > 1) {
            $logger->errorDb('1590079030', "Multiple matches for uniquename, bad data", $db);
            return false;
        } else {
            $row = $result->fetch_assoc();
            return $row['invoiceStatusId'];
        }
    }

    // Return true if the id is a valid invoiceId, false if not
    // INPUT $invoiceId: invoiceId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($invoiceId, $unique_error_id=null) {
        global $db, $logger;
        Invoice::loadDB($db);

        $ret = false;
        $query = "SELECT invoiceId FROM " . DB__NEW_DATABASE . ".invoice WHERE invoiceId=$invoiceId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578691800', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }

    // Return true if the id is a valid invoiceStatusId, false if not
    // INPUT $invoiceStatusId: invoiceId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validateInvoiceStatus($invoiceStatusId, $unique_error_id=null) {
        global $db, $logger;
        Invoice::loadDB($db);

        $ret = false;
        $query = "SELECT invoiceStatusId FROM " . DB__NEW_DATABASE . ".invoiceStatus WHERE invoiceStatusId=$invoiceStatusId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1590177582', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>