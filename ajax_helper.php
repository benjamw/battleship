<?php

$GLOBALS['NODEBUG'] = true;
$GLOBALS['AJAX'] = true;


// don't require log in when testing for used usernames and emails
if (isset($_POST['validity_test']) || (isset($_GET['validity_test']) && isset($_GET['DEBUG']))) {
	define('LOGIN', false);
}


require_once 'includes/inc.global.php';


// if we are debugging, change some things for us
// (although REQUEST_METHOD may not always be valid)
if (('GET' == $_SERVER['REQUEST_METHOD']) && defined('DEBUG') && DEBUG) {
	$GLOBALS['NODEBUG'] = false;
	$GLOBALS['AJAX'] = false;
	$_GET['token'] = $_SESSION['token'];
	$_GET['keep_token'] = true;
	$_POST = $_GET;
	$DEBUG = true;
	call('AJAX HELPER');
	call($_POST);
}


// run the index page refresh checks
if (isset($_POST['timer'])) {
	$message_count = (int) Message::check_new($_SESSION['player_id']);
	$turn_count = (int) Game::check_turns($_SESSION['player_id']);
	echo $message_count + $turn_count;
	exit;
}


// run registration checks
if (isset($_POST['validity_test'])) {
#	if (('email' == $_POST['type']) && ('' == $_POST['value'])) {
#		echo 'OK';
#		exit;
#	}

	$player_id = 0;
	if ( ! empty($_POST['profile'])) {
		$player_id = (int) $_SESSION['player_id'];
	}

	switch ($_POST['validity_test']) {
		case 'username' :
		case 'email' :
			$username = '';
			$email = '';
			${$_POST['validity_test']} = sani($_POST['value']);

			$player_id = (isset($_POST['player_id']) ? (int) $_POST['player_id'] : 0);

			try {
				Player::check_database($username, $email, $player_id);
			}
			catch (MyException $e) {
				echo $e->getCode( );
				exit;
			}
			break;

		default :
			break;
	}

	echo 'OK';
	exit;
}


// run the in game chat
if (isset($_POST['chat'])) {
	try {
		if ( ! isset($_SESSION['game_id'])) {
			$_SESSION['game_id'] = 0;
		}

		$Chat = new Chat((int) $_SESSION['player_id'], (int) $_SESSION['game_id']);
		$Chat->send_message($_POST['chat'], isset($_POST['private']), isset($_POST['lobby']));
		$return = $Chat->get_box_list(1);
		$return = $return[0];
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// run the invites stuff
if (isset($_POST['action']) && ('delete' == $_POST['action'])) {
	try {
		Game::delete($_POST['game_id']);
		echo 'Game Deleted';
	}
	catch (MyEception $e) {
		echo 'ERROR: Could not delete game';
	}

	exit;
}


// init our game
$Game = new Game((int) $_SESSION['game_id']);


// run the game refresh check
if (isset($_POST['refresh'])) {
	echo $Game->last_move;
	exit;
}


// run the ship count clicks
if (isset($_POST['shipcheck'])) {
	try {
		echo $Game->get_sunk_ships($_POST['id']);
	}
	catch (MyException $e) {
		echo 'ERROR';
	}

	exit;
}


// do some more validity checking for the rest of the functions

if (empty($DEBUG) && empty($_POST['notoken'])) {
	test_token( ! empty($_POST['keep_token']));
}


if ($_POST['game_id'] != $_SESSION['game_id']) {
	echo 'ERROR: Incorrect game id given';
	exit;
}


// run the board setup
if (isset($_POST['method'])) {
	$return = array( );

	try {
		if (isset($_POST['done'])) {
			$Game->setup_done( );
			$return['redirect'] = (($Game->test_ready( )) ? 'game.php?id='.$Game->id : 'index.php');
		}
		else {
			switch ($_POST['method']) {
				case 'clear' :
					$Game->setup_action('clear_board');
					break;

				case 'random' :
					$Game->setup_action('random_board');
					break;

				case 'between' :
					list($value1, $value2) = explode(':', $_POST['value']);
					$Game->setup_action('boat_between', $value1, $value2);
					break;

				case 'random_boat' :
					$Game->setup_action('random_boat', $_POST['value']);
					break;

				case 'remove' :
					$Game->setup_action('remove_boat', $_POST['value']);
					break;
			} // end method switch

			$return['board'] = $Game->get_board_html('first', true);
			$return['boats'] = $Game->get_boats_html( );
		}
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// make sure we are the player we say we are
// unless we're an admin, then it's ok
$player_id = (int) $_POST['player_id'];
if (($player_id != $_SESSION['player_id']) && ! $GLOBALS['Player']->is_admin) {
	echo 'ERROR: Incorrect player id given';
	exit;
}


// run the 'Nudge' button
if (isset($_POST['nudge'])) {
	$return = array( );
	$return['token'] = $_SESSION['token'];

	try {
		$Game->nudge($player_id);
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// run the 'Resign' button
if (isset($_POST['resign'])) {
	$return = array( );
	$return['token'] = $_SESSION['token'];

	try {
		$Game->resign($_SESSION['player_id']);
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}


// run the shots
if (isset($_POST['shots'])) {
	$return = array( );
	$return['token'] = $_SESSION['token'];

	// clean up the shots
	$_POST['shots'] = explode(',', trim($_POST['shots'], ', '));

	try {
		$Game->do_shots($_POST['shots']);
		$return['action'] = 'RELOAD';
	}
	catch (MyException $e) {
		$return['error'] = 'ERROR: '.$e->outputMessage( );
	}

	echo json_encode($return);
	exit;
}

