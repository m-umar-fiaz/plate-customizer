<?php
class Plate_Customizer_Admin {

    public function __construct() {
        add_action( 'init', array( $this, 'register_plate_template_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_plate_template_meta_boxes' ) );
        add_action( 'save_post_plate_template', array( $this, 'save_plate_template_meta_data' ) ); // Note post type in hook
        add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function enqueue_admin_assets($hook) {
        // Enqueue only on specific admin pages
        if ( 'post.php' == $hook || 'post-new.php' == $hook || strpos($hook, 'plate-customizer-settings') !== false ) {
            wp_enqueue_style( 'plate-customizer-admin-style', PLATE_CUSTOMIZER_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), PLATE_CUSTOMIZER_VERSION );
            wp_enqueue_script( 'plate-customizer-admin-script', PLATE_CUSTOMIZER_PLUGIN_URL . 'admin/assets/js/admin-script.js', array('jquery', 'wp-color-picker'), PLATE_CUSTOMIZER_VERSION, true );
            wp_enqueue_media(); // For image uploads
            wp_enqueue_style( 'wp-color-picker' );
        }
    }

    public function register_plate_template_cpt() {
        $labels = array(
            'name'               => _x( 'Plate Templates', 'post type general name', 'plate-customizer' ),
            'singular_name'      => _x( 'Plate Template', 'post type singular name', 'plate-customizer' ),
            // ... other labels
            'add_new_item'       => __( 'Add New Plate Template', 'plate-customizer' ),
            'edit_item'          => __( 'Edit Plate Template', 'plate-customizer' ),
        );
        $args = array(
            'labels'             => $labels,
            'public'             => false, // Not publicly queryable on frontend, managed via plugin
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'plate-template' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-art',
            'supports'           => array( 'title' ),
        );
        register_post_type( 'plate_template', $args );
    }

    public function add_plate_template_meta_boxes() {
        add_meta_box(
            'plate_template_options',
            __( 'Plate Template Options', 'plate-customizer' ),
            array( $this, 'render_plate_template_meta_box_content' ),
            'plate_template', // CPT slug
            'normal',
            'high'
        );
    }

    public function render_plate_template_meta_box_content( $post ) {
        wp_nonce_field( 'plate_template_meta_box', 'plate_template_meta_box_nonce' );

        // Get saved values
        $sizes = get_post_meta( $post->ID, '_plate_sizes', true );
        $bg_colors = get_post_meta( $post->ID, '_plate_bg_colors', true );
        $number_sizes = get_post_meta( $post->ID, '_plate_number_sizes', true );
        $max_chars = get_post_meta( $post->ID, '_plate_max_chars', true );
        // ... other meta fields

        // For simplicity, I'm using basic HTML. For complex repeaters, consider CMB2 or custom JS.
        ?>
        <div id="plate-options-wrapper">
            <h4><?php _e('Plate Sizes (Width x Height in inches)', 'plate-customizer'); ?></h4>
            <div id="plate-sizes-repeater">
                <?php
                if ( !empty($sizes) && is_array($sizes) ) {
                    foreach ($sizes as $index => $size) : ?>
                        <div class="repeater-item">
                            <input type="number" step="0.1" name="plate_sizes[<?php echo $index; ?>][width]" value="<?php echo esc_attr($size['width']); ?>" placeholder="Width">
                            x
                            <input type="number" step="0.1" name="plate_sizes[<?php echo $index; ?>][height]" value="<?php echo esc_attr($size['height']); ?>" placeholder="Height">
                            Swatch Image: <input type="text" name="plate_sizes[<?php echo $index; ?>][swatch_url]" value="<?php echo esc_url($size['swatch_url']); ?>" class="image-upload-url">
                            <button type="button" class="button upload-image-button">Upload</button>
                            <button type="button" class="button remove-repeater-item">Remove</button>
                        </div>
                    <?php endforeach;
                }
                ?>
            </div>
            <button type="button" id="add-plate-size" class="button">Add Size</button>

            <h4><?php _e('Background Colors (Image Swatches or Color Hex)', 'plate-customizer'); ?></h4>
            <div id="plate-bg-colors-repeater">
                 <?php /* Similar repeater structure for bg_colors (name, hex, swatch_url) */ ?>
            </div>
            <button type="button" id="add-bg-color" class="button">Add Background Color</button>

            <h4><?php _e('Number Sizes (e.g., 2 inch, 5 inch - label & rendering value)', 'plate-customizer'); ?></h4>
             <div id="plate-number-sizes-repeater">
                 <?php /* Similar repeater structure for number_sizes (label, value_for_render) */ ?>
            </div>
            <button type="button" id="add-number-size" class="button">Add Number Size</button>

            <p>
                <label for="plate_max_chars"><?php _e('Max Number Digits Allowed for this plate type:', 'plate-customizer'); ?></label>
                <input type="number" id="plate_max_chars" name="plate_max_chars" value="<?php echo esc_attr($max_chars); ?>" min="2" max="10" />
            </p>
        </div>
        <script>
            // Basic jQuery for repeater fields (needs to be in admin-script.js for production)
            jQuery(document).ready(function($){
                // Image uploader
                $('body').on('click', '.upload-image-button', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var inputField = button.siblings('.image-upload-url');
                    var frame = wp.media({
                        title: 'Select or Upload Swatch',
                        button: { text: 'Use this swatch' },
                        multiple: false
                    });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        inputField.val(attachment.url);
                    });
                    frame.open();
                });

