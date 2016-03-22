<?php

require_once( "class.SocketServer.php" );

/**
 * Game Server
 */
class GameServer extends SocketServer {
	/**
	 * @var Games list
	 */
	var $games = array();

	/**
	 * @var Counter to make an unique new game id for each game
	 */
	var $lastgame_id = 0;

	/**
	 * @var The maximum players of a game
	 */
	var $max_game_size = 6;

	/**
	 * Constructor: Create a new game server
	 */
	function GameServer() {
		parent::SocketServer( "gameserver" );
		$this->fps = 16;
	}

	/**
	 * Custom implementation of the ON_INPUT function
	 */
	function on_input( $client_index ) {
		$client  = &$this->client[ $client_index ];
		$input   = $this->client[ $client_index ]['input'];
		$user_id = $this->client[ $client_index ]['user_id'];

		if ( $input == "EXIT" ) {
			trace( "recieved EXIT from $client_index" );
			client_disconnect( $client_index );
			continue;
		}

		$xml = xml2array( $input );

		// handle XML input:
		$what = get_value_by_path( $xml, 'a/w' );
		$what = $what['value'];

		// handle requests
		switch ( $what ) {

			case  'list': // queueing actions
				$this->send_gamelist( $user_id );
				break;

			case 'playerlist': // playerlist
				$game_id = get_value_by_path( $xml, 'a/g' );
				$game_id = intval( $game_id['value'] );

				$this->send_playerlist( $game_id, $user_id );
				break;

			case 'create': // create a new game
				$name     = get_value_by_path( $xml, 'a/n' );
				$password = get_value_by_path( $xml, 'a/p' );

				$name     = $name['value'];
				$password = $password['value'];

				$max_players = $this->max_game_size;

				$this->initialize_client( $client_index );

				$game_id = ++$lastgame_id;

				trace( "Game #$game_id: created by UID $user_id" );
				array_push( $this->games, array( "id"          => $game_id,
				                                 "name"        => $name,
				                                 "players"     => array( $user_id ),
				                                 "max_players" => $max_players,
				                                 "password"    => $password,
				                                 "queue_time"  => time()
				) );
				$game_index = $this->get_game_index( $game_id );

				$this->time_queue_start( $user_id );

				// save game to the database
				// $this->stats->add("type_id", $this->games[$game_index]['type_id']); // type of game
				$this->stats->add( "queuetime", $this->games[ $game_index ]['queue_time'] ); // creation time.
				$this->games[ $game_index ]['db_id'] = $this->stats->save( "Games" );


				// update client info to match the game
				$this->client[ $client_index ]['game_id'] = $game_id;

				// send info of created game to the user
				$this->send( $user_id, "<w>created</w><i>$game_id</i>", false, "queue" );
				$this->send_playerlist( $game_id, $user_id );

				// update the gamelist to everybody - who's not in game..?!
				$this->send_all_gamelist();
				break;

			case 'join':
				$game_id = get_value_by_path( $xml, 'a/g' );
				$game_id = intval( $game_id['value'] );

				$password = get_value_by_path( $xml, 'a/p' );
				$password = $password['value'];

				$this->initialize_client( $client_index );

				for ( $i = 0; $i < count( $this->games[ $game_index ]['players'] ); $i++ ) {
					if ( $this->games[ $game_index ]['players'][ $i ] == $user_id ) {
						array_splice( $this->games[ $game_index ]['players'], $i, 1 );
					}
				}

				$game_index  = $this->get_game_index( $game_id );
				$game_name   = $this->games[ $game_index ]['name'];
				$game_full   = ( count( $this->games[ $game_index ]['players'] ) == $this->games[ $game_index ]['max_players'] );
				$password_ok = ( $password == $this->games[ $game_index ]['password'] );

				if ( $game_index == -1 || $this->games[ $game_index ]['active'] || $game_full || ! $password_ok ) {
					if ( $game_index == -1 ) {
						$reason = "Spel bestaat niet meer!";
					}

					if ( $this->games[ $game_index ]['active'] ) {
						$reason = "Spel is al begonnen...";
					}

					if ( $game_full ) {
						$reason = "Spel is al vol!";
					}

					if ( ! $password_ok ) {
						$reason = "Verkeerd wachtwoord!";
					}

					trace( "Game #$game_id: rejected UID $user_id ($reason)" );

					// can't join anymore...
					$this->send( $user_id, "<w>joined</w><gi>0</gi><r>$reason</r>", false, "queue" );
				}
				else {
					$this->time_queue_start( $user_id );

					array_push( $this->games[ $game_index ]["players"], $user_id );

					$this->client[ $client_index ]['game_id']    = $game_id;
					$this->client[ $client_index ]['queue_time'] = time();

					$this->send( $user_id, "<w>joined</w><gi>$game_id</gi><gn>$game_name</gn>", false, "queue" );

					trace( "Game #$game_id: UID $user_id joined" );

					$this->send_playerlist( $game_id );
					$this->send_all_gamelist();
				}
				break;

			case 'leave':
				trace( "UID $user_id requested leave" );
				$this->leave_game( $user_id );
				break;

			case 'start':
				$game_id    = $client['game_id'];
				$game_index = $this->get_game_index( $game_id );

				trace( "Game #$game_id: recieved 'start' by UID $user_id" );

				// only the game owner can start:
				if ( ! $this->games[ $game_index ]['active'] && $client['user_id'] == $this->games[ $game_index ]["players"][0] ) {

					// GO!
					trace( "Game #$game_id: initializing..." );

					/*==== Achievements ====*/

					$this->queue_achievement( $user_id, "GAME_HOSTED" );

					// Get the reference ID to check with
					$reference       = $this->get_achievement_id( "REFERENCE" );
					$apply_reference = false;


					// Set game achievements
					for ( $p = 0; $p < count( $this->games[ $game_index ]['players'] ); $p++ ) {
						$player_id = $this->games[ $game_index ]['players'][ $p ];

						if ( $this->games[ $game_index ]['password'] != "" ) {
							$this->queue_achievement( $player_id, "PP_GAME" );
						}

						$this->queue_achievement( $player_id, "FIRST_TIME" );

						// Check if somebody has the reference-achievement, so we need to apply it to everybody in game
						$reference_check = $this->db->query( "SELECT `id` FROM `achievements__Completed` WHERE `player_id`='$player_id' AND `achievement_id`='$reference'" );
						$apply_reference = $apply_reference || ( $this->db->num_rows( $reference_check ) > 0 );
					}

					// apply the reference-achievement to everybody if somebody has it in game
					if ( $apply_reference ) {
						for ( $p = 0; $p < count( $this->games[ $game_index ]['players'] ); $p++ ) {
							$player_id = $this->games[ $game_index ]['players'][ $p ];
							$this->queue_achievement( $player_id, "REFERENCE" );
						}
					}

					/*---- Achievements ----*/

					// send to all in game...
					$this->send_queued_players( $game_id, "<w>startgame</w>" );

					// set game to active - joins are disabled
					$this->games[ $game_index ]['active']     = true;
					$this->games[ $game_index ]['start_time'] = time();

					// update the game list, removing the game (because its active)
					$this->send_all_gamelist();

				}
				break;

			// ingame actions
			case 'level': // game owner selected a level - means that the client is fully loaded
				$level     = get_value_by_path( $xml, 'a/n' );
				$level     = intval( $level['value'] );
				$positions = get_value_by_path( $xml, 'a/s' );
				$positions = intval( $positions['value'] );

				$game_id    = $client['game_id'];
				$game_index = $this->get_game_index( $game_id );

				$this->games[ $game_index ]['level']           = $level;
				$this->games[ $game_index ]['start_positions'] = $positions;
				$this->send_game_players( $game_id, "<w>level</w><n>$level</n>" );

				$this->client[ $client_index ]['loaded'] = true;

				$this->send_game_players( $game_id, "<w>loaded</w><i>$user_id</i>" );

				trace( "Game #$game_id: UID $user_id (owner) is loaded" );

				$positions_taken = array();

				// initialize start positions:
				for ( $p = 0; $p < count( $this->games[ $game_index ]['players'] ); $p++ ) {
					$check_client = $this->get_client_index( $this->games[ $game_index ]['players'][ $p ] );

					$pos = mt_rand( 1, $positions );
					while ( in_array( $pos, $positions_taken ) ) {
						$pos = mt_rand( 1, $positions );
					}

					$this->client[ $check_client ]['start_position'] = $pos - 1;
					array_push( $positions_taken, $pos );
				}

				trace( "Game #" . $game_id . " set level " . $level );

				$this->check_all_loaded( $game_id );
				break;

			case 'loaded': // non game-owners  must tell when they are fully loaded
				$this->client[ $client_index ]['loaded'] = true;

				$game_id    = $client['game_id'];
				$game_index = $this->get_game_index( $game_id );

				trace( "Game #$game_id: UID $user_id is loaded" );

				$this->send_game_players( $game_id, "<w>loaded</w><i>$user_id</i>" );
				$this->check_all_loaded( $game_id );
				break;

			case 'ready': // wait for all players to be ready to start the game
				$this->client[ $client_index ]['ready'] = true;

				$game_id    = $client['game_id'];
				$game_index = $this->get_game_index( $game_id );

				trace( "Game #$game_id: UID $user_id is ready" );

				$this->send_game_players( $game_id, "<w>ready</w><i>" . $client['user_id'] . "</i>" );

				$ready = 0;
				for ( $p = 0; $p < count( $this->games[ $game_index ]['players'] ); $p++ ) {
					$check_client = $this->get_client_index( $this->games[ $game_index ]['players'][ $p ] );
					$ready += ( $this->client[ $check_client ]['ready'] ) ? 1 : 0;
				}

				if ( $ready == count( $this->games[ $game_index ]['players'] ) ) {
					trace( "Game #" . $game_id . " all players are ready, sending START" );

					$this->send_game_players( $game_id, "<w>start</w>" );

					$this->time_queue_end( $game_id );
					$this->time_game_start( $game_id );
				}
				break;

			case 'shoot':
				$game_id = $client['game_id'];

				// pos, rot, speed, accellation, accellerating
				$posX     = get_value_by_path( $xml, 'a/p/x' );
				$posY     = get_value_by_path( $xml, 'a/p/y' );
				$rotation = get_value_by_path( $xml, 'a/r' ); // 0, 360
				$speed    = get_value_by_path( $xml, 'a/s' ); // initial speed (speed of the tank shooting it)
				$bid      = get_value_by_path( $xml, 'a/bid' );
				$time     = get_value_by_path( $xml, 'a/time' );

				$posX     = $posX['value'];
				$posY     = $posY['value'];
				$rotation = $rotation['value'];
				$speed    = $speed['value'];
				$bid      = $bid['value'];
				$time     = $time['value'];

				// trace("Shoot event: {$client['user_id']} shot bullet {$bid}");
				$this->client[ $client_index ]['shots_fired']++;

				// send to all players - except current sender
				$event = "<w>shoot</w><i>" . $client['user_id'] . "</i><p><x>$posX</x><y>$posY</y></p><r>$rotation</r><s>$speed</s><bid>$bid</bid><time>$time</time>";
				$this->send_game_players( $game_id, $event, $user_id );

				trace( "Game #$game_id: UID $user_id shoots" );

				break;

			case 'hit':
				$game_id = $client['game_id'];

				$bullet_x          = get_value_by_path( $xml, "a/x" );
				$bullet_y          = get_value_by_path( $xml, "a/y" );
				$bullet_identifier = get_value_by_path( $xml, "a/bi" );

				$bullet_x          = $bullet_x['value'];
				$bullet_y          = $bullet_y['value'];
				$bullet_identifier = $bullet_identifier['value'];

				$impact_time = get_value_by_path( $xml, 'a/time' );
				$impact_time = $impact_time['value'];

				list( $owner_id, $bullet_index ) = explode( "|", $bullet_identifier );

				$owner_index = $this->get_client_index( $owner_id );


				trace( "Game #$game_id: UID $user_id got hit by UID $owner_id (bullet: $bullet_index)" );

				$this->queue_achievement( $owner_id, "FIRST_HIT" );


				// update stats on who shot at who
				$this->client[ $owner_index ]['shots_hit']   = intval( $this->client[ $owner_index ]['shots_hit'] ) + 1;
				$this->client[ $owner_index ]['last_target'] = $client_index;

				// update who shot at current player the last (in case we die now...)
				$this->client[ $client_index ]['last_assailant'] = $owner_index;

				// send to all players, except vitcim
				$this->send_game_players( $game_id, "<w>hit</w><i>$user_id</i><oid>$owner_id</oid><bid>$bullet_index</bid><x>$bullet_x</x><y>$bullet_y</y><time>$impact_time</time>", $user_id );
				break;

			case 'playerinfo':
				$game_id = $client['game_id'];

				// pos, rot, speed, accellation, accellerating
				$posX         = get_value_by_path( $xml, 'a/p/x' ); // global X
				$posY         = get_value_by_path( $xml, 'a/p/y' ); // global Y
				$rotation     = get_value_by_path( $xml, 'a/rot' ); // 0, 360
				$accelerating = get_value_by_path( $xml, 'a/acc' ); // -1, 0, 1
				$speed        = get_value_by_path( $xml, 'a/spd' );
				$hp           = get_value_by_path( $xml, 'a/hp' );
				$distance     = get_value_by_path( $xml, 'a/d' );
				$trd          = get_value_by_path( $xml, 'a/trd' );
				$trtd         = get_value_by_path( $xml, 'a/trtd' ); // turret rotation target dir
				$time         = get_value_by_path( $xml, 'a/time' ); //time();

				$target_x = get_value_by_path( $xml, 'a/tar/x' );
				$target_y = get_value_by_path( $xml, 'a/tar/y' );

				$posX         = $posX['value'];
				$posY         = $posY['value'];
				$rotation     = $rotation['value'];
				$accelerating = $accelerating['value'];
				$speed        = $speed['value'];
				$hp           = $hp['value'];
				$trd          = $trd['value'];
				$trtd         = $trtd['value'];
				$time         = $time['value'];
				$distance     = $distance['value'];

				$target_x = $target_x['value'];
				$target_y = $target_y['value'];

				if ( intval( $distance ) > 0 ) {
					$this->client[ $client_index ]['distance'] = $distance;
				}

				$player_info = "<w>player</w><i>" . $user_id . "</i><p><x>" . $posX . "</x><y>" . $posY . "</y></p><tar><x>" . $target_x . "</x><y>" . $target_y . "</y></tar><rot>" . $rotation . "</rot><hp>" . $hp . "</hp><d>" . $distance . "</d><acc>" . $accelerating . "</acc><spd>" . $speed . "</spd><trd>" . $trd . "</trd><trtd>" . $trtd . "</trtd><time>" . $time . "</time>";
				$this->send_game_players( $game_id, $player_info, $user_id );

				break;

			case 'died':
				$game_id    = $client['game_id'];
				$game_index = $this->get_game_index( $game_id );

				$victim_index = $client_index;
				$victim_id    = $user_id;

				$killer_index = $this->client[ $victim_index ]['last_assailant'];
				$killer_id    = $this->client[ $killer_index ]['user_id'];

				$this->client[ $killer_index ]['kills']  = intval( $this->client[ $killer_index ]['kills'] ) + 1;
				$this->client[ $victim_index ]['deaths'] = intval( $this->client[ $victim_index ]['deaths'] ) + 1;


				trace( "Game #$game_id: UID $user_id died (by UID {$killer_id} bullet)" );

				/*==== Achievements ====*/

				$this->queue_achievement( $killer_id, "FIRST_KILL" );

				if ( intval( $this->client[ $client_index ]['shots_fired'] ) == 0 ) {
					if ( intval( $this->client[ $client_index ]['distance'] ) == 0 ) {
						$this->queue_achievement( $user_id, "AFK" );
					}
					else {
						$this->queue_achievement( $user_id, "ONE_HAND" );
					}
				}

				if ( $this->client[ $killer_index ]['kills'] == 2 ) {
					$this->queue_achievement( $killer_id, "DOUBLE_KILL" );
				}

				if ( $this->client[ $killer_index ]['kills'] == 3 ) {
					$this->queue_achievement( $killer_id, "MULTI_KILL" );
				}

				if ( $this->client[ $killer_index ]['kills'] == 4 ) {
					$this->queue_achievement( $killer_id, "GODLIKE" );
				}
				/*---- Achievements ----*/


				$killer_points = $this->client[ $killer_index ]['points'];
				$killer_rank   = floor( sqrt( $killer_points / 20 ) );

				$victim_points = $this->client[ $victim_index ]['points'];
				$victim_rank   = floor( sqrt( $victim_points / 20 ) );


				$rank_diff           = $victim_rank - $killer_rank;
				$victim_points_delta = 0;

				if ( $rank_diff >= 0 ) {                                            // killer is lower ranked
					$killer_points_delta = ( 5 + ( $rank_diff * 2 ) );
					$victim_points_delta = 0 - ( 2 + ( $rank_diff * 2 ) );
				}
				else {                                                            // killer is higher ranked
					$killer_points_delta = max( 1, 5 + $rank_diff );        // rank_diff is < 0, so max 4 points, min 1 point
				}

				$this->client[ $killer_index ]['points_delta'] = intval( $this->client[ $killer_index ]['points_delta'] ) + $killer_points_delta;
				$killer_points += $killer_points_delta;
				$killer_points                           = min( 20 * pow( 10, 2 ), $killer_points ); // dont go above rank 10 (2000 points)
				$this->client[ $killer_index ]['points'] = $killer_points;

				$this->client[ $victim_index ]['points_delta'] = intval( $this->client[ $victim_index ]['points_delta'] ) + $victim_points_delta;
				$victim_points += $victim_points_delta;
				$victim_points                           = max( 0, $victim_points );                    // don't go below 0
				$this->client[ $victim_index ]['points'] = $victim_points;


				$killer_rank_new = floor( sqrt( $killer_points / 20 ) );
				if ( $killer_rank != $killer_rank_new ) {
					// send updated rank
					$this->send_game_players( $game_id, "<w>rank</w><i>" . $killer_id . "</i><r>" . $killer_rank_new . "</r>" );

					// update stats for new rank
					$this->stats->add( "player_id", $killer_id );
					$this->stats->add( "old_rank", $killer_rank );
					$this->stats->add( "new_rank", $killer_rank_new );
					$this->stats->save( "RankChange" );
				}


				$victim_rank_new = floor( sqrt( $victim_points / 20 ) );
				if ( $victim_rank != $victim_rank_new ) {
					// send updated rank
					$this->send_game_players( $game_id, "<w>rank</w><i>" . $victim_id . "</i><r>" . $victim_rank_new . "</r>" );

					// update stats
					$this->stats->add( "player_id", $victim_id );
					$this->stats->add( "old_rank", $victim_rank );
					$this->stats->add( "new_rank", $victim_rank_new );
					$this->stats->save( "RankChange" );
				}


				/* Save stats to db */
				$this->stats->add( "victim_id", $victim_id );
				$this->stats->add( "killer_id", $killer_id );
				$this->stats->add( "game_id", $this->games[ $game_index ]['db_id'] );
				$this->stats->save( "PlayerKills" );


				$this->client[ $client_index ]['dead'] = true;
				$this->send_game_players( $game_id, "<w>died</w><i>$user_id</i>", $user_id );
				$this->check_for_winner( $game_id );

				break;
		}
	}


