<?php

require_once 'includes/inc.global.php';

// grab the game id
if (isset($_GET['id'])) {
	$_SESSION['game_id'] = (int) $_GET['id'];
}
else {
	if ( ! isset($_SESSION['game_id'])) {
		if ( ! defined('DEBUG') || ! DEBUG) {
			Flash::store('No Game Id Given !');
		}
		else {
			call('NO GAME ID GIVEN');
		}

		exit;
	}
}

// ALL GAME FORM SUBMISSIONS ARE AJAXED THROUGH /scripts/setup.js

// load the game
// always refresh the game data, there may be more than one person online
try {
	$Game = new Game($_SESSION['game_id']);

	if ( ! $Game->test_setup( )) {
		if ( ! defined('DEBUG') || ! DEBUG) {
			session_write_close( );
			header('Location: game.php?id='.$_SESSION['game_id'].$GLOBALS['_&_DEBUG_QUERY']);
		}
		else {
			call('GAME IS PLAYING, REDIRECTED TO game.php?id='.$_SESSION['game_id'].$GLOBALS['_&_DEBUG_QUERY'].' AND QUIT');
		}

		exit;
	}
	elseif (isset($_GET['accept'])) {
		$Game->set_state('Placing');
	}
}
catch (MyException $e) {
	if ( ! defined('DEBUG') || ! DEBUG) {
		Flash::store('Error Accessing Game !');
	}
	else {
		call('ERROR ACCESSING GAME');
	}

	exit;
}


$meta['title'] = GAME_NAME.' Game #'.$_SESSION['game_id'].':'.$Game->name.' Setup';
$meta['show_menu'] = false;
$meta['head_data'] = '
	<link rel="stylesheet" type="text/css" media="screen" href="css/board.css" />
	<script type="text/javascript">//<![CDATA[
		var game_id = "'.$_SESSION['game_id'].'";
		var color = "'.$Game->get_my_color( ).'";
	//]]></script>
	<script type="text/javascript" src="scripts/setup.js"></script>
';

$hints = array(
	'Here you can set up your board.' ,
	'Click any two squares to place a boat between those two squares.' ,
	'Click any unplaced boat to randomly place that boat.' ,
	'Click any placed boat to remove that boat.' ,
	'"Random Board" will randomly place ALL the boats, not just the unplaced ones.' ,
);

$contents = '';

$contents .= '<div id="board_wrapper">'.$Game->get_board_html('first')."</div>\n\n";

// the forms we'll need to submit
$contents .= '
	<div class="forms">
		<form method="post" action="'.$_SERVER['REQUEST_URI'].'"><div class="formdiv">
			<input type="hidden" name="notoken" value="1" />
			<input type="hidden" name="game_id" value="'.$_SESSION['game_id'].'" />
			<input type="hidden" name="method" id="method" value="" />
			<input type="hidden" name="value" id="value" value="" />
			<input type="button" class="button" id="clear" value="Clear Board" />
			<input type="button" class="button" id="random" value="Random Board" />
			<input type="button" class="button" id="done" value="Done" />
		</div></form>
	</div>
';

$contents .= '<div id="boat_wrapper">'.$Game->get_boats_html( )."</div>\n\n";

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
echo get_footer( );

