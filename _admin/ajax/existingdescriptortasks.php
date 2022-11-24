<?php 
/*  _admin/ajax/existingdescriptortasks.php

    EXECUTIVE SUMMARY: Return all rows from DB table descriptorSubTasks for a given descriptor2; 
        also returns task descriptions 

    INPUT $_REQUEST['descriptor2Id']: primary key to DB table Descriptor2.
        
    Returns JSON for an associative array with the following members:
        * 'status': 'success'  or 'fail'
        * 'error': only relevant on failure. Error message.
        * 'tasks': Only relevant on success. A (possibly empty) array of associative arrays, corresponding to rows in DB table descriptorSubTask 
           with this descriptor2Id. Content of each associative array corresponds to all of the columns of DB table 
           descriptorSubTask (which is just 'descriptorSubTaskId', 'descriptorSubId' (deprecated as of 2019-12), 'descriptor2Id' (introduced 2019-12), 
           'taskId') plus the relevant task.description. Ordered by descriptorSubTaskId, which is effectively chronological.
            
    >>>00001 JM note to Cristi, which can be removed once input validation & DB returns are properly dealt with: 
             I added descriptor2Id, and did some cleanup that entailed, but there is doubtless more to do here.
*/    

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['task'] = array();
$data['error'] = ''; 
$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;

if (!$descriptor2Id) {
    $data['error'] = '_admin/ajax/existingdescriptortasks.php must have descriptor2Id';
    return $data;
}

$db = DB::getInstance();

/* In the following, 'order by dst.descriptorSubTaskId' will effectively make this forward chronological
   by the time the descriptor2 was associated with the task in question, since these IDs are assigned
   in monotonically increasing order. */
$query = " select  ";
$query .= " dst.*, t.description ";
$query .= " from  " . DB__NEW_DATABASE . ".descriptorSubTask dst  ";
$query .= " join  " . DB__NEW_DATABASE . ".task t on dst.taskId = t.taskId ";
$query .= " where dst.descriptor2Id = " . intval($descriptor2Id);
$query .= " order by dst.descriptorSubTaskId ";

$tasks = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
	if ($result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {
			$tasks[] = $row;
		}
	}
} // >>>00002 ignores failure on DB query!

$data['status'] = 'success';
$data['tasks'] = $tasks;

header('Content-Type: application/json');
echo json_encode($data);
die();

?>