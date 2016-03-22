<?php

require_once( "class.Statistics.php" );

/**
 * Class: Socket Server
 */
class SocketServer {
	/**
	 * @var Readible identified of what server this is (chat / game)
	 */
	var $name;

	/**
	 * @var Name of the config file to get IP/Port information from
	 */
	var $config_file;

	/**
	 * @var Server IP to listen on
	 */
	var $server;

	/**
	 * @var Port to listen on
	 */
	var $port;

	/**
	 * @var Reference to the database
	 */
	var $db;

	var $fakeUsers = 0;
	var $fakeUserIndex = 0;

	/**
	 * Constructor: Initialize to use a server. Getting the server and port from the config file and updating stats.
	 */
	function SocketServer( $server_name, $config_file = "servers.xml" ) {
		$this->config_file = $config_file;
		$this->name        = $server_name;
		$this->db          = &Database::getInstance();
		$this->stats       = new Statistics();

		setlocale( LC_TIME, "nl_NL" );

		// if the server crashed, it didn't update the database
		// using the last modification date of its logfile, we can still put it in the database correctly!

		$logdir   = "logs/" . $server_name . "/";
		$logfiles = array();

		$query = $this->db->query( "SELECT `timestamp`, `status` FROM `stats__Servers` ORDER BY `id` DESC LIMIT 0,1" );
		if ( $last = $this->db->assoc( $query ) ) {
			if ( $last['status'] == "ONLINE" ) {
				// get list of files
				$dir_handle = opendir( $logdir );
				while ( $file = readdir( $dir_handle ) ) {
					if ( $file != "." && $file != ".." ) {
						if ( filemtime( $logdir . $file ) > $last['timestamp'] ) {
							array_push( $logfiles, filemtime( $logdir . $file ) );
						}
					}
				}
				closedir( $dir_handle );
			}
		}

		// if files found = 1 (this logfile) it's not enough...
		if ( count( $logfiles ) >= 2 ) {
			arsort( $logfiles ); // reverse sort the file modification times, making the one we need the 2nd from the start.

			// get the second last file (last is our logfile!)
			$this->stats->add( "type", $this->name );
			$this->stats->add( "status", "OFFLINE" );
			$this->stats->add( "description", "Crashed" );
			$this->stats->forceTimestamp( $logfiles[1] );
			$this->stats->save( "Servers" );
		}


		// Configuration variables
		$config = file_get_contents( 'assets/' . $this->config_file );
		if ( $config == false ) {
			trace( "Failed to open config file." );
			die();
		}

		$xml = xml2array( $config );

		$server = get_value_by_path( $xml, 'servers/' . $this->name . '/server' );
		$port   = get_value_by_path( $xml, 'servers/' . $this->name . '/port' );

		$this->server = $server['value'];
		$this->port   = $port['value'];


		$ipList = @exec( "ifconfig" );
		if ( ! stristr( $ipList, $this->server ) ) {

			$hostname = @exec( "hostname" );
			if ( trim( $hostname ) == "Yahweh.local" ) {
				$host = exec( "ipconfig getifaddr en1" );
			}
			else {
				$host = exec( "/sbin/ifconfig eth0 | grep 'inet addr:' | cut -d: -f2 | awk '{ print $1}'" );
			}

			// @copy("assets/servers.xml", "assets/old_servers.xml");

			$config = str_replace( $this->server, $host, $config );

			// write to XML file?
			if ( $xml = @fopen( "assets/servers.xml", "w" ) ) {
				@fwrite( $xml, $config );
				@fclose( $xml );

				$this->server = $host;
			}
		}

	}

	/**
	 * Initialize network listeners
	 */
	function connect() {
		// Create socket
		if ( ! $this->sock = socket_create( AF_INET, SOCK_STREAM, getprotobyname( "tcp" ) ) ) {
			trace( "Could not create socket." );
			die();
			//	or die("[".date('Y-m-d H:i:s')."] Could not create socket\n");
		}

		// Re-use socket
		socket_set_option( $this->sock, SOL_SOCKET, SO_REUSEADDR, 1 );

		// Bind to socket
		if ( ! socket_bind( $this->sock, $this->server, $this->port ) ) {
			trace( "Could not bind to socket." );
			die();
		}

		// Start listening
		if ( ! socket_listen( $this->sock ) ) {
			trace( "Could not set up socket listener." );
			die();
			//or die("[".date('Y-m-d H:i:s')."] Could not set up socket listener\n");
		}

		trace( "Server '" . $this->name . "' started at " . $this->server . ":" . $this->port );

		$this->stats->add( "type", $this->name );
		$this->stats->add( "status", "ONLINE" );
		$this->stats->save( "Servers" );

		if ( is_resource( $this->sock ) ) {
			while ( $this->wait_for_input() ) {
				;
			}

			$this->shutdown( -1 );
		}
	}

