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

            // Inicializar selects hierárquicos
            initHierarchicalSelects($form);

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
                            // Mostrar modal de sucesso
                            showSuccessModal(response.data.message || successMsg);
                            
                            // Limpar formulário
                            $form[0].reset();
                            
                            // Reset selects hierárquicos
                            $form.find('.wec-interest-level[data-level="2"], .wec-interest-level[data-level="3"]').hide();

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

    // Inicializar selects hierárquicos
    function initHierarchicalSelects($form) {
        var $dataScript = $form.find('.wec-interests-children-data');
        if (!$dataScript.length) return;
        
        var childrenData = {};
        try {
            childrenData = JSON.parse($dataScript.text());
        } catch(e) {
            console.error('WEC: Erro ao parsear dados hierárquicos');
            return;
        }
        
        // Detectar tipo de renderização
        var $selectContainer = $form.find('.wec-hierarchical-selects');
        var $radioContainer = $form.find('.wec-hierarchical-radio');
        var $checkboxContainer = $form.find('.wec-hierarchical-checkbox');
        
        if ($selectContainer.length) {
            initSelectHierarchy($selectContainer, childrenData);
        }
        
        if ($radioContainer.length) {
            initRadioCheckboxHierarchy($radioContainer, childrenData, 'radio');
        }
        
        if ($checkboxContainer.length) {
            initRadioCheckboxHierarchy($checkboxContainer, childrenData, 'checkbox');
        }
    }
    
    // Hierarquia para SELECT
    function initSelectHierarchy($container, childrenData) {
        var $level1 = $container.find('[data-level="1"]');
        var $level2 = $container.find('[data-level="2"]');
        var $level3 = $container.find('[data-level="3"]');
        
        $level1.on('change', function() {
            var parentId = $(this).val();
            $level2.hide().find('option:not(:first)').remove();
            $level3.hide().find('option:not(:first)').remove();
            
            if (!parentId) return;
            
            var children = childrenData[parentId];
            if (children && children.length > 0) {
                children.forEach(function(child) {
                    $level2.append('<option value="' + child.id + '" data-has-children="' + (child.has_children ? '1' : '0') + '">' + child.name + '</option>');
                });
                $level2.show();
            }
        });
        
        $level2.on('change', function() {
            var parentId = $(this).val();
            $level3.hide().find('option:not(:first)').remove();
            
            if (!parentId) return;
            
            var children = childrenData[parentId];
            if (children && children.length > 0) {
                children.forEach(function(child) {
                    $level3.append('<option value="' + child.id + '">' + child.name + '</option>');
                });
                $level3.show();
            }
        });
    }
    
    // Hierarquia para RADIO e CHECKBOX
    function initRadioCheckboxHierarchy($container, childrenData, inputType) {
        var $level1Group = $container.find('[data-level="1"]');
        var $level2Group = $container.find('[data-level="2"]');
        var $level3Group = $container.find('[data-level="3"]');
        var $collector = $container.find('.wec-interests-collector');
        
        // Função para atualizar campo hidden com valores selecionados
        function updateCollector() {
            var values = [];
            $container.find('input:checked').each(function() {
                values.push($(this).val());
            });
            $collector.val(values.join(','));
        }
        
        // Nível 1 change
        $level1Group.find('input').on('change', function() {
            var $input = $(this);
            var parentId = $input.val();
            var hasChildren = $input.data('has-children') == 1;
            
            // Reset níveis 2 e 3
            $level2Group.hide().find('.wec-interest-children').empty();
            $level3Group.hide().find('.wec-interest-grandchildren').empty();
            
            if (!$input.is(':checked') || !hasChildren) {
                updateCollector();
                return;
            }
            
            var children = childrenData[parentId];
            if (children && children.length > 0) {
                var $childContainer = $level2Group.find('.wec-interest-children');
                children.forEach(function(child) {
                    var html = '<label class="wec-form-' + inputType + '-label">' +
                        '<input type="' + inputType + '" name="interests_level2[]" value="' + child.id + '" data-has-children="' + (child.has_children ? '1' : '0') + '" class="wec-interest-input" data-level="2">' +
                        '<span>' + child.name + '</span>' +
                    '</label>';
                    $childContainer.append(html);
                });
                
                // Bind eventos nos novos inputs
                bindLevel2Events($level2Group, $level3Group, childrenData, inputType, updateCollector);
                $level2Group.show();
            }
            
            updateCollector();
        });
        
        updateCollector();
    }
    
    // Bind eventos do nível 2
    function bindLevel2Events($level2Group, $level3Group, childrenData, inputType, updateCollector) {
        $level2Group.find('input').off('change').on('change', function() {
            var $input = $(this);
            var parentId = $input.val();
            var hasChildren = $input.data('has-children') == 1;
            
            // Reset nível 3
            $level3Group.hide().find('.wec-interest-grandchildren').empty();
            
            if (!$input.is(':checked') || !hasChildren) {
                updateCollector();
                return;
            }
            
            var children = childrenData[parentId];
            if (children && children.length > 0) {
                var $grandchildContainer = $level3Group.find('.wec-interest-grandchildren');
                children.forEach(function(child) {
                    var html = '<label class="wec-form-' + inputType + '-label">' +
                        '<input type="' + inputType + '" name="interests_level3[]" value="' + child.id + '" class="wec-interest-input" data-level="3">' +
                        '<span>' + child.name + '</span>' +
                    '</label>';
                    $grandchildContainer.append(html);
                });
                
                // Bind change no nível 3
                $level3Group.find('input').off('change').on('change', updateCollector);
                $level3Group.show();
            }
            
            updateCollector();
        });
    }

    // Modal de sucesso
    function showSuccessModal(message) {
        // Remover modal anterior se existir
        $('.wec-success-modal-overlay').remove();
        
        var modalHtml = '<div class="wec-success-modal-overlay">' +
            '<div class="wec-success-modal">' +
                '<div class="wec-success-icon"><i class="fas fa-check-circle"></i></div>' +
                '<h3>Sucesso!</h3>' +
                '<p>' + message + '</p>' +
                '<button type="button" class="wec-success-modal-btn">OK</button>' +
            '</div>' +
        '</div>';
        
        $('body').append(modalHtml);
        
        // Animar entrada
        setTimeout(function() {
            $('.wec-success-modal-overlay').addClass('active');
        }, 10);
        
        // Fechar modal
        $('.wec-success-modal-overlay').on('click', function(e) {
            if ($(e.target).hasClass('wec-success-modal-overlay') || $(e.target).hasClass('wec-success-modal-btn')) {
                $(this).removeClass('active');
                setTimeout(function() {
                    $('.wec-success-modal-overlay').remove();
                }, 300);
            }
        });
    }

})(jQuery);
