<?php

if ( ! defined ( 'ABSPATH' ) ) {
	die ( 'No script kiddies please!' );
}

/**
 * Returns the registered crop size key that matches $size exactly, or false.
 */
function yoimg_get_valid_crop_size( $size ) {
	if ( ! is_scalar( $size ) ) {
		return false;
	}
	$size  = sanitize_text_field( wp_unslash( (string) $size ) );
	$sizes = yoimg_get_image_sizes();
	return isset( $sizes[ $size ] ) && $sizes[ $size ]['crop'] == 1 ? $size : false;
}

/**
 * Returns true when $id is an image attachment the current user may use as a crop source replacement.
 */
function yoimg_is_valid_replacement( $id ) {
	return $id > 0 && wp_attachment_is_image( $id ) && current_user_can( 'upload_files' );
}

function yoimg_crop_image(){
	check_ajax_referer( 'yoimg_ajax', '_wpnonce' );
	$_required_args = array(
		'post',
		'size',
		'width',
		'height',
		'x',
		'y',
		'quality',
	);
	$_args          = array();
	foreach( $_required_args as $_key ){
		if ( ! isset( $_POST[ $_key ] ) ) {
			wp_send_json( false );
		}
		$_args[ $_key ] = wp_unslash( $_POST[ $_key ] );
	}
	do_action( 'yoimg_pre_crop_image' );
	$result = yoimg_crop_this_image( $_args );
	do_action( 'yoimg_post_crop_image' );
	wp_send_json( $result );
}

