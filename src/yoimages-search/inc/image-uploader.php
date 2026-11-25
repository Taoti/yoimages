<?php
if (! defined ( 'ABSPATH' )) {
	die ( 'No script kiddies please!' );
}
/**
 * Hosts remote images may be imported from. Extend with the yoimg_search_allowed_image_hosts filter
 * when registering a custom search provider.
 */
function yoimg_search_is_allowed_image_url( $image_url ) {
	if ( ! wp_http_validate_url( $image_url ) ) {
		return false;
	}
	$parts = wp_parse_url( $image_url );
	if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
		return false;
	}
	$host          = strtolower( $parts['host'] );
	$allowed_hosts = apply_filters( 'yoimg_search_allowed_image_hosts', array( 'images.unsplash.com' ) );
	foreach ( $allowed_hosts as $allowed_host ) {
		$allowed_host = strtolower( $allowed_host );
		if ( $host === $allowed_host || str_ends_with( $host, '.' . $allowed_host ) ) {
			return true;
		}
	}
	return false;
}

function yoimg_search_upload_images() {
	check_ajax_referer( 'yoimg_ajax', '_wpnonce' );
	$post_id = isset ( $_POST ['postId'] ) ? absint ( $_POST ['postId'] ) : 0;
	if ($post_id && current_user_can ( 'upload_files' ) && current_user_can ( 'edit_post', $post_id )) {
		
		header ( 'Content-type: application/json; charset=UTF-8' );
		$images_urls = isset ( $_POST ['imagesUrls'] ) && is_array ( $_POST ['imagesUrls'] ) ? array_slice ( array_map ( 'esc_url_raw', array_map ( 'wp_unslash', $_POST ['imagesUrls'] ) ), 0, 20 ) : array ();
		if (count ( $images_urls ) > 0) {
			status_header ( 200 );
			$results = array ();
			foreach ( $images_urls as $image_url ) {
				$result = array (
						'origUrl' => $image_url 
				);
				if ( ! yoimg_search_is_allowed_image_url ( $image_url ) ) {
					$result ['errorMessage'] = __ ( 'This image source is not allowed.', YOIMG_DOMAIN );
					status_header ( 400 );
					array_push ( $results, $result );
					continue;
				}
				$downloaded_image = download_url ( $image_url );
				if (is_wp_error ( $downloaded_image )) {
					$result ['errorMessage'] = $downloaded_image->get_error_message ();
					status_header ( 503 );
				} else {
					$downloaded_image_info = pathinfo ( $downloaded_image );
					$downloaded_image_basename = $downloaded_image_info ['basename'];
					$downloaded_image_basename .= '.gif';
					$downloaded_image_filetype = wp_check_filetype_and_ext ( $downloaded_image, $downloaded_image_basename, false );
					if (! wp_match_mime_types ( 'image', $downloaded_image_filetype ['type'] )) {
						$result ['errorMessage'] = __ ( 'The uploaded file is not a valid image. Please try again.' );
						wp_delete_file ( $downloaded_image );
						status_header ( 503 );
					} else {
						$time = current_time ( 'mysql' );
						if ($post = get_post ( $post_id )) {
							if (substr ( $post->post_date, 0, 4 ) > 0)
								$time = $post->post_date;
						}
						$uploads = wp_upload_dir ( $time );
						if (isset ( $uploads ['error'] ) && $uploads ['error'] === false) {
							$filename = isset ( $downloaded_image_filetype ['proper_filename'] ) ? $downloaded_image_filetype ['proper_filename'] : $downloaded_image_basename;
							$filename = wp_unique_filename ( $uploads ['path'], $filename );
							$new_file = $uploads ['path'] . '/' . $filename;
							$move_new_file = @ rename ( $downloaded_image, $new_file );
							if (false === $move_new_file) {
								$result ['errorMessage'] = __ ( 'The uploaded file could not be moved into the uploads directory.' );
								wp_delete_file ( $downloaded_image );
								status_header ( 503 );
							} else {
								$stat = stat ( dirname ( $new_file ) );
								$perms = $stat ['mode'] & 0000666;
								@ chmod ( $new_file, $perms );
								$url = $uploads ['url'] . '/' . $filename;
								$result ['imageUrl'] = $url;
								$attachment = array (
										'post_mime_type' => $downloaded_image_filetype ['type'],
										'guid' => $url,
										'post_parent' => $post_id,
										'post_title' => preg_replace ( '/\.[^.]+$/', '', basename ( $filename ) ),
										'post_content' => '',
										'post_status' => 'inherit' 
								);
								$image_id = wp_insert_attachment ( $attachment, $new_file, $post_id );
								if (! is_wp_error ( $image_id )) {
									require_once (ABSPATH . 'wp-admin/includes/image.php');
									wp_update_attachment_metadata ( $image_id, wp_generate_attachment_metadata ( $image_id, $new_file ) );
									$result ['imageId'] = $image_id;
								} else {
									$result ['errorMessage'] = 'cannot insert attachment';
									status_header ( 503 );
								}
							}
						} else {
							$result ['errorMessage'] = __ ( $uploads ['error'] );
							status_header ( 503 );
						}
					}
				}
				array_push ( $results, $result );
			}
			echo wp_json_encode ( $results );
		} else {
			status_header ( 204 );
		}
	} else {
		status_header ( 401 );
	}
	die ();
}

add_action ( 'wp_ajax_yoimg_search_upload_images', 'yoimg_search_upload_images' );