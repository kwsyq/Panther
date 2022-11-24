<?php
/*  ajax/update_quantity_cost_wot.php

    Usage: on contract page.
    Updates the qty, cost and total cost for each workorderTask.
    After wot update, we update the total cost for Level One wot ( the parent ).

    POSSIBLE INPUT VALUES:
        * $_REQUEST['quantity'],
        * $_REQUEST['cost'],
        * $_REQUEST['workOrderTaskId'],
        * $_REQUEST['updateCost'].

    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error with errorId ),
        * 'status': "success" on successful query.
        * $data["totCost"]: total cost of the children wot.
        * $data['sum'] : total cost of Level One wot.
        * $data['workOrderTaskIdOne'] : workOrderTaskId of Level One wot.

*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $data['status'] = 'fail';
    $data['error'] = '';
    $data['errorId'] = '';
    $data=array();
    // update quantity or cost for this workOrderTaskId
    $invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;
    $data2[4]  = json_decode(isset($_REQUEST['data']) ? $_REQUEST['data'] : []); // for cost

    $data2Str=str_replace("\\", "", json_encode($data2));
    $data2Str=str_replace("'", "''", $data2Str);
    foreach($data2[4] as $task){

        if($task->parentId!==null){
            $query="update workOrderTask set cost=".$task->cost.", quantity=".$task->quantity.", totCost=".$task->totCost." where workOrderTaskId=".$task->workOrderTaskId;
            $db->query($query);
        }
    }
    $query="update invoice set data2='".$data2Str."' where invoiceId=".$invoiceId;
    $result=$db->query($query);

$invoice=new Invoice($invoiceId);
$invoice->getInvoiceTotal();

//$invoice->update(ara);



    $data['status']="success";
    $data['result']=$query;
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>