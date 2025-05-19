<?php
/**
 * Plugin Name:       Plate Customizer
 * Description:       Allows users to customize plates with various options and preview them live.
 * Version:           1.0.2 // Incremented version
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plate-customizer
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die; // Silence is golden.
}

define( 'PLATE_CUSTOMIZER_VERSION', '1.0.2' );
define( 'PLATE_CUSTOMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLATE_CUSTOMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// Define CPT slug centrally. Ensure this matches your CPT registration in the admin class.
define( 'PLATE_CUSTOMIZER_CPT_SLUG', 'plate_template' );

/**
 * Load plugin textdomain.
 */
function plate_customizer_load_textdomain() {
    load_plugin_textdomain( 'plate-customizer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'plate_customizer_load_textdomain' );

/**
 * Include required files.
 */
require_once PLATE_CUSTOMIZER_PLUGIN_DIR . 'includes/helpers.php';
require_once PLATE_CUSTOMIZER_PLUGIN_DIR . 'includes/class-plate-customizer-ajax.php';

require_once PLATE_CUSTOMIZER_PLUGIN_DIR . 'admin/class-plate-customizer-admin.php';
require_once PLATE_CUSTOMIZER_PLUGIN_DIR . 'public/class-plate-customizer-public.php';

/**
 * Initialize plugin components.
 */
function plate_customizer_init() {
    if ( is_admin() ) {
        new Plate_Customizer_Admin();
    }
    new Plate_Customizer_Public();
    new Plate_Customizer_Ajax();
}
add_action( 'plugins_loaded', 'plate_customizer_init' );

/**
 * Activation hook.
 */
function plate_customizer_activate() {
    // Ensure CPT is registered before flushing to make sure rewrite rules for it are created.
    if (class_exists('Plate_Customizer_Admin')) {
        // Temporarily instantiate to call the CPT registration if the main init hasn't run yet.
        $admin_instance = new Plate_Customizer_Admin();
        if (method_exists($admin_instance, 'register_plate_template_cpt')) {
             $admin_instance->register_plate_template_cpt();
        }
    }
    flush_rewrite_rules();

    // Set default options if they don't exist
    if ( false === get_option( 'plate_customizer_google_fonts' ) ) {
        update_option( 'plate_customizer_google_fonts', "Roboto\nOpen Sans\nLato" );
    }
    if ( false === get_option( 'plate_customizer_led_colors' ) ) {
        update_option( 'plate_customizer_led_colors', array(
            array( 'label' => 'Warm White (2700K)', 'color' => '#FFDAB9' ),
            array( 'label' => 'Cool White (5000K)', 'color' => '#E0FFFF' ),
        ));
    }
     if ( false === get_option('plate_customizer_admin_email') ) {
        update_option('plate_customizer_admin_email', get_option('admin_email'));
    }
}
register_activation_hook( __FILE__, 'plate_customizer_activate' );

/**
 * Deactivation hook.
 */
function plate_customizer_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'plate_customizer_deactivate' );
