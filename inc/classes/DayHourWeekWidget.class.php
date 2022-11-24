<?php
/*  inc/classes/DayHourWeekWidget.class.php

    EXECUTIVE SUMMARY: creates three "sync'd-up" inputs, typically as rows in a table, so that user can enter
     a quantity that is actually minutes, but can express it in terms of minutes, hours, or 8-hour workdays.

    Typical usage can be seen in _admin/ajax/editvacationtime.php and (a bit differently) in _admin/holiday/index.php.
    
    * public methods:
    ** __construct($options)
    ** function getHTML($quoted=false)
    ** function getMechanismJS($prefix='<script>', $suffix='</script>')
    ** function getValueInMinutesJS()
*/
#require_once "../config.php";

class DayHourWeekWidget {
    private $visibleMinutes;
    private $visibleHours;
    private $visibleDays;
    private $increment;
    private $promptPrefix;
    private $promptSuffix;
    private $promptCapitalize;
    private $idPrefix;
    private $asRows;
    
/* INPUT options is an associative array. This is entirely optional, but the following indexes are supported:
    'visibleMinutes', 'visibleHours', 'visibleDays': Booleans, indicate whether to generate a visible control for each of these.
        At least one of these should be true; if none are, then 'visibleMinutes' will be considered true as a default.
        DEFAULT: (true, false, false) but if 'visibleHours' or 'visibleDays' is present and true, then 'visibleMinutes' defaults to false  
    'increment': increment in minutes. All times must be a multiple of this. This should work correctly for 15, 30, 60; other values
        have not been tested and could imaginably be problematic.
        DEFAULT: 15
    'allowNegative': Boolean, whether value can be negative. DEFAULT: true
    'promptPrefix', 'promptSuffix': strings to be prefixed/suffixed to prompts. DEFAULT: empty strings
    'promptCapitalize': Boolean: whether to capitalize "minutes"/"hours"/"days". DEFAULT: false.
        E.g. ('promptPrefix' => "Allocate time in ", 'promptSuffix' => ":", 'promptCapitalize' => false) to get prompts like "Allocate time in minutes:".
        E.g. ('promptPrefix' => "", ' to go' => ":", 'promptCapitalize' => true) to get prompts like "Allocate time in minutes:".
    'idPrefix': prefix to HTML ID for these inputs. DEFAULT: empty string. Makes it possible to use this control more than once on a page by providing different prefixes.
    'asRows': Boolean, generate as rows in a table; default = true; >>>00001 as of 2019-07-01, false has not been tested
*/     
	public function __construct($options) {
	    $this->visibleHours = array_key_exists('visibleHours', $options) ?  !! $options['visibleHours'] : false;
	    $this->visibleDays = array_key_exists('visibleDays', $options) ?  !! $options['visibleDays'] : false;
	    $this->visibleMinutes = ( ! $this->visibleHours && ! $this->visibleDays ) ? true :
	                                array_key_exists('visibleMinutes', $options) ?  !! $options['visibleMinutes'] : false;
	    $this->increment = array_key_exists('increment', $options) ?  $options['increment'] : 15;
	    $this->allowNegative = array_key_exists('allowNegative', $options) ?  !! $options['allowNegative'] : true;
	    $this->promptPrefix = array_key_exists('promptPrefix', $options) ?  $options['promptPrefix'] : '';
	    $this->promptSuffix = array_key_exists('promptSuffix', $options) ?  $options['promptSuffix'] : '';
	    $this->promptCapitalize = array_key_exists('promptCapitalize', $options) ?  !! $options['promptCapitalize'] : false;
	    $this->idPrefix = array_key_exists('idPrefix', $options) ?  $options['idPrefix'] : '';
	    $this->asRows = array_key_exists('asRows', $options) ?  !! $options['asRows'] : true;	    
	}
	
