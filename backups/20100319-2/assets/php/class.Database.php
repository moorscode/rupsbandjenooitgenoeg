<?php

/**
 * Class: Database
 * Handle all the database queries and prepare data to be entered correctly.
 *
 */ 
class Database {
	/**
	  * @var Host to connect to
	  */
	var $host;
	
	/**
	  * @var Database to use
	  */
	var $db;
	
	/**
	  * @var Username to connect with
	  */
	var $user;
	
	/**
	  * @var Password to use
	  */
	var $pass;
	
	/**
	  * @var Resource of the database connection
	  */
	var $socket;
	
	/**
	 * Database Constructor: 
	 * Handle all the database queries and prepare data to be entered correctly.
	 *
	 * @access public  
	 * @param  String   $db    Name of the Database to connect to
	 * @param  String   $user  Name of the user to connect with
	 * @param  String   $pass  Password required to log in
	 * @param  String   $host  Host of the database (Default: localhost)
	 */ 
	function Database($config_file = "/assets/config.db.php") {
		
		$root = dirname($_SERVER["DOCUMENT_ROOT"]);
		
		if($root == "") {
			$root = ".";
		} else {
			if(stristr($root, "/MAMP") !== false) {
				$root .= "/htdocs/rupsbandjenooitgenoeg/live";
			} else {
				$root .= "/web";
			}
		}
			
		if(file_exists($root.$config_file)) {
			require_once($root.$config_file);
		} else {
			die("Could not find {$root}{$config_file}");
		}
		
		$this->host = DB_HOST;
		$this->db = DB_DATABASE;
		$this->user = DB_USERNAME;
		$this->pass = DB_PASSWORD;
			
		//$db = 'rupsbandjenooitgenoeg', $user = 'rupsbandje', $pass = '46Emq7dE6fGpTzy3', $host = 'localhost';
		
		$hostname = @exec("hostname");
		if(trim($hostname) == "Yahweh.local") {
			$this->host .= ':/Applications/MAMP/tmp/mysql/mysql.sock';
		}
				
		$this->connect();
	}
	
	/**
	 * Function: Implementation of the Singleton design pattern
	 * Return the same instance of this class to all requestees
	 *
	 * @access public static
	 * @return Database   The correct database instance
	 */ 
	static function getInstance() {
		/**
		  * @var Global instance of the Database class to return to any who needs it
		  */
		static $instance;
		
		if(!isset($instance)) {
			$c = __CLASS__;
			$instance = new $c;
		}
		
		return $instance;
	}

	/**
	 * Function: Connect to the database
	 *
	 * @access public 
	 */ 
	function connect() {
		$this->socket = @mysql_connect($this->host, $this->user, $this->pass) or die(mysql_error());
		@mysql_select_db($this->db, $this->socket) or die(mysql_error());
	}
	
	/**
	  * Function: Prepare text to be entered to the database.
	  * Using this function implements the SQL Injection hacks
	  *
	  * @access public
	  * @param  String   $text         Text to be prepared
	  * @param  int      $max_length   Maximal length of the text (Default: -1)
	  * @return String                 The prepared text, ready for database entry
	  */
	function prepare($text, $max_length = -1) {
		if(get_magic_quotes_gpc()) {
			$text = stripslashes($text);
		}
		
		$text = mysql_real_escape_string($text, $this->socket);
		if($max_length > -1) {
			$text = substr($text, 0, $max_length);
		}
		
		return $text;
	}
	
	/**
	  * Function: Query the database
	  *
	  * @access public
	  * @param  String   $sql        SQL to execute
	  * @return resource             The query result (if any)
	  */
	function query($sql) {
		$result = @mysql_query($sql, $this->socket);
		
		if(!$result) {
			$error = mysql_error();
			
			trace($sql);
			trace("Database error: " . $error);
			
			$sql = $this->prepare($sql);
			$error = $this->prepare($error);
			$time = time();
			mysql_query("INSERT INTO `global__DatabaseErrors` (`sql`, `error`, `timestamp`) VALUES ('$sql', '$error', $time)", $this->socket);
		}
		
		return $result;
	}
	
	function insert_id() {
		return mysql_insert_id($this->socket);
	}
	
	/**
	  * Function: Num Rows retuned on a query
	  */
	function num_rows($result) {
		if($result && is_resource($result)) {
			return mysql_num_rows($result);
		}
		
		return 0;
	}
	
	/**
	  * Function: Free the query result - clearing it from the memory.
	  *
	  * @access public
	  * @param  resource  $result   Resource to unload
	  */
	function free($result) {
		if($result && is_resource($result)) {
			@mysql_free_result($result);
		}
	}
	
	/**
	  * Function: Fetch an associative array from a query resource
	  * 
	  * @access pulic
	  * @param  resource  $result   Resource to fetch associative array from
	  * @return array               Array containing the next row from the resource
	  */
	function assoc($result) {
		if(is_resource($result)) {
			return mysql_fetch_assoc($result);
		}
		
		return $null;
	}
}

?>