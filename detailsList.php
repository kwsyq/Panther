<?php

require_once "../inc/config.php";

function formatDate($in){

    if($in==''){
        return '';
    }
    return substr($in, 5, 2)."/".substr($in, 8, 2)."/".substr($in, 0, 4);

}

$db=DB::getInstance();

$db->select_db("detailnew");

$result=$db->query("SELECT d.detailId, d.parentId, d.name, getDetailFullName(d.detailId) as extendedName,  d.parseName, dr.detailRevisionId, dr.detailRevisionStatusTypeId, dr.code, dr.caption, dr.approved, dr.dateBegin, dr.dateEnd FROM detail d, detailRevision dr WHERE d.detailId=dr.detailId order by extendedName");

$res=$db->query("select  *, getDetailFullName(dr.detailId) as fullName, count(t.detailRevisionId) as typeCount  from detailRevisionTypeItem t 
    join detailRevision dr on t.detailRevisionId = dr.detailRevisionId 
    where dr.approved = 1 		    	    
    group by t.detailRevisionId, concat(t.detailMaterialId,'-',t.detailComponentId,'-',t.detailFunctionId,'-',t.detailForceId)");

$materials=array();

$mats=$db->query("select detailMaterialId, detailMaterialName from detailMaterial");
while($r=$mats->fetch_array()){
    $materials[$r[0]]=$r[1];
}

$components=array();

$comps=$db->query("select detailComponentId, detailComponentName from detailComponent");
while($r=$comps->fetch_array()){
    $components[$r[0]]=$r[1];
}

$forces=array();

$frcs=$db->query("select detailForceId, detailForceName from detailForce");
while($r=$frcs->fetch_array()){
    $forces[$r[0]]=$r[1];
}

$functions=array();

$fncs=$db->query("select detailFunctionId, detailFunctionName from detailFunction");
while($r=$fncs->fetch_array()){
    $functions[$r[0]]=$r[1];
}


?>


<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Details List</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
  </head>
  <body>
  <style>
        html * {
            font-family: Arial,Helvetica !important;
        }        
        .buttonClear {
            background-color:#4467c7;
            -moz-border-radius:28px;
            -webkit-border-radius:28px;
            border-radius:28px;
            border:1px solid #1829ab;
            display:inline-block;
            cursor:pointer;
            color:#ffffff;
            font-family:Arial;
            font-size:12px;
            padding:4px 10px;
            text-decoration:none;
            text-shadow:0px 1px 0px #2f2766;
        }
        .buttonClear:hover {
            background-color:#5c2abf;
        }
        .buttonClear:active {
            position:relative;
            top:1px;
        }
        .buttonAdd {
            background-color:#4467c7;
            -moz-border-radius:28px;
            -webkit-border-radius:28px;
            border-radius:28px;
            border:1px solid #1829ab;
            display:inline-block;
            cursor:pointer;
            color:#ffffff;
            font-family:Arial;
            font-size:12px;
            padding:2px 8px;
            text-decoration:none;
            text-shadow:0px 1px 0px #2f2766;
        }
        .buttonAdd:hover {
            background-color:#5c2abf;
        }
        .buttonAdd:active {
            position:relative;
            top:1px;
        }
        .ui-autocomplete-loading {
            background: white url("images/ui-anim_basic_16x16.gif") right center no-repeat;
        }  
        #scrolling-wrapper {
            width:95%;
            float:left;
            position:absolute;
            background:#eeeeee;
            padding:10px;
            bottom:0px;
            height:300px; 
            
            overflow-x: scroll;
            overflow-y: hidden;
            white-space: nowrap;
        }  
        #datacell {
            height:265;
            overflow-y: auto;
        }
        .highlightBox {
            background-color: #66ccff;
        }
        .detBox {
            display: inline-block;
            vertical-align: top;
            margin: 0px 2px;
        }
        .classification {
            font-size:70%;
        }
        #container1 {
            width:95%;
            float:left;
            position:absolute;
            background:#cccccc;
            padding:10px;
            bottom:320px;
            height:375px;
        }
        tr.border_bottom td {
            border-bottom:1pt solid black;
        }  
    </style>
  <section class="section">
    <div class="container-fluid">
      <h1 class="title">
        Details List
      </h1>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Detail List</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Detail Thumbs</a>
        </li>
        </ul>
