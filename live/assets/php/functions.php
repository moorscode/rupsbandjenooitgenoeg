<?php

/*
require_once('Mail.php');
require_once('Mail/mime.php');
*/
/*
 * Dump for assorted functions
 *	nov 7th, 2009 @ 23:00
*/


/**
  * @var Readable Bug Status - Array to be used to get globalized text for Bug Status
  */
$readable_bug_status = array();
$readable_bug_status[0] = "Vastgesteld";
$readable_bug_status[1] = "Geanalyseerd";
$readable_bug_status[2] = "Test fase";
$readable_bug_status[3] = "Opgelost";

if(function_exists(date_default_timezone_set)) {
	date_default_timezone_set("Europe/Amsterdam");
}

/**
  * Clean the data from _GET and _POST input
  */
function clean_input_data() {
	__url_decode(&$_GET);
	__url_decode(&$_POST);
}

/**
  * URLDecode data from a value until nothing is changed anymore
  */
function __url_decode($value) {
	//urldecode
	if(is_array($value)) {
		foreach($value as $key=>$subvalue) {
			__url_decode(&$value[$key]); // loop until all items have been compromised
		}
	} else {
		while($value != urldecode($value)) {
			$value = urldecode($value);
		}
		$value = htmlspecialchars($value);
	}
}

// get a database-safe value from some input array ($_POST, $_GET)
/**
  * Implements the Database Prepare function to ease use
  *
  * @param String   $key          Index Key of the specified Array
  * @param array    $array        Input array (_GET, _POST or custom)
  * @param int      $max_length   Concat the length to the maximum length (Default: 0)
  */
function get($key, $array, $max_length = -1) {
	$db = &Database::getInstance();
	
	$value = $array[$key];
	$value = $db->prepare($value, $max_length);
	
	return $value;
}

/**
  * Login function
  *
  * @param  String  $email           Emailaddress to login with
  * @param  String  $pass            Password to go with the emailaddress
  * @param  boolean  $from_cookie    Login manually or done by saved cookie
  */
function login($email, $pass, $from_cookie = false) {
	$db = &Database::getInstance();
	$dbPrefix = $db->prefix;
	
	$stats = new Statistics();
	
	$stats->add("login_type", (($from_cookie)?"AUTOMATIC":"MANUAL"));
	$email = strtolower($email);
	
	$result = $db->query("SELECT `id`, `username` FROM `global__Players` WHERE `email`='$email' AND `verify`='' AND `password`='$pass'");
	if($user = $db->assoc($result)) {
		$_SESSION['user_id'] = $user['id'];
		$_SESSION['user_name'] = $user['username'];
		
		$db->query("UPDATE `global__Players` SET `PHPSESSION`='".session_id()."' WHERE `id`=".$user['id'], true);
		
		$stats->add("player_id", $user['id']);
		$stats->save("Login");
		
		// update cookies
		if(!$from_cookie) {
			if($_POST['remember'] == "on") {
				set_cookie("login", $email, $pass);
			} else {
				set_cookie("login");
			}
		}
		
		// remove currentGameType if set.
		setcookie("currentGameType", "-1", time() - 3600);
	} else {
		$stats->add("player_id", "-1");
		$stats->save("Login");
		
		push_message("<h2>Mislukt!</h2><br />Wachtwoord is ongeldig of het e-mailadres is niet gevonden!<br /><br />Controlleer de ingevoerde gegevens en probeer opnieuw.");
	}	
}

/**
  * Logout function
  */
function logout() {
	$db = &Database::getInstance();
	
	/*
	if(isset($_SESSION['user_id'])) {
		$db->query("UPDATE `global__Players` SET `PHPSESSION`='' WHERE `id`=".$_SESSION['user_id']);
	}
	*/
	
	$db->query("UPDATE `global__Players` SET `PHPSESSION`='' WHERE `PHPSESSION`='".session_id()."'");
	
	$_SESSION = array();
	session_destroy();
	session_start();
	session_regenerate_id(true);
	
	// to prevent auto-login!
	$_SESSION['cookie_login'] = true;
	
	header("Location: ./");
	die();	
}

/**
  * Convert the data for HTML use
  *
  * @param  String  $value   Data to convert for use in HTML
  */
function html_safe($value) {
	if(is_array($value)) {
		foreach($value as $key=>$subvalue) {
			htmlSafe(&$value[$key]);
		}
	} else {
		$value = str_replace("\\", "", $value);
	}
}

/**
  * Push message to the queue to display
  *
  * @param  String   $message    Message to be displayed to the client
  */
