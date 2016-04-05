<?php

class WordPen_Metabox {
	private static $args = array();
	public static function init( $args ) {
		$defaults = array(
		);
		$args = wp_parse_args( $args, $defaults );
		self::$args[] = $args;
		if ( !isset( $args['posttype'] ) || !is_array( $args['posttype'] ) ) {
			return false;
		}
		foreach ( $args['posttype'] as $posttype ) {
			self::$args[$posttype][] = $args;
			add_action( "add_meta_boxes", array( 'WordPen_Metabox', 'boxes' ), 10, 2 );
		}
    	add_action( 'save_post', array( 'WordPen_Metabox', 'save' ) );
	}
	public static function boxes( $posttype, $post ) {
		if ( isset( self::$args[$posttype] ) ) {
			foreach ( self::$args[$posttype] as $box ) {
				self::box( $box, $posttype );
			}
		}
	}

	public static function box( $box, $posttype ) {
		$defaults = array(
			'callback'		=> array( 'WordPen_Metabox', 'box_callback' ),
			'screen'		=> $posttype,
			'context'		=> 'advanced',
			'priority'		=> 'default',
			'callback_args' => array(
				'fields' => $box['fields'],
			)
		);
		$args = wp_parse_args( $box, $defaults );
		extract($args);
		add_meta_box( $id, $title, $callback, $screen, $context, $priority, $callback_args );
	}
	public static function box_callback($post, $args) {
		$echo = '<table class="form-table wordpen-table"><tbody>';
		foreach ( $args['args']['fields'] as $field ) {
			$value = call_user_func( array( 'WordPen_Metabox', '_value_'.$field['field_type'] ), $field, $post );
			$echo .= call_user_func( array( 'WordPen_Metabox', '_input_'.$field['input_type'] ), $field, $value );
		}
		$echo .= '</tbody></table>';
		wp_nonce_field( 'wordpen_metabox_nonce', 'wordpen_metabox_nonce' );
		echo $echo;
	}

	public static function _input_text( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td>';
		$echo .= '<input name="'.$field['id'].'" type="text" id="'.$field['id'].'" value="'.$value.'" class="large-text">';
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
		return $echo;
	}
	public static function _input_justtext( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td>';
		$echo .= '<input type="text" readonly id="'.$field['id'].'" value="'.$value.'" class="large-text">';
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
		return $echo;
	}
	public static function _input_codepentext( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td class="codepentext">';
		$screen = get_current_screen();
		if ( 'add' == $screen->action && 'wordpen' == $screen->post_type && 'post' == $screen->base ) {
			$echo .= '<input name="'.$field['id'].'" type="text" id="'.$field['id'].'" class="large-text">';
			// $echo .= '<button class="wordpen-update button">'.__( 'Update from source').'</button>';
		} else {
			$old = get_post_meta( get_the_id(), $field['id'].'_back', true );
			$echo .= '<input name="'.$field['id'].'_back" type="text" id="'.$field['id'].'_back" value="'.$old.'" class="large-text" data-back="'.$old.'" readonly>';
			$echo .= '<button class="wordpen-update button">'.__( 'Update from source').'</button>';
		}
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
		return $echo;
	}
	public static function _input_textarea( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td>';
		$attributes = isset( $field['attributes'] ) ? $field['attributes']: array();
		foreach ( $attributes as $key => $val ) {
			$attributes[ $key ] = "{$key}=\"{$val}\"";
		}
		$attributes = implode( ' ', $attributes );
		if ( $attributes ) {
			$attributes = ' '.$attributes;
		}
		$echo .= '<textarea name="'.$field['id'].'" id="'.$field['id'].'" class="large-text"'.$attributes.'>'.esc_html( $value ).'</textarea>';
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
		$echo .= '<script>';
		$echo .= 'var myCodeMirror = CodeMirror.fromTextArea(document.getElementById("'.$field['id'].'"), { "mode":"css", "lineNumbers":true } );';
		$echo .= '</script>';
		return $echo;
	}
	public static function _input_codemirror( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td class="codemirror-td-container">';
		$attributes = isset( $field['attributes'] ) ? $field['attributes']: array();
		foreach ( $attributes as $key => $val ) {
			$attributes[ $key ] = "{$key}=\"{$val}\"";
		}
		$attributes = implode( ' ', $attributes );
		if ( $attributes ) {
			$attributes = ' '.$attributes;
		}
		$echo .= '<textarea name="'.$field['id'].'" id="'.$field['id'].'" class="large-text"'.$attributes.'>'.$value.'</textarea>';
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '<script>';
		$echo .= 'var myCodeMirror = CodeMirror.fromTextArea(document.getElementById("'.$field['id'].'"), { "mode":"css", "lineNumbers":true } );';
		$echo .= '</script>';
		$echo .= '</td>';
		$echo .= '</tr>';
		return $echo;
	}
	public static function _input_upload( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td>';
		$echo .= '<input name="'.$field['id'].'" type="text" id="'.$field['id'].'" value="'.$value.'" class="regular-text">';
		$echo .= '<input type="button" id="' . $field['id'] . '_button" class="button upload_button" value="' . esc_attr( $field['button_text'] ) . '" data-uploader_button_text="'.esc_attr( $field['uploader_button_text']).'" data-target="#'.$field['id'].'"/>';

		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
        wp_enqueue_media();
        wp_enqueue_script( 'harvestteachings-upload' );
		return $echo;
	}
	public static function _input_date( $field, $value ) {
		$value = date('Y-m-d',strtotime($value));
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td>';
		$echo .= '<input name="'.$field['id'].'" type="text" id="'.$field['id'].'" value="'.$value.'" class="regular-text datepicker">';
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('harvestteachings-datepicker');

		return $echo;
	}
	public static function _input_checkbox( $field, $value ) {
		$echo  = '<tr>';
		$echo .= '<th scope="row"><label for="'.$field['id'].'">'.$field['title'].'</label></th>';
		$echo .= '<td>';
		$checked = checked( true , $value, false );
		$echo .= '<input name="'.$field['id'].'" type="checkbox" id="'.$field['id'].'" value="1" '.$checked.'>';
		if ( isset($field['description']) ) {
			$echo .= '<p class="description">'.$field['description'].'</p>';
		}
		$echo .= '</td>';
		$echo .= '</tr>';
		return $echo;
	}


