<?php
/**
 * Plugin Name: Elementor to Bricks Media Migrator
 * Plugin URI: https://abidkp.com/elementor-to-bricks-media-migrator/
 * Description: Migrate image metadata (Title, Alt, Caption, Description) from Elementor pages to Bricks during website redesigns.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Abid KP
 * Author URI: https://abidkp.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-to-bricks-media-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EBMM_VERSION', '1.0.0' );
define( 'EBMM_FILE', __FILE__ );
define( 'EBMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'EBMM_URL', plugin_dir_url( __FILE__ ) );
define( 'EBMM_META_KEY', '_ebmm_imported_export_v1' );

// Backward-compatibility aliases
if ( ! defined( 'GMM_VERSION' ) ) {
	define( 'GMM_VERSION', EBMM_VERSION );
	define( 'GMM_FILE', EBMM_FILE );
	define( 'GMM_DIR', EBMM_DIR );
	define( 'GMM_URL', EBMM_URL );
	define( 'GMM_META_KEY', EBMM_META_KEY );
}

final class Elementor_To_Bricks_Media_Migrator {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_ebmm_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_ebmm_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_ebmm_clear_import', array( __CLASS__, 'handle_clear_import' ) );

		// Backward-compatibility action handlers
		add_action( 'admin_post_gmm_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_gmm_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_gmm_clear_import', array( __CLASS__, 'handle_clear_import' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_ebmm_get_attachment', array( __CLASS__, 'ajax_get_attachment' ) );
		add_action( 'wp_ajax_ebmm_copy_metadata', array( __CLASS__, 'ajax_copy_metadata' ) );
		add_action( 'wp_ajax_ebmm_save_metadata', array( __CLASS__, 'ajax_save_metadata' ) );

		// Legacy AJAX aliases
		add_action( 'wp_ajax_gmm_get_attachment', array( __CLASS__, 'ajax_get_attachment' ) );
		add_action( 'wp_ajax_gmm_copy_metadata', array( __CLASS__, 'ajax_copy_metadata' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( EBMM_FILE ), array( __CLASS__, 'plugin_action_links' ) );
	}

	public static function admin_menu() {
		add_management_page(
			__( 'Media Migrator', 'elementor-to-bricks-media-migrator' ),
			__( 'Media Migrator', 'elementor-to-bricks-media-migrator' ),
			'manage_options',
			'elementor-to-bricks-media-migrator',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function plugin_action_links( $links ) {
		$url = admin_url( 'tools.php?page=elementor-to-bricks-media-migrator' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'elementor-to-bricks-media-migrator' ) . '</a>' );
		return $links;
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'tools_page_elementor-to-bricks-media-migrator' !== $hook && 'tools_page_greenday-media-migrator' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'ebmm-admin-css',
			EBMM_URL . 'assets/css/admin.css',
			array(),
			EBMM_VERSION
		);

		wp_enqueue_script(
			'ebmm-admin-js',
			EBMM_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			EBMM_VERSION,
			true
		);

		wp_localize_script(
			'ebmm-admin-js',
			'ebmmAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ebmm_ajax' ),
				'i18n'    => array(
					'loadingMetadata'      => __( 'Loading target metadata...', 'elementor-to-bricks-media-migrator' ),
					'couldNotLoadMetadata' => __( 'Could not load target metadata.', 'elementor-to-bricks-media-migrator' ),
					'targetSelected'       => __( 'Target image selected. Ready to copy or edit.', 'elementor-to-bricks-media-migrator' ),
					'confirmCopy'          => __( 'Copy source Title, Alt, Caption and Description to this target image? You can edit them before or after saving.', 'elementor-to-bricks-media-migrator' ),
					'copying'              => __( 'Copying...', 'elementor-to-bricks-media-migrator' ),
					'copyButton'           => __( 'Copy Source → Target', 'elementor-to-bricks-media-migrator' ),
					'copyFailed'           => __( 'Copy failed.', 'elementor-to-bricks-media-migrator' ),
					'copied'               => __( 'Copied ✓', 'elementor-to-bricks-media-migrator' ),
					'saving'               => __( 'Saving...', 'elementor-to-bricks-media-migrator' ),
					'saveButton'           => __( 'Save Target Changes', 'elementor-to-bricks-media-migrator' ),
					'saveFailed'           => __( 'Save failed.', 'elementor-to-bricks-media-migrator' ),
					'saved'                => __( 'Saved ✓', 'elementor-to-bricks-media-migrator' ),
					'selectPage'           => __( '— Select a page —', 'elementor-to-bricks-media-migrator' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * EXPORT
	 * ------------------------------------------------------------------ */

	public static function handle_export() {
		self::require_admin();
		check_admin_referer( 'ebmm_export' );

		// Get user-selected post types from export form
		$selected_post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', $_POST['post_types'] )
			: array( 'page', 'post' );

		$available_post_types = self::get_available_post_types();
		$allowed_post_types   = array_intersect( $selected_post_types, array_keys( $available_post_types ) );

		if ( empty( $allowed_post_types ) ) {
			$allowed_post_types = array( 'page', 'post' );
		}

		$allowed_post_types = apply_filters( 'ebmm_export_post_types', $allowed_post_types );

		$posts = get_posts(
			array(
				'post_type'      => $allowed_post_types,
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$export = array(
			'generator'      => 'elementor-to-bricks-media-migrator',
			'export_type'    => 'ebmm_media_metadata_export',
			'export_version' => '1.1',
			'plugin_version' => EBMM_VERSION,
			'exported_at'    => current_time( 'mysql' ),
			'site_url'       => home_url( '/' ),
			'home_url'       => home_url( '/' ),
			'post_types'     => $allowed_post_types,
			'pages'          => array(),
		);

		foreach ( $posts as $post ) {
			$images = self::get_source_images( $post->ID );

			$export['pages'][] = array(
				'post_id'     => (int) $post->ID,
				'post_type'   => $post->post_type,
				'post_status' => $post->post_status,
				'title'       => get_the_title( $post->ID ),
				'slug'        => $post->post_name,
				'url'         => get_permalink( $post->ID ),
				'image_count' => count( $images ),
				'images'      => $images,
			);
		}

		$filename = 'elementor-media-export-' . gmdate( 'Y-m-d-His' ) . '.json';
		$json     = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			wp_die( esc_html__( 'Could not create the export JSON.', 'elementor-to-bricks-media-migrator' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function get_source_images( $post_id ) {
		$image_ids = array();

		$featured_id = get_post_thumbnail_id( $post_id );
		if ( $featured_id && self::is_image_attachment( $featured_id ) ) {
			$image_ids[ $featured_id ] = $featured_id;
		}

		/* Elementor document data */
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( $elementor_data ) {
			$decoded = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
			if ( is_array( $decoded ) ) {
				self::scan_nested_media( $decoded, $image_ids );
			}
		}

		/* Normal WordPress content */
		$content = get_post_field( 'post_content', $post_id );
		if ( $content ) {
			self::scan_content_media( $content, $image_ids );
		}

		$images = array();
		foreach ( $image_ids as $attachment_id ) {
			$image = self::attachment_export_data( $attachment_id );
			if ( $image ) {
				$images[] = $image;
			}
		}

		return $images;
	}

	/**
	 * Enhanced Elementor scanner: catches single images, galleries, carousels, and repeaters.
	 */
	private static function scan_nested_media( $data, &$image_ids ) {
		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			// Direct numeric attachment ID (e.g. array of IDs [ 101, 102 ])
			if ( is_numeric( $value ) ) {
				$candidate_id = absint( $value );
				$key_lc       = is_string( $key ) ? strtolower( $key ) : '';

				if ( '' === $key_lc ) {
					// Numeric array index in a list
					if ( self::is_image_attachment( $candidate_id ) ) {
						$image_ids[ $candidate_id ] = $candidate_id;
					}
				} else {
					$likely_media_key = ( 'id' === $key_lc || false !== strpos( $key_lc, 'image' ) || false !== strpos( $key_lc, 'media' ) || false !== strpos( $key_lc, 'thumb' ) );
					if ( $likely_media_key && self::is_image_attachment( $candidate_id ) ) {
						$image_ids[ $candidate_id ] = $candidate_id;
					}
				}
			}

			// Object/array representation (e.g. { id: 101, url: '...' })
			if ( is_array( $value ) ) {
				if ( isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
					$candidate_id = absint( $value['id'] );
					if ( self::is_image_attachment( $candidate_id ) ) {
						$image_ids[ $candidate_id ] = $candidate_id;
					}
				}
				self::scan_nested_media( $value, $image_ids );
			}
		}
	}

	/**
	 * Scans content with in-memory caching to avoid repetitive database lookups.
	 */
	private static function scan_content_media( $content, &$image_ids ) {
		if ( preg_match_all( '/wp-image-(\d+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$id = absint( $id );
				if ( self::is_image_attachment( $id ) ) {
					$image_ids[ $id ] = $id;
				}
			}
		}

		if ( preg_match_all( '/<img[^>]+(?:src|data-src|data-lazy-src|data-original)=(["\'])(.*?)\1/i', $content, $matches ) ) {
			foreach ( $matches[2] as $url ) {
				$id = self::url_to_attachment_id( $url );
				if ( $id && self::is_image_attachment( $id ) ) {
					$image_ids[ $id ] = $id;
				}
			}
		}
	}

	/**
	 * In-memory URL to Attachment ID cache.
	 */
	private static function url_to_attachment_id( $url ) {
		static $cache = array();
		$decoded_url  = html_entity_decode( $url );

		if ( isset( $cache[ $decoded_url ] ) ) {
			return $cache[ $decoded_url ];
		}

		$id = attachment_url_to_postid( $decoded_url );
		$cache[ $decoded_url ] = $id ? (int) $id : 0;
		return $cache[ $decoded_url ];
	}

	private static function attachment_export_data( $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type || ! self::is_image_attachment( $attachment_id ) ) {
			return null;
		}

		$file = get_attached_file( $attachment_id );

		return array(
			'attachment_id' => (int) $attachment_id,
			'filename'      => $file ? basename( $file ) : '',
			'file'          => $file ? wp_basename( $file ) : '',
			'url'           => wp_get_attachment_url( $attachment_id ),
			'thumbnail'     => wp_get_attachment_image_url( $attachment_id, 'medium' ),
			'title'         => $post->post_title,
			'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'       => $post->post_excerpt,
			'description'   => $post->post_content,
		);
	}

	/* ---------------------------------------------------------------------
	 * IMPORT & STORAGE
	 * ------------------------------------------------------------------ */

	private static function get_import_dir() {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'ebmm-imports';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );

			$htaccess_file = $dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "Deny from all\n" );
			}

			$index_file = $dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
			}
		}

		return $dir;
	}

	private static function delete_user_import_file( $user_id ) {
		$meta = get_user_meta( $user_id, EBMM_META_KEY, true );
		if ( is_array( $meta ) && ! empty( $meta['file'] ) && file_exists( $meta['file'] ) ) {
			wp_delete_file( $meta['file'] );
		}
		// Also check legacy key
		$legacy_meta = get_user_meta( $user_id, '_gmm_imported_export_v1', true );
		if ( is_array( $legacy_meta ) && ! empty( $legacy_meta['file'] ) && file_exists( $legacy_meta['file'] ) ) {
			wp_delete_file( $legacy_meta['file'] );
		}
	}

	private static function get_user_import_data( $user_id ) {
		$data = get_user_meta( $user_id, EBMM_META_KEY, true );
		if ( empty( $data ) ) {
			$data = get_user_meta( $user_id, '_gmm_imported_export_v1', true );
		}
		return is_array( $data ) ? $data : false;
	}

	private static function get_imported_page( $data, $selected_page_id ) {
		if ( ! is_array( $data ) || empty( $data['pages'] ) || ! $selected_page_id ) {
			return false;
		}

		$found_page = false;
		foreach ( $data['pages'] as $page ) {
			if ( isset( $page['post_id'] ) && (int) $page['post_id'] === $selected_page_id ) {
				$found_page = $page;
				break;
			}
		}

		if ( ! $found_page ) {
			return false;
		}

		if ( ! empty( $found_page['images'] ) && is_array( $found_page['images'] ) ) {
			return $found_page;
		}

		if ( ! empty( $data['file'] ) && file_exists( $data['file'] ) ) {
			$json      = file_get_contents( $data['file'] );
			$full_data = json_decode( $json, true );
			if ( is_array( $full_data ) && ! empty( $full_data['pages'] ) ) {
				foreach ( $full_data['pages'] as $page ) {
					if ( isset( $page['post_id'] ) && (int) $page['post_id'] === $selected_page_id ) {
						return $page;
					}
				}
			}
		}

		return $found_page;
	}

	/**
	 * Validates the uploaded JSON payload for authentic signature, version, and page schema.
	 */
	private static function validate_import_payload( $data ) {
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_json',
				__( 'The uploaded file does not contain valid JSON data or is corrupted.', 'elementor-to-bricks-media-migrator' )
			);
		}

		// Signature verification:
		// 1. Current format: generator === 'elementor-to-bricks-media-migrator' or export_type === 'ebmm_media_metadata_export'
		// 2. Legacy format: export_version in ['1.0', '1.1'] with site_url/home_url and pages array
		$has_signature = (
			( isset( $data['generator'] ) && 'elementor-to-bricks-media-migrator' === $data['generator'] ) ||
			( isset( $data['export_type'] ) && 'ebmm_media_metadata_export' === $data['export_type'] ) ||
			( isset( $data['export_version'] ) && in_array( (string) $data['export_version'], array( '1.0', '1.1' ), true ) && ( isset( $data['site_url'] ) || isset( $data['home_url'] ) ) )
		);

		if ( ! $has_signature ) {
			return new WP_Error(
				'invalid_signature',
				__( 'Invalid file: This JSON file was not generated by Elementor to Bricks Media Migrator.', 'elementor-to-bricks-media-migrator' )
			);
		}

		// Pages array check
		if ( empty( $data['pages'] ) || ! is_array( $data['pages'] ) ) {
			return new WP_Error(
				'empty_pages',
				__( 'Invalid export: No pages found in the uploaded file.', 'elementor-to-bricks-media-migrator' )
			);
		}

		// Deep schema verification on the first page entry to ensure it's not another plugin's JSON
		$sample_page = reset( $data['pages'] );
		if ( ! is_array( $sample_page ) || ! isset( $sample_page['post_id'] ) || ! array_key_exists( 'images', $sample_page ) ) {
			return new WP_Error(
				'invalid_schema',
				__( 'Invalid structure: The file contains pages, but the data does not match the required media migrator format.', 'elementor-to-bricks-media-migrator' )
			);
		}

		return true;
	}

	public static function handle_import() {
		self::require_admin();
		check_admin_referer( 'ebmm_import' );

		if ( empty( $_FILES['ebmm_json']['tmp_name'] ) && empty( $_FILES['gmm_json']['tmp_name'] ) ) {
			self::redirect_with_message( 'error', __( 'Please select a JSON file.', 'elementor-to-bricks-media-migrator' ) );
		}

		$file = ! empty( $_FILES['ebmm_json']['tmp_name'] ) ? $_FILES['ebmm_json'] : $_FILES['gmm_json'];
		if ( ! empty( $file['error'] ) ) {
			self::redirect_with_message( 'error', __( 'The JSON upload failed.', 'elementor-to-bricks-media-migrator' ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::redirect_with_message( 'error', __( 'The uploaded file is not a valid upload.', 'elementor-to-bricks-media-migrator' ) );
		}

		$file_name = sanitize_file_name( $file['name'] );
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( 'json' !== $file_ext ) {
			self::redirect_with_message( 'error', __( 'Invalid file extension. Please upload a .json file.', 'elementor-to-bricks-media-migrator' ) );
		}

		if ( isset( $file['size'] ) && (int) $file['size'] > 50 * MB_IN_BYTES ) {
			self::redirect_with_message( 'error', __( 'The JSON file exceeds the 50 MB limit.', 'elementor-to-bricks-media-migrator' ) );
		}

		$json = file_get_contents( $file['tmp_name'] );
		$data = json_decode( $json, true );

		$validation = self::validate_import_payload( $data );
		if ( is_wp_error( $validation ) ) {
			self::redirect_with_message( 'error', $validation->get_error_message() );
		}

		$user_id = get_current_user_id();
		self::delete_user_import_file( $user_id );

		$import_dir    = self::get_import_dir();
		$token         = wp_generate_password( 24, false );
		$file_basename = sprintf( 'import-%d-%s.json', $user_id, $token );
		$file_path     = $import_dir . '/' . $file_basename;
		file_put_contents( $file_path, $json );

		$pages_summary = array();
		$image_lookup  = array();
		$post_types    = array();

		foreach ( $data['pages'] as $page ) {
			$pt = isset( $page['post_type'] ) ? (string) $page['post_type'] : 'page';
			if ( ! in_array( $pt, $post_types, true ) ) {
				$post_types[] = $pt;
			}

			$pages_summary[] = array(
				'post_id'     => isset( $page['post_id'] ) ? (int) $page['post_id'] : 0,
				'post_type'   => $pt,
				'title'       => isset( $page['title'] ) ? (string) $page['title'] : '',
				'slug'        => isset( $page['slug'] ) ? (string) $page['slug'] : '',
				'url'         => isset( $page['url'] ) ? (string) $page['url'] : '',
				'image_count' => isset( $page['image_count'] ) ? (int) $page['image_count'] : 0,
			);

			if ( ! empty( $page['images'] ) && is_array( $page['images'] ) ) {
				foreach ( $page['images'] as $img ) {
					if ( ! empty( $img['attachment_id'] ) ) {
						$img_id = (int) $img['attachment_id'];
						if ( ! isset( $image_lookup[ $img_id ] ) ) {
							$image_lookup[ $img_id ] = array(
								'title'       => isset( $img['title'] ) ? (string) $img['title'] : '',
								'alt'         => isset( $img['alt'] ) ? (string) $img['alt'] : '',
								'caption'     => isset( $img['caption'] ) ? (string) $img['caption'] : '',
								'description' => isset( $img['description'] ) ? (string) $img['description'] : '',
							);
						}
					}
				}
			}
		}

		$meta_data = array(
			'version'      => '2.0',
			'file'         => $file_path,
			'site_url'     => ! empty( $data['site_url'] ) ? esc_url_raw( $data['site_url'] ) : '',
			'imported_at'  => current_time( 'mysql' ),
			'imported_by'  => $user_id,
			'post_types'   => $post_types,
			'pages'        => $pages_summary,
			'image_lookup' => $image_lookup,
		);

		update_user_meta( $user_id, EBMM_META_KEY, $meta_data );
		self::redirect_with_message( 'success', __( 'Production media export imported successfully.', 'elementor-to-bricks-media-migrator' ) );
	}

	public static function handle_clear_import() {
		self::require_admin();
		check_admin_referer( 'ebmm_clear_import' );
		$user_id = get_current_user_id();
		self::delete_user_import_file( $user_id );
		delete_user_meta( $user_id, EBMM_META_KEY );
		delete_user_meta( $user_id, '_gmm_imported_export_v1' );
		self::redirect_with_message( 'success', __( 'Imported production data cleared.', 'elementor-to-bricks-media-migrator' ) );
	}

	private static function redirect_with_message( $type, $message ) {
		$url = add_query_arg(
			array(
				'page'     => 'elementor-to-bricks-media-migrator',
				'ebmm_msg' => $type,
				'ebmm_txt' => $message,
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * PAGE MATCHING & STAGING SCAN (BRICKS SUPPORT)
	 * ------------------------------------------------------------------ */

	/**
	 * Robust multi-strategy page resolution supporting pages, posts, and CPTs.
	 */
	private static function find_matching_page( $source_page ) {
		$post_type = ! empty( $source_page['post_type'] ) ? sanitize_key( $source_page['post_type'] ) : 'page';

		// Strategy 1: Match by exact path using get_page_by_path
		if ( ! empty( $source_page['url'] ) ) {
			$path = wp_parse_url( $source_page['url'], PHP_URL_PATH );
			$path = trim( (string) $path, '/' );
			if ( $path ) {
				$page = get_page_by_path( $path, OBJECT, $post_type );
				if ( $page ) {
					return $page;
				}

				// Strategy 2: Match using url_to_postid for custom permalinks / posts / CPTs
				$resolved_id = url_to_postid( home_url( '/' . $path ) );
				if ( $resolved_id ) {
					$post = get_post( $resolved_id );
					if ( $post && $post->post_type === $post_type ) {
						return $post;
					}
				}
			}
		}

		// Strategy 3: Match by post_name / slug
		$slug = ! empty( $source_page['slug'] ) ? sanitize_title( $source_page['slug'] ) : '';
		if ( $slug ) {
			$page = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $page ) {
				return $page;
			}

			// Fallback query by slug for non-hierarchical post types
			$matched = get_posts(
				array(
					'name'        => $slug,
					'post_type'   => $post_type,
					'post_status' => array( 'publish', 'private', 'draft' ),
					'numberposts' => 1,
				)
			);
			if ( ! empty( $matched ) ) {
				return $matched[0];
			}
		}

		return false;
	}

	private static function get_staging_images( $post_id ) {
		$image_ids = array();

		$featured_id = get_post_thumbnail_id( $post_id );
		if ( $featured_id && self::is_image_attachment( $featured_id ) ) {
			$image_ids[ $featured_id ] = $featured_id;
		}

		/* Bricks content */
		foreach ( array( '_bricks_page_content_2', '_bricks_page_header_2', '_bricks_page_footer_2' ) as $meta_key ) {
			$data = get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $data ) ) {
				self::scan_bricks_data( $data, $image_ids );
			}
		}

		/* Elementor content fallback if staging also has Elementor */
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( $elementor_data ) {
			$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
			if ( is_array( $data ) ) {
				self::scan_nested_media( $data, $image_ids );
			}
		}

		/* Standard post content */
		$content = get_post_field( 'post_content', $post_id );
		if ( $content ) {
			self::scan_content_media( $content, $image_ids );
		}

		$images = array();
		foreach ( $image_ids as $attachment_id ) {
			$image = self::attachment_export_data( $attachment_id );
			if ( $image ) {
				$images[] = $image;
			}
		}

		return $images;
	}

	/**
	 * Enhanced Bricks scanner: handles image elements, background images, icons, sliders, and nested templates.
	 */
	private static function scan_bricks_data( $data, &$image_ids ) {
		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			// Direct numeric ID (e.g. slider gallery array [ 10, 20 ])
			if ( is_numeric( $value ) ) {
				$candidate_id = absint( $value );
				$key_lc       = is_string( $key ) ? strtolower( $key ) : '';

				if ( '' === $key_lc ) {
					if ( self::is_image_attachment( $candidate_id ) ) {
						$image_ids[ $candidate_id ] = $candidate_id;
					}
				} else {
					$likely_media_key = ( 'id' === $key_lc || false !== strpos( $key_lc, 'image' ) || false !== strpos( $key_lc, 'background' ) || false !== strpos( $key_lc, 'icon' ) );
					if ( $likely_media_key && self::is_image_attachment( $candidate_id ) ) {
						$image_ids[ $candidate_id ] = $candidate_id;
					}
				}
			}

			// Objects with ID or nested template ID
			if ( is_array( $value ) ) {
				if ( isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
					$candidate_id = absint( $value['id'] );
					if ( self::is_image_attachment( $candidate_id ) ) {
						$image_ids[ $candidate_id ] = $candidate_id;
					}
				}

				// Check for Bricks template elements referencing external section templates
				if ( isset( $value['name'] ) && 'template' === $value['name'] && ! empty( $value['settings']['template'] ) ) {
					$template_id = absint( $value['settings']['template'] );
					if ( $template_id ) {
						$tmpl_data = get_post_meta( $template_id, '_bricks_page_content_2', true );
						if ( is_array( $tmpl_data ) ) {
							self::scan_bricks_data( $tmpl_data, $image_ids );
						}
					}
				}

				self::scan_bricks_data( $value, $image_ids );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * AJAX ENDPOINTS
	 * ------------------------------------------------------------------ */

	public static function ajax_get_attachment() {
		self::ajax_guard();

		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! self::is_image_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image.', 'elementor-to-bricks-media-migrator' ) ) );
		}

		$post = get_post( $id );
		wp_send_json_success(
			array(
				'id'          => $id,
				'url'         => wp_get_attachment_image_url( $id, 'medium' ),
				'title'       => $post->post_title,
				'alt'         => get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'caption'     => $post->post_excerpt,
				'description' => $post->post_content,
			)
		);
	}

	public static function ajax_copy_metadata() {
		self::ajax_guard();

		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;

		if ( ! $source_id || ! $target_id || ! self::is_image_attachment( $target_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid source or staging image.', 'elementor-to-bricks-media-migrator' ) ) );
		}

		$data = self::get_user_import_data( get_current_user_id() );
		if ( ! is_array( $data ) || empty( $data['pages'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No imported production export is loaded.', 'elementor-to-bricks-media-migrator' ) ) );
		}

		$source = null;
		// Fast O(1) lookup
		if ( ! empty( $data['image_lookup'][ $source_id ] ) ) {
			$source = $data['image_lookup'][ $source_id ];
		} elseif ( ! empty( $data['pages'] ) && is_array( $data['pages'] ) ) {
			foreach ( $data['pages'] as $page ) {
				if ( empty( $page['images'] ) || ! is_array( $page['images'] ) ) {
					continue;
				}
				foreach ( $page['images'] as $image ) {
					if ( isset( $image['attachment_id'] ) && (int) $image['attachment_id'] === $source_id ) {
						$source = $image;
						break 2;
					}
				}
			}

			// Fallback check in stored file
			if ( ! $source && ! empty( $data['file'] ) && file_exists( $data['file'] ) ) {
				$json      = file_get_contents( $data['file'] );
				$full_data = json_decode( $json, true );
				if ( is_array( $full_data ) && ! empty( $full_data['pages'] ) ) {
					foreach ( $full_data['pages'] as $page ) {
						if ( empty( $page['images'] ) || ! is_array( $page['images'] ) ) {
							continue;
						}
						foreach ( $page['images'] as $image ) {
							if ( isset( $image['attachment_id'] ) && (int) $image['attachment_id'] === $source_id ) {
								$source = $image;
								break 2;
							}
						}
					}
				}
			}
		}

		if ( ! $source ) {
			wp_send_json_error( array( 'message' => __( 'Production image was not found in the imported export.', 'elementor-to-bricks-media-migrator' ) ) );
		}

		$title       = isset( $source['title'] ) ? (string) $source['title'] : '';
		$alt         = isset( $source['alt'] ) ? (string) $source['alt'] : '';
		$caption     = isset( $source['caption'] ) ? (string) $source['caption'] : '';
		$description = isset( $source['description'] ) ? (string) $source['description'] : '';

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => $target_id,
					'post_title'   => $title,
					'post_excerpt' => $caption,
					'post_content' => $description,
				)
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_post_meta( $target_id, '_wp_attachment_image_alt', wp_slash( $alt ) );

		wp_send_json_success(
			array(
				'message'     => __( 'Metadata copied successfully. You can edit the values below if needed.', 'elementor-to-bricks-media-migrator' ),
				'title'       => $title,
				'alt'         => $alt,
				'caption'     => $caption,
				'description' => $description,
			)
		);
	}

	/**
	 * Allows saving manually edited metadata on the staging image.
	 */
	public static function ajax_save_metadata() {
		self::ajax_guard();

		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		if ( ! $target_id || ! self::is_image_attachment( $target_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target image.', 'elementor-to-bricks-media-migrator' ) ) );
		}

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$alt         = isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : '';
		$caption     = isset( $_POST['caption'] ) ? wp_kses_post( wp_unslash( $_POST['caption'] ) ) : '';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => $target_id,
					'post_title'   => $title,
					'post_excerpt' => $caption,
					'post_content' => $description,
				)
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_post_meta( $target_id, '_wp_attachment_image_alt', wp_slash( $alt ) );

		wp_send_json_success(
			array(
				'message'     => __( 'Target image metadata saved successfully.', 'elementor-to-bricks-media-migrator' ),
				'title'       => $title,
				'alt'         => $alt,
				'caption'     => $caption,
				'description' => $description,
			)
		);
	}

	private static function ajax_guard() {
		if ( ! check_ajax_referer( 'ebmm_ajax', 'nonce', false ) && ! check_ajax_referer( 'gmm_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'elementor-to-bricks-media-migrator' ) ) );
		}
		self::require_admin();
	}

	/* ---------------------------------------------------------------------
	 * ADMIN UI
	 * ------------------------------------------------------------------ */

	public static function render_admin_page() {
		self::require_admin();

		$data             = self::get_user_import_data( get_current_user_id() );
		$selected_page_id = isset( $_GET['source_page_id'] ) ? absint( $_GET['source_page_id'] ) : 0;
		$filter_pt        = isset( $_GET['filter_post_type'] ) ? sanitize_key( $_GET['filter_post_type'] ) : '';
		$selected_page    = false;
		$staging_page     = false;
		$staging_images   = array();

		if ( $selected_page_id && is_array( $data ) ) {
			$selected_page = self::get_imported_page( $data, $selected_page_id );
		}

		if ( $selected_page ) {
			$staging_page = self::find_matching_page( $selected_page );
			if ( $staging_page ) {
				$staging_images = self::get_staging_images( $staging_page->ID );
			}
		}

		$total_source_images = ( $selected_page && ! empty( $selected_page['images'] ) ) ? count( $selected_page['images'] ) : 0;
		$synced_images_count = 0;

		if ( $selected_page && ! empty( $selected_page['images'] ) && ! empty( $staging_images ) ) {
			foreach ( $selected_page['images'] as $src_img ) {
				foreach ( $staging_images as $stg_img ) {
					if (
						trim( (string) $stg_img['title'] ) === trim( (string) $src_img['title'] ) &&
						trim( (string) $stg_img['alt'] ) === trim( (string) $src_img['alt'] ) &&
						trim( (string) $stg_img['caption'] ) === trim( (string) $src_img['caption'] ) &&
						trim( (string) $stg_img['description'] ) === trim( (string) $src_img['description'] ) &&
						( ! empty( $src_img['title'] ) || ! empty( $src_img['alt'] ) || ! empty( $src_img['caption'] ) || ! empty( $src_img['description'] ) )
					) {
						$synced_images_count++;
						break;
					}
				}
			}
		}

		$is_import_active     = ! empty( $data ) || ! empty( $selected_page_id );
		$msg_type             = isset( $_GET['ebmm_msg'] ) ? sanitize_key( $_GET['ebmm_msg'] ) : ( isset( $_GET['gmm_msg'] ) ? sanitize_key( $_GET['gmm_msg'] ) : '' );
		$msg_text             = isset( $_GET['ebmm_txt'] ) ? sanitize_text_field( wp_unslash( $_GET['ebmm_txt'] ) ) : ( isset( $_GET['gmm_txt'] ) ? sanitize_text_field( wp_unslash( $_GET['gmm_txt'] ) ) : '' );
		$available_post_types = self::get_available_post_types();
		?>
		<div class="wrap ebmm-wrap gmm-wrap">
			<div class="ebmm-header">
				<h1><?php esc_html_e( 'Elementor to Bricks Media Migrator', 'elementor-to-bricks-media-migrator' ); ?></h1>
				<p class="description">
					<?php esc_html_e( 'Export page-wise image metadata from Elementor and map it to images used on matching Bricks target pages.', 'elementor-to-bricks-media-migrator' ); ?>
					<span> &bull; <?php esc_html_e( 'Developed by Abid KP', 'elementor-to-bricks-media-migrator' ); ?> (<a href="https://abidkp.com" target="_blank" rel="noopener">abidkp.com</a>)</span>
				</p>
			</div>

			<?php if ( $msg_text ) : ?>
				<div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $msg_text ); ?></p></div>
			<?php endif; ?>

			<nav class="gmm-tabs" aria-label="<?php esc_attr_e( 'Media Migrator Tabs', 'elementor-to-bricks-media-migrator' ); ?>">
				<a href="#gmm-tab-export" class="gmm-tab <?php echo ! $is_import_active ? 'active' : ''; ?>">
					<?php esc_html_e( '1. Export (Source / Elementor)', 'elementor-to-bricks-media-migrator' ); ?>
				</a>
				<a href="#gmm-tab-import" class="gmm-tab <?php echo $is_import_active ? 'active' : ''; ?>">
					<?php esc_html_e( '2. Import & Map (Target / Bricks)', 'elementor-to-bricks-media-migrator' ); ?>
				</a>
			</nav>

			<div class="gmm-tab-content">
				<!-- TAB 1: EXPORT -->
				<div id="gmm-tab-export" class="gmm-tab-panel <?php echo ! $is_import_active ? 'active' : ''; ?>">
					<section class="gmm-panel">
						<h2><?php esc_html_e( 'Export from Source Website', 'elementor-to-bricks-media-migrator' ); ?></h2>
						<p><?php esc_html_e( 'Run this on your old/source Elementor website. It scans page content, Elementor widgets, galleries, featured images and inline attachments, then downloads a structured JSON export.', 'elementor-to-bricks-media-migrator' ); ?></p>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ebmm_export">
							<?php wp_nonce_field( 'ebmm_export' ); ?>

							<div class="ebmm-post-types-box">
								<strong><?php esc_html_e( 'Choose Post Types to Scan & Export:', 'elementor-to-bricks-media-migrator' ); ?></strong>
								<div class="ebmm-pt-checkboxes">
									<?php foreach ( $available_post_types as $pt_key => $pt_label ) : ?>
										<label class="ebmm-checkbox-label">
											<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt_key ); ?>" <?php checked( in_array( $pt_key, array( 'page', 'post' ), true ) ); ?>>
											<span><?php echo esc_html( $pt_label ); ?> (<code><?php echo esc_html( $pt_key ); ?></code>)</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<?php submit_button( __( 'Download Production Media JSON', 'elementor-to-bricks-media-migrator' ), 'primary', 'submit', false ); ?>
						</form>
					</section>
				</div>

				<!-- TAB 2: IMPORT & MAP -->
				<div id="gmm-tab-import" class="gmm-tab-panel <?php echo $is_import_active ? 'active' : ''; ?>">
					<section class="gmm-panel">
						<h2><?php esc_html_e( 'Import and Map on Target (Bricks)', 'elementor-to-bricks-media-migrator' ); ?></h2>
						<p><?php esc_html_e( 'Upload the JSON exported from your Elementor site. The imported data is securely isolated for your administrator account.', 'elementor-to-bricks-media-migrator' ); ?></p>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="gmm-upload-form">
							<input type="hidden" name="action" value="ebmm_import">
							<?php wp_nonce_field( 'ebmm_import' ); ?>
							<input type="file" name="ebmm_json" accept=".json,application/json" required>
							<?php submit_button( __( 'Upload & Load JSON', 'elementor-to-bricks-media-migrator' ), 'secondary', 'submit', false ); ?>
						</form>

						<?php if ( is_array( $data ) ) : ?>
							<div class="gmm-loaded">
								<strong><?php esc_html_e( 'Production export loaded.', 'elementor-to-bricks-media-migrator' ); ?></strong>
								<span><?php echo esc_html( isset( $data['site_url'] ) ? $data['site_url'] : '' ); ?></span>
								<span><?php echo esc_html( count( $data['pages'] ) ); ?> <?php esc_html_e( 'pages found', 'elementor-to-bricks-media-migrator' ); ?></span>
							</div>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gmm-clear-form">
								<input type="hidden" name="action" value="ebmm_clear_import">
								<?php wp_nonce_field( 'ebmm_clear_import' ); ?>
								<?php submit_button( __( 'Clear Loaded Export', 'elementor-to-bricks-media-migrator' ), 'secondary', 'submit', false ); ?>
							</form>
						<?php endif; ?>
					</section>

					<?php if ( is_array( $data ) && ! empty( $data['pages'] ) ) : ?>
						<!-- SOURCE PAGE SELECTION & POST TYPE FILTER -->
						<section class="gmm-panel">
							<h2><?php esc_html_e( 'Choose a Source Page to Map', 'elementor-to-bricks-media-migrator' ); ?></h2>

							<div class="ebmm-filter-bar">
								<!-- Post Type Filter -->
								<div class="ebmm-filter-group">
									<label for="ebmm-pt-filter"><strong><?php esc_html_e( 'Filter by Type:', 'elementor-to-bricks-media-migrator' ); ?></strong></label>
									<select id="ebmm-pt-filter" class="ebmm-select">
										<option value=""><?php esc_html_e( 'All Post Types', 'elementor-to-bricks-media-migrator' ); ?></option>
										<?php
										$available_imported_pts = ! empty( $data['post_types'] ) ? $data['post_types'] : array_unique( wp_list_pluck( $data['pages'], 'post_type' ) );
										foreach ( $available_imported_pts as $pt ) :
											$pt_label = isset( $available_post_types[ $pt ] ) ? $available_post_types[ $pt ] : ucfirst( $pt );
											?>
											<option value="<?php echo esc_attr( $pt ); ?>"><?php echo esc_html( $pt_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Instant Search Filter -->
								<div class="ebmm-filter-group ebmm-search-group">
									<label for="ebmm-search-input"><strong><?php esc_html_e( 'Search Pages:', 'elementor-to-bricks-media-migrator' ); ?></strong></label>
									<input type="text" id="ebmm-search-input" class="ebmm-input ebmm-search-box" placeholder="<?php esc_attr_e( 'Type title or slug...', 'elementor-to-bricks-media-migrator' ); ?>" autocomplete="off">
								</div>

								<!-- Source Page Select -->
								<div class="ebmm-filter-group ebmm-page-select-group">
									<form method="get" id="ebmm-page-form">
										<input type="hidden" name="page" value="elementor-to-bricks-media-migrator">
										<label for="ebmm-source-select"><strong><?php esc_html_e( 'Select Page:', 'elementor-to-bricks-media-migrator' ); ?></strong> <span id="ebmm-page-match-count" class="ebmm-match-count"></span></label>
										<select name="source_page_id" id="ebmm-source-select" class="gmm-page-select" onchange="this.form.submit()">
											<option value=""><?php esc_html_e( '— Select a page —', 'elementor-to-bricks-media-migrator' ); ?></option>
											<?php foreach ( $data['pages'] as $page ) : ?>
												<option value="<?php echo esc_attr( $page['post_id'] ); ?>" data-post-type="<?php echo esc_attr( $page['post_type'] ); ?>" data-slug="<?php echo esc_attr( $page['slug'] ); ?>" <?php selected( $selected_page_id, $page['post_id'] ); ?>>
													[<?php echo esc_html( strtoupper( $page['post_type'] ) ); ?>] <?php echo esc_html( $page['title'] ); ?> — <?php echo esc_html( $page['image_count'] ); ?> <?php esc_html_e( 'images', 'elementor-to-bricks-media-migrator' ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</form>
								</div>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( $selected_page ) : ?>
						<section class="gmm-page-banner">
							<div>
								<strong><?php echo esc_html( $selected_page['title'] ); ?> (<?php echo esc_html( strtoupper( $selected_page['post_type'] ) ); ?>)</strong>
								<span><?php echo esc_html( $selected_page['url'] ); ?></span>
							</div>
							<div><?php echo esc_html( $selected_page['image_count'] ); ?> <?php esc_html_e( 'source images', 'elementor-to-bricks-media-migrator' ); ?></div>
						</section>

						<?php if ( ! $staging_page ) : ?>
							<div class="gmm-warning">
								<strong><?php esc_html_e( 'No matching target page found on this staging site.', 'elementor-to-bricks-media-migrator' ); ?></strong>
								<p><?php esc_html_e( 'The migrator checks by exact URL path, slug, and post resolution. Please create or publish the corresponding target page in Bricks.', 'elementor-to-bricks-media-migrator' ); ?></p>
							</div>
						<?php else : ?>
							<section class="gmm-target-banner">
								<div>
									<strong><?php esc_html_e( 'Matching target page found (Bricks)', 'elementor-to-bricks-media-migrator' ); ?></strong>
									<span><?php echo esc_html( get_permalink( $staging_page->ID ) ); ?></span>
								</div>
								<div class="ebmm-progress-stat">
									<div class="ebmm-stat-numbers">
										<span class="ebmm-synced-counter"><strong><?php echo esc_html( $synced_images_count ); ?></strong> / <?php echo esc_html( $total_source_images ); ?></span>
										<span class="ebmm-stat-label"><?php esc_html_e( 'images synced', 'elementor-to-bricks-media-migrator' ); ?></span>
									</div>
									<div class="ebmm-progress-bar">
										<?php $percent = $total_source_images > 0 ? round( ( $synced_images_count / $total_source_images ) * 100 ) : 0; ?>
										<div class="ebmm-progress-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
									</div>
								</div>
							</section>

							<?php if ( empty( $selected_page['images'] ) ) : ?>
								<div class="gmm-warning"><?php esc_html_e( 'No source images were detected on this page in the export.', 'elementor-to-bricks-media-migrator' ); ?></div>
							<?php else : ?>
								<!-- SIDE BY SIDE MAPPING ROWS -->
								<div class="gmm-image-list">
									<?php foreach ( $selected_page['images'] as $image ) :
										$row_is_synced = false;
										$synced_target_id = 0;

										if ( ! empty( $staging_images ) ) {
											foreach ( $staging_images as $stg_img ) {
												if (
													trim( (string) $stg_img['title'] ) === trim( (string) $image['title'] ) &&
													trim( (string) $stg_img['alt'] ) === trim( (string) $image['alt'] ) &&
													trim( (string) $stg_img['caption'] ) === trim( (string) $image['caption'] ) &&
													trim( (string) $stg_img['description'] ) === trim( (string) $image['description'] ) &&
													( ! empty( $image['title'] ) || ! empty( $image['alt'] ) || ! empty( $image['caption'] ) || ! empty( $image['description'] ) )
												) {
													$row_is_synced = true;
													$synced_target_id = (int) $stg_img['attachment_id'];
													break;
												}
											}
										}
									?>
										<div class="gmm-image-row <?php echo $row_is_synced ? 'ebmm-row-synced' : 'ebmm-row-pending'; ?>" data-source-id="<?php echo esc_attr( $image['attachment_id'] ); ?>" data-is-synced="<?php echo $row_is_synced ? '1' : '0'; ?>">
											<!-- SOURCE COLUMN -->
											<div class="gmm-source-col">
												<div class="ebmm-row-topbar">
													<div class="gmm-label"><?php esc_html_e( 'SOURCE (ELEMENTOR)', 'elementor-to-bricks-media-migrator' ); ?></div>
													<span class="ebmm-badge <?php echo $row_is_synced ? 'ebmm-badge-synced' : 'ebmm-badge-pending'; ?>">
														<?php echo $row_is_synced ? esc_html__( '✓ Synced', 'elementor-to-bricks-media-migrator' ) : esc_html__( 'Pending', 'elementor-to-bricks-media-migrator' ); ?>
													</span>
												</div>
												<div class="gmm-preview"><img src="<?php echo esc_url( $image['thumbnail'] ? $image['thumbnail'] : $image['url'] ); ?>" alt=""></div>
												<div class="gmm-filename"><?php echo esc_html( $image['filename'] ); ?></div>
												<?php self::render_metadata_table( $image ); ?>
											</div>

											<!-- TARGET COLUMN -->
											<div class="gmm-target-col">
												<div class="ebmm-row-topbar">
													<div class="gmm-label"><?php esc_html_e( 'TARGET (BRICKS) — SELECT MATCH', 'elementor-to-bricks-media-migrator' ); ?></div>
												</div>

												<?php if ( empty( $staging_images ) ) : ?>
													<div class="gmm-empty-target"><?php esc_html_e( 'No images were detected in Bricks for this target page.', 'elementor-to-bricks-media-migrator' ); ?></div>
												<?php else : ?>
													<div class="gmm-target-grid">
														<?php foreach ( $staging_images as $target ) :
															$is_name_match = ( ! empty( $target['filename'] ) && ! empty( $image['filename'] ) && strtolower( $target['filename'] ) === strtolower( $image['filename'] ) );
															$is_synced_match = ( $synced_target_id === (int) $target['attachment_id'] );
															$btn_classes = array( 'gmm-target-option' );
															if ( $is_synced_match ) {
																$btn_classes[] = 'ebmm-option-synced';
															} elseif ( $is_name_match ) {
																$btn_classes[] = 'ebmm-option-name-match';
															}
														?>
															<button type="button" class="<?php echo esc_attr( implode( ' ', $btn_classes ) ); ?>" data-id="<?php echo esc_attr( $target['attachment_id'] ); ?>" data-url="<?php echo esc_url( $target['thumbnail'] ? $target['thumbnail'] : $target['url'] ); ?>" title="<?php echo esc_attr( $target['title'] ); ?>">
																<img src="<?php echo esc_url( $target['thumbnail'] ? $target['thumbnail'] : $target['url'] ); ?>" alt="">
																<span><?php echo esc_html( $target['filename'] ); ?></span>
																<?php if ( $is_synced_match ) : ?>
																	<span class="ebmm-hint-tag ebmm-hint-synced"><?php esc_html_e( '✓ Mapped', 'elementor-to-bricks-media-migrator' ); ?></span>
																<?php elseif ( $is_name_match ) : ?>
																	<span class="ebmm-hint-tag ebmm-hint-name"><?php esc_html_e( 'Name Match', 'elementor-to-bricks-media-migrator' ); ?></span>
																<?php endif; ?>
															</button>
														<?php endforeach; ?>
													</div>
												<?php endif; ?>

												<!-- EDITABLE TARGET DETAILS -->
												<div class="gmm-selected-target">
													<div class="gmm-selected-label"><?php esc_html_e( 'SELECTED TARGET IMAGE (EDITABLE)', 'elementor-to-bricks-media-migrator' ); ?></div>
													<div class="gmm-preview gmm-target-preview"><span><?php esc_html_e( 'Select an image above', 'elementor-to-bricks-media-migrator' ); ?></span></div>
													<input type="hidden" class="gmm-target-id" value="">

													<div class="ebmm-target-meta">
														<div class="ebmm-field-group">
															<label><?php esc_html_e( 'Title', 'elementor-to-bricks-media-migrator' ); ?></label>
															<input type="text" class="ebmm-input ebmm-target-title" value="" placeholder="—">
														</div>
														<div class="ebmm-field-group">
															<label><?php esc_html_e( 'Alt Text', 'elementor-to-bricks-media-migrator' ); ?></label>
															<input type="text" class="ebmm-input ebmm-target-alt" value="" placeholder="—">
														</div>
														<div class="ebmm-field-group">
															<label><?php esc_html_e( 'Caption', 'elementor-to-bricks-media-migrator' ); ?></label>
															<textarea class="ebmm-input ebmm-target-caption" rows="2" placeholder="—"></textarea>
														</div>
														<div class="ebmm-field-group">
															<label><?php esc_html_e( 'Description', 'elementor-to-bricks-media-migrator' ); ?></label>
															<textarea class="ebmm-input ebmm-target-description" rows="2" placeholder="—"></textarea>
														</div>
													</div>

													<div class="gmm-action-area ebmm-actions">
														<button type="button" class="button button-primary gmm-copy" disabled>
															<?php esc_html_e( 'Copy Source → Target', 'elementor-to-bricks-media-migrator' ); ?>
														</button>
														<button type="button" class="button button-secondary ebmm-save-meta" disabled>
															<?php esc_html_e( 'Save Target Changes', 'elementor-to-bricks-media-migrator' ); ?>
														</button>
														<div class="gmm-status" aria-live="polite"></div>
													</div>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>

			<footer class="ebmm-footer">
				<p>
					<?php esc_html_e( 'Elementor to Bricks Media Migrator v1.0.0', 'elementor-to-bricks-media-migrator' ); ?> &bull;
					<?php esc_html_e( 'Crafted with care by', 'elementor-to-bricks-media-migrator' ); ?> <a href="https://abidkp.com" target="_blank" rel="noopener"><strong>Abid KP</strong></a>
				</p>
			</footer>
		</div>
		<?php
	}

	private static function render_metadata_table( $image ) {
		$fields = array(
			'title'       => __( 'Title', 'elementor-to-bricks-media-migrator' ),
			'alt'         => __( 'Alt', 'elementor-to-bricks-media-migrator' ),
			'caption'     => __( 'Caption', 'elementor-to-bricks-media-migrator' ),
			'description' => __( 'Description', 'elementor-to-bricks-media-migrator' ),
		);
		?>
		<div class="gmm-meta">
			<?php foreach ( $fields as $key => $label ) : ?>
				<div>
					<strong><?php echo esc_html( $label ); ?></strong>
					<span class="ebmm-source-val-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( isset( $image[ $key ] ) ? $image[ $key ] : '' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function get_available_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		$list = array();
		foreach ( $post_types as $pt => $obj ) {
			$list[ $pt ] = ! empty( $obj->labels->name ) ? $obj->labels->name : ucfirst( $pt );
		}
		return $list;
	}

	/* ---------------------------------------------------------------------
	 * SECURITY / HELPERS
	 * ------------------------------------------------------------------ */

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use Elementor to Bricks Media Migrator.', 'elementor-to-bricks-media-migrator' ) );
		}
	}

	private static function is_image_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}
		$mime = get_post_mime_type( $attachment_id );
		return $mime && 0 === strpos( $mime, 'image/' );
	}
}

class_alias( 'Elementor_To_Bricks_Media_Migrator', 'Greenday_Media_Migrator' );

Elementor_To_Bricks_Media_Migrator::init();