	/**
	 * Custom connection function
	 */
	function client_connected( $index ) {
		parent::client_connected( $index );
	}

	/**
	 * Custom disconnection clean-up
	 */
	function client_disconnect( $index, $forced = false ) {
		$user_id = intval( $this->client[ $index ]['user_id'] );
		if ( $user_id > 0 ) {
			if ( ! $forced ) {
				$this->leave_game( $user_id );                    // leave potentially joined games

				$this->time_queue_end( 0, $user_id );
				$this->time_game_end( 0, $user_id );
			}

			// update points to the database
			$points = intval( $this->client[ $index ]['points'] );
			$this->db->query( "UPDATE `global__PlayerInfo` SET `points`='$points' WHERE `player_id`='$user_id'", true );
		}

		parent::client_disconnect( $index );
	}

	/**
	 * Initialize Client
	 *
	 * Reset all potentially saved variables
	 *
	 * Only called when entering a Queue
	 */
	function initialize_client( $client_index ) {
		unset( $this->client[ $client_index ]['distance'] );
		unset( $this->client[ $client_index ]['shots_fired'] );
		unset( $this->client[ $client_index ]['shots_hit'] );
		unset( $this->client[ $client_index ]['points_delta'] );
		unset( $this->client[ $client_index ]['kills'] );
		unset( $this->client[ $client_index ]['deaths'] );

		unset( $this->client[ $client_index ]['game_id'] );

		unset( $this->client[ $client_index ]['last_assailant'] );
		unset( $this->client[ $client_index ]['last_target'] );

		unset( $this->client[ $client_index ]['start_position'] );

		unset( $this->client[ $client_index ]['dead'] );
		unset( $this->client[ $client_index ]['ready'] );
		unset( $this->client[ $client_index ]['loaded'] );
	}

