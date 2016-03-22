<?php

require_once("class.SocketServer.php");

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
	  * Create a new game server
	  */
	function GameServer() {
		parent::SocketServer("gameserver");
	}
	
	/**
	  * Custom implementation of the ON_INPUT function
	  */
   function on_input($client_index) {
		$client = $this->client[$client_index];
		$input = $client['input'];
		
		if($input == "EXIT") {
			client_disconnect($client_index);
			continue;
		}
		
		$xml = xml2array($input);
		
		// handle chat input:
		$what = get_value_by_path($xml, 'a/w');
		$what = $what['value'];
		
		// request user list:
		switch($what) {
			// queueing actions
			case 'list':
				$this->send_gamelist($client_index);
				break;
			
			case 'playerlist':
				$game_id = get_value_by_path($xml, 'a/g');
				$game_id = intval($game_id['value']);
				
				$this->send_playerlist($this->get_game_index($game_id), $client_index);
				
				break;
			
			case 'create':
				$name 		 = get_value_by_path($xml, 'a/n');
				$max_players = get_value_by_path($xml, 'a/m');
				$password 	 = get_value_by_path($xml, 'a/p');
				$type_id 	 = get_value_by_path($xml, 'a/t');
				
				$name 		 = $name['value'];
				$password 	 = $password['value'];
				$type_id 	 = intval($type_id['value']);
				$max_players = intval($max_players['value']);
				
				$max_players = ($max_players < 2)?2:$max_players;
				
				$this->initialize_client($client_index);
				
				$game_id = ++$lastgame_id;
				
				trace("Created game #" . $game_id);
				array_push($this->games, array("id"=>$game_id, "name"=>$name, "players"=>array($client['user_id']), "max_players"=>$max_players, "password"=>$password, "type_id"=>$type_id));
				$game_index = $this->get_game_index($game_id);
				
				// save game to the db
				$this->stats->add("type_id", $this->games[$game_index]['type_id']); // type of game
				$this->stats->add("queuetime", time()); // creation time.
				$this->games[$game_index]['db_id'] = $this->stats->save("Games");
				
				// update client info to match the game
				$this->client[$client_index]['game_id'] = $game_id;
				
				// send info of created game to the user
				$this->send($client_index, "<w>created</w><i>$game_id</i>", false, "queue");
				$this->send_playerlist($game_index, $client_index);
				
				// update the gamelist to everybody - who's not in game..?!
				$this->send_all_gamelist();
				break;
			
			case 'join':
				$game_id = get_value_by_path($xml, 'a/g');
				$game_id = intval($game_id['value']);
				
				$password = get_value_by_path($xml, 'a/p');
				$password = $password['value'];
				
				$this->initialize_client($client_index);
				
				$game_index 	= $this->get_game_index($game_id);
				$game_name		= $this->games[$game_index]['name'];
				$game_full 		= (count($this->games[$game_index]['players']) == $this->games[$game_index]['max_players']);
				$password_ok 	= ($password == $this->games[$game_index]['password']);
				
				if($game_index == -1 || $this->games[$game_index]['active'] || $game_full || !$password_ok) {
					if($game_index == -1) {
						$reason = "Spel bestaat niet meer!";
					}
					
					if($this->games[$game_index]['active']) {
						$reason = "Spel is al begonnen...";	
					}
					
					if($game_full) {
						$reason = "Spel is al vol!";	
					}
					
					if(!$password_ok) {
						$reason = "Verkeerd wachtwoord!";
					}
					
					// can't join anymore...
					$this->send($client_index, "<w>joined</w><gi>0</gi><r>$reason</r>", false, "queue");
				} else {
					
					array_push($this->games[$game_index]["players"], $client['user_id']);
					
					$this->client[$client_index]['game_id'] = $game_id;
					$this->client[$client_index]['queue_time'] = time();
					
					$this->send($client_index, "<w>joined</w><gi>$game_id</gi><gn>$game_name</gn>", false, "queue");
					
					$this->send_playerlist($game_index);
					$this->send_all_gamelist();
				}
				break;
				
			case 'leave':
				$this->leave_game($client_index);
				
				break;
				
			case 'start':
				$game_id 	= $client['game_id'];
				$game_index = $this->get_game_index($game_id);
				
				// only the game owner can start:
				if(!$this->games[$game_index]['active'] && $client['user_id'] == $this->games[$game_index]["players"][0]) {
					
					// GO!
					trace("Initializing game #" . $game_id);
					
					/*
					$achievement_id = $this->set_achievement($client_index, "GAME_HOSTED");
					if($achievement_id > 0) {
						$this->send_to_chat($client_index, "ach$achievement_id");
					}
					
					$reference = get_achievement_id("REFERENCE");
					$applied_reference = false;
					
					for($p = 0; $p < count($this->games[$game_index]['players']); $p++) {
						$player_index = $this->get_client_index($this->games[$game_index]['players'][$p]);
						
						if($this->games[$game_index]['password'] != "") {
							$achievement_id = $this->set_achievement($player_index, "PP_GAME");
							if($achievement_id > 0) {
								$this->send_to_chat($player_index, "ach$achievement_id");
							}
						}
						
						$achievement_id = $this->set_achievement($player_index, "FIRST_TIME");
						if($achievement_id > 0) {
							$this->send_to_chat($player_index, "ach$achievement_id");
						}
						
						
						// REFERENCE achievement
						if(!$applied_reference) {
							$player_id = $this->games[$game_index]['players'][$p];
							
							$test = $this->db->query("SELECT `id` FROM `achievements__Completed` WHERE `player_id`='$player_id' AND `achievement_id`='$reference'");
							if($this->db->num_rows($test) > 0) {
								for($p2 = 0; $p2 < count($this->games[$game_index]['players']); $p2++) {
									if($p2 != $p) {
										$pid = $this->get_client_index($this->games[$game_index]['players'][$p2]);
										
										$achievement_id = $this->set_achievement($pid, "REFERENCE");
										if($achievement_id > 0) {
											$this->send_to_chat($pid, "ach$achievement_id");
										}
									}
								}
							}
						}
					}
					*/
					
					// send to all in game...
					$this->send_queued_players($game_id, "<w>startgame</w><st>".microtime(true)."</st>");
					
					// set game to active - joins are disabled
					$this->games[$game_index]['active'] = true;
					
					// update the game list, removing the game (because its active)
					$this->send_all_gamelist();
					
				}
				break;
			
			// ingame actions
			case 'level':
				$level 		= get_value_by_path($xml, 'a/n');
				$level 		= intval($level['value']);
				$positions 	= get_value_by_path($xml, 'a/s');
				$positions 	= intval($positions['value']);
				
				$game_id 	= $client['game_id'];
				$game_index	= $this->get_game_index($game_id);
				
				$this->games[$game_index]['level'] = $level;
				$this->games[$game_index]['start_positions'] = $positions;
				$this->send_game_players($game_id, "<w>level</w><n>$level</n>");
				
				$this->client[$client_index]['loaded'] = true;
				
				
				$positions_taken = array();
				
				// initialize start positions:
				for($p = 0; $p < count($this->games[$game_index]['players']); $p++) {
					$check_client = $this->get_client_index($this->games[$game_index]['players'][$p]);
					
					$pos = mt_rand(1, $positions);
					while(in_array($pos, $positions_taken)) {
						$pos = mt_rand(1, $positions);
					}
					
					$this->client[$check_client]['start_position'] = $pos - 1;
					array_push($positions_taken, $pos);
				}
				
				trace("Game #".$game_id." set level #".$level);
				
				$this->check_all_loaded($game_index);
				break;
			
			case 'loaded':
				$this->client[$client_index]['loaded'] = true;
				
				$game_id 	= $client['game_id'];
				$game_index	= $this->get_game_index($game_id);
				
				$this->check_all_loaded($game_index);
				break;
			
			/*
			case 'startpos':
				$game_id 	= $client['game_id'];
				$game_index = $this->get_game_index($game_id);
				
				$num_positions = intval($this->games[$game_index]['start_positions']);
				
				trace("Game #" . $game_index . " got startpos request from Client #" . $client_index);
				
				// list all positions already taken
				$positions_taken = array();
				
				for($p = 0; $p < count($this->games[$game_index]['players']); $p++) {
					$check_client = $this->get_client_index($this->games[$game_index]['players'][$p]);
					
					if($check_client != $client_index) {
						if(isset($this->client[$check_client]['start_position'])) {
							array_push($positions_taken, intval($this->client[$check_client]['start_position']));
						}
					}
				}
				
				$start_position = mt_rand(0, $num_positions-1);
				
				if(count($positions_taken) > 0) {
					if(count($positions_taken) == $num_positions) {
						trace("Start Position: All positions were already given!");
						$start_position = -1;
					} else {
						while(in_array($start_position, $positions_taken)) {
							$start_position = mt_rand(0, $num_positions-1);
						}
					}
				}
				
				trace("Game #{$game_index}; Player #{$client_index} (uid: {$client['user_id']}) got start position: $start_position");
				
				$user_id = $client['user_id'];
				$this->client[$client_index]['start_position'] = $start_position;
				$level = $this->games[$game_index]['level'];
				
				$this->send_game_players($game_id, "<w>startpos</w><l>$level</l><i>$user_id</i><pos>$start_position</pos>");
				break;
			*/
			
			case 'ready':
				$this->client[$client_index]['ready'] = true;
				
				$game_id = $client['game_id'];
				$game_index = $this->get_game_index($game_id);
				
				$player_rank = floor(sqrt($this->client[$client_index]['points'] / 20));
				
				$this->send_game_players($game_id, "<w>ready</w><i>".$client['user_id']."</i><r>".$player_rank."</r>");
				
				$ready = 0;
				for($p = 0; $p < count($this->games[$game_index]['players']); $p++) {
					$check_client = $this->get_client_index($this->games[$game_index]['players'][$p]);
					$ready += ($this->client[$check_client]['ready'])?1:0;
				}
				
				if($ready == count($this->games[$game_index]['players'])) {
					trace("Game #" . $game_id . " all players are ready, sending START");
					
					$this->send_game_players($game_id, "<w>start</w>");
					$this->time_queue_end($game_id);
					$this->time_game_start($game_id);
				}
				break;
			
			case 'shoot':
				$game_id = $client['game_id'];
				
				// pos, rot, speed, accellation, accellerating
				$posX 	 = get_value_by_path($xml, 'a/p/x');
				$posY 	 = get_value_by_path($xml, 'a/p/y');
				$rotation = get_value_by_path($xml, 'a/r'); // 0, 360
				$speed 	 = get_value_by_path($xml, 'a/s'); // initial speed (speed of the tank shooting it)
				$bid		 = get_value_by_path($xml, 'a/bid');
				$time		 = get_value_by_path($xml, 'a/time');
				
				$posX 	 = $posX['value'];
				$posY 	 = $posY['value'];
				$rotation = $rotation['value'];
				$speed 	 = $speed['value'];
				$bid		 = intval($bid['value']);
				
				$time		 = $time['value'];
				
				//trace("Shoot event: {$client['user_id']} shot bullet {$bid}.");
				
				$event = "<w>shoot</w><i>".$client['user_id']."</i><p><x>$posX</x><y>$posY</y></p><r>$rotation</r><s>$speed</s><bid>$bid</bid><time>$time</time><st>".microtime(true)."</st>";
				$this->client[$client_index]['shots_fired']++;
				
				// send to all players - except current.
				$this->send_game_players($game_id, $event, $client_index);
				break;
				
			case 'hit':
				$game_id = $client['game_id'];
				$user_id = $client['user_id'];
				
				$bullet_x 			 = get_value_by_path($xml, "a/x");
				$bullet_y 			 = get_value_by_path($xml, "a/y");
				$bullet_identifier = get_value_by_path($xml, "a/bi");
				
				$bullet_x 			 = $bullet_x['value'];
				$bullet_y			 = $bullet_y['value'];
				$bullet_identifier = $bullet_identifier['value'];
				
				$impact_time		 = get_value_by_path($xml, 'a/time');
				$impact_time		 = $impact_time['value'];
				
				list($owner_id, $bullet_index) = explode("|", $bullet_identifier);
				
				$owner_index = $this->get_client_index($owner_id);
				
				$achievement_id = $this->set_achievement($client_index, "FIRST_HIT");
				if($achievement_id > 0) {
					$this->send_to_chat($owner_index, "ach$achievement_id");
				}
				
				// update stats on who shot at who
				$this->client[$owner_index]['shots_hit'] = intval($this->client[$owner_index]['shots_hit']) + 1;
				$this->client[$owner_index]['last_target'] = $client_index;
				
				// update who shot at current player the last (in case we die now...)
				$this->client[$client_index]['last_assailant'] = $owner_index;
				
				$this->send_game_players($game_id, "<w>hit</w><i>$user_id</i><oid>$owner_id</oid><bid>$bullet_index</bid><x>$bullet_x</x><y>$bullet_y</y><time>$impact_time</time><st>".microtime(true)."</st>", $client_index);
				break;
			
			case 'playerinfo':
				$game_id = $client['game_id'];
				
				// pos, rot, speed, accellation, accellerating
				$posX 			= get_value_by_path($xml, 'a/p/x'); // global X
				$posY 			= get_value_by_path($xml, 'a/p/y'); // global Y
				$rotation 		= get_value_by_path($xml, 'a/rot'); // 0, 360
				$accelerating 	= get_value_by_path($xml, 'a/acc'); // -1, 0, 1
				$hp 				= get_value_by_path($xml, 'a/hp');
				$distance 		= get_value_by_path($xml, 'a/d');
				$trd 				= get_value_by_path($xml, 'a/trd');
				$trtd				= get_value_by_path($xml, 'a/trtd'); // turret rotation target dir
				$time 			= get_value_by_path($xml, 'a/time'); //time();
				
				$posX 			= $posX['value'];
				$posY 			= $posY['value'];
				$rotation 		= $rotation['value'];
				$accelerating 	= $accelerating['value'];
				$hp 				= $hp['value'];
				$trd			 	= $trd['value'];
				$trtd 			= $trtd['value'];
				$time 			= $time['value'];
				$distance		= $distance['value'];
				
				if(intval($distance) > 0) {
					$this->client[$client_index]['distance'] = $distance;
				}
				
				$player_info = "<w>player</w><i>".$client['user_id']."</i><p><x>$posX</x><y>$posY</y></p><rot>$rotation</rot><hp>$hp</hp><acc>$accelerating</acc><trd>$trd</trd><trtd>$trtd</trtd><time>$time</time><st>".microtime(true)."</st>";
				
				$this->send_game_players($game_id, $player_info, $client_index);
				break;
			
			case 'died':
				$game_id 		= $client['game_id'];
				$game_index 	= $this->get_game_index($game_id);
				
				$victim_index  = $client_index;
				$victim_id 		= $this->client[$victim_index]['user_id'];
				
				$killer_index 	= $this->client[$victim_index]['last_assailant'];
				$killer_id 		= $this->client[$killer_index]['user_id'];
				
				/*
				$achievement_id = $this->set_achievement($killer_index, "FIRST_KILL");
				if($achievement_id > 0) {
					$this->send_to_chat($client_index, "ach$achievement_id");
				}
				
				
				if(intval($this->client[$client_index]['shots_fired']) == 0) {
					if(intval($this->client[$client_index]['distance']) == 0) {
						$achievement_id = $this->set_achievement($client_index, "AFK");
						if($achievement_id > 0) {
							$this->send_to_chat($client_index, "ach$achievement_id");
						}
					} else {
						$achievement_id = $this->set_achievement($client_index, "ONE_HAND");
						if($achievement_id > 0) {
							$this->send_to_chat($client_index, "ach$achievement_id");
						}
					}
				}
				*/
				
				$this->client[$killer_index]['kills'] 	= intval($this->client[$killer_index]['kills'])  + 1;
				$this->client[$victim_index]['deaths'] = intval($this->client[$victim_index]['deaths']) + 1;
				
				/**
				  * Achievements:
				  */
				
				/*
				if($this->client[$killer_index]['kills'] == 2) {
					$achievement_id = $this->set_achievement($player_index, "DOUBLE_KILL");
					if($achievement_id > 0) {
						$this->send_to_chat($player_index, "ach$achievement_id");
					}
				}
				
				if($this->client[$killer_index]['kills'] == 3) {
					$achievement_id = $this->set_achievement($player_index, "MULTI_KILL");
					if($achievement_id > 0) {
						$this->send_to_chat($player_index, "ach$achievement_id");
					}
				}
				
				if($this->client[$killer_index]['kills'] == 4) {
					$achievement_id = $this->set_achievement($player_index, "GODLIKE");
					if($achievement_id > 0) {
						$this->send_to_chat($player_index, "ach$achievement_id");
					}
				}
				*/
				
				
				$killer_points = $this->client[$killer_index]['points'];
				$killer_rank 	= floor(sqrt($killer_points / 20));
				
				$victim_points = $this->client[$victim_index]['points'];
				$victim_rank	= floor(sqrt($victim_points / 20));
				
				
				$rank_diff = $victim_rank - $killer_rank;
				
				
				$victim_points_delta = 0;
				if($rank_diff >= 0) { // if killer is lower-ranked
					$killer_points_delta = (5 + ($rank_diff * 2));
					$victim_points_delta = 0 - (2 + ($rank_diff * 2));
				} else { // killer is higher-ranked
					// rank_diff is < 0, so max 4 points, min 1 point
					$killer_points_delta = max(1, 5 + $rank_diff);
				}
				
				$this->client[$killer_index]['points_delta'] = intval($this->client[$killer_index]['points_delta']) + $killer_points_delta;
				$killer_points += $killer_points_delta;
				$killer_points = min(20 * pow(10,2), $killer_points); // dont go above rank 10 (2000 points)
				$this->client[$killer_index]['points'] = $killer_points;
				
				$this->client[$victim_index]['points_delta'] = intval($this->client[$victim_index]['points_delta']) + $victim_points_delta;
				$victim_points += $victim_points_delta;
				$victim_points = max(0, $victim_points); // don't go below 0
				$this->client[$victim_index]['points'] = $victim_points;
			
				
				$killer_rank_new = floor(sqrt($killer_points / 20));
				
				if($killer_rank != $killer_rank_new) {
					// send updated rank
					$this->send_game_players($game_id, "<w>rank</w><i>".$killer_id."</i><r>".$killer_rank_new."</r>");
					
					// update stats
					$this->stats->add("player_id", $killer_id);
					$this->stats->add("old_rank", $killer_rank);
					$this->stats->add("new_rank", $killer_rank_new);
					$this->stats->save("RankChange");
				}
				
				
				$victim_rank_new = floor(sqrt($victim_points / 20));
				
				if($victim_rank != $victim_rank_new) {
					// send updated rank
					$this->send_game_players($game_id, "<w>rank</w><i>".$victim_id."</i><r>".$victim_rank_new."</r>");
					
					// update stats
					$this->stats->add("player_id", $victim_id);
					$this->stats->add("old_rank", $victim_rank);
					$this->stats->add("new_rank", $victim_rank_new);
					$this->stats->save("RankChange");
				}			
				
				
				/* Save stats to db */
				$this->stats->add("victim_id", $victim_id);
				$this->stats->add("killer_id", $killer_id);
				$this->stats->add("game_id", $client['game_id']);
				$this->stats->save("PlayerKills");
				
				
				/* PER GAME TYPE - DIFFERENT RESPONSES */
				
				$this->client[$client_index]['dead'] = true;
				$this->send_game_players($game_id, "<w>died</w><i>$victim_id</i><st>".microtime(true)."</st>", $client_index);
				
				$this->check_for_winner($game_index);
				
				break;
		}
	}
	
	function client_connected($index) {
		parent::client_connected($index);
	}
	
	function client_disconnect($index) {
		// leave potentially joined games:
		$this->leave_game($index);
		
		$this->time_queue_end(0, $index);
		$this->time_game_end(0, $index);
		
		// update points to the database
		$player_id = intval($this->client[$index]['user_id']);
		$points = intval($this->client[$index]['points']);
		
		if($player_id > 0) {
			$this->db->query("UPDATE `global__PlayerInfo` SET `points`='$points' WHERE `player_id`='$player_id'");
		}
		
		parent::client_disconnect($index);
	}
	
	function client_identified($index, $success) {
		parent::client_identified($index, $success);
		
		$user_id = $this->client[$index]['user_id'];
		$user_name = $this->client[$index]['user_name'];
		
		$xml = "<w>identified</w><i>$user_id</i><n>$user_name</n><success>".$this->client[$index]['identified']."</success>";
		$this->send($index, $xml, false, "queue");
		$this->send_gamelist($index);
	}
	
	/**
	  * Send the game list to a specific client
	  */
	function send_gamelist($client_index) {
		$gamelist = "<w>list</w><gs>";
		
		for($g = 0; $g < count($this->games); $g++) {
			$game = $this->games[$g];
			
			if(intval($this->games[$g]['id']) > 0) {
				if(!$game['active']) {
					$game_has_password = ($game['password'] == "")?0:1;
					$gamelist .= "<g><i>".$game['id']."</i><n>".$game['name']."</n><pc>".count($game['players'])."</pc><mp>".$game['max_players']."</mp><pp>".$game_has_password."</pp></g>";
				}
			} else {
				array_splice($this->games, $g, 1);
				$g--;
				continue;
			}
		}
		$gamelist .= "</gs>";
		
		$this->send($client_index, $gamelist, false, "queue");
	}
	
	/**
	  * Send the gamelist to everbody not in an active game
	  */
	function send_all_gamelist($__exclude_index = -1) {
		for($c = 0; $c < count($this->client); $c++) {
			if($c == $__exclude_index) continue;
			
			if(isset($this->client[$c]['game_id'])) {
				$game_index = $this->get_game_index($this->client[$c]['game_id']);
				if($this->games[$game_index]['active']) continue;
			}
			
			$this->send_gamelist($c);
		}
	}
	
	/**
	  * Send an updated playerlist for a game queue to everybody not in an active game
	  */
	function send_playerlist($game_index, $sendTo = -1) {
		$game = &$this->games[$game_index];
		
		// only for games still in queue
		if($game['active']) return;
		
		$max_players = $game['max_players'];
		
		// create playerlist for this game:
		$playerlist = "<w>playerlist</w><mp>$max_players</mp><ps>";
		
		for($p = 0; $p < count($game["players"]); $p++) {
			$client_index = $this->get_client_index($game["players"][$p]);
			
			$user_id = $this->client[$client_index]['user_id'];
			$user_name = $this->client[$client_index]['user_name'];
			$user_rank = floor(sqrt($this->client[$client_index]['points'] / 20));
			
			$playerlist .= "<p><i>$user_id</i><n>$user_name</n><r>$user_rank</r></p>";
		}
		
		$playerlist .= "</ps>";
		
		if($sendTo == -1) {
			//trace("Sending playerlist to all players in game #" . $games[$game_index]['id']);
			$this->send_queued_players($game['id'], $playerlist);
		} else {
			//trace("Sending playerlist for game " . $games[$game_index]['id'] . " to client #" . $sendTo);
			$this->send($sendTo, $playerlist, false, "queue");
		}
	}
	
	/**
	  * Leave the game the client is in or queued in
	  */
	function leave_game($index) {
		$user_id 	= $this->client[$index]['user_id'];
		
		if(isset($this->client[$index]['game_id'])) {
			$game_id 	= $this->client[$index]['game_id'];
			$game_index = $this->get_game_index($game_id);
			
			if($game_index > -1) {
				$this->remove_from_game($game_index, $index);
			}
		}
		
		for($i = 0; $i < count($this->games); $i++) {
			if($this->client_in_game($index, $i)) {
				$this->remove_from_game($i, $index);
			}
		}
		
		// update the game list (for player-count)
		$this->send_all_gamelist();
		
		unset($this->client[$index]['game_id']);
	}
	
	function client_in_game($client_index, $game_index) {
		$user_id = $this->client[$client_index]['user_id'];
		
		for($p = 0; $p < count($this->games[$game_index]["players"]); $p++) {
			if($this->games[$game_index]["players"][$p] == $user_id) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	  * Remove player from the game because of a disconnect or quit
	  */
	function remove_from_game($game_index, $client_index) {
		$user_id = $this->client[$client_index]['user_id'];
		
		if(!$this->games[$game_index]) {
			return;
		}
		
		$game_id = $this->games[$game_index]['id'];
		
		for($p = 0; $p < count($this->games[$game_index]["players"]); $p++) {
			if($this->games[$game_index]["players"][$p] == $user_id) {
				
				$this->time_game_end($game_id, $p);
				array_splice($this->games[$game_index]["players"], $p, 1);
				
				trace("User #" . $user_id . " removed from game #" . $game_id);
				
				$game = $this->games[$game_index];
				if($p == 0 && count($game["players"]) > 0 && !$game['active']) {
					// the creater left the game
					// send next in line the command to control the game
					$new_leader = "<w>owner</w><g>$game_id</g><i>" . $game["players"][0] . "</i>";
				}
				
				$players_left = count($this->games[$game_index]["players"]);
				
				if($game['active']) {
					// TODO: update Stats to database!
					$this->save_player_stats($client_index, ($players_left == 0));
					
					// make all other players remove the player
					$this->send_game_players($game_id, "<w>remove</w><i>".$user_id."</i>", $client_index);
				} else {
					$this->time_queue_end(0, $client_index);
					$this->send_playerlist($game_index);
				}
			}
		}
		
		$this->check_for_winner($game_index);
		
		if(count($this->games[$game_index]["players"]) <= 1) {
			trace("Closing game #" . $game_id);
			$this->close_game($game_index);
			
			return;
		} elseif(!empty($new_leader)) {
			$this->send_queued_players($game_id, $new_leader);
		}
		
		// $this->check_for_winner($game_index);
	}
	
	/**
	  * Save the game statistics to the database
	  */
	function close_game($game_index) {
		$game_id = $this->games[$game_index]['id'];
		
		$this->time_game_end($game_id);
	
		trace("Removing Game #" . $game_id);
		array_splice($this->games, $game_index, 1);
		
		$this->send_all_gamelist();
	}
	
	function check_all_loaded($game_index) {
		$game_id = $this->games[$game_index]['id'];
		
		if($this->games[$game_index]['startpos_sent']) return;
		
		$positions = "";
		$loaded = true;
		// check for all players in specified game if they are ready...
		for($p = 0; $p < count($this->games[$game_index]['players']); $p++) {
			$client_id = $this->games[$game_index]['players'][$p];
			$check_client = $this->get_client_index($client_id);
			$loaded = $loaded && $this->client[$check_client]['loaded'];
			
			$positions .= "<p><i>".$client_id."</i><pos>".$this->client[$check_client]['start_position']."</pos></p>";
		}
		
		// then send all startpostions at once
		if($loaded) {
			$this->games[$game_index]['startpos_sent'] = true;
			
			$level = $this->games[$game_index]['level'];
			$this->send_game_players($game_id, "<w>startpos</w><l>$level</l><ps>".$positions."</ps>");
			
			trace("All players loaded for game #$game_id: " . $positions);
		}
	}
	
	/**
	  * Check the gme if a winner is forced by default
	  */
	function check_for_winner($game_index) {
		$game = &$this->games[$game_index];
		$game_id = $game['id'];
		
		// only applies to active games...
		if(!$game['active']) return;
		
		// TODO: Consider GameType win rules
		
		$dead = 0;
		$winner = -1;
		
		// see how many are still alive..
		for($p = 0; $p < count($game['players']); $p++) {
			$client_index = $this->get_client_index($game['players'][$p]);
			if($this->client[$client_index]['dead']) {
				$dead++;
			} else {
				$winner = $client_index;
			}
		}
		
		// if all are dead but 1...
		if($dead == count($game['players']) - 1) {
			// finish the game for all players, send winner
			// save each players stats:
			for($p = 0; $p < count($game['players']); $p++) {
				$client_index = $this->get_client_index($game['players'][$p]);
				$this->save_player_stats($client_index, ($client_index == $winner));
			}
			
			if($winner > -1) {
				/*
				$achievement_id = $this->set_achievement($winner, "GAME_WON");
				if($achievement_id > 0) {
					$this->send_to_chat($winner, "ach$achievement_id");
				}
				*/
				
				$this->send($winner, "<w>finish</w><i>" . $this->client[$winner]['user_id'] . "</i><n>" . $this->client[$winner]['user_name'] . "</n>");
			}
			
			// Don't close the game yet, only close if everybody -1 left.
			// $this->close_game($game_index);
		}
	}
	
	/**
	  * Save player statistics
	  */
	function save_player_stats($client_index, $winner = false) {
		$client 			= $this->client[$client_index];
		
		$user_id 		= $client['user_id'];
		$game_id 		= $client['game_id'];
		
		
		$distance 		= intval($client['distance']);
		$shots_fired 	= intval($client['shots_fired']);
		$shots_hit 		= intval($client['shots_hit']);
		$points_delta	= intval($client['points_delta']);
		$points 			= intval($client['points']);
		
		$kills			= intval($client['kills']);
		$deaths 			= intval($client['deaths']);
		
		
		// UPDATE if the game_id + player_id combination exists; else INSERT
		$this->stats->updateIf("game_id", $game_id);
		$this->stats->updateIf("player_id", $user_id);
		
		$this->stats->add("game_id", 		$game_id);
		$this->stats->add("player_id", 	$user_id);
		$this->stats->add("distance", 	$distance);
		$this->stats->add("shots_fired", $shots_fired);
		$this->stats->add("shots_hit", 	$shots_hit);
		$this->stats->add("points_delta", $points_delta);
		$this->stats->add("kills", 		$kills);
		$this->stats->add("deaths", 		$deaths);
		$this->stats->add("won", 			(($winner)?1:0));
		$this->stats->add("gametime", 	$gametime);
		
		$this->stats->save("PlayerGame");
		
		
		$top_points = $points;
		$query = $this->db->query("SELECT `top_points` FROM  `global__PlayerInfo` WHERE `player_id`='$user_id'");
		if($info = $this->db->assoc($query)) {
			$top_points = max(intval($info['top_points']), $points);
		} else {
			$this->db->query("INSERT INTO `global__PlayerInfo` (`player_id`) VALUES ('$user_id')");
		}
		
		$this->db->query("UPDATE `global__PlayerInfo` SET `top_points`='$top_points', `points`='$points', `wins`=`wins`+".(($winner)?1:0).", `kills`=`kills`+$kills, `deaths`=`deaths`+$deaths, `distance`=`distance`+$distance, `shots_fired`=`shots_fired`+$shots_fired, `shots_hit`=`shots_hit`+$shots_hit WHERE `player_id`='$user_id'");
	}
	
	/**
	  * Initialize Client
	  *
	  * Reset all potentially saved variables
	  *
	  * Only called when entering a Queue
	  */
	function initialize_client($client_index) {
		unset($this->client[$client_index]['distance']);
		unset($this->client[$client_index]['shots_fired']);
		unset($this->client[$client_index]['shots_hit']);
		unset($this->client[$client_index]['points_delta']);
		unset($this->client[$client_index]['kills']);
		unset($this->client[$client_index]['deaths']);
		
		unset($this->client[$client_index]['game_id']);
		
		unset($this->client[$client_index]['last_assailant']);
		unset($this->client[$client_index]['last_target']);
		
		unset($this->client[$client_index]['start_position']);
		
		unset($this->client[$client_index]['dead']);
		unset($this->client[$client_index]['ready']);
		unset($this->client[$client_index]['loaded']);
		
		$this->time_queue_start($client_index);
	}
	
	/**
	  * Statistics: Save the time queued for a game
	  */
	function time_queue_start($client_index) {
		$this->client[$client_index]['queue_time'] = time();
	}
	
	function time_queue_end($game_id, $client_index = -1) {
		$time = time();
			
		if($client_index > -1) {
			if(intval($this->client[$client_index]['queue_time']) > 0) {	
				$duration = $time - $this->client[$client_index]['queue_time'];
				
				$this->stats->add("player_id", $this->client[$client_index]['user_id']);
				$this->stats->add("time_type", "QUEUE");
				$this->stats->add("time_length", $duration);
				$this->stats->save("Time");
				
				$this->db->query("UPDATE `global__PlayerInfo` SET `queuetime`=`queuetime`+$duration WHERE `player_id`=".$this->client[$client_index]['user_id']);
				
				$this->client[$client_index]['queue_time'] = 0;
			}
		} else {
			$game_index = $this->get_game_index($game_id);
			$game = $this->games[$game_index];
			
			for($p = 0; $p < count($game["players"]); $p++) {
				$client_index = $this->get_client_index($game["players"][$p]);
				
				$this->time_queue_end($game_id, $client_index);
			}
		}
	}
	
	/**
	  * Statistics: Set the start time for a game
	  */
	function time_game_start($game_id) {
		$game_index = $this->get_game_index($game_id);
		$game = $this->games[$game_index];
		
		$this->games[$game_index]['starttime'] = time();
		
		for($p = 0; $p < count($game["players"]); $p++) {
			$client_index = $this->get_client_index($game["players"][$p]);
			$this->client[$client_index]['game_time'] = time();
		}
		
		$this->stats->updateIf("id", $this->games[$game_index]['db_id']);
		$this->stats->add("type_id", $this->games[$game_index]['type_id']);
		$this->stats->add("starttime", time());
		$this->stats->save("Games");
	}
	
	/**
	  * Statistics: Save the total game time for this client
	  */
	function time_game_end($game_id, $client_index = -1) {
		$time = time();
			
		if($client_index > -1) {
			if(intval($this->client[$client_index]['game_time']) > 0) {
				
				$duration = intval($time - $this->client[$client_index]['game_time']);
				
				$this->stats->add("player_id", $this->client[$client_index]['user_id']);
				$this->stats->add("time_type", "GAME");
				$this->stats->add("time_length", $duration);
				$this->stats->save("Time");
				
				$this->db->query("UPDATE `global__PlayerInfo` SET `playingtime`=`playingtime`+$duration WHERE `player_id`=".$this->client[$client_index]['user_id']);
				
				$this->client[$client_index]['game_time'] = 0;
			}
		} else {
			$game_index = $this->get_game_index($game_id);
			$game = $this->games[$game_index];
			
			for($p = 0; $p < count($game["players"]); $p++) {
				$client_index = $this->get_client_index($game["players"][$p]);
				
				$this->time_game_end($game_id, $client_index);
			}
			
			$this->stats->updateIf("id", $this->games[$game_index]['db_id']);
			$this->stats->add("endtime", time());
			$this->stats->save("Games");
		}
	}
	
	/**
	  * Send to all players in a specified game
	  */
	function send_game_players($game_id, $xml, $__exclude = -1) {
		$this->send_players($game_id, $xml, $__exclude, "game");
	}
	
	/**
	  * Send to all players queued in a specified game
	  */
	function send_queued_players($game_id, $xml, $__exclude = -1) {
		$this->send_players($game_id, $xml, $__exclude, "queue");
	}
	
	/**
	  * Send to a specified group of players
	  */
	function send_players($game_id, $xml, $__exclude, $type) {
		$game_index = $this->get_game_index($game_id);
		$game = $this->games[$game_index];
		
		if(count($game["players"]) == 0) return;
		
		for($p = 0; $p < count($game["players"]); $p++) {
			$client_index = $this->get_client_index($game["players"][$p]);
			
			if($__exclude > -1 && $__exclude == $client_index) continue;
			$this->send($client_index, $xml, false, $type);
		}
	}
	
	/**
	  * Game -> Flash -> html -> Flash -> Chat communication:
	  */
	function send_to_chat($client_index, $data) {
		return;
		
		$this->send($client_index, "<w>tellChat</w><data>".$data."</data>", false, "passthru");
	}
	
	/**
	  * Core sending XML function
	  */
	function send($index, $xml, $send_raw = false, $type = "game") {
		if($send_raw) {
			parent::send($index, $xml, true);
		} else {
			parent::send($index, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>$type</t>".$xml."</s>");
		}
	}
	
	function send_to_all($xml, $type = "game") {
		parent::send_to_all("<?xml version=\"1.0\" encoding=\"UTF-8\"?><s><t>$type</t>$xml</s>");
	}
	
	/**
	  * Get the client index from the database user id
	  */
	function get_client_index($user_id) {
		for($client_index = 0; $client_index < count($this->client); $client_index++) {
			if($this->client[$client_index]['user_id'] == $user_id) {
				return $client_index;
			}
		}
		return -1;
	}
	
	/**
	  * Get the game index for the specified game
	  */
	function get_game_index($game_id) {
		if(empty($game_id)) return -1;
		
		for($game_index = 0; $game_index < count($this->games); $game_index++) {
			if($this->games[$game_index]['id'] == $game_id) {
				return $game_index;
			}
		}
		return -1;
	}
}

?>