                function addRepeaterItem(containerSelector, namePrefix) {
                    var container = $(containerSelector);
                    var index = container.find('.repeater-item').length;
                    var newItemHtml = '<div class="repeater-item">';
                    if (namePrefix === 'plate_sizes') {
                        newItemHtml += `<input type="number" step="0.1" name="${namePrefix}[${index}][width]" placeholder="Width"> x <input type="number" step="0.1" name="${namePrefix}[${index}][height]" placeholder="Height"> Swatch Image: <input type="text" name="${namePrefix}[${index}][swatch_url]" class="image-upload-url"> <button type="button" class="button upload-image-button">Upload</button>`;
                    } else if (namePrefix === 'plate_bg_colors') {
                         newItemHtml += `<input type="text" name="${namePrefix}[${index}][name]" placeholder="Color Name"> <input type="text" name="${namePrefix}[${index}][hex]" class="color-picker" placeholder="Hex Value"> Swatch Image: <input type="text" name="${namePrefix}[${index}][swatch_url]" class="image-upload-url"> <button type="button" class="button upload-image-button">Upload</button>`;
                    } else if (namePrefix === 'plate_number_sizes') {
                         newItemHtml += `<input type="text" name="${namePrefix}[${index}][label]" placeholder="Label (e.g. 7 inch)"> <input type="number" step="0.1" name="${namePrefix}[${index}][value]" placeholder="Render Value (px or relative)">`;
                    }
                    newItemHtml += ' <button type="button" class="button remove-repeater-item">Remove</button></div>';
                    container.append(newItemHtml);
                    if (namePrefix === 'plate_bg_colors') {
                        container.find('.color-picker:last').wpColorPicker();
                    }
                }

                $('#add-plate-size').on('click', function(){ addRepeaterItem('#plate-sizes-repeater', 'plate_sizes'); });
                $('#add-bg-color').on('click', function(){ addRepeaterItem('#plate-bg-colors-repeater', 'plate_bg_colors'); });
                $('#add-number-size').on('click', function(){ addRepeaterItem('#plate-number-sizes-repeater', 'plate_number_sizes'); });

