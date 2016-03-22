<?php

//manage.Users.php
require_once( "../assets/php/class.Database.php" );
require_once( "../assets/php/functions.php" );

$db = &Database::getInstance();

setlocale( LC_ALL, 'nl_NL' );

$pid = intval( $_GET['pid'] );
if ( $pid > 0 ) {
	// remove user
	$db->query( "DELETE FROM `global__Players` WHERE `id`='$id'" );
}

// list users matched with text
$text = trim( $db->prepare( htmlentities( $_GET['text'] ), 64 ) );

if ( $text == "" ) {
	return;
}
if ( $text == "*" ) {
	$text = "";
}

$query_users = $db->query( "SELECT `id`, `username` FROM `global__Players` WHERE `username` LIKE '%$text%' AND `id` <> 1 AND `username` <> '' LIMIT 0, 10" );
while ( $user = $db->assoc( $query_users ) ) {

	$reg_query = $db->query( "SELECT `type`, `timestamp` FROM `stats__Registration` WHERE `player_id`='{$user['id']}'" );
	while ( $reg = $db->assoc( $reg_query ) ) {
		if ( $reg['type'] == "REGISTER" ) {
			$created = "<br />Registered: " . strftime( "%e %b %Y om %l:%M %p", $reg['timestamp'] );
		}
		else {
			$verified = "<br />Verified: " . strftime( "%e %b %Y om %l:%M %p", $reg['timestamp'] );
		}
	}
	$db->free( $reg_query );

	echo <<<EOUSER
	
	<div class="uidata">{$user['username']}<small>{$created}{$verified}</small></div>
	<div class="uicontrol"><a href="javascript:sendMessage({$user['id']});"><img border="0" src="../assets/html/images/message.gif" alt="message" /></a><br /><a href="javascript:removeUser({$user['id']});"><img border="0" src="../assets/html/images/delete.gif" alt="delete" /></a></div>
	<div class="row"></div>
	
EOUSER;
}

