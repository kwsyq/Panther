<?php

require_once './inc/config.php';
require_once './inc/perms.php';
do_primary_validation(APPLICATION_FATAL_ERROR);
$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'rwname');
if (!$v->validate()) {
    $errorId = '637335223123647588';
    $logger->error2($errorId, "Rwname errors: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid Rwname for this Job in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$rwname = trim($_REQUEST['rwname']);
if (!Job::validateRwname($rwname)) {
    $errorId = '637335281672552232';
    $logger->error2($errorId, "The provided job $rwname does not correspond to an existing DB row in job table");
    $_SESSION["error_message"] = "Invalid job. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$job = new Job($rwname); // Construct Job object

$crumbs = new Crumbs($job, $user);

include BASEDIR . '/includes/header.php';
$jobName = $job->getName();
echo "<script>\ndocument.title = 'Job ". $job->getNumber() .
    ($jobName ? (': ' . str_replace("'", "\'", $jobName)) : '') .
    "';\n</script>\n"; // Add title
unset ($jobName);

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
//include BASEDIR . '/includes/crumbs.php';
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

$query1 = "SELECT distinct d.detailId, d.name, detailnew.getDetailFullName(d.detailId) as extendedName,  d.parseName, dr.detailRevisionId, jd.detailRevisionId, jd.jobId,
dr.detailRevisionStatusTypeId, dr.code, dr.caption, dr.approved, di.detailRevisionId
    FROM ".DB__DETAIL_DATABASE.".detail d
        LEFT JOIN ".DB__DETAIL_DATABASE.".detailRevision dr on dr.detailId = d.detailId
        LEFT JOIN ".DB__DETAIL_DATABASE.".detailRevisionStatus di on dr.detailRevisionId = di.detailRevisionId
        LEFT JOIN ".DB__NEW_DATABASE.".jobDetail jd on jd.detailRevisionId = dr.detailRevisionId
        WHERE dr.approved =1
        /*ORDER BY d.name*/
        ORDER BY jd.jobId DESC, extendedName
";


//echo $query1;
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

$db->select_db(DB__NEW_DATABASE);
$docGroups=$db->query("select * from ".DB__NEW_DATABASE.".documentGroups");


?>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="/node_modules/admin-lte/plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="/node_modules/admin-lte/dist/css/adminlte.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="/node_modules/admin-lte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
<link href="https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css" rel="stylesheet"/>
<script>
    imgs=new Array();


    // Copy link on clipboard.
    function copyToClip(str) {
        function listener(e) {
            e.clipboardData.setData("text/html", str);
            e.clipboardData.setData("text/plain", str);
            e.preventDefault();
        }
        document.addEventListener("copy", listener);
        document.execCommand("copy");
        document.removeEventListener("copy", listener);
    };
    $(document).ready(function() {
        // Change text Button after Copy.
        $('#copyLink').on("click", function (e) {
            $(this).text('Copied');
        });
        $("#copyLink").tooltip({
            content: function () {
                return "Copy WO Link";
            },
            position: {
                my: "center bottom",
                at: "center top"
            }
        });
    });


</script>
<style>
    .detBox {
/*        display:none!important;*/
    }

    .buttonAdd {
       /* background-color:#4467c7;
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
        text-shadow:0px 1px 0px #2f2766; */
    }
    .buttonAdd:hover {
        background-color:#5c2abf;
    }
    .buttonAdd:active {
        position:relative;
        top:1px;
    }

    .detBox {
        display: inline-block;
        vertical-align: top;
        margin: 0px 2px;
    }
    .classification {
        font-size:70%;
    }
    tr.border_bottom td {
        border-bottom:1pt solid black;
    }
    tfoot input {
    width: 100%;
    }

    table tfoot {
        display: table-header-group;
    }

    #detailsTable tfoot tr th {
        background-color: #9d9d9d;
    }
    #detailsTable tfoot th, #detailsTable tfoot td {
        border-right: 1px solid #898989;
    }
    ::placeholder {
        opacity: 0.7;
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

    .showHide {
        display:inline-block;
    }
    .detBox2 {
        display:none;
    }
    #ctrl-show-selected {
        width: 170px;
        background-color: #545b62;
        color: #fff;
        font-weight: 600;
    }
    .ok{
        background-color: red;
    }
    .notOk{
        background-color: yellow;
    }
    .backgroundCkecked{
        background-color: #cb78f2;
    }

    #copyLink {
    color: #000;
    font-family: Roboto,"Helvetica Neue",sans-serif;
    font-size: 12px;
    font-weight: 600;
  }

  #copyLink:hover {
      color: #fff;
      font-size: 12px;
      font-weight: 600;
  }
  #firstLinkToCopy {
      font-size: 18px;
      font-weight: 700;
  }

