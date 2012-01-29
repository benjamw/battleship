<?php

require_once 'includes/inc.global.php';

// this has nothing to do with creating a game
// but I'm running it here to prevent long load
// times on other pages where it would be run more often
GamePlayer::delete_inactive(Settings::read('expire_users'));
Game::delete_inactive(Settings::read('expire_games'));
Game::delete_finished(Settings::read('expire_finished_games'));

$Game = new Game( );

if (isset($_POST['invite'])) {
	// make sure this user is not full
	if ($GLOBALS['Player']->max_games && ($GLOBALS['Player']->max_games <= $GLOBALS['Player']->current_games)) {
		Flash::store('You have reached your maximum allowed games !', false);
	}

	test_token( );

	try {
		$game_id = $Game->invite( );
		Flash::store('Invitation Sent Successfully', 'setup.php?id='.$game_id.$GLOBALS['_&_DEBUG_QUERY']);
	}
	catch (MyException $e) {
		Flash::store('Invitation FAILED !', false);
	}
}

// grab the full list of players
$players_full = GamePlayer::get_list(true);
$invite_players = array_shrink($players_full, 'player_id');

// grab the players who's max game count has been reached
$players_maxed = GamePlayer::get_maxed( );
$players_maxed[] = $_SESSION['player_id'];

// remove the maxed players from the invite list
$players = array_diff($invite_players, $players_maxed);

$opponent_selection = '';
foreach ($players_full as $player) {
	if ($_SESSION['player_id'] == $player['player_id']) {
		continue;
	}

	if (in_array($player['player_id'], $players)) {
		$opponent_selection .= '
			<option value="'.$player['player_id'].'">'.$player['username'].'</option>';
	}
}

$methods = array(
	'Five',
	'Salvo',
	'Single',
);
$method_selection = '';
foreach ($methods as $method) {
	$method_selection .= '<option value="'.$method.'">'.$method.'</option>';
}


$meta['title'] = 'Send Game Invitation';
$meta['head_data'] = '
	<script type="text/javascript" src="scripts/invite.js"></script>
';

$hints = array(
	'Invite a player to a game by filling out your desired game options.' ,
	'Salvo means you get one shot per ship per turn.  If you have two ships sunk, then you\'ll get 3 shots this turn.' ,
	'Five means you get five shots per turn, no matter what.' ,
	'Single means that you get one shot per turn.  This is the original method for Battleship, but may take a long time online.' ,
	'<span class="highlight">WARNING!</span><br />Games will be deleted after '.Settings::read('expire_games').' days of inactivity.' ,
);

// make sure this user is not full
$submit_button = '<div><input type="submit" name="invite" value="Send Invitation" /></div>';
$warning = '';
if ($GLOBALS['Player']->max_games && ($GLOBALS['Player']->max_games <= $GLOBALS['Player']->current_games)) {
	$submit_button = $warning = '<p class="warning">You have reached your maximum allowed games, you can not create this game !</p>';
}

$contents = <<< EOF
	<form method="post" action="{$_SERVER['REQUEST_URI']}" id="send"><div class="formdiv">

		<input type="hidden" name="token" value="{$_SESSION['token']}" />
		<input type="hidden" name="player_id" value="{$_SESSION['player_id']}" />

		{$warning}

		<div><label for="opponent">Opponent</label><select id="opponent" name="opponent">{$opponent_selection}</select></div>
		<div><label for="method">Method</label><select id="method" name="method">{$method_selection}</select></div>

		{$submit_button}

		<div class="clr"></div>
	</div></form>
EOF;

// create our invitation tables
$invites = Game::get_invites($_SESSION['player_id']);

$in_vites = $out_vites = array( );
if (is_array($invites)) {
	foreach ($invites as $game) {
		if ($game['invite']) {
			$in_vites[] = $game;
		}
		else {
			$out_vites[] = $game;
		}
	}
}

$contents .= <<< EOT
	<form method="post" action="{$_SERVER['REQUEST_URI']}"><div class="formdiv" id="invites">
EOT;

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no received invites to show</p>' ,
	'caption' => 'Invitations Received' ,
);
$table_format = array(
	array('ID', 'game_id') ,
	array('Player #1', 'white') ,
	array('Player #2', 'black') ,
	array('Method', 'method') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[create_date]]]\'))', null, ' class="date"') ,
	array('Action', '<input type="button" id="accept-[[[game_id]]]" value="Accept" /><input type="button" id="decline-[[[game_id]]]" value="Decline" />', false) ,
);
$contents .= get_table($table_format, $in_vites, $table_meta);

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no sent invites to show</p>' ,
	'caption' => 'Invitations Sent' ,
);
$table_format = array(
	array('ID', 'game_id') ,
	array('Player #1', 'white') ,
	array('Player #2', 'black') ,
	array('Method', 'method') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[create_date]]]\'))', null, ' class="date"') ,
	array('Action', '###\'<input type="button" id="withdraw-[[[game_id]]]" value="Withdraw" />\'.((strtotime(\'[[[create_date]]]\') >= strtotime(\'[[[resend_limit]]]\')) ? \'\' : \'<input type="button" id="resend-[[[game_id]]]" value="Resend" />\')', false) ,
);
$contents .= get_table($table_format, $out_vites, $table_meta);

$contents .= <<< EOT
	</div></form>
EOT;

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
call($GLOBALS);
echo get_footer( );

