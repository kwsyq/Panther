<?php

require_once "../inc/config.php";
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='DetailsList';\n</script>\n";
?>
<link href="https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js" ></script>
<?php
function formatDate($in){

    if($in==''){
        return '';
    }
    return substr($in, 5, 2)."/".substr($in, 8, 2)."/".substr($in, 0, 4);

}

$db=DB::getInstance();

$db->select_db("detailnew");

/*, w.detailRevisionId *//*LEFT JOIN sssdevnew.workorderdetail w ON w.detailRevisionId = dr.detailRevisionId
$query1 = "SELECT d.detailId, d.parentId, d.name, detailnew.getDetailFullName(d.detailId) as extendedName,  d.parseName, dr.detailRevisionId, 
dr.detailRevisionStatusTypeId, dr.code, dr.caption, dr.approved, dr.dateBegin, dr.dateEnd 
FROM detailnew.detail d, detailnew.detailRevision dr WHERE d.detailId = dr.detailId 
order by extendedName
";
//echo $query1;
*/
$rowDetailId = "";

$query1 = "SELECT d.detailId, d.name, detailnew.getDetailFullName(d.detailId) as extendedName,  d.parseName, dr.detailRevisionId, 
dr.detailRevisionStatusTypeId, dr.code, dr.caption, dr.approved, di.detailRevisionId,
dm.detailMaterialName, df.detailForceName, dfu.detailFunctionName, dco.detailComponentName
    FROM detailnew.detail d
        LEFT JOIN detailnew.detailRevision dr on dr.detailId = d.detailId
        LEFT JOIN detailnew.detailRevisiontypeitem di on dr.detailRevisionId = di.detailRevisionId 
        LEFT JOIN detailnew.detailMaterial dm on dm.detailMaterialId = di.detailMaterialId
        LEFT JOIN detailnew.detailForce df on df.detailForceId = di.detailForceId
        LEFT JOIN detailnew.detailFunction dfu on dfu.detailFunctionId = di.detailFunctionId
        LEFT JOIN detailnew.detailComponent dco on dco.detailComponentId = di.detailComponentId
        order by extendedName
";

$result=$db->query($query1);

$res=$db->query("select  *, detailnew.getDetailFullName(dr.detailId) as fullName, count(t.detailRevisionId) as typeCount  from detailnew.detailRevisionTypeItem t 
    join detailnew.detailRevision dr on t.detailRevisionId = dr.detailRevisionId 
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
</head>
<body>
<style>
    .detBox {
        display:none!important;
    }
    /*html * {
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
    }*/
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
    /*.ui-autocomplete-loading {
        background: white url("images/ui-anim_basic_16x16.gif") right center no-repeat;
    }*/  
    /*#scrolling-wrapper {
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
    }*/  
    /*#datacell {
        height:265;
        overflow-y: auto;
    }
    .highlightBox {
        background-color: #66ccff;
    }*/
    .detBox {
        display: inline-block;
        vertical-align: top;
        margin: 0px 2px;
    }
    .classification {
        font-size:70%;
    }
    /*#container1 {
        width:95%;
        float:left;
        position:absolute;
        background:#cccccc;
        padding:10px;
        bottom:320px;
        height:375px;
    }*/
    tr.border_bottom td {
        border-bottom:1pt solid black;
    } 
    tfoot input {
    width: 100%;
    }

    /* placing the footer on top */
    table tfoot {
        display: table-header-group;
    }
    /* customize tfoot */
    #detailsTable tfoot tr th {
        background-color: #9d9d9d;
    }
    #detailsTable tfoot th, #detailsTable tfoot td {
        border-right: 1px solid #898989;
    }
    ::placeholder {
        opacity: 0.7;
    }
    table.dataTable tfoot th, table.dataTable tfoot td {
        padding: 10px 10px 6px 10px;
    }

    /* customize thead */
    #detailsTable thead tr th {
        background-color: #808080;
        color: #fff;
    }
    #myTab {
        padding:12px;
    }
    .main-content li {
        padding-right:5px;
    }

    li a {
        color:#fff!important;
    }

    #detailsTable { border-bottom: 1px solid black }

 
</style>
<div id="container" class="clearfix">
<section class="section">
    <div class="container-fluid">
        <div class="main-content">
            <div class="full-box clearfix">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active btn btn-secondary mr-auto ml-auto btn-sm" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Detail List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-secondary mr-auto ml-auto btn-sm" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Detail Thumbs</a>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                <table class="stripe row-border cell-border" id="detailsTable">
                    <thead>
                        <tr>
                            <th>Full Name</th> <!-- Previous "Full Name" -->
                            <th>Partial Name</th>
                            <th>Code</th>
                            <th>Parse Name</th>
                            <th>Caption</th>
                            <th>Approved</th>
                            <th>Material</th>
                            <th>Force</th>
                            <th>Function</th>
                            <th>Component</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>Full Name</th> <!-- Previous "Full Name" -->
                            <th>Partial Name</th>
                            <th>Code</th>
                            <th>Parse Name</th>
                            <th>Caption</th>
                            <th>Approved</th>
                            <th>Material</th>
                            <th>Force</th>
                            <th>Function</th>
                            <th>Component</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php
                    
                            while( $row=$result->fetch_assoc() ) {
                                echo "<tr>";
                                echo "<td>".ltrim($row['extendedName'], ".")."</td>";
                                echo "<td>".$row['name']."</td>";
                                echo "<td>".$row['code']."</td>";
                                echo "<td>".$row['parseName']."</td>";
                                echo "<td>".$row['caption']."</td>";
                                echo "<td>".($row['approved']==1?"YES":"NO")."</td>";
                                echo "<td>".$row['detailMaterialName']."</td>";
                                echo "<td>".$row['detailForceName']. "</td>";
                                echo "<td>".$row['detailFunctionName']. "</td>";
                                echo "<td>".$row['detailComponentName']. "</td>";
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                   
                </table>
            </div>
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
        <?php
        $currentDetail=0;

        while($rw=$res->fetch_assoc()) {

            if($currentDetail!=$rw['detailId']) {
                if($currentDetail!=0) {
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
                echo '<table id="tableName" border="0" cellpadding="0" cellspacing="0" width="100%">';
                    echo '<tr>';
                        echo '<td class="fullNameClass">' .ltrim($rw['fullName'], ".");
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
        


            
            /* if (results[i]['classifications']) {
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
                html += '</table>';    
            */

        }
        echo "</tr>";
        echo '</table>';  
        echo '</div>'; 
        ?>

        </div>
        </div>
    </div>
    </div>
    </div>
</section>
</div>
<script>
$(document).ready(function() {
    $('#detailsTable tfoot th').each( function () {
        //var title = $(this).text();
        $(this).html( '<input type="text" placeholder="Search.." />' );
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
    
    $("#profile-tab").click(function() {
        var arrayValues = [];
        table.column(0,  { search:'applied' } ).data().each(function(value, index) {
            arrayValues.push(value); // get all values first column filter
        });

        var idArr = [];
        debugger;
        arrayValues.forEach(function(item) {
            $.each($(".detBox"), function(k,v) {
                var name = v.innerHTML;
                if(name.indexOf(item) > 0 ) {
                    console.log(v);
                    v.addClass("Aici");
                }
            });
          
        });
 
    });

 
});

</script>
</body>
</html>
<?php 
include BASEDIR . '/includes/footer.php';
?>
  



