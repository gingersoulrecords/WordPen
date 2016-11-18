<?php

if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	// load our custom updater
	include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
}

if( !class_exists( 'EDD_License_Glue' ) ) {
	class EDD_License_Glue {
		// private static $site = 'http://www.gingersoulrecords.com';
		private $license = false;
		private $updater = false;
		private $args = false;
		public function __construct( $args ) {
			$defaults = array(
				'site'		=> 'http://www.gingersoulrecords.com',
				'plugin'	=> '',
				'menu'		=> '',
				'menu_title'	=> 'License Menu',
				'page_title'	=> 'License',
				'label'				=> 'License Key',
				'section_text'=> '',
				'file'		=> '', // MAIN plugin file
				'author'	=> 'Dave Bloom',
				'license_text'	=> 'License is %s',
				'license_text_invalid'	=> 'INVALID',
				'license_text_active'		=> 'ACTIVE',
			);
			$this->args = wp_parse_args( $args, $defaults );
			$this->license = $this->_get_license();
			if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				// load our custom updater
				include_once( $this->args['dir'] . 'EDD_SL_Plugin_Updater.php' );
			}
			$this->updater = new EDD_SL_Plugin_Updater( $this->args['site'], $this->args['file'], array(
				'version' 	=> $this->args['version'], 				// current version number
				'license' 	=> isset( $this->license['license_key'] ) ? $this->license['license_key'] : '', 		// license key (used get_option above to retrieve from DB)
				'item_name' => $this->args['plugin'], 	// name of this plugin
				'author' 		=> $this->args['author']  // author of this plugin
			) );
			add_action('admin_menu', array( $this, 'license_menu' ) );
			add_action('admin_init', array( $this, 'license_option' ) );
		}
		function _get_license() {
			return get_option( $this->args['plugin']."_edd_license" );
		}
		function license_menu() {
			add_submenu_page(
			 	$this->args['menu'],
				$this->args['menu_title'],
				$this->args['page_title'],
			  'manage_options',
			  $this->args['plugin'].'-license',
			  array( $this, 'license_page')
			);
		}
		function section(){
			echo $this->args['section_text'];
		}
		function field( $args ) {
			$license = $this->_get_license();
			echo "<input id='{$args['name']}' name='{$args['name']}' type='text' class='regular-text' value='".esc_attr( isset( $license['license_key'] ) ? $license['license_key'] : '' )."' />";
			if ( !isset( $license['license' ] ) ) {
				return true;
			}
			switch ( $license['license']) {
				case 'invalid':
					$status = '<span class="wp-ui-text-notification">'.$this->args['license_text_invalid'].'</span>';
				break;
				case 'active':
					$status =  '<span class="wp-ui-text-highlight">'.$this->args['license_text_active'].'</span>';
				break;
				default:
					$status =  '<span class="wp-ui-text-icon">'.strtoupper( $license['license'] ).'</span>';
				break;
			}
			echo '<br/>';
			echo '<p>';
			$text = sprintf( $this->args['license_text'], $status);
			echo $text;
			echo '</p>';
		}
		function license_option() {
			register_setting( $this->args['plugin'].'_edd_license', $this->args['plugin'].'_edd_license', array( $this, 'license_sanitize' ) );
	    // register a new section in the "wporg" page
	    add_settings_section(
	      $this->args['plugin'].'_edd_license',
	      '',
	      array( $this, 'section' ),
	      $this->args['plugin'].'_edd_license'
	    );
	    // register a new field in the "wporg_section_developers" section, inside the "wporg" page
	    add_settings_field(
	        $this->args['plugin'].'_edd_license', // as of WP 4.6 this value is used only internally
	        // use $args' label_for to populate the id inside the callback
	        $this->args['label'],
	        array( $this, 'field' ),
	        $this->args['plugin'].'_edd_license',
	        $this->args['plugin'].'_edd_license',
	        [
						'name'	=> $this->args['plugin'].'_edd_license',
	        ]
	    );
		}
		function license_sanitize( $new ) {
			if ( 'add_option' === debug_backtrace()[3]['function'] ) {
				return $new;
			}
			$new = trim( $new );
			$old = $this->_get_license();
			// deactivate license if empty license key was entered
			if ( !$new && isset( $old['license_key'] ) ) {
				$this->license_deactivate( $old['license_key'] );
				return $new;
			}
			if ( !$new ) {
				return $new;
			}
			if ( isset( $old['license_key'] ) && ( $new === $old['license_key'] ) ) {
				return $old;
			}
			$data = $this->license_activate( $new );
			$data['license_key'] = $new;
			return $data;
		}

		function license_page() {
			?>
			<div class="wrap">
				<h2><?php echo $this->args['page_title']; ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields( $this->args['plugin'].'_edd_license' ); ?>
					<?php do_settings_sections( $this->args['plugin'].'_edd_license' ); ?>
					<?php submit_button(); ?>
				</form>
			<?php
		}
		function license_activate( $license ) {
			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'activate_license',
				'license' 	=> $license,
				'item_name' => urlencode( $this->args['plugin'] ),
			);
			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, $this->args['site'] ), array( 'timeout' => 15, 'sslverify' => false ) );
			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;
			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );
			return $license_data;
		}
		function license_deactivate( $license ) {
			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'deactivate_license',
				'license' 	=> $license,
				'item_name' => urlencode( $this->args['plugin'] ),
			);
			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, $this->args['site'] ), array( 'timeout' => 15, 'sslverify' => false ) );
			// make sure the response came back okay
			if ( is_wp_error( $response ) ) {
				return false;
			}
			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );
			if( $license_data['license'] == 'deactivated' ) {
				return true;
			}
			return false;
		}
	}
}
