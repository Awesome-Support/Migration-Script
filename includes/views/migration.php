<?php add_thickbox(); ?>
<div class="wrap">
	<div id="icon-tools" class="icon32"></div>

	<h2>Awesome Support Migration Tool</h2>

	<?php if (  isset( $_GET['do'] ) && 'upgrade' === filter_input( INPUT_GET, 'do', FILTER_SANITIZE_STRING ) && isset( $_GET['_nonce'] ) && wp_verify_nonce( $_GET['_nonce'], 'upgrade' ) ):

		global $wpas_migrate;
		$wpas_migrate = new WPAS_Migrate_Tickets(); ?>

		<p>The migration of <strong><?php echo $wpas_migrate->get_tickets_count(); ?></strong> tickets will now begin. Please be patient as this could take a few minutes. Do <strong>not</strong> close this window while the process is running.</p>
		<div class="wpas-migration-result">
			<?php $wpas_migrate->migrate(); ?>
		</div>

	<?php else: ?>

		<p>This tool will help you migrate from Awesome Support version 2.x to the latest version 3.x. This process will heavily modify your database. In order to avoid loosing data in case of problem, please carefully follow the instructions hereafter:</p>

		<h3>Step 1: Custom Fields Migration</h3>

		<?php
		global $wpas;

		$custom_fields     = $wpas->getCustomFields();
		$custom_taxonomies = $wpas->getTaxonomies();

		if ( empty( $custom_fields ) && empty( $custom_taxonomies ) ) {
			echo '<p>You have no custom fields. Nothing to do here.</p>';
		} else {

			echo '<p>In order to migrate your custom fields, you need to add the following bits of code to your theme&#039;s <code>functions.php</code> file.</p>';
			echo '<p>If you were using custom callbacks for your custom fields, please make sure those callback functions are still available.</p>';

			echo '<textarea cols="150" rows="20">';
			echo "
/**
 * Register Awesome Support custom fields after the plugin is safely loaded.
 */\n";
			echo "if ( function_exists( 'wpas_add_custom_field' ) && function_exists( 'wpas_add_custom_taxonomy' ) ):\n\n";

			foreach ( $custom_fields as $custom_field ) {

				if ( 'wpas_text_field_callback' === $custom_field['callback'] ) {
					$custom_field['callback'] = 'text';
				} elseif ( 'wpas_url_field_callback' === $custom_field['callback'] ) {
					$custom_field['callback'] = 'url';
				}

				if ( ! isset( $custom_field['required'] ) ) {
					$custom_field['required'] = 'false';
				}

				$custom_field['required'] = true === $custom_field['required'] ? 'true' : 'false';

				echo "wpas_add_custom_field( '{$custom_field['name']}', array(
	'callback' => '{$custom_field['callback']}',
	'title'    => '{$custom_field['label']}',
	'required' => {$custom_field['required']}
) );\n\n";
			}

			foreach ( $custom_taxonomies as $custom_taxonomy ) {

				$custom_taxonomy['required'] = true === $custom_taxonomy['required'] ? 'true' : false;

				echo "wpas_add_custom_taxonomy( '{$custom_taxonomy['singular']}', array(
	'title'    => '{$custom_taxonomy['label']}',
	'label'    => '{$custom_taxonomy['label']}',
	'singular' => '{$custom_taxonomy['singular']}',
	'plural'   => '{$custom_taxonomy['plural']}',
	'required' => {$custom_taxonomy['required']}
) );\n\n";

			}

			echo "endif;";

			echo '</textarea>';

		}
		?>

		<h3>Step 2: Awesome Support Migration</h3>
		<ol>
			<li><strong>Backup your database</strong> (don&#039;t know how? <a href="https://codex.wordpress.org/Backing_Up_Your_Database" target="_blank">click here</a>)</li>
			<li>Using your FTP client, <strong>delete the old plugin directory</strong> from your server. <strong>DO NOT uninstall the plugin from your dashboard</strong></li>
			<li>Install the latest version of Awesome Support. It is advised to <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'plugin-information', 'plugin' => 'awesome-support', 'TB_iframe' => 'true', 'width' => '800', 'height' => '600' ), admin_url( 'plugin-install.php' ) ) ); ?>" class="thickbox">install it from your dashboard</a></li>
			<li>Click the button bellow</li>
		</ol>
		<?php if ( ! defined( 'WPAS_VERSION' ) || version_compare( WPAS_VERSION, '3.0.0', '<' ) ) {
			echo '<p>You need to have Awesome Support version 3 or higher installed in order to migrate your data. <a href="https://wordpress.org/plugins/awesome-support/">Please download the latest version from WordPress.org</a></p>';
		} else { ?>
			<a href="<?php echo add_query_arg( array( 'page' => 'wpas-upgrade', 'do' => 'upgrade', '_nonce' => wp_create_nonce( 'upgrade' ) ), admin_url( 'tools.php' ) ); ?>" class="button-primary" onclick="return confirm( 'Did you backup your database before clicking this button?\n\nIf you do not backup your database we cannot be held responsible for any loss of data resulting from a migration error.' );">Upgrade</a>
		<?php }

	endif; ?>

</div>