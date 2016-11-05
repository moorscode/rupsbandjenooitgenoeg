<?php

require_once( "functions.php" );
require_once( "class.Database.php" );
require_once( "class.Statistics.php" );

session_start();

$user_id = intval( $_SESSION['user_id'] );

// if not logged in; don't output anything.
if ( ! isset( $_SESSION['user_id'] ) || $user_id == 0 ) {
	exit( 1 );
	die();
}

$db    = &Database::getInstance();
$stats = new Statistics();

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	$global_volume = $_POST['global_volume'];
	$music_volume  = $_POST['music_volume'];
	$fx_volume     = $_POST['fx_volume'];

	$key_forward = $_POST['key_forward'];
	$key_back    = $_POST['key_back'];
	$key_left    = $_POST['key_left'];
	$key_right   = $_POST['key_right'];

	$graphics = $_POST['graphics'];


	$query        = $db->query( "SELECT `volume_global`, `volume_music`, `volume_fx`, `key_forward`, `key_back`, `key_left`, `key_right`, `graphics` FROM `global__Players` WHERE `id`='$user_id'" );
	$old_settings = $db->assoc( $query );


	/** Save changes **/
	$db->query( "UPDATE `global__Players` SET `volume_global`='$global_volume', `volume_music`='$music_volume', `volume_fx`='$fx_volume', `key_forward`='$key_forward', `key_back`='$key_back', `key_left`='$key_left', `key_right`='$key_right', `graphics`='$graphics' WHERE `id`='$user_id'" );

	/** Save differences **/

	$new_settings                  = array();
	$new_settings['volume_global'] = $global_volume;
	$new_settings['volume_music']  = $music_volume;
	$new_settings['volume_fx']     = $fx_volume;
	$new_settings['key_forward']   = $key_forward;
	$new_settings['key_back']      = $key_back;
	$new_settings['key_left']      = $key_left;
	$new_settings['key_right']     = $key_right;
	$new_settings['graphics']      = $graphics;


	$changes = array_diff_assoc( $old_settings, $new_settings );

	foreach ( $changes as $key => $value ) {
		$stats->add( "player_id", $user_id );
		$stats->add( "column", $key );
		$stats->add( "old_value", $old_settings[ $key ] );
		$stats->add( "new_value", $new_settings[ $key ] );
		$stats->save( "Settings" );
	}

	var_dump( $changes );
	die();
}

$query_config = $db->query( "SELECT * FROM `global__Players` WHERE `id`='$user_id'" );
if ( $config = $db->assoc( $query_config ) ) {
	extract( $config );
}

if ( ! isset( $graphics ) || empty( $graphics ) ) {
	$graphics = "default";
}

$config_response = file_get_contents( "../html/templates/config.response.xml" );

$config_response = str_replace( "#VOLUME_GLOBAL#", $volume_global, $config_response );
$config_response = str_replace( "#VOLUME_MUSIC#", $volume_music, $config_response );
$config_response = str_replace( "#VOLUME_FX#", $volume_fx, $config_response );

$config_response = str_replace( "#KEY_FORWARD#", $key_forward, $config_response );
$config_response = str_replace( "#KEY_BACK#", $key_back, $config_response );
$config_response = str_replace( "#KEY_LEFT#", $key_left, $config_response );
$config_response = str_replace( "#KEY_RIGHT#", $key_right, $config_response );

$config_response = str_replace( "#GRAPHICS#", $graphics, $config_response );

echo $config_response;

