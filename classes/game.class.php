<?php
/*
+---------------------------------------------------------------------------
|
|   game.class.php (php 5.x)
|
|   by Benjam Welker
|   http://iohelix.net
|
+---------------------------------------------------------------------------
|
|	This module is built to facilitate the game Battleship, it doesn't really
|	care about how to play, or the deep goings on of the game, only about
|	database structure and how to allow players to interact with the game.
|
+---------------------------------------------------------------------------
|
|   > Battleship Game module
|   > Date started: 2008-02-28
|
|   > Module Version Number: 0.8.0
|
+---------------------------------------------------------------------------
*/

// TODO: comments & organize better

if (defined('INCLUDE_DIR')) {
	require_once INCLUDE_DIR.'func.array.php';
}

class Game
{

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** const property GAME_TABLE
	 *		Holds the game table name
	 *
	 * @var string
	 */
	const GAME_TABLE = T_GAME;


	/** const property GAME_BOARD_TABLE
	 *		Holds the game board table name
	 *
	 * @var string
	 */
	const GAME_BOARD_TABLE = T_GAME_BOARD;


	/** const property GAME_NUDGE_TABLE
	 *		Holds the game nudge table name
	 *
	 * @var string
	 */
	const GAME_NUDGE_TABLE = T_GAME_NUDGE;


	/** public property id
	 *		Holds the game's id
	 *
	 * @var int
	 */
	public $id;


	/** public property state
	 *		Holds the game's current state
	 *		can be one of 'Waiting', 'Placing', 'Playing', 'Finished'
	 *
	 * @var string (enum)
	 */
	public $state;


	/** public property method
	 *		Holds the game's play method
	 *		can be one of 'Five', 'Salvo', 'Single'
	 *
	 * @var string (enum)
	 */
	public $method;


	/** public property turn
	 *		Holds the game's current turn
	 *		can be one of 'white', 'black'
	 *
	 * @var string
	 */
	public $turn;


	/** public property paused
	 *		Holds the game's current pause state
	 *
	 * @var bool
	 */
	public $paused;


	/** public property create_date
	 *		Holds the game's create date
	 *
	 * @var int (unix timestamp)
	 */
	public $create_date;


	/** public property modify_date
	 *		Holds the game's modified date
	 *
	 * @var int (unix timestamp)
	 */
	public $modify_date;


	/** public property last_move
	 *		Holds the game's last move date
	 *
	 * @var int (unix timestamp)
	 */
	public $last_move;


	/** protected property _players
	 *		Holds our player's object references
	 *		along with other game data
	 *
	 * @var array of player data
	 */
	protected $_players;


	/** protected property _boards
	 *		Holds the battleship object references
	 *
	 * @var array of Battleship object references
	 */
	protected $_boards;


	/** protected property _history
	 *		Holds the board history
	 *
	 * @var array of battleship boards
	 */
	protected $_history;


	/** protected property _mysql
	 *		Stores a reference to the Mysql class object
	 *
	 * @param Mysql object
	 */
	protected $_mysql;



	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** public function __construct
	 *		Class constructor
	 *		Sets all outside data
	 *
	 * @param int optional game id
	 * @param Mysql optional object reference
	 * @action instantiates object
	 * @return void
	 */
	public function __construct($id = 0, Mysql $Mysql = null)
	{
		call(__METHOD__);

		$this->id = (int) $id;
		call($this->id);

		if (is_null($Mysql)) {
			$Mysql = Mysql::get_instance( );
		}

		$this->_mysql = $Mysql;

		try {
			$this->_pull( );
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function __destruct
	 *		Class destructor
	 *		Gets object ready for destruction
	 *
	 * @param void
	 * @action saves changed data
	 * @action destroys object
	 * @return void
	 */
	public function __destruct( )
	{
		// save anything changed to the database
		// BUT... only if PHP didn't die because of an error
		$error = error_get_last( );

		if ($this->id && (0 == ((E_ERROR | E_WARNING | E_PARSE) & $error['type']))) {
			try {
				$this->_save( );
			}
			catch (MyException $e) {
				// do nothing, it will be logged
			}
		}
	}


	/** public function __get
	 *		Class getter
	 *		Returns the requested property if the
	 *		requested property is not _private
	 *
	 * @param string property name
	 * @return mixed property value
	 */
	public function __get($property)
	{
		switch ($property) {
			case 'name' :
				if ($_SESSION['player_id'] == $this->_players['player']['player_id']) {
					return $this->_players['opponent']['object']->username;
				}
				else {
					return $this->_players['white']['object']->username.' vs '.$this->_players['black']['object']->username;
				}
				break;

			case 'first_name' :
				if ($_SESSION['player_id'] == $this->_players['player']['player_id']) {
					return 'Your';
				}
				else {
					return $this->_players['white']['object']->username.'\'s';
				}
				break;

			case 'second_name' :
				if ($_SESSION['player_id'] == $this->_players['player']['player_id']) {
					return $this->_players['opponent']['object']->username.'\'s';
				}
				else {
					return $this->_players['black']['object']->username.'\'s';
				}
				break;

			default :
				// go to next step
				break;
		}

		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existent property ('.$property.')', 2);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 2);
		}

		return $this->$property;
	}


	/** public function __set
	 *		Class setter
	 *		Sets the requested property if the
	 *		requested property is not _private
	 *
	 * @param string property name
	 * @param mixed property value
	 * @action optional validation
	 * @return bool success
	 */
	public function __set($property, $value)
	{
		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existent property ('.$property.')', 3);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 3);
		}

		$this->$property = $value;
	}


