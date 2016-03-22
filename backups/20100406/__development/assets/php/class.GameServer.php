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
				
				if(!empty($client['game_id'])) {
					$this->leave_game($client_index);
				}
				
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
				$this->client[$client_index]['queue_time'] = time();
				unset($this->client[$client_index]['start_position']);
				
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
				
				if(!empty($client['game_id'])) {
					$this->leave_game($client_index);	
				}
				
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
					$return = "<w>joined</w><gi>0</gi><r>$reason</r>";
					$this->send($client_index, $return, false, "queue");
				} else {
					$this->send($client_index, "<w>joined</w><gi>$game_id</gi><gn>$game_name</gn>", false, "queue");
					
					array_push($this->games[$game_index]["players"], $client['user_id']);
					
					$this->client[$client_index]['game_id'] = $game_id;
					$this->client[$client_index]['queue_time'] = time();
					
					unset($this->client[$client_index]['start_position']);
					
					$this->send_playerlist($game_index);
					$this->send_all_gamelist();
				}
				break;
				
			case 'leave':
				$this->leave_game($client_index);
				$this->time_queue_end(0, $client_index);
				break;
				
			case 'start':
				$game_id 	= $client['game_id'];
				$game_index = $this->get_game_index($game_id);
				
				// only the game owner can start:
				if($client['user_id'] == $this->games[$game_index]["players"][0]) {
					
					// GO!
					trace("Initializing game #" . $game_id);
					
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
								for($i = 0; $i < count($this->games[$game_index]['players']); $i++) {
									if($i != $p) {
										$player_index = $this->get_client_index($this->games[$game_index]['players'][$i]);
										
										$achievement_id = $this->set_achievement($player_index, "REFERENCE");
										if($achievement_id > 0) {
											$this->send_to_chat($player_index, "ach$achievement_id");
										}
									}
								}
							}
						}
						
					}
					
					//   set game to active - joins are disabled
					$this->games[$game_index]['active'] = true;
				
					// send to all in game...
					$starting = "<w>startgame</w><st>".time()."</st>";
					$this->send_queued_players($game_id, $starting);
					
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
				
				$this->games[$game_index]['start_positions'] = $positions;
				
				$select_level = "<w>level</w><n>$level</n>";
				$this->send_game_players($game_id, $select_level);
				
				trace("Game #".$game_id." set level ".$level);
				break;
			
			case 'startpos':
				$game_id 	= $client['game_id'];
				$game_index = $this->get_game_index($game_id);
				
				$num_positions = intval($this->games[$game_index]['start_positions']);
				
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
						trace("Game #{$game_index}; Player #{$client_index} ({$client['user_id']}) got start position: $start_position");
					}
				}
				
				$user_id = $client['user_id'];
				$this->client[$client_index]['start_position'] = $start_position;
				
				$my_start_position = "<w>startpos</w><i>$user_id</i><pos>$start_position</pos>";
				$this->send_game_players($game_id, $my_start_position);
				break;
			
			case 'ready':
				$this->client[$client_index]['ready'] = true;
				
				$game_id = $client['game_id'];
				$game_index = $this->get_game_index($game_id);
				
				$player_rank = floor(sqrt($this->client[$client_index]['points'] / 20));
				
				$playerReady = "<w>ready</w><i>".$client['user_id']."</i><r>".$player_rank."</r>";
				$this->send_game_players($game_id, $playerReady);
				
				$ready = 0;
				for($p = 0; $p < count($this->games[$game_index]['players']); $p++) {
					$check_client = $this->get_client_index($this->games[$game_index]['players'][$p]);
					$ready += ($this->client[$check_client]['ready'])?1:0;
				}
				
				if($ready == count($this->games[$game_index]['players'])) {
					trace("Game #" . $game_id . " all players are ready, sending START");
					
					$this->send_game_players($game_id, "<w>start</w><time>".time()."</time>");
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
				$ping 	 = get_value_by_path($xml, 'a/ping'); // 'server' time when the action was performed.
				$bid		 = get_value_by_path($xml, 'a/bid');
				
				$time 	 = time();
				
				$posX 	 = $posX['value'];
				$posY 	 = $posY['value'];
				$rotation = $rotation['value'];
				$speed 	 = $speed['value'];
				$ping 	 = $ping['value'];
				$bid		 = intval($bid['value']);
				
				$time -= $ping;
				
				//trace("Shoot event: {$client['user_id']} shot bullet {$bid}.");
				//trace($input);
				
				$event = "<w>shoot</w><i>".$client['user_id']."</i><p><x>$posX</x><y>$posY</y></p><r>$rotation</r><time>$time</time><s>$speed</s><bid>$bid</bid><st>".time()."</st>";
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
				
				$this->send_game_players($game_id, "<w>hit</w><i>$user_id</i><oid>$owner_id</oid><bid>$bullet_index</bid><x>$bullet_x</x><y>$bullet_y</y><st>".time()."</st>", $client_index);
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
				
				$ping				= get_value_by_path($xml, 'a/ping');
				
				// $time 			= get_value_by_path($xml, 'a/time'); // 'server' time when the action was performed.
				$time 			= time();
				
				$posX 			= $posX['value'];
				$posY 			= $posY['value'];
				$rotation 		= $rotation['value'];
				$accelerating 	= $accelerating['value'];
				$hp 				= $hp['value'];
				$trd			 	= $trd['value'];
				$trtd 			= $trtd['value'];
				$time 			= $time['value'];
				$distance		= $distance['value'];
				
				$ping				= $ping['value'];
				$time 			-= $ping;
				
				$this->client[$client_index]['distance'] = $distance;
				
				$player_info = "<w>player</w><i>".$client['user_id']."</i><p><x>$posX</x><y>$posY</y></p><rot>$rotation</rot><hp>$hp</hp><acc>$accelerating</acc><trd>$trd</trd><trtd>$trtd</trtd><time>$time</time><st>".time()."</st>";
				
				$this->send_game_players($game_id, $player_info, $client_index);
				break;
			
			case 'died':
				$game_id 		= $client['game_id'];
				$game_index 	= $this->get_game_index($game_id);
				
				$victim_index  = $client_index;
				$victim_id 		= $this->client[$victim_index]['user_id'];
				
				$killer_index 	= $this->client[$client_index]['last_assailant'];
				$killer_id 		= $this->client[$killer_index]['user_id'];
				
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
				
				$this->client[$killer_index]['kills'] 	= intval($this->client[$killer_index]['kills'])  + 1;
				$this->client[$victim_index]['deaths'] = intval($this->client[$victim_index]['deaths']) + 1;
				
				/**
				  * Achievements:
				  */
				
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
				$this->send_game_players($game_id, "<w>died</w><i>$victim_id</i><st>".time()."</st>", $client_index);
				
				$this->check_for_winner($game_index);
				
				break;
		}
	}
	
	function client_connected($index) {
		parent::client_connected($index);
	}
	
	function client_disconnect($index) {
		$this->leave_game($index);
		
		$this->time_queue_end(0, $index);
		$this->time_game_end(0, $index);
		
		$player_id = $this->client[$index]['user_id'];
		$points = $this->client[$index]['points'];
		
		$this->db->query("UPDATE `global__PlayerInfo` SET `points`='$points' WHERE `player_id`='$player_id'");
		
		parent::client_disconnect($index);
	}
	
	function client_identified($index, $success) {
		parent::client_identified($index, $success);
		
		$user_id = $this->client[$index]['user_id'];
		$user_name = $this->client[$index]['user_name'];
		
		$xml = "<w>identified</w><i>$user_id</i><n>$user_name</n><success>" . $this->client[$index]['identified'] . "</success>";
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
			
			$game_has_password = ($game['password'] == "")?0:1;
			$gamelist .= "<g><i>".$game['id']."</i><n>".$game['name']."</n><pc>".count($game['players'])."</pc><mp>".$game['max_players']."</mp><pp>".$game_has_password."</pp></g>";
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
		$game = $this->games[$game_index];
		
		// only for games still in queue
		if($game['active']) return;
		
		if(intval($game["max_players"]) == 0) {
			$this->close_game($game_index);
			return;
		}
		
		// create player list for this game:
		$max_players = $game["max_players"];
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
		$game_id 	= $this->client[$index]['game_id'];
		$game_index = $this->get_game_index($game_id);
		
		if($game_index == -1) return;
		
		trace("User #" . $user_id . " leaving game #" . $game_id);
		
		$this->remove_from_game($game_index, $index);
		
		if(!$this->games[$game_index]['active']) {
			$this->send_playerlist($game_index);
		} else {
			$this->send_game_players($game_id, "<w>leave</w><g>$game_id</g><u>$user_id</u>");
		}
		
		// update the game list (for player-count)
		$this->send_all_gamelist();
		
		unset($this->client[$index]['game_id']);
	}
	
	/**
	  * Remove player from the game because of a disconnect or quit
	  */
	function remove_from_game($game_index, $client_index) {
		$user_id = $this->client[$client_index]['user_id'];
		$game_id = $this->games[$game_index]['id'];
		
		for($p = 0; $p < count($this->games[$game_index]["players"]); $p++) {
			if($game["players"][$p] == $user_id) {
				
				array_splice($this->games[$game_index]["players"], $p, 1);
				
				$game = $this->games[$game_index];
				
				if($p == 0 && count($game["players"]) > 0 && !$game['active']) {
					// the creater left the game
					// send next in line the command to control the game
					$new_leader = "<w>owner</w><g>$game_id</g><i>" . $game["players"][0] . "</i>";
				}
				
				if($game['active']) {
					// TODO: update Stats to database!
					$this->save_player_stats($client_index);
					
					// make all other players remove the player
					$this->send_game_players($game_id, "<w>remove</w><i>".$user_id."</i>", $client_index);
				}
			}
		}
		
		$this->check_for_winner($game_index);
		
		if(count($this->games[$game_index]["players"]) == 0) {
			trace("Closing game #" . $game_id);
			$this->close_game($game_index);
		} elseif(!empty($new_leader)) {
			$this->send_queued_players($game_id, $new_leader);
		}
	}
	
	/**
	  * Save the game statistics to the database
	  */
	function close_game($game_index) {
		$game_id = $this->games[$game_index]['id'];
		
		$this->time_game_end($game_id);
		
		$this->stats->updateIf("id", $this->games[$game_index]['db_id']);
		$this->stats->add("endtime", time());
		$this->stats->save("Games");
		
		trace("Removing Game #" . $game_id . ".");
		
		array_splice($this->games, $game_index, 1);
		$this->send_all_gamelist();
	}
	
	/**
	  * Check the gme if a winner is forced by default
	  */
	function check_for_winner($game_index) {
		$game = $this->games[$game_index];
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
			if($winner > -1) {
				$this->send($winner, "<w>finish</w><i>" . $this->client[$winner]['user_id'] . "</i><n>" . $this->client[$winner]['user_name'] . "</n>");
				
				$achievement_id = $this->set_achievement($winner, "GAME_WON");
				if($achievement_id > 0) {
					$this->send_to_chat($winner, "ach$achievement_id");
				}
			}
			
			// save each players stats:
			for($p = 0; $p < count($game['players']); $p++) {
				$client_index = $this->get_client_index($game['players'][$p]);
				
				$this->save_player_stats($client_index, ($client_index == $winner));
			}
			
			$this->close_game($game_index);
		}
	}
	
	/**
	  * Save player statistics
	  */
	function save_player_stats($index, $winner = false) {
		$client = $this->client[$index];
		
		$user_id = $client['user_id'];
		$game_id = $client['game_id'];
		
		
		$distance 		= intval($client['distance']);
		$shots_fired 	= intval($client['shots_fired']);
		$shots_hit 		= intval($client['shots_hit']);
		$points_delta	= intval($client['points_delta']);
		$points 			= intval($client['points']);
		
		$kills			= intval($client['kills']);
		$deaths 			= intval($client['deaths']);
		
		
		$gametime = intval($client['game_time']);
		if($gametime > 0) {
			$gametime = time() - $gametime;
		}
		
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
		
		
		$this->db->query("UPDATE `global__PlayerInfo` SET `top_points`='$top_points', `points`='$points', `wins`=`wins`+".(($winner)?1:0).", `kills`=`kills`+$kills, `deaths`=`deaths`+$deaths, `distance`+=$distance, `shots_fired`=`shots_fired`+$shots_fired, `shots_hit`=`shots_hit`+$shots_hit, `playing_time`=`playing_time`+$gametime WHERE `player_id`='$user_id'");
		
		unset($this->client[$index]['distance']);
		unset($this->client[$index]['shots_fired']);
		unset($this->client[$index]['shots_hit']);
		unset($this->client[$index]['points_delta']);
		unset($this->client[$index]['kills']);
		unset($this->client[$index]['deaths']);
		
		unset($this->client[$index]['start_position']);
		unset($this->client[$index]['dead']);
		unset($this->client[$index]['ready']);
	}
	
	/**
	  * Statistics: Save the time queued for a game
	  */
	function time_queue_end($game_id, $client_index = -1) {
		$time = time();
			
		if($game_id == 0) {
			if(intval($this->client[$client_index]['queue_time']) > 0) {
				$this->stats->add("player_id", $this->client[$client_index]['user_id']);
				$this->stats->add("time_type", "QUEUE");
				$this->stats->add("time_length", $time - $this->client[$client_index]['queue_time']);
				$this->stats->save("Time");
				
				$this->client[$client_index]['queue_time'] = 0;
			}
		} else {
			$game_index = $this->get_game_index($game_id);
			$game = $this->games[$game_index];
			
			for($p = 0; $p < count($game["players"]); $p++) {
				$client_index = $this->get_client_index($game["players"][$p]);
				
				if(intval($this->client[$client_index]['queue_time']) > 0) {
					
					$duration = $time - $this->client[$client_index]['queue_time'];
					
					$this->stats->add("player_id", $this->client[$client_index]['user_id']);
					$this->stats->add("time_type", "QUEUE");
					$this->stats->add("time_length", $duration);
					$this->stats->save("Time");
					
					$this->db->query("UPDATE `global__PlayerInfo` SET `queuetime`=`queuetime`+$duration WHERE `player_id`=".$this->client[$client_index]['user_id']);
					
					$this->client[$client_index]['queue_time'] = 0;
				}
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
	function time_game_end($game_id, $client_id = -1) {
		$time = time();
			
		if($game_id == 0) {
			if(intval($this->client[$client_index]['game_time']) > 0) {
				$this->stats->add("player_id", $this->client[$client_index]['user_id']);
				$this->stats->add("time_type", "GAME");
				$this->stats->add("time_length", $time - $this->client[$client_index]['game_time']);
				$this->stats->save("Time");
			}
		} else {
			$game_index = $this->get_game_index($game_id);
			$game = $this->games[$game_index];
			
			for($p = 0; $p < count($game["players"]); $p++) {
				$client_index = $this->get_client_index($game["players"][$p]);
				if(intval($this->client[$client_index]['game_time']) > 0) {
					$this->stats->add("player_id", $this->client[$client_index]['user_id']);
					$this->stats->add("time_type", "GAME");
					$this->stats->add("time_length", $time - $this->client[$client_index]['game_time']);
					$this->stats->save("Time");
				}
			}
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