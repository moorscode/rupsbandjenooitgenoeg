<?php

session_start();

if ( intval( $_SESSION['user_id'] ) != 1 ) {
	header( "Location: ./login.php" );
	die();
}

require_once( "../assets/php/class.Database.php" );
require_once( "../assets/php/functions.php" );

$db = &Database::getInstance();

include( "../assets/php/status.Servers.php" );


/* Set locale to dutch */
setlocale( LC_ALL, 'nl_NL' );

if ( isset( $_GET['clear'] ) ) {
	switch ( $_GET['clear'] ) {
		case 'server':
			$db->query( "TRUNCATE TABLE `global__ServerErrors`" );
			break;
		case 'db':
			$db->query( "TRUNCATE TABLE `global__DatabaseErrors`" );
			break;
	}

	header( "Location: ./" );
	die();
}

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {

	switch ( $_POST['action'] ) {

		case "access":

			$access = ( $_POST['access'] == "1" ) ? 1 : 0;
			$db->query( "UPDATE `global__Configuration` SET `value`='$access' WHERE `item`='access'" );

			header( "Location: ./" );

			return;

			break;

		case "mail":

			$subject = $_POST['subject'];
			$body    = $_POST['body'];

			if ( empty( $subject ) || empty( $body ) ) {
				header( "Location: ./" );
				die();
			}

			$bcc = array();

			$query = $db->query( "SELECT `username`, `email` FROM `global__Players` WHERE `no_emails`=0" );
			while ( $player = $db->assoc( $query ) ) {

				array_push( $bcc, $player['username'] . " <" . $player['email'] . ">" );
			}

			if ( count( $bcc ) > 0 ) {
				$bcc                   = implode( ",", $bcc );
				$body                  = nl2br( $body );
				$_SESSION['mail_send'] = email( "contact@rupsbandjenooitgenoeg.nl", $subject, $body, true, $bcc, '../assets/html/templates/' );
			}

			break;

		case "message":

			$message = $db->prepare( $_POST['message'] );

			$query = $db->query( "SELECT `id` FROM `global__Players` WHERE `verify`=''" );
			while ( $player = $db->assoc( $query ) ) {
				$now = time();
				$db->query( "INSERT INTO `global__Messages` (player_id, message, timestamp) VALUES ('" . $player['id'] . "', '" . $message . "', '$now')" );
			}

			break;
	}
}


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
		//chdir("/home/rupsbandje/web/");
		chdir( "../" );
		exec( "screen -d -m -S rupsChatServer php ./assets/php/server.chat.php &" );
	}

	header( "Location: ./" );
	die();
}

if ( $what == "game" ) {
	if ( isset( $game_id ) ) {
		kill_server( "game" );
	}

	if ( $do == "start" ) {
		//chdir("/home/rupsbandje/web/");
		chdir( "../" );
		exec( "screen -d -m -S rupsGameServer php ./assets/php/server.game.php &" );
	}

	header( "Location: ./" );
	die();
}


$access_query = $db->query( "SELECT `value` FROM `global__Configuration` WHERE `item`='access'" );
if ( $data = $db->assoc( $access_query ) ) {
	$access = ( $data['value'] == 1 ) ? ' checked="checked"' : '';
}


/* Get the user input from the database */
$user_input = array();
$ui_query = $db->query( "SELECT userinput.`id` AS `id`, `player_id`, players.`username` AS `username`, `phrase`, `text`, `looked_at`, userinput.`timestamp` FROM `dev__UserInput` AS userinput INNER JOIN `global__Players` AS players ON players.id=userinput.player_id WHERE `looked_at`=0 ORDER BY `looked_at` DESC, `phrase`, `timestamp` ASC" );
while ( $ui = $db->assoc( $ui_query ) ) {
	if ( ! is_array( $user_input[ $ui['phrase'] ] ) ) {
		$user_input[ $ui['phrase'] ] = array();
	}

	$ui['timestamp'] = strftime( "%d %b %Y om %H:%M", $ui['timestamp'] );

	array_push( $user_input[ $ui['phrase'] ], array( 'id'        => $ui['id'],
	                                                 'pid'       => $ui['player_id'],
	                                                 'username'  => $ui['username'],
	                                                 'text'      => $ui['text'],
	                                                 'timestamp' => $ui['timestamp']
	) );
}
$db->free( $ui_query );