	/**
	 * Client identified
	 * @var $succes        Boolean
	 */
	function client_identified( $index, $success ) {
		parent::client_identified( $index, $success );

		$user_id   = $this->client[ $index ]['user_id'];
		$user_name = $this->client[ $index ]['user_name'];

		$xml = "<w>identified</w><i>" . $user_id . "</i><n>" . $user_name . "</n><success>" . $this->client[ $index ]['identified'] . "</success>";
		$this->send( $user_id, $xml, false, "queue" );

		$this->send_gamelist( $user_id );
	}

	/**
	 * Leave the game the client is in or queued in
	 */
	function leave_game( $user_id ) {
		for ( $i = 0; $i < count( $this->games ); $i++ ) {
			$game_id = $this->games[ $i ]['id'];
			if ( $this->client_in_game( $game_id, $user_id ) ) {
				$this->remove_from_game( $game_id, $user_id );
			}
		}

		$client_index = $this->get_client_index( $user_id );
		if ( $client_index > -1 ) {
			// update the game list (for player-count)
			$this->send_all_gamelist();
			unset( $this->client[ $client_index ]['game_id'] );
		}
	}

	/**
	 * Remove player from the game because of a disconnect or quit
	 */
	function remove_from_game( $game_id, $user_id ) {
		$client_index = $this->get_client_index( $user_id );
		$game_index   = $this->get_game_index( $game_id );

		if ( $game_index == -1 || $client_index == -1 ) {
			return;
		}

		for ( $p = 0; $p < count( $this->games[ $game_index ]["players"] ); $p++ ) {
			if ( $this->games[ $game_index ]["players"][ $p ] == $user_id ) {

				$this->time_game_end( $game_id, $user_id );
				array_splice( $this->games[ $game_index ]["players"], $p, 1 );

				$players_left = count( $this->games[ $game_index ]["players"] );

				trace( "Game #$game_id: UID $user_id removed ($players_left players left)" );

				if ( $p == 0 && $players_left > 0 && ! $this->games[ $game_index ]['active'] ) {
					// the creater left the game
					// send next in line the command to control the game
					$new_leader = "<w>owner</w><g>$game_id</g><i>" . $this->games[ $game_index ]["players"][0] . "</i>";
				}

				if ( $game['active'] ) {
					// TODO: update Stats to database!
					$this->save_player_stats( $user_id, ( $players_left == 0 ) );

					// make all other players remove the player
					$this->send_game_players( $game_id, "<w>remove</w><i>" . $user_id . "</i>", $user_id );
				}
				else {
					$this->time_queue_end( 0, $user_id );
					$this->send_playerlist( $game_id );
				}
			}
		}

		$this->check_for_winner( $game_id );

		unset( $this->client[ client_index ]['game_id'] );

		if ( $this->games[ $game_index ]['active'] ) {
			if ( count( $this->games[ $game_index ]["players"] ) <= 1 ) {
				$this->close_game( $game_id );

				return;
			}
		}
		else {
			if ( count( $this->games[ $game_index ]["players"] ) <= 0 ) {
				$this->close_game( $game_id );

				return;
			}
		}

		if ( ! empty( $new_leader ) ) {
			trace( "Game #$game_id: new leader UID $user_id" );
			$this->send_queued_players( $game_id, $new_leader );
		}
	}