	public static function _value_custom_field( $field, $post ) {
		$value = get_post_meta( $post->ID, $field['id'], true);
		if ( !$value && isset( $field['default'] ) ) {
			$value = $field['default'];
		}
		return $value;
	}
	public static function _value_none( $field, $post ) {
		$value = get_post_meta( $post->ID, $field['id'], true);
		if ( !$value && isset( $field['default'] ) ) {
			$value = $field['default'];
		}
		return $value;
	}
	public static function _value_post_type( $field, $post ) {
		$value = get_post_meta( $post->ID, $field['id']);
		return $value;
	}

	public static function _save_none() {

	}
	public static function _save_custom_field( $post_id, $field, $value ) {
		$new_value = $value;
		update_post_meta( $post_id, $field['id'], $new_value );
	}
	public static function _save_post_type( $post_id, $field, $value ) {
					// 			if ( is_array( $_POST[$field['id']] ) ) {
					// 				delete_post_meta( $post_id, $field['id'] );
					// 				unset( $_POST[$field['id']][0] );
					// 				foreach ( $_POST[$field['id']] as $key => $value ) {
					// 					$new_value = sanitize_text_field( $value );
					// 					add_post_meta( $post_id, $field['id'], $new_value );
					// 				}

					// 			} else {
					// 				$new_value = sanitize_text_field( $_POST[$field['id']] );
					// 				update_post_meta( $post_id, $field['id'], $new_value );
					// 			}
	}


	public static function save( $post_id ) {
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
		$post = get_post( $post_id );
		if ( isset( self::$args[$post->post_type] ) ) {
			foreach ( self::$args[$post->post_type] as $box_id => $box ) {
				foreach ( $box['fields'] as $field_id => $field) {
					$value = isset( $_POST[$field['id']] ) ? $_POST[$field['id']] : false;
					$value = apply_filters( 'wordpen_metabox_field_save', $value, $post_id, $field_id, $field );
					call_user_func( array( 'WordPen_Metabox', "_save_{$field['field_type']}"), $post_id, $field, $value );
				}
			}
		}
	}

}
