<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'plate_customizer_settings_group' ); // Matches register_setting group name
        do_settings_sections( 'plate-customizer-settings' );  // Matches add_settings_section page slug
        submit_button( __( 'Save Settings', 'plate-customizer' ) );
        ?>
    </form>
</div>