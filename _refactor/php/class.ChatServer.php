<?php

require_once( "class.SocketServer.php" );

/**
 * Chat Server
 */
class ChatServer extends SocketServer {
	/**
	 * @var Phrases to look for in the Chat
	 */
	var $catchPhrases = array();

	/**
	 * @var Identifier that preceeds entering commands
	 */
	var $phrase_identifier = "#";

	/**
	 * @var Command that can be executed by Chat
	 */
	var $commands = array();

	var $lastRandomAchievement = array( 'day' => 0, 'month' => 0 );

	/**
	 * Initialize the server, setting commands and phrases to be listened for
	 */
	function ChatServer() {
		parent::SocketServer( "chatserver" );
		$this->fps = 1;

		$this->add_command( "/commands", "cmd_help" );
		$this->add_command( "/debug", "cmd_help" );
		$this->add_command( "/hulp", "cmd_help" );
		$this->add_command( "/help", "cmd_help" );

		$this->add_phrase( "BUG", "foutmelding" );
		$this->add_phrase( "IDEE", "idee" );
		$this->add_phrase( "VRAAG", "vraag" );
	}

	/**
	 * Add command to the list
	 *
	 * @param   String   $text     Command to catch
	 * @param   function $function Function to execute on command
	 */
	function add_command( $text, $function ) {
		$command             = array();
		$command['catch']    = $text;
		$command['function'] = $function;

		array_push( $this->commands, $command );
	}

	/**
	 * Add Phrase to listen to
	 *
	 * @param   String $catch Text to listen for
	 * @param   String $type  Textual type for the response
	 */
	function add_phrase( $catch, $type ) {
		$newPhrase          = array();
		$newPhrase['catch'] = $catch;
		$newPhrase['type']  = $type;

		array_push( $this->catchPhrases, $newPhrase );
	}

	/**
	 * Scan for Commands
	 *
	 * @param   int    $user_id User of the given text
	 * @param   String $text    Text to scan in
	 */
	function scan_for_commands( $user_id, $text ) {
		$time = time();

		// check for server commands:
		foreach ( $this->commands as $command ) {
			if ( strtoupper( substr( $text, 0, strlen( $command['catch'] ) ) ) == strtoupper( $command['catch'] ) ) {
				return call_user_func( array(
					$this,
					$command['function']
				), substr( $text, strlen( $command['catch'] ) ) );
			}
		}

		// check for debug phrases:
		foreach ( $this->catchPhrases as $phrase ) {
			if ( strtoupper( substr( $text, 0, strlen( $this->phrase_identifier ) + strlen( $phrase['catch'] ) ) ) == strtoupper( $this->phrase_identifier . $phrase['catch'] ) ) {

				$text_sql = $this->db->prepare( trim( substr( $text, strlen( $this->phrase_identifier ) + strlen( $phrase['catch'] ) ) ) );
				if ( $text_sql == "" ) {
					return "Om een " . $phrase['type'] . " toe te voegen is een omschrijving verplicht!";
				}

				$this->db->query( "INSERT INTO `dev__UserInput` (`player_id`, `phrase`, `text`, `timestamp`) VALUES ('$user_id', '" . trim( $phrase['catch'] ) . "', '$text_sql', $time)" );

				return "Bedankt voor je " . $phrase['type'] . ", we proberen zo snel mogelijk te reageren!";
			}
		}

		if ( substr( $text, 0, strlen( $this->phrase_identifier ) ) == $this->phrase_identifier && strlen( $text ) > strlen( $this->phrase_identifier ) ) {
			return "Dit is geen geldig commando.\nType /help voor de beschikbare commando's!";
		}

		return $text;
	}

	/**
	 * Execute the HELP command
	 *
	 * @param   String $text Total input text of the user
	 *
	 * @return  String              Returns response to be send back
	 */
	function cmd_help( $text ) {
		$return = "\n\nBeschikbare commando's zijn:\n";
		foreach ( $this->catchPhrases as $phrase ) {
			$return .= $this->phrase_identifier . $phrase['catch'] . " voor een " . $phrase['type'] . ".\n";
		}

		return $return;
	}

