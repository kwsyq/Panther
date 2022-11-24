<?php
/* includes/generate_link_header.php

    Usage: Creates the structure of the link ' (abbreviation of the page) Name of the page ' that is present 
        on all pages in the header and the option to copy 
        by pressing the button and paste as active link in mail / chat etc. 


*/
// parsed path
$scheme = $_SERVER['REQUEST_SCHEME'] . '://'; // http or https.
$path =  "$scheme$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$path=explode("/",$path );
$filename=basename($path[3]);

// extracted basename without extension
$page37 = pathinfo($filename, PATHINFO_FILENAME);
$abv = "";
$to_copy = "";
if($page37 === 'panther') {
    $abv = "(H)";
    $to_copy = "Home";
} else if ($page37 === 'tooltiplist') {
    $abv = "(TL)";
    $to_copy = "TooltipList";
} else if ($page37 === 'multi') {
    $abv = "(M)";
    $to_copy = "Multi";
} else if ($page37 === 'alarm2') {
    $abv = "(A)";
    $to_copy = "Alarms";
} else if ($page37 === 'openworkorders') {
    $abv = "(OWo)";
    $to_copy = "OpenWorkorder";
} else if ($page37 === 'openworkordersemp') {
    $abv = "(EWo)";
    $to_copy = "EmployeeWorkorderList";
} else if ($page37 === 'sms') {
    $abv = "(SMS)";
    $to_copy = "SMS";
} else if ($page37 === 'ticket') {
    $abv = "(T)";
    $to_copy = "Tickets";
} else if ($page37 === 'reviews') {
    $abv = "(R)";
    $to_copy = "Reviews";
} else if ($page37 === 'time') {
    $abv = "(TSh)";
    $to_copy = "TimeSheet";
}  else if ($page37 === 'services') {
    $abv = "(S)";
    $to_copy = "Services"; 
} else if ($page37 === 'about') {
    $abv = "(I)";
    $to_copy = "AboutUs"; 
} else if ($page37 === 'contact') {
    $abv = "(CU)";
    $to_copy = "ContactUs";
} else if ($page37 === 'location') {
    $abv = "(AL)";
    $to_copy = "AddLocation";   
} else if ($page37 === 'companylist') {
    $abv = "(CL)";
    $to_copy = "CompanyList";
} 


?>