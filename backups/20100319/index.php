<?php

require_once("assets/php/functions.php");
require_once("assets/php/class.Database.php");
require_once("assets/php/class.Statistics.php");

session_start();

if((isset($_SESSION['registered']) || isset($_SESSION['user_id'])) && isset($_GET['invite'])) {
	header("Location: ./");
	die();
}

$db = &Database::getInstance();
$stats = new Statistics();

// verifying account:
if(isset($_GET['verify'])) {
	$verify 	= get('verify', $_GET);
	$email 	= get('email', $_GET);
	
	$result = $db->query("SELECT `id`, `username` FROM `global__Players` WHERE `email`='$email' AND `verify`='$verify'");
	if($user = $db->assoc($result)) {
		$_SESSION['user_id'] = $user['id'];
		$_SESSION['user_name'] = $user['username'];
		
		$phpsession = session_id();
		
		push_message(file_get_contents("assets/html/templates/registration_complete.html"));
		$db->query("UPDATE `global__Players` SET `verify`='', `phpsession`='$phpsession' WHERE `id`=".$user['id']);
		
		$stats->add("player_id", $user['id']);
		$stats->add("type", "VERIFY");
		$stats->save("Registration");
		
	} else {
		$result = $db->query("SELECT `id`, `username` FROM `global__Players` WHERE `email`='$email'");
		if($user = $db->assoc($result)) {
			// already verified.
			header("Location: ./");
			die();
		}
		
		push_message("Fout in verifying." . $verify . " - " . $email);	
	}
}

// auto-login if a cookie has been set:
if(!$_SESSION['cookie_login'] && intval($_COOKIE['automatic_login']) == 1) {
	$_SESSION['cookie_login'] = true;
	$email = $_COOKIE['login_email'];
	$pass = $_COOKIE['login_pass'];
	login($email, $pass, true);
}

// login / register is sumbitted:
if($_SERVER['REQUEST_METHOD'] == "POST") {
	$email	= get('email', $_POST, 128);
	$pass		= md5(get('pass', $_POST, 128));
	
	if(is_valid_email($email)) {
		login($email, $pass);
	} else {
		push_message('<h2>Login fout!</h2><br />Het ingevoerde e-mail adres is ongeldig.<br />Controleer het adres en probeer opnieuw!');
	}
	
	header("Location: ./");
	die();
}

// special actions:
if(isset($_GET['do'])) {
	switch($_GET['do']) {
		case 'logout':
			logout();
			break;
	}
}

if(!isset($_SESSION['user_id'])) {
	$db->query("UPDATE `global__Players` SET `PHPSESSION`='' WHERE `PHPSESSION`='".session_id()."'");
}

$forceSingle = (intval($_SESSION['user_id']) == 0)?1:0;

require("assets/php/status.Servers.php");

$chat_online_status = (!$chat_server_online)?"offline":"online";
$game_online_status = (!$game_server_online)?"offline":"online";

$game_type = ($game_server_online)?"multi":"single";

$game_holder = ($game_server_online && $chat_server_online && !$forceSingle)?"sideFlash":"mainFlash";
$chat_holder = ($game_server_online && $chat_server_online && !$forceSingle)?"mainFlash":"sideFlash";

$game_div = str_replace("Flash", "Holder", $game_holder);
$chat_div = str_replace("Flash", "Holder", $chat_holder);


?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Rupsbandjenooitgenoeg</title>
	<link rel="stylesheet" 		href="assets/html/style.css" 				type="text/css"  />
	<link rel="shortcut icon" 	href="assets/html/images/favicon.ico" 	type="image/x-icon" />
	<link rel="icon" 				href="assets/html/images/favicon.ico" 	type="image/x-icon" />
	<script language="JavaScript" type="text/javascript" src="assets/html/swfobject.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/jquery-1.3.2.min.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/jquery.scrollTo-1.4.2-min.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/flash_communication.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/library.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/php/robots.php"></script>
	<script language="JavaScript" type="text/javascript">
	//<![CDATA[
	
	var gameHolder = '<?= $game_holder ?>';
	var chatHolder = '<?= $chat_holder ?>';
		
	var gameDiv 	= '<?= $game_div ?>';
	var chatDiv 	= '<?= $chat_div ?>';
	
	var openInvite = false;
	
	$(document).ready(function() {
		$().mousemove(function(e){
			mouse.x = e.pageX;
			mouse.y = e.pageY;
		});
		
<?php
	if(isset($_GET['invite'])) {
		$_SESSION['invite_code'] = $_GET['invite']; ?>
		openInvite = true;
<?php
	}
?>
		updateMessages();
		updateHighscores();
		updateServerStatus();
		updateAchievements();
<?php
if(isset($_SESSION['user_id'])) { ?>
		updatePlayerInfo();
<?php
} ?>
	});
	
	//]]>
	</script>
</head>
<body>

<div id="achievement"></div>
<div id="overlay" onclick="hideMessage();"></div>