	/**
	 * Save the game statistics to the database
	 */
	function close_game( $game_id ) {
		//$game_id = $this->games[$game_index]['id'];
		$game_index = $this->get_game_index( $game_id );

		// add the end time to the database
		$this->time_game_end( $game_id );

		// remove the game from the list
		trace( "Game #$game_id: removed" );
		array_splice( $this->games, $game_index, 1 );

		$this->send_all_gamelist();
	}

	/**
	 * Check for all players to have loaded the game
	 */
	function check_all_loaded( $game_id ) {
		$game_index = $this->get_game_index( $game_id );

		if ( $this->games[ $game_index ]['startpos_sent'] ) {
			return;
		}

		$positions = "";
		$loaded    = true;

		// check for all players in specified game if they are ready...
		for ( $p = 0; $p < count( $this->games[ $game_index ]['players'] ); $p++ ) {

			$client_id    = $this->games[ $game_index ]['players'][ $p ];
			$client_index = $this->get_client_index( $client_id );

			$loaded      = $loaded && $this->client[ $client_index ]['loaded'];
			$player_rank = floor( sqrt( $this->client[ $client_index ]['points'] / 20 ) );

			$positions .= "<p><i>$client_id</i><pos>" . $this->client[ $client_index ]['start_position'] . "</pos><rank>$player_rank</rank></p>";
		}

		// then send all startpostions at once
		if ( $loaded ) {
			trace( "Game #$game_id: all loaded" );
			trace( "Game #$game_id: sending start positions" );

			$this->games[ $game_index ]['startpos_sent'] = true;

			$level = $this->games[ $game_index ]['level'];
			$this->send_game_players( $game_id, "<w>startpos</w><l>$level</l><ps>" . $positions . "</ps>" );
		}
	}

