<?php

require_once 'includes/inc.global.php';

if (isset($_POST['submit'])) {
	test_token( );

	try {
		$_POST['allow_email'] = isset($_POST['allow_email']) ? $_POST['allow_email'] : false;
		$_POST['pre_hide_board'] = isset($_POST['pre_hide_board']) ? $_POST['pre_hide_board'] : false;

		$GLOBALS['Player']->allow_email = is_checked($_POST['allow_email']);
		$GLOBALS['Player']->pre_hide_board = is_checked($_POST['pre_hide_board']);
		$GLOBALS['Player']->max_games = (int) $_POST['max_games'];

		// color selections may be removed
		if (isset($_POST['color'])) {
			$GLOBALS['Player']->color = $_POST['color'];
		}

		$GLOBALS['Player']->save( );

		Flash::store('Preferences Updated', false);
	}
	catch (MyException $e) {
		Flash::store('Preferences Update FAILED !', false);
	}
}

$meta['title'] = 'Update Preferences';

$hints = array(
	'Here you can update your '.GAME_NAME.' preferences.' ,
	'Setting a max concurrent games value will block people from sending you invites to new games after the max games value has been reached.  Set to 0 to disable.' ,
);

$allow_email_cb = '<input type="checkbox" id="allow_email" name="allow_email" '.get_selected(true, $GLOBALS['Player']->allow_email, false).'/>';
$pre_hide_board_cb = '<input type="checkbox" id="pre_hide_board" name="pre_hide_board" '.get_selected(true, $GLOBALS['Player']->pre_hide_board, false).'/>';

if (is_array($GLOBALS['_COLORS']) && (0 != count($GLOBALS['_COLORS']))) {
	$color_select = '<div><label for="color">Theme Color</label><select id="color" name="color"><option value="">Use Default</option>';

	foreach ($GLOBALS['_COLORS'] as $color) {
		$color_select .= '<option value="'.$color.'"'.get_selected($GLOBALS['Player']->color, $color).'>'.ucwords(str_replace('_', ' ', $color)).'</option>';
	}

	$color_select .= '</select></div>';
}
else {
	$color_select = '';
}

$contents = <<< EOF
	<form method="post" action="{$_SERVER['REQUEST_URI']}"><div class="formdiv">
		<input type="hidden" name="token" value="{$_SESSION['token']}" />

		<div><label for="allow_email" class="inline">{$allow_email_cb}Allow emails for this game to be sent to your email address</label></div>
		<div><label for="pre_hide_board" class="inline">{$pre_hide_board_cb}Load the game board already hidden.</label></div>
		<div><label for="max_games">Max concurrent games</label><input type="text" id="max_games" name="max_games" size="3" maxlength="3" value="{$GLOBALS['Player']->max_games}" /></div>
		{$color_select}
		<div><input type="submit" name="submit" value="Update Preferences" /></div>
	</div></form>
EOF;

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
call($GLOBALS);
echo get_footer( );

