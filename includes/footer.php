<?php
/* includes/footer.php

   EXECUTIVE SUMMARY: footer for regular displayed Panther pages.
   
   This is actually rather voluminous.
*/

require_once dirname(__FILE__).'/../inc/config.php'; // ADDED 2019-02-13 JM 
?>
<div id="btmbar">&nbsp;</div>
    <div id="footerLinks">
        <div id="footerBar" class="clearfix">
            <ul>
               <li>
                    <p>Helpful Links </p>
                    <ul>
                        <li><a id="linkHome" href="/">home</a></li>
                        <li><a id="linkAbout" href="/about">about us</a></li>
                        <li><a id="linkServices" href="/services">our services</a></li>
                        <li><a id="linkContact" href="/contact">contact us</a></li>
                    </ul>
                </li>
    
                <li>
                    <p>Professional Engineers Licensed in:</p>
                    <center><img src="/cust/<?php echo $customer->getShortName(); ?>/img/flag.png" width="120" /></center>
                </li>
                <li style="margin-left:10px;">
                    <p>&nbsp;</p>
                    <center><img src="/cust/<?php echo $customer->getShortName(); ?>/img/certificate.png" height="75" /></center>
                </li>
                <li style="margin:8px 0 0 35px;">
                    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" id="paypalForm">
                        <input type="hidden" name="cmd" value="_s-xclick">
                        <?php
                        /*
                        OLD CODE removed 2019-02-04 JM
                        // (actual old code didn't use PHP at all, but this allows consistent commenting approach)
                        echo '<input type="hidden" name="hosted_button_id" value="Q3T6XPT5U7ASL">';
                        */
                        // BEGIN NEW CODE 2019-02-04 JM
                        echo '<input type="hidden" name="hosted_button_id" value="'.PAYPAL_HOSTED_BUTTON_ID.'">';
                        // BEGIN NEW CODE 2019-02-04 JM
                        ?>
                        <input type="image" src="/cust/<?php echo $customer->getShortName(); ?>/img/bml-logo.png" border="0" name="submit" id="submitPayPal" alt="PayPal - The safer, easier way to pay online!">
                        <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                    </form>
                </li>
            </ul>
        </div>
    </div>
    
    <?php /* >>>00012 JM: do we really want to call the DIV "credibilityContainer"? 
             While not immediately visible in the browser, this is publicly visible to anyone who looks at source). */ ?>
    
    <div id="credibilityContainer">
        <div id="copyright">
            <?php
            /*
            OLD CODE removed 2019-02-04 JM
            // (actual old code didn't use PHP at all, but this allows consistent commenting approach)
            echo '<p> &copy; <?php echo date ('Y'); ?> Sound Structural Solutions, Inc. &#0153; &nbsp;&nbsp;|&nbsp;&nbsp;  24113 56th Ave W &nbsp;&nbsp;|&nbsp;&nbsp; Mountlake Terrace, WA 98043 &nbsp;&nbsp;|&nbsp;&nbsp; 425.778.1023 &nbsp;&nbsp;|&nbsp;&nbsp; <a href="mailto:inbox@ssseng.com">inbox@ssseng.com</a> </p>';
            */
            // BEGIN NEW CODE 2019-02-04 JM
            echo '<p> &copy; '. date ('Y').' '.CUSTOMER_FULL_NAME;
            if (CUSTOMER_USES_REGISTERED_TM) {
                echo ' &reg;';
            } else if (CUSTOMER_USES_TM) {
                echo ' &trade;'; 
            }
            echo ' &nbsp;&nbsp;|&nbsp;&nbsp;  '.CUSTOMER_STREET_ADDRESS.' &nbsp;&nbsp;|&nbsp;&nbsp; '.CUSTOMER_CITY_AND_ZIP.
                 ' &nbsp;&nbsp;|&nbsp;&nbsp; '.CUSTOMER_PHONE_FOR_FOOTER.' &nbsp;&nbsp;|&nbsp;&nbsp; '.
                 '<a href="mailto:'.CUSTOMER_INBOX.'">'.CUSTOMER_INBOX.'</a>'.
                 '<br />Version ' . VERSION . // Reporting version added 2020-01-21
                 '</p>';
            // END NEW CODE 2019-02-04 JM
            // [CP] 20201006 - href with customer_inbox will attach to current page address the inbox. I added the protocol mailto before the email address in order to open the email client.
            ?>		
        </div>
            
    </div>