	/**
	 * Check the gme if a winner is forced by default
	 */
	function check_for_winner( $game_id ) {
		$game_index = $this->get_game_index( $game_id );
		$game       = &$this->games[ $game_index ];

		// only applies to active games...
		if ( ! $game['active'] ) {
			return;
		}

		// set pre-values
		$dead         = 0;
		$winner_id    = -1;
		$winner_index = -1;

		// see how many are still alive..
		for ( $p = 0; $p < count( $game['players'] ); $p++ ) {
			$client_index = $this->get_client_index( $game['players'][ $p ] );

			if ( $this->client[ $client_index ]['dead'] || $client_index == -1 ) {
				$dead++;
			}
			else {
				$winner_id    = $game['players'][ $p ];
				$winner_index = $client_index;
			}
		}

		// if all are dead but 1:
		if ( $dead == count( $game['players'] ) - 1 ) {
			// finish the game for all players, send winner
			// save each players stats:
			for ( $p = 0; $p < count( $game['players'] ); $p++ ) {
				// $client_index = $this->get_client_index();
				$this->save_player_stats( $game['players'][ $p ], ( $game['players'][ $p ] == $winner_id ) );
			}

			if ( intval( $winner_id ) > 0 ) {
				/*==== Achievements ====*/
				$this->queue_achievement( $winner_id, "GAME_WON" );
				/*---- Achievements ----*/

				$this->send( $winner_id, "<w>finish</w><i>" . $winner_id . "</i><n>" . $this->client[ $winner_index ]['user_name'] . "</n>" );
			}
		}
	}

