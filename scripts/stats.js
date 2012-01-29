
$(document).ready( function( ) {
	// change the colors for the win ratios
	$('td.color, .color td').each( function(i, elem) {
		var $elem = $(elem);
		var text = parseFloat($elem.text( ));

		if (0 < text) {
			$elem.css('color', 'green');
		}
		else if (0 > text) {
			$elem.css('color', 'red');
		}
	});
});

