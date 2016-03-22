<?php
include( "../../php/class.Database.php" );
$db = Database::getInstance();

$scale = array();
array_push( $scale, array( "divide_by" => 1, "label" => "seconds", "short_label" => "secs" ) );
array_push( $scale, array( "divide_by" => 60, "label" => "minutes", "short_label" => "mins" ) );
array_push( $scale, array( "divide_by" => 60 * 60, "label" => "hours", "short_label" => "hours" ) );
array_push( $scale, array( "divide_by" => 60 * 60 * 24, "label" => "days", "short_label" => "days" ) );

// load data.
$data_values = array();
$axis_values = array();

$high      = time();
$max_value = 0;

$query = $db->query( "SELECT * FROM `stats__Servers` WHERE `type`='$server_type' ORDER BY `timestamp` DESC" );
while ( $row = $db->assoc( $query ) ) {
	if ( $row['status'] == "OFFLINE" ) {
		$offline_timestamp = $row['timestamp'];
	}
	else {
		if ( ! isset( $offline_timestamp ) ) {
			$offline_timestamp = time();
		}

		$online_timestamp = $row['timestamp'];
		$value            = $offline_timestamp - $online_timestamp;

		if ( ! isset( $min_value ) ) {
			$min_value = $value;
		}

		$max_value = ( $max_value > $value ) ? $max_value : $value;

		array_push( $data_values, $value );
		array_push( $axis_values, $online_timestamp );
	}
}

$divide_data_by = 0;
$divide_axis_by = 0;

if ( count( $data_values ) == 0 ) {
	$data_values[0] = "<number>0</number>";
	$axis_values[0] = time();

	$high = time();
}
else {
	if ( count( $data_values > $display_count ) ) {
		$data_values = array_splice( $data_values, 0, $display_count );
		$axis_values = array_splice( $axis_values, 0, $display_count );
	}

	$data_values = array_reverse( $data_values );
	$axis_values = array_reverse( $axis_values );

	$first_online = $axis_values[ count( $axis_values ) - 1 ];
	$last_online  = $axis_values[0];

	$range = ( time() - $last_online );

	for ( $i = 0; $i < count( $scale ) && round( $range / $scale[ $i ]['divide_by'] ) > 10; $i++ ) {
		$divide_axis_by = $i;
	}

	for ( $i = 0; $i < count( $scale ) && round( $max_value / $scale[ $i ]['divide_by'] ) > 1; $i++ ) {
		$divide_data_by = $i;
	}

	foreach ( $axis_values as &$value ) {
		$when  = round( ( $high - $value ) / $scale[ $divide_axis_by ]['divide_by'] ) . " " . $scale[ $divide_axis_by ]['short_label'] . "\rago";
		$value = "<string>$when</string>";
	}

	foreach ( $data_values as &$data ) {
		$data = "<number>" . round( $data / $scale[ $divide_data_by ]['divide_by'] ) . "</number>";
	}
}

$xml_title = str_replace( "#TIME_SCALE#", $scale[ $divide_data_by ]['label'], $xml_title );

$max_value = round( $max_value / $scale[ $divide_data_by ]['divide_by'] ) + 1;
$steps     = ( $max_value < 5 ) ? $max_value : 5;


$data_values = implode( "\n", $data_values );
$axis_values = implode( "\n", $axis_values );

$server_xml = file_get_contents( "stats.servers.xml" );
$server_xml = str_replace( "#TITLE#", $xml_title, $server_xml );
$server_xml = str_replace( "#AXIS#", $axis_values, $server_xml );
$server_xml = str_replace( "#DATA#", $data_values, $server_xml );
$server_xml = str_replace( "#MAX_VALUE#", $max_value, $server_xml );
$server_xml = str_replace( "#STEPS#", $steps, $server_xml );
$server_xml = str_replace( "#SUFFIX#", $scale[ $divide_data_by ]['short_label'], $server_xml );

echo $server_xml;

