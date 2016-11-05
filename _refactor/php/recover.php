<?php

// contact form;

session_start();

require_once( "functions.php" );
require_once( "class.Database.php" );

$db = &Database::getInstance();

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {

	$email = $db->prepare( htmlspecialchars( $_POST['email'] ), 255 );

	if ( ! is_valid_email( $email ) ) {
		push_message( '<h2>Mislukt!</h2><br />Het opgegeven e-mailadres is ongeldig.<br />Pas deze aan en probeer opnieuw.' );
		die( "{saved:false}" );
	}

	$query = $db->query( "SELECT * FROM `global__Players` WHERE `email`='$email'" );
	if ( $player = $db->assoc( $query ) ) {

		$recover_code = md5( $player['invite_code'] . $player['email'] );
		$name         = $player['name'];

		setlocale( LC_TIME, "nl_NL" );

		$recover_email = file_get_contents( "../html/templates/recover.html" );
		$recover_email = str_replace( "#EMAIL#", $email, $recover_email );
		$recover_email = str_replace( "#NAME#", $name, $recover_email );
		$recover_email = str_replace( "#DATE#", strftime( "%A %e %B %Y om %H:%M" ), $recover_email );
		$recover_email = str_replace( "#RECOVER#", $recover_code, $recover_email );


		if ( email( $email, "Wachtwoord reset", $recover_email, false ) ) {
			push_message( '<h2>Verzonden!</h2><br />Er is een e-mail naar je toe gestuurd met een link om je wachtwoord te resetten.' );
		}
		/*
		$headers = "From: Rupsbandjenooitgenoeg.nl <no-reply@rupsbandjenooitgenoeg.nl>\r\n"; //optional headerfields
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
		
		if(mail($email, "Wachtwoord reset", $recover_email, $headers)) {
			push_message('<h2>Verzonden!</h2><br />Er is een e-mail naar je toe gestuurd met een link om je wachtwoord te resetten.');
		}
		*/

		die( "{saved:true}" );
	}
	else {
		push_message( '<h2>Mislukt!</h2><br />Het opgegeven e-mailadres is niet bekend in het systeem.<br />Je kan dit e-mailadres gebruiken om je mee te registreren.' );
		die( "{saved:false}" );
	}
}

?>

<center><h2>Wachtwoord herstel</h2>
	<p align="center">
		Vul hier je e-mailadres in waarmee je je geregistreerd hebt.<br/>
		<br/>
		Op dit adres zal je een e-mail ontvangen<br/>met een link om je wachtwoord te resetten.<br/>
	</p>
	<table width="100%" cellspacing="0" cellpadding="2">
		<tr>
			<td align="right">E-mail adres:&nbsp;</td>
			<td><input type="email" name="email" value="<?= $email ?>" maxlength="255" autocomplete="no" size="30"/>
			</td>
		</tr>
	</table>