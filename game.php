<?php

require_once 'includes/inc.global.php';

// grab the game id
if (isset($_GET['id'])) {
	$_SESSION['game_id'] = (int) $_GET['id'];
}
elseif ( ! isset($_SESSION['game_id'])) {
	if ( ! defined('DEBUG') || ! DEBUG) {
		Flash::store('No Game Id Given !');
	}
	else {
		call('NO GAME ID GIVEN');
	}

	exit;
}

// ALL GAME FORM SUBMISSIONS ARE AJAXED THROUGH /scripts/game.js

// load the game
// always refresh the game data, there may be more than one person online
try {
	$Game = new Game((int) $_SESSION['game_id']);

	if ( ! $Game->test_ready( )) {
		if ( ! defined('DEBUG') || ! DEBUG) {
			session_write_close( );
			header('Location: setup.php?id='.$_SESSION['game_id'].$GLOBALS['_&_DEBUG_QUERY']);
		}
		else {
			call('GAME IS INCOMPLETE, REDIRECTED TO setup.php?id='.$_SESSION['game_id'].$GLOBALS['_&_DEBUG_QUERY'].' AND QUIT');
		}

		exit;
	}
}
catch (MyException $e) {
	if ( ! defined('DEBUG') || ! DEBUG) {
		Flash::store('Error Accessing Game !');
	}
	else {
		call('ERROR ACCESSING GAME :'.$e->outputMessage( ));
	}

	exit;
}

$players = $Game->get_players( );
$Chat = new Chat($_SESSION['player_id'], $_SESSION['game_id']);
$chat_data = $Chat->get_box_list( );

$chat_html = '
		<div id="chatbox">
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post"><div>
				<input id="chat" type="text" name="chat" />
				<label for="private" class="inline"><input type="checkbox" name="private" id="private" value="yes" /> Private</label>
			</div></form>
			<dl id="chats">';

if (is_array($chat_data)) {
	foreach ($chat_data as $chat) {
		if ('' == $chat['username']) {
			$chat['username'] = '[deleted]';
		}

		$color = '';
		if (isset($players[$chat['player_id']]['color'])) {
			$color = substr($players[$chat['player_id']]['color'], 0, 3);
		}

		// preserve spaces in the chat text
		$chat['message'] = htmlentities($chat['message'], ENT_QUOTES, 'ISO-8859-1', false);
		$chat['message'] = str_replace("\t", '    ', $chat['message']);
		$chat['message'] = str_replace('  ', ' &nbsp;', $chat['message']);

		$chat_html .= '
				<dt class="'.$color.'"><span>'.$chat['create_date'].'</span> '.$chat['username'].'</dt>
				<dd'.($chat['private'] ? ' class="private"' : '').'>'.$chat['message'].'</dd>';
	}
}

$chat_html .= '
			</dl> <!-- #chats -->
		</div> <!-- #chatbox -->';

// hide the chat from non-players
if (('Finished' == $Game->state) && ! $Game->is_player($_SESSION['player_id'])) {
	$chat_html = '';
}

$shots = $Game->get_shot_count( );

// grab the previous shots
list($prev_shots, $prev_color) = $Game->get_previous_shots( );

// grab any previous sinkings
$sunk = $Game->get_sunk( );

$sunk_text = '';
$win_text = '';
if ($sunk) {
	$sunk_text = ($Game->get_my_turn( )) ? $Game->name.' sunk your ' : 'You sunk '.$Game->name.'\'s ';
	foreach ($sunk as $ship) {
		$sunk_text .= $ship.', ';
	}

	$sunk_text = substr($sunk_text, 0, -2);
}

$turn = ($Game->get_my_turn( )) ? 'Your Turn' : $Game->name.'\'s Turn';
$no_turn = false;

if ($Game->paused) {
	$turn = 'PAUSED';
	$no_turn = true;
}
elseif ('Finished' == $Game->state) {
	$turn = 'GAME OVER';
	$no_turn = true;
	$prev_shots = false;
	$sunk_text = '';
	$shots = 0;
	list($win_text, $outcome) = $Game->get_outcome($_SESSION['player_id']);
}

