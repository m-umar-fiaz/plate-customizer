<?php
class Plate_Customizer_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_send_plate_design', array( $this, 'handle_send_plate_design' ) );
        add_action( 'wp_ajax_nopriv_send_plate_design', array( $this, 'handle_send_plate_design' ) ); // For non-logged-in users
    }

    public function handle_send_plate_design() {
        check_ajax_referer( 'plate_customizer_ajax_nonce', 'nonce' );

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $image_data_base64 = isset($_POST['image_data']) ? $_POST['image_data'] : '';
        $customizations_json = isset($_POST['customizations']) ? stripslashes($_POST['customizations']) : '{}';

        if ( empty($name) || empty($email) || !is_email($email) || empty($image_data_base64) ) {
            wp_send_json_error( array('message' => __('Invalid data provided.', 'plate-customizer')) );
        }

        $admin_email = get_option( 'plate_customizer_admin_email', get_option('admin_email') );
        if ( !is_email($admin_email) ) {
            wp_send_json_error( array('message' => __('Admin email not configured correctly.', 'plate-customizer')) );
        }

        $subject = sprintf( __('New Plate Design Submission from %s', 'plate-customizer'), $name );
        $headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $name . ' <' . $email . '>');

        // Prepare email body
        $customizations = json_decode($customizations_json, true);
        $body = "<p>A new plate design has been submitted:</p>";
        $body .= "<ul>";
        $body .= "<li><strong>Name:</strong> " . esc_html($name) . "</li>";
        $body .= "<li><strong>Email:</strong> " . esc_html($email) . "</li>";
        $body .= "</ul>";

        if ($customizations && is_array($customizations)) {
            $body .= "<h3>Customization Details:</h3><ul>";
            if(isset($customizations['plateId']) && $customizations['plateId']) {
                 $template_title = get_the_title($customizations['plateId']);
                 $body .= "<li><strong>Template:</strong> " . esc_html($template_title) . "</li>";
            }
            if(isset($customizations['size'])) $body .= "<li><strong>Size:</strong> " . esc_html($customizations['size']['width'] . '" x ' . $customizations['size']['height'].'"') . "</li>";
            if(isset($customizations['orientation'])) $body .= "<li><strong>Orientation:</strong> " . esc_html(ucfirst($customizations['orientation'])) . "</li>";
            if(isset($customizations['withAddress'])) $body .= "<li><strong>With Address:</strong> " . ($customizations['withAddress'] ? 'Yes' : 'No') . "</li>";
            if(isset($customizations['bgColor'])) $body .= "<li><strong>Background:</strong> " . esc_html($customizations['bgColor']['name'] ?: $customizations['bgColor']['hex']) . "</li>";
            if(isset($customizations['numberSize'])) $body .= "<li><strong>Number Size:</strong> " . esc_html($customizations['numberSize']['label']) . "</li>";
            if(isset($customizations['numberInput'])) $body .= "<li><strong>Number:</strong> " . esc_html($customizations['numberInput']) . "</li>";
            if(isset($customizations['addressInput']) && $customizations['withAddress']) $body .= "<li><strong>Address:</strong> " . esc_html($customizations['addressInput']) . "</li>";
            if(isset($customizations['ledColor'])) $body .= "<li><strong>LED Color:</strong> " . esc_html($customizations['ledColor']['label']) . "</li>";
            if(isset($customizations['fontStyle'])) $body .= "<li><strong>Font:</strong> " . esc_html($customizations['fontStyle']) . "</li>";
            $body .= "</ul>";
        }

        $body .= "<p>See attached image for the design.</p>";


        // Handle image attachment
        // Remove 'data:image/png;base64,' from string
        $image_data = str_replace('data:image/png;base64,', '', $image_data_base64);
        $image_data = str_replace(' ', '+', $image_data); // Replace spaces with + if any
        $decoded_image = base64_decode($image_data);

        if ($decoded_image === false) {
            wp_send_json_error( array('message' => __('Failed to decode image data.', 'plate-customizer')) );
        }

        // Save to a temporary file to attach
        $upload_dir = wp_upload_dir();
        $tmp_filename = wp_unique_filename( $upload_dir['path'], 'plate-design-' . time() . '.png' );
        $tmp_filepath = $upload_dir['path'] . '/' . $tmp_filename;

        if ( wp_put_contents( $tmp_filepath, $decoded_image ) === false ) {
            wp_send_json_error( array('message' => __('Failed to save temporary image file.', 'plate-customizer')) );
        }

        $attachments = array( $tmp_filepath );

        if ( wp_mail( $admin_email, $subject, $body, $headers, $attachments ) ) {
            unlink( $tmp_filepath ); // Delete temporary file
            wp_send_json_success( array('message' => __('Design sent successfully!', 'plate-customizer')) );
        } else {
            unlink( $tmp_filepath ); // Delete temporary file even on failure
            wp_send_json_error( array('message' => __('Failed to send email.', 'plate-customizer')) );
        }
    }
}