function yoimg_crop_this_image( $args ){
	$req_post = isset( $args['post'] ) ? absint( $args['post'] ) : 0;
	$req_size = isset( $args['size'] ) ? yoimg_get_valid_crop_size( $args['size'] ) : false;
	if ( ! $req_post || ! $req_size || ! current_user_can( 'edit_post', $req_post ) || ! wp_attachment_is_image( $req_post ) ) {
		return false;
	}
	foreach ( array( 'width', 'height', 'x', 'y', 'quality' ) as $_key ) {
		if ( ! isset( $args[ $_key ] ) || ! is_numeric( $args[ $_key ] ) ) {
			return false;
		}
	}
	$req_width   = (float) $args['width'];
	$req_height  = (float) $args['height'];
	$req_x       = (float) $args['x'];
	$req_y       = (float) $args['y'];
	$req_quality = min( 100, max( 1, absint( $args['quality'] ) ) );
	if ( $req_width <= 0 || $req_height <= 0 || $req_x < 0 || $req_y < 0 ) {
		return false;
	}

	$yoimg_retina_crop_enabled = yoimg_is_retina_crop_enabled_for_size( $req_size );
	$img_path                  = _load_image_to_edit_path( $req_post );
	$attachment_metadata       = wp_get_attachment_metadata( $req_post );
	if ( ! is_array( $attachment_metadata ) || empty( $attachment_metadata['file'] ) ) {
		return false;
	}

	// Append a timestamp to images to clear external caches (only when enabled in the YoImages settings).
	$crop_options      = get_option( 'yoimg_crop_settings' );
	$filepath          = pathinfo( $attachment_metadata['file'] );
	$pre_crop_filename = isset( $attachment_metadata['sizes'][ $req_size ]['file'] ) ? $attachment_metadata['sizes'][ $req_size ]['file'] : null;
	if ( $pre_crop_filename && ! empty( $crop_options['cachebusting_is_active'] ) && isset( $filepath['extension'] ) ) {
		$attachment_metadata['sizes'][ $req_size ]['file'] = $filepath['filename'] . '-crop-' . time() . '-' . (int) round( $req_width ) . 'x' . (int) round( $req_height ) . '.' . $filepath['extension'];
	}
	// The metadata is saved once, after the crop is written, so a failed crop never leaves it pointing at a missing file.

	if ( isset( $attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size]['replacement'] ) ) {
		$replacement = absint( $attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size]['replacement'] );
	} else {
		$replacement = null;
	}
	$has_replacement = ! empty ( $replacement ) && yoimg_is_valid_replacement( $replacement );
	if ( $has_replacement ) {
		$replacement_path      = _load_image_to_edit_path( $replacement );
		$img_editor            = wp_get_image_editor( $replacement_path );
		$full_image_attributes = wp_get_attachment_image_src( $replacement, 'full' );
	} else {
		$img_editor            = wp_get_image_editor( $img_path );
		$full_image_attributes = wp_get_attachment_image_src( $req_post, 'full' );
	}
	if ( is_wp_error( $img_editor ) ) {
		yoimg_log( $img_editor );
		return false;
	}
	if ( ! $full_image_attributes ) {
		return false;
	}
	// The crop box must sit inside the source image (1px tolerance for cropper rounding).
	if ( $req_x + $req_width > $full_image_attributes[1] + 1 || $req_y + $req_height > $full_image_attributes[2] + 1 ) {
		return false;
	}
	$cropped_image_sizes = yoimg_get_image_sizes( $req_size );
	$is_crop_smaller     = $full_image_attributes[1] < $cropped_image_sizes['width'] || $full_image_attributes[2] < $cropped_image_sizes['height'];
	$crop_width          = $cropped_image_sizes['width'];
	$crop_height         = $cropped_image_sizes['height'];
	$img_editor->crop( $req_x, $req_y, $req_width, $req_height, $crop_width, $crop_height, false );
	$img_editor->set_quality( $req_quality );
	$img_path_parts = pathinfo( $img_path );
	if ( empty( $attachment_metadata['sizes'][$req_size] ) || empty( $attachment_metadata['sizes'][$req_size]['file'] ) ) {
		$cropped_image_filename = yoimg_get_cropped_image_filename( $img_path_parts['filename'], $crop_width, $crop_height, $img_path_parts['extension'] );
		if ( isset( $attachment_metadata['sizes']['thumbnail']['mime-type'] ) ) {
			$mime_type = $attachment_metadata['sizes']['thumbnail']['mime-type'];
		} else {
			$filetype  = wp_check_filetype( $img_path );
			$mime_type = $filetype['type'];
		}
		$attachment_metadata['sizes'][$req_size] = array(
			'file' => $cropped_image_filename,
			'width' => $crop_width,
			'height' => $crop_height,
			'mime-type' => $mime_type
		);
	} else {
		$cropped_image_filename = $attachment_metadata['sizes'][$req_size]['file'];
	}
	$saved = $img_editor->save( $img_path_parts['dirname'] . '/' . $cropped_image_filename );
	if ( is_wp_error( $saved ) ) {
		yoimg_log( $saved );
		return false;
	}
	$cropped_image_retina_filename = null;
	$is_crop_retina_smaller        = false;
	if ( $yoimg_retina_crop_enabled ) {
		$img_editor_retina = wp_get_image_editor( $has_replacement ? $replacement_path : $img_path );
		if ( is_wp_error( $img_editor_retina ) ) {
			yoimg_log( $img_editor_retina );
			return false;
		}
		$crop_width_retina      = $crop_width * 2;
		$crop_height_retina     = $crop_height * 2;
		$is_crop_retina_smaller = $full_image_attributes[1] < $crop_width_retina || $full_image_attributes[2] < $crop_height_retina;
		if ( ! $is_crop_retina_smaller ) {
			$img_editor_retina->crop( $req_x, $req_y, $req_width, $req_height, $crop_width_retina, $crop_height_retina, false );
			$img_editor_retina->set_quality( $req_quality );
			$img_retina_path_parts         = pathinfo( $cropped_image_filename );
			$cropped_image_retina_filename = $img_retina_path_parts['filename'] . '@2x.' . $img_retina_path_parts['extension'];
			$saved_retina = $img_editor_retina->save( $img_path_parts['dirname'] . '/' . $cropped_image_retina_filename );
			if ( is_wp_error( $saved_retina ) ) {
				yoimg_log( $saved_retina );
				return false;
			}
		}
	}
	$attachment_metadata['sizes'][$req_size]['width'] = $crop_width;
	$attachment_metadata['sizes'][$req_size]['height'] = $crop_height;
	if ( empty( $attachment_metadata['yoimg_attachment_metadata']['crop'] ) ) {
		$attachment_metadata['yoimg_attachment_metadata']['crop'] = array();
	}
	$attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size] = array(
			'x' => $req_x,
			'y' => $req_y,
			'width' => $req_width,
			'height' => $req_height
	);
	if ( $has_replacement ) {
		$attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size]['replacement'] = $replacement;
	}
	wp_update_attachment_metadata( $req_post, $attachment_metadata );
	if( $yoimg_retina_crop_enabled ){
		return array(
			'previous_filename' => $pre_crop_filename,
			'filename'        => $cropped_image_filename,
			'smaller'         => $is_crop_smaller,
			'retina_filename' => $cropped_image_retina_filename,
			'retina_smaller'  => $is_crop_retina_smaller,
		);
	}
	return array(
		'previous_filename' => $pre_crop_filename,
		'filename' => $cropped_image_filename,
		'smaller'  => $is_crop_smaller,
	);
}

