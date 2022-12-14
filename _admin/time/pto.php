<?php 
/*  _admin/time/pto.php

    VESTIGIAL PAGE. Draws a calendar; allows indicating who has paid time off on what days, 
    including for forward scheduling. While this is clearly grouped with biggrid.php, time.php, 
    and summary.php, unlike all of those it doesn't have a set of links at the top to navigate 
    to the others. 
    
    It looks like this isn't particularly maintained, because it is hard-coded to date a calendar 
    for the year 2015. Therefore, I (JM) haven't pursued it further.
*

include '../../inc/config.php';
include '../../inc/access.php';


?>

<style>

table.calendar		{ border-left:1px solid #999; }
tr.calendar-row	{  }
td.calendar-day	{ min-height:80px; font-size:11px; position:relative; } * html div.calendar-day { height:80px; }
td.calendar-day:hover	{ background:#eceff5; }
td.calendar-day-np	{ background:#eee; min-height:80px; } * html div.calendar-day-np { height:80px; }
td.calendar-day-head { background:#ccc; font-weight:bold; text-align:center; width:120px; padding:5px; border-bottom:1px solid #999; border-top:1px solid #999; border-right:1px solid #999; }
div.day-number		{ background:#999; padding:5px; color:#fff; font-weight:bold; float:right; margin:-5px -5px 0 0; width:20px; text-align:center; }
/* shared */
td.calendar-day, td.calendar-day-np { width:120px; padding:5px; border-bottom:1px solid #999; border-right:1px solid #999; }

</style>

<?php 

/* draws a calendar */
function draw_calendar($month,$year){

	$db = DB::getInstance();

	$people = array();
	
	$query = " select pto.*,cp.legacyInitials ";
	$query .= " from " . DB__NEW_DATABASE . ".pto pto  ";
	$query .= " join " . DB__NEW_DATABASE . ".customerPerson cp on pto.personId = cp.personId  ";
	$query .= " join " . DB__NEW_DATABASE . ".person p on cp.personId = p.personId  ";
	$query .= " where day ";
	$query .= "  between '" . $year . "-" . $month . "-1' ";
	$query .= "  and '" . $year . "-" . $month . "-" . cal_days_in_month(CAL_GREGORIAN, $month, $year) . "' ";

	if ($result = $db->query($query)) {
		if ($result->num_rows > 0){
			while ($row = $result->fetch_assoc()){
				$parts = explode("-", $row['day']);
				$people[intval($parts[2])][] = $row;
			}
		}
	}	
	

	
	
	/* draw table */
	$calendar = '<table border="1" cellpadding="0" cellspacing="0" class="calendar">';

	/* table headings */
	$headings = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
	$calendar.= '<tr class="calendar-row"><td class="calendar-day-head">'.implode('</td><td class="calendar-day-head">',$headings).'</td></tr>';

	/* days and weeks vars now ... */
	$running_day = date('w',mktime(0,0,0,$month,1,$year));
	$days_in_month = date('t',mktime(0,0,0,$month,1,$year));
	$days_in_this_week = 1;
	$day_counter = 0;
	$dates_array = array();

	/* row for week one */
	$calendar.= '<tr class="calendar-row">';

	/* print "blank" days until the first of the current week */
	for($x = 0; $x < $running_day; $x++):
	$calendar.= '<td class="calendar-day-np"> </td>';
	$days_in_this_week++;
	endfor;

	/* keep going with days.... */
	for($list_day = 1; $list_day <= $days_in_month; $list_day++):
	$calendar.= '<td class="calendar-day">';
	/* add in the day number */
	$calendar.= '<div class="day-number">'.$list_day.'</div>';

	/** QUERY THE DATABASE FOR AN ENTRY FOR THIS DAY !!  IF MATCHES FOUND, PRINT THEM !! **/
	$calendar.= str_repeat('<p> </p>',2);
		
	if (array_key_exists(intval($list_day), $people)){
		$str = '<table border="0" cellpadding="1" cellspacing="1">';
		$p = $people[intval($list_day)];
		foreach ($p as $pkey => $initials){
			//if (strlen($str)){
			//	$str .= '<br>';
			//}
			$str .= '<tr>';
			$pre = ($initials['ptoTypeId'] == 1) ? 'V' : 'H';
			$str .= '<td><span style="font-size:90%;font-weight:heavy;">' . $initials['legacyInitials'] . '</span></td>';
			$str .= '<td><span style="font-size:75%">' . number_format((float)intval($initials['minutes'])/60, 2, '.', '') . '</span></td>';
			$str .= '<td><span style="font-size:75%">' . $pre . '</span></td>';
				
//			$str .= $initials['legacyInitials'] . '&nbsp;(' . $pre . ' &nbsp;' . number_format((float)intval($initials['minutes'])/60, 2, '.', '') . ')';
			$str .= '</tr>';
		}
		$calendar .= $str . '</table>';
		
	}
	
	
	$calendar.= '</td>';
	if($running_day == 6):
	$calendar.= '</tr>';
	if(($day_counter+1) != $days_in_month):
	$calendar.= '<tr class="calendar-row">';
	endif;
	$running_day = -1;
	$days_in_this_week = 0;
	endif;
	$days_in_this_week++; $running_day++; $day_counter++;
	endfor;

	/* finish the rest of the days in the week */
	if($days_in_this_week < 8):
	for($x = 1; $x <= (8 - $days_in_this_week); $x++):
	$calendar.= '<td class="calendar-day-np"> </td>';
	endfor;
	endif;

	/* final row */
	$calendar.= '</tr>';

	/* end the table */
	$calendar.= '</table>';

	/* all done, return result */
	return $calendar;
}

/* sample usages */

$m = array('January','February','March','April','May','June','July','August','September','October','November','December');

for ($i = 1; $i < 13; ++$i){
	echo '<h2>' . $m[$i - 1] . '</h2>';
	echo draw_calendar($i,2015);
}

?>