	/**
	 * Wait for client input to be parsed
	 */
	function wait_for_input() {
		if ( ! is_resource( $this->sock ) ) {
			return false;
		}

		$read = array();
		array_push( $read, $this->sock );
		// Setup clients listen socket for reading

		for ( $i = 0; $i < count( $this->client ); $i++ ) {
			if ( is_resource( $this->client[ $i ]['sock'] ) ) {
				array_push( $read, $this->client[ $i ]['sock'] );
			}
			else {
				$this->client_disconnect( $i );
				$i--;
			}
		}

		// Set up a blocking call to socket_select()
		$num_changed_sockets = @socket_select( $read, $write = null, $except = null, 1 );

		if ( $num_changed_sockets == 0 ) {
			return true;
		}

		// If a new connection is being made add it to the clients array
		if ( in_array( $this->sock, $read ) ) {
			for ( $new_socket = 0; $new_socket < $num_changed_sockets; $new_socket++ ) {
				$i = count( $this->client );
				if ( ( $this->client[ $i ]['sock'] = socket_accept( $this->sock ) ) < 0 ) {
					trace( "socket_accept() failed: " . socket_strerror( $this->client[ $i ]['sock'] ) );
				}
				else {
					socket_getpeername( $this->client[ $i ]['sock'], $this->client[ $i ]['ip'] );
					$this->client_connected( $i );
				}
			}
		}

		for ( $i = 0; $i < count( $this->client ); $i++ ) {

			if ( in_array( $this->client[ $i ]['sock'], $read ) ) {
				$input = @socket_read( $this->client[ $i ]['sock'], 1024 );

				if ( $input == null ) {
					$this->client_disconnect( $i );
					$i--;
					continue;
				}

				$input = $this->client[ $i ]['input'] = trim( $input );

				if ( $input == "PING" ) {
					$this->send( $i, "PONG", true );
				}
				elseif ( $input == "<policy-file-request/>" ) {
					// Security request (needed online)
					trace( "Client #$i requesting policy file" );

					$cdmp = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><cross-domain-policy xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"http://www.adobe.com/xml/schemas/PolicyFileSocket.xsd\"><allow-access-from domain=\"*\" to-ports=\"" . $this->port . "\" secure=\"false\" /></cross-domain-policy>";

					$this->send( $i, $cdmp, true );

					return true;

				}
				elseif ( $input == "TERM" ) {
					// Server termination requested
					// Require Log In!
					if ( $this->client[ $i ]['admin'] ) {
						$this->shutdown( $i );
					}

				}
				elseif ( substr( $input, 0, 11 ) == "PHPSESSION=" ) {
					$php_session                      = str_replace( "PHPSESSION=", "", $input );
					$this->client[ $i ]['PHPSESSION'] = $php_session;

					$dbPrefix = $this->db->prefix;
					// get user_id from the database
					$result = $this->db->query( "SELECT `id`, `username` FROM `global__Players` WHERE `PHPSESSION`='" . $php_session . "'" );
					if ( $user = $this->db->assoc( $result ) ) {
						$this->client[ $i ]['admin']     = ( $user['id'] == 1 );
						$this->client[ $i ]['user_id']   = $user['id'];
						$this->client[ $i ]['user_name'] = $user['username'];

						$result = $this->db->query( "SELECT `points` FROM `global__PlayerInfo` WHERE `player_id`='" . $this->client[ $i ]['user_id'] . "'" );
						if ( $user = $this->db->assoc( $result ) ) {
							$this->client[ $i ]['points'] = $user['points'];
						}

						$this->client_identified( $i, true );
					}
					else {

						if ( $this->name == "chatserver" ) {
							$this->fakeUsers++;
							$this->fakeUserIndex++;

							// if chat, make a fake user.
							$this->client[ $i ]['points']    = 0;
							$this->client[ $i ]['user_id']   = 2147483640 - $this->fakeUsers;
							$this->client[ $i ]['fake']      = true;
							$this->client[ $i ]['user_name'] = 'Gast #' . ( $this->fakeUserIndex );

							$this->client_identified( $i, true );
						}
						else {
							$this->client_identified( $i, false );
						}
					}
				}
				else {
					$this->on_input( $i );
				}

				$this->client[ $i ]['last_action_time'] = time();
				$this->client[ $i ]['input']            = '';
			}
		}

		return true;
	}

	/**
	 * Holder for on_input handling
	 *
	 * @param  int $index Client index which has inputted something
	 *
	 */

	public function on_input( $index ) {
		// holder
	}

