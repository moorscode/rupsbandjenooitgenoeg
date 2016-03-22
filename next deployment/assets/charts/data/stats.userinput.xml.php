<?php
include( "../../php/class.Database.php" );
$db = Database::getInstance();

$days_ago      = array();
$show_num_days = 7;

$today = mktime( 0, 0, 0 );

$query = $db->query( "SELECT `phrase`, `timestamp` FROM `dev__UserInput` ORDER BY `timestamp` DESC" );
while ( $row = $db->assoc( $query ) ) {

	$event_day = mktime( 0, 0, 0, date( 'm', $row['timestamp'] ), date( 'd', $row['timestamp'] ), date( 'Y', $row['timestamp'] ) );
	$day       = $today - $event_day;
	$day /= ( 60 * 60 * 24 );

	if ( ! is_array( $days_ago[ $day ] ) ) {
		$days_ago[ $day ] = array();
	}

	$days_ago[ $day ][ $row['phrase'] ]++;
}

if ( count( $days_ago ) > $show_num_days ) {
	$days_ago = array_splice( $days_ago, 0, $show_num_days );
}

$days_ago = array_reverse( $days_ago, true );

if ( count( $days_ago ) == 0 ) {
	$axis          = "<string>today</string>";
	$bug_data      = "<number>0</number>";
	$question_data = "<number>0</number>";
	$idea_data     = "<number>0</number>";
}

foreach ( $days_ago as $index => $values ) {
	$day = ( $index == 0 ) ? "today" : $index . "\rday" . ( ( $index == 1 ) ? "" : "s" ) . " ago";

	$axis .= "<string>$day</string>";

	$bug_data .= "<number>" . intval( $values['#BUG'] ) . "</number>";
	$question_data .= "<number>" . intval( $values['#VRAAG'] ) . "</number>";
	$idea_data .= "<number>" . intval( $values['#IDEE'] ) . "</number>";

	$max_value = ( $max_value < intval( $values['#BUG'] ) ) ? intval( $values['#BUG'] ) : $max_value;
	$max_value = ( $max_value < intval( $values['#VRAAG'] ) ) ? intval( $values['#VRAAG'] ) : $max_value;
	$max_value = ( $max_value < intval( $values['#IDEE'] ) ) ? intval( $values['#IDEE'] ) : $max_value;
}

$max_value = $max_value + 1;
$steps     = ( $max_value > 5 ) ? 5 : $max_value;

$register_xml = file_get_contents( "stats.userinput.xml" );

$register_xml = str_replace( "#AXIS#", $axis, $register_xml );
$register_xml = str_replace( "#BUG_DATA#", $bug_data, $register_xml );
$register_xml = str_replace( "#QUESTION_DATA#", $question_data, $register_xml );
$register_xml = str_replace( "#IDEA_DATA#", $idea_data, $register_xml );

$register_xml = str_replace( "#MAX_VALUE#", $max_value, $register_xml );
$register_xml = str_replace( "#STEPS#", $steps, $register_xml );

echo $register_xml;

