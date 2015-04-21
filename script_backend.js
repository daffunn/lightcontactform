jQuery(document).ready(function(e) {
	jQuery('#autoresponder_activated').on('change', function(){
   		jQuery("#autoresponder_subject, #autoresponder_name, #autoresponder_from, #autoresponder_text").prop("disabled", !jQuery(this).is(':checked')); 
	});
   	jQuery("#autoresponder_subject, #autoresponder_name, #autoresponder_from, #autoresponder_text").prop("disabled", !jQuery('#autoresponder_activated').is(':checked')); 
});