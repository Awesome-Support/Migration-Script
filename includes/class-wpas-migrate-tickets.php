<?php

class WPAS_Migrate_Tickets {

	protected $converted_tickets     = 0;
	protected $converted_replies     = 0;
	protected $converted_attachments = 0;
	protected $conversion_errors     = 0;
	protected $errors                = array();

	public function __construct() {

		/**
		 * Set a limit for the number of tickets to migrate at once.
		 *
		 * Setting a limit is important to avoid timeout issues. The default is set
		 * at 300 but this could be too much for some hosts. That's why a filter is added.
		 *
		 * @param integer Number of tickets to process at once
		 */
		$this->limit = apply_filters( 'wpas_migration_script_processing_limit', 300 );

		$args = array(
			'post_type'      => 'tickets',
			'posts_per_page' => - 1,
			'post_status'    => 'any',
		);

		$this->query   = new WP_Query( $args );
		$this->tickets = $this->query->posts;

		/**
		 * Set all the session variables we need
		 */
		$this->set_session_vars();

	}

	protected function set_session_vars() {

		if ( ! isset( $_SESSION['as']['tickets_total'] ) ) {
			$_SESSION['as']['tickets_total'] = $this->query->post_count;
		}

		if ( ! isset( $_SESSION['as']['converted_tickets'] ) ) {
			$_SESSION['as']['converted_tickets'] = 0;
		}

		if ( ! isset( $_SESSION['as']['converted_replies'] ) ) {
			$_SESSION['as']['converted_replies'] = 0;
		}

		if ( ! isset( $_SESSION['as']['converted_attachments'] ) ) {
			$_SESSION['as']['converted_attachments'] = 0;
		}

		if ( ! isset( $_SESSION['as']['current_page'] ) ) {
			$_SESSION['as']['current_page'] = 1;
		}

		if ( ! isset( $_SESSION['as']['converted_errors'] ) ) {
			$_SESSION['as']['converted_errors'] = 0;
		}

	}

	protected function increment_tickets() {
		++$_SESSION['as']['converted_tickets'];
	}

	protected function increment_replies() {
		++$_SESSION['as']['converted_replies'];
	}

	protected function increment_attachments() {
		++$_SESSION['as']['converted_attachments'];
	}

	protected function increment_current_page() {
		++$_SESSION['as']['current_page'];
	}

	protected function increment_errors() {
		++$_SESSION['as']['converted_errors'];
	}

	public function get_converted_tickets_count() {
		return isset( $_SESSION['as']['converted_tickets'] ) ? $_SESSION['as']['converted_tickets'] : 0;
	}

	public function get_converted_replies_count() {
		return isset( $_SESSION['as']['converted_replies'] ) ? $_SESSION['as']['converted_replies'] : 0;
	}

	public function get_converted_attachments_count() {
		return isset( $_SESSION['as']['converted_attachments'] ) ? $_SESSION['as']['converted_attachments'] : 0;
	}

	public function get_errors_count() {
		return isset( $_SESSION['as']['converted_errors'] ) ? $_SESSION['as']['converted_errors'] : 0;
	}

	public function get_current_page() {
		return isset( $_SESSION['as']['current_page'] ) ? $_SESSION['as']['current_page'] : 0;
	}

	public function get_tickets_total() {
		return isset( $_SESSION['as']['tickets_total'] ) ? $_SESSION['as']['tickets_total'] : null;
	}

