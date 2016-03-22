<?php
/*
 * Message parsing file
 *
 * 	This file is called from JavaScript each time a message can be displayed.
 *		Parsing through the messages array, saved in the session.
 *		Each time giving the first message recieved, following up until the array is empty again.
 *
 *	nov 7th, 2009 @ 11:00 pm
 * modified for db messaging, nov 30th, 2009 @ 11:00 am
*/

session_start();

// check for database queued messages
if ( intval( $_SESSION['user_id'] ) > 0 ) {
	require( "class.Database.php" );
	require_once( "functions.php" );
	$db = &Database::getInstance();

	$user_id = intval( $_SESSION['user_id'] );
	$query   = $db->query( "SELECT `id`, `message`, `timestamp` FROM `global__Messages` WHERE `player_id`='$user_id' AND `read`=0 ORDER BY `timestamp` ASC LIMIT 0,1" );
	if ( $message = $db->assoc( $query ) ) {
		if ( ! is_array( $_SESSION['messages'] ) ) {
			$_SESSION['messages'] = array();
		}

		$message['message'] = nl2br( $message['message'] );

		setlocale( LC_ALL, 'nl_NL' );
		$message['message'] .= "<br /><br />Met vriendelijke groet,<br />Jip Moors (Ontwikkelaar Rupsbandjenooitgenoeg)<br /><small><i>Verstuurd: " . strftime( "%A %e %B %Y om %l:%M %p", $message['timestamp'] ) . ".</i></small>";

		array_push( $_SESSION['messages'], $message['message'] );

		$db->query( "UPDATE `global__Messages` SET `read`='" . time() . "' WHERE `id`='" . $message['id'] . "'" );
	}
}

if ( isset( $_SESSION['messages'] ) ) {
	if ( count( $_SESSION['messages'] ) > 0 ) {
		echo $_SESSION['messages'][0];
		array_shift( $_SESSION['messages'] );
	}
}

