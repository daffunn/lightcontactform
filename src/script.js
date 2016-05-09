/*!
 * @author: Jan Wolf (https://jan-wolf.de)
 * @license: 2016 Jan Wolf
 */
(function ( $ ) {
	"use strict";

	// Define.
	$.fn.jwLightContactForm = function(options) {

		// Merge options with default values.
		options = $.extend( true, {
			prefix: 'jw_lightcontactform',
			selectors: {
				name: '[data-name]',
				mail: '[data-mail]',
				snippet: '[data-snippet]',
				submit: '[data-submit]'
			},
			attributes: {
				snippet: 'data-snippet',
				snippet_order: 'data-snippet-order'
			},
			attribute_functions: {
				snippet: {
					value: 'value()',
					newline: 'newline()'
				}
			},
			callbacks: {
				fire: function(){},
				sent: function(){},
				error: function(){}
			}
		}, options || {} );

		var prefix = function(string){
			return options.prefix + '_' + string;
		};

		// Iterate through each element and add events.
		return this.each(function (  ) {
			var widget = $(this);
			widget.on( prefix('submit_event'), function () {
				// Break up, if form is in pending mode.
				if(widget.hasClass('pending')) return;

				// Disable all possible submit fields, any previous classes and fire the callback.
				widget.removeClass( 'error success' ).addClass( 'pending' );
				options.callbacks.fire(widget);

				// Define fail function for reuse.
				var fail = function () {
					// Add error class and fire the callback.
					widget.addClass('error');
					options.callbacks.error(widget);
				};

				// Make call.
				var ajax_obj = eval( prefix('ajaxobj') );
				$.ajax( {
					url: ajax_obj.endpoint_url,
					cache: false,
					type: "POST",
					data: {
						action: ajax_obj.endpoint_action,
						nonce: ajax_obj.endpoint_nonce,
						name: widget.find( options.selectors.name ).eq(0).val(),
						mail: widget.find( options.selectors.mail ).eq(0).val(),
						message: function(){
							var message = [];
							widget.find( options.selectors.snippet ).each(function(){
								// Make snippet.
								var snippet = $(this).attr(options.attributes.snippet);
								snippet = snippet === undefined || snippet.length == 0 ? $(this).val() : snippet.replace(options.attribute_functions.snippet.value, $(this).val())

								// Replace attribute functions.
								snippet.replace(options.attribute_functions.snippet.newline, "\n");

								// Order it.
								var order = parseInt($(this).attr(options.attributes.snippet_order));
								if(isNaN(order))
									message.push( snippet );
								else
									message[order] = snippet;
							});

							// Make a string out of it.
							return message.join("\n");
						}()
					},
					dataType: "json",
					beforeSend: function ( x ) {
						if ( x && x.overrideMimeType ) {
							x.overrideMimeType( "application/json;charset=UTF-8" );
						}
					}
				} ).always( function () {
					widget.removeClass( 'pending' );
				} ).fail( fail ).done( function ( data ) {
					// Failed?
					if(!data.success) {
						fail();
						return;
					}

					// Create success classes and fire callback.
					widget.addClass('success');
					options.callbacks.sent(widget);

					// Reset form.
					widget.find([options.selectors.mail, options.selectors.name, options.selectors.snippet].join(',')).val('');
				} );
			} );

			widget.find( options.selectors.submit ).not('[data-submit="silent"]').click(function(){
				widget.trigger(prefix('submit_event'));
			});
		});
	};

	// Initialize.
	$(document).ready(function() {
		if(eval('jw_lightcontactform_ajaxobj' ).autoload) $('.jw_lightcontactform_widget').jwLightContactForm();
	});
}( jQuery ));
