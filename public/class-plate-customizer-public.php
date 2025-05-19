<?php
class Plate_Customizer_Public {

    private $assets_enqueued = false; // Flag to prevent multiple enqueues

    public function __construct() {
        // Hook to enqueue assets. It will check if shortcode is present.
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_public_assets' ) );
        // Register the shortcode
        add_shortcode( 'plate_customizer', array( $this, 'render_customizer_shortcode' ) );
    }

    /**
     * Checks if assets should be enqueued (i.e., if the shortcode is on the page)
     * and then calls the actual enqueue method.
     */
    public function maybe_enqueue_public_assets() {
        global $post; // WordPress global post object

        // Proceed only if assets haven't been enqueued yet, $post is a valid WP_Post object,
        // and the post content contains our shortcode.
        if ( $this->assets_enqueued || !is_a( $post, 'WP_Post' ) || !has_shortcode( $post->post_content, 'plate_customizer' ) ) {
            return;
        }
        $this->enqueue_all_public_assets();
        $this->assets_enqueued = true;
    }

    /**
     * Enqueues all public-facing scripts and styles and localizes data.
     */
    private function enqueue_all_public_assets() {
        wp_enqueue_style(
            'plate-customizer-public-style',
            PLATE_CUSTOMIZER_PLUGIN_URL . 'public/assets/css/public-style.css',
            array(),
            PLATE_CUSTOMIZER_VERSION
        );

        $google_fonts_url = plate_customizer_get_google_fonts_url(); // Get URL from helper
        if ( $google_fonts_url ) {
            wp_enqueue_style( 'plate-customizer-google-fonts', $google_fonts_url, array(), null );
        }

        wp_enqueue_script(
            'plate-customizer-public-script', // Script handle
            PLATE_CUSTOMIZER_PLUGIN_URL . 'public/assets/js/public-script.js',
            array( 'jquery' ), // Dependencies
            PLATE_CUSTOMIZER_VERSION,
            true // Load in footer
        );

        // Prepare data for JavaScript
        $plate_templates_data = plate_customizer_get_all_templates_data();
        $global_options = array(
            'led_colors'        => plate_customizer_get_led_colors(),
            'google_fonts_list' => plate_customizer_get_google_font_families(),
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'plate_customizer_ajax_nonce' ),
            'i18n'              => array( // Translatable strings for JS
                'selectTemplate' => __( '-- Select a Template --', 'plate-customizer' ),
                'noTemplates'    => __( 'No templates available', 'plate-customizer' ),
                'loadingTemplates' => __( '-- Loading Templates --', 'plate-customizer' ),
                'errorDataLoad'  => __( 'Error: Customizer data not loaded. Please check plugin configuration.', 'plate-customizer' ),
                'noSizes'        => __( 'No sizes defined for this template.', 'plate-customizer'),
                'noBgColors'     => __( 'No background colors defined for this template.', 'plate-customizer'),
                'noNumberSizes'  => __( 'No number sizes defined for this template.', 'plate-customizer'),
                'noLedColors'    => __( 'No LED colors configured.', 'plate-customizer'),
                'canvasError'    => __( 'Canvas Error: Preview not available.', 'plate-customizer'),
                'criticalDataMissing' => __( 'Critical data missing for customizer.', 'plate-customizer'),
                'nameRequired'   => __( 'Name is required.', 'plate-customizer' ),
                'emailRequired'  => __( 'Email is required.', 'plate-customizer' ),
                'validEmailRequired' => __( 'Please enter a valid email address.', 'plate-customizer' ),
                'plateRequired'  => __( 'Please select and customize a plate first.', 'plate-customizer' ),
                'sending'        => __( 'Sending...', 'plate-customizer' ),
                'designSent'     => __( 'Design sent successfully!', 'plate-customizer' ),
                'errorPrefix'    => __( 'Error: ', 'plate-customizer' ),
                'ajaxErrorPrefix'=> __( 'AJAX Error: ', 'plate-customizer' ),
                'unknownError'   => __( 'Unknown error occurred.', 'plate-customizer' ),
            ),
        );

        $data_to_localize = array(
            'templates' => $plate_templates_data,
            'options'   => $global_options,
        );

        // Pass data to the enqueued script
        wp_localize_script( 'plate-customizer-public-script', 'plateCustomizerData', $data_to_localize );
    }

    /**
     * Renders the shortcode output.
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the shortcode.
     */
    public function render_customizer_shortcode( $atts ) {
        // Attempt to enqueue assets if not already done. This is a fallback.
        if (!$this->assets_enqueued) {
            // We can't use has_shortcode here as $post might not be fully set if shortcode
            // is in a widget or other non-post context. So, we just try to enqueue.
            // The enqueue_all_public_assets method itself should be efficient.
            $this->enqueue_all_public_assets();
            $this->assets_enqueued = true; // Mark as attempted/done
        }
        
        ob_start(); // Start output buffering
        // Include the display template
        $template_path = PLATE_CUSTOMIZER_PLUGIN_DIR . 'public/views/customizer-display.php';
        if ( file_exists( $template_path ) ) {
            require $template_path;
        } else {
            // Fallback message if template file is missing
            echo '<p>' . esc_html__( 'Plate customizer display template not found.', 'plate-customizer' ) . '</p>';
        }
        return ob_get_clean(); // Return buffered content
    }
}