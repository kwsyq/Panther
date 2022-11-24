<?php 
/*  includes/header_fb.php

    EXECUTIVE SUMMARY: Header for a Panther fancybox (pop-up dialog).
    
    NOTE that at the end of this, the BODY of the HTML document is open.    
*/ 
?>
<!DOCTYPE html>
<html>
<head>
    <?php
    if ($customer) {
        ?>
        <link href="/cust/<?php echo $customer->getShortName(); ?>/css/main.css" rel="stylesheet"/>
        <link href="/cust/<?php echo $customer->getShortName(); ?>/css/admin.css" rel="stylesheet"/>		
        <link href="/cust/<?php echo $customer->getShortName(); ?>/css/buttons.css" rel="stylesheet"/>
        <link href="/cust/<?php echo $customer->getShortName(); ?>/css/responsive.css" rel="stylesheet"/>
        <link href="/cust/<?php echo $customer->getShortName(); ?>/css/new.css" rel="stylesheet"/>
        <?php
    }
    ?>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">  
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>    
    <script src="/js/jquery.autocomplete.min.js"></script>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <!-- Latest compiled and minified JavaScript -->
    <!-- BEGIN COMMENTED OUT JM 2020-02-11, I don't see how a second inclusion of jQuery can possibly be right: clobbers the jQueryUI already added to the prior one.
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous">
    </script>
    END COMMENTED OUT JM 2020-02-11-->        
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

    <script src="https://kit.fontawesome.com/e85b454207.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js" ></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/additional-methods.min.js" ></script>
    <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
    <script src="https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"></script>
    
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
    </style>
</head>
<body>
<style>
/* disable pointer events on body*/
body {
  pointer-events:none;
}
</style>
<script>
//activate all pointer-events on body
$(document).ready(() => {
    $('body').css('pointer-events', 'all');
});
</script>
<?php 
require_once BASEDIR . '/generate_jwt.php';

$pageException = basename($_SERVER["SCRIPT_FILENAME"], '.php');
if($pageException === "workorder" || $pageException === "companyperson" || $pageException === "invoice" || $pageException === "creditrecord") {
    $path = pathinfo($_SERVER["REQUEST_URI"]);
    if($path['dirname'] == "/fb") {
       $pageException = "fb-". $pageException;
    }
}

$linkWordPress = $schema ."://". $wordpressServerName ."/wordpress/".$restRoute. "&page=". $pageException ."&JWT=".$jwt;
if (isset($user) && $user) { 
    if ($user->isEmployee()) {
        
        if($pageException != "recentnotes") {
            echo '<button type="button" class="btn btn-info btn-sm mr-2" id="editTooltip">Edit Tooltip</button>'; 
            echo '<button type="button" class="btn btn-info btn-sm" id="hideTooltip">Hide Tooltip</button>';
            echo  '&nbsp;&nbsp;&nbsp;<a class="btn btn-outline-success btn-sm" target="_blank" href="'.$linkWordPress.'">User Manual</a>';
        }
    } else {
        if($pageException != "recentnotes") {
            echo '<button type="button" class="btn btn-info btn-sm" id="hideTooltip">Hide Tooltip</button>';
            echo  '&nbsp;&nbsp;&nbsp;<a class="btn btn-outline-success btn-sm" target="_blank" href="'.$linkWordPress.'">User Manual</a>';
        }
    }
} 
?>