function push_message($message) {
	if(empty($_SESSION['messages'])) {
		$_SESSION['messages'] = array();
	}
	
	array_push($_SESSION['messages'], $message);
	
	/*
	$user_id = intval($_SESSION['user_id']);
	if($user_id > 0) {
		$db = &Database::getInstance();
		
		$time = time();
		$message = $db->prepare($message);
		
		$db = &Database::getInstance();
		$db->query("INSERT INTO `global__Messages` (`player_id`, `message`, `timestamp`) VALUES ('$user_id', '$message', '$time')");
	}
	*/
}

/**
  * Send email - when it fails, log it to the message-list of the admin
  *
  * @param   String   $to         Emailaddress of the reciever
  * @param   String   $subject    Topic of the message to be send
  * @param   String   $body       Body of the message
  */
function email($to, $subject, $body, $use_template = true, $bcc = '', $path_to_template = '../html/templates/') {
	$boundary = "-----rupsbandjenooitgenoeg_nl-----".md5(time());
	$headers = "From: Rupsbandjenooitgenoeg.nl <no-reply@rupsbandjenooitgenoeg.nl>\n"; //optional headerfields
	
	if($bcc != '') {
		$headers .= "Bcc: $bcc\n";
	}
	
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\n";
	
	
	if($use_template) {
		$template = file_get_contents($path_to_template . "email.html");
		$body = str_replace("#BODY#", $body, $template);
		$body = str_replace("#SUBJECT#", $subject, $body);
	}
	
	$body = email_split_mimes($body, $boundary);
	
	
	if(!mail($to, $subject, $body, $headers)) { //or trigger_error("MAIL|Email sending failed!\n\nReciever: $to\nSubject: $subject\n\n$body", E_USER_ERROR);
	//if(!pear_mail($to, $subject, $body, $bcc)) {
		$time = time();
		
		$db = &Database::getInstance();
		$db->query("INSERT INTO `global__Messages` (`player_id`, `message`, `timestamp`) VALUES ('1', 'Email sending failed!\n\nReciever: $to\nSubject: $subject\n\n$body', '$time')");
		
		return false;
	}
	
	return true;
}

/*
function pear_mail($to, $subject, $html_body, $bcc = '') {
	$headers = array('From'=>'contact@rupsbandjenooitgenoeg.nl', 'To'=>$to, 'Subject'=>$subject, 'Bcc'=>$bcc);
	
	$text_body = preg_replace("/<head(| .*?)>(.*?)<\/head>/is", "", $body);
	$text_body = trim(strip_tags($text_body));
	
	$mime = new Mail_mime();
	$mime->setTXTBody($text_body);
	$mime->setHTMLBody($html_body);
	
	$body 	= $mime->get();
	$headers = $mime->headers($hdrs);

	
	$smtp = Mail::factory('smtp', array('host'=>'rupsbandjenooitgenoeg.nl', 'port'=>25, 'auth'=>true, 'username'=>'contact@rupsbandjenooitgenoeg.nl', 'password'=>'peace', 'persist'=>true));
	//$mail =& Mail::factory('mail', $params);
	return $mail->send($to, $headers, $body);
}
*/

function email_split_mimes($body, $boundary) {
	
	// remove the HEAD tags
	$plain_body = preg_replace("/<head(| .*?)>(.*?)<\/head>/is", "", $body);
	
	# -=-=-=- TEXT EMAIL PART
	$message = "\n--$boundary\n";
	$message .= "Content-Type: text/plain; charset=UTF-8\n";
	$message .= "Content-Transfer-Encoding: 8bit\n\n";
	
	$message .= trim(strip_tags($plain_body));
	
	# -=-=-=- HTML EMAIL PART
	 
	$message .= "\n--$boundary\n";
	$message .= "Content-Type: text/html; charset=UTF-8\n";
	$message .= "Content-Transfer-Encoding: 8bit\n\n";
	
	$message .= $body;
	
	# -=-=-=- FINAL BOUNDARY
	
	$message .= "\n--$boundary--\n\n"; 
	
	return $message;
}

/** Save / Update the Cookies
  *
  * @param  String   $type     Type of the cookie to save
  * @param  String   $value1   Value used by Type (Default: "")
  * @param  String   $value2   Value used by Type (Default: "")
  */
function set_cookie($type, $value1 = "", $value2 = "") {
	if($type == "login") {
		$doLogin = ($value1 == "")?"0":"1";
		
		$path = "/";
		$domain = ".rupsbandjenooitgenoeg.nl";
		$expire = ($value1 == "")?time()-3600:time()+3600*24*30;
		$secure = false;
		$httponly = true;
		
		setcookie("automatic_login", 	$doLogin, 	$expire, $path, $domain, $secure, $httponly);
		setcookie("login_email", 		$value1, 	$expire, $path, $domain, $secure, $httponly);
		setcookie("login_pass", 		$value2, 	$expire, $path, $domain, $secure, $httponly);
	}
}

