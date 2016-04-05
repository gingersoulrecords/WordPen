<?php
/*
Plugin Name: WordPen
Plugin URI: http://gingersoulrecords.com
Description: Import and insert snippets from Codepen as shortcodes.
Version: 0.1.0
Author: Dave Bloom
Author URI:  http://gingersoulrecords.com
*/

// TO DO haml compiler integration | http://codepen.io/goosey/pen/NGjMOe | http://codepen.io/xhepigerta/pen/bprWbR
// TO DO less compiler integration | http://codepen.io/dicson/pen/oxeeVr


// Working samples
// http://soul.tribuna.lt/?wordpen=http://codepen.io/five23/pen/bEoKu
// http://soul.tribuna.lt/?wordpen=http://codepen.io/chasebank/pen/GZvjBJ
// http://soul.tribuna.lt/?wordpen=http://codepen.io/AdrienBachmann/pen/wGqqVJ
// http://soul.tribuna.lt/?wordpen=http://codepen.io/jackrugile/pen/Xdaavx
// http://soul.tribuna.lt/?wordpen=http://codepen.io/bradyhouse/pen/GZvmjN

add_action( 'plugins_loaded', array( 'WordPen', 'init' ) );
class WordPen {
	public static $plugin_path = '';
	public static function init() {
		self::$plugin_path = plugin_dir_path( __FILE__ );
		if ( isset( $_REQUEST['wordpen'] ) ) {
			$result = self::import_pen( $_REQUEST['wordpen'] );
			var_dump( $result );
			die();
		}
		add_action( 'init',			array( 'WordPen', 'cpt_init' ) );
		// add_filter( 'wordpen_metabox_field_save', array( 'WordPen', 'maybe_import' ), 10, 3 );
    	add_action( 'save_post', 	array( 'WordPen', 'maybe_import' ), 100 );
		add_shortcode( 'wordpen' , array( 'WordPen', 'shortcode' ), 99, 1 );
		if ( is_admin() ) {
			self::postmeta_init();
			add_action( 'admin_enqueue_scripts',		array( 'WordPen', 'style' )	);
		}
	}
	public static function style() {
		wp_register_script( 'codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.13.2/codemirror.min.js' );
		wp_register_script( 'codemirror-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.13.2/mode/css/css.min.js',array( 'codemirror' ) );
		wp_register_script( 'wordpen-admin', plugins_url( 'wordpen-admin.js', __FILE__ ) );
		wp_enqueue_script( 'codemirror' );
		wp_enqueue_script( 'codemirror-css' );
		wp_enqueue_script( 'wordpen-admin' );
		wp_register_style( 'codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.13.2/codemirror.min.css' );
		wp_register_style( 'codemirror-theme', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.13.2/theme/cobalt.min.css', array( 'codemirror' ) );
		wp_register_style( 'wordpen-admin', plugins_url( 'wordpen-admin.css', __FILE__ ) );
		wp_enqueue_style( 'codemirror' );
		wp_enqueue_style( 'codemirror-theme' );
		wp_enqueue_style( 'wordpen-admin' );
	}
	public static function shortcode( $args, $content = '' ) {
		$pen_id = $args['id'];
		$html = get_post_meta( $pen_id, 'codepen_html', true );
		$css = get_post_meta( $pen_id, 'codepen_css', true );
		echo '<style>'.$css.'</style>';
		echo $html;
		$js = get_post_meta( $pen_id, 'codepen_js', true );
		echo '<script>'.$js.'</script>';
	}
	public static function import_pen( $url ) {
		$response = wp_remote_get( $url );
		$dom = new DOMDocument();
		$dom->loadHTML( $response['body'] );

		$data = $dom->getElementById( 'init-data' )->getAttribute( 'value' );
		$data = json_decode( $data, true );
		$data = $data['__pen'];
		$data = json_decode( $data, true );
		$result = array(
			'_codepen_css' => $data['css'],
			'_codepen_html' => $data['html'],
			'_codepen_js' => $data['js'],
			'_codepen_resources' => $data['resources'],
			'_codepen_import' => false,
			'_codepen_uri'	=> '',
			'_codepen_uri_back'	=> $url,
			// 'codepen_raw' => $data,
		);
		if ( 'scss' == $data['css_pre_processor'] ) {
			require_once( 'includes/scss.php' );
			$scss = new scssc();
			$result['css'] = $scss->compile( $result['css'] );
			unset($scss);
		}
		unset($data);
		return $result;
	}
	public static function maybe_import( $post_id ) {
		if ( ! isset( $_POST['wordpen_metabox_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['wordpen_metabox_nonce'], 'wordpen_metabox_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_REQUEST['_codepen_uri'] ) || ! $_REQUEST['_codepen_uri'] ) {
			return;
		}
		$result = self::import_pen( $_REQUEST['_codepen_uri'] );
		if ( $result ) {
			foreach( $result as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}		
	}

	public static function cpt_init() {
		$args = array(
			// 'public' 				=> false,
			'show_ui'	=> true,
			'label'  				=> __( 'WordPens', 'wordpen' ),
			'supports'			=> array( '' ),
			'has_archive'			=> false,
		);
		$args = apply_filters( 'wordpen_cpt_args', $args );
		register_post_type( 'wordpen', $args );
	}
	private static function postmeta_init() {
		require_once( self::$plugin_path . 'tiny/postmeta.php');
		$args = array(
			'id'	=> 'wordpen_box',
			'posttype' => array('wordpen'),
			'title' => __( 'WordPen information', 'wordpen' ),
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				'_codepen_uri' => array(
					'id' => '_codepen_uri',
					'field_type' => 'custom_field',
					'input_type' => 'codepentext',// date|upload
					'title' => __( 'Codepen URI', 'wordpen' ),
				),
				// '_codepen_uri_back' => array(
				// 	'id' => '_codepen_uri_back',
				// 	'field_type' => 'none',
				// 	'input_type' => 'justtext',// date|upload
				// 	'title' => __( 'Codepen URI', 'wordpen' ),
				// ),
				'_codepen_html' => array(
					'id' => '_codepen_html',
					'field_type' => 'custom_field',
					'input_type' => 'codemirror',// date|upload
					'title' => __( 'Codepen HTML', 'wordpen' ),
					'attributes' => array(
						'rows' => 15,
						'cols' => 30,
					),
				),
				'_codepen_css' => array(
					'id' => '_codepen_css',
					'field_type' => 'custom_field',
					'input_type' => 'codemirror',// date|upload
					'title' => __( 'Codepen CSS', 'wordpen' ),
					'attributes' => array(
						'rows' => 15,
					),
				),
				'_codepen_js' => array(
					'id' => '_codepen_js',
					'field_type' => 'custom_field',
					'input_type' => 'codemirror',// date|upload
					'title' => __( 'Codepen JavaScript', 'wordpen' ),
					'attributes' => array(
						'rows' => 15,
					),
				),
			),
		);
		$args = apply_filters( 'wordpen_postmeta', $args );
		WordPen_Metabox::init( $args );
	}
}
