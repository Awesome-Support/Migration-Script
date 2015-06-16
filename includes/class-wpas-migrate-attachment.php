<?php
class WPAS_Migrate_Attachment {

	/**
	 * Post ID.
	 */
	protected $post_id = 0;

	/**
	 * Parent ID.
	 */
	protected $parent_id = 0;

	/**
	 * Attachment array.
	 */
	protected $attachment = array();

	/**
	 * Result of the attachment migration.
	 */
	protected $result = null;

	/**
	 * Class constructor.
	 *
	 * @param $post_id    int ID of the post for this attachment
	 * @param $attachment array Array of the attached media
	 * @param $parent_id  int ID of the parent post
	 */
	public function __construct( $post_id, $attachment, $parent_id = 0 ) {

		$this->post_id    = $post_id;
		$this->parent_id  = $parent_id;
		$this->attachment = $attachment;

		if ( 'wordpress' === $this->get_upload_source() ) {
			$this->result = $this->move_file();
		} elseif ( 'filepicker' === $this->get_upload_source() ) {
			$this->result = $this->add_media();
		}

	}

	/**
	 * Get the result of the attachment migration.
	 */
	public function get_result() {
		return $this->result;
	}

	/**
	 * Get the upload source.
	 *
	 * The upload source is either Filepicker or the WordPress media uploader.
	 */
	protected function get_upload_source() {
		return isset( $this->attachment['uploader'] ) && 'filepicker' === $this->attachment['uploader'] ? 'filepicker' : 'wordpress';
	}

	/**
	 * Move a file uploaded with v2 to the new protected location.
	 */
	protected function move_file() {

		$dir_id     = 0 !== $this->parent_id ? $this->parent_id : $this->post_id;
		$current    = str_replace( '\\', '/', $this->get_file_path() );
		$upload_dir = wp_upload_dir();
		$subdir     = "/ticket_$dir_id";
		$root       = $upload_dir['basedir'] . '/awesome-support';
		$dir        = $root . $subdir;
		$new_path   = $dir . '/' . $this->attachment['file'];

		/* Create the root directory for a start. */
		if ( ! is_dir( $root ) ) {

			mkdir( $root );

			if ( true === $root ) {
				$this->protect_upload_dir( $root );
			}

		}

		/* Create the ticket upload directory if needed. */
		if ( ! is_dir( $dir ) ) {

			$make = mkdir( $dir );

			if ( true === $make ) {
				$this->protect_upload_dir( $dir );
			}

		}

		/* Move the attachment to the new location. */
		if ( is_dir( $dir ) ) {

			$rename = @rename( $current, $new_path );

			if ( true === $rename && is_file( $new_path ) ) {

				$attachment = array(
					'post_mime_type' => filetype( $new_path ),
					'guid'           => $upload_dir['baseurl'] . '/awesome-support/' . $subdir . '/' . $this->attachment['file'],
					'post_title'     => $this->attachment['filename'],
					'post_content'   => '',
					'post_excerpt'   => ''
				);

				/* Save the data */
				$id = wp_insert_attachment( $attachment, $new_path, $this->post_id );

				if ( ! is_wp_error( $id ) ) {
					wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $new_path ) );
				}

				return $id;

			}

		}

		return false;

	}

	/**
	 * Protects an upload directory by adding a .htaccess file
	 *
	 * @since 3.1.7
	 *
	 * @param string $dir Upload directory
	 *
	 * @return void
	 */
	protected function protect_upload_dir( $dir ) {
		$filename = $dir . '/.htaccess';
		if ( ! file_exists( $filename ) ) {
			$file = fopen( $filename, 'a+' );
			fwrite( $file, 'Options -Indexes' );
			fclose( $file );
		}
	}

	protected function get_upload_dir() {

		if ( 'wordpress' === $this->get_upload_source() ) {
			$upload_dir = wp_upload_dir();
			return $upload_dir['basedir'] . '/wpas_attachments/';
		}

		return false;

	}

	protected function get_file_path() {

		if ( 'wordpress' === $this->get_upload_source() ) {
			return $this->get_upload_dir() . $this->attachment['file'];
		}

		return $this->attachment['file'];

	}

	/**
	 * Add a new media to the media library.
	 */
	protected function add_media() {

		$filename = preg_replace( '/\.[^.]+$/', '', $this->attachment['filename'] );

		$file = array(
			'guid'           => $this->attachment['file'],
			'post_mime_type' => $this->attachment['mime'],
			'post_title'     => $filename,
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		$resource_id = wp_insert_attachment( $file, $this->attachment['file'], $this->post_id );

		if ( ! is_wp_error( $resource_id ) ) {

			$attach_data = wp_generate_attachment_metadata( $resource_id, $filename );

			/**
			 * Add the upload source to the metadata
			 */
			$attach_data['wpas_upload_source'] = 'filepicker';
			$attach_data['file_size']          = $this->attachment['size'];
			$attach_data['file_name']          = $filename;

			wp_update_attachment_metadata( $resource_id, $attach_data );

			return $resource_id;

		} else {
			return false;
		}

	}

}