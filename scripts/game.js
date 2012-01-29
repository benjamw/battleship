
var reload = true; // do not change this
var refresh_timer = false;
var refresh_timeout = 2001; // 2 seconds
var board_storage = false;

$(document).ready( function( ) {
	if (('finished' == state) || ('paused' == state)) {
		$('div#boards').removeClass('active');
	}

	// PANIC BUTTON
	// hides the players board when it's clicked in
	// case the opponent walked in the room
	$('div.active div.first').live('click', function( ) {
		$this = $(this);
		if (board_storage) {
			$this.replaceWith(board_storage);
			board_storage = false;
		}
		else {
			board_storage = $this;
			$this.replaceWith('<div class="noboard first panic">HIDDEN</div>');
		}
	});

	// set the previous shots
	var id = 0;
	for (var i in prev_shots) {
		id = prev_shots[i];

		if (10 > id) {
			id = '0'+id;
		}

		if (my_turn) {
			$('#dfd-'+id).addClass('prevshot');
		}
		else {
			$('#tgt-'+id).addClass('prevshot');
		}
	}

	// make the board clicks work
	if (my_turn) {
		$('div.active div.second div.row:not(div.top, div.bottom) div:not(div.side):not(div:has(img))').click( function(evnt) {
			var $this = $(this);
			var id = $this.attr('id').slice(4);

			// are we adding or removing the square
			if ($this.hasClass('curshot')) { // removing square
				var $shots = $('#shots');
				var value = $shots.val( );
				value = value.split(',');
				value.splice(value.indexOf(id), 1);
				value.join(',');
				$shots.val(value);
				++shots;
			}
			else { // adding square
				var $shots = $('#shots');
				var value = $shots.val( );
				value = value.split(',');
				value.push(id);
				value.join(',');
				$shots.val(value);
				--shots;
			}

			// update the shot markers
			update_shots( );

			// run the shots
			if (0 == shots) {
				if (debug) {
					window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( );
					return;
				}

				$.ajax({
					type: 'POST',
					url: 'ajax_helper.php',
					data: $('form#game').serialize( ),
					success: function(msg) {
						// if something happened, just reload
						if ('{' != msg[0]) {
							alert('ERROR: AJAX failed');
						}

						var reply = JSON.parse(msg);

						if (reply.error) {
							alert(reply.error);
						}

						if (reload) { window.location.reload( ); }
						return;
					}
				});
			}

			$this.toggleClass('curshot');
		}).css('cursor', 'pointer');
	}


	// nudge button
	$('#nudge').click( function( ) {
		if (confirm('Are you sure you wish to nudge this person?')) {
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( )+'&nudge=1';
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('form#game').serialize( )+'&nudge=1',
				success: function(msg) {
					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
					}
					else {
						alert('Nudge Sent');
					}

					if (reload) { window.location.reload( ); }
				}
			});
		}

		return false;
	});


	// resign button
	$('#resign').click( function( ) {
		if (confirm('Are you sure you wish to resign the game?')) {
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( )+'&resign=1';
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('form#game').serialize( )+'&resign=1',
				success: function(msg) {
					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
					}

					if (reload) { window.location.reload( ); }
				}
			});
		}

		return false;
	});


	// chat box functions
	$('#chatbox form').submit( function( ) {
		if ('' == $.trim($('#chatbox input#chat').val( ))) {
			return false;
		}

		if (debug) {
			window.location = 'ajax_helper.php'+debug_query+'&'+$('#chatbox form').serialize( );
			return false;
		}

		$.ajax({
			type: 'POST',
			url: 'ajax_helper.php',
			data: $('#chatbox form').serialize( ),
			success: function(msg) {
				// if something happened, just reload
				if ('{' != msg[0]) {
					alert('ERROR: AJAX failed');
					if (reload) { window.location.reload( ); }
				}

				var reply = JSON.parse(msg);

				if (reply.error) {
					alert(reply.error);
				}
				else {
					var entry = '<dt><span>'+reply.create_date+'</span> '+reply.username+'</dt>'+
						'<dd'+(('1' == reply.private) ? ' class="private"' : '')+'>'+reply.message+'</dd>';

					$('#chats').prepend(entry);
					$('#chatbox input#chat').val('');
				}
			}
		});

		return false;
	});


	// sunk ship display
	$('span.ships').click( function( ) {
		var id = $(this).attr('id').slice(0, -6);

		if (debug) {
			window.location = 'ajax_helper.php'+debug_query+'&'+'shipcheck=1&id='+id;
			return false;
		}

		$.ajax({
			type: 'POST',
			url: 'ajax_helper.php',
			data: 'shipcheck=1&id='+id,
			success: function(msg) {
				alert(msg);
			}
		});

		return false;
	}).css('cursor', 'pointer');


	// run the ajax refresher
	if ( ! my_turn && ('finished' != state)) {
		ajax_refresh( );

		// set some things that will halt the timer
		$('#chatbox form input').focus( function( ) {
			clearTimeout(refresh_timer);
		});

		$('#chatbox form input').blur( function( ) {
			if ('' != $(this).val( )) {
				refresh_timer = setTimeout('ajax_refresh( )', refresh_timeout);
			}
		});
	}

	update_shots( );

	if (pre_hide_board) {
		$('div.active div.first').click( );
	}
});


function update_shots( ) {
	$('span.shots img').remove( );
	for (var i = 0; i < shots; ++i) {
		$('span.shots').append('<img src="images/hit.gif" />');
	}
}


function ajax_refresh( ) {
	// no debug redirect, just do it

	$.ajax({
		type: 'POST',
		url: 'ajax_helper.php',
		data: 'refresh=1',
		success: function(msg) {
			if (msg != last_move) {
				// don't just reload( ), it tries to submit the POST again
				if (reload) { window.location = window.location.href; }
			}
		}
	});

	// successively increase the timeout time in case someone
	// leaves their window open, don't poll the server every
	// two seconds for the rest of time
	if (0 == (refresh_timeout % 5)) {
		refresh_timeout += Math.floor(refresh_timeout * 0.001) * 1000;
	}

	++refresh_timeout;

	refresh_timer = setTimeout('ajax_refresh( )', refresh_timeout);
}

