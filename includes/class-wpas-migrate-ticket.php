<?php
class WPAS_Migrate_Ticket extends WPAS_Migrate_Tickets  {

	/**
	 * ID of the ticket to migrate.
	 * 
	 * @var integer
	 */
	protected $ticket_id = null;

	/**
	 * Migration result.
	 * 
	 * @var mixed
	 */
	public $result = true;
	
	public function __construct( $ticket ) {

		/**
		 * List all custom fields.
		 */
		global $wpas;

		$this->custom_fields = $wpas->getCustomFields();

		if ( is_object( $ticket ) && is_a( $ticket, 'WP_Post' ) ) {
			$this->ticket_id = $ticket->ID;
		} else {
			$this->ticket_id = (int) $ticket;
		}
	}

	public function migrate() {

		$this->update_ticket(); // Update the post
		$this->move_replies_attachments();
		$this->update_ticket_metas();
		$this->update_history();
		$this->update_envato();
		$this->move_attachments();
		$this->migrate_custom_fields();

	}

	public function result() {
		return $this->result;
	}

	protected function error( $message ) {

		if ( ! is_wp_error( $this->result ) ) {
			$this->result = new WP_Error();
		}

		$this->result->add( 'migration_error', $message );
		$this->increment_errors();

	}

	/**
	 * Get the ticket status.
	 *
	 * A ticket status can be wpas-open
	 * or wpas-close
	 * 
	 * @return string
	 */
	public function get_status() {
		
		$status = get_the_terms( $this->ticket_id, 'status' );
		$out    = '';

		if ( $status ) {
			foreach( $status as $s ) {
				$out = $s->slug;
			}
		}

		if ( 'wpas-open' == $out ) {
			$out = 'open';
		} elseif ( 'wpas-close' == $out ) {
			$out = 'closed';
		}

		return $out;

	}

	/**
	 * Get ticket type.
	 * 
	 * @return string
	 */
	public function get_type() {

		$types = get_the_terms( $this->ticket_id, 'type' );
		$out   = '';

		if( $types ) {
			foreach($types as $t) {
				$out = $t->name;
			}
		}

		return $out;

	}

	/**
	 * Get ticket state.
	 * 
	 * @return string
	 */
	public function get_state() {

		$states = get_the_terms( $this->ticket_id, 'state' );
		$out    = '';

		/* Get the state and apply style to the containing tag */
		if ( !empty( $states ) ) {
			foreach ( $states as $sid => $state ) {
				$out = $state->slug;
			}
		}

		return $out;

	}

	public function get_new_state() {

		switch ( $this->get_state() ) {

			case 'in-progress':
				$state = 'processing';
				break;

			case 'queued':
				$state = 'queued';
				break;

			case 'on-hold':
				$state = 'hold';
				break;

			default:
				$state = 'queued';

		}

		return $state;

	}

	/**
	 * Update the ticket.
	 *
	 * @return integer The Post ID
	 */
	public function update_ticket() {

		$args = array(
			'ID'          => $this->ticket_id,
			'post_type'   => 'ticket',
			'post_status' => $this->get_new_state()
		);

		$update = wp_update_post( $args );

		if ( 0 === $update ) {
			$this->error( 'Ticket couldn&#39;t be updated' );
		} else {
			/* Increment the converted tickets */
			$this->increment_tickets();
		}

		return $update;

	}

	/**
	 * Go through the ticket replies and move the attachments if needed.
	 *
	 * @return bool Whether or not an attachment was moved.
	 */
	protected function move_replies_attachments() {

		$args = array(
			'post_type'      => 'ticket_reply',
			'post_status'    => 'inherit',
			'post_parent'    => $this->ticket_id,
			'posts_per_page' => - 1
		);

		$replies = new WP_Query( $args );

		if ( ! empty( $replies->posts ) ) {

			foreach ( $replies->posts as $reply ) {

				/* Increment converted replies */
				$this->increment_replies();

				$result      = true;
				$attachments = get_post_meta( $reply->ID, 'wpas_ticket_attachments', true );

				if ( is_array( $attachments ) ) {

					foreach ( $attachments as $attachment ) {

						$migrate = new WPAS_Migrate_Attachment( $reply->ID, $attachment, $this->ticket_id );

						if ( false === $migrate->get_result() ) {
							$result = false;
						} else {
							/* Increment converted attachments */
							$this->increment_attachments();
						}
					}

					if ( true === $result ) {
						delete_post_meta( $this->ticket_id, 'wpas_ticket_attachments' );
					}

					return $result;

				} else {
					return true;
				}

			}

		}

		return true;

	}