<div class="tab-content" id="myTabContent">
  <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">

    <table class="display compact hover stripe cell-border" id="detailsTable">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Partial Name</th>
                <th>Code</th>
                <th>Parse Name</th>
                <th>Caption</th>
                <th>Approved</th>
                <th>Start Date</th>
                <th>End Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
                while($row=$result->fetch_assoc()){
                    echo "<tr>";
                    echo "<td>".$row['extendedName']."</td>";
                    echo "<td>".$row['name']."</td>";
                    echo "<td>".$row['code']."</td>";
                    echo "<td>".$row['parseName']."</td>";
                    echo "<td>".$row['caption']."</td>";
                    echo "<td>".($row['approved']==1?"YES":"NO")."</td>";
                    echo "<td>".formatDate($row['dateBegin'])."</td>";
                    echo "<td>".formatDate($row['dateEnd'])."</td>";
                    echo "</tr>";
                }
            ?>

        </tbody>
        <tfoot>
            <tr>
                <th>Full Name</th>
                <th>Partial Name</th>
                <th>Code</th>
                <th>Parse Name</th>
                <th>Caption</th>
                <th>Approved</th>
                <th>Start Date</th>
                <th>End Date</th>
            </tr>
        </tfoot>
    </table>

  </div>
  <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
<?php

    $currentDetail=0;

    while($rw=$res->fetch_assoc()){


        if($currentDetail!=$rw['detailId']){
            if($currentDetail!=0){
                echo "</tr>";
                echo '</table>';  
                echo '</div>';      
            }           
            $params = array();
            $params['act'] = 'detailthumb';
            $params['time'] = time();
            $params['keyId'] = DETAILS_HASH_KEYID;
            $params['fileId'] = $rw['detailRevisionId'];
            $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);					
            
    
            echo '<div id="detId_' . $rw['detailId'] . '" class="detBox">';
    
            echo '<table border="0" cellspacing="2" cellpadding="2">';   
            
            echo '<tr bgcolor="#ddffdd">';
    
            echo '<td colspan="4">';
            echo '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
                echo '<tr>';
                    echo '<td>' . $rw['fullName'];
                    echo '</td>';                
                    echo '<td align="right"><a class="buttonAdd" href="javascript:addDetail(' . ($rw['detailRevisionId']) . ')" style="margin-left: 20px">add</a></td>';
                echo '</tr>';
            echo '</table>';
            echo  '</td>';
            echo '</tr>';
            echo '<tr bgcolor="#ffffff" align="center">';
            echo '<td colspan="4">';    
            echo '<a href="javascript:clickScroller(' . ($rw['detailId']) . ')"><img src="' .$url. '"></a>';
            echo '</td>';
    
            echo '<tr bgcolor="#dddddd" class="border_bottom">';
            echo '<td class="classification" width="22%" align="center"><em>Material</em></td>';
            echo '<td class="classification" width="22%" align="center"><em>Component</em></td>';
            echo '<td class="classification" width="22%" align="center"><em>Function</em></td>';
            echo '<td class="classification" width="22%" align="center"><em>Force</em></td>';
            echo '</tr>';
    
            $currentDetail=$rw['detailId'];
        }
        echo '<tr class="border_bottom">';
        echo '<td class="classification">' . ($rw['detailMaterialId']>0?$materials[$rw['detailMaterialId']]:'') . '</td>';
        echo '<td class="classification">' . ($rw['detailComponentId']>0?$components[$rw['detailComponentId']]:'') . '</td>';
        echo '<td class="classification">' . ($rw['detailFunctionId']>0?$functions[$rw['detailFunctionId']]:'') . '</td>';
        echo '<td class="classification">' . ($rw['detailForceId']>0?$forces[$rw['detailForceId']]:'') . '</td>';
        echo '</tr>';
     


        
/*                                   
            if (results[i]['classifications']) {
                    var classifications = results[i]['classifications'];
                    for (j = 0; j < classifications.length; j++) {
                        html += '<tr class="border_bottom">';
                            html += '<td class="classification">' + classifications[j]['detailMaterialName'] + '</td>';
                            html += '<td class="classification">' + classifications[j]['detailComponentName'] + '</td>';
                            html += '<td class="classification">' + classifications[j]['detailFunctionName'] + '</td>';
                            html += '<td class="classification">' + classifications[j]['detailForceName'] + '</td>';
                        html += '</tr>';
                    }
            }
            html += '<tr>';
            html += '</tr>';                       
        html += '</table>';    */

    
    


    }
    echo "</tr>";
    echo '</table>';  
    echo '</div>'; 
?>


  </div>
</div>




    </div>
  </section>
    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.js"></script>  


<script>
    $(document).ready(function(){
        //$('#detailsTable').DataTable();

    $('#detailsTable tfoot th').each( function () {
        var title = $(this).text();
        $(this).html( '<input type="text" placeholder="Search '+title+'" />' );
    } );
        var table = $('#detailsTable').DataTable({
            initComplete: function () {
                // Apply the search
                this.api().columns().every( function () {
                    var that = this;
    
                    $( 'input', this.footer() ).on( 'keyup change clear', function () {
                        if ( that.search() !== this.value ) {
                            that
                                .search( this.value )
                                .draw();
                        }
                    } );
                } );
            }
        });
    });


</script>

  </body>
</html>

  



