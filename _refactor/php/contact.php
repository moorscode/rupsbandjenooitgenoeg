<?php

// contact form;

session_start();

$user_id = intval( $_SESSION['user_id'] );
if ( $user_id == 0 ) {
	die();
}


require_once( "functions.php" );
require_once( "class.Database.php" );

$db = &Database::getInstance();

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {

	$name  = $db->prepare( htmlspecialchars( $_POST['name'] ), 255 );
	$email = $db->prepare( htmlspecialchars( $_POST['email'] ), 255 );
	$body  = $_POST['body'];

	if ( empty( $name ) || $name == '' ) {
		$name = "Anoniem";
	}

	if ( ! is_valid_email( $email ) ) {
		push_message( '<h2>Invoer fout!</h2><br />Het ingevoerde e-mail adres is ongeldig.<br /><br />Gebruik een geldig e-mail adres zodat wij je hierop terug kunnen mailen.' );
		die( "{'saved':false}" );
	}

	if ( empty( $body ) ) {
		push_message( '<h2>Invoer fout!</h2><br />Het is onmogelijk voor ons om een vraag te beantwoorden zonder dat hij gestelt is!<br />Voer een vraag of opmerking in en probeer opnieuw.' );
		die( "{'saved':false}" );
	}

	setlocale( LC_TIME, "nl_NL" );

	$contact_email = file_get_contents( "../html/templates/contact.html" );
	$contact_email = str_replace( "#EMAIL#", $email, $contact_email );
	$contact_email = str_replace( "#NAME#", $name, $contact_email );
	$contact_email = str_replace( "#DATE#", strftime( "%A %e %B %Y om %H:%M" ), $contact_email );
	$contact_email = str_replace( "#BODY#", $body, $contact_email );

	if ( email( "contact@rupsbandjenooitgenoeg.nl", "Vraag/Opmerking", $contact_email, false ) ) {
		push_message( '<h2>Bedankt!</h2><br />Bedankt voor je vraag of opmerking.<br />We zullen zo spoedig mogelijk proberen hier een goed antwoord op te geven.' );
	}

	/*
	$headers = "From: Rupsbandjenooitgenoeg.nl <no-reply@rupsbandjenooitgenoeg.nl>\r\n"; //optional headerfields
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	$headers .= "Reply-to: $name <$email>\r\n";
	
	if(mail("contact@rupsbandjenooitgenoeg.nl", "Vraag/Opmerking", $contact_email, $headers)) {
		push_message('<h2>Bedankt!</h2><br />Bedankt voor je vraag of opmerking.<br />We zullen zo spoedig mogelijk proberen hier een goed antwoord op te geven.');
	}
	*/

	die( "{saved:true}" );
}

$query  = $db->query( "SELECT `email` FROM `global__Players` WHERE `id`='$user_id'" );
$player = $db->assoc( $query );

$email = $player['email'];

?>

<center><h2>Neem contact op met rupsbandje</h2></center>
<br/>
<table width="100%" cellspacing="0" cellpadding="2">
	<tr>
		<td width="35%" align="right">Naam:&nbsp;</td>
		<td><input type="text" name="name" value="<?= $_SESSION['user_name'] ?>" maxlength="64" size="30"/></td>
	</tr>
	<tr>
		<td align="right">E-mail adres:&nbsp;</td>
		<td><input type="email" name="email" value="<?= $email ?>" maxlength="255" autocomplete="no" size="30"/></td>
	</tr>
	<tr>
		<td align="right">Vraag en/of opmerking:&nbsp;</td>
		<td><textarea rows="7" cols="35" name="body"></textarea></td>
	</tr>
</table>