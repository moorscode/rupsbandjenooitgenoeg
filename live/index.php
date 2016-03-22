<?php

require_once( "assets/php/functions.php" );
require_once( "assets/php/class.Database.php" );
require_once( "assets/php/class.Statistics.php" );

session_start();

if ( ( isset( $_SESSION['registered'] ) || isset( $_SESSION['user_id'] ) ) && isset( $_GET['invite'] ) ) {
	header( "Location: ./" );
	die();
}


if ( isset( $_COOKIE['hide_intro'] ) ) {
	$_SESSION['hide_intro'] = true;
}

$show_intro = false;
if ( ! isset( $_SESSION['hide_intro'] ) ) {
	$show_intro             = true;
	$_SESSION['hide_intro'] = true;
	setcookie( "hide_intro", true, time() + 3600 * 24 * 30, null, null, false, true );
}


$db = &Database::getInstance();
$stats = new Statistics();


/* Check for site access */
if ( intval( $_SESSION['user_id'] ) != 1 ) {
	$access_query = $db->query( "SELECT `value` FROM `global__Configuration` WHERE `item`='access'" );
	if ( $data = $db->assoc( $access_query ) ) {
		if ( $data['value'] == 0 ) {
			echo file_get_contents( "assets/html/templates/no_access.html" );

			return;
		}
	}
}

if ( isset( $_GET['action'] ) ) {
	$action = $_GET['action'];

	if ( $action == "passwordreset" ) {
		$code = $db->prepare( $_GET['code'], 32 );

		$query = $db->query( "SELECT `id`, `username`, `verify` FROM `global__Players` WHERE MD5(CONCAT(`invite_code`, `email`))='$code'" );
		if ( $db->num_rows( $query ) > 0 ) {
			$player = $db->assoc( $query );

			$_SESSION['user_id']    = $player['id'];
			$_SESSION['user_name']  = $player['username'];
			$_SESSION['pass_reset'] = $passwordReset = true;

			if ( $player['verify'] != "" ) {
				$db->query( "UPDATE `global__Players` SET `verify`='' WHERE `id`=" . $player['id'] );

				$stats->add( "player_id", $player['id'] );
				$stats->add( "type", "VERIFY" );
				$stats->save( "Registration" );
			}

			$stats->add( 'player_id', $player['id'] );
			$stats->add( 'type', 'RECOVER' );
			$stats->save( 'Registration' );
		}
	}
}



// verifying account:
if ( isset( $_GET['verify'] ) ) {
	$verify = get( 'verify', $_GET );
	$email  = get( 'email', $_GET );

	$result = $db->query( "SELECT `id`, `username` FROM `global__Players` WHERE `email`='$email' AND `verify`='$verify'" );
	if ( $user = $db->assoc( $result ) ) {
		$_SESSION['user_id']   = $user['id'];
		$_SESSION['user_name'] = $user['username'];

		$phpsession = session_id();

		push_message( file_get_contents( "assets/html/templates/registration_complete.html" ) );
		$db->query( "UPDATE `global__Players` SET `verify`='', `phpsession`='$phpsession' WHERE `id`=" . $user['id'] );

		$stats->add( "player_id", $user['id'] );
		$stats->add( "type", "VERIFY" );
		$stats->save( "Registration" );

	}
	else {
		$result = $db->query( "SELECT `id`, `username` FROM `global__Players` WHERE `email`='$email'" );
		if ( $user = $db->assoc( $result ) ) {
			// already verified.
			header( "Location: ./" );
			die();
		}

		push_message( "Fout in verifying." . $verify . " - " . $email );
	}
}

// login / register is sumbitted:
if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	$email = get( 'email', $_POST, 128 );
	$pass  = md5( get( 'pass', $_POST, 128 ) );

	if ( is_valid_email( $email ) ) {
		login( $email, $pass );
	}
	else {
		push_message( '<h2>Mislukt!</h2><br />Het ingevoerde e-mail adres is ongeldig!<br /><br />Controleer het adres en probeer opnieuw.' );
	}

	if ( $_SESSION['user_id'] == 1 ) {
		header( "Location: admin/" );
		die();
	}

	header( "Location: ./" );
	die();
}

// special actions:
if ( isset( $_GET['do'] ) ) {
	switch ( $_GET['do'] ) {
		case 'logout':
			logout();
			break;
	}
}

if ( intval( $_SESSION['user_id'] ) == 0 ) {
	$db->query( "UPDATE `global__Players` SET `PHPSESSION`='' WHERE `PHPSESSION`='" . session_id() . "'", true );
}

