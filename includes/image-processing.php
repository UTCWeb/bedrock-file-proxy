<?php
/*
 * Bedrock File Proxy: Image Processing
 */

/*
 * A very important mission we have is to shut up all errors on static-looking paths, otherwise errors
 * are going to screw up the header or download & serve process. So this plugin has to execute first.
 *
 * We're also going to *assume* that if a request for /wp-content/uploads/ causes PHP to load, it's
 * going to be a 404, and we should go and get it from the remote server.
 *
 * Developers need to know that this stuff is happening and should generally understand how this plugin
 * works before they employ it.
 *
 * The dynamic resizing portion was adapted from dynamic-image-resizer.
 * See: https://wordpress.org/plugins/dynamic-image-resizer/
 */
add_action( 'activated_plugin', 'bfp_first' );

if ( bfp_is_upload_request() ) {
	bfp_expect();
}

add_filter( 'wp_generate_attachment_metadata', 'bfp_generate_metadata' );
add_filter( 'intermediate_image_sizes_advanced', 'bfp_image_sizes_advanced' );

/**
 * Determine whether the current request is for an uploads asset path.
 *
 * Supports both standard WordPress (/wp-content/uploads/...) and
 * Bedrock-style content directories (/app/uploads/...).
 *
 * @return bool
 */
function bfp_is_upload_request(): bool {
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';

	return false !== stripos( $request_uri, '/wp-content/uploads/' )
		|| false !== stripos( $request_uri, '/app/uploads/' );
}

/**
 * Load BFP before anything else to silence other plugins' warnings.
 *
 * @see https://wordpress.org/support/topic/how-to-change-plugins-load-order
 */
function bfp_first(): void {
	$plugin_path    = 'bedrock-file-proxy/bedrock-file-proxy.php';
	$active_plugins = get_option( 'active_plugins' );
	$plugin_key     = array_search( $plugin_path, $active_plugins, true );

	if ( false !== $plugin_key ) {
		array_splice( $active_plugins, $plugin_key, 1 );
		array_unshift( $active_plugins, $plugin_path );
		update_option( 'active_plugins', $active_plugins );
	}
}

/**
 * This function, triggered above, sets the chain in motion.
 */
function bfp_expect(): void {
	ob_start();
	ini_set( 'display_errors', 'off' ); // phpcs:ignore
	add_action( 'init', 'bfp_dispatch' );
}

/**
 * This function ensures a path exists.
 *
 * @param string|null $path The path to check.
 * @return string The full URL.
 */
function bfp_get_url( ?string $path = null ): string {
	if ( ! $path ) {
		$path = bfp_get_relative_path();
	}

	return bfp_get_base_url() . $path;
}

/**
 * This function can fetch a remote image or resize a local one.
 *
 * If a cropped image is requested, and the original does not exist locally, it will take two runs of
 * this function to return the proper resized image, which is achieved by the header("Location: ...")
 * bits. The first run will fetch the remote image, the second will resize it.
 *
 * Ideally we could do this in one pass.
 */
