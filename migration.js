jQuery(document).ready(function ($) {

	/*
	Page closing confirmation
	https://developer.mozilla.org/en-US/docs/DOM/Mozilla_event_reference/beforeunload
	 */
	if ($('.wpas-migration-result').length) {
		$(window).on('beforeunload', function (e) {
			return 'Migration is in progress. Please do not close this page until migration is finished.';
		});
	}

});