/*
if(intval($_SESSION['user_id']) == 0) {
	// auto-login if a cookie has been set:
	if(!$_SESSION['cookie_login'] && intval($_COOKIE['automatic_login']) == 1) {
		$_SESSION['cookie_login'] = true;
		
		login($_COOKIE['login_email'], $_COOKIE['login_pass'], true);
		
		if(intval($_SESSION['user_id']) == 1) {
			header("Location: ./admin/");
			die();
		}
	}
}

unset($_SESSION['cookie_login']);
*/

$loggedin = ( intval( $_SESSION['user_id'] ) == 0 ) ? 0 : 1;
$forceSingle = ( intval( $_SESSION['user_id'] ) == 0 ) ? 1 : 0;

require( "assets/php/status.Servers.php" );

$chat_online_status = ( ! $chat_server_online ) ? "offline" : "online";
$game_online_status = ( ! $game_server_online ) ? "offline" : "online";

$game_type = ( $game_server_online ) ? "multi" : "single";

$game_holder = ( $game_server_online && $chat_server_online && ! $forceSingle ) ? "sideFlash" : "mainFlash";
$chat_holder = ( $game_server_online && $chat_server_online && ! $forceSingle ) ? "mainFlash" : "sideFlash";

$game_div = str_replace( "Flash", "Holder", $game_holder );
$chat_div = str_replace( "Flash", "Holder", $chat_holder );

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>rupsbandjenooitgenoeg</title>
	<link rel="stylesheet" href="assets/html/style.css" type="text/css"/>
	<?php
	if ( intval( $_SESSION['user_id'] ) == 0 ) { ?>
		<link rel="stylesheet" href="assets/html/login.css" type="text/css"/>
	<?php } ?>
	<link rel="shortcut icon" href="assets/html/images/favicon.ico" type="image/x-icon"/>
	<link rel="icon" href="assets/html/images/favicon.ico" type="image/x-icon"/>
	<script language="JavaScript" type="text/javascript" src="assets/html/swfobject.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/jquery-1.3.2.min.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/jquery.scrollTo-1.4.2-min.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/flash_communication.js"></script>
	<script language="JavaScript" type="text/javascript" src="assets/html/library.js"></script>
	<script language="JavaScript" type="text/javascript">
		//<![CDATA[

		var PHPSESSION = '<?= session_id() ?>';
		var loggedin = parseInt('<?= $loggedin ?>');

		var gameHolder = '<?= $game_holder ?>';
		var chatHolder = '<?= $chat_holder ?>';

		var gameDiv = '<?= $game_div ?>';
		var chatDiv = '<?= $chat_div ?>';

		var openInvite = false;
		var passwordReset = false;

		$(document).ready(function () {
			$().mousemove(function (e) {
				mouse.x = e.pageX;
				mouse.y = e.pageY;
			});

			initializeMenuFeature();

			<?php
			if($passwordReset) { ?>#
		passwordReset = true;
			loadingQueue('showProfile();');
			<?php
			} elseif(isset( $_GET['invite'] )) {
			$_SESSION['invite_code'] = $_GET['invite']; ?>
			loadingQueue('showRegister();');
			<?php
			} elseif($show_intro) { ?>
			loadingQueue('showIntro();');
			<?php
			} ?>

			updateMessages();
			updateHighscores();
			updateServerStatus();
			updateAchievements();
			<?php
			if(isset( $_SESSION['user_id'] )) { ?>
			updatePlayerInfo();
			<?php
			} ?>
		});

		//]]>
	</script>
</head>
<body>

<div id="achievement">
	<div id="close_achievement"></div>
	<div id="achievement_text"></div>
</div>

<div id="overlay" onclick="hideMessage();"></div>

<div id="message">
	<div id="text"></div>
	<div class="button"><input type="button" id="controlMessageWindow" onclick="hideMessage();" value=" sluit "/></div>
</div>

<div id="dynamicForm">
	<form onsubmit="return false;" action="">
		<div id="form"></div>
		<div class="button"><input type="submit" id="dynamicFormSubmit" value=" verstuur "/> <input type="button"
		                                                                                            id="dynamicFormCancel"
		                                                                                            value=" annuleer "/>
		</div>
	</form>
</div>

