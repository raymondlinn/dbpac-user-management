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

}



// initialize the plugin
$dbpac_user_manager = new DBPAC_User_Manager();