	/** public function invite
	 *		Creates the game from _POST data
	 *
	 * @param void
	 * @action creates a game
	 * @return int game id
	 */
	public function invite( )
	{
		call(__METHOD__);

		// DON'T sanitize the data
		// it gets sani'd in the MySQL->insert method
		$_P = $_POST;

		// translate (filter/sanitize) the data
		$_P['white_id'] = $_P['player_id'];
		$_P['black_id'] = $_P['opponent'];
		$_P['method'] = $_P['method'];

		// create the game
		$required = array(
			'white_id' ,
			'black_id' ,
			'method' ,
		);

		$key_list = $required;

		try {
			$_DATA = array_clean($_P, $key_list, $required);
		}
		catch (MyException $e) {
			throw $e;
		}

		$_DATA['state'] = 'Waiting';
		$_DATA['create_date '] = 'NOW( )'; // note the trailing space in the field name, this is not a typo


		// THIS IS THE ONLY PLACE IN THE CLASS WHERE IT BREAKS THE _pull / _save MENTALITY
		// BECAUSE I NEED THE INSERT ID FOR THE REST OF THE GAME FUNCTIONALITY

		$insert_id = $this->_mysql->insert(self::GAME_TABLE, $_DATA);

		if (empty($insert_id)) {
			throw new MyException(__METHOD__.': Game could not be created');
		}

		$this->id = $insert_id;

		$this->_create_blank_boards( );
		Email::send('invite', $_P['black_id'], array('name' => $GLOBALS['_PLAYERS'][$_P['white_id']]));

		// set the modified date
		$this->_mysql->insert(self::GAME_TABLE, array('modify_date' => NULL), " WHERE game_id = '{$this->id}' ");

		// pull the fresh data
		$this->_pull( );

		return $this->id;
	}


	/** public function resign
	 *		Resigns the given player from the game
	 *
	 * @param int player id
	 * @return void
	 */
	public function resign($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to resign from a game (#'.$this->id.') they are not playing in');
		}

		if ($this->_players['player']['player_id'] != $player_id) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to resign another player from a game (#'.$this->id.')');
		}
		// we need to edit the board of the person resigning if it is not their turn
		if ( ! $this->get_my_turn($player_id)) {
			$this->_boards['player']->board = str_replace('0', 'W', $this->_boards['player']->board);
		}

		$this->_players['opponent']['object']->add_win( );
		$this->_players['player']['object']->add_loss( );
		$this->state = 'Finished';
		Email::send('resigned', $this->_players['opponent']['object']->id, array('name' => $this->_players['player']['object']->username));
	}


	/** public function is_player
	 *		Tests if the given ID is a player in the game
	 *
	 * @param int player id
	 * @return bool player is in game
	 */
	public function is_player($player_id)
	{
		$player_id = (int) $player_id;

		return ((isset($this->_players['white']['player_id']) && ($player_id == $this->_players['white']['player_id']))
			|| (isset($this->_players['black']['player_id']) && ($player_id == $this->_players['black']['player_id'])));
	}


	/** public function get_my_color
	 *		Returns the current player's color
	 *
	 * @param void
	 * @return string current player's color (or false on failure)
	 */
	public function get_my_color( )
	{
		return ((isset($this->_players['player']['color'])) ? $this->_players['player']['color'] : false);
	}


	/** public function get_my_turn
	 *		Returns the current player's turn
	 *
	 * @param void
	 * @return bool is the current players turn
	 */
	public function get_my_turn( )
	{
		return ((isset($this->_players['player']['turn'])) ? $this->_players['player']['turn'] : false);
	}


