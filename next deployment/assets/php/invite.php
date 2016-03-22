<?php
/**
 * Invite.php
 *
 * Shows a form to enter 5 email adresses
 *
 * On submit, send an email to each of them inviting them in the name of the current user
 */

require_once( "functions.php" );
require_once( "class.Database.php" );
require_once( "class.Statistics.php" );

$db    = &Database::getInstance();
$stats = new Statistics();

session_start();

$user_id = intval( $_SESSION['user_id'] );


if ( $user_id > 0 ) {
	$query  = $db->query( "SELECT `invite_code` FROM `global__Players` WHERE `id`=$user_id" );
	$player = $db->assoc( $query );

	$invite_code = $player['invite_code'];

	$username = $_SESSION['user_name'];
}
else {
	die();
}


if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	if ( $user_id == 0 ) {
		die( "{'saved':false}" );
	}

	$name  = $db->prepare( htmlspecialchars( $_POST['naam'] ), 64 );
	$email = $db->prepare( htmlspecialchars( $_POST['email'] ), 64 );

	if ( empty( $name ) || $name == '' ) {
		push_message( '<h2>Uitnodigen mislukt!</h2><br />Je bent vergeten een naam op te geven.<br />Wij verzoeken je om een naam op te geven zodat de uitgenodigde weet dat het geen spam is!' );
		die( "{'saved':false}" );
	}

	if ( ! is_valid_email( $email ) ) {
		push_message( '<h2>Uitnodigen mislukt!</h2><br />Het ingevoerde e-mail adres is ongeldig.<br /><br />Controlleer het adres en probeer opnieuw!' );
		die( "{'saved':false}" );
	}

	$result = $db->query( "SELECT id FROM `global__Players` WHERE `email`='$email'" );
	if ( $user = $db->assoc( $result ) ) {
		// email taken.
		push_message( "<h2>Uitnodigen mislukt!</h2><br />Het door jou ingevoerde e-mail adres is al geregistreerd!" );
		die( "{'saved':false}" );
	}

	$stats->add( "player_id", $user_id );
	$stats->add( "type", "INVITE" );
	$stats->save( "Registration" );

	$invite_friend = file_get_contents( "../html/templates/invite_friend.html" );
	$invite_friend = str_replace( "#EMAIL#", $email, $invite_friend );
	$invite_friend = str_replace( "#NAME#", $name, $invite_friend );
	$invite_friend = str_replace( "#BY#", $username, $invite_friend );
	$invite_friend = str_replace( "#INVITE_CODE#", $invite_code, $invite_friend );

	push_message( "<h2>Uitnodigen geslaagd!</h2><br />$name ($email) ontvangt binnen een korte tijd een uitnodiging om op rupsbandjenooitgenoeg.nl te registreren!" );

	email( $email, "Uitnodiging voor rupsbandjenooitgenoeg.nl!", $invite_friend, false );

	die( "{'saved':true}" );
}

?>

<center>
	<h2>Nodig je vrienden uit!</h2>


	Vul hier de gegevens van een van je vrienden in om hem of haar uit te nodigen!<br>
	Deze persoon krijgt een persoonlijke uitnodiging met een speciale vriend-code.<br>

	<br>

	<table width="100%">
		<tr>
			<td align="right">Naam:</td>
			<td><input type="text" name="naam" maxlength="64"/></td>
		</tr>
		<tr>
			<td align="right">Emailadres:</td>
			<td><input type="text" name="email" maxlength="64"/></td>
		</tr>
	</table>
	<br>

	Je kan ook persoonlijk iemand uitnodigen om zich te registreren met deze code:<br>
	<input type="text" value="http://www.rupsbandjenooitgenoeg.nl/?invite=<?= $invite_code ?>" size="50"
	       onfocus="this.select();" onclick="this.select();"
	       onchange="this.value='http://www.rupsbandjenooitgenoeg.nl/?invite=<?= $invite_code ?>';"
	       onkeyup="this.value='http://www.rupsbandjenooitgenoeg.nl/?invite=<?= $invite_code ?>';"/>
	<br>
	<br>


	Nodig vrienden uit en verdien prestaties en<br/> misschien wel meer spannende extra's!