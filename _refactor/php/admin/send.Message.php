<?php

//send.Message.php
require_once( "../assets/php/class.Database.php" );
require_once( "../assets/php/functions.php" );

$db = &Database::getInstance();

if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
	$pid = intval( $_POST['pid'] );
	if ( $pid == 0 ) {
		die( "{send:'false'}" );
	}

	$reply = intval( $_POST['reply'] );

	$time    = time();
	$message = $db->prepare( htmlspecialchars( $_POST['message'] ) );

	$db->query( "INSERT INTO `global__Messages` (`player_id`, `message`, `timestamp`) VALUES ('$pid', '$message', '$time')" );

	if ( $reply > 0 ) {
		$db->query( "UPDATE `dev__UserInput` SET `looked_at`='1' WHERE `id`='$reply'" );
		die( "{send:'true',reply:$reply}" );
	}

	die( "{send:'true'}" );
}

$pid   = intval( $_GET['pid'] );
$reply = intval( $_GET['reply'] );

if ( $pid > 0 ) {
	$player_query = $db->query( "SELECT `username` FROM `global__Players` WHERE `id`='$pid' LIMIT 0,1" );
	if ( $user = $db->assoc( $player_query ) ) {
		$username = $user['username'];
		$db->free( $player_query );
	}
}

/* In reply to UserInput */
if ( $reply > 0 ) {
	$ui_query = $db->query( "SELECT `phrase`, `text` FROM `dev__UserInput` WHERE `id`='$reply'" );
	if ( $msg = $db->assoc( $ui_query ) ) {
		$message = "\n\n\nIn antwoord op:\n\"{$msg['phrase']} {$msg['text']}\"";
		$db->free( $ui_query );
	}
}

?>
<h2>Send message<?= ( ( $username != "" ) ? " to $username" : "" ) ?></h2>

<input type="hidden" name="pid" value="<?= $pid ?>"/>
<input type="hidden" name="reply" value="<?= $reply ?>"/>
<table>
	<tr>
		<td align="right">Message:</td>
		<td><textarea name="message" rows="10" cols="55"><?= $message ?></textarea></td>
	</tr>
</table>