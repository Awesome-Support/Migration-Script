<?php
class WPAS_Migrate_Custom_Fields {

	protected $customFields = array();
	protected $taxonomies   = array();

	/**
	 * Add custom field to submission form
	 *
	 * @param mixed $name The custom field name
	 * @param array $args List of arguments
	 *
	 * @since 2.0.0
	 */
	public function addCustomField( $name = false, $args ) {

		if ( ! $name ) {
			return;
		}

		$name = sanitize_text_field( $name );

		$field = array(
			'name'  => $name,
			'id'    => isset( $args['id'] ) ? sanitize_text_field( $args['id'] ) : $name,
			'label' => isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : $name,
		);

		if ( isset( $args['class'] ) ) {
			$field['class'] = sanitize_text_field( $args['class'] );
		}

		if ( isset( $args['callback'] ) ) {
			$field['callback'] = sanitize_text_field( $args['callback'] );
		}

		if ( isset( $args['required'] ) ) {
			$field['required'] = boolval( $args['required'] );
		}

		$this->customFields[] = $field;
	}

	/**
	 * Add a new taxonomy
	 *
	 * @param array $taxo Contains the taxonomy information as follows array( 'singular' => 'singular name', 'plural'
	 *                      => 'plural name', 'label' => 'menu label' )
	 *
	 * @since 2.0.0
	 */
	public function addTaxonomy( $taxo ) {

		if ( ! is_array( $taxo ) || ! isset( $taxo['singular'] ) || ! isset( $taxo['id'] ) ) {
			return;
		}

		$singular = $taxo['singular'];
		$plural   = isset( $taxo['plural'] ) ? $taxo['plural'] : $singular;
		$label    = isset( $taxo['label'] ) ? $taxo['label'] : $plural;
		$required = isset( $taxo['required'] ) ? $taxo['required'] : false;
		$id       = isset( $taxo['id'] ) ? $taxo['id'] : false;

		$new = array(
			'singular' => $singular,
			'plural'   => $plural,
			'label'    => $label,
			'required' => $required,
			'id'       => $id
		);

		$this->taxonomies[] = $new;

	}

	public function getCustomFields() {
		return $this->customFields;
	}

	/**
	 * Get all the taxonomies
	 *
	 * This function will mix the default taxonomies and the custom ones, either added through the class
	 * WP_Awesome_Support::addTaxonomy() or through the admin (saved in the database).
	 *
	 * @since 2.0.0
	 */
	public function getTaxonomies() {

		/**
		 * TODO
		 *
		 * Get custom taxonomies from DB then iterate
		 * and call WP_Awesome_Support::addTaxonomy()
		 * for each. Finally add it to $this->taxonomies
		 */
		$custom = array();

		return $this->taxonomies;

	}

}

$wpas = new WPAS_Migrate_Custom_Fields();