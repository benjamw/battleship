
var reload = true; // do not change this
var id1 = -1;

$(document).ready( function( ) {

	// make the board clickable
	$('#board_wrapper').on('click', 'div.board div.row:not(div.top, div.bottom) div:not(div.side)', function(evnt) {
		var $this = $(this);
		var id = $this.attr('id').slice(4);
		var _class = $this.attr('class');

		// run the placed boat clicks
		if (_class && ((-1 != _class.indexOf('v-')) || (-1 != _class.indexOf('h-')))) {
			$('#method').val('remove');
			$('#value').val(id);

			run_ajax( );

			return false;
		}

		// are we adding or removing the square
		if ($this.hasClass('curshot')) { // removing square
			id1 = -1;
		}
		else { // adding square
			$this.toggleClass('curshot');

			if (-1 == id1) {
				id1 = id;
			}
			else {
				// test the two squares for validity
				if ((id1.slice(0, 1) != id.slice(0, 1)) && (id1.slice(1) != id.slice(1))) {
					alert('The two squares MUST be on the same line.\nEither horizontal or vertical.\n\nPlease try again.');
					return false;
				}

				// we made it here, fill in the form and ajax it off
				$('#method').val('between');
				$('#value').val(id1+':'+id);

				run_ajax( );

				return false;
			}
		}

	}).find('div.board div.row:not(div.top, div.bottom) div:not(div.side)').css('cursor', 'pointer')
		.filter('[class*="-"]').attr('title', 'Remove this boat');


	// run the unplaced boat clicks
	$('#boat_wrapper').on('click', 'div.boat', function( ) {
		$('#method').val('random_boat');
		$('#value').val($('div', this).length);

		run_ajax( );

		return false;
	}).find('div.boat').css('cursor', 'pointer').attr('title', 'Randomly place this boat');


	// run the buttons and form
	$('input.button').on('click', function( ) {
		var id = $(this).attr('id');

		if ('done' == id) {
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$('.forms form').serialize( )+'&done=1';
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('.forms form').serialize( )+'&done=1',
				success: function(msg) {
					// if something happened, just reload
					if ('{' != msg[0]) {
						if (reload) { window.location.reload( ); }
						return;
					}

					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
					}

					if (reply.redirect) {
						window.location = ((-1 !== reply.redirect.indexOf('?')) ? reply.redirect+debug_query : reply.redirect+debug_query_);
					}

					if (reload) { window.location.reload( ); }
					return;
				}
			});

			return false;
		}

		$('#method').val(id);

		run_ajax( );

		return false;
	});

});


/* function clear_form
 *		Clears the dynamic form elements
 *
 * @param void
 * @action clears the dynamic form elements
 * @return void
 */
function clear_form( ) {
	id1 = -1;
	$('#method').val('');
	$('#value').val('');
	$('div.curshot').removeClass('curshot');
}


/* function run_ajax
 *		Serializes the form data and sends it off
 *		then parses the return and acts accordingly.
 *
 * @param void
 * @action runs ajax and possibly refreshes page or refreshes html
 * @return null
 */
function run_ajax( ) {
	if (debug) {
		window.location = 'ajax_helper.php'+debug_query+'&'+$('.forms form').serialize( );
		return;
	}

	$.ajax({
		type: 'POST',
		url: 'ajax_helper.php',
		data: $('.forms form').serialize( ),
		success: function(msg) {
			// if something happened, just reload
			if ('{' != msg[0]) {
				if (reload) { window.location.reload( ); }
				return;
			}

			var reply = JSON.parse(msg);

			if (reply.error) {
				alert(reply.error);
				if (reload) { window.location.reload( ); }
				return;
			}

			if ('RELOAD' == reply.action) {
				if (reload) { window.location.reload( ); }
				return;
			}

			// refresh the board and boats
			$('#boat_wrapper').empty( ).append(reply.boats)
				.find('div.boat').css('cursor', 'pointer').attr('title', 'Randomly place this boat');
			$('#board_wrapper').empty( ).append(reply.board)
				.find('div.board div.row:not(div.top, div.bottom) div:not(div.side)').css('cursor', 'pointer')
					.filter('[class*="-"]').attr('title', 'Remove this boat');
			clear_form( );
		}
	});

	return;
}