/** Validate entered emailaddress
  *
  * @param  String   $email    Emailaddress to check
  * @return boolean            True if valid, False if invalid
  */
function is_valid_email($email, $useDns = false) {
	// prevent attacks by reducing the value to the real value
	while($email != urldecode($email)) {
		$email = urldecode($email);
	}
	
	return is_email($email, $useDns);
	
	/*
	
   $isValid = true;
   $atIndex = strrpos($email, "@");
	
   if (is_bool($atIndex) && !$atIndex) {
      $isValid = false;
   } else {
      $domain = substr($email, $atIndex+1);
      $local = substr($email, 0, $atIndex);
      $localLen = strlen($local);
      $domainLen = strlen($domain);
      if ($localLen < 1 || $localLen > 64) {
         // local part length exceeded
         $isValid = false;
      } else if ($domainLen < 1 || $domainLen > 255) {
         // domain part length exceeded
         $isValid = false;
      } else if ($local[0] == '.' || $local[$localLen-1] == '.') {
         // local part starts or ends with '.'
         $isValid = false;
      } else if (preg_match('/\\.\\./', $local)) {
         // local part has two consecutive dots
         $isValid = false;
      } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
         // character not valid in domain part
         $isValid = false;
      } else if (preg_match('/\\.\\./', $domain)) {
         // domain part has two consecutive dots
         $isValid = false;
      } else if(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
         // character not valid in local part unless 
         // local part is quoted
         if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
            $isValid = false;
         }
      }
		
		if($useDns) {
			if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
				// domain not found in DNS
				$isValid = false;
			}
		}
		
   }
	
   return $isValid;
	*/
}

function is_email( $email, $check_dns = false ) {
	// Test for the minimum length the email can be
	if ( strlen( $email ) < 3 ) {
		return false;
	}

	// Test for an @ character after the first position
	if ( strpos( $email, '@', 1 ) === false ) {
		return false;
	}

	// Split out the local and domain parts
	list( $local, $domain ) = explode( '@', $email, 2 );

	// LOCAL PART
	// Test for invalid characters
	if ( !preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) ) {
		return false;
	}

	// DOMAIN PART
	// Test for sequences of periods
	if ( preg_match( '/\.{2,}/', $domain ) ) {
		return false;
	}

	// Test for leading and trailing periods and whitespace
	if ( trim( $domain, " \t\n\r\0\x0B." ) !== $domain ) {
		return false;
	}

	// Split the domain into subs
	$subs = explode( '.', $domain );

	// Assume the domain will have at least two subs
	if ( 2 > count( $subs ) ) {
		return false;
	}

	// Loop through each sub
	foreach ( $subs as $sub ) {
		// Test for leading and trailing hyphens and whitespace
		if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub ) {
			return false;
		}

		// Test for invalid characters
		if ( !preg_match('/^[a-z0-9-]+$/i', $sub ) ) {
			return false;
		}
	}

	// DNS
	// Check the domain has a valid MX and A resource record
	if ( $check_dns && function_exists( 'checkdnsrr' ) && !( checkdnsrr( $domain . '.', 'MX' ) || checkdnsrr( $domain . '.', 'A' ) ) ) {
		return false;
	}

	// Congratulations your email made it!
	return true;
}

/** Twitter Time Period formatting
  *
  * @param  int      $unix_date     Timestamp to format
  * @param  boolean  $use_tense     Use the historical tense in the formatting
  */
function twitter_time_period($unix_date, $use_tense = false) {
	if(empty($unix_date)) {
		return "No date provided";
	}
	
	$periods = array("seconde", "minuut", "uur", "dag", "week", "maand", "jaar", "decennium", "eeuw");
	$periods_plural = array("seconden", "minuten", "uren", "dagen", "weken", "maanden", "jaren", "decennia", "eeuwen");
	$lengths	= array("60","60","24","7","4.35","12","10","10");
	
	$now = time();
	
	 // check validity of date
	if(empty($unix_date)) {   
	  return "Bad date";
	}
	
	// is it future date or past date
	if($now > $unix_date) {
		$difference = $now - $unix_date;
		$tense = "geleden";
	} else {
		$difference = $unix_date - $now;
		$tense = "van nu";
	}
	
	for($j = 0; $difference >= $lengths[$j] && $j < count($lengths); $j++) {
	  $difference /= $lengths[$j];
	}
	
	$difference = round($difference);
	
	if($difference != 1) {
//	  $periods[$j].= "s";
	  $period = $periods_plural[$j];
	} else {
		$period = $periods[$j];
	}
	
	$return = "$difference $period";
	if($use_tense) {
		$return .= " $tense";
	}
	
	return $return;
}


