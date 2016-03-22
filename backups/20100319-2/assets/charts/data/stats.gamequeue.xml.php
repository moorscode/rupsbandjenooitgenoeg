<?php
include("../../php/class.Database.php");
$db = Database::getInstance();

$days_ago = array();
$show_num_days = 7;

$today = mktime(0,0,0);

$query = $db->query("SELECT `time_length`, `timestamp` FROM `stats__Time` WHERE `time_type`='QUEUE' ORDER BY `timestamp` DESC");
while($row = $db->assoc($query)) {
	
	$event_day = mktime(0,0,0,date('m',$row['timestamp']),date('d',$row['timestamp']),date('Y',$row['timestamp']));
	$day = $today - $event_day;
	$day /= (60*60*24);
	
	if(!is_array($days_ago[$day])) {
		$days_ago[$day] = array();
	}
	
	$days_ago[$day]['count']++;
	$days_ago[$day]['total']+=$row['time_length'];
}

if(count($days_ago) > $show_num_days) {
	$days_ago = array_splice($days_ago, 0, $show_num_days);
}

$days_ago = array_reverse($days_ago, true);

if(count($days_ago) == 0) {
	$axis = "<string>today</string>";
	$idle_data = "<number>0</number>";
}

foreach($days_ago as $index=>$value) {
	$average = $value['total'] / $value['count'];
	
	$day = ($index == 0)?"today":$index."\rday".(($index == 1)?"":"s")." ago";
	$axis .= "<string>$day</string>";
	
	$queue_data .= "<number>$average</number>";
	
	$max_value = ($max_value < $average)?$average:$max_value;
}

$max_value *= 1.1;
$steps = ($max_value > 5)?5:$max_value;

$register_xml = file_get_contents("stats.gamequeue.xml");
$register_xml = str_replace("#AXIS#", $axis, $register_xml);
$register_xml = str_replace("#QUEUE_DATA#", $queue_data, $register_xml);

$register_xml = str_replace("#MAX_VALUE#", $max_value, $register_xml);
$register_xml = str_replace("#STEPS#", $steps, $register_xml);

echo $register_xml;

?>