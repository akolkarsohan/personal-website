jQuery(document).ready(function() {
	var popup = false;
	jQuery('.request-feature a').click(function( event ) {
		event.preventDefault();
		if (!popup) {
			jQuery('.request-feature-popup').slideDown( "slow" );
			popup = true;
		} else {
			jQuery('.request-feature-popup').slideUp( "slow", function() {
				jQuery('.request-feature-popup').css('display', 'none');
			});
			popup = false;
		}
	});
	jQuery('.close-popup').click(function( event ) {
		event.preventDefault();
		jQuery('.request-feature-popup').slideUp( "slow", function() {
			jQuery('.request-feature-popup').css('display', 'none');
		});
		popup = false;
	});
});