/**
  * Kill the Chat / Game server - only usable by Admin
  *
  * @param   String   $type    Type of server to kill - Chat / Game
  */
function kill_server($type) {
	$config = file_get_contents("../assets/servers.xml");
	$xml = xml2array($config);
	
	$server = get_value_by_path($xml, 'servers/'.$type.'server/server');
	$port = get_value_by_path($xml, 'servers/'.$type.'server/port');
	
	$server = $server['value'];
	$port = $port['value'];
	
	// connect to the server
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>1, "usec"=>0));
	
	if(@socket_connect($socket, $server, $port)) {
		// identify to the server (must be admin to TERMinate)
		$msg = "PHPSESSION=".session_id();
		socket_write($socket, $msg, strlen($msg));
		
		$data = @socket_read($socket, 1024);
		
		$response = xml2array($data);
		$identified = get_value_by_path($response, "s/i");
		
		$ok = (intval($identified['value']) == 1);
		if($ok) {
			// terminate the server
			socket_write($socket, "TERM", strlen("TERM"));
		
			// close the connection
			while($data = @socket_read($socket, 1024)) {
				// wait for server to close.
			}
			
			@socket_close($socket);
			return;
		}
		
		@socket_close($socket);
	}
	
	$server = ($type == "game")?"Game":"Chat";
	$serverProcess = exec("ps aux | grep rups".$server."Server | grep -v grep | awk '{print $2'}");
	
	if(intval($serverProcess) > 0) {
		exec("kill -9 $serverProcess");
		exec("screen -wipe");
	}
}

/*
 * SocketServer functions
 *
 * 	xml2array: coverts xml to a php array.
 *		get_value_by_path: fetching data logically from the array.
 *		trace: SocketServer tracing to the output buffer (captured in SCREEN)
 *
 *	nov 7th, 2009 @ 23:00
*/

/**
  * Convert XML to Associative Array
  * 
  * @param  String   $contents    XML Input to be converted
  * @return array                 Array created from the input XML
  */
function xml2array($contents) {
    $xml_values = array();
    $parser = xml_parser_create('');
    
	 if(!$parser) {
        return false;
	 }

    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
	 
    if(!$xml_values) {
    	return array();
	 }
   
    $xml_array = array();
    $last_tag_ar =& $xml_array;
    $parents = array();
    $last_counter_in_tag = array(1=>0);
	 
    foreach ($xml_values as $data) {
        switch($data['type']) {
            case 'open':
                $last_counter_in_tag[$data['level']+1] = 0;
                $new_tag = array('name' => $data['tag']);
					 
                if(isset($data['attributes']))
                    $new_tag['attributes'] = $data['attributes'];
						  
                if(isset($data['value']) && trim($data['value']))
                    $new_tag['value'] = trim($data['value']);
					 
                $last_tag_ar[$last_counter_in_tag[$data['level']]] = $new_tag;
                $parents[$data['level']] =& $last_tag_ar;
                $last_tag_ar =& $last_tag_ar[$last_counter_in_tag[$data['level']]++];
					 
                break;
            case 'complete':
                $new_tag = array('name' => $data['tag']);
                
					 if(isset($data['attributes']))
                    $new_tag['attributes'] = $data['attributes'];
                
					 if(isset($data['value']) && trim($data['value']))
                    $new_tag['value'] = trim($data['value']);

                $last_count = count($last_tag_ar)-1;
                $last_tag_ar[$last_counter_in_tag[$data['level']]++] = $new_tag;
                break;
            case 'close':
                $last_tag_ar =& $parents[$data['level']];
                break;
            default:
                break;
        }
    }
	 
    return $xml_array;
}

// use this to get node of tree by path with '/' terminator
/**
  * Retrieve data from the Associative Array in an easy way
  *
  * @param   array    $__xml_tree     The Associative Array
  * @param   String   $__tag_path     Path to extract
  * @return  mixed                    Depending on node or data, returns array or String
  */
function get_value_by_path($__xml_tree, $__tag_path) {
	$tmp_arr =& $__xml_tree;
	$tag_path = explode('/', $__tag_path);
	
	foreach($tag_path as $tag_name) {
		$res = false;
		foreach($tmp_arr as $key => $node) {
			if(is_int($key) && $node['name'] == $tag_name) {
				$tmp_arr = $node;
				$res = true;
				break;
			}
		}
	  
		if(!$res)
			return false;
	}
		
	return $tmp_arr;
}

/**
  * Trace output to the logfile or just to the screen
  *
  * @param  String   $msg     Message to be logged
  */
function trace($msg) {
	global $log_file;
	
	$msg = "[".date('Y-m-d H:i:s')."] ".$msg;
	
	if($log_file) {
		fwrite($log_file, "\r\n".$msg);
	}
	
	print($msg."\n");
}

?>