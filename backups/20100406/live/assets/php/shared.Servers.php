<?php

// No timeouts, flush content immediatly
set_time_limit(0);
ob_implicit_flush();

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

if($test = @fopen("logs/test", "w")) {
	ini_set('display_errors', 0);
	@unlink("logs/test");
}


if(class_exists("Database")) {
	set_error_handler("error_handler");
}
	
/**
  * Log the last error before closing a server.
  */
function log_error() {
	global $chat, $game;
	
	if($error = error_get_last()) {
		if($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR) {
			$error_msg = "Message: " . $error['message'] . "\nFile: " . $error['file'] . "\nLine: " . $error['line'];
			if($fpError = @fopen("logs/errors/".date('Y-m-d_h-i-s', time()).".txt", "w")) {
				fwrite($fpError, $error_msg, strlen($error_msg));
				fclose($fpError);
			}
			
			if(isset($chat) || isset($game)) {
				$stats = new Statistics();
				$stats->add("type", $this->name);
				$stats->add("status", "OFFLINE");
				$stats->add("description", "Crashed: " . $error['message']);
				$stats->save("Servers");
			}
		}
	}
}

/**
  * Handle PHP Error in a custom way; If there is no write access to the log directory output PHP errors to the database
  */
function error_handler($errno, $errstr, $errfile, $errline) {
	$db = &Database::getInstance();
	
    switch ($errno) {
    case E_USER_ERROR:
        $data .= "<b>My ERROR</b> [$errno] $errstr<br />\n";
        $data .=  "  Fatal error on line $errline in file $errfile";
        $data .=  ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
        $data .=  "Aborting...<br />\n";
        exit(1);
        break;

    case E_USER_WARNING:
        $data .= "<b>My WARNING</b> [$errno] $errstr";
        break;

    case E_USER_NOTICE:
        $data .= "<b>My NOTICE</b> [$errno] $errstr";
        break;
		 
	 case E_ERROR:
	 		$type = "ERROR";
	 case E_WARNING:
	 		$type = (!isset($type))?"WARNING":$type;
	 case E_PARSE:
	 		$type = (!isset($type))?"PARSE":$type;
        $data .= "<b>PHP $type</b> [$errno] $errstr";
        break;
    }
	 
	 if(isset($data)) {
		 $time = time();
		 $data = $db->prepare($data);
		 $db->query("INSERT INTO `global__ServerErrors` (`message`, `timestamp`) VALUES ('$data', '$time')");
	 }

    /* Don't execute PHP internal error handler */
    return true;
}

register_shutdown_function("log_error");

?>