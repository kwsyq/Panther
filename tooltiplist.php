<?php

/*
  tooltiplist.php

  EXECUTIVE SUMMARY: This is a top-level page. Allow Admin to view data about tooltip and help text.
    Database table tooltip.
    Displays a dynamic table : searchable and order by column name.
     * Id - unique
     * Page Name
     * Field Name
     * Field Name
     * Tooltip
     * Help Text
     * Edit button
     * Delete button
    
    Possible actions:
      Edit: display a popup modal with two textareas Tooltip and Help Text. The fields are populated with 
        information from database, table tooltip. The admin cand edit the tooltip and/ or help text.
        On Save the new updated information will be display ( without refresh ) on the dynamic table.
        The tooltip textarea can't be blank. An alert message will show.
      
      Delete: when is clicked the selected row will be automatically deleted from database table tooltip,
        the table row will be removed from the dynamic table without refreshing the page. A Confirmation message 
        will show.
      
    */

require_once './inc/config.php';
require_once './inc/access.php';
?>

<?php

  $error = "";
  $db = DB::getInstance();

  $tooltipList = array();

  $query = "SELECT * FROM " . DB__NEW_DATABASE . ".tooltip ";
  $result = $db->query($query);

  if ( $result ) {
    while ($row = $result->fetch_assoc()) {
      $tooltipList[] = $row;
    }
  } else {
    $errorId = "637487429860964237";
    $error = "We could not display the Tooltips. Database Error.";  // Message for end user
    $logger->errorDB($errorId, $error, $db);
  }

?>

<?php
include_once BASEDIR . '/includes/header.php';
if ($error) {
  echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>
<link href="https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js" ></script>

<style>
body {
    background-color: #fff;
}

tfoot input {
  width: 100%;
}

/* placing the footer on top */
table tfoot {
    display: table-header-group;
}
/* customize tfoot */
#tooltipList tfoot tr th {
    background-color: #9d9d9d;
}
#tooltipList tfoot th, #tooltipList tfoot td {
    border-right: 1px solid #898989;
}
::placeholder {
    opacity: 0.7;
}
table.dataTable tfoot th, table.dataTable tfoot td {
    padding: 10px 10px 6px 10px;
}

#tooltipList { border-bottom: 1px solid black }

/* prevent body movement */
body.modal-open {
  padding-right: 0px !important;
  overflow-y: auto;
}

/* Information Help */
#infoHelp {
  display:none; 
  background-color: #f2f2f2; 
  padding:10px;
  float: left;
}

#labelInfoHelp,  #labelTooltip {
  float: left;
  font-weight: 600;
}
#infoHelp p {
  float: left;
  font-weight: 600;
}


