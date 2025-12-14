/**
 * JavaScript do Formulário de Lead - Elementor Widget
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 2.2.0
 * @created 2025-12-13 22:35:00
 */

(function($) {
    'use strict';

    // Inicializar quando DOM estiver pronto
    $(document).ready(function() {
        initLeadForms();
    });

    // Também inicializar após Elementor carregar widgets (para preview)
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/wec_lead_form.default', function($scope) {
                initLeadForms();
            });
        }
    });

    function initLeadForms() {
        $('.wec-lead-form').each(function() {
            var $form = $(this);
            
            // Evitar dupla inicialização
            if ($form.data('wec-initialized')) return;
            $form.data('wec-initialized', true);

            // Máscara de telefone
            var $phoneInputs = $form.find('.wec-phone-input');
            $phoneInputs.each(function() {
                var $input = $(this);
                $input.on('input', function() {
                    var value = this.value.replace(/\D/g, '');
                    var formatted = '';
                    
                    if (value.length > 0) {
                        // Formato brasileiro: (11) 99999-9999
                        if (value.length <= 2) {
                            formatted = '(' + value;
                        } else if (value.length <= 7) {
                            formatted = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                        } else if (value.length <= 11) {
                            formatted = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
                        } else {
                            formatted = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7, 11);
                        }
                    }
                    
                    this.value = formatted;
                });
            });

            // Submit do formulário
            $form.on('submit', function(e) {
                e.preventDefault();
                
                var $btn = $form.find('.wec-form-submit');
                var $message = $form.find('.wec-form-message');
                var originalText = $btn.data('text');
                var loadingText = $btn.data('loading');
                var successMsg = $btn.data('success');
                var errorMsg = $btn.data('error');

                // Limpar erros anteriores
                $form.find('.wec-field-error').remove();
                $form.find('.error').removeClass('error');
                $message.hide().removeClass('success error');

                // Validação básica
                var isValid = true;
                $form.find('[required]').each(function() {
                    var $field = $(this);
                    if (!$field.val().trim()) {
                        isValid = false;
                        $field.addClass('error');
                        $field.after('<span class="wec-field-error">Este campo é obrigatório.</span>');
                    }
                });

                // Validar email
                var $emailField = $form.find('input[type="email"]');
                if ($emailField.length && $emailField.val().trim()) {
                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test($emailField.val())) {
                        isValid = false;
                        $emailField.addClass('error');
                        $emailField.after('<span class="wec-field-error">E-mail inválido.</span>');
                    }
                }

                if (!isValid) {
                    return false;
                }

                // Preparar FormData para upload de arquivo
                var formData = new FormData($form[0]);
                formData.append('action', 'wec_lead_form_submit');
                formData.append('nonce', WEC_FORM.nonce);

                // Desabilitar botão e mostrar loading
                $btn.prop('disabled', true).addClass('loading');
                $btn.find('span').text(loadingText);
                $btn.find('i').removeClass().addClass('fas fa-spinner');

                // Enviar AJAX
                $.ajax({
                    url: WEC_FORM.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $message.html(response.data.message || successMsg)
                                    .addClass('success')
                                    .show();
                            
                            // Limpar formulário
                            $form[0].reset();
                            
                            // Scroll para mensagem
                            $('html, body').animate({
                                scrollTop: $form.offset().top - 50
                            }, 300);

                            // Disparar evento customizado
                            $(document).trigger('wec_lead_form_success', [response.data]);

                        } else {
                            $message.html(response.data.message || errorMsg)
                                    .addClass('error')
                                    .show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('WEC Form Error:', error);
                        $message.html(errorMsg)
                                .addClass('error')
                                .show();
                    },
                    complete: function() {
                        // Restaurar botão
                        $btn.prop('disabled', false).removeClass('loading');
                        $btn.find('span').text(originalText);
                        $btn.find('i').removeClass('fas fa-spinner').addClass($btn.data('icon') || 'fas fa-paper-plane');
                    }
                });
            });
        });
    }

})(jQuery);
