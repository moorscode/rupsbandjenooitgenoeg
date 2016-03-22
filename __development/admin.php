<?php

require_once( "assets/php/functions.php" );
require_once( "assets/php/class.Database.php" );

session_start();

if ( $_SESSION['user_id'] != 1 ) {
	header( "Location: ./" );
	die();
}

$db = &Database::getInstance();

//include("assets/php/update.serverstatus.php");

exec( "screen -wipe" );

include( "assets/php/status.Servers.php" );

if ( $chat_server_online ) {
	$chat_id = exec( "ps aux | grep rupsChatServer | grep -v grep | awk '{print $2'}" );
}

if ( $game_server_online ) {
	$game_id = exec( "ps aux | grep rupsGameServer | grep -v grep | awk '{print $2'}" );
}

$do = $_GET['do'];
$what = $_GET['what'];

if ( $what == "chat" ) {
	if ( isset( $chat_id ) ) {
		kill_server( "chat" );
	}

	if ( $do == "start" ) {
		exec( "screen -d -m -S rupsChatServer php ./assets/php/server.chat.php &" );
	}

	usleep( 500 );
	header( "Location: admin.php" );
	die();
}

if ( $what == "game" ) {
	if ( isset( $game_id ) ) {
		kill_server( "game" );
	}

	if ( $do == "start" ) {
		exec( "screen -d -m -S rupsGameServer php ./assets/php/server.game.php &" );
	}

	usleep( 500 );
	header( "Location: admin.php" );
	die();
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>Rupsbandjenooitgenoeg: Server Beheer</title>
	<link href="assets/html/style.css" type="text/css" rel="stylesheet"/>
	<link rel="shortcut icon" href="assets/html/images/favicon.ico" type="image/x-icon">
	<link rel="icon" href="assets/html/images/favicon.ico" type="image/x-icon">
	<script language="JavaScript" type="text/javascript" src="assets/html/swfobject.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/jquery-1.3.2.min.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/library.js"></script>
	<script language="javascript" type="text/javascript">
		$(document).ready(function () {
			updateAdminInfo();
		});
	</script>
</head>

<body>

<div id="body">

	<div id="leftHolder">
		<br/>
		<h2>Server statistieken</h2>

		<div id="adminStats">
			Bezig met laden...
		</div>
	</div>

	<div id="mainHolder" style="padding: 10px; width: 430px; height: 280px">
		<h2>Server beheer</h2>

		<div><img id="chatStatus" src="assets/html/images/<?= ( isset( $chat_id ) ? "online" : "offline" ) ?>.gif"
		          width="12" height="11"/> &nbsp;Chat Server
		</div>
		<div><img id="chatStatus" src="assets/html/images/<?= ( isset( $game_id ) ? "online" : "offline" ) ?>.gif"
		          width="12" height="11"/> &nbsp;Game Server
		</div>
		<br/>

		<div>
			<input type="button" name="chatControl" value=" <?= ( isset( $chat_id ) ? "kill" : "start" ) ?> "
			       onclick="location.href='admin.php?do=<?= ( isset( $chat_id ) ? "kill" : "start" ) ?>&what=chat';"/>
			Chat Server<br/>
			<input type="button" name="chatControl" value=" <?= ( isset( $game_id ) ? "kill" : "start" ) ?> "
			       onclick="location.href='admin.php?do=<?= ( isset( $game_id ) ? "kill" : "start" ) ?>&what=game';"/>
			Game Server
		</div>
	</div>

	<div id="highscores">
		<br/>
		<h2><?= $_SESSION['user_name'] ?></h2>
		<br/>
		<input type="button" name="return" value=" to the game " onclick="location.href='./';"/>
	</div>
</div>

</body>
</html>