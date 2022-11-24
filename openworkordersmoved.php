<?php 
/* openworkordersmoved.php

   EXECUTIVE SUMMARY Static page. Tells user that the link they just clicked is 
   being replaced by the blue person icon at the top of the page to view your open workorders. Linked from header ("Open WOs (nn)").
*/ 

include './inc/config.php';
include './inc/access.php';

?>

<?php 
include BASEDIR . '/includes/header.php';

?>

<div id="container" class="clearfix">
    <div class="main-content">
        The link you just clicked will go away soon.  The blue person icon at the top of the page will now be used to view your open workorders.  So press the blue person!
    </div>
</div>
</div> <?php /* one too many DIVs closed */ ?>

<?php 
include BASEDIR . '/includes/footer.php';
?>