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

}



// initialize the plugin
$dbpac_user_manager = new DBPAC_User_Manager();