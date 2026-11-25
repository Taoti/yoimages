<?php

if ( ! defined ( 'ABSPATH' ) ) {
	die ( 'No script kiddies please!' );
}

$sizes = yoimg_get_image_sizes ();
?>
<h3 class="title"><?php esc_html_e( 'Image sizes are defined by the current theme', YOIMG_DOMAIN ); ?></h3>
<?php
if ( count($sizes ) > 3 ) {
	$current_theme = wp_get_theme();
	$current_theme_name = $current_theme->get( 'Name' );
?>

<p><?php esc_html_e( 'The sizes listed below are the crop and resize formats defined by ', YOIMG_DOMAIN ); ?> <?php echo esc_html( $current_theme_name ); ?></p>

<table class="form-table">
<tbody>

<?php
foreach ( $sizes as $size_key => $size_value ) {
	if (! in_array ( $size_key, array (
		'thumbnail',
		'medium',
		'large'
	) ) ) {
?>

<tr class="yoimg-size-row">
	<th scope="row"><?php echo esc_html( $size_value['name'] ); ?></th>
	<td>
		<?php if ( $size_value['crop'] == 1 ) { ?>
			<label class="yoimg-first"><?php esc_html_e( 'Width', YOIMG_DOMAIN ); ?>: <?php echo (int) $size_value['width']; ?></label>
			<label><?php esc_html_e( 'Height', YOIMG_DOMAIN ); ?>: <?php echo (int) $size_value['height']; ?></label>
		<?php } else { ?>
			<label class="yoimg-first"><?php esc_html_e( 'Max Width', YOIMG_DOMAIN ); ?>: <?php echo (int) $size_value['width']; ?></label>
			<label><?php esc_html_e( 'Max Height', YOIMG_DOMAIN ); ?>: <?php echo (int) $size_value['height']; ?></label>
		<?php } ?>
	</td>
</tr>

<tr class="yoimg-size-type-row">
	<th scope="row"><?php esc_html_e( 'Type:', YOIMG_DOMAIN ); ?></th>
	<td>
		<?php if ( $size_value['crop'] == 1 ) { ?>
			<label><?php esc_html_e( 'Hard cropped', YOIMG_DOMAIN ); ?>
				<a class="dashicons dashicons-editor-help yoimg-hard-crop-help" data-code="f223" href="javascript:;"></a>
			</label>
		<?php } else { ?>
			<label><?php esc_html_e( 'Resized', YOIMG_DOMAIN ); ?>
				<a class="dashicons dashicons-editor-help yoimg-resize-help" data-code="f223" href="javascript:;"></a>
			</label>
		<?php } ?>
	</td>
</tr>

<?php
	}
}

?>
</tbody>
</table>
<script>
jQuery(document).ready(function(){
	jQuery('.yoimg-hard-crop-help').pointer({
		<?php $contentStr = '<h3>' . __( 'Hard cropped image', YOIMG_DOMAIN ) .'</h3> <p>' . __( 'this image format contains only a part of the original image, it has fixed width and height so that the image ratio is fixed too, e.g. thumbnail in listings are cropped', YOIMG_DOMAIN ) . '</p>'; ?>
		content: <?php echo json_encode( $contentStr ); ?>,
		position: {
			edge: 'left',
			align: 'center'
		}
	});
	jQuery('.yoimg-resize-help').pointer({
		<?php $contentStr = '<h3>' . __( 'Resized image', YOIMG_DOMAIN ) . '</h3> <p>' . __( 'this image format is not cropped but instead it is resized to fit the maximum available space, either in width or height, therefore this image ratio is not fixed', YOIMG_DOMAIN ) . '</p>'; ?>
		content: <?php echo json_encode( $contentStr ); ?>,
		position: {
			edge: 'left',
			align: 'center'
		}
	});
	jQuery('.yoimg-hard-crop-help, .yoimg-resize-help').click(function(){
		jQuery(this).pointer('open');
	});
});
</script>
<br />
<?php
} else {
?>
<p><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?> <?php echo wp_kses_post( __( 'doesn\'t define any additional image size using the standard Wordpress functions (<a href="http://codex.wordpress.org/Function_Reference/add_image_size" target="_blank">add_image_size</a> and <a href="http://codex.wordpress.org/Function_Reference/set_post_thumbnail_size" target="_blank">set_post_thumbnail_size</a>)', YOIMG_DOMAIN ) ); ?></p>
<br />
<?php
}
