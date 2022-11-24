<?php
/* _admin/contractlanguage/index.php

    Top-level page for _admin/contractlanguage. If directory ../ssseng_documents/contract_language
    doesn't exist, (or equivalent for other customer besides ssseng) returns a "403 Forbidden" with an appropriate message.

    EXECUTIVE SUMMARY: 
        * Page to give a report of the system contract languanges based on status and type. 
        * Possible Actions:
            * act = setactive. 
                A checkbox that will set the current contract languange active for each type.
                There can be only one current contract languange active for each type.
            * act = deleatelanguage.
                Each contract languange type is checked if is used in a billing profile. If not then the action 'delete'
                can be done, else the 'del' button is disabled.
                If the checked contract languange is deleted, the las uploaded contract languange will be set as 'active'.
            

    NO INPUT.
*/

include '../../inc/config.php';

$db = DB::getInstance();
$error = "";
$errorId = 0;
$error_is_db = false;

// BEGIN NEW CODE 2019-02-06 JM
$fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/contract_language/';
// END NEW CODE 2019-02-06 JM	

if (!file_exists($fileDir)){
	header('HTTP/1.0 403 Forbidden');
	echo "Save Dir Doesn't Exist";
	die();
}

$files = array();

if (is_dir($fileDir)){
	if ($dh = opendir($fileDir)){
		while (($file = readdir($dh)) !== false){
			if (is_file($fileDir . $file)){
				$files[] = $file;
			}
		}
		closedir($dh);
	}
}

sort($files);

include "../../includes/header_admin.php";

?>
<script>
    var setActive = function(contractLanguageId, type) {

        window.location.href = "/_admin/contractlanguage/index.php?act=setactive&contractLanguageId=" + escape(contractLanguageId) +"&type=" + escape(type) + " ";
    }



</script>

<?php
$contractLanguages = getContractLanguageFiles($error_is_db);
if ($error_is_db) { //true on query failed.
    $errorId = '637813919257423795';
    $error =  "We could not display the Contract Language Files. Database Error. Error Id: " . $errorId; // message for User
    $logger->errorDB($errorId, "getContractLanguageFiles() function failed", $db);
}


//The following is typically triggered by a call to local function setActive, 
// which effectively self-submits. Sets contractLanguage to active.
if ($act == 'setactive') {
    
    $contractLanguageId = isset($_REQUEST['contractLanguageId']) ? intval($_REQUEST['contractLanguageId']) : 0;
    $type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
    $type = strtoupper($type);


    // make all inactive for this type
    $query = " UPDATE " . DB__NEW_DATABASE . ".contractLanguage ";
    $query .= " SET status = 0 ";
    $query .= " WHERE type = '" . $db->real_escape_string($type) . "' ";

    $result = $db->query($query);
    if (!$result) {
        $errorId = '637812974302142834';
        $error= 'We could not update the Contract Language Status. Database error. Error id: ' . $errorId;
        $logger->errorDb($errorId, 'We could not update the Contract Language Status', $db);
    }

    if(!$error) {
    
        // make the contractLanguageId from REQUEST active
        $query = " UPDATE " . DB__NEW_DATABASE . ".contractLanguage ";
        $query .= " SET status = 1 ";
        $query .= " WHERE contractLanguageId = " . intval($contractLanguageId) .  " ";
        $query .= " AND type = '" . $db->real_escape_string($type) . "' ";

        $result = $db->query($query);
        if (!$result) {
            $errorId = '637812971552160707';
            $error= 'We could not update the Contract Language Status. Database error. Error id: ' . $errorId;
            $logger->errorDb($errorId, 'We could not update the Contract Language Status', $db);
        }

    }

    if(!$error) {
         header('Location: '.$_SERVER['PHP_SELF']);
         die();
    }


}

