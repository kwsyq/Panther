<?php
/* _admin/index_alt.php

   As of 2019-02-08 (JM), this is Martin's (possibly abandoned) work in progress to replace _admin/index.php, which previously
   was just a frameset, no script. 
   Among other things, it introduces a function window.CallParent which, in fact, is already referenced in the committed 
   version of _admin/newtasks/edit.php: 
   >>>00026 on the production system, as of 2019-02-08, this is a call to a nonexistent function!
   
   However, window.CallParent here is clearly just work in progress. 
   JM: Saving this as _admin/index_alt.php; someone can eventually decide whether this is useful going forward; 
      * If it is useful, then this content can be merged into _admin/index.php and this file can be deleted.
      * If it is not useful, then this file can just be deleted.
   NOTE that _admin/index_alt.php is not referenced from anywhere.    
   */
?>

<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>

<script>
    // This presumably relies on being called from outside of this file, presumably from some child page launched in an iframe.  - JM 2019-02-08     
	window.CallParent = function() {
		$.fancybox.open({
			src  : 'link-to-your-page.html',  // must be a placeholder for work in progress, because there is no such file  - JM 2019-02-08
			type : 'iframe',
			opts : {
				afterShow : function( instance, current ) {
					console.info( 'done!' ); // must be a placeholder for work in progress  - JM 2019-02-08
				}
			}
		});			  
    };
</script>
<?php
//Frameset opens menu.php in top frame, uses clicks in menu.php to load bottom frame.  - JM 2019-02-08
// >>>00039 Framesets are now deprecated HTML; we should be replacing frames with iframes.
?>
<frameset rows="65,*">
    <frame src="menu.php">
    <frame name="bottom_frame" src="">
</frameset> 

<script type="text/javascript" src="/_admin/include/fancybox/jquery.fancybox.js?v=2.1.5"></script>
<link rel="stylesheet" type="text/css" href="/_admin/include/fancybox/jquery.fancybox.css?v=2.1.5" media="screen" />	

<script>
    <?php
    // On document ready, this should open any element here with class "fancybox" as a fancybox,
    // BUT there doesn't appear to be any such element - JM 2019-02-08
    ?>
    $(document).ready(function() {
        $(".fancybox").fancybox();
        });
</script>