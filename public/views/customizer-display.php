<div id="plate-customizer-app" class="plate-customizer-app-wrapper" style="visibility: hidden;"> <?php // Initially hidden, shown by JS to prevent FOUC ?>
    <div class="plate-customizer-sidebar">
        <h3><?php esc_html_e('Customize Your Plate', 'plate-customizer'); ?></h3>

        <div class="control-group">
            <label for="plate-template-select"><?php esc_html_e('1. Choose Plate Template:', 'plate-customizer'); ?></label>
            <select id="plate-template-select" disabled> <?php // Disabled initially, enabled by JS ?>
                <?php /* Option populated by JS using plateCustomizerData.options.i18n.loadingTemplates */ ?>
            </select>
        </div>

        <div id="plate-options-panel" style="display:none;">
            <div class="control-group">
                <label><?php esc_html_e('2. Plate Size:', 'plate-customizer'); ?></label>
                <div id="plate-size-swatches" class="swatch-group"></div>
            </div>

            <div class="control-group">
                <label><?php esc_html_e('3. Plate Style:', 'plate-customizer'); ?></label>
                <select id="plate-orientation-style">
                    <option value="horizontal"><?php esc_html_e('Horizontal', 'plate-customizer'); ?></option>
                    <option value="vertical"><?php esc_html_e('Vertical', 'plate-customizer'); ?></option>
                </select>
                <label class="inline-checkbox"><input type="checkbox" id="plate-with-address"> <?php esc_html_e('With Address', 'plate-customizer'); ?></label>
            </div>

            <div class="control-group">
                <label><?php esc_html_e('4. Background Color/Style:', 'plate-customizer'); ?></label>
                <div id="plate-bg-color-swatches" class="swatch-group"></div>
            </div>

            <div class="control-group">
                <label><?php esc_html_e('5. Number Size:', 'plate-customizer'); ?></label>
                <div id="plate-number-size-swatches" class="swatch-group"></div>
            </div>

            <div class="control-group">
                <label for="plate-number-input"><?php esc_html_e('6. Enter Number:', 'plate-customizer'); ?></label>
                <input type="text" id="plate-number-input" inputmode="numeric" pattern="[0-9]*" placeholder="<?php esc_attr_e('e.g. 12345', 'plate-customizer'); ?>">
                <small id="plate-number-char-limit"></small>
            </div>

            <div class="control-group" id="plate-address-group" style="display:none;">
                <label for="plate-address-input"><?php esc_html_e('7. Address Text:', 'plate-customizer'); ?></label>
                <textarea id="plate-address-input" rows="2" placeholder="<?php esc_attr_e('e.g. 123 Main Street', 'plate-customizer'); ?>"></textarea>
            </div>

            <div class="control-group">
                <label><?php esc_html_e('8. LED Light Color:', 'plate-customizer'); ?></label>
                <div id="plate-led-color-swatches" class="swatch-group"></div>
            </div>

            <div class="control-group">
                <label for="plate-font-style"><?php esc_html_e('9. Font Style:', 'plate-customizer'); ?></label>
                <select id="plate-font-style"></select>
            </div>

             <div class="control-group download-buttons">
                <button type="button" id="download-plate-png" class="button"><?php esc_html_e('Download PNG', 'plate-customizer'); ?></button>
                <button type="button" id="download-plate-jpg" class="button"><?php esc_html_e('Download JPG', 'plate-customizer'); ?></button>
            </div>
        </div>
    </div>

    <div class="plate-customizer-preview-area">
        <canvas id="plate-preview-canvas" width="600" height="400">
            <?php esc_html_e('Your browser does not support the HTML5 canvas tag. Please update your browser.', 'plate-customizer'); ?>
        </canvas>
        <div id="plate-preview-summary">
            <h4><?php esc_html_e('Current Selections:', 'plate-customizer'); ?></h4>
            <ul id="current-options-list"></ul>
        </div>
    </div>

    <div class="plate-customizer-send-form">
        <h3><?php esc_html_e('Send Your Design', 'plate-customizer'); ?></h3>
        <form id="send-plate-design-form" novalidate> <?php // novalidate to rely on JS validation ?>
            <p>
                <label for="user-name"><?php esc_html_e('Name:', 'plate-customizer'); ?></label>
                <input type="text" id="user-name" name="user_name" required aria-required="true">
            </p>
            <p>
                <label for="user-email"><?php esc_html_e('Email:', 'plate-customizer'); ?></label>
                <input type="email" id="user-email" name="user_email" required aria-required="true">
            </p>
            <button type="submit" id="send-design-button" class="button-primary"><?php esc_html_e('Send to Admin', 'plate-customizer'); ?></button>
            <div id="send-form-message" role="alert" aria-live="polite"></div>
        </form>
    </div>

    <?php // Loading overlay, initially hidden by JS or CSS ?>
    <div id="plate-customizer-loading-overlay" class="plate-customizer-loading-overlay" style="display:none;">
        <p><?php esc_html_e('Loading Customizer...', 'plate-customizer'); ?></p>
        <?php // You can add a spinner SVG or CSS animation here ?>
    </div>
</div>