	public function update_ticket_metas() {

		/**
		 * Update the agent meta
		 */
		$agent = get_post_meta( $this->ticket_id, 'wpas_ticket_assignee', true );
		$added = add_post_meta( $this->ticket_id, '_wpas_assignee', $agent, true );

		if ( ! $added ) {
			$this->error( 'The agent couldn&#39;t be migrated' );
		}

		delete_post_meta( $this->ticket_id, 'wpas_ticket_assignee', $agent );

		/**
		 * Update the status meta
		 */
		add_post_meta( $this->ticket_id, '_wpas_status', $this->get_status(), true );

		if ( ! $added ) {
			return false;
		} else {
			return true;
		}

	}

	public function update_envato() {

		$license = get_post_meta( $this->ticket_id, 'wpas_ticket_envato_purchase_license', true );
		$data    = $old = get_post_meta( $this->ticket_id, 'wpas_ticket_envato_purchased_item', true );

		/* Convert old meta data (when license info was not stored in DB) into an array */
		if ( '1' === $data ) {
			$data = array();
		}

		if ( ! empty( $license ) && ! empty( $data ) ) {

			$data['license_key'] = $license;
			$added               = add_post_meta( $this->ticket_id, '_wpas_envato_license_data', $data, true );

			if ( ! $added ) {
				$this->error( 'The Envato license couldn&#39;t be migrated' );
			}

			delete_post_meta( $this->ticket_id, 'wpas_ticket_envato_purchase_license', $license );
			delete_post_meta( $this->ticket_id, 'wpas_ticket_envato_purchased_item', $old );

			return true;

		}

		return false;

	}

	public function convert_custom_fields() {

//		global $wpas;

//		$wpas->getCustomFields();
//		$wpas->getTaxonomies();
	}

	/**
	 * Update the ticket history.
	 *
	 * @return boolean True if all history posts were migrated, false otherwise
	 */
	public function update_history() {
		return $this->update_post_type( 'ticket_status', 'ticket_history', $this->ticket_id );
	}

	/**
	 * Move the ticket attachments.
	 *
	 * @return bool Whether or not attachments have been moved
	 */
	protected function move_attachments() {

		$attachments = get_post_meta( $this->ticket_id, 'wpas_ticket_attachments', true );

		if ( is_array( $attachments ) ) {

			$result = true;

			foreach ( $attachments as $attachment ) {

				$migrate = new WPAS_Migrate_Attachment( $this->ticket_id, $attachment );

				if ( false === $migrate->get_result() ) {
					$result = false;
				}
			}

			if ( true === $result ) {
				delete_post_meta( $this->ticket_id, 'wpas_ticket_attachments' );
			}

			return $result;

		}

		/* If this is not an array then we have no attachments, return true as not migration it is not an error */

		return true;
	}

	protected function migrate_custom_fields() {

		if ( ! empty( $this->custom_fields ) ) {

			foreach ( $this->custom_fields as $custom_field ) {

				$old   = "wpas_{$custom_field['name']}";
				$value = get_post_meta( $this->ticket_id, $old, true );

				if ( ! empty( $value ) ) {

					$name    = "_wpas_{$custom_field['name']}";
					$meta_id = add_post_meta( $this->ticket_id, $name, $value, true );

					if ( $meta_id ) {
						delete_post_meta( $this->ticket_id, $old, $value );
					}
				}

			}

		}

	}

}