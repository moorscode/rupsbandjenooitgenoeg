<?php

require_once("class.SocketServer.php");

/**
  * Chat Server
  */

class ChatServer extends SocketServer {
	/**
	  * @var Catch Phrases to look for in Chat
	  */
	var $catchPhrases = array();
	
	/**
	  * @var Command that can be executed by Chat
	  */
	var $commands = array();
	
	var $phrase_identifier = "#";
	
	/**
	  * Initialize the server, setting commands and phrases to be listened for
	  */
	function ChatServer() {
		parent::SocketServer("chatserver");
		
		$this->add_command("/commands", "cmd_help");
		$this->add_command("/debug", "cmd_help");
		$this->add_command("/hulp", "cmd_help");
		$this->add_command("/help", "cmd_help");
		
		$this->add_phrase("BUG", "foutmelding");
		$this->add_phrase("IDEE", "idee");
		$this->add_phrase("VRAAG", "vraag");
	}
	
	/**
	  * Add command to the list
	  *
	  * @param   String     $text          Command to catch
	  * @param   function   $function      Function to execute on command
	  */
	function add_command($text, $function) {
		$command = array();
		$command['catch'] = $text;
		$command['function'] = $function;
		
		array_push($this->commands, $command);
	}
	
	/**
	  * Add Phrase to listen to
	  *
	  * @param   String    $catch       Text to listen for
	  * @param   String    $type        Textual type for the response
	  */
	function add_phrase($catch, $type) {
		$newPhrase = array();
		$newPhrase['catch'] = $catch;
		$newPhrase['type'] = $type;
		
		array_push($this->catchPhrases, $newPhrase);
	}
	
	/**
	  * Scan for Commands
	  *
	  * @param   int      $user_id       User of the given text
	  * @param   String   $text          Text to scan in
	  */
	function scan_for_commands($user_id, $text) {
		$time = time();
		
		// check for server commands:
		foreach($this->commands as $command) {
			if(strtoupper(substr($text, 0, strlen($command['catch']))) == strtoupper($command['catch'])) {
				return call_user_func(array($this, $command['function']), substr($text, strlen($command['catch'])));
			}
		}
		
		// check for debug phrases:
		foreach($this->catchPhrases as $phrase) {
			if(strtoupper(substr($text, 0, strlen($this->phrase_identifier) + strlen($phrase['catch']))) == strtoupper($this->phrase_identifier . $phrase['catch'])) {
				
				$text_sql = $this->db->prepare( trim(substr($text, strlen($this->phrase_identifier) + strlen($phrase['catch']))) );
				if($text_sql == "") {
					return "Om een " . $phrase['type'] . " toe te voegen is een omschrijving verplicht!";	
				}
				
				$this->db->query("INSERT INTO `dev__UserInput` (`player_id`, `phrase`, `text`, `timestamp`) VALUES ($user_id, '".trim($phrase['catch'])."', '$text_sql', $time)");
				return "Bedankt voor je ".$phrase['type'].", we proberen zo snel mogelijk te reageren!";
			}
		}
		
		if(substr($text, 0, strlen($this->phrase_identifier)) == $this->phrase_identifier && strlen($text) > strlen($this->phrase_identifier)) {
			return "Dit is geen geldig report.\nType /help voor de beschikbare commando's!";
		}
		
		return $text;
	}
	
	/**
	  * Execute the HELP command
	  *
	  * @param   String    $text     Total input text of the user
	  * @return  String              Returns response to be send back
	  */
	function cmd_help($text) {
		$return = "\n\nBeschikbare commando's zijn:\n";
		foreach($this->catchPhrases as $phrase) {
			$return .= $this->phrase_identifier . $phrase['catch'] . " voor een " . $phrase['type'] . ".\n";
		}
		return $return;
	}
	
	/**
	  * Implementation of on_input
	  */
	  
	function on_input($client_index) {
		$client 	= &$this->client[$client_index];
		$input 	= $client['input'];
		$user_id = $client['user_id'];
		
		$xml = xml2array($input);
				
		// handle chat input:
		$what = get_value_by_path($xml, 'a/w');
		$what = $what['value'];
		
		// request user list:
		switch($what) {
			case "list":
				$this->send_list($client_index);
				break;
				
			case "say":
				$text = get_value_by_path($xml, 'a/text');
				$text = $text['value'];
				
				$time = time();
				$idle_time = intval($client['idle_time']);
				$idle_time = $time - $idle_time;
				
				$this->stats->add("player_id", $user_id);
				$this->stats->add("type", "IDLE");
				$this->stats->add("value", $idle_time);
				$this->stats->save("Chat");
				
				// check for commands to be executed
				$returnText = $this->scan_for_commands($user_id, $text);
				
				if($returnText == $text) {
					$text = strip_tags($text);
					$text = htmlentities($text);
					
					$text = str_replace('&nbsp;', ' ', $text);
					
					while($text != str_replace('  ', ' ', $text)) {
						$text = str_replace('  ', ' ', $text);
					}
					
					$xml = "<w>say</w><i>$user_id</i><text>$text</text>";
					$this->send_to_all($xml);
				} else {
					$this->send($client_index, "<w>say</w><i>$user_id</i><text>$returnText</text>");
				}
				
				$client['idle_time'] = $time;
				break;
				
			case "join":
			case "part":
				if($client['identified']) {
					$this->send_action($client_index, $what);
				}
				break;
			
			case "achievement":
				$achievement_id = get_value_by_path($xml, 'a/i');
				$achievement_id = $achievement_id['value'];
				
				$this->announce_achievement($client_index, $achievement_id);
				
				break;
			
			case "achieved":
				// achieving the 'completed single player'
				
				$achievement_id = $this->set_achievement($client_index, "PREPARED");
				if($achievement_id > 0) {
					$this->announce_achievement($client_index, $achievement_id);
				}
				
				break;
		}
	}
	
