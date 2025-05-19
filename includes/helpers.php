<?php
/**
 * Helper Functions for Plate Customizer
 *
 * @package Plate_Customizer
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Get parsed Google Fonts for use in font selection dropdowns.
 * @return array An array of font family names.
 */
function plate_customizer_get_google_font_families() {
    $fonts_string = get_option( 'plate_customizer_google_fonts', "Roboto\nOpen Sans\nLato" ); // Default if option not set
    if ( empty( trim( $fonts_string ) ) ) {
        return array('Arial'); // Fallback if option is explicitly empty
    }
    $font_lines = array_filter( array_map( 'trim', explode( "\n", $fonts_string ) ) );
    $font_families = array();
    foreach ( $font_lines as $line ) {
        // Extract only the font family name (e.g., "Roboto" from "Roboto:wght@400;700")
        $font_families[] = strtok( $line, ':' );
    }
    $unique_families = array_values( array_unique( $font_families ) );
    return !empty($unique_families) ? $unique_families : array('Arial'); // Ensure always returns a populated array
}

/**
 * Get the URL for enqueuing Google Fonts based on admin settings.
 * @return string|false The Google Fonts API URL, or false if no valid fonts are defined.
 */
function plate_customizer_get_google_fonts_url() {
    $google_fonts_string = get_option( 'plate_customizer_google_fonts' );
    if ( empty( trim( $google_fonts_string ) ) ) {
        return false;
    }

    $font_families_to_enqueue = array_filter( array_map( 'trim', explode( "\n", $google_fonts_string ) ) );
    if ( empty( $font_families_to_enqueue ) ) {
        return false;
    }

    $url_param_parts = array();
    foreach ($font_families_to_enqueue as $font_family_with_variants) {
        $parts = explode(':', $font_family_with_variants, 2); // Limit to 2 parts
        $family_name = str_replace(' ', '+', trim($parts[0]));
        if ( !empty($family_name) ) { // Ensure family name is not empty
            if (isset($parts[1]) && !empty(trim($parts[1]))) {
                $url_param_parts[] = $family_name . ':' . trim($parts[1]);
            } else {
                $url_param_parts[] = $family_name;
            }
        }
    }
    if (empty($url_param_parts)) return false;

    return 'https://fonts.googleapis.com/css?family=' . esc_attr( implode( '|', $url_param_parts ) ) . '&display=swap';
}

/**
 * Get configured LED colors.
 * @return array An array of LED color objects.
 */
function plate_customizer_get_led_colors() {
    $default_led_colors = array(
        array( 'label' => __( 'Warm White (2700K)', 'plate-customizer' ), 'color' => '#FFDAB9' ),
        array( 'label' => __( 'Cool White (5000K)', 'plate-customizer' ), 'color' => '#E0FFFF' ),
    );
    // Get option, provide default if not set
    $led_colors = get_option( 'plate_customizer_led_colors', $default_led_colors );

    // If the retrieved option is not an array or is empty, return default
    if ( !is_array( $led_colors ) || empty( $led_colors ) ) {
        return $default_led_colors;
    }

    $valid_colors = array();
    foreach ( $led_colors as $lc ) {
        // Validate each item structure and content
        if ( is_array( $lc ) && isset( $lc['label'], $lc['color'] ) && !empty(trim($lc['label'])) && !empty(trim($lc['color'])) ) {
            $valid_colors[] = array(
                'label' => sanitize_text_field( $lc['label'] ),
                'color' => sanitize_hex_color( $lc['color'] ) // Ensure valid hex
            );
        }
    }
    // If after validation no valid colors remain, return default; otherwise, return validated colors.
    return !empty( $valid_colors ) ? $valid_colors : $default_led_colors;
}

/**
 * Get all published plate templates with their relevant meta data.
 * @return array Array of plate template data.
 */