                $('body').on('click', '.remove-repeater-item', function(){ $(this).closest('.repeater-item').remove(); });
                $('.color-picker').wpColorPicker(); // Initialize existing color pickers
            });
        </script>
        <?php
    }

    public function save_plate_template_meta_data( $post_id ) {
        if ( ! isset( $_POST['plate_template_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['plate_template_meta_box_nonce'], 'plate_template_meta_box' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitize and save repeater fields
        $fields_to_save = [
            '_plate_sizes' => 'plate_sizes',
            '_plate_bg_colors' => 'plate_bg_colors',
            '_plate_number_sizes' => 'plate_number_sizes',
        ];

        foreach ($fields_to_save as $meta_key => $post_key) {
            if ( isset( $_POST[$post_key] ) && is_array($_POST[$post_key]) ) {
                $sanitized_data = array();
                foreach ($_POST[$post_key] as $item) {
                    $sanitized_item = array();
                    if ($post_key === 'plate_sizes') {
                        $sanitized_item['width'] = isset($item['width']) ? floatval($item['width']) : 0;
                        $sanitized_item['height'] = isset($item['height']) ? floatval($item['height']) : 0;
                        $sanitized_item['swatch_url'] = isset($item['swatch_url']) ? esc_url_raw($item['swatch_url']) : '';
                    } elseif ($post_key === 'plate_bg_colors') {
                        $sanitized_item['name'] = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                        $sanitized_item['hex'] = isset($item['hex']) ? sanitize_hex_color($item['hex']) : '';
                        $sanitized_item['swatch_url'] = isset($item['swatch_url']) ? esc_url_raw($item['swatch_url']) : '';
                    } elseif ($post_key === 'plate_number_sizes') {
                         $sanitized_item['label'] = isset($item['label']) ? sanitize_text_field($item['label']) : '';
                         $sanitized_item['value'] = isset($item['value']) ? floatval($item['value']) : 0;
                    }
                    // Add more specific sanitization if needed
                    if (!empty(array_filter($sanitized_item))) { // Only add if not all empty
                        $sanitized_data[] = $sanitized_item;
                    }
                }
                update_post_meta( $post_id, $meta_key, $sanitized_data );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }


        if ( isset( $_POST['plate_max_chars'] ) ) {
            update_post_meta( $post_id, '_plate_max_chars', intval($_POST['plate_max_chars']) );
        }
        // ... save other meta fields
    }

    public function add_admin_menu_page() {
        add_submenu_page(
            'edit.php?post_type=plate_template', // Parent slug (Plate Templates CPT)
            __( 'Plate Customizer Settings', 'plate-customizer' ),
            __( 'Settings', 'plate-customizer' ),
            'manage_options',
            'plate-customizer-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'plate_customizer_settings_group', 'plate_customizer_admin_email' );
        register_setting( 'plate_customizer_settings_group', 'plate_customizer_google_fonts', array( 'sanitize_callback' => array($this, 'sanitize_google_fonts') ) );
        register_setting( 'plate_customizer_settings_group', 'plate_customizer_led_colors' ); // Store as serialized array

        add_settings_section(
            'plate_customizer_general_section',
            __( 'General Settings', 'plate-customizer' ),
            null,
            'plate-customizer-settings'
        );

        add_settings_field(
            'plate_customizer_admin_email',
            __( 'Admin Email for Notifications', 'plate-customizer' ),
            array( $this, 'render_admin_email_field' ),
            'plate-customizer-settings',
            'plate_customizer_general_section'
        );

        add_settings_field(
            'plate_customizer_google_fonts',
            __( 'Google Fonts (one per line, e.g., Roboto, Open Sans:wght@400;700)', 'plate-customizer' ),
            array( $this, 'render_google_fonts_field' ),
            'plate-customizer-settings',
            'plate_customizer_general_section'
        );

        add_settings_field(
            'plate_customizer_led_colors',
            __( 'LED Light Colors for Numbers/Text', 'plate-customizer' ),
            array( $this, 'render_led_colors_field' ),
            'plate-customizer-settings',
            'plate_customizer_general_section'
        );
    }

    public function sanitize_google_fonts($input) {
        $lines = explode("\n", $input);
        $sanitized_lines = array();
        foreach ($lines as $line) {
            $sanitized_lines[] = sanitize_text_field(trim($line));
        }
        return implode("\n", array_filter($sanitized_lines)); // Remove empty lines
    }

    public function render_admin_email_field() {
        $email = get_option( 'plate_customizer_admin_email', get_option('admin_email') );
        echo '<input type="email" name="plate_customizer_admin_email" value="' . esc_attr( $email ) . '" class="regular-text">';
    }

    public function render_google_fonts_field() {
        $fonts = get_option( 'plate_customizer_google_fonts', "Roboto\nOpen Sans\nLato" );
        echo '<textarea name="plate_customizer_google_fonts" rows="5" cols="50" class="large-text code">' . esc_textarea( $fonts ) . '</textarea>';
    }

    public function render_led_colors_field() {
        $led_colors = get_option('plate_customizer_led_colors', array(
            array('label' => 'Warm White (2700K)', 'color' => '#FFDAB9'),
            array('label' => 'Cool White (5000K)', 'color' => '#E0FFFF'),
            array('label' => 'Blue', 'color' => '#0000FF'),
        ));
        // This needs a repeater field interface in admin-script.js
        echo '<div id="led-colors-repeater-settings">';
        if (is_array($led_colors)) {
            foreach ($led_colors as $index => $lc) {
                echo '<div class="repeater-item">';
                echo '<input type="text" name="plate_customizer_led_colors['.$index.'][label]" value="'.esc_attr($lc['label']).'" placeholder="Label (e.g. 2700K)"> ';
                echo '<input type="text" name="plate_customizer_led_colors['.$index.'][color]" value="'.esc_attr($lc['color']).'" class="color-picker" placeholder="Color Hex">';
                echo ' <button type="button" class="button remove-repeater-item">Remove</button>';
                echo '</div>';
            }
        }
        echo '</div>';
        echo '<button type="button" id="add-led-color-setting" class="button">Add LED Color</button>';
        // Add JS in admin-script.js to handle adding/removing these items and initializing color pickers
    }


    public function render_settings_page() {
        // Located in admin/views/settings-page.php
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once PLATE_CUSTOMIZER_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}