	/** public function do_shots
	 *		Performs the shots requested
	 *
	 * @param array of human readable targets (or single target string)
	 * @action updates the board with the shots
	 * @return void
	 */
	public function do_shots($targets)
	{
		call(__METHOD__);

		// shoot !
		try {
			$shots = $this->get_shot_count( );
			foreach ((array) $targets as $target) {
				// only allow as many shots as they have
				if ( ! $shots--) { // read then decrement
					break;
				}

				$this->_boards['opponent']->do_shot($target);
			}

			$this->_test_winner( );
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function get_previous_shots
	 *		Grabs the previous shots made
	 *
	 * @param void
	 * @return array of computer string indexes and player color
	 */
	public function get_previous_shots( )
	{
		call(__METHOD__);

		if (2 > count($this->_history)) {
			return false;
		}
		else {
			if (is_null($this->_history[0]['white_board'])) {
				return array($this->_diff($this->_history[0]['black_board'], $this->_history[1]['black_board']), 'black');
			}
			else {
				return array($this->_diff($this->_history[0]['white_board'], $this->_history[1]['white_board']), 'white');
			}
		}
	}


	/** public function test_setup
	 *		Tests the game to see if this player
	 *		can still setup the board
	 *
	 * @param void
	 * @action tests player setup ability
	 * @return bool player can setup
	 */
	public function test_setup( )
	{
		call(__METHOD__);

		return ! $this->_players['player']['ready'];
	}


	/** public function test_ready
	 *		Tests the game to see if the boards are
	 *		set up and ready to go
	 *
	 * @param void
	 * @action sets the state to 'Playing' and sends an email
	 * @return bool game is ready
	 */
	public function test_ready( )
	{
		if (in_array($this->state, array('Waiting', 'Placing'))) {
			$ready = $this->_players['white']['ready'] && $this->_players['black']['ready'];

			// test both boards and make sure they both have all 5 boats on them
			$first = $this->get_missing_boats( );
			$second = $this->get_missing_boats(false);

			if ($ready && ! count($first) && ! count($second)) {
				$this->state = 'Playing';

				$player_ids = array( );
				$player_ids[] = $this->_players['white']['player_id'];
				$player_ids[] = $this->_players['black']['player_id'];

				Email::send('start', $player_ids, array('name' => $this->name));

				return true;
			}

			return false;
		}

		return true;
	}


	/** public function nudge
	 *		Nudges the given player to tke their move
	 *
	 * @param void
	 * @return bool success
	 */
	public function nudge( )
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$nudger = $this->_players['player']['object']->username;

		if ($this->test_nudge( )) {
			Email::send('nudge', $this->_players['opponent']['player_id'], array('id' => $this->id, 'name' => $this->name, 'player' => $nudger));
			$this->_mysql->delete(self::GAME_NUDGE_TABLE, " WHERE game_id = '{$this->id}' ");
			$this->_mysql->insert(self::GAME_NUDGE_TABLE, array('game_id' => $this->id, 'player_id' => $this->_players['opponent']['player_id']));
			return true;
		}

		return false;
	}


	/** public function test_nudge
	 *		Tests if the current player can be nudged or not
	 *
	 * @param void
	 * @return bool player can be nudged
	 */
	public function test_nudge( )
	{
		call(__METHOD__);

		$player_id = (int) $this->_players['opponent']['player_id'];

		if ($this->get_my_turn( ) || ('Finished' == $this->state) || $this->paused) {
			return false;
		}

		try {
			$nudge_time = Settings::read('nudge_flood_control');
		}
		catch (MyException $e) {
			return false;
		}

		if (-1 == $nudge_time) {
			return false;
		}
		elseif (0 == $nudge_time) {
			return true;
		}

		// check the nudge status for this game/player
		// 'now' is taken from the DB because it may
		// have a different time from the PHP server
		$query = "
			SELECT NOW( ) AS now
				, G.modify_date AS move_date
				, GN.nudged
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_NUDGE_TABLE." AS GN
					ON (GN.game_id = G.game_id
						AND GN.player_id = '{$player_id}')
			WHERE G.game_id = '{$this->id}'
		";
		$dates = $this->_mysql->fetch_assoc($query);

		if ( ! $dates) {
			return false;
		}

		// check the dates
		// if the move date is far enough in the past
		//  AND the player has not been nudged
		//   OR the nudge date is far enough in the past
		if ((strtotime($dates['move_date']) <= strtotime('-'.$nudge_time.' hour', strtotime($dates['now'])))
			&& ((empty($dates['nudged']))
				|| (strtotime($dates['nudged']) <= strtotime('-'.$nudge_time.' hour', strtotime($dates['now'])))))
		{
			return true;
		}

		return false;
	}


	/** public function get_players
	 *		Grabs the player array
	 *
	 * @param void
	 * @return array player data
	 */
	public function get_players( )
	{
		$players = array( );

		foreach (array('white','black') as $color) {
			$player_id = $this->_players[$color]['player_id'];
			$players[$player_id] = $this->_players[$color];
			$players[$player_id]['username'] = $this->_players[$color]['object']->username;
			unset($players[$player_id]['object']);
		}

		return $players;
	}


	/** public function get_board_html
	 *		Creates the board html based on the type
	 *			'first' - all squares are id'd with 'dfd'
	 *			'second' - all squares are id'd with 'tgt'
	 *
	 * @param string optional type of board to display
	 * @param bool optional pull direct from class
	 * @return string board html
	 */
	public function get_board_html($type = 'first', $direct = false)
	{
		call(__METHOD__);

		if ( ! isset($this->_players['player'])) {
			throw new MyException(__METHOD__.': Player session id is missing');
		}

		if ('first' == $type) {
			$color = $this->_players['player']['color'];
			$theirs = false;
		}
		else {
			$color = ('white' == $this->_players['player']['color']) ? 'black' : 'white';
			$theirs = ! ('Finished' == $this->state);
		}
		call($color);

		// grab the boards
		if ( ! $direct) {
			// grab the most recent board
			$board = $this->_history[0][$color.'_board'];
			if (is_null($board)) {
				$board = $this->_history[1][$color.'_board'];
			}
			call($board);
			call($this->_boards['player']->get_board_ascii($board));

			// grab the first board
			$orig_board = $this->_history[count($this->_history) - 1][$color.'_board'];
			call($orig_board);
			call($this->_boards['player']->get_board_ascii($orig_board));
		}
		else {
			$board = $orig_board = $this->_boards[$color]->board;
		}

		$letters = 'ABCDEFGHIJ';

		$html = '<div class="board '.$type.' '.$color.'">';
		$top = '<div class="row top"><div class="corner"></div><div>1</div><div>2</div><div>3</div><div>4</div><div>5</div><div>6</div><div>7</div><div>8</div><div>9</div><div>10</div><div class="corner"></div></div>';

		$html .= $top;

		for ($i = 0; $i < strlen($board); ++$i) {
			$j = str_pad($i, 2, '0', STR_PAD_LEFT);
			$tgt_id = ('first' == $type) ? " id=\"dfd-{$j}\"" : " id=\"tgt-{$j}\"";

			$side = '<div class="side">'.$letters[floor($i / 10)].'</div>';

			// add the border
			if (0 == ($i % 10)) {
				$html .= '<div class="row">'.$side;
			}

			switch($board[$i]) {
				case 'Y' : $img = '<img src="images/miss.gif" alt="miss" />';  break;
				case 'X' : $img = '<img src="images/hit.gif" alt="hit" />';   break;
				default  : $img = '&nbsp;'; break;
			}

			if ( ! $theirs) {
				switch (strtolower($orig_board[$i])) {
					case 'a' : // no break
					case 'f' : // no break
					case 'j' : // no break
					case 'm' : // no break
					case 'p' :
						$class = ' class="h-bow"';
						break;

					case 'b' : // no break
					case 'g' :
						$class = ' class="h-fore"';
						break;

					case 'c' : // no break
					case 'k' : // no break
					case 'n' :
						$class = ' class="h-mid"';
						break;

					case 'd' : // no break
					case 'h' :
						$class = ' class="h-aft"';
						break;

					case 'e' : // no break
					case 'i' : // no break
					case 'l' : // no break
					case 'o' : // no break
					case 'q' :
						$class = ' class="h-stern"';
						break;

					default  :
						$class = '';
						break;
				} // end switch

				// if it's vertical, switch the class
				if (strtolower($orig_board[$i]) != $orig_board[$i]) {
					$class = str_replace('h', 'v', $class);
				}
			}
			else { // theirs (don't show the boats)
				$class = '';
			}

			// put in the div
			$html .= "<div{$tgt_id}{$class}>{$img}</div>";

			// add the border
			if (9 == ($i % 10)) {
				$html .= $side.'</div>'."\n";
			}
		}
		$html .= str_replace('top', 'bottom', $top) . '</div>';

		// if there is no board
		if (100 != strlen($board)) {
			// let the opponent know about it
			$html = '<div class="noboard">This player has not set up their board yet.</div>';
		}
		call($html);

		return $html;
	}


	public function get_missing_boats($mine = true)
	{
		if ($mine) {
			return $this->_boards['player']->get_missing_boats( );
		}
		else {
			return $this->_boards['opponent']->get_missing_boats( );
		}
	}


	public function get_sunk( )
	{
		call(__METHOD__);

		// grab the boards for the player who's turn it is
		$this_board = $this->_history[0][$this->turn.'_board'];

		if (isset($this->_history[1][$this->turn.'_board'])) {
			$prev_board = $this->_history[1][$this->turn.'_board'];
		}
		else {
			return false;
		}

		if (is_null($this_board)) {
			$this_board = $prev_board;

			if (isset($this->_history[2][$this->turn.'_board'])) {
				$prev_board = $this->_history[2][$this->turn.'_board'];
			}
			else {
				return false;
			}
		}

		$diff = $this->_diff($this_board, $prev_board);

		$sunk = array( );

		// look where each shot was made and find out which ships were hit
		foreach($diff as $shot) {
			$hit = $prev_board[$shot];

			// if we can find the boat before the shot
			if ('0' != $hit) {
				switch (strtolower($hit)) {
					case 'a' : // no break
					case 'b' : // no break
					case 'c' : // no break
					case 'd' : // no break
					case 'e' :
						$pattern = '/[a-e]/i';
						$ship = 'Carrier';
						break;

					case 'f' : // no break
					case 'g' : // no break
					case 'h' : // no break
					case 'i' :
						$pattern = '/[f-i]/i';
						$ship = 'Battleship';
						break;

					case 'j' : // no break
					case 'k' : // no break
					case 'l' :
						$pattern = '/[jkl]/i';
						$ship = 'Cruiser';
						break;

					case 'm' : // no break
					case 'n' : // no break
					case 'o' :
						$pattern = '/[mno]/i';
						$ship = 'Submarine';
						break;

					case 'p' : // no break
					case 'q' :
						$pattern = '/[pq]/i';
						$ship = 'Destroyer';
						break;
				}

				// if we can't find it now
				if (0 == preg_match($pattern, $this_board)) {
					// it must have been sunk this round
					call('--SHIP SUNK--');
					$sunk[] = $ship;
				}
			} // end if hit check
		} // end foreach shot loop

		// sort it by name
		sort($sunk);
		call($sunk);

		return array_unique($sunk);
	}


	public function get_sunk_ships($board)
	{
		$missing = $this->get_missing_boats('my' == $board);

		$return = 'No boats sunk';
		if ($missing) {
			$return = "Sunk Boats:";
			foreach ($missing as $bow => $null) {
				switch ($bow) {
					case 'a' : $return .= "\n\tCarrier"; break;
					case 'f' : $return .= "\n\tBattleship"; break;
					case 'j' : $return .= "\n\tCruiser"; break;
					case 'm' : $return .= "\n\tSubmarine"; break;
					case 'p' : $return .= "\n\tDestroyer"; break;
				}
			}
		}

		return $return;
	}


	public function get_salvo_shots( )
	{
		return $this->_boards['player']->get_salvo_shots( );
	}


	public function get_shot_count( )
	{
		switch (strtolower($this->method)) {
			case 'five' :
				$shots = 5;
				break;

			case 'salvo' :
				$shots = $this->get_salvo_shots( );
				break;

			case 'single' :
			default :
				$shots = 1;
				break;
		}

		return $shots;
	}


	/** public function get_boats_html
	 *		Creates the boat html for placing boats
	 *
	 * @param array optional of ints boat sizes available
	 * @return string boat html
	 */
	public function get_boats_html($boats = -1)
	{
		call(__METHOD__);

		if (-1 == $boats) {
			$boats = $this->get_missing_boats(true);
		}

		$html = '<div class="boats">';

		foreach ($boats as $boat) 	{
			$html .= '<div class="boat">';

			switch ($boat) {
				case 5 :
					$html .= '<div class="h-bow">&nbsp;</div><div class="h-fore">&nbsp;</div><div class="h-mid">&nbsp;</div><div class="h-aft">&nbsp;</div><div class="h-stern">&nbsp;</div>';
					break;

				case 4 :
					$html .= '<div class="h-bow">&nbsp;</div><div class="h-fore">&nbsp;</div><div class="h-aft">&nbsp;</div><div class="h-stern">&nbsp;</div>';
					break;

				case 3 :
					$html .= '<div class="h-bow">&nbsp;</div><div class="h-mid">&nbsp;</div><div class="h-stern">&nbsp;</div>';
					break;

				case 2 :
					$html .= '<div class="h-bow">&nbsp;</div><div class="h-stern">&nbsp;</div>';
					break;
			}

			$html .= "</div>\n";
		}

		$html .= '</div>';

		return $html;
	}


	/** public function setup_clear_board
	 *		Clears the board
	 *
	 * @param void
	 * @action clears the board
	 * @return string html game board
	 */
	public function setup_clear_board( )
	{
		call(__METHOD__);

		try {
			$this->test_setup( );
			$this->_boards['player']->clear_board( );
			return $this->get_board_html('first', true);
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function setup_remove_boat
	 *		Removes the boat on the given square
	 *
	 * @param string board square
	 * @action removes the given boat
	 * @return string html game board
	 */
	public function setup_remove_boat($index)
	{
		call(__METHOD__);

		try {
			$this->test_setup( );
			$this->_boards['player']->remove_boat($index);
			return $this->get_board_html('first', true);
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function setup_random_boat
	 *		Places the given boat randomly
	 *
	 * @param int size of boat
	 * @action randomly places given boat
	 * @return string html game board
	 */
	public function setup_random_boat($size)
	{
		call(__METHOD__);

		try {
			$this->test_setup( );
			$this->_boards['player']->place_random_boat($size);
			return $this->get_board_html('first', true);
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function setup_random_board
	 *		Clears the board and sets up a
	 *		completely random board
	 *
	 * @param void
	 * @action sets up random board
	 * @return string html game board
	 */
	public function setup_random_board( )
	{
		call(__METHOD__);

		try {
			$this->test_setup( );
			$this->_boards['player']->generate_random_board( );
			return $this->get_board_html('first', true);
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function setup_place_boat_between
	 *		Places a boat between the two given squares
	 *
	 * @param string board square
	 * @param string board square
	 * @action places boat
	 * @return string html game board
	 */
	public function setup_place_boat_between($index1, $index2)
	{
		call(__METHOD__);

		try {
			$this->test_setup( );
			$this->_boards['player']->place_boat_between($index1, $index2);
			return $this->get_board_html('first', true);
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function setup_done
	 *		Finalizes the board setup
	 *
	 * @param void
	 * @action sets the game state to disallow further editing
	 * @return bool success
	 */
	public function setup_done( )
	{
		call(__METHOD__);

		if ($this->test_setup( )) {
			// make sure the player has placed all the boats
			if (count($this->get_missing_boats( ))) {
				throw new MyException(__METHOD__.': All boats must be placed before finalizing setup');
			}

			$this->_players['player']['ready'] = true;

			return true;
		}

		return false;
	}


	/** public function get_outcome
	 *		Returns the outcome string and outcome
	 *
	 * @param int id of observing player
	 * @return array (outcome text, outcome string)
	 */
	public function get_outcome($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		if ('Finished' != $this->state) {
			return false;
		}

		list($null, $loser) = $this->get_previous_shots( );
		$winner = ('white' == $loser) ? 'black' : 'white';

		if ($player_id == $this->_players[$winner]['player_id']) {
			return array('You Won !', 'won');
		}
		else {
			return array($this->_players[$winner]['object']->username.' Won', 'lost');
		}
	}


	/** public function write_game_file
	 *		TODO
	 *
	 * @param void
	 * @action void
	 * @return bool true
	 */
	public function write_game_file( )
	{
		// TODO: build a logging system to log game data
		return true;
	}


	/** protected function _pull
	 *		Pulls the data from the database
	 *		and sets up the objects
	 *
	 * @param void
	 * @action pulls the game data
	 * @return void
	 */
	protected function _pull( )
	{
		call(__METHOD__);

		if ( ! $this->id) {
			return false;
		}

		if ( ! $_SESSION['player_id']) {
			throw new MyException(__METHOD__.': Player id is not in session when pulling game data');
		}

		// grab the game data
		$query = "
			SELECT *
			FROM ".self::GAME_TABLE."
			WHERE game_id = '{$this->id}'
		";
		$result = $this->_mysql->fetch_assoc($query);
		call($result);

		if ( ! $result) {
			throw new MyException(__METHOD__.': Game data not found for game #'.$this->id);
		}

		if (($_SESSION['player_id'] != $result['white_id']) && ($_SESSION['player_id'] != $result['black_id']) && ('Finished' != $result['state'])) {
			throw new MyException(__METHOD__.': In progress game #'.$this->id.' being accessed by non-playing player ('.$_SESSION['player_id'].')');
		}

		// set the properties
		$this->state = $result['state'];
		$this->method = $result['method'];
		$this->paused = (bool) $result['paused'];
		$this->create_date = strtotime($result['create_date']);
		$this->modify_date = strtotime($result['modify_date']);

		// set up the players
		$this->_players['white']['player_id'] = $result['white_id'];
		$this->_players['white']['object'] = new GamePlayer($result['white_id']);

		$this->_players['black']['player_id'] = $result['black_id'];
		if (0 != $result['black_id']) { // we may have an open game
			$this->_players['black']['object'] = new GamePlayer($result['black_id']);
		}

		// we test this first one against the black id, so if it fails because
		// the person viewing the game is not playing in the game (viewing it
		// after it's finished) we want "player" to be equal to "white"
		if ($_SESSION['player_id'] == $result['black_id']) {
			$this->_players['player'] = & $this->_players['black'];
			$this->_players['player']['color'] = 'black';
			$this->_players['player']['opp_color'] = 'white';
			$this->_players['opponent'] = & $this->_players['white'];
			$this->_players['opponent']['color'] = 'white';
			$this->_players['opponent']['opp_color'] = 'black';
		}
		else {
			$this->_players['player'] = & $this->_players['white'];
			$this->_players['player']['color'] = 'white';
			$this->_players['player']['opp_color'] = 'black';
			$this->_players['opponent'] = & $this->_players['black'];
			$this->_players['opponent']['color'] = 'black';
			$this->_players['opponent']['opp_color'] = 'white';
		}

		$this->_players['white']['ready'] = (bool) $result['white_ready'];
		$this->_players['black']['ready'] = (bool) $result['black_ready'];

		// set up the boards
		$query = "
			SELECT *
			FROM ".self::GAME_BOARD_TABLE."
			WHERE game_id = '{$this->id}'
			ORDER BY move_date DESC
		";
		$result = $this->_mysql->fetch_array($query);
		call($result);

		if ($result) {
			$this->_history = $result;
			$this->last_move = strtotime($result[0]['move_date']);

			try {
				$this->_boards['white'] = new Battleship( );
				$this->_boards['black'] = new Battleship($result[0]['black_board']);

				// we may have to backtrack a bit to grab the white board
				if ( ! is_null($result[0]['white_board'])) {
					$this->_boards['white']->board = $result[0]['white_board'];
					$this->turn = 'white';
				}
				elseif ( ! empty($result[1]['white_board'])) {
					$this->_boards['white']->board = $result[1]['white_board'];
					$this->turn = 'black';
				}
			}
			catch (MyException $e) {
				throw $e;
			}
		}
		else {
			$this->last_move = $this->create_date;

			$this->_boards['white'] = new Battleship( );
			$this->_boards['black'] = new Battleship( );
		}

		if ('white' == $this->_players['player']['color']) {
			$this->_boards['player'] = & $this->_boards['white'];
			$this->_boards['opponent'] = & $this->_boards['black'];
		}
		else {
			$this->_boards['player'] = & $this->_boards['black'];
			$this->_boards['opponent'] = & $this->_boards['white'];
		}

		$this->_players[$this->turn]['turn'] = true;
	}


	/** protected function _save
	 *		Saves all changed data to the database
	 *
	 * @param void
	 * @action saves the game data
	 * @return void
	 */
	protected function _save( )
	{
		call(__METHOD__);

		// make sure we don't have a MySQL error here, it may be causing the issues
		$run_once = false;
		do {
			if ($run_once) {
				// pause for 3 seconds, then try again
				sleep(3);
			}

			// update the game data
			$query = "
				SELECT state
					, white_ready
					, black_ready
					, modify_date
				FROM ".self::GAME_TABLE."
				WHERE game_id = '{$this->id}'
			";
			$game = $this->_mysql->fetch_assoc($query);
			call($game);

			// make sure we don't have a MySQL error here, it may be causing the issues
			$error = $this->_mysql->error;
			$errno = preg_replace('/(\\d+)/', '$1', $error);

			$run_once = true;
		}
		while (2006 == $errno || 2013 == $errno);

		$update_modified = false;

		if ( ! $game) {
			throw new MyException(__METHOD__.': Game data not found for game #'.$this->id);
		}

		$this->_log('DATA SAVE: #'.$this->id.' @ '.time( )."\n".' - '.$this->modify_date."\n".' - '.strtotime($game['modify_date']));

		// test the modified date and make sure we still have valid data
		call($this->modify_date);
		call(strtotime($game['modify_date']));
		if ($this->modify_date != strtotime($game['modify_date'])) {
			$this->_log('== FAILED ==');
			throw new MyException(__METHOD__.': Trying to save game (#'.$this->id.') with out of sync data');
		}

		$update_game = false;
		call($game['state']);
		call($this->state);
		if ($game['state'] != $this->state) {
			$update_game['state'] = $this->state;
		}

		call($game['white_ready']);
		call($this->_players['white']['ready']);
		if ($game['white_ready'] != (int) $this->_players['white']['ready']) {
			$update_game['white_ready'] = (int) $this->_players['white']['ready'];
		}

		call($game['black_ready']);
		call($this->_players['black']['ready']);
		if ($game['black_ready'] != (int) $this->_players['black']['ready']) {
			$update_game['black_ready'] = (int) $this->_players['black']['ready'];
		}

		if ($update_game) {
			$update_modified = true;
			$this->_mysql->insert(self::GAME_TABLE, $update_game, " WHERE game_id = '{$this->id}' ");
		}

		// update the boards
		$color = $this->_players['player']['color'];
		call($color);
		if (in_array($this->state, array('Waiting', 'Placing'))) {
			call('SETUP SAVE');

			// grab the first boards from the database
			$query = "
				SELECT *
				FROM ".self::GAME_BOARD_TABLE."
				WHERE game_id = '{$this->id}'
				ORDER BY move_date ASC
				LIMIT 1
			";
			$boards = $this->_mysql->fetch_assoc($query);
			call($boards);

			call($this->_boards);

			if ($boards[$color.'_board'] != $this->_boards['player']->board) {
				$update_modified = true;
				$where = " WHERE game_id = '{$boards['game_id']}' AND move_date = '{$boards['move_date']}' ";
				$this->_mysql->insert(self::GAME_BOARD_TABLE, array($color.'_board' => $this->_boards['player']->board), $where);
			}
		}
		else {
			call('IN-GAME SAVE');

			// grab the current boards from the database
			$query = "
				SELECT *
				FROM ".self::GAME_BOARD_TABLE."
				WHERE game_id = '{$this->id}'
				ORDER BY move_date DESC
				LIMIT 2
			";
			$boards = $this->_mysql->fetch_array($query);
			call($boards);

			$white_board = $this->_boards['white']->board;
			$black_board = $this->_boards['black']->board;
			call($white_board);
			call($black_board);

			// only one should be different at a time
			if ($black_board != $boards[0]['black_board']) {
				call('UPDATED BLACK');
				$update_modified = true;
				$this->_mysql->insert(self::GAME_BOARD_TABLE, array('black_board' => $black_board, 'game_id' => $this->id));
			}
			elseif (is_null($boards[0]['white_board']) && ($white_board != $boards[1]['white_board'])) {
				call('UPDATED WHITE');
				$update_modified = true;
				$this->_mysql->insert(self::GAME_BOARD_TABLE, array('white_board' => $white_board), " WHERE game_id = '{$this->id}' AND move_date = '{$boards[0]['move_date']}' ");
			}
		}

		// update the game modified date
		if ($update_modified) {
			$this->_mysql->insert(self::GAME_TABLE, array('modify_date' => NULL), " WHERE game_id = '{$this->id}' ");
		}
	}


	/** protected function _log
	 *		Report messages to a file
	 *
	 * @param string message
	 * @action log messages to file
	 * @return void
	 */
	protected function _log($msg)
	{
		// log the error
		if (false && class_exists('Log')) {
			Log::write($msg, __CLASS__);
		}
	}


	/** protected function _test_winner
	 *		Tests for a winner in the game
	 *
	 * @param void
	 * @action takes appropriate action if a winner is found
	 * @return void
	 */
	protected function _test_winner( )
	{
		call(__METHOD__);

		$color = $this->_players['player']['opp_color'];
		call($color);

		$match = preg_match('/[a-q]/i', $this->_boards[$color]->board);
		call($match);

		if ( ! $match) {
			$this->_players['player']['object']->add_win( );
			$this->_players['opponent']['object']->add_loss( );
			$this->state = 'Finished';
			Email::send('defeated', $this->_players['opponent']['object']->id, array('name' => $this->_players['player']['object']->username));
		}
		else {
			Email::send('turn', $this->_players['opponent']['object']->id, array('name' => $this->_players['player']['object']->username));
		}
	}


	/** protected function _create_blank_boards
	 *		Initializes the game board table with blank
	 *		boards for the setup page
	 *
	 * @param void
	 * @action initializes blank boards in the game board table
	 * @return void
	 */
	protected function _create_blank_boards( )
	{
		if ( ! empty($this->id)) {
			$data['game_id'] = (int) $this->id;
			$data['white_board'] = $data['black_board'] = str_repeat('0', 100);

			$this->_mysql->insert(self::GAME_BOARD_TABLE, $data);
		}
	}


	/** protected function _diff
	 *		Compares two boards are returns the
	 *		indexes of any differences
	 *
	 * @param string board
	 * @param string board
	 * @return array of difference indexes
	 */
	protected function _diff($board1, $board2)
	{
		$diff = array( );
		for ($i = 0; $i < 100; ++$i) {
			if ($board1[$i] != $board2[$i]) {
				$diff[] = $i;
			}
		}
		call($diff);

		return $diff;
	}


	/**
	 *		STATIC METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** static public function get_list
	 *		Returns a list array of all the games in the database
	 *		with games which need the users attention highlighted
	 *
	 *		NOTE: $player_id is required when not pulling all games
	 *		(when $all is false)
	 *
	 * @param int optional player's id
	 * @param bool optional pull all games (vs only given player's games)
	 * @return array game list (or bool false on failure)
	 */
	static public function get_list($player_id = 0, $all = true)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		if ( ! $all && ! $player_id) {
			throw new MyException(__METHOD__.': Player ID required when not pulling all games');
		}

		$WHERE = " WHERE G.state <> 'Waiting' ";
		if ( ! $all) {
			$WHERE .= "
					AND G.state <> 'Finished'
					AND (G.white_id = {$player_id}
						OR G.black_id = {$player_id})
			";
		}

		$query = "
			SELECT G.*
				, IF((0 = MAX(GB.move_date)) OR MAX(GB.move_date) IS NULL, G.create_date, MAX(GB.move_date)) AS last_move
				, 0 AS my_turn
				, 0 AS in_game
				, W.username AS white
				, B.username AS black
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_BOARD_TABLE." AS GB
					ON GB.game_id = G.game_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS W
					ON W.player_id = G.white_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS B
					ON B.player_id = G.black_id
			{$WHERE}
			GROUP BY game_id
			ORDER BY state ASC
				, last_move ASC
		";
		$list = $Mysql->fetch_array($query);

		if (0 != $player_id) {
			// run though the list and find games the user needs action on
			foreach ($list as $key => $game) {
				$query = "
					SELECT IF(white_board IS NULL, 'black', 'white') AS turn
					FROM ".self::GAME_BOARD_TABLE."
					WHERE game_id = '{$game['game_id']}'
					ORDER BY move_date DESC
					LIMIT 1
				";
				$game['turn'] = $Mysql->fetch_value($query);

				$game['in_game'] = (int) (($player_id == $game['white_id']) || ($player_id == $game['black_id']));
				$game['my_turn'] = (int) ( ! empty($game['turn']) && ($player_id == $game[$game['turn'].'_id']));

				if ('Finished' == $game['state']) {
					$game['my_turn'] = 0;
					$game['in_game'] = 1;
				}

				$game['my_color'] = ($player_id == $game['white_id']) ? 'white' : 'black';
				$game['opp_color'] = ($player_id == $game['white_id']) ? 'black' : 'white';

				$game['opponent'] = ($player_id == $game['white_id']) ? $game['black'] : $game['white'];

				$list[$key] = $game;
			}
		}

		return $list;
	}


	/** static public function get_invites
	 *		Returns a list array of all the invites in the database
	 *		for the given player
	 *
	 * @param int player's id
	 * @return array game list (or bool false on failure)
	 */
	static public function get_invites($player_id)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		$query = "
			SELECT G.*
				, IF((0 = MAX(GB.move_date)) OR MAX(GB.move_date) IS NULL, G.create_date, MAX(GB.move_date)) AS last_move
				, (G.black_id = {$player_id}) AS invite
				, W.username AS white
				, B.username AS black
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_BOARD_TABLE." AS GB
					ON GB.game_id = G.game_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS W
					ON W.player_id = G.white_id
				LEFT JOIN ".Player::PLAYER_TABLE." AS B
					ON B.player_id = G.black_id
			WHERE G.state = 'Waiting'
				AND (G.white_id = {$player_id}
					OR G.black_id = {$player_id})
			GROUP BY game_id
			ORDER BY invite DESC
				, last_move DESC
		";
		$list = $Mysql->fetch_array($query);

		return $list;
	}


	/** static public function get_count
	 *		Returns a count of all games in the database,
	 *		as well as the highest game id (the total number of games played)
	 *
	 * @param void
	 * @return array (int current game count, int total game count)
	 */
	static public function get_count($player_id = 0)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		// games in play
		$query = "
			SELECT COUNT(*)
			FROM ".self::GAME_TABLE."
			WHERE state <> 'Finished'
		";
		$count = $Mysql->fetch_value($query);

		// total games
		$query = "
			SELECT MAX(game_id)
			FROM ".self::GAME_TABLE."
		";
		$next = $Mysql->fetch_value($query);

		return array($count, $next);
	}


	/** static public function check_turns
	 *		Checks if it's the given player's turn in any games
	 *
	 * @param int player id
	 * @return int number of games player has a turn in
	 */
	static public function check_turns($player_id)
	{
		call(__METHOD__);

		try {
			$list = self::get_list($player_id, false);
		}
		catch (MyException $e) {
			throw $e;
		}

		$count = 0;
		foreach ($list as $entry) {
			if ($entry['my_turn']) {
				++$count;
			}
		}

		return $count;
	}


	/** static public function get_my_count
	 *		Returns a count of all given player's games in the database,
	 *		as well as the games in which it is the player's turn
	 *
	 * @param int player id
	 * @return array (int player game count, int turn game count)
	 */
	static public function get_my_count($player_id)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		// games in play
		$query = "
			SELECT game_id
				, IF(white_id = {$player_id}, 'white', 'black') AS color
			FROM ".self::GAME_TABLE."
			WHERE state = 'Playing'
				AND (white_id = '{$player_id}'
					OR black_id = '{$player_id}'
				)
		";
		$games = $Mysql->fetch_array($query);
		$mine = count($games);

		// games with turns
		$turn = 0;
		foreach ($games as $game) {
			$query = "
				SELECT IF(white_board IS NULL, 'black', 'white') AS cur_turn
				FROM ".self::GAME_BOARD_TABLE."
				WHERE game_id = '{$game['game_id']}'
				ORDER BY move_date DESC
				LIMIT 1
			";
			$cur_turn = $Mysql->fetch_value($query);

			if ($cur_turn == $game['color']) {
				++$turn;
			}
		}

		return array($mine, $turn);
	}


	/** public function delete_inactive
	 *		Deletes the inactive games from the database
	 *
	 * @param int age in days (0 = disable)
	 * @action deletes the inactive games
	 * @return void
	 */
	static public function delete_inactive($age)
	{
		$Mysql = Mysql::get_instance( );

		$age = (int) $age;

		if ( ! $age) {
			return;
		}

		$query = "
			SELECT game_id
			FROM ".self::GAME_TABLE."
			WHERE modify_date < DATE_SUB(NOW( ), INTERVAL {$age} DAY)
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** public function delete_finished
	 *		Deletes the finished games from the database
	 *
	 * @param int age in days (0 = disable)
	 * @action deletes the finished games
	 * @return void
	 */
	static public function delete_finished($age)
	{
		$Mysql = Mysql::get_instance( );

		$age = (int) $age;

		if ( ! $age) {
			return;
		}

		$query = "
			SELECT game_id
			FROM ".self::GAME_TABLE."
			WHERE state = 'Finished'
				AND modify_date < DATE_SUB(NOW( ), INTERVAL {$age} DAY)
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** static public function delete
	 *		Deletes the given game and all related data
	 *
	 * @param mixed array or csv of game ids
	 * @action deletes the game and all related data from the database
	 * @return void
	 */
	static public function delete($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No game ids given');
		}

#		foreach ($ids as $id) {
#			self::write_game_file($id);
#		}

		$tables = array(
			self::GAME_BOARD_TABLE ,
			self::GAME_TABLE ,
		);

		$Mysql->multi_delete($tables, " WHERE game_id IN (".implode(',', $ids).") ");

		$query = "
			OPTIMIZE TABLE ".self::GAME_TABLE."
				, ".self::GAME_BOARD_TABLE."
		";
		$Mysql->query($query);
	}


	/** static public function player_deleted
	 *		Deletes the games the given players are in
	 *
	 * @param mixed array or csv of player ids
	 * @action deletes the players games
	 * @return void
	 */
	static public function player_deleted($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No player ids given');
		}

		$query = "
			SELECT DISTINCT(game_id)
			FROM ".self::GAME_TABLE."
			WHERE white_id IN (".implode(',', $ids).")
				OR black_id IN (".implode(',', $ids).")
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** static public function pause
	 *		Pauses the given games
	 *
	 * @param mixed array or csv of game ids
	 * @param bool optional pause game (false = unpause)
	 * @action pauses the games
	 * @return void
	 */
	static public function pause($ids, $pause = true)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		$pause = (int) (bool) $pause;

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No game ids given');
		}

		$Mysql->insert(self::GAME_TABLE, array('paused' => $pause), " WHERE game_id IN (".implode(',', $ids).") ");
	}


} // end of Game class


/*		schemas
// ===================================

Game table
----------------------
CREATE TABLE IF NOT EXISTS `bs_game` (
  `game_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `white_id` int(10) unsigned DEFAULT NULL,
  `black_id` int(10) unsigned DEFAULT NULL,
  `state` enum('Waiting', 'Placing', 'Playing', 'Finished') COLLATE latin1_general_ci NOT NULL DEFAULT 'Waiting',
  `white_ready` tinyint(1) NOT NULL DEFAULT '0',
  `black_ready` tinyint(1) NOT NULL DEFAULT '0',
  `method` enum('Single', 'Five', 'Salvo') COLLATE latin1_general_ci NOT NULL DEFAULT 'Single',
  `paused` tinyint(1) NOT NULL DEFAULT '0',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modify_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`game_id`),
  KEY `state` (`state`),
  KEY `white_id` (`white_id`),
  KEY `black_id` (`black_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;


Boards Table
----------------------
CREATE TABLE IF NOT EXISTS `bs_game_board` (
  `game_id` int(10) unsigned NOT NULL DEFAULT 0,
  `white_board` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `black_board` varchar(100) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `move_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',

  KEY `game_id` (`game_id`),
  KEY `move_date` (`move_date`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `wr_game_nudge`
--

DROP TABLE IF EXISTS `bs_game_nudge`;
CREATE TABLE IF NOT EXISTS `bs_game_nudge` (
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `player_id` int(10) unsigned NOT NULL DEFAULT '0',
  `nudged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY `game_player` (`game_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ;



*/


