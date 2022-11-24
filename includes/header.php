<!DOCTYPE html>
<?php 
/*  includes/header.php

    EXECUTIVE SUMMARY: Header for all normal PHP pages in Panther.
    
    >>>00001 JM: This is pretty voluminous, and as of 2019-04 I can't really say I've studied it closely.
    A lot of bringing in code that is used elsewhere.
    
    NOTE that at the end of this, the BODY of the HTML document is open.
*/ 
if (LIVE_SYSTEM) { // NOTE that this requires that the header be included by inc/config.php AFTER this constant is defined
    $bgcolor = "#ffffff";
} else {
    $bgcolor = "#e8cccc";
}
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <?php
        if ($customer) {        
            /* In the following, Martin said 2018-11-26 that the v=333 in /css/main.css?v=333 is "for caching" 
               >>>00014 JM 2019-04: I don't get that: I'd understand a pseudo-random number to prevent caching, 
               but what is the point to a constant)? */ 
    ?>
            <link href="/cust/<?php echo $customer->getShortName(); ?>/css/main.css?v=333" rel="stylesheet"/>
            <link href="/cust/<?php echo $customer->getShortName(); ?>/css/admin.css" rel="stylesheet"/>
            <link href="/cust/<?php echo $customer->getShortName(); ?>/css/buttons.css" rel="stylesheet"/>
            <link href="/cust/<?php echo $customer->getShortName(); ?>/css/responsive.css" rel="stylesheet"/>
            <link href="/cust/<?php echo $customer->getShortName(); ?>/css/new.css" rel="stylesheet"/>
            
            <style type="text/css">
                .counters {
                    display:block;
                    position:absolute;
                    background:#E1141E;
                    color:#FFF;
                    font-size:12px;
                    font-weight:normal;
                    padding:1px 3px;
                    margin:-8px 0 0 25px;
                    border-radius:2px;
                    -moz-border-radius:2px; 
                    -webkit-border-radius:2px;
                    z-index:1;
                }
                #ssss {
                        position: fixed;
                        top: 10px;
                        left: 0;
                        width: 35px;
                        padding: 12px 0;
                        text-align: center;
                        background: #999999;
                        -webkit-transition-duration: 0.3s;
                        -moz-transition-duration: 0.3s;
                        -o-transition-duration: 0.3s;
                        transition-duration: 0.3s;
                        -webkit-border-radius: 0 5px 5px 0;
                        -moz-border-radius: 0 5px 5px 0;
                        border-radius: 0 5px 5px 0;
                }
                #ssss_inner {
                    position: fixed;
                    top: 10px;
                    left: -250px;
                    background: #dddddd;
                    width: 200px;
                    padding: 25px;
                
                    -webkit-transition-duration: 0.3s;
                    -moz-transition-duration: 0.3s;
                    -o-transition-duration: 0.3s;
                    transition-duration: 0.3s;
                    text-align: left;
                    -webkit-border-radius: 0 0 5px 0;
                    -moz-border-radius: 0 0 5px 0;
                    border-radius: 0 0 5px 0;
                }
                #ssss_inner textarea {
                    width: 190px;
                    height: 100px;
                    margin-bottom: 6px;
                }
                #ssss:hover {
                    left: 250px;
                }
                #ssss:hover #ssss_inner {
                    left: 0;
                }
            </style>
        
    <?php
        } // END if ($customer)
    ?>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js" ></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>

        <script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <!-- Tooltip -->
    <style>
     
  
        .clearfix:after {
             visibility: hidden;
             display: block;
             font-size: 0;
             content: " ";
             clear: both;
             height: 0;
        }
        .clearfix { display: inline-block; }
         
        /* Martin comment: start commented backslash hack \*/
        * html .clearfix { height: 1%; }
        .clearfix { display: block; }
        /* Martin comment: close commented backslash hack */        
        
        .dialog-data {
            background-color:blue;
        }
    </style>
    
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    
    <?php 
        // >>>00014 JM: absolutely no idea why invoice.php is excluded here. George 2021-04-29. Remove the if condition.
        // if ($_SERVER['SCRIPT_NAME'] != '/invoice.php') {   
    ?>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

        <link rel="stylesheet" href="/js/poshytip/tip-yellowsimple/tip-yellowsimple.css" type="text/css" />


        <script type="text/javascript" src="/js/poshytip/jquery.poshytip.js"></script>
        <script type="text/javascript" src="/js/poshytip/index.js"></script>
    <?php 
       // }
    ?>  
    <!--  -->
        <script type="text/javascript" src="/includes/fancybox/jquery.fancybox.js?v=2.1.5"></script>
        <link rel="stylesheet" type="text/css" href="/includes/fancybox/jquery.fancybox.css?v=2.1.5" media="screen" />  
        
        <script type="text/javascript" src="/js/jquery.tabSlideOut.v1.3.js"></script>

        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

        <script src="https://kit.fontawesome.com/e85b454207.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js" ></script>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/additional-methods.min.js" ></script>
        <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
        <script src="https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"></script>


    <?php
        if ($customer) {
    ?>
            <script type="text/javascript">
                $(function() {
                    <?php /* Comments here are either Martin's or inherited from a third party. */ ?>
                    $('.slide-out-div').tabSlideOut({
                        tabHandle: '.handle',                     //class of the element that will become your tab
                        pathToTabImage: '/cust/<?php echo $customer->getShortName(); ?>/img/Recent.png', //path to the image for the tab //Optionally can be set using css
                        imageHeight: '122px',                     //height of tab image           //Optionally can be set using css
                        imageWidth: '40px',                       //width of tab image            //Optionally can be set using css
                        tabLocation: 'left',                      //side of screen where tab lives, top, right, bottom, or left
                        speed: 3000,                               //speed of animation
                        action: 'hover',                          //options: 'click' or 'hover', action to trigger animation
                        topPos: '200px',                          //position from the top/ use if tabLocation is left or right
                        leftPos: '20px',                          //position from left/ use if tabLocation is bottom or top
                        fixedPosition: false                      //options: true makes it stick(fixed position) on scroll
                    });        
                });
            </script>   
            <link href="/cust/<?php echo $customer->getShortName(); ?>/css/recent_slideout.css" rel="stylesheet"/>
        
    <?php
        }
    ?>
        <script type="text/javascript">
            var woturl = '';
            
            $(function() {
                $(".fancyboxIframe").fancybox({
                    maxWidth    : 1024,
                    maxHeight   : 1000,
                    fitToView   : false,
                    width       : '90%',
                    height      : '95%',
                    autoSize    : false,
                    closeClick  : false,
                    openEffect  : 'none',
                    closeEffect : 'none',
                iframe: {
                    scrolling : 'auto',
                    preload   : true
                },
                "afterClose": function(){
                    if (woturl.length){
                        document.location.href = woturl;
                    } else {
                        parent.location.reload(true);
                    }
                }
                });
            });
            
            // BEGIN ADDED 2020-04-21 JM for http://bt.dev2.ssseng.com/view.php?id=130 
            $(function() {
                $(".fancyboxIframeWide").fancybox({
                    maxWidth    : 1280,
                    maxHeight   : 1000,
                    fitToView   : false,
                    width       : '98%',
                    height      : '95%',
                    autoSize    : false,
                    closeClick  : false,
                    openEffect  : 'none',
                    closeEffect : 'none',
                iframe: {
                    scrolling : 'auto',
                    preload   : true
                },
                "afterClose": function(){
                    if (woturl.length){
                        document.location.href = woturl;
                    } else {
                        parent.location.reload(true);
                    }
                }
                });
            });
            // END ADDED 2020-04-21 JM for http://bt.dev2.ssseng.com/view.php?id=130


            $(function() {
                $( "#helpdialog" ).dialog({  autoOpen: false,width:10,height:20 });

                $( ".helpopen" ).mousedown(function() {
                    $( "#helpdialog" ).dialog({
                        position: { my: "center bottom", at: "center top", of: $(this) },
                        autoResize:true ,
                        open: function(event, ui) {
                        $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                        $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
                        }
                    });

                    var helpId = $(this).attr('id');    
                    $("#helpdialog").dialog("open").html(
                        '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').dialog({height:'45', width:'auto'})
                        .load('/ajax/helpajax.php?helpId=' + escape(helpId) , function(){
                            $('#helpdialog').dialog({height:'auto', width:'auto'});
                        });
                });
                
                $( ".helpopen" ).mouseleave(function() {
                    $( "#helpdialog" ).dialog("close");
                });
            });
        
            var repeatCount = function() {
                $.ajax({
                    url: '/ajax/ticket_opencount.php',
                    async:true,
                    type:'post',
                    success: function(data, textStatus, jqXHR) {                        
                        if (data['status']) {
                            if (data['status'] == 'success') { // [T000016] 
                                $('#noti_Counter')
                                    .css({ opacity: 0.5 })
                                    // BEGIN REPLACED JM 2019-12-16
                                    //.text(data['count'])
                                    // END REPLACED JM 2019-12-16
                                    // BEGIN REPLACEMENT JM 2019-12-16
                                    .html(data['count'])
                                    // END REPLACEMENT JM 2019-12-16
                                    setTimeout( repeatCount, 12000 );
                            } else {
                                <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                                //location.reload();
                                //alert('error not success');
                                <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                            }
                        } else {
                            <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                            //location.reload();
                            //alert('error no status');
                            <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                        //location.reload();
                        //alert('error');
                        <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                    }
                }); // end $.ajax
            } // END function repeatCount

            var repeatReviewCount = function() {
                $.ajax({
                    url: '/ajax/reviewcount.php',
                    async:true,
                    type:'post',
                    success: function(data, textStatus, jqXHR) {
                        //location.reload();
                        if (data['status']) {
                            if (data['status'] == 'success') { // [T000016]
                                $('#review_Counter')
                                    .css({ opacity: 0.5 })
                                    // BEGIN REPLACED JM 2019-12-16
                                    //.text(data['count'])
                                    // END REPLACED JM 2019-12-16
                                    // BEGIN REPLACEMENT JM 2019-12-16
                                    .html(data['count'])
                                    // END REPLACEMENT JM 2019-12-16
                                setTimeout( repeatReviewCount, 12000 );                            
                            } else {
                                <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                                //location.reload();
                                //alert('error not success');
                                <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                            }
                        } else {
                            <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                            //location.reload();
                            //alert('error no status');
                            <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                        }                    
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                        //location.reload();
                        //alert('error');
                        <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                    }
                });
            } // // END function repeatReviewCount
            
            <?php /* conditional added 2019-06-07 JM; content within was already there.
            If this is (for example) the login page, and no one is logged in, it makes no sense
            to try to give them information about their task count, etc.
            */ 
//OLD CODE REMOVED            if ($user) {  
            if (isset($user) && $user) {
            ?>
                $(document).ready(function () {
                    // Martin comment: ANIMATEDLY DISPLAY THE NOTIFICATION COUNTER.
                    <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
                    //   $('#noti_Counter')
                    //     .css({ opacity: 0.5 })
                    //   .text('100')              // ADD DYNAMIC VALUE (YOU CAN EXTRACT DATA FROM DATABASE OR XML).
                    <?php /* END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>     
         
                    repeatCount();
                    repeatReviewCount();
                });
            <?php 
            } /* END conditional added 2019-06-07 JM */
            ?>
        </script>       
    <?php /* 
    // BEGIN COMMENTED OUT BY JM 2019-04: there is no SCRIPT element to close here  
    </script>
    // END COMMENTED OUT BY JM 2019-04
    */ ?>
    
</head>
<?php
//require_once './inc/perms.php';
require_once BASEDIR . '/generate_jwt.php';

$pageName = "";
$pageName = basename($_SERVER["SCRIPT_FILENAME"], '.php');

$linkWordPress = $schema ."://". $wordpressServerName ."/wordpress/".$restRoute. "&page=". $pageName ."&JWT=".$jwt;

//$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

$newLink= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://old.ssseng.com".str_replace("//", "/",$_SERVER['REQUEST_URI']);
?>
<body>
<style>
/* disable pointer events on body*/
body {
  pointer-events:none;
}

#adminPath:hover {
    color: #fff;
}
#mainHeaderSticky {
    position: -webkit-sticky;
    position: sticky;
    top: 0;
    z-index: 20;
}



</style>
<script>
//activate all pointer-events on body
$(document).ready(() => {
    $('body').css('pointer-events', 'all');
});


</script>

<script>
    // copy URL function
    function copyToClipGeneralPages(str) {
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
        $('#copyLinkGeneralPages').on("click", function (e) {

        $(this).text('Copied');
        });
        $("#copyLinkGeneralPages").tooltip({
            content: function () {
                return "Copy Url Link";
            },
            position: {
                my: "center bottom",
                at: "center top"
            }
        });

        $('.notify-bubble').show(300);
    });
</script>
<style>
  #copyLinkGeneralPages {
        color: #000;
        font-family: Roboto,"Helvetica Neue",sans-serif;
        font-size: 12px;
        font-weight: 600;
    }

    #copyLinkGeneralPages:hover {
        color: #fff;
        font-size: 12px;
        font-weight: 600;
    }
    #firstLinkToCopyGeneralPages {
        color: #000;
        font-size: 18px;
        font-weight: 700;
    }

        
    .notify-bubble {
        position: absolute;
        top: -8px;
        right: -7px;
        padding: 2px 5px 2px 6px;
        background-color: #dc3545;
        color: white;
        font-size: 0.65em;
        border-radius: 50%;
        box-shadow: 1px 1px 1px gray;
        display: none;
        font-weight: 700;
    }
