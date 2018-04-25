<?php
/**
 * Handles the registration of custom fields for the menu items.
 *
 * @package     wp-user-manager
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License
*/

use Carbon_Fields\Container;
use Carbon_Fields\Field;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Define the settings for the menu items.
 */
class WPUM_Menus {

	/**
	 * Get things started.
	 */
	public function __construct() {
		add_action( 'carbon_fields_register_fields', [ $this, 'menu_settings' ] );
		add_action( 'load-nav-menus.php', [ $this, 'cssjs' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'js' ] );
		add_filter( 'nav_menu_link_attributes', [ $this, 'set_nav_item_as_logout' ], 10, 4 );
		if( ! is_admin() ) {
			add_filter( 'wp_get_nav_menu_items', [ $this, 'exclude_menu_items' ], 10, 3 );
		}
	}

	/**
	 * Register menu settings.
	 *
	 * @return void
	 */
	public function menu_settings() {
		Container::make( 'nav_menu_item', 'Menu Settings' )
			->add_fields( array(
				Field::make( 'checkbox', 'convert_to_logout', esc_html__( 'Set as logout url' ) )
					->set_help_text( esc_html__( 'Enable to make this link a logout link.' ) )
					->set_classes( 'wpum-link-logout-toggle' ),
				Field::make( 'select', 'link_visibility', esc_html__( 'Display to:' ) )
					->add_options( array(
						''    => esc_html__( 'Everyone' ),
						'in'  => esc_html__( 'Logged in users' ),
						'out' => esc_html__( 'Logged out users' ),
					) )
					->set_classes( 'wpum-link-visibility-toggle' )
					->set_help_text( esc_html__( 'Set the visibility of this menu item.' ) ),
				Field::make( 'multiselect', 'link_roles', esc_html__( 'Select roles:' ) )
					->add_options( $this->get_roles() )
					->set_classes( 'wpum-link-visibility-roles' )
					->set_help_text( esc_html__( 'Select the roles that should see this menu item. Leave blank for all roles.' ) )
			) );
	}

	/**
	 * Return an array containing user roles.
	 *
	 * @return array
	 */
	private function get_roles() {

		$roles = [];

		foreach( wpum_get_roles( true ) as $role ) {
			$roles[ $role['value'] ] = $role['label'];
		}

		return $roles;

	}

	/**
	 * Adjust styling of the menu settings.
	 *
	 * @return void
	 */
	public function cssjs() {
		?>
		<style>
			.carbon-field.carbon-checkbox {padding-left:0px !important;}
			.wpum-link-visibility-roles {display:none};
		</style>
		<?php
	}

	/**
	 * Add custom js file to handle hide/show of the roles selector.
	 *
	 * @return void
	 */
	public function js() {
		$screen = get_current_screen();
		if( $screen->base == 'nav-menus' ) {
			wp_enqueue_script( 'wpum-menu-editor', WPUM_PLUGIN_URL . '/assets/js/admin/admin-menus.min.js', false, WPUM_VERSION, true );
		}
	}

	/**
	 * Modify a nav menu item url to a logout url if the option is enabled.
	 *
	 * @param array $atts
	 * @param object $item
	 * @param array $args
	 * @param string $depth
	 * @return array
	 */
	public function set_nav_item_as_logout( $atts, $item, $args, $depth ) {

		$is_logout = carbon_get_nav_menu_item_meta( $item->ID, 'convert_to_logout' );

		if( $is_logout ) {
			$atts['href'] = wp_logout_url();
		}

		return $atts;

	}

	/**
	 * Determine if the menu item should be visible or not.
	 *
	 * @param array $items
	 * @param array $menu
	 * @param array $args
	 * @return void
	 */
	public function exclude_menu_items( $items, $menu, $args ) {

		foreach( $items as $key => $item ) {

			$status    = carbon_get_nav_menu_item_meta( $item->ID, 'link_visibility' );
			$roles     = carbon_get_nav_menu_item_meta( $item->ID, 'link_roles' );
			$is_logout = carbon_get_nav_menu_item_meta( $item->ID, 'convert_to_logout' );
			$visible   = true;

			switch ( $status ) {
				case 'in':
					$visible = is_user_logged_in() ? true : false;
					if( is_array( $roles ) && ! empty( $roles ) ) {
						foreach ( $roles as $role ) {
							if( ! current_user_can( $role ) ) {
								$visible = false;
							}
						}
					}
					break;
				case 'out':
					$visible = ! is_user_logged_in() ? true : false;
					break;
			}
			// Now exclude item if not visible.
			if( ! $visible && ! $is_logout ) {
				unset( $items[ $key ] );
			}

		}

		return $items;

	}

}

new WPUM_Menus;
