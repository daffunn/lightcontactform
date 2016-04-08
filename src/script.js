/*!
 * @author: Jan Wolf (https://jan-wolf.de)
 * @license: 2016 Jan Wolf
 */
(function ( $ ) {
	"use strict";

	// Define.
	var jw_lighttwitterwidget = function(options) {
		// Merge options with default values.
		options = $.extend( true, {
			element: null,
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

		// Stop, if no element is set.
		if ( options.element === null || options.element.length > 1 ) throw('Multiple or no element set as a widget container.');

		var prefix = function ( string ) {
			return options.element.attr( 'data-prefix' ) + '_' + string;
		};

		var reset = function(){
			options.element.find([options.selectors.mail, options.selectors.name, options.selectors.snippet].join(',')).val('');
		};

		options.element.on( prefix('submit_event'), function () {
			// Break up, if form is in pending mode.
			if(options.element.hasClass('pending')) return;

			// Disable all possible submit fields, any previous classes and fire the callback.
			options.element.removeClass( 'error success' ).addClass( 'pending' );
			options.callbacks.fire(options.element);

			// Define fail function for reuse.
			var fail = function () {
				// Add error class and fire the callback.
				options.element.addClass('error');
				options.callbacks.error(options.element);
			};

			// Make call.
			var ajaxobj = eval( prefix( 'ajaxobj' ) );
			$.ajax( {
				url: ajaxobj.ajaxurl,
				cache: false,
				type: "POST",
				data: {
					action: prefix( 'api' ),
					name: options.element.find( options.selectors.name ).eq(0).val(),
					mail: options.element.find( options.selectors.mail ).eq(0).val(),
					message: function(){
						var message = [];
						options.element.find( options.selectors.snippet ).each(function(){
							// Make snippet.
							var snippet = $(this).attr(options.attributes.snippet);
							snippet = snippet === undefined || snippet.length == 0 ? $(this).val() : snippet.replace(options.attribute_functions.snippet.value, $(this).val())

							// Replace attribute functions.
							snippet.replace(options.attribute_functions.snippet.newline, "\n");

							// Order it.
							var order = parseInt($(this).attr(options.attributes.snippet_order));
							if(isNaN(order))
								message.push( snippet );
							else message[order] = snippet;
						});

						// Make a string out of it.
						return message.join("\n");
					}(),
					nonce: ajaxobj.nonce
				},
				dataType: "json",
				beforeSend: function ( x ) {
					if ( x && x.overrideMimeType ) {
						x.overrideMimeType( "application/json;charset=UTF-8" );
					}
				}
			} ).always( function () {
				options.element.removeClass( 'pending' );
			} ).fail( fail ).done( function ( data ) {
				// Failed?
				if(!data.success) {
					fail();
					return;
				}

				// Create success classes and fire callback.
				options.element.addClass('success');
				options.callbacks.sent(options.element);

				// Reset form.
				reset();
			} );
		} );

		options.element.find( options.selectors.submit ).not('[data-submit="silent"]').click(function(){
			options.element.trigger(prefix('submit_event'));
		});
	};

	// Initialize.
	$(document).ready(function() {
		$('.jw_lightcontactform_widget').each(function(){
			new jw_lighttwitterwidget({element: $(this)});
		});
	});
})(jQuery);
