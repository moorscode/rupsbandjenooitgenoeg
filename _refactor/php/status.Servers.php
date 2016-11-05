<?php
/*
 * Getting the Servers-status
 *
 * 	Made this a stand-alone file, to parse output to JavaScript and PHP at the same time.
 *		Reusing code ftw.
 *
 *	nov 7th, 2009 @ 23:00
*/

//require_once("functions.php");
//require_once("class.Database.php");

$fromAjax = false;


if ( ! isset( $db ) ) {
	$fromAjax = true;
	//$db = &Database::getInstance();
}

/*
$query = $db->query("SELECT `game`, `chat` FROM `global__ServerStatus`");
$status = $db->assoc($query);

$chat_server_online = intval($status['chat']);
$game_server_online = intval($status['game']);
*/

// ps lists all processes, filtering only the SCREEN names of the servers
$chat_server_online = ( exec( "ps aux | grep rupsChatServer | grep -v grep" ) != "" );
$game_server_online = ( exec( "ps aux | grep rupsGameServer | grep -v grep" ) != "" );


// if no database has been defined; it's called from JavaScript, so output to the buffer
if ( $fromAjax ) {
	$chat = ( $chat_server_online ) ? 1 : 0;
	$game = ( $game_server_online ) ? 1 : 0;

	die( "{'chatServer':$chat, 'gameServer':$game}" );
}

