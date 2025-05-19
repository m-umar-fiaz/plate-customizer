jQuery(document).ready(function($) {
    'use strict'; // Enable strict mode

    const $app = $('#plate-customizer-app');
    const $loadingOverlay = $('#plate-customizer-loading-overlay'); // Assuming you have this element

    if (!$app.length) {
        console.warn('Plate Customizer: App container #plate-customizer-app not found.');
        return; // Customizer not on page
    }

    // --- Configuration & State ---
    const config = { // Renamed to const as it's not meant to change after init
        dpi: 72,
        canvasPadding: 20,
        defaultFont: 'Arial',
    };
    let i18n = {}; // To store localized strings for easier access

    let currentTemplate = null;
    let currentSettings = {};

    const $canvas = $('#plate-preview-canvas');
    let ctx = null;

    if ($canvas.length) {
        ctx = $canvas[0].getContext('2d');
    } else {
        console.error('Plate Customizer: Canvas element #plate-preview-canvas not found!');
        // Try to get i18n string if plateCustomizerData is partially available
        const errorMsg = (typeof plateCustomizerData !== 'undefined' && plateCustomizerData.options && plateCustomizerData.options.i18n && plateCustomizerData.options.i18n.canvasError)
                       ? plateCustomizerData.options.i18n.canvasError
                       : 'Canvas Error: Preview not available.';
        $app.html(`<p style="color:red;">${errorMsg}</p>`).css('visibility', 'visible');
        return;
    }

    // --- Helper Functions ---
    const inchToPx = (inches) => parseFloat(inches) * config.dpi;
    const clearCanvas = () => {
        if (ctx) ctx.clearRect(0, 0, $canvas[0].width, $canvas[0].height);
    };

    // --- Initialization ---
    function initializeCustomizer() {
        if (typeof plateCustomizerData === 'undefined' || !plateCustomizerData || !plateCustomizerData.options || !plateCustomizerData.options.i18n || !plateCustomizerData.templates) {
            console.error('Plate Customizer: Essential data (plateCustomizerData, options, i18n, or templates) is missing.');
            const errorMsg = (typeof plateCustomizerData !== 'undefined' && plateCustomizerData.options && plateCustomizerData.options.i18n && plateCustomizerData.options.i18n.criticalDataMissing)
                           ? plateCustomizerData.options.i18n.criticalDataMissing
                           : 'Critical data missing for customizer. Please contact support.';
            $app.html(`<p style="color:red;">${errorMsg}</p>`).css('visibility', 'visible');
            $loadingOverlay.hide();
            return;
        }
        i18n = plateCustomizerData.options.i18n; // Store i18n strings

        resetCurrentSettings();
        populateTemplateSelector();
        populateGlobalOptions();
        bindEvents();

        $app.css('visibility', 'visible'); // Show the app
        $loadingOverlay.hide();
        updatePreviewSummary();
    }

    function resetCurrentSettings(template = null) {
        const defaultLed = (plateCustomizerData.options.led_colors && plateCustomizerData.options.led_colors.length > 0)
                         ? plateCustomizerData.options.led_colors[0]
                         : { label: 'Default', color: '#FFFFFF' }; // Fallback
        const defaultFont = (plateCustomizerData.options.google_fonts_list && plateCustomizerData.options.google_fonts_list.length > 0)
                          ? plateCustomizerData.options.google_fonts_list[0].split(':')[0] // Get font name without variants
                          : config.defaultFont;

        currentSettings = {
            plateId: template ? template.id : null,
            size: template && template.sizes && template.sizes.length > 0 ? template.sizes[0] : null,
            orientation: 'horizontal',
            withAddress: false,
            bgColor: template && template.bg_colors && template.bg_colors.length > 0 ? template.bg_colors[0] : null,
            numberSize: template && template.number_sizes && template.number_sizes.length > 0 ? template.number_sizes[0] : null,
            numberInput: '',
            addressInput: '',
            ledColor: defaultLed,
            fontStyle: defaultFont,
            maxChars: template ? (parseInt(template.max_chars, 10) || 5) : 5,
        };
    }

    function populateTemplateSelector() {
        const $select = $('#plate-template-select');
        $select.empty().append(`<option value="">${i18n.loadingTemplates || '-- Loading --'}</option>`); // Initial message

        if (plateCustomizerData.templates && Array.isArray(plateCustomizerData.templates) && plateCustomizerData.templates.length > 0) {
            $select.empty().append(`<option value="">${i18n.selectTemplate}</option>`); // Replace loading with select prompt
            plateCustomizerData.templates.forEach(template => {
                if (template && typeof template.id !== 'undefined' && typeof template.title !== 'undefined') {
                    $select.append(`<option value="${template.id}">${template.title}</option>`);
                }
            });
            $select.prop('disabled', false);
        } else {
            $select.empty().append(`<option value="">${i18n.noTemplates}</option>`);
            $select.prop('disabled', true);
            $('#plate-options-panel').hide();
        }
    }

    function populateGlobalOptions() {
        const $ledSwatches = $('#plate-led-color-swatches').empty();
        if (plateCustomizerData.options.led_colors && plateCustomizerData.options.led_colors.length > 0) {
            plateCustomizerData.options.led_colors.forEach((lc, index) => {
                const $swatch = $(`<button type="button" class="swatch-button ${index === 0 ? 'active' : ''}" data-color='${JSON.stringify(lc)}'>${lc.label}</button>`);
                if (lc.color && lc.color.startsWith('#')) $swatch.css('background-color', lc.color);
                $ledSwatches.append($swatch);
            });
        } else {
            $ledSwatches.html(`<p>${i18n.noLedColors}</p>`);
        }

        const $fontSelect = $('#plate-font-style').empty();
        if (plateCustomizerData.options.google_fonts_list && plateCustomizerData.options.google_fonts_list.length > 0) {
            plateCustomizerData.options.google_fonts_list.forEach(fontFullName => {
                const fontName = fontFullName.split(':')[0]; // Get "Roboto" from "Roboto:wght@400"
                $fontSelect.append(`<option value="${fontName}">${fontName}</option>`);
            });
        } else {
            $fontSelect.append(`<option value="${config.defaultFont}">${config.defaultFont} (Default)</option>`);
        }
        // Ensure currentSettings.fontStyle is set based on the populated dropdown
        if ($fontSelect.find('option').length > 0) {
            currentSettings.fontStyle = $fontSelect.val(); // Set to the first/default selected
        } else {
            currentSettings.fontStyle = config.defaultFont; // Fallback
        }
    }

    function loadTemplateOptions(templateId) {
        currentTemplate = plateCustomizerData.templates.find(t => String(t.id) === String(templateId));

        if (!currentTemplate) {
            $('#plate-options-panel').hide();
            resetCurrentSettings();
            console.warn(`Plate Customizer: Template with ID ${templateId} not found.`);
        } else {
            resetCurrentSettings(currentTemplate);

            $('#plate-number-input').attr('maxlength', currentSettings.maxChars).val(''); // Clear previous number
            $('#plate-number-char-limit').text(`Max ${currentSettings.maxChars} digits`);
            $('#plate-address-input').val(''); // Clear previous address


            populateSwatches('#plate-size-swatches', currentTemplate.sizes, 'size', (item) => {
                let content = `${item.width}" x ${item.height}"`;
                if (item.swatch_url) content = `<img src="${item.swatch_url}" alt="${content}" title="${content}">`;
                return content;
            }, i18n.noSizes);

            populateSwatches('#plate-bg-color-swatches', currentTemplate.bg_colors, 'bg', (item) => {
                let content = item.name || item.hex;
                if (item.swatch_url) content = `<img src="${item.swatch_url}" alt="${content}" title="${content}">`;
                return content;
            }, i18n.noBgColors, (item, $swatch) => { // postProcessCallback for bg color
                if (!item.swatch_url && item.hex) $swatch.css('background-color', item.hex);
            });

            populateSwatches('#plate-number-size-swatches', currentTemplate.number_sizes, 'numsize', (item) => item.label, i18n.noNumberSizes);

            // Update UI controls to reflect currentSettings after reset
            $('#plate-orientation-style').val(currentSettings.orientation);
            $('#plate-with-address').prop('checked', currentSettings.withAddress).trigger('change'); // trigger to update visibility
            $('#plate-font-style').val(currentSettings.fontStyle);
            // Activate the correct LED swatch
            $('#plate-led-color-swatches .swatch-button').removeClass('active');
            if(currentSettings.ledColor && currentSettings.ledColor.label){
                 $('#plate-led-color-swatches .swatch-button').filter((i, el) => $(el).data('color').label === currentSettings.ledColor.label).addClass('active');
            } else if ($('#plate-led-color-swatches .swatch-button').length > 0) {
                 $('#plate-led-color-swatches .swatch-button').first().addClass('active'); // Default to first if no match
            }

            $('#plate-options-panel').show();
        }
        drawPreview(); // Draw preview after loading options or resetting
    }

    function populateSwatches(containerSelector, items, dataKey, contentCallback, noItemsMessage, postProcessCallback = null) {
        const $container = $(containerSelector).empty();
        if (items && Array.isArray(items) && items.length > 0) {
            items.forEach((item, index) => {
                // Ensure item is an object before trying to stringify or access properties
                if (typeof item === 'object' && item !== null) {
                    const $swatch = $(`<button type="button" class="swatch-button ${index === 0 ? 'active' : ''}" data-${dataKey}='${JSON.stringify(item)}'>${contentCallback(item)}</button>`);
                    if (postProcessCallback) postProcessCallback(item, $swatch);
                    $container.append($swatch);
                } else {
                    console.warn(`PopulateSwatches: Invalid item found in ${dataKey} array at index ${index}`, item);
                }
            });
        } else {
            $container.html(`<p>${noItemsMessage}</p>`);
        }
    }

    // --- Event Binding ---
    function bindEvents() {
        $('#plate-template-select').on('change', function() {
            const templateId = $(this).val();
            loadTemplateOptions(templateId); // loadTemplateOptions handles empty/invalid templateId
        });

        // Generic swatch click handler using event delegation
        $app.on('click', '.swatch-button', function() {
            const $button = $(this);
            $button.addClass('active').siblings().removeClass('active');
            if ($button.data('size')) currentSettings.size = $button.data('size');
            else if ($button.data('bg')) currentSettings.bgColor = $button.data('bg');
            else if ($button.data('numsize')) currentSettings.numberSize = $button.data('numsize');
            else if ($button.data('color')) currentSettings.ledColor = $button.data('color'); // For LED
            drawPreview();
        });

        $('#plate-orientation-style').on('change', function() {
            currentSettings.orientation = $(this).val();
            drawPreview();
        });

        $('#plate-with-address').on('change', function() {
            currentSettings.withAddress = $(this).is(':checked');
            $('#plate-address-group').toggle(currentSettings.withAddress);
            if (!currentSettings.withAddress) { // Clear address if unchecked
                currentSettings.addressInput = '';
                $('#plate-address-input').val('');
            }
            drawPreview();
        });

        $('#plate-number-input').on('input', function() {
            let val = $(this).val().replace(/[^0-9]/g, ''); // Numeric only
            if (currentSettings.maxChars && val.length > currentSettings.maxChars) {
                val = val.substring(0, currentSettings.maxChars);
            }
            $(this).val(val); // Update input field
            currentSettings.numberInput = val;
            drawPreview();
        });

        $('#plate-address-input').on('input', function() {
            currentSettings.addressInput = $(this).val();
            drawPreview();
        });

        $('#plate-font-style').on('change', function() {
            currentSettings.fontStyle = $(this).val();
            drawPreview();
        });

        $('#download-plate-png').on('click', () => downloadImage('image/png', 'plate-design.png'));
        $('#download-plate-jpg').on('click', () => downloadImage('image/jpeg', 'plate-design.jpg'));

        $('#send-plate-design-form').on('submit', sendDesignToAdmin);
    }

    // --- Canvas Drawing ---
    function drawPreview() {
        if (!ctx) return;
        clearCanvas();

        if (!currentTemplate || !currentSettings.size || !currentSettings.bgColor || !currentSettings.numberSize) {
            updatePreviewSummary(); // Update summary even if not drawing plate
            return;
        }

        // Validate essential numeric values
        const plateWidthIn = parseFloat(currentSettings.size.width);
        const plateHeightIn = parseFloat(currentSettings.size.height);
        const baseNumberFontSizeRaw = parseFloat(currentSettings.numberSize.value);

        if (isNaN(plateWidthIn) || isNaN(plateHeightIn) || plateWidthIn <= 0 || plateHeightIn <= 0 ||
            isNaN(baseNumberFontSizeRaw) || baseNumberFontSizeRaw <= 0) {
            console.error("DrawPreview: Invalid dimensions or number size value.", currentSettings);
            updatePreviewSummary();
            return;
        }

        const plateWidthPx = inchToPx(plateWidthIn);
        const plateHeightPx = inchToPx(plateHeightIn);
        const canvasAvailableWidth = $canvas[0].width - 2 * config.canvasPadding;
        const canvasAvailableHeight = $canvas[0].height - 2 * config.canvasPadding;
        let scale = 1;
        if (plateWidthPx > 0 && plateHeightPx > 0) { // Prevent division by zero
            scale = Math.min(canvasAvailableWidth / plateWidthPx, canvasAvailableHeight / plateHeightPx, 1); // Don't scale up if plate is smaller than canvas area
        }

        const scaledPlateWidth = plateWidthPx * scale;
        const scaledPlateHeight = plateHeightPx * scale;
        const plateX = ($canvas[0].width - scaledPlateWidth) / 2;
        const plateY = ($canvas[0].height - scaledPlateHeight) / 2;

        // Draw Plate Background
        ctx.fillStyle = currentSettings.bgColor.hex || '#cccccc'; // Default color if hex is missing
        ctx.fillRect(plateX, plateY, scaledPlateWidth, scaledPlateHeight);
        // TODO: Implement image background drawing if currentSettings.bgColor.swatch_url is an image for the plate

        // Text Styling
        const baseNumberFontSize = baseNumberFontSizeRaw * scale;
        const addressFontSize = baseNumberFontSize * 0.4; // Relative size for address
        const ledColor = currentSettings.ledColor.color || '#000000'; // Default LED color
        const font = currentSettings.fontStyle || config.defaultFont;

        ctx.fillStyle = ledColor;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // LED Glow Effect
        ctx.shadowColor = ledColor;
        ctx.shadowBlur = Math.max(1, 10 * scale); // Ensure shadow is visible even at small scales
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;

        const numberText = currentSettings.numberInput;
        const addressText = currentSettings.addressInput;

        // Draw Number & Address based on orientation
        if (currentSettings.orientation === 'horizontal') {
            let numberY = plateY + scaledPlateHeight / 2;
            let addressY = 0; // To be calculated if address is present

            if (currentSettings.withAddress && addressText) {
                numberY = plateY + scaledPlateHeight * 0.4; // Position number higher
                addressY = plateY + scaledPlateHeight * 0.7; // Position address lower
                // Draw line between number and address
                ctx.strokeStyle = ledColor;
                ctx.lineWidth = Math.max(1, 2 * scale); // Ensure line is visible
                ctx.beginPath();
                ctx.moveTo(plateX + scaledPlateWidth * 0.1, plateY + scaledPlateHeight * 0.55);
                ctx.lineTo(plateX + scaledPlateWidth * 0.9, plateY + scaledPlateHeight * 0.55);
                ctx.stroke();
            }
            ctx.font = `${baseNumberFontSize}px "${font}", sans-serif`;
            ctx.fillText(numberText, plateX + scaledPlateWidth / 2, numberY);

            if (currentSettings.withAddress && addressText) {
                ctx.font = `${addressFontSize}px "${font}", sans-serif`;
                ctx.fillText(addressText, plateX + scaledPlateWidth / 2, addressY);
            }
        } else { // Vertical orientation
            const digits = numberText.split('');
            const spacingFactor = 1.2; // Multiplier for line height of vertical digits
            const singleDigitHeight = baseNumberFontSize; // Approximate height of one digit
            const totalNumberBlockHeight = digits.length * singleDigitHeight * spacingFactor - (singleDigitHeight * (spacingFactor - 1)); // Adjusted for no trailing space

            let contentStartY; // Top Y for the entire content block (numbers + optional address)

            if (currentSettings.withAddress && addressText) {
                const lineSpace = baseNumberFontSize * 0.15; // Space above/below the dividing line
                const addressBlockHeight = addressFontSize + (lineSpace * 2); // Height for address and its spacing
                const totalContentHeight = totalNumberBlockHeight + addressBlockHeight;
                contentStartY = plateY + (scaledPlateHeight - totalContentHeight) / 2;

                // Draw vertical digits
                digits.forEach((digit, index) => {
                    const digitY = contentStartY + (index * singleDigitHeight * spacingFactor) + (singleDigitHeight / 2);
                    ctx.font = `${baseNumberFontSize}px "${font}", sans-serif`;
                    ctx.fillText(digit, plateX + scaledPlateWidth / 2, digitY);
                });

                // Draw line
                const lineY = contentStartY + totalNumberBlockHeight + lineSpace;
                ctx.strokeStyle = ledColor;
                ctx.lineWidth = Math.max(1, 2 * scale);
                ctx.beginPath();
                ctx.moveTo(plateX + scaledPlateWidth * 0.2, lineY);
                ctx.lineTo(plateX + scaledPlateWidth * 0.8, lineY);
                ctx.stroke();

                // Draw address
                const addressActualY = lineY + lineSpace + (addressFontSize / 2);
                ctx.font = `${addressFontSize}px "${font}", sans-serif`;
                ctx.fillText(addressText, plateX + scaledPlateWidth / 2, addressActualY);

            } else { // Vertical, no address
                contentStartY = plateY + (scaledPlateHeight - totalNumberBlockHeight) / 2;
                digits.forEach((digit, index) => {
                    const digitY = contentStartY + (index * singleDigitHeight * spacingFactor) + (singleDigitHeight / 2);
                    ctx.font = `${baseNumberFontSize}px "${font}", sans-serif`;
                    ctx.fillText(digit, plateX + scaledPlateWidth / 2, digitY);
                });
            }
        }

        // Reset shadow for any subsequent non-glowing drawings
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        updatePreviewSummary();
    }

    function updatePreviewSummary() {
        const $list = $('#current-options-list').empty();
        if (!currentSettings.plateId && !currentTemplate) {
            $list.append(`<li>${i18n.selectTemplate || 'Please select a template.'}</li>`);
            return;
        }

        if(currentTemplate) $list.append(`<li>Template: ${currentTemplate.title}</li>`);
        if(currentSettings.size) $list.append(`<li>Size: ${currentSettings.size.width}" x ${currentSettings.size.height}"</li>`);
        else $list.append(`<li>Size: N/A</li>`);
        $list.append(`<li>Orientation: ${currentSettings.orientation}</li>`);
        $list.append(`<li>With Address: ${currentSettings.withAddress ? 'Yes' : 'No'}</li>`);
        if(currentSettings.bgColor) $list.append(`<li>Background: ${currentSettings.bgColor.name || currentSettings.bgColor.hex || 'N/A'}</li>`);
        else $list.append(`<li>Background: N/A</li>`);
        if(currentSettings.numberSize) $list.append(`<li>Number Size: ${currentSettings.numberSize.label}</li>`);
        else $list.append(`<li>Number Size: N/A</li>`);
        $list.append(`<li>Number: ${currentSettings.numberInput || 'N/A'}</li>`);
        if(currentSettings.withAddress && currentSettings.addressInput) $list.append(`<li>Address: ${currentSettings.addressInput}</li>`);
        else if (currentSettings.withAddress) $list.append(`<li>Address: N/A</li>`);
        if(currentSettings.ledColor) $list.append(`<li>LED Color: ${currentSettings.ledColor.label}</li>`);
        else $list.append(`<li>LED Color: N/A</li>`);
        $list.append(`<li>Font: ${currentSettings.fontStyle}</li>`);
    }

    // --- Utilities ---
    function downloadImage(format, filename) {
        if (!$canvas.length || !ctx) return;
        const dataUrl = $canvas[0].toDataURL(format, format === 'image/jpeg' ? 0.9 : 1.0); // Quality for JPEG
        const link = document.createElement('a');
        link.download = filename;
        link.href = dataUrl;
        document.body.appendChild(link); // Required for Firefox for click to work
        link.click();
        document.body.removeChild(link); // Clean up the appended link
    }

    function sendDesignToAdmin(event) {
        event.preventDefault();
        const $form = $(this);
        const name = $('#user-name').val().trim();
        const email = $('#user-email').val().trim();
        const $messageDiv = $('#send-form-message').text('').removeClass('error success').css('color',''); // Clear previous messages and styles

        let isValid = true;
        let errorMessages = [];

        if (!name) errorMessages.push(i18n.nameRequired);
        if (!email) errorMessages.push(i18n.emailRequired);
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errorMessages.push(i18n.validEmailRequired);
        if (!currentSettings.plateId) errorMessages.push(i18n.plateRequired);

        if (errorMessages.length > 0) {
            $messageDiv.html(errorMessages.join('<br>')).addClass('error').css('color', 'red');
            return;
        }

        $messageDiv.text(i18n.sending).css('color', 'blue');
        $('#send-design-button').prop('disabled', true);

        const imageData = $canvas[0].toDataURL('image/png'); // Send as PNG

        $.ajax({
            url: plateCustomizerData.options.ajax_url,
            type: 'POST',
            data: {
                action: 'send_plate_design', // Matches WP AJAX action hook
                nonce: plateCustomizerData.options.nonce,
                name: name,
                email: email,
                image_data: imageData,
                customizations: JSON.stringify(currentSettings) // Send summary of choices
            },
            success: function(response) {
                if (response.success) {
                    $messageDiv.text(i18n.designSent).addClass('success').css('color', 'green');
                    $form[0].reset(); // Reset form fields
                    // Optionally, reset the customizer to its initial state:
                    // $('#plate-template-select').val('').trigger('change');
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : i18n.unknownError;
                    $messageDiv.text(i18n.errorPrefix + errorMsg).addClass('error').css('color', 'red');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $messageDiv.text(i18n.ajaxErrorPrefix + textStatus + (errorThrown ? ` - ${errorThrown}` : '')).addClass('error').css('color', 'red');
                console.error("AJAX Error Details:", jqXHR, textStatus, errorThrown);
            },
            complete: function() {
                $('#send-design-button').prop('disabled', false);
            }
        });
    }

    // --- Run ---
    // Show loading overlay before initialization attempt
    $loadingOverlay.show();
    // A small delay can sometimes help ensure all DOM elements are fully ready, though usually $(document).ready is enough.
    // setTimeout(initializeCustomizer, 0); // Could use a slight delay if needed, but usually not.
    initializeCustomizer();

});