	// RETURN the HTML (no JS)
	// INPUT $quoted: Boolean, default false, if true then this is returned
	// as a series of lines in quotes joined by the plus-sign that joins quotes in JS
	//  NO plus-sign at end.
	public function getHTML($quoted=false) {
	    $minutes = $this->promptCapitalize ? 'Minutes' : 'minutes';
	    $hours = $this->promptCapitalize ? 'Hours' : 'hours';
	    $days = $this->promptCapitalize ? 'Days' : 'days';
	    $minutesIncrement = $this->increment;
	    $hoursIncrement = $minutesIncrement / 60;
	    $daysIncrement = $hoursIncrement / 8;
	    $quote = $quoted ? "'" : '';
	    $plus = $quoted ? "+" : '';
	    $html = '';
	    if ($this->asRows) {
	        if ($this->visibleMinutes) {
                $html .= $quote. '<tr>' . "$quote $plus\n";
                $html .= $quote. '    <th align="right">' . $this->promptPrefix . $minutes . $this->promptSuffix . '&nbsp;</th>' . "$quote $plus\n";
                $html .= $quote. '    <td><input id="' . $this->idPrefix . 'minutes" type="number" value="0" step="'. $minutesIncrement . '"/></td>' . "$quote $plus\n";
                $html .= $quote. '</tr>' . "$quote $plus\n";
            } else {
                $html .= $quote. '<input id="' . $this->idPrefix . 'minutes" type="hidden" value="0" />' . "$quote $plus\n";
            }
            if ($this->visibleHours) {
                $html .= $quote. '<tr>' . "$quote $plus\n";
                $html .= $quote. '    <th align="right">' . $this->promptPrefix . $hours . $this->promptSuffix . '&nbsp;</th>' . "$quote $plus\n";
                $html .= $quote. '    <td><input id="' . $this->idPrefix . 'hours" type="number" value="0" step="'. $hoursIncrement . '"/></td>' . "$quote $plus\n";
                $html .= $quote. '</tr>' . "$quote $plus\n";
            } else {
                $html .= $quote. '<input id="' . $this->idPrefix . 'hours" type="hidden" value="0" />' . "$quote $plus\n";
            }
            if ($this->visibleDays) {
                $html .= $quote. '<tr>' . "$quote $plus\n";
                $html .= $quote. '    <th align="right">' . $this->promptPrefix . $days . $this->promptSuffix . '&nbsp;</th>' . "$quote $plus\n";
                $html .= $quote. '    <td><input id="' . $this->idPrefix . 'days" type="number" value="0" step="'. $daysIncrement . '"/></td>' . "$quote $plus\n";
                $html .= $quote. '</tr>' . "$quote\n";
            } else {
                $html .= $quote. '<input id="' . $this->idPrefix . 'days" type="hidden" value="0" />' . "$quote\n";
            }
        } else {
	        if ($this->visibleMinutes) {
                $html .= $quote. '<label for="' . $this->idPrefix . 'minutes" >' . $this->promptPrefix . $minutes . $this->promptSuffix . '&nbsp;</label>' . "$quote $plus\n";
                $html .= $quote. '<input id="' . $this->idPrefix . 'minutes" type="number" value="0" step="'. $minutesIncrement . '"/>' . "$quote $plus\n";
            } else {
                $html .= $quote. '<input id="' . $this->idPrefix . 'minutes" type="hidden" value="0" />' . "$quote $plus\n";
            }
            if ($this->visibleHours) {
                $html .= $quote. '<label for="' . $this->idPrefix . 'hours" >' . $this->promptPrefix . $hours . $this->promptSuffix . '&nbsp;</label>' . "$quote $plus\n";
                $html .= $quote. '<input id="' . $this->idPrefix . 'hours" type="number" value="0" step="'. $hoursIncrement . '"/>' . "$quote $plus\n";
            } else {
                $html .= $quote. '<input id="' . $this->idPrefix . 'hours" type="hidden" value="0" />' . "$quote $plus\n";
            }
            if ($this->visibleDays) {
                $html .= $quote. '<label for="' . $this->idPrefix . 'days" >' . $this->promptPrefix . $days . $this->promptSuffix . '&nbsp;</label>' . "$quote $plus\n";
                $html .= $quote. '<input id="' . $this->idPrefix . 'days" type="number" value="0" step="'. $daysIncrement . '"/>' . "$quote\n";
            } else {
                $html .= $quote. '<input id="' . $this->idPrefix . 'days" type="hidden" value="0" />' . "$quote\n";
            }
        }
	    return $html;
	} // END public function getHTML

