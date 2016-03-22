<?php

session_start();

require_once( "functions.php" );
require_once( "class.Database.php" );
require_once( "class.Statistics.php" );

$db    = &Database::getInstance();
$stats = new Statistics();

// check for login!

$user_id = intval( $_SESSION['user_id'] );

if ( $user_id == 0 ) {
	die();
}


if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	$username  = $db->prepare( htmlspecialchars( $_POST['username'] ), 64 );
	$password  = $db->prepare( htmlspecialchars( $_POST['password'] ), 255 );
	$password2 = $db->prepare( htmlspecialchars( $_POST['password2'] ), 255 );

	/* Check if alias is in use already, except for current user (for password-only changes!) */
	$result = $db->query( "SELECT `id` FROM `global__Players` WHERE UPPER(`username`)=UPPER('$username') AND `id` <> '$user_id'" );
	if ( $test = $db->assoc( $result ) ) {
		push_message( "Deze alias is al in gebruik. Kies een andere (of pas deze aan) en probeer opnieuw!" );
		die( "{'saved':false}" );
	}

	$update = array();

	$no_emails = ( $_POST['no_emails'] == "on" ) ? 1 : 0;
	array_push( $update, array( "column" => "no_emails", "value" => $no_emails ) );

	array_push( $update, array( "column" => "username", "value" => $username ) );

	if ( $password != "" ) {
		if ( $password2 == $password ) {
			array_push( $update, array( "column" => "password", "value" => md5( $password ) ) );
		}
		else {
			push_message( "Wachtwoorden komen niet overeen, je profiel kan niet worden opgeslagen!" );
			die( "{'saved':false}" );
		}
	}

	$query        = $db->query( "SELECT `username` FROM `global__Players` WHERE `id`='$user_id'" );
	$player       = $db->assoc( $query );
	$old_username = $player['username'];


	$query = "";
	foreach ( $update as $row ) {
		$query .= ( $query == "" ) ? "" : ", ";
		$query .= "`" . $row['column'] . "`='" . $row['value'] . "'";
	}


	$db->query( "UPDATE `global__Players` SET $query WHERE `id`='$user_id'" );

	$stats->add( "player_id", $_SESSION['user_id'] );
	$stats->add( "old_value", $old_username );
	$stats->add( "new_value", $username );
	$stats->save( "Username" );

	if ( $_SESSION['pass_reset'] ) {
		logout();
	}

	$_SESSION['user_name'] = stripslashes( $username );
	die( "{'saved':true,'username':'$username'}" );
}

$query = $db->query( "SELECT `username`, `no_emails` FROM `global__Players` WHERE `id`='$user_id'" );
if ( $user = $db->assoc( $query ) ) {
	$username  = $user['username'];
	$no_emails = intval( $user['no_emails'] );
}
else {
	die( "Er is een fout opgetreden.<br />Je wordt nu uitgelogt, hierna kan je opnieuw inloggen en het nogmaals proberen.<br />Excuses voor het ongemak." );
	logout();
}

// handle XML from and to Flash
// loading and saving settings

// Controls
// Sound
// Layout
// Activated Bonusses

?>
<center><h2>Wijzig je profiel</h2></center>
<br/>
<table width="100%" cellspacing="0" cellpadding="2">
	<tr>
		<td width="30%" align="right">Alias:&nbsp;</td>
		<td><input type="text" name="username" value="<?= $username ?>" maxlength="64"/></td>
	</tr>
	<tr>
		<td align="center" colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td align="right">Nieuw wachtwoord:&nbsp;</td>
		<td><input type="password" name="password" value="" maxlength="255" autocomplete="no" size="30"/></td>
	</tr>
	<tr>
		<td align="right">Herhaal wachtwoord:&nbsp;</td>
		<td><input type="password" name="password2" value="" maxlength="255" autocomplete="no" size="30"/></td>
	</tr>
	<tr>
		<td align="center" colspan="2">&nbsp;</td>
	</tr>
	<tr>
		<td align="right"><input type="checkbox" name="no_emails"
		                         id="no_emails" <?= ( ( $no_emails == 1 ) ? " checked" : "" ) ?>/></td>
		<td><label for="no_emails">Ik wil <b>geen</b> e-mails van rupsbandjenooitgenoeg.nl meer ontvangen.</label></td>
	</tr>
</table>
