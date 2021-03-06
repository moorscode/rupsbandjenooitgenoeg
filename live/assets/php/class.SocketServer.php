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

	/*
	 * @var Fake (un-identified) users
	 */
	var $fakeUsers = 0;
	var $fakeUserIndex = 0;

	/**
	 * @var Client list
	 */
	var $client = array();

	/**
	 * @var Disable achievements
	 */
	var $achievements = true;

	/**
	 * @var Set the Frames Per Second the server is calculating
	 */
	var $fps = 1;

	/**
	 * @var How much data is read each cycle
	 *        It's recommended to make it bigger then then the average size of a packet
	 */
	var $socketReadLength = 4096;

	/**
	 * @var Policy file contents
	 */
	var $policy_file;

	/**
	 * Achievements parked for clients to be applied after the game
	 */
	var $queued_achievements = array();

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

	function socket_error( $message ) {
		$errorcode = socket_last_error();
		$errormsg  = socket_strerror( $errorcode );

		trace( "$message: [$errorcode] $errormsg" );
		die();
	}

	/**
	 * Initialize network listeners
	 */
	function connect() {
		// Create socket
		if ( false === ( $this->sock = @socket_create( AF_INET, SOCK_STREAM, getprotobyname( "tcp" ) ) ) ) {
			$this->socket_error( "Couldn't create socket" );
		}

		// Re-use socket
		socket_set_option( $this->sock, SOL_SOCKET, SO_REUSEADDR, 1 );

		// Bind to socket
		if ( false === @socket_bind( $this->sock, $this->server, $this->port ) ) {
			$this->socket_error( "Couldn't bind to socket" );
			die();
		}

		// Start listening
		if ( false === @socket_listen( $this->sock ) ) {
			$this->socket_error( "Couldn't set up socket listener" );
			die();
		}

		if ( ! socket_set_nonblock( $this->sock ) ) {
			trace( "Couldn't set socket non-blocking" );
		}

		trace( "Server '" . $this->name . "' started at " . $this->server . ":" . $this->port . " @ " . $this->fps . " fps" );

		$this->stats->add( "type", $this->name );
		$this->stats->add( "status", "ONLINE" );
		$this->stats->save( "Servers" );

		$this->policy_file = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><cross-domain-policy xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"http://www.adobe.com/xml/schemas/PolicyFileSocket.xsd\"><allow-access-from domain=\"*.rupsbandjenooitgenoeg.nl\" to-ports=\"" . $this->port . "\" secure=\"false\" /></cross-domain-policy>";


		while ( $this->wait_for_input() ) {
			$this->tick();
		}

		$this->shutdown( -1 );
	}

	/**
	 * Wait for client input to be parsed
	 */
	function wait_for_input() {
		if ( ! is_resource( $this->sock ) ) {
			return false;
		}

		$read  = array();
		$write = array();

		array_push( $read, $this->sock );
		array_push( $write, $this->sock );

		// Setup clients listen socket for reading and writing
		for ( $i = 0; $i < count( $this->client ); $i++ ) {
			if ( is_resource( $this->client[ $i ]['sock'] ) ) {
				// check all sockets for read
				array_push( $read, $this->client[ $i ]['sock'] );

				// only check sockets for write if we got something to write to them
				if ( count( $this->client[ $i ]['write'] ) > 0 ) {
					array_push( $write, $this->client[ $i ]['sock'] );
				}
			}
			else {
				$this->client_disconnect( $i );
				$i--;
				continue;
			}
		}

		$timeout_sec  = ( $this->fps <= 1 ) ? ( 1 / ( $this->fps ) ) : 0;
		$timeout_usec = ( $this->fps > 1 ) ? round( 1000 / $this->fps ) : null;

		// Set up a blocking call to socket_select()
		if ( $timeout_usec == null ) {
			$num_changed_sockets = socket_select( $read, $write, $except = null, $timeout_sec );
		}
		else {
			$num_changed_sockets = socket_select( $read, $write, $except = null, $timeout_sec, $timeout_usec );
		}

		if ( $num_changed_sockets == 0 ) {
			return true;
		}

		// If the MAIN socket is in the read list, somebody tries to connect:
		if ( in_array( $this->sock, $read ) ) {
			for ( $new_socket = 0; $new_socket < $num_changed_sockets; $new_socket++ ) {
				$i = count( $this->client );

				// socket_accepts tries to accept the connection:
				if ( false === ( $socket = socket_accept( $this->sock ) ) ) {
					trace( "Socket connection failed: " . socket_strerror( socket_last_error() ) );
				}
				else {
					$this->client[ $i ]          = array();
					$this->client[ $i ]['sock']  = $socket;
					$this->client[ $i ]['write'] = array();

					/* force-send policy file */
					@socket_write( $socket, $this->policy_file . chr( 0 ) );

					if ( socket_getpeername( $this->client[ $i ]['sock'], $address ) ) {
						$this->client[ $i ]['ip'] = $address;
					}

					$this->client_connected( $i );
				}
			}
		}

		for ( $i = 0; $i < count( $this->client ); $i++ ) {
			if ( in_array( $this->client[ $i ]['sock'], $read ) ) {

				$this->client[ $i ]['input'] = '';

				// read as much as we can:
				// $input = '';
				while ( '' !== ( $data = socket_read( $this->client[ $i ]['sock'], $this->socketReadLength ) ) ) {
					if ( $data == '' ) {
						$this->client[ $i ]['input'] = $this->client[ $i ]['recv'];
						$this->client[ $i ]['recv']  = '';
						break;
					}
					// $input .= $data;
					$this->client[ $i ]['recv'] .= $data;

					if ( strlen( $data ) < $this->socketReadLength ) {
						$this->client[ $i ]['input'] = $this->client[ $i ]['recv'];
						$this->client[ $i ]['recv']  = '';
						break;
					}
				}


				// on BINARY_READ (default) when an error occures
				if ( '' === $data ) {
					$this->client_disconnect( $i );
					$i--;
					continue;
				}

				// skip till we have all the data
				if ( strlen( $this->client[ $i ]['recv'] ) > 0 ) {
					continue;
				}

				$input = $this->client[ $i ]['input'] = trim( $this->client[ $i ]['input'] ); // = trim($input);
				if ( strlen( $input ) > $this->socketReadLength ) {
					trace( "Socket read > " . $this->socketReadLength . " bytes: " . $input );
				}


				if ( $this->client[ $i ]['input'] == '' ) {
					continue;
				}


				if ( $input == "<policy-file-request/>" ) {
					// Security request (needed online)
					trace( "Client #$i requesting policy file" );
					//@socket_write($socket, $this->policy_file . chr(0));
					$this->send_to_index( $i, $this->policy_file, true );
				}
				elseif ( $input == "PING" ) {
					$this->send_to_index( $i, "PONG", true );
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

					// get user_id from the database
					$result = $this->db->query( "SELECT `id`, `username` FROM `global__Players` WHERE `PHPSESSION`='" . $php_session . "'" );
					if ( $user = $this->db->assoc( $result ) ) {

						$client_index = $this->get_client_index( $user['id'] );
						$client_sock  = $this->client[ $client_index ]['sock'];

						$return_to_index = 0;

						/* Only 1 login per user is allowed! */
						if ( $client_index > -1 && $client_sock != $this->client[ $i ]['sock'] ) {
							$this->client[ $client_index ]['sock'] = $this->client[ $i ]['sock'];
							$this->client[ $i ]['sock']            = null;
							$return_to_index                       = $i;
							$i                                     = $client_index;

							trace( "Reconnecting time-out client #$client_index - disconnecting new client #$return_to_index" );
						}

						$this->client[ $i ]['admin']     = ( $user['id'] == 1 );
						$this->client[ $i ]['user_id']   = $user['id'];
						$this->client[ $i ]['user_name'] = $user['username'];

						$result = $this->db->query( "SELECT `points` FROM `global__PlayerInfo` WHERE `player_id`='" . $this->client[ $i ]['user_id'] . "'" );
						if ( $user = $this->db->assoc( $result ) ) {
							$this->client[ $i ]['points'] = $user['points'];
						}
						else {
							$this->client[ $i ]['points'] = 0;
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
			}

			// write responses to the sockets
			if ( in_array( $this->client[ $i ]['sock'], $write ) ) {

				$send = true;

				while ( $send && count( $this->client[ $i ]['write'] ) > 0 ) {
					// zlib compression?
					$data = $this->client[ $i ]['write'][0] . chr( 0 );

					if ( false === ( $data_send = socket_write( $this->client[ $i ]['sock'], $data ) ) ) {
						$this->client_disconnect( $i );
						$i--;
						$send = false;
						//trace("Write (socket #i) failed.");
					}
					else if ( $data_send == strlen( $data ) ) {                // data send ok, remove from array
						//trace("Write (socket #i): " . $data);
						array_shift( $this->client[ $i ]['write'] );
					}
					else {
						$this->client[ $i ]['write'][0] = substr( $data, $data_send );
						$send                           = false;

						//trace("Write (socket #i) [partial]: " . substr($data, $data_send));
					}
				}
			}

			if ( $return_to_index > 0 ) {
				$i = $return_to_index;
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

	protected function tick() {

	}

	protected function on_input( $index ) {
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
			socket_shutdown( $this->sock );
		}

		// close the socket if it still exists
		if ( is_resource( $this->sock ) ) {
			socket_close( $this->sock );
		}

		// log who killed the server
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
		trace( "Client #$index connected (" . $this->client[ $index ]['sock'] . ")" );
		$this->client[ $index ]['connect_time'] = time();

		$this->send_to_index( $index, "CONNECTED", true );
	}

	/**
	 * When a client disconnects from the server, clean up
	 *
	 * @param  int $index Index of the client
	 */
	function client_disconnect( $index, $forced = false ) {
		if ( is_resource( $this->client[ $index ]['sock'] ) ) {
			socket_shutdown( $this->client[ $index ]['sock'] );
		}

		if ( is_resource( $this->client[ $index ]['sock'] ) ) {
			socket_close( $this->client[ $index ]['sock'] );
		}

		$user_id = $this->client[ $index ]['user_id'];
		if ( $this->client[ $index ]['fake'] ) {
			$this->fakeUsers--;
			$user_id = -1;
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
		$user_id   = $this->client[ $index ]['user_id'];

		$msg = $success ? "identified as '$user_name' (ID: $user_id)" : "identification failed";

		trace( "Client #$index " . $msg );
		$this->client[ $index ]['identified'] = $success;
	}

	/**
	 * Achievement functions:
	 */
	function queue_achievement( $user_id, $identifier ) {
		if ( ! in_array( $this->queued_achievements, array( $user_id, $identifier ) ) ) {
			array_push( $this->queued_achievements, array( $user_id, $identifier ) );
		}
	}

	function apply_achievements( $user_id ) {
		if ( count( $this->queued_achievements ) == 0 ) {
			return;
		}

		$notified = false;
		for ( $i = 0; $i < count( $this->queued_achievements ); $i++ ) {
			if ( $this->queued_achievements[ $i ][0] == $user_id ) {
				if ( ! $notified ) {
					trace( "Applying achievements for #$user_id" );
					$notified = true;
				}

				$this->set_achievement( $this->queued_achievements[ $i ][0], $this->queued_achievements[ $i ][1] );

				array_splice( $this->queued_achievements, $i, 1 );
				$i--;
			}
		}
	}

	function set_achievement( $user_id, $identifier ) {
		if ( ! $this->achievements ) {
			return false;
		}

		$achievement_id = $this->get_achievement_id( $identifier );

		if ( $achievement_id > 0 ) {
			// check for existing entry:
			$query = $this->db->query( "SELECT `id` FROM `achievements__Completed` WHERE `player_id`='$user_id' AND `achievement_id`='$achievement_id'", true );
			if ( ! $this->db->assoc( $query ) ) {
				$this->db->query( "INSERT INTO `achievements__Completed` (`achievement_id`, `player_id`, `timestamp`) VALUES ('$achievement_id', '$user_id', UNIX_TIMESTAMP())", true );

				return true;
			}
		}

		return false;
	}

	function get_achievement_id( $identifier ) {
		if ( ! $this->achievements ) {
			return 0;
		}

		$query = $this->db->query( "SELECT `id` FROM `achievements__Achievements` WHERE `identifier`='$identifier'", true );
		if ( $achievement = $this->db->assoc( $query ) ) {
			return intval( $achievement['id'] );
		}

		return 0;
	}

	/**
	 * Get the client index from the database user id
	 */
	function get_client_index( $user_id ) {
		for ( $client_index = count( $this->client ) - 1; $client_index >= 0; $client_index-- ) {
			if ( $this->client[ $client_index ]['user_id'] == $user_id ) {
				return $client_index;
			}
		}

		return -1;
	}

	/**
	 * Send data to a specified client
	 *
	 * @param  int     $index   Index of the client
	 * @param  String  $data    Data to send to the client
	 * @param  boolean $sendRaw Embed XML around the data or not
	 */
	function send( $user_id, $data, $send_raw = false ) {
		$client_index = $this->get_client_index( $user_id );

		if ( $this->client[ $client_index ] ) {
			if ( $this->client[ $client_index ]['disconnecting'] ) {
				return;
			}

			if ( ! is_resource( $this->client[ $client_index ]['sock'] ) ) {
				$this->client_disconnect( $client_index );
			}
			else {
				array_push( $this->client[ $client_index ]['write'], $data );
				/*
				if(false === socket_write($this->client[$index]['sock'], $data . chr(0))) {
					$this->client_disconnect($index);
				}
				*/
			}
		}
	}

	function send_to_index( $client_index, $data, $send_raw = false ) {
		if ( $this->client[ $client_index ] ) {
			if ( $this->client[ $client_index ]['disconnecting'] ) {
				return;
			}

			if ( ! is_resource( $this->client[ $client_index ]['sock'] ) ) {
				$this->client_disconnect( $client_index );
			}
			else {
				array_push( $this->client[ $client_index ]['write'], $data );
			}
		}
	}

	/**
	 * Send data to all the connected clients
	 *
	 * @param  int $data          Data to send to the clients
	 * @param  int $exclude_index Exclude a client from recieving the data (Default: -1)
	 */
	function send_to_all( $data, $exclude_id = -1 ) {
		for ( $i = 0; $i < count( $this->client ); $i++ ) {
			/* If clients are not identified - exclude it from mass messaging */
			if ( ! $this->client[ $i ]['identified'] ) {
				continue;
			}

			$user_id = $this->client[ $i ]['user_id'];

			if ( $exclude_index != -1 && $user_id == $exclude_id ) {
				continue;
			}

			$this->send( $user_id, $data, true );
		}
	}
}