	// RETURN the JavaScript that makes all of this work.
	public function getMechanismJS($prefix='<script>', $suffix='</script>') {
	    $js = "$prefix\n";
	    
        // INPUT $that - jQuery object for the relevant INPUT; name here & below is so we don't confuse it with PHP $this for the class
        // May not be ideal, but user gets to review after.
        // Forces minutes to a multiple of increment.
        $js .= "function cleanMinutes(\$that) {\n";
        $js .= "    var str = \$that.val().trim();\n";
        $js .= "    var negative = str.substr(0, 1) == '-';\n";
        $js .= "    if (negative) {\n";
        if ($this->allowNegative) {
            $js .= "        str = str.substr(1);\n";
        } else {
            $js .= "        str = 0;\n";
        }
        $js .= "    }\n";
        $js .= "    var str = str.replace(/[^0-9]/g, '');\n"; // clean out non-digits
        $js .= "    var val = parseInt(str, 10);\n"; // make it an integer
        $js .= "    val -= (val%{$this->increment});\n"; // get rid of any remainder if it's not a multiple of increment
        $js .= "    if (negative) {\n";
        $js .= "        val = -val;\n";
        $js .= "    }\n";
        $js .= "    \$that.val(val);\n";
        $js .= "}\n";
        
        $js .= "$(function() {\n"; // Begin anonymous function to control scope of minutesPerHour, minutesPerDay  
        $js .= "    let minutesPerHour = 60;\n";
        $js .= "    let minutesPerDay = 480;\n"; // 8-hour workday
        $js .= "\n";
        
        // Keep minutes, hours, days sync'd; on blur or carriage return, make sure minutes is a multiple of increment.
        $js .= "    $('#{$this->idPrefix}minutes').on('blur', function(event) {\n";
        $js .= "        var \$that = $(this);\n";
        $js .= "        cleanMinutes(\$that);\n";
        $js .= "        $('#{$this->idPrefix}hours').val(\$that.val()/minutesPerHour);\n";
        $js .= "        $('#{$this->idPrefix}days').val(\$that.val()/minutesPerDay);\n";
        $js .= "    })\n";
        $js .= "\n";
    
        $js .= "    $('#{$this->idPrefix}hours').on('blur', function(event) {\n";
        $js .= "        var \$that = $(this);\n";
        $js .= "        $('#{$this->idPrefix}minutes').val(\$that.val()*minutesPerHour).blur();\n";
        $js .= "    });\n";
        $js .= "\n";
    
        $js .= "    $('#{$this->idPrefix}days').on('blur', function(event) {\n";
        $js .= "        var \$that = $(this);\n";
        $js .= "        $('#{$this->idPrefix}minutes').val(\$that.val()*minutesPerDay).blur();\n";
        $js .= "    });\n";
        $js .= "\n";
        
        $js .= "    $('#{$this->idPrefix}minutes').on('keyup mouseup surrogate', function(event) {\n";
        $js .= "        var \$that = $(this);\n";
        $js .= "        if (\$that.val().trim() == '') {\n";
        $js .= "            $('#{$this->idPrefix}hours').val('');\n";
        $js .= "            $('#{$this->idPrefix}days').val('');\n";
        $js .= "        } else {\n";
        $js .= "            var hours = \$that.val()/minutesPerHour;\n";
        $js .= "            var days = \$that.val()/minutesPerDay;\n";
                
                // Want to leave it alone if it evaluates the same but has a decimal point appended.
                // Code here may look a bit "funny", but it works.
        $js .= "            if ($('#{$this->idPrefix}hours').val() != hours) {\n";
        $js .= "                $('#{$this->idPrefix}hours').val(\$that.val()/minutesPerHour);\n";
        $js .= "            };\n";
        $js .= "            if ($('#{$this->idPrefix}days').val() != days) {\n";
        $js .= "                $('#{$this->idPrefix}days').val(\$that.val()/minutesPerDay);\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "        if (event.type == 'keyup' &&  (event.keyCode === 10 || event.keyCode === 13) ) {\n";
        $js .= "            \$that.blur();\n";
        $js .= "        }\n";
        $js .= "    });\n";
        $js .= "\n";
        
        // INPUT _this: the hours or days input element
        // INPUT event
        // INPUT numberOfMinutes = 60 for hour, 480 for day
        $js .= "    function hoursOrDaysHandler(_this, event, numberOfMinutes) {\n";
        $js .= '        var $that = $(_this);'."\n";
        $js .= "        if (_this.validity && ! _this.validity.valid) {\n";
                // Invalid number. We want to keep that in place so it can be edited, but
                // we want to blank the other fields.
        $js .= "            $('#{$this->idPrefix}minutes').val('');\n";
        $js .= "            if ( ! $('#{$this->idPrefix}hours').is(\$that) ) {\n";
        $js .= "                $('#{$this->idPrefix}hours').val('');\n";
        $js .= "            }\n";
        $js .= "            if ( ! $('#{$this->idPrefix}days').is(\$that) ) {\n";
        $js .= "                $('#{$this->idPrefix}days').val('');\n";
        $js .= "            }\n";
        $js .= "        } else {\n";
        $js .= "            if (\$that.val().trim() == '') {\n";
        $js .= "                $('#{$this->idPrefix}minutes').val('').trigger('surrogate');\n";
        $js .= "            } else {\n";
        $js .= "                $('#{$this->idPrefix}minutes').val(\$that.val()*numberOfMinutes).trigger('surrogate');\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "        if (event.type == 'keyup' &&  (event.keyCode === 10 || event.keyCode === 13) ) {\n";
        $js .= "            \$that.blur();\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "\n";
    
        $js .= "    $('#{$this->idPrefix}hours').on('keyup mouseup', function(event) {\n";
        $js .= "        hoursOrDaysHandler( this, event, minutesPerHour);\n";
        $js .= "    });\n";
        $js .= "\n";
        
        $js .= "    $('#{$this->idPrefix}days').on('keyup mouseup', function(event) {\n";
        $js .= "        hoursOrDaysHandler( this, event, minutesPerDay);\n";
        $js .= "    });\n";
        $js .= "\n";
        
        // And define a public JS function:
        $js .= "    setValueInMinutes = function (minutes) {\n";
        $js .= "        $('#{$this->idPrefix}minutes').val(minutes);\n";
        $js .= "        $('#{$this->idPrefix}hours').val(minutes/minutesPerHour);\n";
        $js .= "        $('#{$this->idPrefix}days').val(minutes/minutesPerDay);\n";
        $js .= "    }\n";
        $js .= "\n";
        
        $js .= "});\n";  // End anonoymous function
        
        
        $js .= "$suffix\n";
        return $js;
    } // END public function getMechanismJS

	// RETURN the JavaScript that gets the value in minutes.
	public function getValueInMinutesJS() {
	    return "$('#{$this->idPrefix}minutes').val()"; 
	}
	
} // END class DayHourWeekWidget
/*
Use the following to test this

$dayHourWeekWidget = new DayHourWeekWidget( Array(
    'visibleMinutes' => true, 
    'visibleHours' => true, 
    'visibleDays' => true, 
    'increment' => 15, // minutes
    'promptPrefix' => 'Allocate time in ',
    'promptSuffix' => ':',
    'idPrefix' => 'allocate-'
));

echo $dayHourWeekWidget->getHTML();
echo "\n\n\n\n----\n\n\n\n";
echo $dayHourWeekWidget->getMechanismJS();
echo "\n\n\n\n----\n\n\n\n";
echo $dayHourWeekWidget->getValueInMinutesJS();
*/
?>