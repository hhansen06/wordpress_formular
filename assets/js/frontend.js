/**
 * Frontend JavaScript für Form Builder Plugin
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initFormBuilder();
    });
    
    function initFormBuilder() {
        $('.form-builder-form').on('submit', handleFormSubmit);
        initSignatureFields();
    }

    function initSignatureFields() {
        $('.form-builder-signature').each(function() {
            const $wrapper = $(this);
            const $canvas = $wrapper.find('.form-builder-signature-canvas');
            const $input = $wrapper.find('.form-builder-signature-input');
            const $clear = $wrapper.find('.form-builder-signature-clear');

            const canvas = $canvas[0];
            const ctx = canvas.getContext('2d');
            canvas.style.touchAction = 'none';

            function resizeCanvas() {
                let width = $wrapper.width();
                if (!width || width < 10) {
                    width = 600;
                }
                const height = 200;
                canvas.width = width;
                canvas.height = height;
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
            }

            setTimeout(resizeCanvas, 0);
            setTimeout(resizeCanvas, 100);
            setTimeout(resizeCanvas, 500);

            let drawing = false;
            let hasDrawing = false;

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDraw(e) {
                drawing = true;
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                e.preventDefault();
            }

            function draw(e) {
                if (!drawing) return;
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                hasDrawing = true;
                e.preventDefault();
            }

            function endDraw(e) {
                if (!drawing) return;
                drawing = false;
                ctx.closePath();
                e.preventDefault();
            }

            function handleTouchMove(e) {
                if (drawing) {
                    e.preventDefault();
                }
            }

            $canvas.on('mousedown', startDraw);
            $canvas.on('mousemove', draw);
            $(document).on('mouseup', endDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', endDraw, { passive: false });
            document.addEventListener('touchmove', handleTouchMove, { passive: false });
            document.addEventListener('touchend', endDraw, { passive: false });

            $clear.on('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawing = false;
                $input.val('');
            });

            $wrapper.data('getSignature', function() {
                if (!hasDrawing) {
                    return '';
                }
                return canvas.toDataURL('image/png');
            });

            $(window).on('resize', function() {
                const dataUrl = hasDrawing ? canvas.toDataURL('image/png') : '';
                resizeCanvas();
                if (dataUrl) {
                    const img = new Image();
                    img.onload = function() {
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    };
                    img.src = dataUrl;
                }
            });
        });
    }
    
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitButton = $form.find('.form-builder-submit-button');
        const buttonText = $submitButton.text();
        const formId = $form.data('form-id');
        
        // Sammle Signaturen vor der Validierung
        $form.find('.form-builder-signature').each(function() {
            const $wrapper = $(this);
            const getSignature = $wrapper.data('getSignature');
            const dataUrl = typeof getSignature === 'function' ? getSignature() : '';
            $wrapper.find('.form-builder-signature-input').val(dataUrl);
        });

        // Validierung
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        let signatureValid = true;
        $form.find('.form-builder-signature').each(function() {
            const $wrapper = $(this);
            const isRequired = $wrapper.data('required') === 1 || $wrapper.data('required') === '1';
            const $input = $wrapper.find('.form-builder-signature-input');
            if (isRequired && !$input.val()) {
                signatureValid = false;
                const $field = $wrapper.closest('.form-field');
                $field.addClass('has-error');
                if (!$field.find('.form-field-error').length) {
                    $field.append('<div class="form-field-error">' + formBuilderFrontend.strings.requiredField + '</div>');
                }
            }
        });

        if (!signatureValid) {
            return;
        }
        
        // Sammle Formulardaten
        const formData = {
            action: 'form_builder_submit',
            nonce: formBuilderFrontend.nonce,
            form_id: formId
        };
        
        // Füge alle Felder hinzu (inkl. CAPTCHA und Honeypot)
        $form.find('input, textarea, select').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            
            // Überspringe Felder ohne Namen oder Button
            if (!name || $field.attr('type') === 'submit' || $field.attr('type') === 'button') {
                return;
            }
            
            if ($field.attr('type') === 'checkbox') {
                // Checkbox-Gruppe (Array) oder einzelne Checkbox
                if (name.endsWith('[]')) {
                    // Checkbox-Gruppe - sammle alle ausgewählten Werte
                    const baseName = name.slice(0, -2);
                    if (!formData[baseName]) {
                        const checkedValues = [];
                        $form.find('[name="' + name + '"]:checked').each(function() {
                            checkedValues.push($(this).val());
                        });
                        formData[baseName] = checkedValues.join(', ');
                    }
                } else {
                    // Einzelne Checkbox
                    formData[name] = $field.is(':checked') ? $field.val() : '';
                }
            } else if ($field.attr('type') === 'radio') {
                if ($field.is(':checked')) {
                    formData[name] = $field.val();
                }
            } else {
                formData[name] = $field.val();
            }
        });
        
        // UI-Feedback
        $form.addClass('submitting');
        $submitButton.prop('disabled', true).text(formBuilderFrontend.strings.submitting);
        clearMessages($form);
        
        // AJAX-Request
        $.post(formBuilderFrontend.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    showMessage($form, response.data.message, 'success');
                    $form.addClass('submitted');
                    
                    // Verstecke alle Formularfelder und Submit-Button nach erfolgreichem Absenden
                    $form.find('.form-builder-fields, .form-field-captcha, .form-builder-submit').hide();
                    
                    // Scroll zur Nachricht
                    $('html, body').animate({
                        scrollTop: $form.find('.form-builder-messages').offset().top - 100
                    }, 500);
                } else {
                    showMessage($form, response.data.message, 'error');
                    $form.removeClass('submitting');
                    $submitButton.prop('disabled', false).text(buttonText);
                }
            })
            .fail(function(xhr) {
                let errorMessage = formBuilderFrontend.strings.errorSubmitting;
                
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                
                showMessage($form, errorMessage, 'error');
                $form.removeClass('submitting');
                $submitButton.prop('disabled', false).text(buttonText);
            });
    }
    
    function showMessage($form, message, type) {
        const $messagesContainer = $form.find('.form-builder-messages');
        const $message = $('<div class="form-builder-message ' + type + '">' + message + '</div>');
        
        $messagesContainer.empty().append($message);
        
        // Auto-hide nach 8 Sekunden bei Erfolg
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 8000);
        }
    }
    
    function clearMessages($form) {
        $form.find('.form-builder-messages').empty();
        $form.find('.form-field').removeClass('has-error');
        $form.find('.form-field-error').remove();
    }
    
    // Client-seitige Validierung
    function validateField($field) {
        const $input = $field.find('.form-field-input');
        const value = $input.val().trim();
        const isRequired = $input.prop('required');
        const type = $input.attr('type');
        
        let isValid = true;
        let errorMessage = '';
        
        if (isRequired && !value) {
            isValid = false;
            errorMessage = formBuilderFrontend.strings.requiredField;
        } else if (value) {
            // E-Mail-Validierung
            if (type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
                }
            }
            
            // URL-Validierung
            if (type === 'url') {
                try {
                    new URL(value);
                } catch (e) {
                    isValid = false;
                    errorMessage = 'Bitte geben Sie eine gültige URL ein.';
                }
            }
        }
        
        if (!isValid) {
            $field.addClass('has-error');
            if (!$field.find('.form-field-error').length) {
                $field.append('<div class="form-field-error">' + errorMessage + '</div>');
            }
        } else {
            $field.removeClass('has-error');
            $field.find('.form-field-error').remove();
        }
        
        return isValid;
    }
    
    // Live-Validierung bei Eingabe (optional)
    $('.form-field-input').on('blur', function() {
        const $field = $(this).closest('.form-field');
        validateField($field);
    });
    
})(jQuery);