function plate_customizer_get_all_templates_data() {
    $transient_key = 'plate_customizer_templates_data_v1'; // Versioned transient key for easier invalidation
    $templates_data = get_transient( $transient_key );

    if ( false === $templates_data ) {
        $templates_data = array();
        $args = array(
            'post_type'      => PLATE_CUSTOMIZER_CPT_SLUG, // Use defined constant
            'posts_per_page' => -1,
            'post_status'    => 'publish', // Only fetch published templates
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        $plate_query = new WP_Query( $args );

        if ( $plate_query->have_posts() ) {
            while ( $plate_query->have_posts() ) {
                $plate_query->the_post();
                $post_id = get_the_ID();
                $templates_data[] = array(
                    'id'             => $post_id,
                    'title'          => get_the_title(),
                    'sizes'          => get_post_meta( $post_id, '_plate_sizes', true ) ?: array(), // Default to empty array
                    'bg_colors'      => get_post_meta( $post_id, '_plate_bg_colors', true ) ?: array(),
                    'number_sizes'   => get_post_meta( $post_id, '_plate_number_sizes', true ) ?: array(),
                    'max_chars'      => (int) get_post_meta( $post_id, '_plate_max_chars', true ) ?: 5, // Default and cast to int
                );
            }
            wp_reset_postdata();
        }
        // Cache the result for 6 hours
        set_transient( $transient_key, $templates_data, 6 * HOUR_IN_SECONDS );
    }
    return $templates_data;
}

/**
 * Clear the plate templates data transient when a plate template is saved or deleted.
 */
function plate_customizer_clear_templates_cache_hook( $post_id = 0, $post = null, $update = false ) {
    // Check if the post type matches our CPT. $post_id === 0 can be a general call.
    if ( ($post && PLATE_CUSTOMIZER_CPT_SLUG === $post->post_type) ) {
        delete_transient( 'plate_customizer_templates_data_v1' );
    }
}
// Hook for when a specific CPT is saved
add_action( 'save_post_' . PLATE_CUSTOMIZER_CPT_SLUG, 'plate_customizer_clear_templates_cache_hook', 10, 3 );
add_action( 'delete_post', function( $post_id ) {
    // Check post type before deleting transient
    if ( get_post_type( $post_id ) === PLATE_CUSTOMIZER_CPT_SLUG ) {
        delete_transient( 'plate_customizer_templates_data_v1' );
    }
});


/**
 * Sanitize an array of items (repeater field data).
 * @param array $data_array The array of items to sanitize.
 * @param array $field_definitions Associative array where keys are field names, values are sanitize functions.
 * @return array The sanitized array of items.
 */
function plate_customizer_sanitize_repeater_items( $data_array, $field_definitions ) {
    if ( ! is_array( $data_array ) ) {
        return array();
    }
    $sanitized_items_array = array();
    foreach ( $data_array as $item_values ) {
        if ( ! is_array( $item_values ) ) continue;

        $sanitized_item = array();
        $is_item_valid = false; // Flag to check if at least one field in the item has a value
        foreach ( $field_definitions as $field_key => $sanitize_callback ) {
            $value_to_sanitize = isset( $item_values[ $field_key ] ) ? $item_values[ $field_key ] : null;

            if ( is_callable( $sanitize_callback ) ) {
                $sanitized_value = call_user_func( $sanitize_callback, $value_to_sanitize );
            } else {
                // Default to sanitize_text_field if callback is not callable or not specified
                $sanitized_value = sanitize_text_field( $value_to_sanitize );
            }
            $sanitized_item[ $field_key ] = $sanitized_value;

            // Check if the sanitized value is considered non-empty
            // Allows 0, '0' but not null, false, empty string
            if ( $sanitized_value !== null && $sanitized_value !== false && $sanitized_value !== '' ) {
                $is_item_valid = true;
            }
        }
        // Only add the item if it has at least one non-empty, non-null, non-false value after sanitization
        if ( $is_item_valid ) {
            $sanitized_items_array[] = $sanitized_item;
        }
    }
    return $sanitized_items_array;
}