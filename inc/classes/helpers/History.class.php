<?php
/* History.class.php

EXECUTIVE SUMMARY: Manages a History table that lets us keep old values of something.
As of 2019-03, JM believes the only place we are using this is so that we don't completely
throw away deleted Notes.
>>>00001 JM believes this is something Martin started & didn't really follow up.

* Public methods:
** __construct($entityId, $thingId)
** add($historyTypeId, $data)
*/

class History {
	private $db;
    private $entityId;
    private $thingId;
	
    // >>>00001: a bit of a mystery what is the intent of the inputs here.
    // Only existing call is in inc/SSSEng.class.com: new History($this->user->getUserId(), intval($noteId)).
    // I (JM) am not sure that makes the intent of $entityId clear, or even if that is really the correct
    // choice of entityId in that call; $thingId is obviously an ID of the  
    // thing for which we are tracking an old value, but presumably makes sense only in the context of 
    // $historyTypeId passed later to public function add so >>>00026: I (JM 2019-03-06) suspect this
    // all is work in progress, not really followed through 
	public function __construct($entityId, $thingId) {
		$this->entityId = $entityId;
		$this->thingId = $thingId;
		$this->db = DB::getInstance();
		
	}

	// INPUT $historyTypeId: from inc/config.php, as of 2019-03 only defined value is HISTORY_DELETE_NOTE
	// INPUT $data: the old value being backed up. Can be a string or an array; the latter will be serialized.
	//  (For that matter, a number will work fine, but will be treated like a string).
	//  Currently only use is old text of a note.
	public function add($historyTypeId, $data) {
		if (is_array($data)){
			$store = serialize($data);
		} else {
			$store = $data;
		}
		
		$query = " insert into " . DB__NEW_DATABASE . ".history (historyTypeId, entityId, thingId, data) values (";
		$query .= " " . intval($historyTypeId) . " ";
		$query .= " ," . intval($this->entityId) . " ";
		$query .= " ," . intval($this->thingId) . " ";
		$query .= " ,'" . $this->db->real_escape_string($store) . "') ";
		
		$this->db->query($query); // >>>00002 NOTE that nothing is logged on DB failure		
		
	}	
}

?>