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

            // Inicializar tree views
            initTreeViews($form);

            // Inicializar cropper de foto
            initPhotoCropper($form);

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

                // Validar campos de taxonomia obrigatórios
                $form.find('.wec-taxonomy-group[data-required="true"]').each(function() {
                    var $group = $(this);
                    var requiredLevel = parseInt($group.data('required-level')) || 1;
                    var hasSelection = false;
                    
                    // Verificar tree view
                    var $tree = $group.find('.wec-tree-view');
                    if ($tree.length) {
                        var $checked = $tree.find('input:checked');
                        if ($checked.length > 0) {
                            var level = parseInt($checked.first().data('level')) || 1;
                            hasSelection = level >= requiredLevel || requiredLevel === 1;
                        }
                    }
                    
                    // Verificar selects
                    var $selects = $group.find('.wec-form-select, .wec-select-level');
                    if ($selects.length && !$tree.length) {
                        $selects.each(function(index) {
                            if (index < requiredLevel && $(this).val()) {
                                hasSelection = true;
                            }
                        });
                        if (requiredLevel === 1 && $selects.first().val()) {
                            hasSelection = true;
                        }
                    }
                    
                    if (!hasSelection) {
                        isValid = false;
                        $group.addClass('error');
                        if (!$group.find('.wec-field-error').length) {
                            $group.append('<span class="wec-field-error">Este campo é obrigatório.</span>');
                        }
                    }
                });

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

    // Inicializar tree views
    function initTreeViews($form) {
        $form.find('.wec-tree-view').each(function() {
            var $tree = $(this);
            
            // Toggle expansão ao clicar no toggle
            $tree.on('click', '.wec-tree-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $(this).closest('.wec-tree-item');
                toggleTreeItem($item);
            });
            
            // Também expandir ao clicar no node inteiro (exceto input)
            $tree.on('click', '.wec-tree-node', function(e) {
                if ($(e.target).is('input')) return;
                var $item = $(this).closest('.wec-tree-item');
                if ($item.hasClass('has-children')) {
                    toggleTreeItem($item);
                }
            });
            
            // Expandir ao selecionar um item com filhos
            $tree.on('change', '.wec-tree-label input', function() {
                var $item = $(this).closest('.wec-tree-item');
                if ($item.hasClass('has-children') && $(this).is(':checked')) {
                    if (!$item.hasClass('expanded')) {
                        toggleTreeItem($item);
                    }
                }
            });
        });
    }
    
    function toggleTreeItem($item) {
        var $sublist = $item.find('> .wec-tree-list');
        if ($sublist.length) {
            $item.toggleClass('expanded');
            $sublist.slideToggle(200);
        }
    }

    // Inicializar cropper de foto
    function initPhotoCropper($form) {
        var $photoGroup = $form.find('.wec-photo-group');
        if (!$photoGroup.length) return;
        
        var enableCropper = $photoGroup.data('cropper') === true || $photoGroup.data('cropper') === 'true';
        if (!enableCropper) return;
        
        var aspectRatio = $photoGroup.data('aspect') || '1:1';
        var customW = parseFloat($photoGroup.data('custom-w')) || 1;
        var customH = parseFloat($photoGroup.data('custom-h')) || 1;
        var outputWidth = parseInt($photoGroup.data('output-width')) || 400;
        var outputQuality = parseFloat($photoGroup.data('output-quality')) || 0.85;
        
        // Calcular aspect ratio numérico
        var numericAspect = getNumericAspect(aspectRatio, customW, customH);
        
        var $fileInput = $photoGroup.find('.wec-photo-input');
        var $croppedInput = $photoGroup.find('.wec-photo-cropped');
        var $preview = $photoGroup.find('.wec-photo-preview');
        var $previewImg = $preview.find('img');
        var $changeBtn = $photoGroup.find('.wec-photo-change');
        
        // Evento de seleção de arquivo
        $fileInput.on('change', function(e) {
            var file = e.target.files[0];
            if (!file || !file.type.match('image.*')) return;
            
            var reader = new FileReader();
            reader.onload = function(event) {
                openCropperModal(event.target.result, numericAspect, outputWidth, outputQuality, function(croppedData) {
                    $croppedInput.val(croppedData);
                    $previewImg.attr('src', croppedData);
                    $preview.show();
                    $fileInput.hide();
                });
            };
            reader.readAsDataURL(file);
        });
        
        // Botão trocar foto
        $changeBtn.on('click', function() {
            $croppedInput.val('');
            $preview.hide();
            $fileInput.val('').show();
        });
    }
    
    // Calcular aspect ratio numérico
    function getNumericAspect(ratio, customW, customH) {
        if (ratio === 'free') return NaN;
        if (ratio === 'custom') return customW / customH;
        
        var parts = ratio.split(':');
        if (parts.length === 2) {
            return parseFloat(parts[0]) / parseFloat(parts[1]);
        }
        return 1;
    }
    
    // Modal do cropper
    function openCropperModal(imageSrc, aspectRatio, outputWidth, quality, callback) {
        var texts = WEC_FORM.cropperTexts || {};
        
        var modalHtml = '<div class="wec-cropper-overlay">' +
            '<div class="wec-cropper-modal">' +
                '<div class="wec-cropper-header">' +
                    '<h3>' + (texts.title || 'Recortar Imagem') + '</h3>' +
                '</div>' +
                '<div class="wec-cropper-body">' +
                    '<img src="' + imageSrc + '" class="wec-cropper-image">' +
                '</div>' +
                '<div class="wec-cropper-controls">' +
                    '<button type="button" class="wec-cropper-btn wec-cropper-rotate" title="' + (texts.rotate || 'Girar') + '">' +
                        '<i class="fas fa-redo"></i>' +
                    '</button>' +
                    '<button type="button" class="wec-cropper-btn wec-cropper-zoom-in" title="' + (texts.zoom || 'Zoom') + ' +">' +
                        '<i class="fas fa-search-plus"></i>' +
                    '</button>' +
                    '<button type="button" class="wec-cropper-btn wec-cropper-zoom-out" title="' + (texts.zoom || 'Zoom') + ' -">' +
                        '<i class="fas fa-search-minus"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="wec-cropper-footer">' +
                    '<button type="button" class="wec-cropper-cancel">' + (texts.cancel || 'Cancelar') + '</button>' +
                    '<button type="button" class="wec-cropper-confirm">' + (texts.confirm || 'Confirmar') + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        $('body').append(modalHtml);
        
        var $overlay = $('.wec-cropper-overlay');
        var $image = $overlay.find('.wec-cropper-image');
        var cropper = null;
        
        // Inicializar Cropper.js
        setTimeout(function() {
            $overlay.addClass('active');
            
            cropper = new Cropper($image[0], {
                aspectRatio: aspectRatio,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        }, 50);
        
        // Controles
        $overlay.find('.wec-cropper-rotate').on('click', function() {
            if (cropper) cropper.rotate(90);
        });
        
        $overlay.find('.wec-cropper-zoom-in').on('click', function() {
            if (cropper) cropper.zoom(0.1);
        });
        
        $overlay.find('.wec-cropper-zoom-out').on('click', function() {
            if (cropper) cropper.zoom(-0.1);
        });
        
        // Cancelar
        $overlay.find('.wec-cropper-cancel').on('click', function() {
            closeCropperModal($overlay, cropper);
        });
        
        // Confirmar
        $overlay.find('.wec-cropper-confirm').on('click', function() {
            if (!cropper) return;
            
            var canvas = cropper.getCroppedCanvas({
                width: outputWidth,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            var croppedData = canvas.toDataURL('image/jpeg', quality);
            callback(croppedData);
            closeCropperModal($overlay, cropper);
        });
    }
    
    // Fechar modal do cropper
    function closeCropperModal($overlay, cropper) {
        $overlay.removeClass('active');
        setTimeout(function() {
            if (cropper) cropper.destroy();
            $overlay.remove();
        }, 300);
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
