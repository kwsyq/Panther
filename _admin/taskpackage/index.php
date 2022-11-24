<?php 
/*  _admin/taskpackage/index.php

    EXECUTIVE SUMMARY: Just side-by-side frames to hold three pages
    
    >>>00039 Framesets are now deprecated HTML; we should be replacing frames with iframes.
*/ ?>
<frameset cols="20%,40%,40%">
    <frame name="packageList" src="packagelist.php">
    <frame name="packageItems" src="packageitems.php">
    <frame name="packageTaskSelect" src="packagetaskselect.php">
</frameset>