	/**
	 * Send the game list to a specific client
	 */
	function send_gamelist( $user_id ) {
		$gamelist = "<w>list</w><gs>";

		for ( $g = 0; $g < count( $this->games ); $g++ ) {
			$game = $this->games[ $g ];

			if ( intval( $this->games[ $g ]['id'] ) > 0 ) {
				if ( ! $game['active'] ) {
					$game_has_password = ( $game['password'] == "" ) ? 0 : 1;
					$gamelist .= "<g><i>" . $game['id'] . "</i><a>" . ( ( $game['active'] ) ? 1 : 0 ) . "</a><n>" . $game['name'] . "</n><pc>" . count( $game['players'] ) . "</pc><mp>" . $game['max_players'] . "</mp><pp>" . $game_has_password . "</pp></g>";
				}
			}
			else {
				array_splice( $this->games, $g, 1 );
				$g--;
				continue;
			}
		}
		$gamelist .= "</gs>";

		$this->send( $user_id, $gamelist, false, "queue" );
	}

	/**
	 * Send the gamelist to everbody not in an active game
	 */
	function send_all_gamelist() {
		trace( 'Sending gamelist to all' );

		for ( $c = 0; $c < count( $this->client ); $c++ ) {
			if ( isset( $this->client[ $c ]['game_id'] ) ) {
				$game_index = $this->get_game_index( $this->client[ $c ]['game_id'] );

				if ( $game_index > -1 && $this->games[ $game_index ]['active'] ) {
					continue;
				}
			}

			$this->send_gamelist( $this->client[ $c ]['user_id'] );
		}
	}

