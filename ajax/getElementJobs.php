<?php 



    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    // Select jobId based on the unique jobNumber.
    $elementName = isset($_REQUEST['elementName']) ? trim($_REQUEST['elementName']) : "";
   
    if (strlen($elementName)) {  
    	$query="
			SELECT j.jobId, j.number, j.name, js.jobStatusName, j.jobStatusId, l.address1, l.city, l.state, l.postalCode, l.state, l.latitude, l.longitude, getJobElements(j.jobId) AS elem
			FROM job j LEFT JOIN 
			    location l ON j.locationId=l.locationId INNER join
			    jobStatus js ON js.jobStatusId=j.jobStatusId
			WHERE jobId IN (SELECT DISTINCT jobId FROM element WHERE SOUNDEX(elementName)=SOUNDEX('$elementName'))";

        
        $result = $db->query($query);
        $output=[];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $output[] = $row;
            }
            $data['data']=$output;
        } else {
            $error = "Query Error. Database Error";
            $logger->errorDb('6376109496157552131', $error, $db);
            $data['error'] = "ajax/getElementJobs.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
     

    }

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();


 ?>