function bfp_dispatch(): void {
	$mode         = bfp_get_mode();
	$relative_path = bfp_get_relative_path();

	if ( 'header' === $mode ) {
		header( 'Location: ' . bfp_get_base_url() . $relative_path );
		exit;
	}

	$doing_resize = false;
	$resize       = array();

	if ( preg_match( '/(.+)(-r)?-([0-9]+)x([0-9]+)(c)?\.(jpe?g|png|gif)/iU', $relative_path, $matches ) ) {
		$doing_resize       = true;
		$resize['filename'] = $matches[1] . '.' . $matches[6];
		$resize['width']    = $matches[3];
		$resize['height']   = $matches[4];
		$resize['crop']     = ! empty( $matches[5] );
		$resize['mode']     = substr( (string) $matches[2], 1 );

		$basefile = bfp_get_local_upload_path( $resize['filename'] );
		bfp_resize_image( $basefile, $resize );
		$relative_path = $resize['filename'];
	}

	$remote_url = bfp_get_url( $relative_path );

	$remote_http_request_args = apply_filters( 'bfp_http_remote_args', array( 'timeout' => 30 ) );
	$remote_http_request_args = apply_filters( 'sfp_http_remote_args', $remote_http_request_args );

	$remote_request = wp_remote_get( $remote_url, $remote_http_request_args );
	$response_code  = wp_remote_retrieve_response_code( $remote_request );

	if ( is_wp_error( $remote_request ) || $response_code >= 400 ) {
		bfp_error();
	}

	$local_file = bfp_get_local_upload_path( $relative_path );
	$local_dir  = dirname( $local_file );

	if ( ! wp_mkdir_p( $local_dir ) ) {
		bfp_error();
	}

	$bytes_written = file_put_contents( $local_file, wp_remote_retrieve_body( $remote_request ) );

	if ( false === $bytes_written ) {
		bfp_error();
	}

	if ( $doing_resize ) {
		bfp_dispatch();
	} else {
		bfp_serve_requested_file( $local_file );
	}
}

/**
 * Resizes $basefile based on parameters in $resize
 *
 * @param string $basefile The path to the file to resize.
 * @param array  $resize   The resize parameters.
 */
function bfp_resize_image( string $basefile, array $resize ): void {
	if ( ! file_exists( $basefile ) ) {
		return;
	}

	$suffix = $resize['width'] . 'x' . $resize['height'];
	if ( $resize['crop'] ) {
		$suffix .= 'c';
	}
	if ( 'r' === $resize['mode'] ) {
		$suffix = 'r-' . $suffix;
	}

	$img = wp_get_image_editor( $basefile );

	if ( is_wp_error( $img ) ) {
		bfp_error();
	}

	$img->resize( (int) $resize['width'], (int) $resize['height'], (bool) $resize['crop'] );
	$info             = pathinfo( $basefile );
	$path_to_new_file = $info['dirname'] . '/' . $info['filename'] . '-' . $suffix . '.' . $info['extension'];
	$save_result      = $img->save( $path_to_new_file );

	if ( is_wp_error( $save_result ) || empty( $save_result['path'] ) ) {
		bfp_error();
	}

	bfp_serve_requested_file( $path_to_new_file );
}

/**
 * Serve the file directly.
 *
 * @param string $filename The path to the file to serve.
 */
function bfp_serve_requested_file( string $filename ): void {
	if ( ! file_exists( $filename ) ) {
		bfp_error();
	}

	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$type  = $finfo ? finfo_file( $finfo, $filename ) : false;

	if ( $finfo ) {
		finfo_close( $finfo );
	}

	if ( ! $type ) {
		$type = 'application/octet-stream';
	}

	ob_end_clean();
	header( 'Content-Type: ' . $type );
	header( 'Content-Length: ' . filesize( $filename ) );
	readfile( $filename );
	exit;
}

/**
 * Prevent WordPress from generating resized images on upload.
 *
 * @param array $sizes Associative array of image sizes to be created.
 * @return array
 */
function bfp_image_sizes_advanced( array $sizes ): array {
	global $dynimg_image_sizes;

	$dynimg_image_sizes = $sizes;

	return array();
}

/**
 * Trick WP into thinking the images were generated anyways.
 *
 * @param array $meta An array of attachment meta data.
 * @return array
 */