function yoimg_edit_thumbnails_page() {
	check_ajax_referer( 'yoimg_ajax', '_wpnonce' );
	global $yoimg_image_id;
	global $yoimg_image_size;
	$yoimg_image_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$yoimg_image_size = isset( $_GET['size'] ) ? sanitize_text_field( wp_unslash( $_GET['size'] ) ) : '';

	$sizes = yoimg_get_image_sizes ();
	$size = false;
	foreach ( $sizes as $size_key => $size_value ) {
		if ( $size_value['crop'] == 1 && $size_value['active'] ) {
			if ( $size_key == $yoimg_image_size ) {
				$size = $size_key;
				break;
			} else if ( ! $size ) {
				$size = $size_key;
			}
		}
	}
	$yoimg_image_size = $size;

	if ( $yoimg_image_id && $size && current_user_can ( 'edit_post', $yoimg_image_id ) && wp_attachment_is_image( $yoimg_image_id ) ) {
		include (YOIMG_CROP_PATH . '/html/edit-image-size.php');
	} else {
		wp_die( '', '', array( 'response' => 403 ) );
	}
}

function yoimg_replace_image_for_size() {
	check_ajax_referer( 'yoimg_ajax', '_wpnonce' );
	$id          = isset( $_POST['image'] ) ? absint( $_POST['image'] ) : 0;
	$size        = isset( $_POST['size'] ) ? yoimg_get_valid_crop_size( $_POST['size'] ) : false;
	$replacement = isset( $_POST['replacement'] ) ? absint( $_POST['replacement'] ) : 0;
	if ( $id && $size && current_user_can ( 'edit_post', $id ) && yoimg_is_valid_replacement( $replacement ) ) {
		$attachment_metadata = wp_get_attachment_metadata( $id );
		if ( is_array( $attachment_metadata ) ) {
			$attachment_metadata['yoimg_attachment_metadata']['crop'][$size]['replacement'] = $replacement;
			wp_update_attachment_metadata( $id, $attachment_metadata );
		}
	}
	die();
}

function yoimg_restore_original_image_for_size() {
	check_ajax_referer( 'yoimg_ajax', '_wpnonce' );
	$id   = isset( $_POST['image'] ) ? absint( $_POST['image'] ) : 0;
	$size = isset( $_POST['size'] ) ? yoimg_get_valid_crop_size( $_POST['size'] ) : false;
	if ( $id && $size && current_user_can ( 'edit_post', $id ) ) {
		$attachment_metadata = wp_get_attachment_metadata( $id );
		if ( is_array( $attachment_metadata ) ) {
			unset( $attachment_metadata['yoimg_attachment_metadata']['crop'][$size]['replacement'] );
			wp_update_attachment_metadata( $id, $attachment_metadata );
		}
	}
	die();
}

add_action( 'wp_ajax_yoimg_edit_thumbnails_page', 'yoimg_edit_thumbnails_page' );
add_action( 'wp_ajax_yoimg_restore_original_image_for_size', 'yoimg_restore_original_image_for_size' );
add_action( 'wp_ajax_yoimg_crop_image', 'yoimg_crop_image' );
add_action( 'wp_ajax_yoimg_replace_image_for_size', 'yoimg_replace_image_for_size' );