	/**
	 * Send an updated playerlist for a game queue to everybody not in an active game
	 */
	function send_playerlist( $game_id, $send_to_user = -1 ) {
		$game_index = $this->get_game_index( $game_id );
		$game       = &$this->games[ $game_index ];

		// only for games still in queue
		if ( $game['active'] ) {
			return;
		}

		$max_players = $game['max_players'];

		// create playerlist for this game:
		$playerlist = "<w>playerlist</w><gi>" . $game_id . "</gi><mp>" . $max_players . "</mp><ps>";

		for ( $p = 0; $p < count( $game["players"] ); $p++ ) {
			$client_index = $this->get_client_index( $game["players"][ $p ] );

			$user_id   = $this->client[ $client_index ]['user_id'];
			$user_name = $this->client[ $client_index ]['user_name'];
			$user_rank = floor( sqrt( $this->client[ $client_index ]['points'] / 20 ) );

			$playerlist .= "<p><i>$user_id</i><n>$user_name</n><r>$user_rank</r></p>";
		}

		$playerlist .= "</ps>";

		if ( $send_to_user == -1 ) {
			//trace("Sending playerlist to all players in game #" . $games[$game_index]['id']);
			$this->send_queued_players( $game['id'], $playerlist );
		}
		else {
			//trace("Sending playerlist for game " . $games[$game_index]['id'] . " to client #" . $sendTo);
			$this->send( $send_to_user, $playerlist, false, "queue" );
		}
	}