<div id="message">
	<div id="text"></div>
	<div class="button"><input type="button" id="controlMessageWindow" onclick="hideMessage();" value=" sluit " /></div>
</div>

<div id="dynamicForm">
	<form onsubmit="return false;" action="">
	<div id="form"></div>
	<div class="button"><input type="submit" id="dynamicFormSubmit" value=" verstuur " /> <input type="button" id="dynamicFormCancel" value=" annuleer " /></div>
	</form>
</div>


<div id="body">

<?php
if(intval($_SESSION['user_id']) > 0) { ?>
	<div id="invite" onclick="showInvite();">
		<img src="assets/html/images/invite.gif" width="142" height="36" alt="nodig vrienden uit" border="0" />
	</div>
<?php
} ?>

	<div id="leftHolder">
		<br />
<?php
if(isset($_SESSION['user_id'])) { ?>
		
		<div id="personalStats">
			Bezig met laden...
		</div>
<?php
} else { ?>
		<h2>Inloggen</h2>
		
		<form name="login" method="post" action="./" autocomplete="off">
		<input type="hidden" name="type" value="login" />
		
		<input type="text" name="email" maxlength="128" value="voer je e-mail adres in" id="login_user" onfocus="if(this.value == 'voer je e-mail adres in') this.value = '';" onblur="if(this.value == '') this.value = 'voer je e-mail adres in';" /><br />
		<div style="line-height: 5px; width: 100%; text-align: center;">&nbsp;</div>
		
		<input type="password" name="pass" maxlength="128" value="password" id="login_pass" onfocus="if(this.value == 'password') this.value = '';" onblur="if(this.value == '') this.value = 'password';" /><br />
		<div style="line-height: 5px; width: 100%; text-align: center;">&nbsp;</div>
		<input type="checkbox" name="remember" /> Onthoud mij<br />
		<br />
		<input type="submit" name="login" class="submit login" value=" log in! " onclick="return submitLogin(this.form, this.name);" />

		</form>
		
		<br /><br />
		<br /><br />

		Heb je nog geen account?<br /><br />

		<span class="big"><a href="javascript:showRegister();">Registreer je</a> dan nu!</span>

<?php
}
?>
	</div>

	<div id="mainHolder">
		<div id="mainFlash"><a href="http://www.adobe.com/go/getflashplayer" target="_blank"><img style="margin-top: 135px;" src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" align="middle" border="0" /></a></div>
	</div>
	
	<div id="sideHolder">
		<div id="sideFlash"></div>
	</div>
	
	<div id="logout">
	
		<h2>Menu</h2>
		<!--// <div class="button" onclick="showMenu();">show menu</div> //-->
		
<?php
if(isset($_SESSION['user_id'])) {
		//<input type="button" name="config" value=" opties " onclick="createConfigFlash();" /><br />
		?>
		
		<input type="button" name="profile" value=" wijzig je profiel " onclick="showProfile();" /><br />
		<input type="button" name="config" value=" opties " disabled /><br />
<?php
	if($_SESSION['user_id'] != 1) {
		//<input type="button" name="achievement" value=" presetaties " onclick="showAchievements();" />
		?>
		
		<input type="button" name="achievement" value=" presetaties " disabled />
		<br />
		<input type="button" name="faq" value=" help! " onclick="showFAQ();" /><br />
		<input type="button" name="bug" value=" bug lijst " onclick="showBugs();" /><br />
		<br />
		<input type="button" name="contact" value=" contact " onclick="showContactForm();" /><br />
<?php
	} else { ?>
		<input type="button" name="admin" value=" admin panel " onclick="location.href='./admin';" /><br />
		<input type="button" name="admin" value=" server beheer " onclick="location.href='admin.php';" /><br />
<?php
	} ?>
		<br />
		<input type="button" name="logout" value=" uitloggen " onclick="logout();" />
<?php
} else {
?>
		<input type="button" name="faq" value=" help! " onclick="showFAQ();" /><br />
		<input type="button" name="bug" value=" bug lijst " onclick="showBugs();" />
<?php
}
?>
	</div>
	
	<div id="menu"></div>
	
	<div id="highscores">
		<h2>Top 5 spelers</h2>
		
		<div id="topscores">
			Bezig met laden...
		</div>
	</div>
	
	<div id="serverStats">
	
		<h2>Server informatie</h2>
		<div><img id="chatStatus" src="assets/html/images/<?= $chat_online_status ?>.gif" width="12" height="11" alt="Chat Server Status" /> &nbsp;Chat Server</div>
		<div><img id="gameStatus" src="assets/html/images/<?= $game_online_status ?>.gif" width="12" height="11" alt="Game Server Status" /> &nbsp;Game Server</div>
	
	</div>
	
	<div id="gameControl">
		<input type="button" id="practise" name="practise" value=" " onclick="togglePractise();" /><br />
	</div>
	
</div>

<div id="popup"></div>

</body>
</html>