/* Get the bugs from the database */
$bugs = array();
$bug_query = $db->query( "SELECT `id`, `type`, `description`, `timestamp` FROM `dev__KnownBugs` ORDER BY `type`, `timestamp` DESC" );
while ( $bug = $db->assoc( $bug_query ) ) {

	$bug_index = -1 + array_push( $bugs, $bug );

	// get all status chances per bug
	// set latest status as current
	$status_history = array();
	$status_query   = $db->query( "SELECT `status`, `description`, `timestamp` FROM `dev__BugStatus` WHERE `bug_id`='{$bug['id']}' ORDER BY `timestamp` DESC" );
	while ( $status = $db->assoc( $status_query ) ) {
		array_push( $status_history, $status );
	}
	$db->free( $status_query );

	$bugs[ $bug_index ]['status_history'] = $status_history;
	if ( count( $status_history ) > 0 ) {
		$bugs[ $bug_index ]['status'] = $status_history[0]['status'];
	}
}
$db->free( $bug_query );



/* Get the FAQ list from the database */
$faqs = array();
$faq_query = $db->query( "SELECT `id`, `title`, `description` FROM `global__Faqs` ORDER BY `title` ASC" );
while ( $faq = $db->assoc( $faq_query ) ) {
	$faq['description'] = ( strlen( $faq['description'] ) > 100 ) ? substr( $faq['description'], 0, 120 ) . "..." : $faq['description'];
	array_push( $faqs, $faq );
}
$db->free( $faq_query );

/* Get Server-Errors from the database (only applies when the error files can't be written) */
$errors = array();
$error_query = $db->query( "SELECT `id`, `message` FROM `global__ServerErrors` ORDER BY `timestamp` DESC" );
while ( $error = $db->assoc( $error_query ) ) {
	array_push( $errors, $error );
}
$db->free( $error_query );

/* Get Database-Errors from the database (only applies when the error files can't be written) */
$db_errors = array();
$error_query = $db->query( "SELECT `id`, `sql`, `error` FROM `global__DatabaseErrors` ORDER BY `timestamp` DESC" );
while ( $error = $db->assoc( $error_query ) ) {
	array_push( $db_errors, $error );
}
$db->free( $error_query );

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>Rupsbandjenooitgenoeg: Advanced Control Panel</title>
	<link rel="stylesheet" href="../assets/html/style.css" type="text/css"/>
	<link rel="stylesheet" href="style.css" type="text/css"/>
	<link rel="shortcut icon" href="../assets/html/images/favicon.ico" type="image/x-icon">
	<link rel="icon" href="../assets/html/images/favicon.ico" type="image/x-icon">
	<script language="JavaScript" type="text/javascript" src="../assets/html/jquery-1.3.2.min.js"></script>
	<script language="JavaScript" type="text/javascript" src="../assets/html/jquery.scrollTo-1.4.2-min.js"></script>
	<script language="JavaScript" type="text/javascript" src="../assets/html/library.js"></script>
	<script language="JavaScript" type="text/javascript">
		//<![CDATA[

		$(document).ready(function () {
			$("#adminBody").find("div.tab").click(function () {
				$("#adminBody").find("div.tab").removeClass("active");
				$("#adminBody").find("div.tabdata").removeClass("active");

				$(this).addClass("active");
				$("#" + $(this).attr('id') + "tab").addClass("active");
			});
		});

		/*
		 * Show the profile popup.
		 & 	Not disabling the messages because of form errors.
		 */
		function showBug(action, id) {
			loadForm("manage.Bugs.php", {action: action, id: id}, function (data) {
				if (data.saved) history.go(0);
				return data.saved;
			});
		}

		function deleteBug(id) {
			if (!window.confirm('Are you sure you want to delete this bug?')) return;

			data = {action: 'del', id: id};

			$.ajax({
				url: "manage.Bugs.php",
				data: data,
				type: "POST",
				cache: false,
				success: function (text) {
					history.go(0);
				}
			});
		}

		function showFaqs(action, id) {
			loadForm("manage.Faqs.php", {action: action, id: id}, function (data) {
				if (data.saved) history.go(0);
				return data.saved;
			});
		}

		function deleteFaq(id) {
			if (!window.confirm('Are you sure you want to delete this FAQ?')) return;

			data = {action: 'del', id: id};

			$.ajax({
				url: "manage.Faqs.php",
				data: data,
				type: "GET",
				cache: false,
				success: function (text) {
					history.go(0);
				}
			});
		}

		function removeUser(id) {
			if (!window.confirm('Are you sure you want to remove this user?')) return;

			$.ajax({
				url: "manage.Users.php",
				data: {pid: id},
				type: "GET",
				cache: false,
				success: function (text) {
					updateUserList($('#find').val());
				}
			});
		}

		function sendMessage(pid, replyto) {
			var reply = replyto;

			loadForm("send.Message.php", {pid: pid, reply: reply}, function (data) {
				if (data.reply) {
					$("#seen" + data.reply).attr("checked", "checked");
					$("#seen" + data.reply).attr("disabled", "disabled");
				}
				return data.send;
			});
		}

		function findUser(name) {
			$('#find').val(name);
			updateUserList(name);
		}

		function markAsSeen(checkbox, id) {
			checkbox.disabled = 'disabled';

			$.ajax({
				url: "manage.UserInput.php",
				data: {id: id},
				type: "GET",
				cache: false,
				success: function (text) {
					// history.go(0);
				}
			});
		}

		function updateUserList(searchInput) {
			$.ajax({
				url: "manage.Users.php",
				data: {text: searchInput},
				type: "GET",
				cache: false,
				success: function (text) {
					$('#userlist').html(text);
				}
			});
		}

		function clearServerErrors() {
			location.href = '?clear=server';
		}

		function clearDBErrors() {
			location.href = '?clear=db';
		}

		//]]>
	</script>