	/**
	 * Check if the client is in a specified game
	 */
	function client_in_game( $game_id, $user_id ) {
		$game_index = $this->get_game_index( $game_id );
		for ( $p = 0; $p < count( $this->games[ $game_index ]["players"] ); $p++ ) {
			if ( $this->games[ $game_index ]["players"][ $p ] == $user_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save player statistics
	 */
	function save_player_stats( $user_id, $winner = false ) {

		trace( "UID $user_id: Saving stats" );

		$client_index = $this->get_client_index( $user_id );
		$client       = $this->client[ $client_index ];

		$game_id    = $client['game_id'];
		$game_index = $this->get_game_index( $game_id );

		$db_id = $this->games[ $game_index ]['db_id'];

		$distance     = intval( $client['distance'] );
		$shots_fired  = intval( $client['shots_fired'] );
		$shots_hit    = intval( $client['shots_hit'] );
		$points_delta = intval( $client['points_delta'] );
		$points       = intval( $client['points'] );

		$kills  = intval( $client['kills'] );
		$deaths = intval( $client['deaths'] );

		// UPDATE if the game_id + player_id combination exists; else INSERT
		$this->stats->updateIf( "game_id", $db_id );
		$this->stats->updateIf( "player_id", $user_id );

		$this->stats->add( "game_id", $db_id );
		$this->stats->add( "player_id", $user_id );
		$this->stats->add( "distance", $distance );
		$this->stats->add( "shots_fired", $shots_fired );
		$this->stats->add( "shots_hit", $shots_hit );
		$this->stats->add( "points_delta", $points_delta );
		$this->stats->add( "kills", $kills );
		$this->stats->add( "deaths", $deaths );
		$this->stats->add( "won", ( ( $winner ) ? 1 : 0 ) );
		$this->stats->add( "gametime", $gametime );

		$this->stats->save( "PlayerGame" );

		/**
		 * Update top points
		 */
		$top_points = $points;
		$query      = $this->db->query( "SELECT `top_points` FROM  `global__PlayerInfo` WHERE `player_id`='$user_id'" );
		if ( $info = $this->db->assoc( $query ) ) {
			$top_points = max( intval( $info['top_points'] ), $points );
		}
		else {
			$this->db->query( "INSERT INTO `global__PlayerInfo` (`player_id`) VALUES ('$user_id')" );
		}

		/**
		 * Update player statistics to the Info table
		 */
		$this->db->query( "UPDATE `global__PlayerInfo` SET `top_points`='$top_points', `points`='$points', `wins`=`wins`+" . ( ( $winner ) ? 1 : 0 ) . ", `kills`=`kills`+$kills, `deaths`=`deaths`+$deaths, `distance`=`distance`+$distance, `shots_fired`=`shots_fired`+$shots_fired, `shots_hit`=`shots_hit`+$shots_hit WHERE `player_id`='$user_id'" );

		// apply achievements got this game
		$this->apply_achievements( $user_id );
	}

	/**
	 * Statistics: Save the time queued for a game
	 */
	function time_queue_start( $user_id ) {
		$client_index                                = $this->get_client_index( $user_id );
		$this->client[ $client_index ]['queue_time'] = time();
	}

	/**
	 * End queue time
	 */
	function time_queue_end( $game_id, $user_id = -1 ) {
		$time = time();

		$client_index = $this->get_client_index( $user_id );

		if ( $client_index > -1 ) {
			if ( intval( $this->client[ $client_index ]['queue_time'] ) > 0 ) {
				$duration = $time - $this->client[ $client_index ]['queue_time'];

				$this->stats->add( "player_id", $user_id );
				$this->stats->add( "time_type", "QUEUE" );
				$this->stats->add( "time_length", $duration );
				$this->stats->save( "Time" );

				$this->db->query( "UPDATE `global__PlayerInfo` SET `queuetime`=`queuetime`+$duration WHERE `player_id`=" . $user_id );

				$this->client[ $client_index ]['queue_time'] = 0;
			}
		}
		else {
			$game_index = $this->get_game_index( $game_id );

			for ( $p = 0; $p < count( $this->games[ $game_index ]["players"] ); $p++ ) {
				$this->time_queue_end( $game_id, $this->games[ $game_index ]["players"][ $p ] );
			}
		}
	}

	/**
	 * Statistics: Set the start time for a game
	 */
	function time_game_start( $game_id ) {
		$game_index = $this->get_game_index( $game_id );
		$game       = $this->games[ $game_index ];

		$start = time();

		$this->games[ $game_index ]['starttime'] = $start;

		for ( $p = 0; $p < count( $game["players"] ); $p++ ) {
			$client_index                               = $this->get_client_index( $game["players"][ $p ] );
			$this->client[ $client_index ]['game_time'] = $start;
		}

		$this->stats->updateIf( "id", $this->games[ $game_index ]['db_id'] );
		// $this->stats->add("type_id", $this->games[$game_index]['type_id']);
		$this->stats->add( "starttime", $start );
		$this->stats->save( "Games" );
	}

	/**
	 * Statistics: Save the total game time for this client
	 */
	function time_game_end( $game_id, $user_id = -1 ) {
		$time = time();

		$client_index = $this->get_client_index( $user_id );

		if ( $client_index > -1 ) {
			if ( intval( $this->client[ $client_index ]['game_time'] ) > 0 ) {

				$duration = intval( $time - $this->client[ $client_index ]['game_time'] );

				$this->stats->add( "player_id", $user_id );
				$this->stats->add( "time_type", "GAME" );
				$this->stats->add( "time_length", $duration );
				$this->stats->save( "Time" );

				$this->db->query( "UPDATE `global__PlayerInfo` SET `playingtime`=`playingtime`+$duration WHERE `player_id`=" . $user_id );

				$this->client[ $client_index ]['game_time'] = 0;
			}
		}
		else {
			$game_index = $this->get_game_index( $game_id );
			$game       = $this->games[ $game_index ];

			for ( $p = 0; $p < count( $game["players"] ); $p++ ) {
				$this->time_game_end( $game_id, $game["players"][ $p ] );
			}

			$this->stats->updateIf( "id", $this->games[ $game_index ]['db_id'] );
			$this->stats->add( "endtime", time() );
			$this->stats->save( "Games" );
		}
	}

	/**
	 * Send to all players in a specified game
	 */
	function send_game_players( $game_id, $xml, $__exclude = -1 ) {
		$this->send_players( $game_id, $xml, $__exclude, "game" );
	}

	/**
	 * Send to all players queued in a specified game
	 */
	function send_queued_players( $game_id, $xml, $__exclude = -1 ) {
		$this->send_players( $game_id, $xml, $__exclude, "queue" );
	}

	/**
	 * Send to a specified group of players
	 */
	function send_players( $game_id, $xml, $__exclude, $type ) {
		$game_index = $this->get_game_index( $game_id );
		$game       = $this->games[ $game_index ];

		if ( count( $game["players"] ) == 0 ) {
			return;
		}

		for ( $p = 0; $p < count( $game["players"] ); $p++ ) {
			$user_id = $game["players"][ $p ];

			if ( $__exclude > -1 && $__exclude == $user_id ) {
				continue;
			}
			$this->send( $user_id, $xml, false, $type );
		}
	}

	/**
	 * Core sending XML function
	 */
	function send( $user_id, $xml, $send_raw = false, $type = "game" ) {
		if ( $send_raw ) {
			parent::send( $user_id, $xml, true );
		}
		else {
			parent::send( $user_id, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>" . $type . "</t>" . $xml . "</s>" );
		}
	}

	/**
	 * Send data to all users
	 */
	function send_to_all( $xml, $type = "game" ) {
		parent::send_to_all( "<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>" . $type . "</t>" . $xml . "</s>" );
	}

	/**
	 * Get the game index for the specified game
	 */
	function get_game_index( $game_id ) {
		if ( empty( $game_id ) ) {
			return -1;
		}

		for ( $game_index = 0; $game_index < count( $this->games ); $game_index++ ) {
			if ( $this->games[ $game_index ]['id'] == $game_id ) {
				return $game_index;
			}
		}

		return -1;
	}
}

