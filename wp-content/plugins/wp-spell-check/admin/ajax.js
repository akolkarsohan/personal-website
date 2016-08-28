jQuery(document).ready(function($) {
	console.debug("check Triggered");
	jQuery.ajax({
		url: ajax_object.ajax_url,
		type: "POST",
		data: {
			action: 'results_sc',
		},
		dataType: 'html',
		success: function(response) {
			if (response == 'true') recheck_scan();
		}
	});
});

function recheck_scan() {
	console.debug("Recheck Triggered");
	jQuery.ajax({
		url: ajax_object.ajax_url,
		type: "POST",
		data: {
			action: 'results_sc',
		},
		dataType: 'html',
		success: function(response) {
			if (response == 'true') { window.setInterval(recheck_scan(), 5000 ) }
			else { window.location.href = encodeURI("?page=wp-spellcheck.php"); }
		}
	});
}