if ($act == 'deletelanguage') { 

    $contractLanguageId = isset($_REQUEST['contractLanguageId']) ? intval($_REQUEST['contractLanguageId']) : 0;
    $status = isset($_REQUEST['status']) ? intval($_REQUEST['status']) : 0;
    $type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
    $type = strtoupper($type);

    if($status == 1) { // active
        $query = " UPDATE " . DB__NEW_DATABASE . ".contractLanguage ";
        $query .= " SET status = 0 ";
        $query .= " WHERE type = '" . $db->real_escape_string($type) . "' ";

        $result = $db->query($query);

        if (!$result) {
            $errorId = '637813114103385162';
            $error= 'We could not update the Contract Language Status. Database error. Error id: ' . $errorId;
            $logger->errorDb($errorId, 'We could not update the Contract Language Status', $db);
        }

        if(!$error) {
            // delete
            $query = " DELETE FROM " . DB__NEW_DATABASE . ".contractLanguage " ;
            $query .= " WHERE contractLanguageId =" . intval($contractLanguageId) . " ";
       
            
            $result = $db->query($query);

            if (!$result) {
                $errorId = '637813124380013100';
                $error= 'We could not delete Contract Language. Database error. Error id: ' . $errorId;
                $logger->errorDb($errorId, 'We could not delete Contract Language', $db);
            }
        }

        // after delete an ctr lang with active status
        // select the max id from this type and set it to active.
        if(!$error) { 
            $query  = "SELECT MAX(contractLanguageId) as contractLanguageId FROM " . DB__NEW_DATABASE . ".contractLanguage ";
            $query .= " WHERE type = '" . $db->real_escape_string($type) . "' ";

           
            $result = $db->query($query);
            if (!$result) {
                $errorId = '637813117558855110';
                $error= 'We could not get the Contract Language data. Database error. Error id: ' . $errorId;
                $logger->errorDb($errorId, 'We could not get the Contract Language data', $db);
            }
            

            $row = $result->fetch_assoc();
            if(count($row) == 1) {
                $query = " UPDATE " . DB__NEW_DATABASE . ".contractLanguage ";
                $query .= " SET status = 1 ";
                $query .= " WHERE contractLanguageId = " . intval($row['contractLanguageId']) .  " ";

                $result = $db->query($query);

                if (!$result) {
                    $errorId = '637813122060806926';
                    $error= 'We could not update the Contract Language Status. Database error. Error id: ' . $errorId;
                    $logger->errorDb($errorId, 'We could not update the Contract Language Status', $db);
                }
            }
         

        } 
        // end set active
    } else {
        // delete the contractLanguageId entry
        $query = " DELETE FROM " . DB__NEW_DATABASE . ".contractLanguage " .
        $query .= " WHERE contractLanguageId =" . intval($contractLanguageId) . " ";

        
        $result = $db->query($query);

        if (!$result) {
            $errorId = '637813126651068625';
            $error= 'We could not delete Contract Language. Database error. Error id: ' . $errorId;
            $logger->errorDb($errorId, 'We could not delete Contract Language', $db);
        }
    }

 

    if(!$error) {
        header('Location: '.$_SERVER['PHP_SELF']);
        die();
    }
    
    
}
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}


// One-column table, each row of which displays a filename from contract_language directory, 
// linked to getuploadfile.php, passing in that file in the query string. getuploadfile.php
// downloads the file
echo '<div style="padding:50px;" >';
echo '<form method="post" name="contractChecked" action="">';
echo '<table style="width:60%;"  class="table table-sm" cellpadding="4" cellspacing="2">';
?>
    <tr>
        <th>Action</th>
        <th>Type</th>
        <th>Contract lang. Name</th>
        <th>Date</th>
        <th>Status</th>
        <th style="text-align: right">&nbsp;&nbsp;Delete</th>
    </tr>
