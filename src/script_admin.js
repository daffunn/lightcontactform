/*!
 * @author: Jan Wolf (https://jan-wolf.de)
 * @license: 2016 Jan Wolf
 */
(function ( $ ) {
	"use strict";

	// Initialize.
	$(document).ready(function() {
		var prefix = 'jw_lightcontactform_';
		$('.' + prefix + 'autoresponder_commander').on('change', function(){
			$('.' + prefix + 'autoresponder_complier').prop('disabled', !$('.' + prefix + 'autoresponder_commander').is(':checked'));
		});
	});
})(jQuery);

