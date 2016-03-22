<?php

session_start();

require_once("functions.php");
require_once("class.Database.php");
require_once("class.Statistics.php");

require_once('Mail/RFC822.php');

$db = &Database::getInstance();
$stats = new Statistics();

// check for login!

if($_SERVER['REQUEST_METHOD'] == "POST") {
	$email = $_POST['email'];
	$username = $db->prepare(htmlspecialchars($_POST['username']), 64);
	
	$password = $_POST['password'];
	$password2 = $_POST['password2'];
	
	$no_emails = ($_POST['no_emails'] == "on")?1:0;
	
	if(empty($username) || $username == '') {
		push_message('<h2>Registratiefout!</h2><br />Je bent vergeten een alias op te geven.<br />Onder deze naam zal jij bekend worden bij de andere spelers, kies dus een naam die goed bij je past!');
		die("{'saved':false}");
	}
	
	if(!is_valid_email($email)) {
		push_message('<h2>Registratiefout!</h2><br />Het ingevoerde e-mail adres is ongeldig.<br /><br />Gebruik een geldig e-mail adres om te registreren<br />(hierop ontvang je een verificatie code om het account te kunnen gebruiken).');
		die("{'saved':false}");
	}
	
	$hotmail_domains = array("hotmail.com", "live.nl", "live.be", "live.com");
	
	$addresses = Mail_RFC822::parseAddressList($email, 'rupsbandjenooitgenoeg.nl', TRUE);
	if(in_array($addresses[0]->host, $hotmail_domains)) {
		push_message('<h2>Microsoft: Hotmail & Live</h2><br />Het ingevoerde e-mail adres is een hotmail of live adres.<br />Op het moment hebben wij problemen om naar deze adressen emails te versturen.<br />Zolang dit problemen geeft accepteren wij geen adressen van deze domeinen.<br />Wij zijn hier hard mee bezig en hopen dit snel te hebben opgelost.<br />Je kan je wel via een ander adres, bijvoorbeeld gmail, registreren.');
		die("{'saved':false}");
	}
	
	
	$result = $db->query("SELECT id FROM `global__Players` WHERE `email`='$email'");
	if($user = $db->assoc($result)) {
		// email taken.
		push_message("<h2>Registratiefout!</h2><br />Het door jou ingevoerde e-mail adres is al in gebruik!<br />Als dit een e-mail adres is wat je zelf bezit, controleer dan je Spam/Junk mappen.");
		die("{'saved':false}");
	}
	
	/* Check if alias is in use already, except for current user (for password-only changes!) */
	$result = $db->query("SELECT `id` FROM `global__Players` WHERE UPPER(`username`) = UPPER('$username')");
	if($test = $db->assoc($result)) {
		push_message("<h2>Registratiefout!</h2><br />Deze alias is al in gebruik. Kies een andere (of pas deze aan) en probeer opnieuw!");
		die("{'saved':false}");
	}
	
	if(empty($password) || $password == '') {
		push_message("<h2>Registratiefout!</h2><br />Je moet een wachtwoord opgeven!");
		die("{'saved':false}");
	}
	
	if($password2 != $password) {
		push_message("<h2>Registratiefout!</h2><br />De opgegeven wachtwoorden komen niet overeen, typ twee keer uw wachtwoord zodat u zeker weet dat deze klopt!");
		die("{'saved':false}");
	}
	
	$password = md5($password);
	$password2 = md5($password2);
	
	$verify = time() . $email . $pass;
	$verify = md5($verify);
	
	$time = time();
	$db->query("INSERT INTO `global__Players` (`email`, `password`, `username`, `verify`, `invite_code`, `no_emails`, `timestamp`) VALUES ('$email', '$password', '$username', '$verify', MD5('$time'), '$no_emails', UNIX_TIMESTAMP())");
	
	$player_id = mysql_insert_id();
	$db->query("INSERT INTO `global__PlayerInfo` (`player_id`) VALUES ('$player_id')");
	
	/** Check for Invite code **/
	if(isset($_SESSION['invite_code'])) {
		$query = $db->query("SELECT `id` FROM `global__Players` WHERE `invite_code`='$invite_code'");
		if($player = $db->assoc($query)) {
			$invited_by = $player['id'];
			
			$db->query("UPDATE `global__Players` SET `invited_by`='$invited_by' WHERE `id`='$player_id'");
		}
	}
	
	/** Add stats to the database **/
	$stats->add("player_id", $player_id);
	$stats->add("type", "REGISTER");
	$stats->save("Registration");
	
	/** Send verification email **/
	$verify_email = file_get_contents("../html/templates/verify_email.html");
	$verify_email = str_replace("#EMAIL#", $email, $verify_email);
	$verify_email = str_replace("#VERIFY#", $verify, $verify_email);
	
	email($email, "Bevestig je emailadres", $verify_email);
	
	push_message(file_get_contents("../html/templates/registration_send.html"));
	
	$_SESSION['registered'] = true;
	die("{'saved':true}");
}
// handle XML from and to Flash
// loading and saving settings

// Controls
// Sound
// Layout
// Activated Bonusses

?>
<center><h2>Registreer je nu!</h2></center>
<br />
<table width="100%" cellspacing="0" cellpadding="1">
<tr>
	<td width="35%" align="right">Alias:&nbsp;</td>
	<td><input type="text" name="username" value="" maxlength="64" /></td>
</tr><tr>
	<td colspan="2" style="font-size: 4px; line-height: 5px;">&nbsp;</td>
</tr><tr>
	<td align="right">E-mail adres:&nbsp;</td>
	<td><input type="text" name="email" value="" maxlength="255" autocomplete="no" size="30" /></td>
</tr><tr>
	<td align="right">Wachtwoord:&nbsp;</td>
	<td><input type="password" name="password" value="" maxlength="255" autocomplete="no" /></td>
</tr><tr>
	<td align="right">Herhaal wachtwoord:&nbsp;</td>
	<td><input type="password" name="password2" value="" maxlength="255" autocomplete="no" /></td>
</tr><tr>
	<td align="right"><input type="checkbox" name="no_emails" id="no_emails" <?= (($no_emails == 1)?" checked":"") ?>/></td>
	<td><label for="no_emails">Ik wil <b>geen</b> periodieke e-mails van rupsbandjenooitgenoeg.nl ontvangen.</label></td>
</tr>
</table>
<br>

<center>
<small>Je e-mail adres wordt alleen gebruikt om te verifiëren dat je een menselijke speler bent.<br>
Wij zullen deze nooit gebruiken voor niet-'Rupsbandjenooitgenoeg'-gerelateerde berichten.<br>
<!-- Tevens verstrekken wij deze adressen nooit aan derden. -->
</small>
</center>