<?php
    foreach ($contractLanguages as $key=>$file) {
    
        $fileType = 0;
        $bgColor = "#fff";
        $textWeight = 400;
        $textColor= "#000";
 

        if(  $file['type'] == 'LL' || $file['type'] == 'TYPE_IV') {
            $bgColor = "#f8f8f8";
        }
    echo '<tr style="background-color:'.$bgColor.'">';
        $checked = (intval($file["status"]) == 1) ? ' checked ':'';
        if($file["status"] == 1) {
            $status = "Active";
            $textWeight = 700;
            $textColor  = "#00c02c";
        } else {
            $status = "Inactive";
        }
        $type = $file["type"];

        //echo '<tr><td><input type="checkbox"  name="contractChecked" '.($file["type"] == 1 ? "checked" : "").'  value='.$file["contractLanguageId"].'  > </td>
        echo '<td><input onClick="setActive(' . intval($file["contractLanguageId"]) . ', \'' . $type . '\')" type="checkbox" id="current' .$file["contractLanguageId"] . '"  name="contractLanguageActive" value="' . $file["contractLanguageId"] . '" ' . $checked . '></td>
        <td> ' .$file['type'] . ' </td>
        <td><a href="getuploadfile.php?f=' . rawurlencode($file['fileName']) . '">' . $file['fileName'] . '</a></td>';
        
        echo '<td> '. DateTime::createFromFormat("Y-m-d H:i:s", $file['inserted'])->format("d/m/Y").'</td>';
        echo '<td style="color:'.$textColor.'; font-weight:'.$textWeight.'">' .$status .'</td>';
        echo '<td style="text-align: right">';
        if ( canDelete("contractLanguage", "contractLanguageId", intval($file["contractLanguageId"] ))) {
            echo '[<a   id="deleteLanguage' . intval($file["contractLanguageId"] ) . '" href="/_admin/contractlanguage/?&act=deletelanguage&contractLanguageId=' . intval($file["contractLanguageId"]) .'&status=' . intval($file["status"]) .'&type=' . $type .'">del</a>]';
        }else {
            echo '[<a onclick="return false;" class="disabled" id="deleteLanguage' . intval($file["contractLanguageId"] ) . '">del</a>]';
        }
        echo '<td>';
        echo '</tr>';
    echo '<tr class="space_tr2"></tr>  ' ;
    }

    
   
echo '</table>';
echo '</form>';
?>
    <style>
        .space_tr {
            height:8px;
        }
        .space_tr2 {
            height:12px;
        }
        .table-sm td, .table-sm th {
            padding: 0.4rem;
            padding-left: 1em;
        }
    </style>
    <hr>

    <?php /* form to upload a new contract language file */ 

    // contract language type
    $contractLanguagesTypes = [];
    $contractLanguagesTypes = array_unique(array_column($contractLanguages, 'type'));
    
    ?>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <table>
            <tr>
                <td>Display Name</td>
                <td><input type="text" name="displayName" class="form-control form-control-sm" size="40" maxlength="255"></td>
            </tr> 
            <tr class="space_tr"></tr>  
            <tr>
               <td> <label for="type">Choose a Type:</label> </td>

               <td>
                    <select class="form-control form-control-sm" name="type" id="type" required>
                    <?php
                     foreach ($contractLanguagesTypes as $val) {
                        echo '<option  value="' . $val . '">' . $val . '</option>';

                    }
                    
                    ?>
                    <option  disabled >──────────</option>
                    <option value="new"  id="new_categ" >New Type</option>
                    </select>
                </td> 
            </tr>
            <tr class="space_tr"></tr>
            <tr class="space_tr"></tr>
            <tr>
                <td>Select file to upload:</td>
                <td><input type="file" name="file" id="file"></td>
            </tr>
            <tr class="space_tr"></tr>
            <tr>
                <td colspan="2"><input type="submit"  class="btn btn-secondary  btn-sm" value="Upload File" name="submit"></td>
            </tr>
        </table>
    </form>
</div>
<script>
        $(document).ready(function() {   // new category


            $('#type').on('change', function (event) {

                if ($(this).find('option:selected').val() === 'new') {
                    alert("The name of the new type will be taken from the PDF parenthesis: pdf_file_name_example(NEW_TYPE).pdf");
                }
            });

        });

</script>
<?php
include "../../includes/footer_admin.php";
?>