</style>
<div class="wrapper">
<?php
        $scheme = $_SERVER['REQUEST_SCHEME'] . '://'; // http or https.
        $urlToCopy = "$scheme$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  
    ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            (DC)<a href="<?php echo $urlToCopy ?>">&nbsp;DocumentCenter&nbsp;</a>
            [J]&nbsp;<a id="linkWoToCopy" href="<?= $job->buildLink()?>"> <?php echo $job->getName();?> (<?php echo $job->getNumber();?>) </a>
            <button id="copyLink" title="Copy DC link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>    
        <span id="linkToCopy" style="display:none"> (DC)&nbsp;<a href="<?php echo $urlToCopy ?>">DocumentCenter</a>&nbsp;[J]&nbsp;<a href="<?= $job->buildLink()?>"> <?php echo $job->getName();?> (<?php echo $job->getNumber();?>) </a></span>
    </div>
    <div class="clearfix"></div>

  <!-- Preloader
  <div class="preloader flex-column justify-content-center align-items-center">
    <img class="animation__shake" src="/cust/ssseng/img/header_logo.jpg" alt="AdminLTELogo" height="60" width="160">
  </div> -->

  <!-- Content Wrapper. Contains page content -->
  <div class="container-fluid">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-12 text-left ">
            <h1 style="font-weight: 600; font-family: Comic Sans Ms; display:none">
            [J] <?php echo $job->getName(); ?> (<a href="/job/<?php echo $job->getRwName();?>"><?php echo $job->getNumber(); ?></a>)
            </h1>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <!-- Main row -->
        <div class="row">
          <!-- Left col -->
          <section class="col-lg-12 connectedSortable">
            <!-- Custom tabs (Charts with tabs)-->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="fas fa-clipboard-list mr-1"></i>DETAILLS
                </h3>
                <div class="card-tools">
                  <button type="button" class="btn btn-primary btn-sm" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                  </button>
                </div>
              </div><!-- /.card-header -->
              <div class="card-body">
                <div class="tab-content p-0">