	function client_connected($index) {
		parent::client_connected($index);
	}
	
	function client_disconnect($index) {
		$client 		= &$this->client[$index];
		
		$user_id   	= $client['user_id'];
		$user_name 	= $client['user_name'];
		
		if($client['identified']) {
			$time = time();
			$player_id = $client['user_id'];
			
			$time = time();
			$idle_time = intval($client['idle_time']);
			$idle_time = $time - $idle_time;
			
			if($idle_time > 0) {
				$this->stats->add("player_id", $player_id);
				$this->stats->add("type", "IDLE");
				$this->stats->add("value", $idle_time);
				$this->stats->save("Chat");
			}
			
			$this->stats->add("player_id", $user_id);
			$this->stats->add("type", "LEAVE");
			$this->stats->save("Chat");
			
			// send 'leaving' to all clients :: clients figure if they show it
			$this->send_to_all("<w>part</w><i>$user_id</i>");
		}
		
		parent::client_disconnect($index);
		
		$this->send_list();
	}
	
	// send join info when identified.
	function client_identified($index, $success) {
		parent::client_identified($index, $success);
		
		$this->send_action($index, "identified");
		
		if($success) {
			$time = time();
			$user_id = $this->client[$index]['user_id'];
			$user_name = $this->client[$index]['user_name'];
			
			$this->stats->add("player_id", $user_id);
			$this->stats->add("type", "JOIN");
			$this->stats->add("value", $this->client[$index]['ip']);
			$this->stats->save("Chat");
			
			$this->client[$index]['idle_time'] = time();
			
			$this->send_list();
			
			$this->send($index, "<w>say</w><i>$user_id</i><text>Welkom in de chat! Typ /help voor beschikbare commando's.</text>");
			$this->send_to_all("<w>join</w><i>$user_id</i><n>$user_name</n>", $index);
			
		} else {
			$this->stats->add("type", "REJECTED");
			$this->stats->add("value", $this->client[$index]['ip']);
			$this->stats->save("Chat");
		}
	}
	
	/**
	  * Send an action to the client
	  *
	  * @param  int      $index      Client index to send to
	  * @param  String   $type       Type of action
	  */
	function send_action($index, $type = "join") {
		$user_id = $this->client[$index]['user_id'];
		$user_name = $this->client[$index]['user_name'];
		
		$xml = "<w>$type</w><i>$user_id</i><n>$user_name</n>";
		
		if($type != "identified") {
			for($c = 0; $c < count($this->client); $c++) {
				if($c == $index) continue;
				$this->send($c, $xml);
			}
		} else {
			$xml .= "<success>" . $this->client[$index]['identified'] . "</success>";
			$this->send($index, $xml);
		}
	}
	
	/**
	  * Send chat listings to the client
	  *
	  * @param    int     $index        Client to send it to, or everybdoy (Default: -1)
	  */
	function send_list($index = -1) {
		$chatlist = "<w>list</w><us>";
		
		for($c = 0; $c < count($this->client); $c++) {
			$client = $this->client[$c];
			
			if($client['identified'] && is_resource($client['sock'])) {
				$user_id = $client['user_id'];
				$user_name = $client['user_name'];
				$chatlist .= "<u><i>$user_id</i><n>$user_name</n></u>";
			}
		}
		
		$chatlist .= "</us>";
		
		if($index != -1) {
			$this->send($index, $chatlist);	
		} else {
			$this->send_to_all($chatlist);
		}
	}
	
	function announce_achievement($client_index, $achievement_id) {
		return;
		
		$client = &$this->client[$client_index];
		
		// check against database:
		$query = $this->db->query("SELECT c.`timestamp`, a.`title` FROM `achievements__Achievements` AS a INNER JOIN `achievements__Completed` as c ON a.`id`=c.`achievement_id` WHERE a.`id`=$achievement_id AND c.`player_id`=".$client['user_id']);
		
		if($achievement = $this->db->assoc($query)) {
			// record found.
			$this->send_to_all("<w>achievement</w><i>".$client['user_id']."</i><title>".$achievement['title']."</title><when>".strftime("%e %B %Y om %l:%M %p", $achievement['timestamp'])."</when>");
		}
	}
	
	function send($index, $xml, $send_raw = false) {
		if($send_raw) {
			parent::send($index, $xml, true);
		} else {
			parent::send($index, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>chat</t>".$xml."</s>");
		}
	}
	
	function send_to_all($xml, $exclude_index = -1) {
		parent::send_to_all("<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>chat</t>".$xml."</s>", $exclude_index);
	}
}

?>