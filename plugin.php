<?php
/**
 * Plugin Name: Light Contact Form
 * Description: A light contact form API that gives you just freedom.
 * Version: 1.0.2
 * Author: Jan Wolf
 * Author URI: https://jan-wolf.de
 * License: MIT
 */

class jw_lightcontactform {

	const PREFIX = 'jw_lightcontactform';
	const OPTION_NAME = 'settings';
	const OPTION_GROUP = 'settings_group';
	const OPTION_SECTION_GENERAL = 'settings_section_general';
	const OPTION_SECTION_AUTORESPONDER = 'settings_section_autoresponder';
	const TEXT_DOMAIN = 'jw_lightcontactform';
	private $api_name = null;
	private $api_mail = null;
	private $api_message = null;

	private static function prefix($string) {
		return self::PREFIX . '_' . $string;
	}

	private function get_options(){
		$mail = explode('@', get_option('admin_email'));
		$options = get_option( self::prefix(self::OPTION_NAME), false );
		$default = [
			'recipients' => $mail[0] . '@' . $mail[1],
			'via_mail' => 'noreply@' . $mail[1],
			'styling' => true,
			'autoload' => true,
			'autoresponder' => false,
			'autoresponder_name' => get_option('blogname'),
			'autoresponder_mail' => 'noreply@' . $mail[1]
		];
		if($options === false) return $default;
		return wp_parse_args($options, $default);
	}

	function load_textdomain() {
		load_plugin_textdomain( self::TEXT_DOMAIN, false, plugin_basename( dirname( __FILE__ ) ) . '/lang' );
	}