	/**
	 * Shutdown the socket server
	 *
	 * @param  int $index Shutdown requested by client index
	 */
	public function shutdown( $index ) {
		if ( $index > -1 ) {
			$user_name = $this->client[ $index ]['user_name'];

			$this->stats->add( "type", $this->name );
			$this->stats->add( "status", "OFFLINE" );
			$this->stats->add( "description", "Manually" );
			$this->stats->save( "Servers" );
		}
		else {
			$this->stats->add( "type", $this->name );
			$this->stats->add( "status", "OFFLINE" );
			$this->stats->add( "description", "Crashed" );
			$this->stats->save( "Servers" );
		}

		// shutdown clients
		while ( count( $this->client ) > 0 ) {
			$this->client_disconnect( 0 );
		}

		// shutdown the master/listening socket
		if ( is_resource( $this->sock ) ) {
			socket_close( $this->sock );
		}

		trace( "Terminated server (requested by " . $user_name . ")" );
		// need to do a proper exit code to shutdown the 'screen' command
		exit( 0 );
	}

	/**
	 * When a client connects to the server, set defaults
	 *
	 * @param  int $index Index of the client
	 */
	function client_connected( $index ) {
		trace( "Client #$index connected" );
		$this->client[ $index ]['connect_time'] = time();
	}

	/**
	 * When a client disconnects from the server, clean up
	 *
	 * @param  int $index Index of the client
	 */
	function client_disconnect( $index ) {
		if ( is_resource( $this->client[ $index ]['sock'] ) ) {
			@socket_shutdown( $this->client[ $index ]['sock'] );
		}

		if ( is_resource( $this->client[ $index ]['sock'] ) ) {
			socket_close( $this->client[ $index ]['sock'] );
		}

		if ( $this->client[ $index ]['fake'] ) {
			$this->fakeUsers--;
		}

		//unset($this->client[$index]);
		array_splice( $this->client, $index, 1 );

		trace( "Client #$index disconnected: " . count( $this->client ) . " clients left." );
	}

	/**
	 * When a client has been identified
	 *
	 * @param  int     $index   Index of the client
	 * @param  boolean $success If the client was identified or denied
	 */
	function client_identified( $index, $success ) {
		$user_name = $this->client[ $index ]['user_name'];
		trace( "Client #$index ($user_name) identified: " . ( ( $success ) ? "yes" : "no" ) );
		$this->client[ $index ]['identified'] = $success;
	}

	/**
	 * Achievement functions:
	 */
	function set_achievement( $client_index, $identifier ) {
		$achievement_id = $this->get_achievement_id( $identifier );

		if ( $achievement_id > 0 ) {
			$user_id = $this->client[ $client_index ]['user_id'];

			// check for existing entry:
			$query = $this->db->query( "SELECT `id` FROM `achievements__Completed` WHERE  `player_id`=$user_id AND `achievement_id`=$achievement_id" );
			if ( $this->db->num_rows( $query ) == 0 ) {
				$this->db->query( "INSERT INTO `achievements__Completed` (`achievement_id`, `player_id`, `timestamp`) VALUES ($achievement_id, $user_id, UNIX_TIMESTAMP())" );

				return $achievement_id;
			}
		}

		return 0;
	}

	function get_achievement_id( $identifier ) {
		return 0;

		$query = $this->db->query( "SELECT `id` FROM `achievements__Achievements` WHERE `identifier`='$identifier'" );
		if ( $achievement = $this->db->assoc( $query ) ) {
			return intval( $achievement['id'] );
		}

		return 0;
	}

	/**
	 * Send data to a specified client
	 *
	 * @param  int     $index   Index of the client
	 * @param  String  $data    Data to send to the client
	 * @param  boolean $sendRaw Embed XML around the data or not
	 */
	function send( $index, $data, $send_raw = false ) {
		if ( $this->client[ $index ] && $this->client[ $index ]['sock'] ) {
			if ( ! is_resource( $this->client[ $index ]['sock'] ) ) {
				array_splice( $this->client, $index, 1 );
			}
			else {
				socket_write( $this->client[ $index ]['sock'], $data . chr( 0 ) );
			}
		}
	}

	/**
	 * Send data to all the connected clients
	 *
	 * @param  int $data          Data to send to the clients
	 * @param  int $exclude_index Exclude a client from recieving the data (Default: -1)
	 */
	function send_to_all( $data, $exclude_index = -1 ) {
		for ( $i = 0; $i < count( $this->client ); $i++ ) {
			if ( $exclude_index != -1 && $i == $exclude_index ) {
				continue;
			}

			/* If clients are not identified - exclude it from mass messaging */
			if ( ! $this->client[ $i ]['identified'] ) {
				continue;
			}

			$this->send( $i, $data, true );
		}
	}
}