</style>
<div id="container" class="clearfix">
  <div class="main-content mt-10">
    <div class="full-box clearfix">
      <h2 class="heading">Tooltip List</h2>
      <br>
      <table class="stripe row-border cell-border  " id="tooltipList" style="width:100%">
        <thead>
          <tr>
            <th scope="col"> ID</th>
            <th scope="col">Page Name</th>
            <th scope="col">Field Name</th>
            <th scope="col">Field Label</th>
            <th scope="col">Tooltip</th>
            <th scope="col">Help Text</th>
            <th scope="col">Edit</th>
            <th scope="col">Delete</th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <th id="searchFieldId">&nbsp;</th>
            <th >Page Name</th>
            <th >Field Name</th>
            <th >Field Label</th>
            <th>Tooltip</th>
            <th >Help Text</th>
            <th id="editSearchId">Edit</th>
            <th id="deleteId">&nbsp;</th>
          </tr>
      </tfoot>
        <tbody>
        <?php 

          foreach( $tooltipList as $tooltip ) {
        ?>
          <tr>
              <th id="fieldId<?=$tooltip['id']?>"><?=$tooltip['id']?></th>
              <td id="pageName<?=$tooltip['id']?>"> <?=$tooltip['pageName']?></td>
              <td id="fieldName<?=$tooltip['id']?>"> <?=$tooltip['fieldName']?></td>
              <td id="fieldLabel<?=$tooltip['id']?>"> <?=$tooltip['fieldLabel']?></td>
              <td id="tooltip<?=$tooltip['id']?>"><?=$tooltip['tooltip']?></td>
              <td id="help<?=$tooltip['id'] ?>"><?=$tooltip['help'] ?></td>
              <td><button class="make-active btn btn-info" id="buttonModal<?=$tooltip['id']?>">Edit</button></td>
              <td><button class="make-active btn btn-secondary" id="deleteTooltip<?=$tooltip['id']?>">Del</button></td>
          </tr>
      <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
$(document).ready(function() {
 

    $('#tooltipList tfoot th').each( function () {
        $(this).html( '<input type="text" placeholder="Search.." />' );
        $("#deleteId input").hide(); //Hide for Delete column. 
        $("#searchFieldId input").hide(); //Hide for ID column.
        $("#editSearchId input").hide(); //Hide for Edit column.
        $('input[type="text"]').addClass('form-control form-control-sm');
        
    } );

    // DataTable
    var table = $('#tooltipList').DataTable({
        "autoWidth": false,
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
    table
    .order( [ 1, 'asc' ] )
    .draw();

    // Declare variables.
    var fieldName, pageName, tooltipId, helpId;
    var textTooltip = "";
    var textHelp = "";
    // on button "Edit" click, populate the modal with values.
    $("#tooltipList tbody").on('click', "[id^='buttonModal']", function (ev) {
    
        buttonId = $(this).attr('id'); // button Id we clicked

        // Finds the closest row <tr>. Gets a descendent with id="fieldName". Retrieves the text within <td>
        fieldName = $(this).closest("tr").find("[id^='fieldName']").text();
        pageName = $(this).closest("tr").find("[id^='pageName']").text();
        tooltipId = $(this).closest("tr").find("[id^='tooltip']").attr('id'); //tooltip Id
        helpId = $(this).closest("tr").find("[id^='help']").attr('id'); //help Id

     
        ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.
       
   
        $.ajax({
            type:'GET',
            url: '../ajax/get_tooltip2.php',
            async:false,
            dataType: "json",
            data: {
                fieldName: fieldName,
                pageName: pageName
            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                  
                    if (data['status'] == 'success') {
                      
                        // populate the modal with values.
                        $(".modal-body #tooltipInput").val(data["tooltip"]);
                        $(".modal-body #helpInput").val(data["help"]);

                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
            // alert('error');
            }
        });
        $("#tooltipModal").modal(); // use native function of Bootstrap. Display Modal.
    });

    $('#tooltipModal').on('show.bs.modal', function (e) {
        // Show Information for Help Link.
        $("#labelInfoHelp").click(function() {
          $("#infoHelp").show("slow");
          $("#pageNameHelpLink").text(pageName); // add dynamic page as example

        });

        // Save
        $("#saveInfo").click(function(ev) {
          ev.stopImmediatePropagation();
          textTooltip = $("#tooltipInput").val();
          if( $("#tooltipInput").val() == "" ) { //tooltip text is required.
                alert("Please fill the tooltip text. If you want to delete this tooltip, use the Delete Button");
          } else if(textTooltip.trim() == "") {
                alert("Tooltip text can not be blank. If you want to delete this tooltip, use the Delete Button");
          } else {
            $.ajax({
                  type:'POST',
                  url: '../ajax/set_tooltip.php',
                  async:false,
                  dataType: "json",
                  data: {
                      fieldName: fieldName,
                      pageName: pageName,
                      textTooltip: $("#tooltipInput").val(),
                      textHelp: $("#helpInput").val()
                  },
                  
                  success: function(data, textStatus, jqXHR) {

                      if (data['status']) {
                          if (data['status'] == 'success') {
                            // Update entry in tooltip table. Update table row from data table.
                            $("#"+tooltipId).html(data[0]["tooltip"]);
                            $("#"+helpId).html(data[0]["help"]);
  
                            $('#tooltipModal').modal('hide'); // close modal.
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
  
            delete fieldName, textTooltip, textHelp;
          }
    
        });
    });
 
  $("#tooltipList tbody").on('click', "[id^='deleteTooltip']", function (ev) {
      buttonId = $(this).attr('id'); // Button Id we clicked
  
      //field Id. Unique
      var fieldId = $(this).closest("tr").find("[id^='fieldId']").text(); 

      ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.  
      if (!confirm('Are you sure to Delete this entry?')) {
        return;
        event.preventDefault(); 
      } else {
          $.ajax({
            type:'POST',
            url: '../ajax/delete_tooltip.php',
            async:false,
            dataType: "json",
            data: {
              fieldId: fieldId
            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                      // deleted from DB. Remove table row from data table.
                      $("#"+buttonId).closest('tr').remove();

                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
            // alert('error');
            }
        });
    }
  });
  $('#tooltipModal').on('hide.bs.modal', function (e) { 
    $("#infoHelp").hide(); // hide Help Text Link Information.                 
  });

});
</script>

<div class="modal fade" id="tooltipModal" tabindex="-1" role="dialog" aria-labelledby="tooltipModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tooltipModalLabel">Tooltip | Help Text</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group">
            <label for="tooltip-text" class="col-form-label" id="labelTooltip" >Tooltip Details:</label>
            <textarea class="form-control" name="tooltipInput" id="tooltipInput" value=""></textarea>
          </div>
          <div class="form-group">
            <label for="message-text" class="col-form-label" id="labelInfoHelp">Help Text Link: &nbsp;<i class="fas fa-info-circle" title="click for Info"></i></label>
            <textarea class="form-control" name="helpInput" id="helpInput" maxlength="60" placeholder="Make sure the page you are pointing to exists in the CMS system." value=""></textarea>
            </br>
            <div class="form-group" id="infoHelp" >
                    <p>Page with parameter : <span id="pageNameHelpLink"></span>/parameter</p>
                    <p>Simple Page(name of the page): page</p>
                    <p>External URL: https://www.google.com/</p>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" type="submit" id="saveInfo" class="btn btn-info">Save</button>
      </div>
    </div>
  </div>
</div>

<?php
include_once BASEDIR . '/includes/footer.php';
?>