</style>
<div id="helpdialog" ><?php /* We use this DIV to show dynamic help text (info icon at top of certain PHP files) */ ?>
</div>

<div id="max">
<div>You are in the NEW Panther 
<a href="<?= $newLink ?>">Go to Legacy PANTHER</a> 
</div>  
    <div id="mainHeaderSticky" >
        <div id="logoBar" class="clearfix" style="background-color:<?php echo $bgcolor; ?>">
            <div id="logo"> <a href="/"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/header_logo.jpg" /></a> </div>
            <div id="loginstatus">
            <?php 
                if (isset($user) && $user) { // isset check added 2019-05-30 JM        
                    echo '<table border="0" cellpadding="0" cellspacing="0" style="background-color:#e8cccc">';
                    echo '<tr>';
                        echo '<td style="text-align:left;">';
                            echo $user->getUsername();
                            if (OFFER_BUG_LOGGING) {
                                echo '&nbsp;|&nbsp;<a id="logBug" href="'.BUG_LOG.'" target="_blank">log a bug</a>';
                            }                        
                            echo '&nbsp;|&nbsp;<a id="logOut" href="/?act=logout">logout</a>';
                        

                            if(!$user->isEmployee()) {
                                echo '&nbsp;|&nbsp&nbsp;<button type="button" class="btn btn-info btn-sm" id="hideTooltip">Hide Tooltip</button>'; 
                                echo '&nbsp;|&nbsp&nbsp;<a class="btn btn-outline-success btn-sm" target="_blank" href="'.$linkWordPress.'">User Manual</a>';
                            }
                        echo '</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<td style="text-align:right;">';
                
                            /* In the following, Martin said 2018-11-26 that $lol_array 
                                "was for an exception that has now been removed" 
                                >>>00007 JM: so presumably we can we get rid of $lol_array */
                            $lol_array = array(0000);
            
                            if ($user->isEmployee()) { // || in_array($user->getUserId(), $lol_array)){ // Commented out by Martin before 2019            
                                echo '<table border="0" cellpadding="2" cellspacing="0" style="float:right; background-color:#e8cccc"">';
                                    echo '<tr>';
                                    /*
                                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                        $sep = DIRECTORY_SEPARATOR;
                                        $fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '../schedule_pdfs' . $sep;
                                        if (file_exists($fileDir)){                                                
                                            $files = array();                                                
                                            $h = opendir($fileDir);                                                
                                            if ($h) {                                                    
                                                while (false !== ($entry = readdir($h))) {                                                        
                                                    if (is_file($fileDir . $entry)){
                                                        if ($entry != 'index.php'){
                                                            $parts = explode(".", $entry);
                                                            $ext = strtolower(end($parts));
                                                            if ($ext == 'pdf'){                                                                    
                                                                $files[] = $entry;                                                                    
                                                            }                                                                
                                                        }
                                                    }                                                        
                                                }
                                            }
                                            sort($files);
                                            $files = array_reverse($files);
                                            $files = array_slice($files, 0, 4);
                                            foreach ($files as $fkey => $file){
                                                echo '<td><a href="/getuploadfile.php?f=' . rawurlencode($file) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_schedule_32x32.png" width="24" height="24"></a></td>';
                                            }   
                                        }
                                        // END COMMENTED OUT BY MARTIN BEFORE 2019
                                    */  
                                    echo '<td><button type="button" class="btn btn-info btn-sm" id="editTooltip">Edit Tooltip</button></td>'; 
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    echo '<td><button type="button" class="btn btn-info btn-sm" id="hideTooltip">Hide Tooltip</button></td>'; 
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    echo '<td><a class="btn btn-outline-success btn-sm" target="_blank" href="'.$linkWordPress.'">User Manual</a></td>';
                                    
                                    // For admin
                                    echo '<td>&nbsp;&nbsp;</td>';
                                    if($user->getIsAdmin()){
                                        echo '<td>
                                        <a href="/_admin" target="_blank"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_gear_48x48.png" width="24" height="24" title="Admin"></a>
                                        </td>';
                                
                                    
                                        echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                        echo '<td><a id="linkMulti" href="/multi.php?tab=5"><img src="/cust/' . $customer->getShortName() . 
                                            '/img/icons/icon_multi_48x48.png" width="24" height="24" title="Multi"></a></td>';
                                    }
                                    
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                
                                    /*
                                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                    echo '<td><a href="/invoicelist.php?isCommitted=1"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_invoice_committed_48x48.png" width="24" height="24" title="WO No Invoice 2"></a></td>';                                    
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';                                    
                                    echo '<td><a href="/invoicelist.php?isCommitted=0"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_invoice_uncommitted_48x48.png" width="24" height="24" title="WO No Invoice 2"></a></td>';
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                                    */

                                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                    //echo '<td><a href="/wonoinvoice2.php"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_noinvoice_48x48.png" width="24" height="24" title="WO No Invoice 2"></a></td>';                                    
                                    //echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    // END COMMENTED OUT BY MARTIN BEFORE 2019                                    
                                    echo '<td><a id="linkPantherHome" href="/panther.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_home_64x64.png" width="24" height="24" title="Home"></a></td>';                                    
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';   
                                    echo '<td><a id="linkAlarm" href="/alarm.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_alarm_48x48.png" width="24" height="24" title="Alarms"></a></td>';                                        
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                                                  
                                    echo '<td><a id="linkOpenWorkOrders" href="/openworkorders.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_person_48x48.png" width="24" height="24" title="My Open WOs"></a></td>';
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    echo '<td><a id="linkOpenWorkOrdersEmp" href="/openworkordersemp.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_employee_48x48.png" width="24" height="24" title="Emp Open WOs"></a></td>';
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    echo '<td><div style="position:relative;"><a id="linkSms" href="/sms.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_sms_header.png" width="32" height="26" title="SMS"></a></td>';


                                    $query = " SELECT * FROM " . DB__NEW_DATABASE . ".permission  ";
                                    $definedPermissions = array();
                                    $result = $db->query($query); // CP - 2020-11-30
                                    if ($result) {
                                        if ($result->num_rows > 0){
                                            while ($row = $result->fetch_assoc()){
                                                $definedPermissions[$row['permissionIdName']] = $row;
                                            }
                                        } else {
                                            $logger->info2('1569343371333', "Permissions table is empty!");
                                        }
                                    } else {
                                        $logger->fatal2('1569342223370', "Error query on permissions table!");
                                        die();
                                    } 

                                    $employeesCurrent = $customer->getEmployees(EMPLOYEEFILTER_CURRENTLYEMPLOYED); 
                                    $arrEmpIds = [];
                                    foreach ($employeesCurrent as $employee) { 
                                        $pers = new Person($employee->getUserId());
                                        $permString = $pers->getPermissionString();
                                        
                                        $userPermissions = array();
                                        foreach ($definedPermissions as $dkey => $definedPermission) {
                                            $userPermissions[$definedPermission['permissionIdName']] = substr($permString, $definedPermission['permissionId'], 1);
                                        }
                                    
                                    
                                        $userPermsContract = ['1','2','3','5' ]; // valid PERM_CONTRACT
                                        if (array_key_exists("PERM_CONTRACT",$userPermissions))
                                        {
                                            if(in_array( $userPermissions['PERM_CONTRACT'], $userPermsContract)  ) {
                                                $arrEmpIds[] = $employee->getUserId();
                                            } 
                                        }
                                        $noteCount = [];
                                        $query = " SELECT notificationId";
                                        $query .= " FROM " . DB__NEW_DATABASE . ".contractNotification ";
                                        $query .= " WHERE reviewStatus = 0 AND reviewerPersonId = " . intval($user->getUserId()) . " "; // current user only
                                    
                                        $result = $db->query($query);
                                        if ($result) {
                                            
                                            if ($result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    $noteCount[] = $row;
                                                }
                                            }
                                            
                                        } 
                                    }
                                    if(in_array($user->getUserId(), $arrEmpIds)) {
                                        echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                        echo '<td><div style="position:relative;"><a id="linkReviews" href="/contract_reviews.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_contract_rev_48x48.png" width="24" height="24" title="Contracts"></a>'.
                                        '<span id="notify-bubble" value='. count($noteCount).' class="notify-bubble">'. count($noteCount).'</span></div></td>';
                                    }    
                   


                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    echo '<td><div style="position:relative;"><a id="linkTicket" href="/ticket.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_ticket_32x32.png" width="24" height="24" title="Tickets"></a>'.
                                        '<div class="counters" id="noti_Counter"></div></div></td>';   
                                                                         
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                                    echo '<td><div style="position:relative;"><a id="linkReviews" href="/reviews.php"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_review_32x32.png" width="24" height="24" title="Reviews"></a>'.
                                        '<div class="counters" id="review_Counter"></div></div></td>';
                                    echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';
                
                                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                    //echo '<td><a target="_blank" href="https://docs.google.com/spreadsheets/d/1WqIxnNnMtFmfsWPk0gbn_uTkKNWdL05i8vZJyE_AGIg/edit#gid=81996181"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_google_docs_64x64.png" width="24" height="24" title="Google Docs"></a></td>';                                        
                                    //echo '<td>&nbsp;</td>';
                                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                                    
                                    echo '<td><a id="linkTimeSheet" href="/time/workWeek"><img src="/cust/' . $customer->getShortName() . 
                                        '/img/icons/icon_clock_32x32.png" width="24" height="24" title="Time Sheet"></a></td>';

                                    
                                    echo '</tr>';
                                echo '</table>';                        
                            } // END if ($user->isEmployee())
                        echo '</td>';
                    echo '</tr>';                    
                    echo '</table>';        
                } // END if ($user)
                ?>
            </div>
            <?php  /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 ?>
            <div id="megaSearch">
                <div class="inner">
                    <form action="/results.php" method="get" name="searchForm">
                        <input id="megaSearchBox" placeholder="What are you looking for?" value="" name="q"  class="searchInputBox" type="text" size="30" />
                        <input type="image" src="/cust/<?php echo $customer->getShortName(); ?>/img/btn_megasearch.gif" alt="Search"/>
                    </form>
                </div>
            </div>
            <?php END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>
        
        </div>
        <div class="top-nav">
            <ul>    
            <?php 
                if (!isset($user) || !$user) { // isset check added 2019-05-30 JM
            ?>
                    <li><a id="linkHomeHeader" href="/">Home</a></li>
                    <li><a id="linkAboutHeader" href="/about">About Us</a></li>
                    <li><a id="linkServicesHeader" href="/services">Our Services</a></li>
                    <li><a id="linkContactHeader" href="/contact">Contact Us</a></li>
                    <li><a id="linkPantherHeader" href="/panther">Panther</a></li>
            <?php 
                }

                if (isset($user) && $user) { // isset check added 2019-05-30 JM            
                    echo '<li><a id="linkHomeHeaderUser" href="/panther">Home</a></li>';
                    
                    $time = new Time($user, false, 'incomplete');
                    $workordertasks = $time->getWorkOrderTasksByDisplayType();
                    
                    $lastWorkOrderId = 0;
                    $lastJobId = 0;
                    
                    $c = 0; // >>>00007 set here, but never used before being set again                
                    $wots = array();                
                    foreach ($workordertasks as $wot) {
                        if (isset($wot['jobId'])) {
                            if ($wot['type'] == 'real') {
                                $wots[$wot['jobId']][$wot['workOrderId']][] = $wot;
                            }
                        }
                    }

                    $c = 0;
                    foreach ($wots as $jj) {
                        foreach ($jj as $workorder) {
                            $hasreal = false;
                            foreach ($workorder as $workordertask) {
                                if ($workordertask['type'] == 'real') {
                                    $hasreal = true;
                                }
                            }
                            if ($hasreal) {
                                $c++;
                            }                    
                        }                
                    }
                
                    /*
                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                    foreach ($workordertasks as $wkey => $workordertask){                
                        if ($workordertask['type'] == 'real')                
                            if (intval($workordertask['jobId'])){                            
                            if ($lastWorkOrderId != $workordertask['workOrderId']){                
                                $c++;                                
                            }                            
                            $lastWorkOrderId = $workordertask['workOrderId'];                            
                        }
                    }
                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                
                    */
            
                    /*
                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019                 
                    echo '<li><a href="/openworkordersmoved.php">Open WOs';                
                    if (intval($c)){
                        echo '&nbsp;[' . $c . ']';
                    }
                    
                    echo '</a></li>';
                    // END COMMENTED OUT BY MARTIN BEFORE 2019                
                    */

                    echo '<li><a id="linkOpenWoCompany" href="/openworkorderscompany.php">Comp Open WOs';                
                    echo '</a></li>';
                    
                    echo '<li><a id="linkWoNoInvoice" href="/wonoinvoice.php">WO No Invoice';
                    echo '</a></li>';
            ?>
                    <li><a id="linkCrumbs" href="/crumbs">Crumbs</a></li>
            <?php
                    if ($user->isEmployee()) { 
                        echo '<div id="listLinks" style="float:right; display:inline-block!important; padding-right:15px">';
                        echo '&nbsp;&nbsp;<a id="companyListPageLink" href="/companylist.php"> Company List</a>';
                        echo '&nbsp;&nbsp;|&nbsp;&nbsp;<a id="tooltipListPageLink" href="/tooltiplist.php">Tooltip List</a>';
                        echo '</div>';
                    }
                }
            ?>
            
            
        </ul>

    </div>
    <?php
    // This files have all the pages and abri
    require_once BASEDIR . '/includes/generate_link_header.php';

    // Full Url path:
    $urlToCopyGeneralPages = REQUEST_SCHEME . '://' . HTTP_HOST .  $_SERVER['REQUEST_URI'];

    // exceptions from general pages
    $customPagesLinks = array("person", "company", "workorder", "workordertasks", "job", "documentcenter", "contract", "invoice");
  
    if($to_copy) {
        if(!in_array($page37, $customPagesLinks )) {
        ?>
            <div style="overflow: hidden; background-color:#fff!important">
                <p  id="firstLinkToCopyGeneralPages" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important"><?php echo $abv; ?><a id="linkWoToCopy" href="<?php echo $urlToCopyGeneralPages ?>"> <?php echo $to_copy; ?> </a>
                &nbsp;<button id="copyLinkGeneralPages" title="Copy URL link" class="btn btn-outline-secondary btn-sm " onclick="copyToClipGeneralPages(document.getElementById('linkToCopyGeneral2').innerHTML)">Copy</button>
                </p>
                <span id="linkToCopyGeneral2" style="display:none"> <a href="<?=$urlToCopyGeneralPages?>"><?php echo $abv; ?>&nbsp;<?php echo $to_copy; ?> </a></span>
                <?php if($abv=='(M)' && $_REQUEST['tab']==5){ ?>
                    <a class="btn btn-secondary float-right mr-5 mt-1 text-white text-decoration-none" href="https://demo.ssseng.com/financiallist" target="_blank">Master List</a>
                <?php } ?>
                <?php if($abv=='(H)'){ ?>
                    <a class="btn btn-secondary float-right mr-5 mt-1 text-white text-decoration-none" href="https://demo.ssseng.com" target="_blank" >Master List</a>
                <?php } ?>
            </div>
        <?php
        }
    }
    ?>
</div>  


