<?php
/**
 * Plugin Name: DBPAC User Manager
 * Plugin URI:
 * Description: User management functionality for DBPAC application.
 * Author: Raymond Linn
 * Version: 1.0
 * Author URI: http://raymondlinn.com/
 */

class DBPAC_User_Manager {
	/**
	 * Initialize the plugin
	 *
	 * only add filter and action hooks in the cnstructor
	 */
	public function __construct() {

		// Create the custom pages at plugin activation
		register_activation_hook(__FILE__, array('DBPAC_User_Manager', 'activate_plugin'));

		// Creating shortcode for login form
		add_shortcode('dbpac-login-form', array($this, 'render_login_form'));

		// hooking login_form_{action} for login
		add_action('login_form_login', array($this, 'redirect_to_dbpac_login'));

		// authentication hook
		add_filter('authenticate', array($this, 'maybe_redirect_at_authenticate'), 101, 3);

		// add redirect logout
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );

		// redirect user after logged in to member-account
		add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );

		// Creating shortcod for registration form
		add_shortcode( 'dbpac-register-form', array( $this, 'render_register_form' ) );

		// hooking login_form_{action} for register
		add_action( 'login_form_register', array( $this, 'redirect_to_custom_register' ) );

		// call the registration cod when user submits the form
		add_action( 'login_form_register', array( $this, 'do_register_user' ) );

		// Setting for reCAPTCHA
		add_filter( 'admin_init' , array( $this, 'register_settings_fields' ) );

		// adding the javascript for reCAPTCHA in footer
		add_action( 'wp_print_footer_scripts', array( $this, 'add_captcha_js_to_footer' ) );
	}

	/**
	 * Plugin activation hook
	 *
	 * Creates necessary pages needed by the plugin
	 */
	public static function activate_plugin() {
		// Information needed for creating the plugin;s pages
		$page_definitions = array(
			'member-login' => array( 
				'title' => __('Log In', 'dbpac-user'),
				'content' => '[dbpac-login-form]'
			),
			'member-account' => array(
				'title' => __('Your Account', 'dbpac-user'),
				'content' => '[dbpac-account-info]'
			),
			'member-register' => array(
			    'title' => __( 'Register', 'dbpac-login' ),
			    'content' => '[dbpac-register-form]'
		    ),
		    'member-password-lost' => array(
		        'title' => __( 'Forgot Your Password?', 'dbpac-login' ),
		        'content' => '[dbpac-password-lost-form]'
		    ),
		    'member-password-reset' => array(
		        'title' => __( 'Pick a New Password', 'dbpac-login' ),
		        'content' => '[dbpac-password-reset-form]'
		    )
		);

		foreach ($page_definitions as $slug => $page) {
			// Checke that the page does not exist already
			$query = new WP_Query('pagename=' . $slug);
			if(! $query->have_posts()) {
				// Add the page using the data from the array above
				wp_insert_post(
					array(
						'post_content' 	=> $page['content'],
						'post_name'		=> $slug,
						'post_title'	=> $page['title'],
						'post_status'	=> 'publish',
						'post_type'		=> 'page',
						'ping_status'	=> 'closed',
						'comment_status'=> 'closed',
					)
				);
			}
		}
	}


	/**
	 * A shortcode for rendering login form
	 *
	 * @param 	array 	$attributes Shortcode attributes
	 * @param 	string 	$content 	The text content for shortcode. Not use
	 *
	 * @return 	string 	The shortcode output
	 */
	public function render_login_form($attributes, $content = null) {

		// parse shortcode attributes
		$defualt_attributes = array('show_title' => false);
		$attributes = shortcode_atts($defualt_attributes, $attributes);
		$show_title = $attributes['show_title'];

		if (is_user_logged_in()){
			return __('You are already logged in.', 'dbpac-user');
		}

		// pass the redirect parameter to the wordpress login functionality: by default,
		// don't speicify  aredirect, but if a valid redirect URL has been passed as
		// request parameter, use it.
		$attributes['redirect'] = '';
		if (isset($_REQUEST['redirect_to'])) {
			$attributes['redirect'] = wp_validate_redirect($_REQUEST['redirect_to'], $attributes['redirect']);
		}

		// Error messages
		$errors = array();
		if (isset($_REQUEST['login'])) {
			$error_codes = explode(',', $_REQUEST['login']);

			foreach($error_codes as $code) {
				$errors [] = $this->get_error_message($code);
			}
		}
		$attributes['errors'] = $errors;

		// Check if user just logged out
		$attributes['logged_out'] = isset( $_REQUEST['logged_out'] ) 
									&& $_REQUEST['logged_out'] == true;

		// Render the login form using an external template
		return $this->get_template_html('login-form', $attributes);
	}

	/**
	 * Renders the contents of the given template to a string and returns it.
	 *
	 * @param string $template_name The name of the template to render (without .php)
	 * @param array  $attributes    The PHP variables for the template
	 *
	 * @return string               The contents of the template.
	 */
	private function get_template_html( $template_name, $attributes = null ) {
	    if ( ! $attributes ) {
	        $attributes = array();
	    }
	 
	    ob_start();
	 
	    do_action( 'dbpac_login_before_' . $template_name );
	 
	    require( 'templates/' . $template_name . '.php');
	 
	    do_action( 'dbpac_login_after_' . $template_name );
	 
	    $html = ob_get_contents();
	    ob_end_clean();
	 
	    return $html;
	}

	/**
	 * Redirect the user to the dbpac login page instad of wp-login.php
	 */
	public function redirect_to_dbpac_login(){
		if($_SERVER['REQUEST_METHOD'] == 'GET') {
			$redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : null;

			if (is_user_logged_in()) {
				$this->redirect_logged_in_user($redirect_to);
				exit;
			}

			// the rest are redirected to the login page
			$login_url = home_url('member-login');
			if (!empty($redirect_to)) {
				$login_url = add_query_arg('redirect_to', $redirect_to, $login_url);
			}

			wp_redirect($login_url);
			exit;
		}
	}

	/**
	 * Redirects the user to the correct page depending on whether he / she
	 * is an admin or not.
	 *
	 * @param string $redirect_to   An optional redirect_to URL for admin users
	 */
	private function redirect_logged_in_user( $redirect_to = null ) {
	    $user = wp_get_current_user();
	    if ( user_can( $user, 'manage_options' ) ) {
	        if ( $redirect_to ) {
	            wp_safe_redirect( $redirect_to );
	        } else {
	            wp_redirect( admin_url() );
	        }
	    } else {
	        wp_redirect( home_url( 'member-account' ) );
	    }
	}

	/**
	 * Redirect the user after authentication if there were any errors.
	 *
	 * @param Wp_User|Wp_Error  $user       The signed in user, or the errors that have occurred during login.
	 * @param string            $username   The user name used to log in.
	 * @param string            $password   The password used to log in.
	 *
	 * @return Wp_User|Wp_Error The logged in user, or error information if there were errors.
	 */
	function maybe_redirect_at_authenticate( $user, $username, $password ) {
	    // Check if the earlier authenticate filter (most likely, 
	    // the default WordPress authentication) functions have found errors
	    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	        if ( is_wp_error( $user ) ) {
	            $error_codes = join( ',', $user->get_error_codes() );
	 
	            $login_url = home_url( 'member-login' );
	            $login_url = add_query_arg( 'login', $error_codes, $login_url );
	 
	            wp_redirect( $login_url );
	            exit;
	        }
	    }
	 
	    return $user;
	}

	/**
	 * Finds and returns a matching error message for the given error code.
	 *
	 * @param string $error_code    The error code to look up.
	 *
	 * @return string               An error message.
	 */
	private function get_error_message( $error_code ) {
	    switch ( $error_code ) {
	        case 'empty_username':
	            return __( 'You do have an email address, right?', 'dbpac-login' );
	 
	        case 'empty_password':
	            return __( 'You need to enter a password to login.', 'dbpac-login' );
	 
	        case 'invalid_username':
	            return __(
	                "We don't have any users with that email address. Maybe you used a different one when signing up?",
	                'dbpac-login'
	            );
	 
	        case 'incorrect_password':
	            $err = __(
	                "The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
	                'dbpac-login'
	            );
	            return sprintf( $err, wp_lostpassword_url() );

	        // Registration errors 
			case 'email':
			    return __( 'The email address you entered is not valid.', 'dbpac-login' );
			 
			case 'email_exists':
			    return __( 'An account exists with this email address.', 'dbpac-login' );

			case 'password':
			    return __( 'The password you entered is not valid.', 'dbpac-login' );
			 
			case 'first_name':
			    return __( 'First name is required', 'dbpac-login' );

			case 'last_name':
			    return __( 'Last name is required', 'dbpac-login' );

			case 'address':
			    return __( 'Address is required', 'dbpac-login' );

			case 'phone':
			    return __( 'Phone number is required', 'dbpac-login' );

			case 'captcha':
    			return __( 'The Google reCAPTCHA check failed. Are you a robot?', 'personalize-login' );
			 
			case 'closed':
			    return __( 'Registering new users is currently not allowed.', 'dbpac-login' );
	 
	        default:
	            break;
	    }
	     
	    return __( 'An unknown error occurred. Please try again later.', 'dbpac-login' );
	}

	/**
	 * Redirect to custom login page after the user has been logged out.
	 */
	public function redirect_after_logout() {
	    $redirect_url = home_url( 'member-login?logged_out=true' );
	    wp_safe_redirect( $redirect_url );
	    exit;
	}

	/**
	 * Returns the URL to which the user should be redirected after the (successful) login.
	 *
	 * @param string           $redirect_to           The redirect destination URL.
	 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
	 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
	 *
	 * @return string Redirect URL
	 */
	public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
	    $redirect_url = home_url();
	 
	    if ( ! isset( $user->ID ) ) {
	        return $redirect_url;
	    }
	 
	    if ( user_can( $user, 'manage_options' ) ) {
	        // Use the redirect_to parameter if one is set, otherwise redirect to admin dashboard.
	        if ( $requested_redirect_to == '' ) {
	            $redirect_url = admin_url();
	        } else {
	            $redirect_url = $requested_redirect_to;
	        }
	    } else {
	        // Non-admin users always go to their account page after login
	        $redirect_url = home_url( 'member-account' );
	    }
	 
	    return wp_validate_redirect( $redirect_url, home_url() );
	}


	/**
	 * A shortcode for rendering the new user registration form.
	 *
	 * @param  array   $attributes  Shortcode attributes.
	 * @param  string  $content     The text content for shortcode. Not used.
	 *
	 * @return string  The shortcode output
	 */
	public function render_register_form( $attributes, $content = null ) {
	    // Parse shortcode attributes
	    $default_attributes = array( 'show_title' => false );
	    $attributes = shortcode_atts( $default_attributes, $attributes );

	    // Check if the user just registered
		$attributes['registered'] = isset( $_REQUEST['registered'] );

		// Retrieve recaptcha key
		$attributes['recaptcha_site_key'] = get_option( 'dbpac-login-recaptcha-site-key', null );

	 
	    if ( is_user_logged_in() ) {
	        return __( 'You are already signed in.', 'dbpac-login' );
	    } elseif ( ! get_option( 'users_can_register' ) ) {
	        return __( 'Registering new users is currently not allowed.', 'dbpac-login' );
	    } else {
	    	// Retrieve possible errors from request parameters
			$attributes['errors'] = array();
			if ( isset( $_REQUEST['register-errors'] ) ) {
			    $error_codes = explode( ',', $_REQUEST['register-errors'] );
			 
			    foreach ( $error_codes as $error_code ) {
			        $attributes['errors'] []= $this->get_error_message( $error_code );
			    }
			}
	        return $this->get_template_html( 'registration-form', $attributes );
	    }
	}

	/**
	 * Redirects the user to the custom registration page instead
	 * of wp-login.php?action=register.
	 */
	public function redirect_to_custom_register() {
	    if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
	        if ( is_user_logged_in() ) {
	            $this->redirect_logged_in_user();
	        } else {
	            wp_redirect( home_url( 'member-register' ) );
	        }
	        exit;
	    }
	}

	/**
	 * Validates and then completes the new user signup process if all went well.
	 *
	 * @param string $email         The new user's email address
	 * @param string $first_name    The new user's first name
	 * @param string $last_name     The new user's last name
	 * @param string $password     	The new user's password
	 * @param string $address       The new user's address - to uermeta
	 * @param string $phone         The new user's phone - to usermeta
	 *
	 * @return int|WP_Error         The id of the user that was created, or error if failed.
	 */
	private function register_user($email, $password, $first_name, $last_name, $address, $phone) {
	    $errors = new WP_Error();
	 
	    // Email address is used as both username and email. 
	    if ( ! is_email( $email ) ) {
	        $errors->add( 'email', $this->get_error_message( 'email' ) );
	        return $errors;
	    }
	 
	    if ( username_exists( $email ) || email_exists( $email ) ) {
	        $errors->add( 'email_exists', $this->get_error_message( 'email_exists') );
	        return $errors;
	    }

	    if (empty($password)) {
	    	$errors->add('password', $this->get_error_message('password'));
	    	return $errors;
	    }

	    if (empty($first_name)) {
	    	$errors->add('first_name', $this->get_error_message('first_name'));
	    	return $errors;
	    }

	    if (empty($last_name)) {
	    	$errors->add('last_name', $this->get_error_message('last_name'));
	    	return $errors;
	    }	    

	    // since $address and $phone are required, they need to be validated here
	    if (empty($address)) {
	    	$errors->add('address', $this->get_error_message('address'));
	    	return $errors;
	    }
	 	
	 	if (empty($phone)) {
	    	$errors->add('phone', $this->get_error_message('phone'));
	    	return $errors;
	    }

	    // Generate the password so that the subscriber will have to check email...
	    //$password = wp_generate_password( 12, false );
	 
	    $user_data = array(
	        'user_login'    => $email,
	        'user_email'    => $email,
	        'user_pass'     => $password,
	        'first_name'    => $first_name,
	        'last_name'     => $last_name,
	        'nickname'      => $first_name,
	    );
	 
	    $user_id = wp_insert_user( $user_data );

	    // add the usermeta with $address and $phone
	    update_user_meta($user_id, 'user_address', sanitize_text_field($address));
	    update_user_meta($user_id, 'user_phone', sanitize_text_field($phone));

	    wp_new_user_notification( $user_id ); // only send notification to admin
	 
	    return $user_id;
	}

	/**
	 * Handles the registration of a new user.
	 *
	 * Used through the action hook "login_form_register" activated on wp-login.php
	 * when accessed through the registration action.
	 */
	public function do_register_user() {
	    if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
	        $redirect_url = home_url( 'member-register' );
	 
	        if ( ! get_option( 'users_can_register' ) ) {
	            // Registration closed, display error
	            $redirect_url = add_query_arg( 'register-errors', 'closed', $redirect_url );
	        } elseif ( ! $this->verify_recaptcha() ) {
			    // Recaptcha check failed, display error
			    $redirect_url = add_query_arg( 'register-errors', 'captcha', $redirect_url );
			} else {
	            $email = $_POST['email'];
	            $password = $_POST['password'];
	            $first_name = sanitize_text_field( $_POST['first_name'] );
	            $last_name = sanitize_text_field( $_POST['last_name'] );
	            // handle on these following two fileds
	            $address = sanitize_text_field( $_POST['address'] );
	            $phone = sanitize_text_field( $_POST['phone'] );
	 
	            $result = $this->register_user( $email, $password, $first_name, $last_name , $address, $phone);
	 
	            if ( is_wp_error( $result ) ) {
	                // Parse errors into a string and append as parameter to redirect
	                $errors = join( ',', $result->get_error_codes() );
	                $redirect_url = add_query_arg( 'register-errors', $errors, $redirect_url );
	            } else {
	                // Success, redirect to login page.
	                $redirect_url = home_url( 'member-login' );
	                $redirect_url = add_query_arg( 'registered', $email, $redirect_url );
	            }
	        }
	 
	        wp_redirect( $redirect_url );
	        exit;
	    }
	}

	/**
	 * Registers the settings fields needed by the plugin.
	 */
	public function register_settings_fields() {
	    // Create settings fields for the two keys used by reCAPTCHA
	    register_setting( 'general', 'dbpac-login-recaptcha-site-key' );
	    register_setting( 'general', 'dbpac-login-recaptcha-secret-key' );
	 
	    add_settings_field(
	        'dbpac-login-recaptcha-site-key',
	        '<label for="dbpac-login-recaptcha-site-key">' . __( 'reCAPTCHA site key' , 'dbpac-login' ) . '</label>',
	        array( $this, 'render_recaptcha_site_key_field' ),
	        'general'
	    );
	 
	    add_settings_field(
	        'dbpac-login-recaptcha-secret-key',
	        '<label for="dbpac-login-recaptcha-secret-key">' . __( 'reCAPTCHA secret key' , 'dbpac-login' ) . '</label>',
	        array( $this, 'render_recaptcha_secret_key_field' ),
	        'general'
	    );
	}
	 
	public function render_recaptcha_site_key_field() {
	    $value = get_option( 'dbpac-login-recaptcha-site-key', '' );
	    echo '<input type="text" id="dbpac-login-recaptcha-site-key" name="dbpac-login-recaptcha-site-key" value="' . esc_attr( $value ) . '" />';
	}
	 
	public function render_recaptcha_secret_key_field() {
	    $value = get_option( 'dbpac-login-recaptcha-secret-key', '' );
	    echo '<input type="text" id="dbpac-login-recaptcha-secret-key" name="dbpac-login-recaptcha-secret-key" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * An action function used to include the reCAPTCHA JavaScript file
	 * at the end of the page.
	 */
	public function add_captcha_js_to_footer() {
	    echo "<script src='https://www.google.com/recaptcha/api.js'></script>";
	}

	/**
	 * Checks that the reCAPTCHA parameter sent with the registration
	 * request is valid.
	 *
	 * @return bool True if the CAPTCHA is OK, otherwise false.
	 */
	private function verify_recaptcha() {
	    // This field is set by the recaptcha widget if check is successful
	    if ( isset ( $_POST['g-recaptcha-response'] ) ) {
	        $captcha_response = $_POST['g-recaptcha-response'];
	    } else {
	        return false;
	    }
	 
	    // Verify the captcha response from Google
	    $response = wp_remote_post(
	        'https://www.google.com/recaptcha/api/siteverify',
	        array(
	            'body' => array(
	                'secret' => get_option( 'personalize-login-recaptcha-secret-key' ),
	                'response' => $captcha_response
	            )
	        )
	    );
	 
	    $success = false;
	    if ( $response && is_array( $response ) ) {
	        $decoded_response = json_decode( $response['body'] );
	        $success = $decoded_response->success;
	    }
	 
	    return $success;
	}

}



// initialize the plugin
$dbpac_user_manager = new DBPAC_User_Manager();