	/**
	 * Ticking every x ms (depending on FPS)
	 */
	protected function tick() {
		list( $month, $day, $hour, $minute ) = explode( " ", date( 'n j G i' ) );

		if ( count( $this->client ) > 0 ) {

			// return if achievements are disabled
			if ( ! $this->achievements ) {
				return;
			}

			// try to apply the 'by_admin' achievement every day at 21:00
			if ( 21 == $hour && 0 == $minute ) {
				if ( $this->lastRandomAchievement['day'] != $day || $this->lastRandomAchievement['month'] != $month ) {

					$clients_tried = 0;
					$applied       = false;

					while ( $clients_tried < count( $this->client ) && ! $applied ) {

						// only try each client once
						$index = rand( 0, count( $this->client ) - 1 );
						while ( $this->client[ $index ]['achievement_by_admin'] ) {
							$index = rand( 0, count( $this->client ) - 1 );
						}

						// try to apply the achievement to the client
						$uid                                            = $this->client[ $index ]['user_id'];
						$this->client[ $index ]['achievement_by_admin'] = true;

						// if it fails, its already applied or achievements are disabled
						if ( ( $applied = $this->set_achievement( $uid, "BY_ADMIN" ) ) ) {
							$this->lastRandomAchievement['day']   = $day;
							$this->lastRandomAchievement['month'] = $month;
						}

						$clients_tried++;
					}
				}
			}
		}
	}

	/**
	 * Implementation of on_input
	 */
	protected function on_input( $client_index ) {
		$client  = &$this->client[ $client_index ];
		$input   = $client['input'];
		$user_id = $client['user_id'];

		$xml = xml2array( $input );

		// handle chat input:
		$what = get_value_by_path( $xml, 'a/w' );
		$what = $what['value'];

		// request user list:
		switch ( $what ) {
			case "list":
				$this->send_list( $user_id );
				break;

			case "say":
				$text = get_value_by_path( $xml, 'a/text' );
				$text = $text['value'];

				$time      = time();
				$idle_time = intval( $client['idle_time'] );
				$idle_time = $time - $idle_time;

				$this->stats->add( "player_id", $user_id );
				$this->stats->add( "type", "IDLE" );
				$this->stats->add( "value", $idle_time );
				$this->stats->save( "Chat" );

				// check for commands to be executed
				$returnText = $this->scan_for_commands( $user_id, $text );

				if ( $returnText == $text ) {
					// $text = strip_tags($text);
					$text = htmlentities( $text, ENT_NOQUOTES, "ISO8859-15" );
					$text = str_replace( "&lt;", "&amp;lt;", $text );
					$text = str_replace( "&gt;", "&amp;gt;", $text );

					// strip spaces
					$text = str_replace( '&amp;nbsp;', '&nbsp;', $text );
					$text = str_replace( '&nbsp;', ' ', $text );
					// replace double spaces by a single space
					while ( $text != str_replace( '  ', ' ', $text ) ) {
						$text = str_replace( '  ', ' ', $text );
					}

					$xml = "<w>say</w><i>$user_id</i><text>$text</text>";
					$this->send_to_all( $xml );
				}
				else {
					$this->send( $user_id, "<w>say</w><i>$user_id</i><text>$returnText</text>" );
				}

				$client['idle_time'] = $time;
				break;

			case "join":
			case "part":
				if ( $client['identified'] ) {
					$this->send_action( $user_id, $what );
				}
				break;

			case "achievement":
				$achievement_id = get_value_by_path( $xml, 'a/i' );
				$achievement_id = $achievement_id['value'];

				$this->announce_achievement( $user_id, $achievement_id );

				break;
		}
	}

	function client_connected( $index ) {
		parent::client_connected( $index );
		$this->client[ $index ]['idle_time'] = time();
	}

	function client_disconnect( $index, $forced = false ) {
		$client = &$this->client[ $index ];

		if ( $client['identified'] ) {
			$user_id   = intval( $client['user_id'] );
			$user_name = $client['user_name'];

			$time      = time();
			$idle_time = intval( $client['idle_time'] );

			if ( $idle_time > 0 ) {
				$idle_time = $time - $idle_time;

				$this->stats->add( "player_id", $user_id );
				$this->stats->add( "type", "IDLE" );
				$this->stats->add( "value", $idle_time );
				$this->stats->save( "Chat" );
			}

			if ( ! $forced ) {
				$this->stats->add( "player_id", $user_id );
				$this->stats->add( "type", "LEAVE" );
				$this->stats->save( "Chat" );

				// send 'leaving' to all clients :: clients figure if they show it
				$this->send_to_all( "<w>part</w><i>$user_id</i>" );
			}
		}

		parent::client_disconnect( $index );

		if ( ! $forced ) {
			$this->send_list();
		}
	}

