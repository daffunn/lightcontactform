function jw_lightcontact_form (){
	this.toastqueue = new Array();
	this.toastqueue_locked = false;
	this.init();
};

jw_lightcontact_form.prototype.showToast = function(message, type, noqueue){
		var jw_lightcontactform_obj = this;
		if(jw_lightcontact_form.prototype.showToast.arguments.length==2)
			this.toastqueue.push(new Array(message, type));

		if(jw_lightcontact_form.prototype.showToast.arguments.length==2 && this.toastqueue_locked)
			return;

		this.toastqueue_locked = true;
		var m = this.toastqueue[0][0];
		var t = this.toastqueue[0][1];
		this.toastqueue.shift();

		if(!jQuery("div#jw_lightcontactform_toast").length > 0)
			jQuery("body").prepend('<div id="jw_lightcontactform_toast"></div>');

		if(t)
			jQuery("div#jw_lightcontactform_toast").css('background-color', '#19CC29');
		else
			jQuery("div#jw_lightcontactform_toast").css('background-color', '#F36');

		jQuery("div#jw_lightcontactform_toast").html(m).addClass('active');
		setTimeout(function(){
			jQuery("div#jw_lightcontactform_toast").removeClass('active');
			setTimeout(function(){
				jw_lightcontactform_obj.toastqueue_locked = false;
				if(jw_lightcontactform_obj.toastqueue.length>0)
					jw_lightcontactform_obj.showToast(m, t, true);
				else
					jQuery("div#jw_lightcontactform_toast").remove();
			},500);
		}, 7000);
	};

jw_lightcontact_form.prototype.init = function() {
		var jw_lightcontactform_obj = this;
		jQuery("#jw_lightcontactform_submit").prop("disabled", false);
		jQuery("#jw_lightcontactform_submit").click(function(e){
			e.preventDefault();
			jQuery("#jw_lightcontactform_submit").prop("disabled", true);
			jQuery.ajax({
				url: jw_lightcontactform_ajaxobj.ajaxurl,
				cache: false,
				type: "POST",
				data: {
					action: 'jw_lightcontactform_submitform',
					name: jQuery("#jw_lightcontactform_name").val(),
					email: jQuery("#jw_lightcontactform_mail").val(),
					text: jQuery("#jw_lightcontactform_text").val(),
					nonce: jw_lightcontactform_ajaxobj.nonce
				},
				dataType: "json",
				beforeSend: function(x) {
					if (x && x.overrideMimeType) {
						x.overrideMimeType("application/json;charset=UTF-8");
					}
				},
				success: function(result) {
					jQuery("#jw_lightcontactform_submit").prop("disabled", false);
					jw_lightcontactform_obj.showToast((result.status) ? result.message : result.error, result.status);
					if (result.status)
						jQuery("#jw_lightcontactform_text, #jw_lightcontactform_name, #jw_lightcontactform_mail").val("");
				},
				error: function(e, xhr) {
					jQuery("#jw_lightcontactform_submit").prop("disabled", false);
					jw_lightcontactform_obj.showToast(jw_lightcontactform_ajaxobj.servererror,false);
				}
			});
		});
	};

jQuery(document).ready(function(e) {
	new jw_lightcontact_form();
});