function bfp_generate_metadata( $meta ) {
	global $dynimg_image_sizes;

	if ( ! is_array( $dynimg_image_sizes ) ) {
		return $meta;
	}

	foreach ( $dynimg_image_sizes as $sizename => $size ) {
		$newsize = image_resize_dimensions( $meta['width'], $meta['height'], $size['width'], $size['height'], $size['crop'] );

		if ( $newsize ) {
			$info = pathinfo( $meta['file'] );
			$ext  = $info['extension'];
			$name = wp_basename( $meta['file'], ".$ext" );

			$suffix = 'r-' . $newsize[4] . 'x' . $newsize[5];
			if ( $size['crop'] ) {
				$suffix .= 'c';
			}

			$resized = array(
				'file'   => $name . '-' . $suffix . '.' . $ext,
				'width'  => $newsize[4],
				'height' => $newsize[5],
			);

			$meta['sizes'][ $sizename ] = $resized;
		}
	}

	return $meta;
}

/**
 * Get the relative file path by stripping out the uploads base path.
 *
 * Supports both /wp-content/uploads/... and /app/uploads/...
 *
 * @return string The relative path.
 */
function bfp_get_relative_path(): string {
	static $path;

	if ( ! $path ) {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$path        = preg_replace( '/.*(?:\/wp-content\/uploads|\/app\/uploads)\//i', '', $request_uri );
	}

	$path = apply_filters( 'bfp_relative_path', $path );
	$path = apply_filters( 'sfp_relative_path', $path );

	return (string) $path;
}

/**
 * Grab a random file from a local directory and return the path.
 *
 * @return string The local path to the file.
 */
function bfp_get_random_local_file_path(): string {
	static $local_dir;

	$transient_key = 'bfp-replacement-images';

	if ( ! $local_dir ) {
		$local_dir = get_option( 'bfp_local_dir' );
		if ( ! $local_dir ) {
			$local_dir = get_option( 'sfp_local_dir' );
		}
		if ( ! $local_dir ) {
			$local_dir = 'sfp-images';
		}
	}

	$replacement_image_path = get_template_directory() . '/' . $local_dir . '/';

	if ( false === ( $images = get_transient( $transient_key ) ) ) {
		$images = array();

		foreach ( glob( $replacement_image_path . '*' ) as $filename ) {
			if ( ! preg_match( '/.+[0-9]+x[0-9]+c?\.(jpe?g|png|gif)$/iU', $filename ) ) {
				$images[] = basename( $filename );
			}
		}

		set_transient( $transient_key, $images );
	}

	if ( empty( $images ) ) {
		bfp_error();
	}

	$rand = wp_rand( 0, count( $images ) - 1 );

	return $replacement_image_path . $images[ $rand ];
}

/**
 * Retrieve the saved mode. See the README for the available modes.
 *
 * @return string The saved mode. Default is 'header'.
 */
function bfp_get_mode(): string {
	static $mode;

	if ( ! $mode ) {
		$mode = get_option( 'bfp_mode' );
		if ( ! $mode ) {
			$mode = get_option( 'sfp_mode' );
		}
		if ( ! $mode ) {
			$mode = 'header';
		}
	}

	return (string) $mode;
}

/**
 * Get the base URL of the uploads/ directory.
 *
 * @return string
 */
function bfp_get_base_url(): string {
	static $url;

	$mode = bfp_get_mode();

	if ( ! $url ) {
		$url = get_option( 'bfp_url' );
		if ( ! $url ) {
			$url = get_option( 'sfp_url' );
		}
		if ( ! $url && 'local' !== $mode ) {
			bfp_error();
		}
	}

	return trailingslashit( (string) $url );
}

/**
 * Die with an error.
 */
function bfp_error(): void {
	wp_die( esc_html__( 'BFP tried to load but encountered an error', 'bedrock-file-proxy' ) );
}

/**
 * Get the local uploads root directory.
 *
 * @return string
 */
function bfp_get_local_uploads_root(): string {
	return trailingslashit( WP_CONTENT_DIR ) . 'uploads';
}

/**
 * Convert an uploads-relative path into an absolute local file path.
 *
 * @param string $relative_path The path relative to uploads.
 * @return string
 */
function bfp_get_local_upload_path( string $relative_path ): string {
	return trailingslashit( bfp_get_local_uploads_root() ) . ltrim( $relative_path, '/' );
}
