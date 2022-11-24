<?php 
/* _admin/ajax/changedescriptor2displayorder.php
    
    Used by _admin/descriptor/descriptor2.php to modify displayOrder in the Descriptor2 hierarchy.
    
    PRIMARY INPUTs
    * $_REQUEST['descriptor2IdToMove'] - ID of descriptor2 whose displayOrder we are changing  
    * $_REQUEST['before'] - 1 or 0, effectively Boolean. 
        * 1 means we are inserting BEFORE the sibling descriptor2 that currently has display order $_REQUEST['relativeTo']
          It also means that the sibling that we are inserting before has a smaller displayOrder than descriptor2IdToMove, because
          that's how we use this.
        * 0 means we are inserting AFTER the sibling descriptor2 that currently has display order $_REQUEST['relativeTo']
          It also means that the sibling that we are inserting after has a larger displayOrder than descriptor2IdToMove, because
          that's how we use this.
    * $_REQUEST['relativeTo'] - current displayOrder of the descriptor2 we've said to place it immediately before or immediately after.

    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      
    // >>>00002: Cristi will probably want to revisit validation of inputs, I've done something quick & ad hoc for the moment; I think
    //  I'm at least close - JM 2019-12-31
    
*/

include '../../inc/config.php';
include '../../inc/access.php';

$v=new Validator2($_REQUEST);

$v->rule('required', ['descriptor2IdToMove', 'before', 'relativeTo']);
$v->rule('integer', ['descriptor2IdToMove', 'before', 'relativeTo']);
$v->rule('min', 'descriptor2IdToMove', 1);
$v->rule('min', 'before', 0);
$v->rule('max', 'before', 1);
$v->rule('min', 'relativeTo', 0);

if(!$v->validate()){
    $logger->error2('1577752634', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$changedDB = false;

$descriptor2Id = $_REQUEST['descriptor2IdToMove'];  
$before = !! $_REQUEST['before'];
$relativeTo = $_REQUEST['relativeTo'];

if ( ! Descriptor2::validate($descriptor2Id) ) {
    $data['error'] = "Invalid descriptor2Id $descriptor2Id";
}

if ( ! $data['error'] ) {
    $descriptor2 = new Descriptor2($descriptor2Id);
    if ($descriptor2) {
        $oldDisplayOrder = $descriptor2->getDisplayOrder();
    } else {
        $data['error'] = "failed to build Descriptor2($descriptor2Id)";
        $logger->error2('1577752638', $data['error']);
    }
    unset($descriptor2);
}

if ( ! $data['error'] ) {
    if ($before && $relativeTo > $oldDisplayOrder) {
        $data['error'] = "using 'before', new position $relativeTo must be less than old $oldDisplayOrder, but it's not"; 
        $logger->error2('1577822268', $data['error']);
    } else if (!$before && $relativeTo < $oldDisplayOrder) {
        $data['error'] = "using 'after', new position $relativeTo must be greater than old $oldDisplayOrder, but it's not"; 
        $logger->error2('1577822298', $data['error']);
    }
}

if ( ! $data['error'] ) {
    $query = 'START TRANSACTION;';
    $result = $db->query($query);
    if (!$result)  {
        $data['error'] = "Hard error on $query"; 
        $logger->errorDb('1577752639', $data['error'], $db);        
    }
}

if ( ! $data['error'] ) {
    $query = "SELECT parentId FROM " . DB__NEW_DATABASE . ".descriptor2 WHERE descriptor2Id=$descriptor2Id;";
    $result = $db->query($query);
    if ( $result )  {
        if ($result->num_rows == 0) {
            $data['error'] = "Can't get parent for descriptor2Id=$descriptor2Id";
            $logger->errorDb('1577752640', $data['error'], $this->db);
        } else {
            $row = $result->fetch_assoc();
            $parentId = $row['parentId']; 
        }        
    } else {
        $data['error'] = "Hard error"; 
        $logger->errorDb('1577752642', $data['error'], $db);
    }
}
    
if ( ! $data['error'] ) {
    if ($before) {
        $query = "UPDATE " . DB__NEW_DATABASE . ".descriptor2 " ;
        $query .= "SET displayOrder = displayOrder+1 "; 
        $query .= "WHERE parentId=$parentId ";
        $query .= "AND displayOrder >= $relativeTo ";
        $query .= "AND displayOrder < $oldDisplayOrder;";
    } else {
        $query = "UPDATE " . DB__NEW_DATABASE . ".descriptor2 " ;
        $query .= "SET displayOrder = displayOrder-1 "; 
        $query .= "WHERE parentId=$parentId ";
        $query .= "AND displayOrder > $oldDisplayOrder ";
        $query .= "AND displayOrder <= $relativeTo;";
    }
    
    $result = $db->query($query);
    if ($result)  {
        $changedDB = true;
    } else {
        $data['error'] = "Hard error"; 
        $logger->errorDb('1577752654', $data['error'], $db);
    }
}

if ( ! $data['error'] ) {
    $query = "UPDATE " . DB__NEW_DATABASE . ".descriptor2 " ;
    $query .= "SET displayOrder = $relativeTo "; 
    $query .= "WHERE descriptor2Id=$descriptor2Id;";
    
    $result = $db->query($query);
    if ( ! $result )  {
        $data['error'] = "Hard error"; 
        $logger->errorDb('1577752661', $data['error'], $db);
    }
}

if ($changedDB) { 
    if ( $data['error'] ) {
        $query = "ROLLBACK;";
        
        $result = $db->query($query);
        if (!$result)  {
            $logger->errorDb('1577752699', 'ROLLBACK failed', $db);
        }
    } else {
        $query = "COMMIT;";
        
        $result = $db->query($query);
        if (!$result)  {
            $data['error'] = 'COMMIT failed';
            $logger->errorDb('1577752734', $data['error'], $db);
        }
    }
}

if ( ! $data['error'] ) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>
