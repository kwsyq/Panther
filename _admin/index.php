<?php
/*  _admin/index.php
    
    Just a frameset. Opens menu.php in top frame, uses clicks in menu.php to load bottom frame.
    
    >>>00039 Framesets are now deprecated HTML; we should be replacing frames with iframes.
*/    
?>
<frameset rows="100,*">

  <frame src="menu.php">
  <frame name="bottom_frame" src="">
</frameset> 