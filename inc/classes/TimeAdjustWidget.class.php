<?php
/*  inc/classes/TimeAdjustWidget.class.php

    EXECUTIVE SUMMARY: "device" to adjust a time: icons for -4 hours, -1 hour, -15 mins, +15 mins, + 1 hour, + 4 hours.

    Multiple places in the code, Martin built a widget to allow work times to be adjusted upward or downward
    in increments of 15 minutes, one hour, or four hours. This abstracts that into a reusable class.
    
    Typical usage can be seen in _admin/time/sheet.php.
    
    * public methods:
    ** __construct($options)
    ** function getDeclarationHTML()
    ** function getOpenJS() 
*/
#require_once "../config.php";

class TimeAdjustWidget {
    private $callback;
    private $iconPath;
    private $widgetId;
    private $width;
    private $height;
    private $iconSize;
    
    /* INPUT options is an associative array. This is entirely optional, but the following indexes are supported
       'callback': Necessarily, this dialog we are building will call back. Callback function must be provided by name, because this is PHP
         referring to a JavaScript function. For example, 'myTimeAdjust'.
         Defaults to 'timeAdjust', so if you don't set this there had better be a function timeAdjust in the JavaScript!
       'customer': a Customer object or short customer name. This is used only to get a path to icons, and in this case those will 
         probably be the same for all customers, anyway. Defaults to constant CUSTOMER.
       'widgetId': allows you to control the name of the created dialog; default is 'time-adjust-dialog'
       'width': width of the created dialog; default is 240 (pixels)
       'height': height of the created dialog; default is 75 (pixels)
       'iconSize': size of the icons, which are all square; default is 27 (pixels), meaning the icons are 27x27
       
       Example of typical usage:

       $timeAdjustWidget = new TimeAdjustWidget(
           Array(
               'callback' => 'timeAdjust', // JavaScript function where the real work gets done
               'customer' => $customer
           )
       );
*/
	public function __construct($options) {
	    $this->callback = array_key_exists('callback', $options) ? $options['callback'] : 'timeAdjust';
	    $this->iconPath = getIconPath( array_key_exists('customer', $options) ? $options['customer'] : '' );
	    $this->widgetId = array_key_exists('widgetId', $options) ? $options['widgetId'] : 'time-adjust-dialog';
	    $this->width = array_key_exists('width', $options) ? $options['width'] : 250;
	    $this->height = array_key_exists('height', $options) ? $options['height'] : 85;
	    $this->iconSize = array_key_exists('iconSize', $options) ? $options['iconSize'] : 27;
	}

	// Call this to place the basic HTML + JavaScript + CSS that defines this dialog into your HTML document. 
	public function getDeclarationHTML() {
	    $html = '<div id="' . $this->widgetId . '">' . "\n";
	    $html .= '    <a href="javascript:' . $this->callback . '(-240);"><img src="'. $this->iconPath . '/icon_time_b_240.png"' . 
	                 ' width="' . $this->iconSize . '" height="' . $this->iconSize . '" border="0"></a>' . "\n";
        $html .= '    <a href="javascript:' . $this->callback . '(-60);"><img src="'. $this->iconPath . '/icon_time_b_60.png"' . 
	                 ' width="' . $this->iconSize . '" height="' . $this->iconSize . '" border="0"></a>' . "\n";
        $html .= '    <a href="javascript:' . $this->callback . '(-15);"><img src="'. $this->iconPath . '/icon_time_b_15.png"' . 
	                 ' width="' . $this->iconSize . '" height="' . $this->iconSize . '" border="0"></a>' . "\n";
        $html .= '    <a href="javascript:' . $this->callback . '(15);"><img src="'. $this->iconPath . '/icon_time_f_15.png"' . 
	                 ' width="' . $this->iconSize . '" height="' . $this->iconSize . '" border="0"></a>' . "\n";
        $html .= '    <a href="javascript:' . $this->callback . '(60);"><img src="'. $this->iconPath . '/icon_time_f_60.png"' . 
	                 ' width="' . $this->iconSize . '" height="' . $this->iconSize . '" border="0"></a>' . "\n";
        $html .= '    <a href="javascript:' . $this->callback . '(240);"><img src="'. $this->iconPath . '/icon_time_f_240.png"' . 
	                 ' width="' . $this->iconSize . '" height="' . $this->iconSize . '" border="0"></a>' . "\n";
        $html .= '</div>' . "\n\n";
        
        $html .= '<style>' . "\n";
        $html .= '    #'. $this->widgetId . '.ui-dialog-title {' . "\n";
        $html .= '    display: none;' . "\n";
        $html .= '    }' . "\n";
        $html .= '</style>' . "\n\n";
        
        $html .= '<script>' . "\n";
        $html .= '    $("#'. $this->widgetId . '" ).dialog({ autoOpen: false, width:' . $this->width . ', height:' . $this->height . ' })' . "\n";
        $html .= '</script>' . "\n\n";
        
        return $html;
	} // END public function getDeclarationHTML	
	
	// RETURN the JavaScript to open the dialog; this should go in the code that opens the dialog (typically a click-event handler) 
	// 
	// >>>00026 I (JM) carried this in from Martin's code, but I have a few doubts:
	//  * The selectors here in the 'open' statement would affect any dialog, not just the current one. 
	//    Probably OK because we won't have two or more dialogs open at once, but sloppy.
	//  * Not sure where the styling comes from, but titlebar seems at least a bit too tall. 
	//    Its padding-top & padding-bottom are about 7px, would like to adjust to 3px, but not sure what's controlling that.
	public function getOpenJS() {
	    $js = '$("#'. $this->widgetId . '" ).dialog({' . "\n";	    
        $js .= '    position: { my: "center bottom", at: "center top", of: $(this) },' . "\n";
        $js .= '    open: function(event, ui) {' . "\n";
        $js .= '        $(".ui-dialog-titlebar-close", ui.dialog | ui ).show();' . "\n";
        $js .= '        $(".ui-dialog-titlebar", ui.dialog | ui ).show();' . "\n";
        $js .= '    }' . "\n";    
        $js .= '});' . "\n";
        $js .= '$("#'. $this->widgetId . '" ).dialog("open");' . "\n";
        
        return $js;
    }
}

?>