</div>

<?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 ?>
        <div class="slide-out-div">
            <a class="handle" href="#">Content</a>
            <table border="0" cellpadding="0" cellspacing="0" style="float:left;">
            <tr>
            <td>
            <?php

            $recents = $crumbs->getCrumbs();

            foreach ($recents as $rkey => $recent){

                // other ruses besides ending in 'y' needs to be 'ies'
                echo '<h3 style="float:left;">' . ((substr($rkey, -1) == 'y') ? substr($rkey,0,(strlen($rkey) - 1)) . 'ies' : $rkey . 's') .  '</h3><br><br>';

                foreach ($recent as $crkey => $cr){

                    $class = $rkey;
                    $object = new $class($cr);

                    if ($rkey == 'WorkOrder'){
                        echo '<p><a  class="async-workorderid" rel="' . $object->getCrumbId() . '"  tx="' . htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . htmlspecialchars($object->getName()) . '</a></p>';
                    } else if ($rkey == 'Person'){
                        echo '<p><a  class="async-personid" rel="' . $object->getCrumbId() . '"  tx="' . htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . htmlspecialchars($object->getName()) . '</a></p>';
                    } else {
                        echo '<p><a href="' . $object->buildLink() . '">' . htmlspecialchars($object->getName()) . '</a></p>';
                    }

                }

            }

            if (isset($_SESSION['searches'])){

                if (count($_SESSION['searches'])){

                    echo '<h3 style="float:left;">Searches</h3><br><br>';

                    foreach ($_SESSION['searches'] as $skey => $search){

                        echo '<p><a href="/panther.php?act=search&q=' . rawurlencode($search) . '">' . htmlspecialchars($search) . '</a></p>';


                    }


                }

            }
          //  print_r($recents);


            ?>
            <br>
            <br>
            <br>
            <a href="/clearcrumbs.php?uri=<?php echo rawurlencode($_SERVER['REQUEST_URI']); ?>">clear</a>
            </td>
            </tr>
            </table>
        </div>
<?php END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>

