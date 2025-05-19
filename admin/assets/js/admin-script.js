// (Code from meta box render function for repeaters)
// ... plus ...
jQuery(document).ready(function($) {
    // For LED Colors in Settings
    function addLedColorSettingItem() {
        var container = $('#led-colors-repeater-settings');
        var index = container.find('.repeater-item').length;
        var newItemHtml = `<div class="repeater-item">
            <input type="text" name="plate_customizer_led_colors[${index}][label]" placeholder="Label (e.g. 2700K)">
            <input type="text" name="plate_customizer_led_colors[${index}][color]" class="color-picker" placeholder="Color Hex">
            <button type="button" class="button remove-repeater-item">Remove</button>
        </div>`;
        container.append(newItemHtml);
        container.find('.color-picker:last').wpColorPicker();
    }

    $('#add-led-color-setting').on('click', function() {
        addLedColorSettingItem();
    });

    // Ensure dynamic color pickers are initialized
    $('body').on('focus', '.color-picker', function(){
        if (!$(this).data('wpWpColorPicker')) { // Check if already initialized
            $(this).wpColorPicker();
        }
    });
});