</head>

<body>

<div id="overlay" onClick="hideMessage();"></div>

<div id="dynamicForm">
	<form onsubmit="return false;">
		<div id="form"></div>
		<div class="button"><input type="submit" id="dynamicFormSubmit" value=" opslaan "/> <input type="button"
		                                                                                           id="dynamicFormCancel"
		                                                                                           value=" annuleer "/>
		</div>
	</form>
</div>

<div id="adminBody">

	<h1>Advanced Control Panel</h1>

	<fieldset>
		<legend>Site access</legend>
		<form action="<?php __FILE__ ?>" method="POST">
			<input type="hidden" name="action" value="access"/>
			<table width="580">
				<tr>
					<td><input type="checkbox" value="1" name="access" id="access"<?php echo $access ?>/><label
							for="access">access granted</label></td>
					<td align="right"><input type="submit" value=" apply "/></td>
				</tr>
			</table>
		</form>
	</fieldset>

	<fieldset>
		<legend>Server beheer</legend>

		<table width="580">
			<tr>
				<td width="16"><img id="chatStatus"
				                    src="../assets/html/images/<?= ( isset( $chat_id ) ? "online" : "offline" ) ?>.gif"
				                    width="12" height="11"/></td>
				<td>Chat Server</td>
				<td align="right"><input type="button" name="chatControl"
				                         value=" <?= ( isset( $chat_id ) ? "kill" : "start" ) ?> "
				                         onclick="location.href='./?do=<?= ( isset( $chat_id ) ? "kill" : "start" ) ?>&what=chat';"/>
				</td>
			</tr>
			<tr>
				<td width="16"><img id="chatStatus"
				                    src="../assets/html/images/<?= ( isset( $game_id ) ? "online" : "offline" ) ?>.gif"
				                    width="12" height="11"/></td>
				<td>Game Server</td>
				<td align="right"><input type="button" name="chatControl"
				                         value=" <?= ( isset( $game_id ) ? "kill" : "start" ) ?> "
				                         onclick="location.href='./?do=<?= ( isset( $game_id ) ? "kill" : "start" ) ?>&what=game';"/>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
		<legend>Mass-mail</legend>

		<form name="massmail" method="POST" action="./">
			<input type="hidden" name="action" value="mail"/>
			<table width="580" cellpadding="5">
				<?php
				if ( isset( $_SESSION['mail_send'] ) ) {
					echo "\t<tr>\n\t\t<td></td>\n\t\t<td>" . ( $_SESSION['mail_send'] ) ? "Mail send." : "Mail sending failed." . "</td>\n\t</tr>\n";
					unset( $_SESSION['mail_send'] );
				}
				?>
				<tr>
					<td width="100" align="right">Titel:</td>
					<td><input type="text" name="subject" autocomplete="no" size="70" style="width: 460px;"/></td>
				</tr>
				<tr>
					<td valign="top" align="right">Mail:</td>
					<td><textarea rows="10" cols="65" name="body" style="width: 460px;"></textarea></td>
				</tr>
				<tr>
					<td></td>
					<td><input type="submit" value=" verstuur "/></td>
				</tr>
			</table>
		</form>
	</fieldset>

	<fieldset>
		<legend>Mass-message</legend>

		<form name="massmail" method="POST" action="./">
			<input type="hidden" name="action" value="message"/>
			<table width="580" cellpadding="5">
				<tr>
					<td valign="top" width="100" align="right">Message:</td>
					<td><textarea rows="10" cols="20" name="message" style="width: 460px;"></textarea></td>
				</tr>
				<tr>
					<td></td>
					<td><input type="submit" value=" verstuur "/></td>
				</tr>
			</table>
		</form>

	</fieldset>


	<h2>User input</h2>

	<?php

	$tabs      = "";
	$tabs_data = "";
	foreach ( $user_input as $type => $data ) {
		$type_stripped = strtolower( substr( $type, 1 ) );

		$active = ( $tabs == "" ) ? " active" : "";
		$count  = count( $data );

		$tabs .= "<div id=\"$type_stripped\" class=\"tab{$active}\">$type <small>($count)</small></div>";
		$tabs_data .= "<div id=\"{$type_stripped}tab\" class=\"tabdata{$active}\">";

		foreach ( $data as $row ) {
			$tabs_data .= "<div class=\"uidata\">{$row['text']}<br /><small>{$row['timestamp']} door </small><span class=\"click\" onclick=\"findUser('{$row['username']}');\">{$row['username']}</span></div>";
			$tabs_data .= "<div class=\"uicontrol\"><a href=\"javascript:sendMessage({$row['pid']}, {$row['id']});\" title=\"reply to the report\">reply</a><br /><input type=\"checkbox\" id=\"seen{$row['id']}\" name=\"seen{$row['id']}\" autocomplete=\"no\" onchange=\"markAsSeen(this, {$row['id']});\" title=\"Mark read\"/></div>";
			$tabs_data .= "<div class=\"row\"></div>";
		}

		$tabs_data .= "</div>";
	}

	echo $tabs;
	echo $tabs_data;

	?>

	<h2>User management</h2>

	Find user: <input type="text" name="find" id="find" onchange="updateUserList(this.value);"
	                  onkeyup="updateUserList(this.value);" size="40" autocomplete="no" value=""/><br/>

	<div id="userlist">
	</div>

	<br/>

	<h2>Bug management</h2>
	<a href="javascript:showBug('add');">Add bug</a><br/>
	<br/>

	<table width="580" bgcolor="#efefef" cellpadding="5" cellspacing="0" style="border: 1px dotted black;">
		<?php

		foreach ( $bugs as $bug ) {
//	$latest = strftime("%A %d %b %Y", $bug['last_changed']);
			$status = $readable_bug_status[ $bug['status'] ];

			echo <<<EOBUGS
	<tr>
		<td><span class="status{$bug['status']}">$status:</span> {$bug['description']}</td>
		<td width="16"><a href="javascript:showBug('add_status', {$bug['id']});"><img border="0" src="../assets/html/images/new_status.gif" alt="change status" title="change status" /></a></td>
		<td width="16"><a href="javascript:showBug('edit', {$bug['id']});"><img border="0" src="../assets/html/images/edit.gif" alt="edit" title="edit" /></a></td>
		<td width="16"><a href="javascript:deleteBug({$bug['id']});"><img border="0" src="../assets/html/images/delete.gif" alt="delete" title="delete" /></a></td>
	</tr>
EOBUGS;
		}
		?>

	</table>

	<br/>

	<h2>F.A.Q. management</h2>

	<a href="javascript:showFaqs('add');">Add F.A.Q.</a><br/>
	<br/>

	<table width="580" bgcolor="#efefef" cellpadding="5" cellspacing="0"
	       style="border: 1px dotted black; border-bottom: 0px;">
		<?php

		foreach ( $faqs as $faq ) {

			echo <<<EOFAQS
	<tr>
		<td style="border-bottom: 1px dotted black;">{$faq['title']}</td>
		<td style="border-bottom: 1px dotted black;" width="300">{$faq['description']}</td>
		<td style="border-bottom: 1px dotted black;" width="16"><a href="javascript:showFaqs('edit', {$faq['id']});"><img border="0" src="../assets/html/images/edit.gif" alt="edit" title="edit" /></a></td>
		<td style="border-bottom: 1px dotted black;" width="16"><a href="javascript:deleteFaq({$faq['id']});"><img border="0" src="../assets/html/images/delete.gif" alt="delete" title="remove" /></a></td>
	</tr>
EOFAQS;
		}
		?>


	</table>

	<br/>

	<h2>Server Errors</h2>

	<div id="errors">
		<table width="100%" cellpadding="5" cellspacing="0">
			<?php

			foreach ( $errors as $error ) {
				echo <<<EOERRORS
	<tr>
		<td style="border-bottom: 1px dotted black;">{$error['message']}</td>
		<!--// <td style="border-bottom: 1px dotted black;" width="16"><a href="javascript:deleteError({$error['id']});"><img border="0" src="../assets/html/images/delete.gif" alt="delete" title="delete" /></a></td> //-->
	</tr>
EOERRORS;
			}
			?>    </table>
	</div>
	<br/>

	<input type="button" value=" clear " onclick="clearServerErrors();">
	<br/>
	<br/>

	<h2>Database Errors</h2>

	<div id="errors">
		<table width="100%" cellpadding="5" cellspacing="0">
			<?php

			foreach ( $db_errors as $error ) {
				echo <<<EOERRORS
	<tr>
		<td style="border-bottom: 1px dotted black;">{$error['sql']}<br />{$error['error']}</td>
	</tr>
EOERRORS;
			}
			?>    </table>
	</div>
	<br/>
	<input type="button" value=" clear " onclick="clearDBErrors();">
	<br/>

</div>


</body>
</html>