<?php
    // INPUT $str - string
    // Return the string with any non-breaking spaces stripped (>>>00001 oddly, not just turned into normal
    //   spaces, which is what I'd expect - JM)
    function removeNbsp($str) {
        $str = trim($str);
        $str = str_replace("&nbsp;", "", $str);
        return $str;
    }
    
    if (isset($user) && $user && $user->getUserId()) { // isset check added 2019-05-31 JM, $user added 2019-10-23 JM because it can apparently be Boolean false.
                                                       // That should address http://bt.dev2.ssseng.com/view.php?id=42.
        // Someone is logged in, so display their crumbs
?>
        <div id="ssss">
            <img src="/cust/<?php echo $customer->getShortName(); ?>/img/recents.png" alt="Feedback" />
                <div id="ssss_inner">
                    <a id="linkCrumbsViewAll" href="/crumbs">view all</a>
                    <table border="0" cellpadding="0" cellspacing="0" style="float:left;">
                        <tr>
                            <td>
                            <?php
                                $crumbs = new Crumbs(null,  isset($user) ? $user : null);                    
                                $newCrumbs = $crumbs->getNewCrumbs();                    
                                $indexed = array();                    
                                if (is_array($newCrumbs)) {
                                    foreach ($newCrumbs as $key => $newCrumb) {
                                        $indexed[] = array('name' => $key, 'data' => $newCrumb);
                                    }
                                }

                                // $recents = $crumbs->getCrumbs(); // Commented out by Martin before 2019
                                echo '<div style="font-size:74%"><table border="0" cellpadding="4" cellspacing="0">';
                                
                                foreach ($indexed as $work) {
                                    echo '<tr><td><h4 style="float:left;">' . 
                                          ((substr($work['name'], -1) == 'y') ? 
                                              substr($work['name'],0,(strlen($work['name']) - 1)) . 'ies' : 
                                              $work['name'] . 's') .  
                                          '</h4></td></tr>';
                                    if (is_array($work['data'])) {
                                        $work['data'] = array_slice($work['data'],0,5);
                                    }

                                    if (count($work['data'])) {
                                        echo '<tr><td>';
                                        echo '<table border="0" cellpadding="4" cellspacing="0">';
                                            foreach ($work['data'] as $record) {
                                                if ($work['name'] == 'Search') {
                                                    echo '<tr><td><a href="/panther.php?act=search&q=' . rawurlencode($record['id']) . '">' . 
                                                         $record['id'] . '</a></td></tr>';
                                                } else {
                                                    $class = $work['name'];
                                                    $object = new $class($record['id']);

                                                    // JM 2019-12-04: dealing with issues related to http://bt.dev2.ssseng.com/view.php?id=53
                                                    // (better supporting longer job names)
                                                    // CODE REPLACED JM 2019-12-04
                                                    /*                                                    
                                                    if ($class == 'WorkOrder'){
                                                        echo '<tr><td><a  class="async-workorderid" rel="' .$record['id'] . '"  tx="' . 
                                                             htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . 
                                                             htmlspecialchars(removeNbsp($object->getName())) . '</a></td></tr>';
                                                    } else if ($class == 'Person'){
                                                        echo '<tr><td><a  class="async-personid" rel="' . $record['id'] . '"  tx="' . 
                                                             htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . 
                                                             htmlspecialchars(removeNbsp($object->getName())) . '</a></td></tr>';
                                                    } else {
                                                        echo '<tr><td><a href="' . $object->buildLink() . '">' . 
                                                             htmlspecialchars(removeNbsp($object->getName())) . '</a></td></tr>';
                                                    }
                                                    */
                                                    // BEGIN REPLACEMENT CODE JM 2019-12-04
                                                    $optional_ellipsis = '';
                                                    $displayname = removeNbsp($object->getName());
                                                    if (strlen($displayname) > 30 ) {
                                                        $displayname = substr($displayname, 0, 30);
                                                        $optional_ellipsis = '&hellip;';
                                                    }
                                                    
                                                    if ($class == 'WorkOrder'){
                                                        echo '<tr><td><a id="linkWorkOrder' .$record['id'] . '" class="async-workorderid" rel="' .$record['id'] . '"  tx="' . 
                                                             htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . 
                                                             htmlspecialchars($displayname) . $optional_ellipsis . '</a></td></tr>';
                                                    } else if ($class == 'Person'){
                                                        echo '<tr><td><a  id="linkPerson' .$record['id'] . '"  class="async-personid" rel="' . $record['id'] . '"  tx="' . 
                                                             htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . 
                                                             htmlspecialchars($displayname) . $optional_ellipsis . '</a></td></tr>';
                                                    } else {
                                                        echo '<tr><td><a id="linkObject'.preg_replace("/[^a-zA-Z0-9]/", "", $displayname).'" href="' . $object->buildLink() . '">' . 
                                                             htmlspecialchars($displayname) . $optional_ellipsis . '</a></td></tr>';
                                                    }
                                                    // END REPLACEMENT CODE JM 2019-12-04

                                                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                                    //echo '<tr><td><a href="' . $object->buildLink() . '">' . $object->getName() . '</a>&nbsp;[' . date("m/d/Y",$record['time']) . ']</td></tr>';
                                                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                                                }
                                            }
                                        echo '</table>';
                                        echo '</td></tr>';
                                    }

                                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                    //echo '<tr><td>&nbsp;</td></tr>';                    
                                    //echo '<br>';
                                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                                } // END foreach ($indexed
                                
                                echo '</table></div>';

                                /*
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                foreach ($recents as $rkey => $recent){
                                    // other ruses besides ending in 'y' needs to be 'ies'
                                    echo '<h3 style="float:left;">' . ((substr($rkey, -1) == 'y') ? substr($rkey,0,(strlen($rkey) - 1)) . 'ies' : $rkey . 's') .  '</h3><br><br>';
                    
                                    foreach ($recent as $crkey => $cr){
                    
                                        $class = $rkey;
                                        $object = new $class($cr);
                    
                                        if ($rkey == 'WorkOrder'){
                                            echo '<p><a  class="async-workorderid" rel="' . $object->getCrumbId() . '"  tx="' . htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . htmlspecialchars(removeNbsp($object->getName())) . '</a></p>';
                                        } else if ($rkey == 'Person'){
                                            echo '<p><a  class="async-personid" rel="' . $object->getCrumbId() . '"  tx="' . htmlspecialchars($object->getName()) . '" href="' . $object->buildLink() . '">' . htmlspecialchars(removeNbsp($object->getName())) . '</a></p>';
                                        } else {
                                            echo '<p><a href="' . $object->buildLink() . '">' . htmlspecialchars(removeNbsp($object->getName())) . '</a></p>';
                                        }
                                    }
                                }
                    
                                if (isset($_SESSION['searches'])){
                                    if (count($_SESSION['searches'])){
                                        echo '<h3 style="float:left;">Searches</h3><br><br>';
                                        foreach ($_SESSION['searches'] as $skey => $search){
                                            echo '<p><a href="/panther.php?act=search&q=' . rawurlencode($search) . '">' . htmlspecialchars($search) . '</a></p>';
                                        }
                                    }
                                }
                                // END COMMENTED OUT BY MARTIN BEFORE 2019
                    
                                */
                    
                                //  print_r($recents); // COMMENTED OUT BY MARTIN BEFORE 2019
                    
                            ?>
                            <a id="crumbsViewAll" href="/crumbs">view all</a>
                            <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 ?>
                            <a href="/clearcrumbs.php?uri=<?php echo rawurlencode($_SERVER['REQUEST_URI']); ?>">clear</a>
                            <?php END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
<?php
    }