	public function convert_tickets() {

		if ( ! empty( $this->tickets ) ) {

			foreach ( $this->tickets as $ticket ) {

				/**
				 * First of all we need to make sure we don't have to change page
				 */
				if ( $this->limit === $this->get_converted_tickets_count() / $this->get_current_page() ) {
					$this->increment_current_page();
					break;
				}

				$convert = new WPAS_Migrate_Ticket( $ticket );
				$convert->migrate();
				$result  = $convert->result();

				/* If the conversion failed, we add the post ID to the list of errors */
				if ( is_wp_error( $result ) ) {

					array_push( $this->errors, $ticket->ID );

					/* Update the live log */
					$this->update_log( $ticket->ID, false, $result->get_error_messages() );

				}

				/* Otherwise we increment all the counts */
				else {
					/* Update the live log */
					$this->update_log( $ticket->ID );
				}

			}

			/* If we still have more pages to process, let's just reload the page. The nonce will still be valid. */
			if ( $this->get_converted_tickets_count() < $this->get_tickets_total() && $this->get_current_page() <= ceil( $this->get_tickets_total() / $this->limit ) ) {

				echo '<meta http-equiv="refresh" content="5">';
				printf( '<p>We processed a total of %d tickets. Please <a href="#" onClick="window.location.reload()">click this link if the page does not refresh</a>.</p>', $this->get_converted_tickets_count() );

			}

			if ( $this->get_converted_tickets_count() === $this->get_tickets_total() && $this->get_current_page() >= ceil( $this->get_tickets_total() / $this->limit ) ) {

				/* Show the final result */
				printf( '<div class="wpas-migration-final-result"><p>Migration done with the following results: %d tickets converted, %d replies, %d attachments</p></div>', $this->get_converted_tickets_count(), $this->get_converted_replies_count(), $this->get_converted_attachments_count() );
				echo '<p>You can now delete this migration plugin and start using Awesome Support version 3.</p>';

				/* Clear the session */
				unset( $_SESSION['as'] );
			}

		}

	}

	public function get_tickets_count() {
		return count( $this->tickets );
	}

	public function migrate() {

		if ( isset( $_GET['_nonce'] ) && wp_verify_nonce( $_GET['_nonce'], 'upgrade' ) ) {
			$this->convert_tickets();
			$this->update_canned_responses();
		}

	}

	/**
	 * Update the canned responses.
	 *
	 * @return boolean True if all history posts were migrated, false otherwise
	 */
	public function update_canned_responses() {
		return $this->update_post_type( 'quick-reply', 'canned-response' );
	}

	/**
	 * Update a post type
	 *
	 * @param $from   string Original post type
	 * @param $to     string New post type
	 * @param $parent int ID of the parent post
	 *
	 * @return bool Whether or not all posts were migrated
	 */
	public function update_post_type( $from, $to, $parent = 0 ) {

		$pass = 0;

		$args = array(
			'post_type'      => $from,
			'posts_per_page' => - 1,
			'post_status'    => 'any',
		);

		if ( 0 !== $parent ) {
			$args['post_parent'] = $parent;
		}

		$history = new WP_Query( $args );

		if ( ! empty( $history->posts ) ) {

			foreach ( $history->posts as $post ) {

				$new_args = array(
					'ID'          => $post->ID,
					'post_type'   => $to,
					'post_status' => 'publish'
				);

				if ( 'ticket_status' === $from ) {

					if ( 'open' === $post->post_content ) {
						$new_args['post_content'] = 'The ticket was re-opened.';
					}

					if ( 'close' === $post->post_content ) {
						$new_args['post_content'] = 'The ticket was closed.';
					}

				}

				$new = wp_update_post( $new_args );

				if ( ! $new ) {
					++ $pass;
				}

			}

		}

		if ( 0 !== $pass ) {
			return false;
		}

		return true;

	}

	protected function update_log( $post_id = 0, $status = true, $errors = '' ) {

		if ( $status ) {

			$link    = add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) );
			$message = sprintf( 'Success migrating ticket %s', "<a href='$link'>#$post_id</a>" );

		} else {
			$message = empty( $errors ) ? sprintf( 'Failed migrating ticket #%d', $post_id ) : sprintf( 'Failed migrating ticket #%d (%s)', $post_id, implode( ', ', $errors ) );
		}

		$message .= '<br>';

		echo $message;

		ob_flush();
		flush();

	}

}