<?php
/*
Plugin Name: WPLS Push
Plugin URI: http://www.wpletsstart.com
Description: 
Author: WPLS
Version: 1.0.0
Author URI: http://www.wpletsstart.com
Text Domain: wpls-push
*/

if ( !class_exists( 'WPLS_Push' ) ) :

class WPLS_Push {
	private $data;

	public function __construct() {
		
	}

	public static function instance() {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self();
			$instance->setup_actions();
		}

		return $instance;
	}

	private function setup_actions() {
		add_action( 'admin_menu', array( $this, 'admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'head' ) );
		add_action( 'update_option_wpls_push_settings', array( $this, 'manifest' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'footer' ) );
		add_action( 'init', array( $this, 'showNotification' ) );
		add_action( 'wp_insert_post', array( $this, 'startSending' ), 10, 3 );
		register_activation_hook( __FILE__, array( $this, 'buildWorker' ) );

		add_action( 'wp_ajax_wpls_push_subscribes', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_wpls_push_subscribes', array( $this, 'ajax' ) );
	}

	public function enqueue() {
		wp_enqueue_script( 'wpls-push', plugin_dir_url( __FILE__ ) . 'script.js', array(), '1.0.0' );
		$localize = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'worker' => plugin_dir_url( __FILE__ ) . 'load-scripts.php?s=worker',
			'nonce' => wp_create_nonce( 'wpls_push_subscribes' )
		);

		wp_localize_script( 'wpls-push', 'wpls_push', $localize );
	}

	public function head() {
		?> <link rel="manifest" href="<?php echo plugin_dir_url( __FILE__ ) . 'load-scripts.php?s=manifest' ?>"> <?php
	}

	public function footer() {
		$subscribes = get_option( 'wpls_push_subscribes', array() );
		$this->push($subscribes);
	}

	public function admin_page() {
		add_submenu_page(
			'tools.php',
			__( 'WPLS Push', 'wpls-push' ),
			__( 'WPLS Push', 'wpls-push' ),
			'manage_options',
			basename( __FILE__, '.php' ),
			array( $this, 'admin_screen' )
		);
	}

	public function register_settings() {
		add_settings_section( 'wpls_push_settings', null, false, 'wpls_push_settings' );

		add_settings_field(
			'wpls_push_settings[api_key]',
			__( 'Google API key', 'wpls-push' ),
			array( $this, 'text' ),
			'wpls_push_settings',
			'wpls_push_settings',
			array(
				'id' => 'api_key',
				'name' => 'wpls_push_settings[api_key]',
				'placeholder' => __( 'Your Project Number', 'wpls-push' ),
				'value' => $this->get_option( 'api_key' )
			)
		);

		add_settings_field(
			'wpls_push_settings[sender_id]',
			__( 'Sender ID', 'wpls-push' ),
			array( $this, 'text' ),
			'wpls_push_settings',
			'wpls_push_settings',
			array(
				'id' => 'sender_id',
				'name' => 'wpls_push_settings[sender_id]',
				'placeholder' => __( 'Your Sender ID', 'wpls-push' ),
				'value' => $this->get_option( 'sender_id' )
			)
		);

		register_setting( 'wpls_push_settings', 'wpls_push_settings' );


		$notifications = get_option( 'wpls_push_cache_notifications', array() );
		if ( empty( $notifications ) ) {
			update_option( 'wpls_push_cache_notifications', array(
				array(
					'title' => 'New Notification',
					'body' => 'Test message',
					'icon' => plugin_dir_url(__FILE__) . 'icon.png',
					'url' => get_site_url()
				)
			) );
		}
	}

	public function admin_screen() {
		?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title() ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpls_push_settings' ) ?>
				<?php do_settings_sections( 'wpls_push_settings' ); ?>
				<?php submit_button() ?>
			</form>
		</div>
		<?php
	}

	public function text( $args ) {
		$defaults = array(
			'name'				=> null,
			'name' 				=> null,
			'value' 			=> null,
			'placeholder' 		=> null,
			'class' 			=> 'regular-text',
			'disabled' 			=> false,
			'data' 				=> array(),
			'autocomplete' 		=> '',
			'desc'				=> '',
		);

		$args = wp_parse_args( $args, $defaults );

		$args['name'] = !is_null( $args['name'] ) ? $args['name'] : $args['id'];

		$disabled = $args['disabled'] ? ' disabled="disabled" ' : '';

		$data = '';
		if ( $args['data'] ) {
			foreach( $data as $k => $v ) {
				$data .= sprintf( ' data-%s="%s" ', $k, $v );
			}
		}

		$html = '<input type="text" id="'. esc_attr( $args['id'] ) .'" name="'. esc_attr( $args['name'] ) .'" value="'. esc_attr( $args['value'] ) .'" autocomplete="'. esc_attr( $args['autocomplete'] ) .'" class="'. esc_attr( $args['class'] ) .'" placeholder="'. esc_attr( $args['placeholder'] ) .'" '. $data . $disabled .' />';

		if ( $args['desc'] ) {
			$html .= '<span class="description">' . wp_kses_post( $args['desc'] ) . '</span>';
		}

		echo $html;
	}

	public function get_option( $k, $default = null ) {
		$options = get_option( 'wpls_push_settings', array() );
		$value = isset( $options[$k] ) ? $options[$k] : $default;
		$value = apply_filters( 'wpls_push_get_option', $value, $k, $default );
		return $value;
	}

	public function add_notify( $args ) {
		$default = array(
			'body' => '',
			'title' => __( 'New Message', 'wpls-push' ),
			'icon' => plugin_dir_path( __FILE__ ) . 'icon.png',
			'url' => ''
		);

		$args = wp_parse_args( $args, $default );
		$notifications = get_option( 'wpls_push_cache_notifications', array() );
		$notifications[] = $args;
		update_option( 'wpls_push_cache_notifications', $notifications );
	}

	public function manifest( $old_value, $value ) {
		if ( is_array( $value ) && !empty( $value ) && isset( $value ) ) {
			file_put_contents( plugin_dir_path( __FILE__ ) . 'manifest.json' , json_encode( array( 'name' => get_bloginfo( 'name' ), 'gcm_sender_id' => $this->get_option( 'sender_id' ) ) ) );
		}
	}

	public function ajax() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'wpls_push_subscribes' ) ) {
			$subscribes = get_option( 'wpls_push_subscribes', array() );
			$endpoint = esc_url( $_POST['endpoint'] );
			$endpointPath = explode( '/', $endpoint );
			$last = count( $endpointPath ) - 1;
			$subscribes[] = $endpointPath[ $last ];
			update_option( 'wpls_push_subscribes', $subscribes );
		}
		die;
	}

	public function startSending( $post_id, $post, $update ) {
		if ( 'post' == get_post_type( $post_id ) && 'publish' == get_post_status( $post_id ) ) {
			$subscribes = get_option( 'wpls_push_subscribes', array() );

			if ( !empty( $subscribes ) ) {
				$this->add_notify( array(
					'title' => __( 'New Post', 'wpls-push' ),
					'body' => get_the_title( $post_id ),
					'url' => get_permalink( $post_id )
				) );
				$this->push( $subscribes );
			}
		}
	}

	public function push( $subscribes ) {
		$host = 'android.googleapis.com';
		$path = '/gcm/send';
		$http_host = 'https://' . $host . $path;
		$port = 443;
		$body = json_encode( array(
			'registration_ids' => $subscribes
		) );
		$response = '';
		$content_length = strlen( $body );
		if ( !empty( $subscribes ) ) {
			if ( !function_exists( 'wp_remote_post' ) ) {
				require_once( ABSPATH . WP_INC . '/http.php' );
			}

			$http_args = array(
				'body' => $body,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'key=' . $this->get_option( 'api_key' )
				),
			);

			$response = wp_remote_post( $http_host, $http_args );
			return $response;
		}
	}

	public function showNotification() {
		if ( isset( $_GET['wpls-push-notification'] ) ) {
			$notifications = get_option( 'wpls_push_cache_notifications', array() );
			delete_option( 'wpls_push_cache_notifications' );
			header( 'Content-Type: application/json' );
			echo json_encode( $notifications );
			die;
		}
	}

	public function buildWorker() {
		$worker = "var debug = true;
var notification;
if (debug) console.log('Started',self);
self.addEventListener('install',function(e){
	self.skipWaiting();
	if (debug) console.log('install', e);
})

self.addEventListener('activate',function(e){
	if (debug) console.log('activate',e);
})

self.addEventListener('push', function(e) {
	if (debug) console.log('Push message received', e);
	e.waitUntil(
		fetch('".get_site_url()."?wpls-push-notification').then(function(response) {  
			response.json().then(function(json) {
				if (debug) console.log(json);
				var promises = [];
				for (var i = 0; i < json.length; i++) {
					var single_notification = json[i];

					if(!single_notification.title)
					    single_notification.title = 'New Notification!';

					if(!single_notification.body)
					    single_notification.body = '';

					promises.push(showNotification(single_notification.title, single_notification.body, single_notification.url));
				}
				Promise.all(promises);
			});
		})
	)
});

self.addEventListener('notificationclick', function(event) {
	event.notification.close();
	console.log('click', event.notification);
	event.waitUntil(clients.openWindow(event.notification.data).focus());
});


function showNotification(title, body, data) {
    var options = {
        body: body,
        data: data,
        icon: '". $this->get_option( 'icon', plugin_dir_url( __FILE__ ) . 'icon.png' ) ."'
    };
    return self.registration.showNotification(title, options);
}
		";
		return $worker;
	}
}

function wpls_push() {
	return WPLS_Push::instance();
}

global $wpls_push;
$wpls_push = wpls_push();

endif;