<div id="body">

	<div id="leftHolder">
		<?php
		if ( intval( $_SESSION['user_id'] ) > 0 ) { ?>

			<h2>Menu</h2>

			<div id="menu">
				<div class="menuItem disabled" id="practise" onclick="togglePractise();">&nbsp;</div>
				<div class="menuItem" onclick="showProfile();">beheer je profiel</div>
				<div class="menuItem" onclick="createConfigFlash();">instellingen</div>
				<?php
				if ( $_SESSION['user_id'] != 1 ) {
					//input type="button" name="achievement" value=" presetaties " onclick="showAchievements();" />
					?>
					<br/>
					<div class="menuItem" onclick="showAchievements();">prestaties</div>
					<br/>

					<div class="menuItem" onclick="showFAQ();">help!</div>
					<div class="menuItem" onclick="showBugs();">bekende 'bugs'</div>
					<div class="menuItem" onclick="showContactForm();">contact</div>
					<?php
				}
				else { ?>
					<br/>
					<div class="menuItem" onclick="location.href='./admin';">globaal beheer</div>
					<?php
				} ?>
				<br/>
				<div class="menuItem" onclick="logout();">uitloggen</div>
				<div id="menuTank"></div>
			</div>
			<?php
		}
		else { ?>
			<br/>
			<h2>Stap in:</h2>

			<form name="login" method="post" action="./" autocomplete="off">
				<input type="hidden" name="type" value="login"/>

				<input type="text" name="email" maxlength="128" value="voer je e-mail adres in" id="login_user"
				       onfocus="if(this.value == 'voer je e-mail adres in') this.value = '';"
				       onblur="if(this.value == '') this.value = 'voer je e-mail adres in';"/><br/>
				<div style="line-height: 5px; width: 100%; text-align: center;">&nbsp;</div>

				<input type="password" name="pass" maxlength="128" value="password" id="login_pass"
				       onfocus="if(this.value == 'password') this.value = '';"
				       onblur="if(this.value == '') this.value = 'password';"/><br/>
				<div style="line-height: 5px; width: 100%; text-align: center;">&nbsp;</div>
				<!--// <input type="checkbox" name="remember" id="remember" /> <label for="remember">Onthoud mij</label><br /> //-->
				<br/>
				<input type="submit" name="login" class="submit login" value=" log in! "
				       onclick="return submitLogin(this.form, this.name);"/>
			</form>

			<br/><br/>
			<a href="javascript:showPasswordRecover();">Wachtwoord vergeten?</a>
			<br/><br/><br/>


			Heb je nog geen tank?<br/>

			<span class="big"><a href="javascript:showRegister();">Registreer je</a> dan nu!</span>
			<?php
		}
		?>
	</div>

	<div id="mainHolder">
		<div id="mainFlash"><a href="http://www.adobe.com/go/getflashplayer" target="_blank"><img
					style="margin-top: 135px;"
					src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif"
					alt="Get Adobe Flash player" align="middle" border="0"/></a></div>
	</div>

	<div id="sideHolder">
		<div id="sideFlash"></div>
	</div>


	<?php if ( intval( $_SESSION['user_id'] ) == 0 ) { ?>
		<div id="offlineMenu">
			<h2>Informatie</h2>
			<div class="menuItem" onclick="openUrl('/nieuws');">rupsbandje nieuws</div>
			<div class="menuItem" onclick="openUrl('http://forum.rupsbandjenooitgenoeg.nl/');">rupsbandje forums</div>
			<br/>
			<div class="menuItem" onclick="showFAQ();">algemene informatie</div>
		</div>
	<?php }
	else { ?>
		<div id="leftBottom">
			<div id="personalStats">
				Bezig met laden...
			</div>
		</div>
	<?php } ?>


	<div id="menu"></div>

	<div id="highscores" ondblclick="updateHighscores();">
		<h2>Top spelers</h2>

		<div id="topscores">
			Bezig met laden...
		</div>
	</div>

	<div id="serverStats">
		<h2>Server informatie</h2>
		<div><img id="gameStatus" src="assets/html/images/<?= $game_online_status ?>.gif" width="12" height="11"
		          alt="Game Server Status"/> &nbsp;Game Server
		</div>
		<div><img id="chatStatus" src="assets/html/images/<?= $chat_online_status ?>.gif" width="12" height="11"
		          alt="Chat Server Status"/> &nbsp;Chat Server
		</div>
	</div>

	<?php
	if ( intval( $_SESSION['user_id'] ) > 0 ) { ?>
		<div id="invite" onclick="showInvite();">
			<img src="assets/html/images/invite.gif" width="142" height="36" alt="nodig vrienden uit" border="0"/>
		</div>
		<?php
	}
	else { ?>
		<div id="tested">
			Deze website is getest met:<br/>
			<img src="assets/html/images/browsers/firefox.gif" alt="firefox" title="mozilla firefox 3.6.2"/>
			<img src="assets/html/images/browsers/safari.gif" alt="safari" title="apple safari 4.0.5"/>
			<img src="assets/html/images/browsers/chrome.gif" alt="google chrome" title="google chrome 5.0 beta"/>
			<img src="assets/html/images/browsers/opera.gif" alt="opera" title="opera 10.10"/>
			<img src="assets/html/images/browsers/ie.gif" alt="ie" title="internet explorer 8.0"/>
		</div>
	<?php } ?>

	<div id="ping"></div>

</div>

<div id="popup"></div>

</body>
</html>