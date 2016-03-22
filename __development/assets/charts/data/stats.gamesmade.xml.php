<?php
include( "../../php/class.Database.php" );
$db = Database::getInstance();

$days_ago      = array();
$show_num_days = 7;

$today = mktime( 0, 0, 0 );

$query = $db->query( "SELECT `queuetime`, `starttime`, `endtime`, `timestamp` FROM `stats__Games` ORDER BY `timestamp` DESC" );
while ( $row = $db->assoc( $query ) ) {

	$event_day = mktime( 0, 0, 0, date( 'm', $row['timestamp'] ), date( 'd', $row['timestamp'] ), date( 'Y', $row['timestamp'] ) );
	$day       = $today - $event_day;
	$day /= ( 60 * 60 * 24 );

	if ( ! is_array( $days_ago[ $day ] ) ) {
		$days_ago[ $day ]           = array();
		$days_ago[ $day ]['queues'] = 0;
		$days_ago[ $day ]['games']  = 0;
	}

	$queuetime = intval( $row['queuetime'] );
	$starttime = intval( $row['starttime'] );
	$endtime   = intval( $row['endtime'] );

	$queue_length = ( ( $starttime == 0 ) ? $endtime : $starttime ) - $queuetime; // in seconds
	$game_length  = ( $starttime == 0 ) ? 0 : $endtime - $starttime; // if game was active, in seconds

	$days_ago[ $day ]['queues'] += 1;
	$days_ago[ $day ]['games'] += ( $game_length == 0 ) ? 0 : 1;
}

if ( count( $days_ago ) > $show_num_days ) {
	$days_ago = array_splice( $days_ago, 0, $show_num_days );
}

$days_ago = array_reverse( $days_ago, true );

if ( count( $days_ago ) == 0 ) {
	$axis       = "<string>today</string>";
	$queue_data = "<number>0</number>";
	$games_data = "<number>0</number>";
}

foreach ( $days_ago as $index => $value ) {
	$day = ( $index == 0 ) ? "today" : $index . "\rday" . ( ( $index == 1 ) ? "" : "s" ) . " ago";
	$axis .= "<string>$day</string>";

	$queue_data .= "<number>" . $value['queues'] . "</number>";
	$games_data .= "<number>" . $value['games'] . "</number>";

	$max_value = ( $max_value < $value['queues'] ) ? $value['queues'] : $max_value;
	$max_value = ( $max_value < $value['games'] ) ? $value['games'] : $max_value;
}

$max_value += 1;
$steps = ( $max_value > 5 ) ? 5 : $max_value;

$register_xml = file_get_contents( "stats.gamesmade.xml" );
$register_xml = str_replace( "#AXIS#", $axis, $register_xml );
$register_xml = str_replace( "#QUEUE_DATA#", $queue_data, $register_xml );
$register_xml = str_replace( "#GAMES_DATA#", $games_data, $register_xml );

$register_xml = str_replace( "#MAX_VALUE#", $max_value, $register_xml );
$register_xml = str_replace( "#STEPS#", $steps, $register_xml );

echo $register_xml;

