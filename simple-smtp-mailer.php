<?php
/**
 * Plugin Name: Simple SMTP Mailer
 * Version: 1.0.5
 * Plugin URI: https://kibb.in/ssmtp
 * Author: Josh Mckibbin
 * Author URI: https://joshmckibbin.com
 * Description: Simplifies configuring WordPress to use SMTP instead of the PHP mail() function 
 * Text Domain: simple-smtp-mailer
 */


use PHPMailer\PHPMailer\PHPMailer;

// Don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;


class SimpleSMTPMailer {

    /**
     * Default Options
     */
    private $options;
    private $field_pre = 'simple_smtp_mailer';

    private const DEFAULT_OPTS = array(
        'host' => 'smtp.gmail.com',
        'username' => '',
        'password' => '',
        'port' => 587,
        'security' => 'tls',
        'debug' => FALSE
    );


    /**
     * Initialize the class
     */
    function __construct() {
        $this->initialize_options();

        add_action( 'admin_menu', array($this, 'settings_page') );
        add_action( 'admin_init', array($this, 'register_settings') );
        add_action( 'phpmailer_init', array($this, 'mail_config'), 10, 1);
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'settings_link') );

        # For debugging:
        if ( isset($this->options['debug']) && $this->options['debug'] == TRUE ) {
            add_action( 'wp_mail_failed', array($this, 'mail_errors'), 10, 1 );
        }
    }


    /**
     * Add Administration Menu
     */
    private function initialize_options() {
		$options = get_option( $this->field_pre .'_options' );

		if ( false === $options || empty( $options ) ) {
			// The options don't exist in the DB. Add them with default values.
			$options = self::DEFAULT_OPTS;
			add_option( $this->field_pre .'_options', $options );
		}

		$this->options = $options;
	}

    public function settings_page() {
        add_options_page(
            __('Simple SMTP Mailer', 'simple-smtp-mailer'), 
            __('Simple SMTP Mailer', 'simple-smtp-mailer'), 
            'manage_options', 
            'simple-smtp-mailer', 
            array($this, 'settings'));
    }

    public function settings() { ?>
        <div class="wrap">
            <h1>Simple SMTP Mailer Settings</h1>
            <form action="options.php" method="post">
                <?php 
                settings_fields( $this->field_pre );
                wp_nonce_field( $this->field_pre . '_options', $this->field_pre . '_options_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="<?php echo $this->field_pre; ?>-host"><?php _e('Host', 'simple-smtp-mailer'); ?></label></th>
                        <td><input type="text" id="<?php echo $this->field_pre; ?>-host" name="<?php echo $this->field_pre; ?>-host" size="70" value="<?php echo esc_attr( $this->options['host'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo $this->field_pre; ?>-username"><?php _e('Username', 'simple-smtp-mailer'); ?></label></th>
                        <td><input type="text" id="<?php echo $this->field_pre; ?>-username" name="<?php echo $this->field_pre; ?>-username" size="70" value="<?php echo esc_attr( $this->options['username'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo $this->field_pre; ?>-password"><?php _e('Password', 'simple-smtp-mailer'); ?></label></th>
                        <td><input type="password" id="<?php echo $this->field_pre; ?>-password" name="<?php echo $this->field_pre; ?>-password" size="70" value="" /><br><?php _e('* Leave blank to keep the same password.', 'simple-smtp-mailer'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo $this->field_pre; ?>-port"><?php _e('Port', 'simple-smtp-mailer'); ?></label></th>
                        <td><select id="<?php echo $this->field_pre; ?>-port" name="<?php echo $this->field_pre; ?>-port">
                            <option value="587"<?php if($this->options['port'] == 587) echo ' selected'; ?>><?php _e('587 (recommended)', 'simple-smtp-mailer'); ?></option>
                            <option value="465"<?php if($this->options['port'] == 465) echo ' selected'; ?>><?php _e('465', 'simple-smtp-mailer'); ?></option>
                            <option value="25"<?php if($this->options['port'] == 25) echo ' selected'; ?>><?php _e('25', 'simple-smtp-mailer'); ?></option>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo $this->field_pre; ?>-security"><?php _e('Security', 'simple-smtp-mailer'); ?></label></th>
                        <td><select id="<?php echo $this->field_pre; ?>-security" name="<?php echo $this->field_pre; ?>-security">
                            <option value="tls"<?php if($this->options['security'] == 'tls') echo ' selected'; ?>><?php _e('TLS (recommended)', 'simple-smtp-mailer'); ?></option>
                            <option value="ssl"<?php if($this->options['security'] == 'ssl') echo ' selected'; ?>><?php _e('SSL', 'simple-smtp-mailer'); ?></option>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo $this->field_pre; ?>-debug"><?php _e('Debug', 'simple-smtp-mailer'); ?></label></th>
                        <td><input type="checkbox" name="<?php echo $this->field_pre; ?>-debug"<?php if($this->options['debug'] == TRUE) echo ' checked'; ?>/></td>
                    </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    public function register_settings() {
        register_setting( $this->field_pre, $this->field_pre . '_options', array($this, 'settings_callback'));
    }

    public function settings_callback() {

        if ( !isset( $_POST[$this->field_pre . '_options_nonce'] ) || !wp_verify_nonce($_POST[$this->field_pre . '_options_nonce'], $this->field_pre . '_options') ) {

            wp_die( __('Sorry, your nonce did not verify.', 'simple-smtp-mailer') );

        } else {

            $host = sanitize_text_field($_POST[$this->field_pre . '-host']);
            $username = sanitize_text_field($_POST[$this->field_pre . '-username']);

            $password = '';
            if(isset($_POST[$this->field_pre . '-password']) && !empty($_POST[$this->field_pre . '-password'])){
                $password = sanitize_text_field($_POST[$this->field_pre . '-password']);
                $password = wp_unslash($password);
                $password = base64_encode($password);
            } else {
                $password = $this->options['password'];
            }
            
            $port = sanitize_text_field($_POST[$this->field_pre . '-port']);
            $security = sanitize_text_field($_POST[$this->field_pre . '-security']);
            $debug = ( isset($_POST[$this->field_pre . '-debug']) ? TRUE : FALSE );
            
            $this->options = array(
                'host' => $host,
                'username' => $username,
                'password' => $password,
                'port' => $port,
                'security' => $security,
                'debug' => $debug
            );

            return $this->options;
        }
    }


    /**
     * Add settings link in the plugin list
     */
    public function settings_link( $links ) {
        $links[] = '<a href="' . admin_url( 'options-general.php?page=simple-smtp-mailer' ) . '">' . __('Settings') . '</a>';
	    return $links;
    }


    /**
     * Make php_mail function use SMTP server
     */
    function mail_config( PHPMailer $mailer ) {
        $mailer->isSMTP();
        $mailer->Host = $this->options['host'];
        $mailer->Port = $this->options['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->options['username'];
        $mailer->Password = base64_decode($this->options['password']) ;
        $mailer->SMTPSecure = $this->options['security'];
        //$mailer->SMTPDebug = 2;
        $mailer->CharSet = 'utf-8';
    }


    /**
     * Error Reporting for debugging
     */
    function mail_errors( $wp_error ) {
        echo '<pre>';
        print_r($wp_error);
        echo '</pre>';
    }

}

new SimpleSMTPMailer();
