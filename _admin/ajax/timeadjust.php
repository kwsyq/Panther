<?php 
/*  _admin/ajax/timeadjust.php

    EXECUTIVE SUMMARY The idea here is just to wrap ajax/timeadjust.php so it can be called in the _admin area and
    will pick up an _admin login and set $user accordingly.
    
    See ajax/timeadjust.php for all other documentation 
*/

$called_from_admin_side = true; 
include "../../ajax/timeadjust.php";