	// send join info when identified.
	function client_identified( $index, $success ) {
		parent::client_identified( $index, $success );

		$user_id = $this->client[ $index ]['user_id'];
		$this->send_action( $user_id, "identified" );

		if ( $success ) {
			$time      = time();
			$user_name = $this->client[ $index ]['user_name'];

			$this->stats->add( "player_id", $user_id );
			$this->stats->add( "type", "JOIN" );
			$this->stats->add( "value", $this->client[ $index ]['ip'] );
			$this->stats->save( "Chat" );

			$this->client[ $index ]['idle_time'] = time();

			$this->send_list();

			$this->send( $user_id, "<w>say</w><i>$user_id</i><text>Welkom in de chat! Typ /help voor beschikbare commando's.</text>" );
			$this->send_to_all( "<w>join</w><i>$user_id</i><n>$user_name</n>", $user_id );

		}
		else {
			$this->stats->add( "type", "REJECTED" );
			$this->stats->add( "value", $this->client[ $index ]['ip'] );
			$this->stats->save( "Chat" );
		}
	}

	/**
	 * Send an action to the client
	 *
	 * @param  int    $index Client index to send to
	 * @param  String $type  Type of action
	 */
	function send_action( $user_id, $type = "join" ) {
		//$user_id = $this->client[$index]['user_id'];
		$index     = $this->get_client_index( $user_id );
		$user_name = $this->client[ $index ]['user_name'];

		$xml = "<w>$type</w><i>$user_id</i><n>$user_name</n>";

		if ( $type != "identified" ) {
			for ( $c = 0; $c < count( $this->client ); $c++ ) {
				if ( $this->client[ $c ]['user_id'] == $user_id ) {
					continue;
				}
				$this->send( $this->client[ $c ]['user_id'], $xml );
			}
		}
		else {
			$xml .= "<success>" . $this->client[ $index ]['identified'] . "</success>";
			$this->send( $user_id, $xml );
		}
	}

	/**
	 * Send chat listings to the client
	 *
	 * @param    int $index Client to send it to, or everybody = Default: -1
	 */
	function send_list( $user_id = -1 ) {
		$chatlist = "<w>list</w><us>";

		for ( $c = 0; $c < count( $this->client ); $c++ ) {
			$client = $this->client[ $c ];

			if ( $client['identified'] && is_resource( $client['sock'] ) ) {
				$uid       = $client['user_id'];
				$user_name = $client['user_name'];
				$chatlist .= "<u><i>$uid</i><n>$user_name</n></u>";
			}
		}

		$chatlist .= "</us>";

		if ( $user_id != -1 ) {
			$this->send( $user_id, $chatlist );
		}
		else {
			$this->send_to_all( $chatlist );
		}
	}

	/**
	 * Show an achievement earned by a user (mostly parsed through javascript)
	 */
	function announce_achievement( $user_id, $achievement_id ) {
		if ( ! $this->achievements ) {
			return;
		}

		// check against the database
		$query = $this->db->query( "SELECT c.`timestamp`, a.`title` FROM `achievements__Achievements` AS a INNER JOIN `achievements__Completed` as c ON a.`id`=c.`achievement_id` WHERE a.`id`=$achievement_id AND c.`player_id`=" . $user_id );

		// if a record is found - announce to chat users
		if ( $achievement = $this->db->assoc( $query ) ) {
			$this->send_to_all( "<w>achievement</w><i>" . $user_id . "</i><title>" . $achievement['title'] . "</title><when>" . strftime( "%e %B %Y om %l:%M %p", $achievement['timestamp'] ) . "</when>" );
		}
	}

	function send( $user_id, $xml, $send_raw = false ) {
		if ( $send_raw ) {
			parent::send( $user_id, $xml, true );
		}
		else {
			parent::send( $user_id, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>chat</t>" . $xml . "</s>" );
		}
	}

	function send_to_all( $xml, $exclude_id = -1 ) {
		parent::send_to_all( "<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>chat</t>" . $xml . "</s>", $exclude_id );
	}
}