?>

<?php /* George 2021-02-09 get file name and ajax call to tooltip table. */ ?>
<?php 

require_once BASEDIR . '/generate_jwt.php'; // to get the token $jwt

$pageId = "";
$pageName = "";

$pageName = basename($_SERVER["SCRIPT_FILENAME"], '.php');
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
    $("#hideTooltip").click(function(){
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
                            var instance = tippy("#" + data[index]['fieldName'], {
                            theme: 'custom',
                            content: '<div class="tippy_div"> <p> '+ data[index]["tooltip"] +' </p> <br>'
                            + '<a id=\"linkTool\" target=\"_blank\" href=' + link +' > Learn More.. </a>'
                            + '</div>',
                            allowHTML: true,
                            interactive: true, // prevent closing on mouseenter
                            placement: 'right-end', // position
                            });
                        } else { // else construct Tippy without "Learn More".
                            var instance = tippy("#" + data[index]['fieldName'], {
                            theme: 'custom',
                            content: '<div class="tippy_div"> <p> '+ data[index]["tooltip"] +' </p>'
                            + '</div>',
                            allowHTML: true,
                            interactive: true, // prevent closing on mouseenter
                            placement: 'right-end', // position
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

        $("input[type=text]:not('.noTooltip'), select:not('.noTooltip'), textarea:not('#textTooltip, #textHelp')").click(function(ev) {
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
                                        placement: 'right-end', // position
                                        });
                                    }
                                } else {
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
                                        placement: 'right-end', // position
                                        });
                                     }
                                }

                                $('#exampleModalTool').modal('hide'); // close modal.
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
        // on hover change background color.
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
                <label for="tooltip-text" class="col-form-label">Tooltip Details:</label>
                <textarea class="form-control" name="textTooltip" id="textTooltip" value="" required></textarea>
            </div>
            <div class="form-group">
                <label for="message-text" class="col-form-label" id="labelHelp"> Help Text Link: &nbsp;<i class="fas fa-info-circle" title="click for Info"></i></label>
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
</body>
</html><!-- Localized -->