$info_bar = '<span class="turn">'.$turn.'</span>';

// game is not finished and it's this player's turn
if (('Finished' != $Game->state) && $Game->get_my_turn( ) && ! $no_turn) {
	$info_bar .= ' <span class="shots">'.$Game->method.' '.plural($shots, 'Shot').': </span>';
}

$player_boats = 5 - count($Game->get_missing_boats($mine = true));
$opponent_boats = 5 - count($Game->get_missing_boats($mine = false));

$meta['title'] = $turn.' - '.$Game->name.' (#'.$_SESSION['game_id'].')';
$meta['show_menu'] = false;
$meta['head_data'] = '
	<link rel="stylesheet" type="text/css" media="screen" href="css/board.css" />

	<script type="text/javascript">//<![CDATA[
		var state = "'.(( ! $Game->paused) ? strtolower($Game->state) : 'paused').'";
		var shots = '.$shots.';
		var prev_shots = ['.implode(',', (array) $prev_shots).'];
		var prev_color = "'.$prev_color.'";
		var last_move = '.$Game->last_move.';
		var my_turn = '.(( ! $Game->get_my_turn( ) || $no_turn) ? 'false' : 'true').';
		var pre_hide_board = '.(($GLOBALS['Player']->pre_hide_board) ? 'true' : 'false').';
	//]]></script>
	<script type="text/javascript" src="scripts/game.js"></script>
';

echo get_header($meta);

?>

		<div id="contents">
			<ul id="buttons">
				<li><a href="index.php<?php echo $GLOBALS['_?_DEBUG_QUERY']; ?>">Main Page</a></li>
				<li><a href="game.php<?php echo $GLOBALS['_?_DEBUG_QUERY']; ?>">Reload Game Board</a></li>
			</ul>
			<h2>Game #<?php echo $_SESSION['game_id'].' vs '.htmlentities($Game->name, ENT_QUOTES, 'ISO-8859-1', false); ?> <?php echo $info_bar; ?></h2>

			<?php if ('' != $sunk_text) { ?>
			<div class="msg sunk"><?php echo $sunk_text; ?></div>
			<?php } ?>

			<?php if ('' != $win_text) { ?>
			<div class="msg <?php echo $outcome; ?>"><?php echo $win_text; ?></div>
			<?php } ?>

			<div id="boards" class="active">
				<div id="board_data">
					<div class="board_info"><span class="fleet"><?php echo $Game->first_name; ?> Fleet</span><span class="ships" id="my_ships"><?php echo $player_boats.' '.plural($player_boats, 'Ship'); ?> Remaining</span></div>
					<div class="board_info"><span class="fleet"><?php echo $Game->second_name; ?> Fleet</span><span class="ships" id="opp_ships"><?php echo $opponent_boats.' '.plural($opponent_boats, 'Ship'); ?> Remaining</span></div>
				</div>
				<?php echo $Game->get_board_html('first'); ?>
				<?php echo $Game->get_board_html('second'); ?>
			</div>

			<div id="chat">
				<?php echo $chat_html; ?>
			</div>

			<form id="game" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"><div class="formDiv">
				<input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>" />
				<input type="hidden" name="game_id" value="<?php echo $_SESSION['game_id']; ?>" />
				<input type="hidden" name="player_id" value="<?php echo $_SESSION['player_id']; ?>" />
				<input type="hidden" name="shots" id="shots" value="" />
				<?php if ('Playing' == $Game->state) { ?>
					<input type="button" name="resign" id="resign" value="Resign" />
				<?php } ?>
				<?php if ($Game->test_nudge( )) { ?>
					<input type="button" name="nudge" id="nudge" value="Nudge" />
				<?php } ?>
			</div></form>

		</div> <!-- #contents -->

<?php

call($GLOBALS);
echo get_footer( );