<!-- START DETAILS -->
                <div class="container-fluid p-0 m-0">
                  <div class="row">
                    <div class="col-sm-8">
                    <ul class="nav nav-tabs float-left" id="myTab" role="tablist">
                    <li class="nav-item">
                <a class="nav-link active btn btn-secondary mr-auto ml-auto btn-sm"  id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Detail List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-secondary mr-auto ml-auto btn-sm" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Detail Thumbs</a>
            </li>
                  </ul>
                    </div>
                    <div class="col-sm-4">
                    <select style="float:right;" class="form-control form-control-sm mb-3" id="ctrl-show-selected">
                    <option value="all" selected>Show all</option>
                    <option value="selected">Show selected</option>
                    <option value="not-selected">Show not selected</option>
                </select>
                    </div>
                  </div>
    <div class="container-fluid p-0 m-0">
        <div class="main-content p-0">
            <div class="full-box clearfix">

            <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                <table class="stripe row-border cell-border" id="detailsTable">
                    <thead>
                        <tr>
                            <th>Full Name</th> <!-- Previous "Full Name" -->
                            <th>Partial Name</th>
                            <th>Code</th>
                            <th>Img. Status</th>
                            <th>Parse Name</th>
                            <th>Caption</th>
                            <!--<th>Approved</th>-->
                            <th>Include</th>

                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th >Full Name</th> <!-- Previous "Full Name" -->
                            <th >Partial Name</th>
                            <th >Code</th>
                            <th style='width:130px'>Img. Status</th>
                            <th >Parse Name</th>
                            <th >Caption</th>
                            <!--<th style="padding: 0">Approved</th>-->
                              <!--<td ><button type="button" id="buttonId" class="btn btn-outline-primary btn-sm">Show Selected Reports</button></td>-->
                            <th>Include</th>

                        </tr>
                    </tfoot>
                    <tbody>
                        <?php

                            while( $row=$result->fetch_assoc() ) {

                                echo "<tr>";
                                echo "<td id='".$row['detailId']."' >".ltrim($row['extendedName'], ".")."</td>";
                                echo "<td id='detailName_".$row['name']."'>".$row['name']."</td>";
                                echo "<td>".$row['code']."</td>";
                                echo "<td id='imgId".$row['detailId']."' onclick='showModal(".$row['detailId'].")'>".$row['detailId']."</td>";
                                echo "<td>".$row['parseName']."</td>";
                                echo "<td>".$row['caption']."</td>";
                                if($job->getJobId() == $row['jobId']) {
                                  echo "<td style='text-align: center'><input name='checkRow' class='addedToJob' id='checkBox_".$row['detailRevisionId']."' type='checkbox' checked></td>";
                                } else {
                                echo "<td style='text-align: center'><input name='checkRow' id='checkBox_".$row['detailRevisionId']."' type='checkbox'></td>";
                                }
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

                echo "<script>

                    imgs['".$rw["detailId"]."']='".$url."';

                </script>";


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
                        echo "</tr>";

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



<!-- END DETAILS -->
                </div>
              </div><!-- /.card-body -->
            </div>
            <!-- /.card -->

          </section>
          <!-- /.Left col -->
          <!-- right col (We are only adding the ID to make the widgets sortable)-->
        <section class="col-lg-6 connectedSortable">

<?php while($row=$docGroups->fetch_assoc()) { ?>
<div class="card bg-gradient-info">
    <div class="card-header border-0">
      <h3 class="card-title font-weight-bold">
        <i class="fas fa-map-marker-alt mr-1"></i>
          <?php echo $row['documentGroupName'] ?>
      </h3>
      <!-- card tools -->
      <div class="card-tools">
        <button type="button" class="btn btn-primary btn-sm" data-card-widget="collapse" title="Collapse">
          <i class="fas fa-minus"></i>
        </button>
      </div>
      <!-- /.card-tools -->
    </div>
    <div class="card-body bg-white p-0">
        <table class="table table-striped table-hover container-fluid">
            <thead>
              <tr>
                <th>Document Name</th>
                <th>Document File</th>
                <th>Description</th>
                <th><span data-toggle="modal" data-target="#exampleModal" data-documentGroupId="<?php echo $row['documentGroupId'] ?>"><i class="fas fa-file"></i></span></th>
              </tr>
            </thead>
            <tbody>
              <?php
                $docs=$db->query("select * from jobDocument where jobId=".$job->getJobId()." and documentGroupId=".$row['documentGroupId']);
                if($docs){
                  while($rdoc=$docs->fetch_assoc()){
              ?>
                    <tr>
                      <td><?php echo $rdoc['name']; ?></td>
                      <td><?php echo $rdoc['fileName']; ?></td>
                      <td><?php echo $rdoc['description']; ?></td>
                      <td>
                      <a href="/uploads/<?php echo $rdoc['fileOnDisk'];?>" target="_blank"><i class="fas fa-eye"></i></a>
                        <span class="deleteDocument" tag="<?php echo $rdoc['jobDocumentId'];?>"><i class="fas fa-trash"></i></span>
                      </td>
                    </tr>
            <?php
                  }
                }
            ?>
            </tbody>
        </table>

    </div>
    <!-- /.card-body-->
    <div class="card-footer clearfix">

    </div>
</div>



  <?php
}
?>

          </section>
          <section class="col-lg-6 connectedSortable">
          </section>
          <!-- right col -->
        </div>
        <!-- /.row (main row) -->
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>
</div>
<!-- ./wrapper -->
<div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="tooltipModalLabel">Detail Image</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body" id="modal-bodyImg">
        </div>
        <div class="modal-footer">
            <input type="hidden" id="userToLogIn" value="<?=$user->getUserName()?>">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <!--<button type="button" type="submit" id="saveInfo" class="btn btn-info">Go to Detail</button> -->
            <a id="linkDetailManage" href='' target="_blank" class="btn btn-info active" role="button">Go to Detail</a>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="addDocument" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Document Center - Load new Document</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
          <div class="card card-primary">
              <div class="card-header">
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role="form"  enctype="multipart/form-data" action="loadDocument.php" method="post">
                <input type="hidden" class="form-control" name="documentJobId" id="documentJobId" value="<?php echo $job->getJobId();?>">
                <input type="hidden" class="form-control" id="documentGroupId" name="documentGroupId" value="<?php echo $job->getJobId();?>">
                <div class="card-body text-left">
                  <div class="form-group">
                    <label for="documentName">Document Name</label>
                    <input type="text" class="form-control" id="documentName" name="documentName" placeholder="Document Name">
                  </div>
                  <div class="form-group">
                    <label for="documentDescription">Document Name</label>
                    <textarea class="form-control" id="documentDescription" name="documentDescription" placeholder="Document Description"></textarea>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputFile">File</label>
                    <div class="input-group">
                      <div class="custom-file">
                        <input type="file" class="custom-file-input" id="exampleInputFile" name="documentFile">
                        <label class="custom-file-label" for="exampleInputFile">Choose file</label>
                      </div>
                      <div class="input-group-append">
                        <span class="input-group-text" id="">Upload</span>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- /.card-body -->

                <div class="card-footer">
                  <button type="submit" class="btn btn-primary">Submit</button>
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
              </form>
            </div>
            <div class="modal-footer">
      </div>
    </div>
  </div>
</div>
<script>
  $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
<script src="/node_modules/admin-lte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/node_modules/admin-lte/plugins/moment/moment.min.js"></script>
<script src="/node_modules/admin-lte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- AdminLTE App -->
<script src="/node_modules/admin-lte/dist/js/adminlte.js"></script>

<script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js" ></script>


<script>

function showModal(detailId) {
    $('#exampleModalCenter').modal('show');
    userToLogIn = $('#userToLogIn').val();

    $("#linkDetailManage").attr("href","http://details/manage?detailId="+detailId+"&username="+userToLogIn);

    // Image Source Validation
    if(imgs[detailId]) {
        $('#modal-bodyImg').html("<img src='"+imgs[detailId]+"' height='220' >");
    } else {
        $('#modal-bodyImg').html("<p>This Detail has no image attached.</p><p>You can see more info of this particular detail on the link</p> <p><strong>Go to Detail</strong>.</p>");
    }


}


$(document).ready(function() {

  $('.deleteDocument').mousedown(function(){
    var id=$(this).attr('tag');
    if(confirm("Are you sure you want to remove the selected document?")){
      $.post('deleteDocument.php', {'id': id}).done(function(data){
        alert(data + " done!");
        location.reload();
      });
    }
  });

    // Image Status
    $('#detailsTable tr').each( function () {
        var rowId = $(this).closest("tr").find("[id]").attr('id'); // we got the id's of each  row
        var imgId = $(this).closest("tr").find("[id^='imgId" + rowId +"']").attr('id'); // we got the id's of each td IMG

        var imgExists = "";

        var hasImage = imgs[rowId];

        if(hasImage) {
            imgExists = "Show Img";
            $("#"+imgId).text(imgExists);
            $("#"+imgId).css("background-color", "#cb78f2");
        } else {
            imgExists = "No Img";
            $("#"+imgId).text(imgExists);
            $("#"+imgId).css("background-color", "#eed0a4");

        }
    });
    // End Image Status

    $('#exampleModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget)
      var recipient = button.data('documentgroupid')
      var modal = $(this);
      modal.find('#documentGroupId').val(recipient);
    })

    $('#detailsTable tfoot th').each( function () {
        var title = $(this).text();

		if(title !="Img. Status") {
			$(this).html( '<input class="form-control form-control-sm" type="text" placeholder="Search.." />' );
            $('input[type="text"]').addClass('form-control form-control-sm noTooltip');
		} else {
			$(this).html(''); // hide input for Select Img.
		}

    });

    var values = [];
    var table = $('#detailsTable').DataTable({

        'drawCallback': function(settings) {

            // Show Selected
            $("input").on("click", function() {

                if (this.checked) {
                    this.setAttribute("checked", "checked");
                    row = $(this).closest("tr").addClass("selected");


                    $("#detailsTable tbody input[name=checkRow]:checked").each(function() {
                        row = $(this).closest("tr");
                        values.push({
                            //detailNameFull: $(row).find("[class='sorting_1']").text()
                            detailNameFull: $(row).children('td:first').text()
                        });
                    });

                } else {



                    values = [];
                    $("#detailsTable tbody input[name=checkRow]:checked").each(function() {
                        row = $(this).closest("tr");
                        values.push({
                            //detailNameFull: $(row).find("[class='sorting_1']").text()
                            detailNameFull: $(row).children('td:first').text()
                        });
                    });
                    this.removeAttribute("checked");
                    $(this).closest("tr").removeClass("selected");

                }
            });
            $('.addedToJob').each(function() {
              row = $(this).closest("tr").addClass("selected");
              values.push({
                  //detailNameFull: $(row).find("[class='sorting_1']").text()
                  detailNameFull: $(row).children('td:first').text()
                });
            });
        },
        'columnDefs': [
         {
            'targets': 0,
            'checkboxes': {
               'selectRow': true,
               'selectCallback': function(nodes, selected){
                  // If "Show all" is not selected
                  if($('#ctrl-show-selected').val() !== 'all'){
                     // Redraw table to include/exclude selected row
                     table.draw(false);
                  }
               }
            },
         }
      ],'select': 'multi',
        initComplete: function () {
            // Apply the search

            this.api().columns().every( function () {
					if( this[0] == 3 ) { // Apply the select
						var column = this;
						var select = $('<select class="form-control form-control-sm"><option value="">Img. Status</option></select>')
							.appendTo( $(column.footer()))
							.on( 'change', function () {
								var val = $.fn.dataTable.util.escapeRegex(
									$(this).val()
								);

								column
									.search( val ? '^'+val+'$' : '', true, false )
									.draw();
							} );

						column.data().unique().sort().each( function ( d, j ) {
							select.append( '<option value="'+d+'">'+d+'</option>' )
						} );
					} else if ( this[0] != 3 ) { // Apply the search
						var that = this;
						$( 'input', this.footer() ).on( 'keyup change clear', function () {
							if ( that.search() !== this.value ) {
								that
									.search( this.value )
									.draw();
							}
						});
					}

            });
        },
        "order": [],
    });

    // Handle change event for "Show selected records" control
    $('#ctrl-show-selected').on('change', function() {
        var val = $(this).val();

        // If all records should be displayed
        if(val === 'all'){
            $.fn.dataTable.ext.search.pop();
            table.draw();
        }

        // If selected records should be displayed
        if(val === 'selected'){
            $.fn.dataTable.ext.search.pop();
            $.fn.dataTable.ext.search.push(
                function (settings, data, dataIndex){
                return ($(table.row(dataIndex).node()).hasClass('selected')) ? true : false;
                }
            );

            table.draw();
        }

        // If selected records should not be displayed
        if(val === 'not-selected'){
            $.fn.dataTable.ext.search.pop();
            $.fn.dataTable.ext.search.push(
                function (settings, data, dataIndex){
                return ($(table.row(dataIndex).node()).hasClass('selected')) ? false : true;
                }
            );

            table.draw();
        }
    });



    $("#detailsTable tbody").on('click', "[id^='checkBox_']", function (ev) {
      var thisButton = this;
      buttonId = $(this).attr('id'); // button Id we clicked
      var revision = buttonId.split('_').pop(); // revision Id of the detail we clicked

      ev.stopImmediatePropagation();
      if ( this.checked ) {

        $.ajax({
            url: '/ajax/add_job_detail.php',
            dataType: "json",
            data: {
                jobId: <?php echo $job->getJobId(); ?>,
                detailRevisionId: revision,
                personId: <?php echo $user->getUserId(); ?>
            },
            async:false,
            type:'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {

                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                //alert('error');
            }
        });

      } else {
        $(this).closest("tr").removeClass("selected");
        $.ajax({
            url: '/ajax/remove_job_detail.php',
            dataType: "json",
            data: {
                jobId: <?php echo $job->getJobId(); ?>,
                detailRevisionId: revision

            },
            async:false,
            type:'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {

                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                //alert('error');
            }
        });
      }
    });





    $("#profile-tab").click(function() {
        var value = "";
        var item = "";
        var arrayValues = [];
        var arrayCheckValues = [];
        console.log(values);
        $(".detBox").addClass("detBox2");

        // For select/checked
        $(".detBox").removeClass("backgroundCkecked");
        values.forEach(function(item) {
            arrayCheckValues.push(item['detailNameFull']);

        });
        var uniqueArray = Array.from(new Set(arrayCheckValues));


        uniqueArray.forEach(function(item) {
            var notExistingCheck = "";
            notExistingCheck = $('div.detBox:contains("'+item+'")').attr('id');
            $("#"+notExistingCheck).removeClass("detBox2");
            $("#"+notExistingCheck).addClass("showHide");
            $("#"+notExistingCheck).addClass("backgroundCkecked");
        });

        table.column(0,  { search:'applied' } ).data().each(function(value, index) {

            if(value) {
                arrayValues = [];

                arrayValues.push(value); // get all values first column filter

                if(jQuery.inArray(value, arrayValues) != -1) {

                    var notExistingList = "";
                    notExistingList = $('div.detBox:contains("'+value+'")').attr('id');
                    $("#"+notExistingList).removeClass("detBox2");
                    $("#"+notExistingList).addClass("showHide");
                }

                delete window.arrayValues;
            }

        });
        delete window.uniqueArray;

    });


});

$('.connectedSortable').sortable({
    placeholder: 'sort-highlight',
    connectWith: '.connectedSortable',
    handle: '.card-header, .nav-tabs',
    forcePlaceholderSize: true,
    zIndex: 999999
  })
  $('.connectedSortable .card-header').css('cursor', 'move')

</script>

<?php
include BASEDIR . '/includes/footer.php';
?>