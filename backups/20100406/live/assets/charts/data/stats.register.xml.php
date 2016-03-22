<?php
include("../../php/class.Database.php");
$db = Database::getInstance();

$days_ago = array();
$show_num_days = 7;

$today = mktime(0,0,0);

$query = $db->query("SELECT `type`, `timestamp` FROM `stats__Registration`");
while($row = $db->assoc($query)) {
	
	$event_day = mktime(0,0,0,date('m',$row['timestamp']),date('d',$row['timestamp']),date('Y',$row['timestamp']));
	$day = $today - $event_day;
	$day /= (60*60*24);
	
	if(!is_array($days_ago[$day])) {
		$days_ago[$day] = array();
	}
	
	$days_ago[$day][$row['type']]++;
}

if(count($days_ago) > $show_num_days) {
	$days_ago = array_splice($days_ago, 0, $show_num_days);
}

$days_ago = array_reverse($days_ago, true);

if(count($days_ago) == 0) {
	$axis = "<string>today</string>";
	$data_register = "<number>0</number>";
	$data_verify	= "<number>0</number>";
}

foreach($days_ago as $index=>$values) {
	$day = ($index == 0)?"today":$index."\rday".(($index == 1)?"":"s")." ago";
	$axis .= "<string>$day</string>";
	
	$data_register .= "<number>".intval($values['REGISTER'])."</number>";
	$data_verify 	.= "<number>".intval($values['VERIFY'])."</number>";
	
	$max_value = ($max_value < intval($values['REGISTER']))?intval($values['REGISTER']):$max_value;
	$max_value = ($max_value < intval($values['VERIFY']))?intval($values['VERIFY']):$max_value;
}

$max_value = $max_value + 1;
$steps = ($max_value > 5)?5:$max_value;

$register_xml = file_get_contents("stats.register.xml");
$register_xml = str_replace("#AXIS#", 				$axis, 				$register_xml);
$register_xml = str_replace("#REGISTER_DATA#", 	$data_register, 	$register_xml);
$register_xml = str_replace("#VERIFY_DATA#", 	$data_verify, 		$register_xml);

$register_xml = str_replace("#MAX_VALUE#", 		$max_value, 		$register_xml);
$register_xml = str_replace("#STEPS#", 			$steps, 				$register_xml);

echo $register_xml;

?>