<?php 
/* includes/footer_fb.php

   EXECUTIVE SUMMARY: basically a placeholder if we ever want more of a footer for
   our fancyboxes; for now, just closes out the BODY & HTML elements.
    
*/
// George 03-03-2021. Add tooltip system.
require_once BASEDIR . '/generate_jwt.php'; // to get the token $jwt

$pageId = "";
$pageName = "";

$pageName = basename($_SERVER["SCRIPT_FILENAME"], '.php');
// files with the same names, one in the root, the other in fb/
if($pageName === "workorder" || $pageName === "companyperson" || $pageName === "invoice" || $pageException === "creditrecord") {
   $path = pathinfo($_SERVER["REQUEST_URI"]);
   if($path['dirname'] == "/fb") {
      $pageName = "fb-". $pageName;
   }
}

?>
<style>
.tippy-box[data-theme~="custom"] {
  background-color: #fff;
  color: black;
  font-weight: 500;
  font-size: 17px;
  border: 1px solid #ededed;
  border-radius: 10;
  width: auto;
  height: auto;
  float: right;
}

#exitModeId {
    color: red;
}
body.modal-open {
padding-right: 0px !important;
overflow-y: auto;
}
</style>
<script>
$( document ).ready(function() {
   // on button click refresh the page to show tooltips.
   $("#hideTooltip").click(function() {
        if($("#hideTooltip").hasClass("hideTippy")){
            location.reload();
        }
    });

    $.ajax({
        type:'POST',
        url: '../ajax/get_tooltip.php',
        async:false,
        dataType: "json",
        data: {
            pageName:"<?php echo $pageName;?>"
        },
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') {

                    $.each(data, function(index) {
                        if(data[index]["help"]) { // if helpText exists.
                            // Begin link construction
                            var strHelp = data[index]["help"]; // the entry from DB.
                            var pattern = /^((http|https|ftp):\/\/)/; // check if we have http or https at the begining

                            if(pattern.test(data[index]["help"])) {
                                link = data[index]["help"];
                            } else {
                                if( data[index]["help"].indexOf('/') >= 0) { // if page and parameter
                                    pageParam = strHelp.substring(0, strHelp.indexOf('/'));
                                    pageId = strHelp.substring(strHelp.indexOf('/') + 1);
                                    
                                    pageParam = pageParam.replace(/%20/g, "");
                                    pageId = pageId.replace(/%20/g, "");
                                    pageId = pageId.replace(/#/g, "");
                        
                                    helpLinkText = '&page='+pageParam+'&pageId='+pageId;
                                    link = '<?=$schema ."://". $wordpressServerName ."/wordpress/".$restRoute?>' + helpLinkText + '<?="&JWT=".$jwt?>';
                                } else { // only page
                                    pageParam = data[index]["help"].replace(/%20/g, "");
                                    helpLinkText = '&page='+pageParam;
                                    link = '<?=$schema ."://". $wordpressServerName ."/wordpress/".$restRoute?>' + helpLinkText + '<?="&JWT=".$jwt?>';
                                }
                            }
                            // End link construction
                            var instance = tippy("#" + data[index]['fieldName'], {
                            theme: 'custom',
                            content: '<div> <p> '+ data[index]["tooltip"] +' </p> <br>'
                            + '<a id=\"linkTool\" target=\"_blank\" href=' + link +' > Learn More.. </a>'
                            + '</div>',
                            allowHTML: true,
                            interactive: true, // prevent closing on mouseenter
                            placement: 'top', // position
                            });
                        } else { // else construct Tippy without "Learn More".
                            var instance = tippy("#" + data[index]['fieldName'], {
                            theme: 'custom',
                            content: '<div class="tippy_div"> <p> '+ data[index]["tooltip"] +' </p>'
                            + '</div>',
                            allowHTML: true,
                            interactive: true, // prevent closing on mouseenter
                            placement: 'top', // position
                            });
                        }
                        
                        $("#" + data[index]['fieldName']).click(function() {
                            $("#textTooltip").val(data[index]["tooltip"]);
                            $("#textHelp").val(data[index]["help"]);
                        });

                        $("#hideTooltip").click(function() {
                            $(this).addClass('hideTippy');
                            // change button text and color.
                            $(this).text("Tooltip Off");
                            $(this).css("background","#cc0000");

                            if( instance[0] != undefined ) {
                                var tippyDiv = instance[0]["popper"];
                                if(tippyDiv) {
                                    $(tippyDiv).hide(); // hide Tooltips.
                                } 
                            }
                        });
                    });
                } else {
                    alert(data['error']);
                }
            } else {
                alert('error: no status');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
           // alert('error'); // disable error in case of actions like login, logout.
        }
    });

   
    // Modal actions:
    $("#editTooltip").click(function() {

        var inputId, nameId;
        $("input[type=text]:not('.noTooltip'), select:not('.noTooltip'), textarea:not('#textTooltip, #textHelp'), input[type=radio]").click(function(ev) {
            inputId = $(this).attr('id'); // fieldName of input we clicked
            nameId = $(this).attr("name"); // fieldLabel of input we clicked
            ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.
            
            $.ajax({
                type:'POST',
                url: '../ajax/get_tooltip2.php',
                async:false,
                dataType: "json",
                data: {
                    fieldName: inputId,
                    pageName:"<?php echo $pageName;?>"
                },
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {

                            $(".modal-body #textTooltip").val(data["tooltip"]);
                            $(".modal-body #textHelp").val(data["help"]);
   
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
            $("#exampleModalTool").modal(); // use native function of Bootstrap.
        });

        // on show modal
        $('#exampleModalTool').on('show.bs.modal', function (e) {
        
            $("#labelHelp").click(function() {
                $("#informationHelp").show("slow");
            });
            
            $("#saveTooltip").click(function() {
                if($("#textTooltip").val() == "") { //tooltip text is required.
                    alert("Please fill tooltip text. If you want to delete this tooltip, go to tooltiplist.php");
                } else {
                    $.ajax({
                    type:'POST',
                    url: '../ajax/set_tooltip.php',
                    async:false,
                    dataType: "json",
                    data: {
                        pageName:"<?php echo $pageName;?>",
                        fieldName: inputId,
                        fieldLabel: nameId,
                        textTooltip: $("#textTooltip").val(),
                        textHelp: $("#textHelp").val()
                    },
                    success: function(data, textStatus, jqXHR) {

                        if (data['status']) {
                            if (data['status'] == 'success') {
                               
                                link = "";
                                if(data[0]["help"]) {
                                    var strHelp = data[0]["help"]; // the entry from DB.
                                    var pattern = /^((http|https|ftp):\/\/)/; // check if we have http or https at the begining

                                    if(pattern.test(data[0]["help"])) {
                                        link = data[0]["help"];
                                    } else {
                                        if( data[0]["help"].indexOf('/') >= 0) { // if page and parameter
                                            pageParam = strHelp.substring(0, strHelp.indexOf('/'));
                                            pageId = strHelp.substring(strHelp.indexOf('/') + 1);
                                            
                                            pageParam = pageParam.replace(/%20/g, "");
                                            pageId = pageId.replace(/%20/g, "");
                                            pageId = pageId.replace(/#/g, "");

                                            helpLinkText = '&page='+pageParam+'&pageId='+pageId;
                                            link = '<?=$schema ."://". $wordpressServerName ."/wordpress/".$restRoute?>' + helpLinkText + '<?="&JWT=".$jwt?>';
                                        } else { // only page
                                            pageParam = data[0]["help"].replace(/%20/g, "");
                                            helpLinkText = '&page='+pageParam;
                                            link = '<?=$schema ."://". $wordpressServerName ."/wordpress/".$restRoute?>' + helpLinkText + '<?="&JWT=".$jwt?>';
                                        }
                                    }
                                    // check if tippy exists.
                                    var control = document.querySelector("#" + data[0]['fieldName']);
                                    if(control._tippy) {
                                        control._tippy.setContent( '<div> <p> '+ data[0]["tooltip"] +' </p> <br>'
                                            + '<a id=\"linkTool\" target=\"_blank\" href=\"' + link + '\"> Learn More.. </a>'
                                            + '</div>');
                                    } else {
                                        // build new tippy
                                        tippy("#" + data[0]['fieldName'], {
                                        theme: 'custom',
                                        content: '<div> <p> '+ data[0]["tooltip"] +' </p> <br>'
                                        + '<a id=\"linkTool\" target=\"_blank\"  href=\"' + link + '\">  Learn More.. </a>'
                                        + '</div>',
                                        allowHTML: true,
                                        interactive: true, // prevent closing on mouseenter
                                        placement: 'top', // position
                                        });
                                    }
                                } else {
                                    // if tippy exists
                                    var control = document.querySelector("#" + data[0]['fieldName']);
                                    if(control._tippy) {
                                        control._tippy.setContent( '<div> <p> '+ data[0]["tooltip"] +' </p> <br>'
                                            + '</div>');
                                    } else {
                                        // build new tippy
                                        tippy("#" + data[0]['fieldName'], {
                                        theme: 'custom',
                                        content: '<div> <p> '+ data[0]["tooltip"] +' </p> <br>'
                                        + '</div>',
                                        allowHTML: true,
                                        interactive: true, // prevent closing on mouseenter
                                        placement: 'top', // position
                                        });
                                    }
                                }
                                $('#exampleModalTool').modal('hide');

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
                    delete fieldName, fieldLabel, textTooltip, textHelp;
                } // end if else Tooltip is Required.

            });
        });

        $('#exampleModalTool').on('hide.bs.modal', function (e) { 
            $("#informationHelp").hide(); // hide Help Text Link Information.                     
        });

        // on hover change background color
        $("input[type=text]:not('.noTooltip'), select:not('.noTooltip'), textarea:not('#textTooltip, #textHelp'), input[type=radio]").hover(function() {
            $(this).css("background", "#dee2e6");
        },
        function() {
            $(this).css("background", "#FFF");
        });
        var $this = $(this);
        $this.toggleClass('editMode');
        if($this.hasClass('editMode')) {
            $("#smallModal").modal();
            $this.text('Exit Edit Mode');
        } else {
            location.reload();
        }
  
    });

});  
</script> 

<!-- Welcome edit mode - Small modal -->
<div class="modal fade bd-example-modal-sm" tabindex="-1" role="dialog" id="smallModal" aria-labelledby="mySmallModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Welcome, sir!</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body">
            <p><strong>You are now in Edit Tooltip Mode.</strong></p>
            <p><strong>When you're done click on <span id="exitModeId">Exit Edit Mode</span> button.</strong></p>
        </div>
    </div>
  </div>
</div>

<!-- End @Welcome edit mode -->

<!-- Edit mode - Tooltip modal -->
<div class="modal fade" id="exampleModalTool" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTool" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tooltip | Help Text</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group">
            <label for="tooltip-text" class="col-form-label" >Tooltip Details:</label>
            <textarea class="form-control" name="textTooltip" id="textTooltip" value="" required></textarea>
          </div>
          <div class="form-group">
            <label for="message-text" class="col-form-label" id="labelHelp">Help Text: &nbsp;<i class="fas fa-info-circle" title="click for Info"></i></label>
            <textarea class="form-control mb-4" name="textHelp" id="textHelp" value="" ></textarea>
            <div class="form-group" id="informationHelp" style="display:none; background-color: #f2f2f2; padding:10px">
                <p>Page with parameter(<?=$pageName;?>/nickname) : page/parameter</p>
                <p>Simple Page(name of the page): page</p>
                <p>External URL: https://www.google.com/</p>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" id="saveTooltip" class="btn btn-info">Save</button>
      </div>
    </div>
  </div>
</div>
<!-- Edit Mode - end Tooltip modal -->
</body>
</html>
<!-- End Add tooltip system -->