	public function __construct() {
		add_action( 'admin_init', [ $this, 'page_init' ] );
		add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
		add_action( 'plugins_loaded', [$this, 'load_textdomain'] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_nopriv_' . self::prefix('api'), [ $this, 'api' ] );
		add_action( 'wp_ajax_' . self::prefix('api'), [ $this, 'api' ] );
		add_shortcode( self::prefix('make'), [ $this, 'generate_widget' ] );
		register_uninstall_hook( __FILE__, [ get_called_class(), 'uninstall' ] );

	}

	public function admin_enqueue_scripts() {
		wp_enqueue_script( self::prefix('script_admin'), plugins_url( 'dst/script_admin.min.js', __FILE__ ), [ 'jquery' ] );
		wp_register_style( self::prefix('style_admin'), plugins_url( 'dst/style_admin.min.css', __FILE__ ) );
		wp_enqueue_style( self::prefix('style_admin') );
	}

	public function enqueue_scripts() {
		$options = $this->get_options();

		wp_enqueue_script( self::prefix('script'), plugins_url( 'dst/script.min.js', __FILE__ ), [ 'jquery' ] );
		wp_localize_script( self::prefix('script'), self::prefix('ajaxobj'), [
			'endpoint_url' => admin_url( 'admin-ajax.php' ),
			'endpoint_nonce' => wp_create_nonce( self::prefix('nonce') ),
			'endpoint_action' => self::prefix('api'),
			'autoload' => $options['autoload'] == true
		] );

		// Optional styling.
		if($options['styling']) {
			wp_register_style( self::prefix('style'), plugins_url( 'dst/style.min.css', __FILE__ ) );
			wp_enqueue_style( self::prefix('style') );
		}
	}

	public static function uninstall() {
		delete_option( self::prefix(self::OPTION_NAME) );
	}

	/**
	 * Add options page.
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			__('Light Contact Form Settings', self::TEXT_DOMAIN),
			__('Light Contact Form', self::TEXT_DOMAIN),
			'edit_pages', self::prefix(self::OPTION_GROUP), [
			$this,
			'create_admin_page'
		] );
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php _e( 'Contact Form Settings', self::TEXT_DOMAIN );?></h2>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields.
				settings_fields( self::prefix(self::OPTION_GROUP) );
				do_settings_sections( self::prefix(self::OPTION_GROUP) );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings.
	 */
	public function page_init() {
		register_setting( self::prefix(self::OPTION_GROUP), // Option group
			self::prefix(self::OPTION_NAME), // Option name
			[ $this, 'sanitize' ] // Sanitize
		);

		add_settings_section( self::OPTION_SECTION_GENERAL, // ID
			__('General Settings', self::TEXT_DOMAIN), // Title
			[ $this, 'print_section_info_general' ], // Callback
			self::prefix(self::OPTION_GROUP) // Page
		);

		add_settings_section( self::OPTION_SECTION_AUTORESPONDER, // ID
			__('Autoresponder Settings', self::TEXT_DOMAIN), // Title
			[ $this, 'print_section_info_autoresponder' ], // Callback
			self::prefix(self::OPTION_GROUP) // Page
		);

		add_settings_field( 'recipients', // ID
			__('Mails of Recipients (separated by commas)', self::TEXT_DOMAIN), // Title
			[ $this, 'recipients_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_GENERAL // Section
		);

		add_settings_field( 'via_mail', // ID
			_x('Delivery Service Mail', 'Delivery Mail', self::TEXT_DOMAIN), // Title
			[ $this, 'via_mail_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_GENERAL // Section
		);

		add_settings_field( 'styling', // ID
			__('Enqueue default styling?', self::TEXT_DOMAIN), // Title
			[ $this, 'styling_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_GENERAL // Section
		);

		add_settings_field( 'autoload', // ID
			__('Initialize automatically?', self::TEXT_DOMAIN), // Title
			[ $this, 'autoload_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_GENERAL // Section
		);

		add_settings_field( 'autoresponder', // ID
			__('Enable Autoresponder', self::TEXT_DOMAIN), // Title
			[ $this, 'autoresponder_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_AUTORESPONDER // Section
		);

		add_settings_field( 'autoresponder_name', // ID
			_x('Delivery Service Name', 'Autoresponder', self::TEXT_DOMAIN), // Title
			[ $this, 'autoresponder_name_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_AUTORESPONDER // Section
		);

		add_settings_field( 'autoresponder_mail', // ID
			_x('Delivery Service Mail', 'Autoresponder', self::TEXT_DOMAIN), // Title
			[ $this, 'autoresponder_mail_callback' ], // Callback
			self::prefix(self::OPTION_GROUP), // Page
			self::OPTION_SECTION_AUTORESPONDER // Section
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys
	 * @return array|mixed
	 */
	public function sanitize( $input ) {
		$data = $this->get_options();

		// Recipients set?
		$recipients = explode( ",", $input[ 'recipients' ] );
		if ( empty( $recipients ) ) {
			add_settings_error( self::OPTION_NAME, // setting title
				self::prefix('error_recipients_empty'), // error ID
				_('Please enter at least one mail of a recipient.', self::TEXT_DOMAIN), // error message
				'error' // type of message
			);
		}

		// Mails of recipients valid?
		for ( $i = 0; $i < count( $recipients ); $i++ ) {
			$mail = filter_var( trim( $recipients[ $i ] ), FILTER_VALIDATE_EMAIL );

			// Valid mail of current recipient?
			if($mail) {
				$recipients[ $i ] = $mail;
				continue;
			}

			add_settings_error( self::OPTION_NAME, // setting title
				self::prefix('error_recipients_invalid_format_' . count($recipients)), // error ID
				sprintf(__( 'Recipient mail "%s" is invalid.', self::TEXT_DOMAIN), $recipients[ $i ]), // error message
				'error' // type of message
			);

			// Delete from array.
			unset( $recipients[ $i ] );
			$i--;
		}

		// Add it to the data array.
		if(!empty($recipients)) $data['recipients'] =  implode( ',', $recipients );

		// Mail of delivery service valid?
		$via_mail = filter_var( trim( $input[ 'via_mail' ] ), FILTER_VALIDATE_EMAIL );
		if ( !$via_mail ) {
			add_settings_error( self::OPTION_NAME, // setting title
				self::prefix('via_mail'), // error ID
				__('Please enter a valid mail for the Delivery Service Mail.', self::TEXT_DOMAIN), // error message
				'error' // type of message
			);
		} else {
			// Add it to the data array.
			$data['via_mail'] = $via_mail;
		}

		// Is the autoload activated?
		$data[ 'autoload' ] = isset( $input[ 'autoload' ] ) && $input[ 'autoload' ] == '1';

		// Is the styling activated?
		$data[ 'styling' ] = isset( $input[ 'styling' ] ) && $input[ 'styling' ] == '1';

		// Is the autoresponder activated?
		$data[ 'autoresponder' ] = isset( $input[ 'autoresponder' ] ) && $input[ 'autoresponder' ] == '1';
		if($data[ 'autoresponder' ]) {
			// Sanitize name of delivery service (autoresponder).
			$data[ 'autoresponder_name' ] = sanitize_text_field( $input[ 'autoresponder_name' ] );

			// Mail of delivery service (autoresponder) valid?
			$autoresponder_mail = filter_var( trim( $input[ 'autoresponder_mail' ] ), FILTER_VALIDATE_EMAIL );
			if ( !$autoresponder_mail ) {
				add_settings_error( self::OPTION_NAME, // setting title
					self::prefix( 'autoresponder_mail' ), // error ID
					_x( 'Please enter a valid mail for the Delivery Service Mail (Autoresponder).', 'Autoresponder', self::TEXT_DOMAIN ), // error message
					'error' // type of message
				);
			}
			else {
				// Add it to the data array.
				$data[ 'autoresponder_mail' ] = $autoresponder_mail;
			}
		}

		return $data;
	}

	/**
	 * Print the Section text for general section
	 */
	public function print_section_info_general() {
		_e('Define your desired general settings of the widget here.', self::TEXT_DOMAIN);
	}

	/**
	 * Print the Section text for autoresponder section
	 */
	public function print_section_info_autoresponder() {
		_e('Define your desired autoresponder settings here.', self::TEXT_DOMAIN);
	}

	public function recipients_callback() {
		$options = $this->get_options();
		print '<input type="text" id="recipients" name="' . self::prefix(self::OPTION_NAME) . '[recipients]" value="' . ( isset( $options[ 'recipients' ] ) ? esc_attr( $options[ 'recipients' ] ) : '' ) . '" >';
	}

	public function via_mail_callback() {
		$options = $this->get_options();
		print '<input type="text" id="via_mail" name="' . self::prefix(self::OPTION_NAME) . '[via_mail]" value="' . ( isset( $options[ 'via_mail' ] ) ? esc_attr( $options[ 'via_mail' ] ) : '' ) . '">';
	}

	public function styling_callback() {
		$options = $this->get_options();
		print '<input type="checkbox" id="styling" name="' . self::prefix(self::OPTION_NAME) . '[styling]" value="1" ' . checked(true, $options['styling'], false) . '>';
	}

	public function autoload_callback() {
		$options = $this->get_options();
		print '<input type="checkbox" id="autoload" name="' . self::prefix(self::OPTION_NAME) . '[autoload]" value="1" ' . checked(true, $options['autoload'], false) . '>';
	}

	public function autoresponder_callback() {
		$options = $this->get_options();
		print '<input type="checkbox" class="' . self::prefix('autoresponder_commander') . '" id="autoresponder" name="' . self::prefix(self::OPTION_NAME) . '[autoresponder]" value="1" ' . checked(true, $options['autoresponder'], false) . '>';
	}

	public function autoresponder_name_callback() {
		$options = $this->get_options();
		print '<input type="text" class="' . self::prefix('autoresponder_complier') . '" id="autoresponder_name" name="' . self::prefix(self::OPTION_NAME) . '[autoresponder_name]" value="' . ( isset( $options[ 'autoresponder_name' ] ) ? esc_attr( $options[ 'autoresponder_name' ] ) : '' ) . '"' . (!$options['autoresponder'] ? ' disabled' : '') . '>';
	}

	public function autoresponder_mail_callback() {
		$options = $this->get_options();
		print '<input type="text" class="' . self::prefix('autoresponder_complier') . '" id="autoresponder_mail" name="' . self::prefix(self::OPTION_NAME) . '[autoresponder_mail]" value="' . ( isset( $options[ 'autoresponder_mail' ] ) ? esc_attr( $options[ 'autoresponder_mail' ] ) : '' ) . '"' . (!$options['autoresponder'] ? ' disabled' : '') . '>';
	}

	public function replaceText($text, $allow_newlines = false) {
		$options = $this->get_options();
		$replacements = [
			'via_mail()' => $options['via_mail'],
			'autoresponder_name()' => $options['autoresponder_name'],
			'autoresponder_mail()' => $options['autoresponder_mail'],
			'blog_name()' => get_option('blogname'),
			'name()' => $this->api_name,
			'mail()' => $this->api_mail,
			'message()' => $this->api_message
		];
		if($allow_newlines) $replacements['newline()'] = "\n";
		return str_replace(array_keys($replacements), array_values($replacements), $text);
	}

	function api() {
		$options = $this->get_options();
		$output = [ 'success' => false ];
		header( "Content-Type: application/json; charset=utf-8" );

		// Nonce ok?
		$output['success'] = isset( $_REQUEST[ 'nonce' ] ) && wp_verify_nonce( $_REQUEST[ 'nonce' ], 'jw_lightcontactform_nonce' );

		// Name ok?
		$this->api_name = filter_var( trim( $_REQUEST[ 'name' ] ), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES );
		$output['success'] &= !empty($this->api_name);

		// Mail ok?
		$this->api_mail = filter_var( trim( $_REQUEST[ 'mail' ] ), FILTER_VALIDATE_EMAIL );
		$output['success'] &= !empty($this->api_mail);

		// Message ok?
		$this->api_message = filter_var( trim( $_REQUEST[ 'message' ] ), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES );
		$output['success'] &= !empty($this->api_message);

		// Break up on errors.
		if(!$output['success']) die( json_encode( $output ) );

		// Send mail.
		$headers = [
			'Content-Type: text/plain; charset=utf-8',
			'Reply-To: ' . $this->api_name . ' <' . $this->api_mail . '>',
			'From: ' . self::replaceText(_x('blog_name() - Delivery Service', 'Delivery service name to recipients', self::TEXT_DOMAIN ) ) . ' <' . $options[ 'via_mail' ] . '>'
		];
		$output['success'] = wp_mail( $options[ "recipients" ], self::replaceText(_x('blog_name() - New Mail by name()', 'Subject to the recipients - You can use blog_name(), name(), mail(), message(), via_mail(), autoresponder_name() and autoresponder_mail() as variables', self::TEXT_DOMAIN)), self::replaceText(_x('name() wrote:newline()newline()message()', 'Message to the recipients - You can use blog_name(), name(), mail(), message(), via_mail(), newline(), autoresponder_name() and autoresponder_mail() as variables', self::TEXT_DOMAIN), true), $headers );
		if(!$output['success']) die( json_encode( $output ) );

		// Send autoresponder, if activated.
		$headers = [
			'Content-Type: text/plain; charset=utf-8',
			'From: ' . $options[ 'autoresponder_name' ] . ' <' . $options[ 'autoresponder_mail' ] . '>'
		];
		$output['success'] = wp_mail( $this->api_mail, self::replaceText(_x('blog_name() - Mail sent', 'Subject to the sender (Autoresponder) - You can use blog_name(), name(), mail(), message(), via_mail(), autoresponder_name() and autoresponder_mail() as variables', self::TEXT_DOMAIN)), self::replaceText(_x('name(),newline()newline()thank you for contacting me. Your message was sent successfully and will be answered as soon as possible. This message was autogenerated.newline()newline()Your original message:newline()newline()message()', 'Message to the sender (Autoresponder) - You can use blog_name(), name(), mail(), message(), via_mail(), newline(), autoresponder_name() and autoresponder_mail() as variables', self::TEXT_DOMAIN), true), $headers );
		if(!$output['success']) die( json_encode( $output ) );

		// Make response.
		die( json_encode( $output ) );
	}

	public function generate_widget($atts, $content = null) {
		$atts = shortcode_atts([
			'class' => 'jw_lightcontactform_widget'
		], $atts);

		// Echo the wrapper.
		?>
		<div class="<?php echo $atts['class'];?>">
			<?php
			if(empty($content)):
				?>
				<input data-name type="text" placeholder="<?php _e('Your Name', self::TEXT_DOMAIN);?>">
				<input data-mail type="text" placeholder="<?php _e(_e('Your Mail'), self::TEXT_DOMAIN);?>">
				<textarea data-snippet placeholder="<?php _e('Your Message', self::TEXT_DOMAIN);?>"></textarea>
				<input type="submit" data-submit value="<?php _e('Send Message', self::TEXT_DOMAIN);?>">
		<?php
			else:
				echo do_shortcode($content);
			endif;
			?>
		</div>
		<?php
	}
}

new jw_lightcontactform();
