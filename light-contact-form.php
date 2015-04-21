<?php
/**
 * Plugin Name: Light Contact Form
 * Description: A light contact form with multiple users function.
 * Version: 1.0
 * Author: Jan Wolf
 * Author URI: http://jan-wolf.de
 * License: GPL2
 */
/*  Copyright 2013  Jan Wolf  (email : info@jan-wolf.de)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class jw_lightcontactform {
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	protected $defaults;
	protected $option_name;
	protected $option_group;
	protected $option_section_general;
	protected $option_section_autoresponder;
	protected $option_section_form;

	/**
	 * Start up
	 */
	public function __construct() {
		$this->option_section_general = 'jw_lightcontactform_settingssection_general';
		$this->option_section_autoresponder = 'jw_lightcontactform_settingssection_autoresponder';
		$this->option_section_form = 'jw_lightcontactform_settingssection_form';
		$this->option_name = 'jw_lightcontactform_settings';
		$this->option_group = 'jw_lightcontactform_settingsgroup';
		$this->defaults = [
			'mails'                   => 'yourmail(s)@a.bc',
			'mail_subject'            => 'Neue E-Mail eingetroffen',
			'mail_name'               => '%%%NAME%%% über yourserver.x',
			'mail_from'               => 'from@yourserver.x',
			'form_name'               => 'Ihren Namen',
			'form_name_error'         => 'Ihr Name ist nicht korrekt.',
			'form_mail'               => 'Ihre E-Mail-Adresse',
			'form_mail_error'         => 'Ihre E-Mail-Adresse ist nicht korrekt.',
			'form_text'               => 'Ihr Anliegen',
			'form_text_error'         => 'Ihr Nachrichtentext ist zu kurz.',
			'form_success'            => 'Ihre Nachricht wurde erfolgreich übermittelt.',
			'form_servererror'        => 'Ihre Nachricht konnte aus unbekannten Gründen nicht übermittelt werden.',
			'form_submit'             => 'Ab damit!',
			'autoresponder_activated' => 0,
			'autoresponder_name'      => 'Ihre Anfrage',
			'autoresponder_from'      => 'from@yourserver.x',
			'autoresponder_subject'   => 'Vielen Dank für Ihre Anfrage!',
			'autoresponder_text'      => "Sehr geehrte(r) %%%NAME%%%,%%%NEWLINE%%%%%%NEWLINE%%%vielen Dank für Ihre Anfrage. Ich werde Ihnen sobald möglich antworten. Dies ist eine automatisch generierte E-Mail. Bitte antworten Sie nicht auf diese E-Mail.%%%NEWLINE%%%%%%NEWLINE%%%Mit freundlichen Grüßen"
		];

		add_action( 'admin_init', [ $this, 'page_init' ] );
		add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueueScripts' ] );
		add_shortcode( 'jw_lightcontactform', [ $this, 'generateCustomForm' ] );

		if ( is_admin() ) {
			add_action( 'wp_ajax_nopriv_jw_lightcontactform_submitform', [ $this, 'submitForm' ] );
			add_action( 'wp_ajax_jw_lightcontactform_submitform', [ $this, 'submitForm' ] );
		}

		// Uninstall Plugin hook
		register_uninstall_hook( __FILE__, [ $this, 'uninstall' ] );
	}

	public function enqueueScripts() {
		$options = get_option( $this->option_name, $this->defaults );
		wp_enqueue_script( 'jw_lightcontactform_script', plugins_url( 'script.js', __FILE__ ), [ 'jquery' ] );
		wp_localize_script( 'jw_lightcontactform_script', 'jw_lightcontactform_ajaxobj', [
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'servererror' => $options[ 'form_servererror' ],
			'nonce'       => wp_create_nonce( 'jw_lightcontactform_nonce' )
		] );
		wp_enqueue_style( 'jw_lightcontactform_style', plugins_url( 'style.css', __FILE__ ) );
	}

	/* BACK END FUNCTIONS */

	public function uninstall() {
		delete_option( $this->option_name );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page( 'Kontaktformular-Einstellungen', 'Kontaktformular', 'edit_pages', $this->option_group, [
			$this,
			'create_admin_page'
		] );
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		?>

		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>Kontaktformular-Einstellungen</h2>

			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( $this->option_group );
				do_settings_sections( $this->option_group );
				submit_button();
				?>
			</form>
		</div>
	<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		wp_enqueue_script( "script", plugins_url( 'script_backend.js', __FILE__ ), "jquery" );
		wp_enqueue_style( "style", plugins_url( 'style_backend.css', __FILE__ ) );

		register_setting( $this->option_group, // Option group
			$this->option_name, // Option name
			[ $this, 'sanitize' ] // Sanitize
		);

		add_settings_section( $this->option_section_general, // ID
			'Allgemeine Einstellungen', // Title
			[ $this, 'print_section_info_general' ], // Callback
			$this->option_group // Page
		);

		add_settings_section( $this->option_section_form, // ID
			'Formularfelder', // Title
			[ $this, 'print_section_info_form' ], // Callback
			$this->option_group // Page
		);

		add_settings_section( $this->option_section_autoresponder, // ID
			'Autoresponder', // Title
			[ $this, 'print_section_info_autoresponder' ], // Callback
			$this->option_group // Page
		);

		add_settings_field( 'mails', // ID
			'Empfängeradresse (durch Komma getrennt)', // Title
			[ $this, 'mails_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_general // Section
		);

		add_settings_field( 'mail_subject', // ID
			'Betreff', // Title
			[ $this, 'mail_subject_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_general // Section
		);

		add_settings_field( 'mail_name', // ID
			'E-Mail des Absenders', // Title
			[ $this, 'mail_name_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_general // Section
		);

		add_settings_field( 'mail_from',  // ID
			'Absenderadresse',  // Title
			[ $this, 'mail_from_callback' ],  // Callback
			$this->option_group,  // Page
			$this->option_section_general // Section
		);

		add_settings_field( 'form_name', // ID
			'Name', // Title
			[ $this, 'form_name_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_name_error', // ID
			'Fehlernachricht für Name', // Title
			[ $this, 'form_name_error_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_mail', // ID
			'E-Mail', // Title
			[ $this, 'form_mail_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_mail_error', // ID
			'Fehlernachricht für E-Mail', // Title
			[ $this, 'form_mail_error_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_text', // ID
			'Text', // Title
			[ $this, 'form_text_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_text_error', // ID
			'Fehlernachricht für Text', // Title
			[ $this, 'form_text_error_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_success', // ID
			'Erfolgsmeldung', // Title
			[ $this, 'form_success_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'form_submit', // ID
			'Senden-Button', // Title
			[ $this, 'form_submit_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_form // Section
		);

		add_settings_field( 'autoresponder_activated',  // ID
			'Autoresponder aktivieren',  // Title
			[ $this, 'autoresponder_activated_callback' ],  // Callback
			$this->option_group,  // Page
			$this->option_section_autoresponder // Section
		);

		add_settings_field( 'autoresponder_from',  // ID
			'E-Mail des Absenders',  // Title
			[ $this, 'autoresponder_from_callback' ],  // Callback
			$this->option_group,  // Page
			$this->option_section_autoresponder // Section
		);

		add_settings_field( 'autoresponder_name', // ID
			'Name des Absenders', // Title
			[ $this, 'autoresponder_name_callback' ], // Callback
			$this->option_group, // Page
			$this->option_section_autoresponder // Section
		);

		add_settings_field( 'autoresponder_subject',  // ID
			'Betreff des Autoresponders',  // Title
			[ $this, 'autoresponder_subject_callback' ],  // Callback
			$this->option_group,  // Page
			$this->option_section_autoresponder // Section
		);

		add_settings_field( 'autoresponder_text',  // ID
			'Mailformat',  // Title
			[ $this, 'autoresponder_text_callback' ],  // Callback
			$this->option_group,  // Page
			$this->option_section_autoresponder // Section
		);

		add_settings_field( 'form_servererror',  // ID
			'Unbekannte Fehlermeldung',  // Title
			[ $this, 'form_servererror_callback' ],  // Callback
			$this->option_group,  // Page
			$this->option_section_form // Section
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input ) {
		$output = get_option( $this->option_name, $this->defaults );
		$mails = explode( ",", $input[ 'mails' ] );
		if ( empty( $mails ) ) add_settings_error( $this->option_name,            // setting title
			$this->option_name . '_mailserror_empty',            // error ID
			'Bitte gebe mindestens eine E-Mail-Adresse ein.',        // error message
			'error'                            // type of message
		);

		$e = false;
		for ( $i = 0; $i < count( $mails ); $i++ ) {
			$mail = filter_var( trim( $mails[ $i ] ), FILTER_VALIDATE_EMAIL );
			if ( $mail == false ) {
				//Delete from Array
				unset( $mails[ $i ] );
				$i--;

				//Throw User Error
				if ( !$e ) {
					$e = true;
					add_settings_error( $this->option_name,                    // setting title
						$this->option_name . '_mailserror_empty',            // error ID
						'Bitte gebe nur valide E-Mail-Adresse an.',        // error message
						'error'                            // type of message
					);
				}
			}
		}
		$output[ 'mails' ] = implode( ',', $mails );
		$output[ 'mail_subject' ] = sanitize_text_field( $input[ 'mail_subject' ] );
		$output[ 'mail_name' ] = sanitize_text_field( $input[ 'mail_name' ] );

		$output[ 'form_name' ] = sanitize_text_field( $input[ 'form_name' ] );
		$output[ 'form_submit' ] = sanitize_text_field( $input[ 'form_submit' ] );
		$output[ 'form_name_error' ] = sanitize_text_field( $input[ 'form_name_error' ] );
		$output[ 'form_mail' ] = sanitize_text_field( $input[ 'form_mail' ] );
		$output[ 'form_mail_error' ] = sanitize_text_field( $input[ 'form_mail_error' ] );
		$output[ 'form_text' ] = sanitize_text_field( $input[ 'form_text' ] );
		$output[ 'form_text_error' ] = sanitize_text_field( $input[ 'form_text_error' ] );
		$output[ 'form_servererror' ] = sanitize_text_field( $input[ 'form_servererror' ] );
		$output[ 'form_success' ] = sanitize_text_field( $input[ 'form_success' ] );

		$mail_from = is_email( $input[ 'mail_from' ] );
		if ( $mail_from != false ) $output[ 'mail_from' ] = $mail_from;
		else
			add_settings_error( $this->option_name,                    // setting title
				$this->option_name . '_mail_fromerror',            // error ID
				'Bitte gebe eine valide Absenderadresse an.',        // error message
				'error'                            // type of message
			);
		if ( !isset( $input[ 'autoresponder_activated' ] ) || $input[ 'autoresponder_activated' ] != '1' ) $output[ 'autoresponder_activated' ] = 0;
		else {
			$output[ 'autoresponder_activated' ] = 1;
			$output[ 'autoresponder_text' ] = sanitize_text_field( $input[ 'autoresponder_text' ] );
			$output[ 'autoresponder_subject' ] = sanitize_text_field( $input[ 'autoresponder_subject' ] );
			$output[ 'autoresponder_name' ] = sanitize_text_field( $input[ 'autoresponder_name' ] );

			$autoresponder_from = is_email( $input[ 'autoresponder_from' ] );
			if ( $autoresponder_from != false ) $output[ 'autoresponder_from' ] = $autoresponder_from;
			else
				add_settings_error( $this->option_name,                    // setting title
					$this->option_name . '_autoresponder_fromerror',            // error ID
					'Bitte gebe eine valide Absenderadresse an.',        // error message
					'error'                            // type of message
				);
		}

		return $output;
	}

	/**
	 * Print the Section text for general section
	 */
	public function print_section_info_general() {
		print 'Bearbeiten Sie Ihre bevorzugten E-Mail-Adressen, die bei der Absendung einer Anfrage informiert werden sollen:';
	}

	/**
	 * Print the Section text for autoresponder section
	 */
	public function print_section_info_autoresponder() {
		print 'Verändern Sie die Funktionen des Autoresponders, um eine automatische Antwort generieren zu lassen:';
	}

	/**
	 * Print the Section text for autoresponder section
	 */
	public function print_section_info_form() {
		print 'Verändern Sie das Erscheinungsbild des Kontaktformulars:';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function mails_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="mails" name="' . $this->option_name . '[mails]" value="%s" />', isset( $options[ "mails" ] ) ? esc_attr( $options[ "mails" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function mail_subject_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="mail_subject" name="' . $this->option_name . '[mail_subject]" value="%s" />', isset( $options[ "mail_subject" ] ) ? esc_attr( $options[ "mail_subject" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function mail_name_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="mail_name" name="' . $this->option_name . '[mail_name]" value="%s" />', isset( $options[ "mail_name" ] ) ? esc_attr( $options[ "mail_name" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function mail_from_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="mail_from" name="' . $this->option_name . '[mail_from]" value="%s" />', isset( $options[ "mail_from" ] ) ? esc_attr( $options[ "mail_from" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_name_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_name" name="' . $this->option_name . '[form_name]" value="%s" />', isset( $options[ "form_name" ] ) ? esc_attr( $options[ "form_name" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_name_error_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_name_error" name="' . $this->option_name . '[form_name_error]" value="%s" />', isset( $options[ "form_name_error" ] ) ? esc_attr( $options[ "form_name_error" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_mail_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_mail" name="' . $this->option_name . '[form_mail]" value="%s" />', isset( $options[ "form_mail" ] ) ? esc_attr( $options[ "form_mail" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_submit_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_submit" name="' . $this->option_name . '[form_submit]" value="%s" />', isset( $options[ "form_submit" ] ) ? esc_attr( $options[ "form_submit" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_mail_error_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_mail_error" name="' . $this->option_name . '[form_mail_error]" value="%s" />', isset( $options[ "form_mail_error" ] ) ? esc_attr( $options[ "form_mail_error" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_text_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_text" name="' . $this->option_name . '[form_text]" value="%s" />', isset( $options[ "form_text" ] ) ? esc_attr( $options[ "form_text" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_text_error_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_text_error" name="' . $this->option_name . '[form_text_error]" value="%s" />', isset( $options[ "form_text_error" ] ) ? esc_attr( $options[ "form_text_error" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_success_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_success" name="' . $this->option_name . '[form_success]" value="%s" />', isset( $options[ "form_success" ] ) ? esc_attr( $options[ "form_success" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function form_servererror_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="form_servererror" name="' . $this->option_name . '[form_servererror]" value="%s" />', isset( $options[ "form_servererror" ] ) ? esc_attr( $options[ "form_servererror" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function autoresponder_subject_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input disabled type="text" id="autoresponder_subject" name="' . $this->option_name . '[autoresponder_subject]" value="%s" />', isset( $options[ "autoresponder_subject" ] ) ? esc_attr( $options[ "autoresponder_subject" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function autoresponder_text_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<textarea disabled cols="40" id="autoresponder_text" name="' . $this->option_name . '[autoresponder_text]">%s</textarea>', isset( $options[ "autoresponder_text" ] ) ? esc_attr( $options[ "autoresponder_text" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function autoresponder_activated_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		print
			'<input type="checkbox" name="' . $this->option_name . '[autoresponder_activated]" id="autoresponder_activated" value="1"' . ( isset( $options[ "autoresponder_activated" ] ) && $options[ "autoresponder_activated" ] == '1' ? ' checked' : '' ) . '>';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function autoresponder_name_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="autoresponder_name" name="' . $this->option_name . '[autoresponder_name]" value="%s" />', isset( $options[ "autoresponder_name" ] ) ? esc_attr( $options[ "autoresponder_name" ] ) : '' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function autoresponder_from_callback() {
		$options = get_option( $this->option_name, $this->defaults );
		printf( '<input type="text" id="autoresponder_from" name="' . $this->option_name . '[autoresponder_from]" value="%s" />', isset( $options[ "autoresponder_from" ] ) ? esc_attr( $options[ "autoresponder_from" ] ) : '' );
	}


	/* FRONT END FUNCTIONS */

	function submitForm() {
		$output = [ ];
		$status = false;
		$options = get_option( $this->option_name, $this->defaults );

		//Sanitize and Check
		$status_nounce = isset( $_REQUEST[ 'nonce' ] ) && wp_verify_nonce( $_REQUEST[ 'nonce' ], 'jw_lightcontactform_nonce' );
		if ( !$status_nounce ) $output[ "error" ] = $options[ 'form_servererror' ];

		$name = filter_var( trim( $_REQUEST[ 'name' ] ), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES );
		$status_name = strlen( $name ) >= 3;
		if ( !$status_name ) $output[ "error" ] = $options[ 'form_name_error' ];

		$mail = trim( $_REQUEST[ 'email' ] );
		$status_mail = filter_var( $mail, FILTER_VALIDATE_EMAIL ) != false;
		if ( !$status_mail ) $output[ "error" ] = $options[ 'form_mail_error' ];

		$text = filter_var( trim( $_REQUEST[ 'text' ] ), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES );
		$status_text = strlen( $text ) > 10;
		if ( !$status_text ) $output[ "error" ] = $options[ 'form_text_error' ];

		if ( $status_text && $status_mail && $status_name && $status_nounce ) {
			$headers[ ] = 'Content-Type: text/plain; charset=utf-8';
			$headers[ ] = 'Reply-To: ' . $name . ' <' . $mail . '>';
			$headers[ ] = 'From: ' . str_replace( "%%%NAME%%%", $name, $options[ 'mail_name' ] ) . ' <' . $options[ 'mail_from' ] . '>';
			$status = wp_mail( $options[ "mails" ], $options[ 'mail_subject' ], $text, $headers );

			if ( $status && $options[ 'autoresponder_activated' ] != '0' ) {
				$headers[ ] = 'Content-Type: text/plain; charset=utf-8';
				$headers[ ] = 'From: ' . str_replace( "%%%NAME%%%", $name, $options[ 'autoresponder_name' ] ) . ' <' . $options[ 'autoresponder_from' ] . '>';
				$autorespondertext = str_replace( "%%%NAME%%%", $name, str_replace( "%%%NEWLINE%%%", "\n", $options[ 'autoresponder_text' ] ) );
				$status = wp_mail( $mail, $options[ 'autoresponder_subject' ], $autorespondertext, $headers );
			}


			if ( $status ) $output[ "message" ] = $options[ 'form_success' ];
			else
				$output[ "error" ] = $options[ 'form_servererror' ];
		}

		$output[ "status" ] = $status;
		header( "Content-Type: application/json; charset=utf-8" );
		die( json_encode( $output ) );
	}

	public function generateCustomForm() {
		$options = get_option( $this->option_name, $this->defaults );

		return sprintf( '<form class="jw_lightcontactform_form">
			<div class="input">
				<input id="jw_lightcontactform_name" type="text" placeholder="%s">
				<input id="jw_lightcontactform_mail" type="text" placeholder="%s">
				<textarea id="jw_lightcontactform_text" placeholder="%s"></textarea>
			</div>
			<input type="submit" id="jw_lightcontactform_submit" value="%s"	disabled>
		</form>', $options[ 'form_name' ], $options[ 'form_mail' ], $options[ 'form_text' ], $options[ 'form_submit' ] );
	}
}

new jw_lightcontactform();
