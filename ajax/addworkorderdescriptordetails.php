<?php
/*  ajax/addworkorderdescriptordetails.php
    
    INPUT:
        * $_REQUEST['workOrderId']: primary key in DB table workOrder

    From the input workorder, this code successively drills down through all  
    related tasks, elements, descriptors (before 2020-01-02, this was decriptorSubs,
    now descriptor2s), details. We form an array of 
    detailRevisionIds, using table descriptorSubDetail.
    
    For each of these detailRevisionIds, if we don't already have a row 
    in table workorderDetail for this workOrderId, insert it, using 
    personId from current user. 
    
    No explicit return.
    
    Acts only if workOrderId is valid (row exists in workOrder table). 
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (existWorkOrderId($workOrderId)) {
    $wo = new WorkOrder($workOrderId);
    if (intval($wo->getWorkOrderId())) {        
        $workordertasks = $wo->getWorkOrderTasksRaw();
        
        foreach ($workordertasks as $wot) {
            $workOrderTaskElements = $wot->getWorkOrderTaskElements(); // These are things like a building, vault, etc.
            
            /* REPLACED 2020-01-02 JM
            foreach ($workOrderTaskElements as $workOrderTaskElement) {               
                $descriptors = $workOrderTaskElement->getDescriptors(1);
                foreach ($descriptors as $descriptor) {
                    $dsid = intval($descriptor['descriptorSubId']);
                    
                    // Down to descriptorSubId, get details.
                    if (intval($dsid)) {                        
                        $details = array();                        
                        $query = " select * from " . DB__NEW_DATABASE . ".descriptorSubDetail ";
                        $query .= " where descriptorSubId = " . intval($dsid);
                        $result = $db->query($query);
                        if(!$result){
                            //Logging
                        }else {
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {                                    
                                    $details[] = $row;                                    
                                }                                
                            }                            
                        }      
                        
                        foreach ($details as $detail) {                            
                            $exists = false;
                            $query = " select workOrderDetailId from " . DB__NEW_DATABASE . ".workOrderDetail where workOrderId = " . intval($workOrderId) . " and detailRevisionId = " . intval($detail['detailRevisionId']);
                            $result = $db->query($query);
                            if(!$result){
                            }else{
                                if ($result->num_rows > 0) {
                                    $exists = true;
                                }
                            }
                                
                            if (!$exists) {                                    
                                $query = "insert into " . DB__NEW_DATABASE . ".workOrderDetail (workOrderId, detailRevisionId, personId) values (";
                                $query .= " " . intval($workOrderId) . " ";
                                $query .= " ," . intval($detail['detailRevisionId']) . " ";
                                $query .= " ," . intval($user->getUserId()) . " ";
                                $query .= ") ";
                                    
                                $db->query($query);                                    
                            }                                
                        }
                    }
                }
            }
            */
            // BEGIN REPLACEMENT 2020-01-02 JM
            foreach ($workOrderTaskElements as $element) {               
                $descriptors = $element->getDescriptor2s();
                
                foreach ($descriptors as $descriptor2Id) {                    
                    // Get details associated with that descriptor2
                    $details = array();                        
                    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".descriptorSubDetail ";
                    $query .= "WHERE descriptor2Id=$descriptor2Id;";
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1578008577', 'Hard DB error', $this->db);
                    } else {
                        while ($row = $result->fetch_assoc()) {                                    
                            $details[] = $row;                   
                        }                            
                    }      
                    
                    foreach ($details as $detail) {                            
                        $query = "SELECT workOrderDetailId FROM " . DB__NEW_DATABASE . ".workOrderDetail ";
                        $query .= "WHERE workOrderId = " . intval($workOrderId) . " ";
                        $query .= "AND detailRevisionId = " . intval($detail['detailRevisionId']). ";";
                        $result = $db->query($query);
                        if (!$result) {
                            $logger->errorDb('1578008588', 'Hard DB error', $this->db);
                        } else {
                            if ($result->num_rows == 0) {
                                $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderDetail (workOrderId, detailRevisionId, personId) VALUES (";
                                $query .= intval($workOrderId);
                                $query .= ", " . intval($detail['detailRevisionId']);
                                $query .= ", " . intval($user->getUserId());
                                $query .= ");";
                                    
                                $result = $db->query($query);
                                if (!$result) {
                                    $logger->errorDb('1578008634', 'Hard DB error', $this->db);
                                }
                            }
                        }                                
                    }
                }
            }            
            // END REPLACEMENT 2020-01-02 JM            
        }
    }
}  // END if (existWorkOrderId($workOrderId)) 
?>