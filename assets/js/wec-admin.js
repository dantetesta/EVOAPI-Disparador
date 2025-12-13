/**
 * WhatsApp Evolution Clients - Admin JavaScript
 * 
 * Interface premium para envio de mensagens WhatsApp
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11
 */

(function ($) {
    'use strict';

    /**
     * Namespace do plugin
     */
    const WEC = {
        // Elementos
        elements: {},

        // Estado
        state: {
            selectedImage: null,
            imageBase64: null,
            imageMimetype: null,
            imageFilename: null,
        },

        /**
         * Inicialização
         */
        init: function () {
            this.cacheElements();
            this.bindEvents();
            // FAB removido - disparo em massa agora é feito pelo painel WP PostZap
            this.initToast();
        },

        /**
         * Cache de elementos DOM
         */
        cacheElements: function () {
            this.elements = {
                // Modais
                singleModal: $('#wec-single-send-modal'),
                bulkModal: $('#wec-bulk-send-modal'),

                // Campos do modal individual
                singleClientId: $('#wec-single-client-id'),
                singleClientName: $('#wec-single-client-name'),
                singleClientPhone: $('#wec-single-client-phone'),
                singleClientAvatar: $('#wec-single-client-avatar'),
                singleMessage: $('#wec-single-message'),
                singleCharCount: $('#wec-single-char-count'),
                singleImage: $('#wec-single-image'),
                singleDropzone: $('#wec-single-dropzone'),
                singlePreview: $('#wec-single-preview'),
                singlePreviewImg: $('#wec-single-preview-img'),
                singleSendBtn: $('.wec-single-send-btn'),

                // Botões
                testConnectionBtn: $('#wec-test-connection'),
                testResult: $('#wec-test-result'),
            };
        },

        /**
         * Bindando eventos
         */
        bindEvents: function () {
            const self = this;

            // Toggle de senha nos campos password
            $(document).on('click', '.wec-toggle-password', function () {
                const $btn = $(this);
                const $input = $btn.siblings('input');
                const isPassword = $input.attr('type') === 'password';
                
                $input.attr('type', isPassword ? 'text' : 'password');
                $btn.toggleClass('active', isPassword);
            });

            // Exportar leads
            $(document).on('click', '#wec-export-btn', function () {
                self.exportLeads($(this));
            });

            // Teste de conexão
            this.elements.testConnectionBtn.on('click', function () {
                self.testConnection();
            });

            // Fechar modais
            $(document).on('click', '.wec-modal-overlay, .wec-modal-close, .wec-modal-cancel', function () {
                self.closeAllModals();
            });

            // Prevenir fechamento ao clicar no container
            $(document).on('click', '.wec-modal-container', function (e) {
                e.stopPropagation();
            });

            // ESC para fechar
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    self.closeAllModals();
                }
            });

            // Abrir modal individual
            $(document).on('click', '.wec-send-single-action', function (e) {
                e.preventDefault();
                const clientId = $(this).data('client-id');
                const clientName = $(this).data('client-name');
                const clientPhone = $(this).data('client-phone');
                self.openSingleModal(clientId, clientName, clientPhone);
            });

            // Contador de caracteres
            this.elements.singleMessage.on('input', function () {
                const count = $(this).val().length;
                self.elements.singleCharCount.text(count);
                if (count > 4096) {
                    self.elements.singleCharCount.css('color', 'var(--wec-error)');
                } else {
                    self.elements.singleCharCount.css('color', '');
                }
            });

            // Upload de imagem - Click
            $(document).on('click', '.wec-select-image', function () {
                $(this).closest('.wec-image-upload').find('input[type="file"]').click();
            });

            // Upload de imagem - Change
            this.elements.singleImage.on('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    self.handleImageUpload(file, 'single');
                }
            });

            // Drag and Drop
            this.elements.singleDropzone.on('dragover', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            }).on('dragleave drop', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            }).on('drop', function (e) {
                const file = e.originalEvent.dataTransfer.files[0];
                if (file && file.type.startsWith('image/')) {
                    self.handleImageUpload(file, 'single');
                }
            });

            // Remover imagem
            $(document).on('click', '.wec-remove-image', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.clearImage('single');
            });

            // Enviar mensagem individual
            this.elements.singleSendBtn.on('click', function () {
                self.sendSingleMessage();
            });
        },

        /**
         * Inicializa o FAB (Floating Action Button)
         */
        initFAB: function () {
            // Verificar se estamos na listagem
            if (!$('body').hasClass('post-type-wec_client')) return;
            if ($('.wec-fab-container').length) return;

            const fabHTML = `
                <div class="wec-fab-container">
                    <button type="button" class="wec-fab" id="wec-fab-main" title="Disparo em Massa via WhatsApp">
                        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.372-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    </button>
                    <span class="wec-fab-tooltip">Selecione clientes para disparo em massa</span>
                </div>
            `;

            $('body').append(fabHTML);

            // Clique no FAB - abre modal direto se houver seleção
            $('#wec-fab-main').on('click', function () {
                const selected = $('input[name="post[]"]:checked');
                if (selected.length === 0) {
                    // Mostrar tooltip de aviso
                    $('.wec-fab-tooltip').addClass('show');
                    setTimeout(() => $('.wec-fab-tooltip').removeClass('show'), 3000);
                    WEC.showToast('Selecione ao menos um cliente para o disparo em massa', 'warning');
                    return;
                }
                const ids = selected.map(function () { return $(this).val(); }).get();
                window.wecOpenBulkModal(ids);
            });

            // Atualizar estado do FAB baseado em seleção
            $(document).on('change', 'input[name="post[]"], #cb-select-all-1', function () {
                const count = $('input[name="post[]"]:checked').length;
                const $fab = $('#wec-fab-main');
                $fab.toggleClass('has-selection', count > 0);

                // Atualizar badge com contador
                if (count > 0) {
                    if (!$fab.find('.wec-fab-badge').length) {
                        $fab.append('<span class="wec-fab-badge">0</span>');
                    }
                    $fab.find('.wec-fab-badge').text(count);
                } else {
                    $fab.find('.wec-fab-badge').remove();
                }
            });
        },

        /**
         * Inicializa container de toast
         */
        initToast: function () {
            if (!$('.wec-toast-container').length) {
                $('body').append('<div class="wec-toast-container"></div>');
            }
        },

        /**
         * Mostra toast notification
         */
        showToast: function (message, type = 'success') {
            const iconMap = {
                success: 'yes-alt',
                error: 'warning',
                warning: 'info'
            };

            const toast = $(`
                <div class="wec-toast wec-toast-${type}">
                    <span class="dashicons dashicons-${iconMap[type]}"></span>
                    <span>${message}</span>
                </div>
            `);

            $('.wec-toast-container').append(toast);

            setTimeout(() => toast.addClass('show'), 10);
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        },

        /**
         * Testa conexão com a API
         */
        testConnection: function () {
            const btn = this.elements.testConnectionBtn;
            const result = this.elements.testResult;
            const message = $('#wec-test-message');

            btn.prop('disabled', true).addClass('loading');
            btn.find('svg').addClass('wec-spin');
            result.removeClass('show success error');

            $.ajax({
                url: wecAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_test_connection',
                    nonce: wecAjax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        result.addClass('show success');
                        message.html(`${response.data.message}<br><small>Status: ${response.data.state}</small>`);
                    } else {
                        result.addClass('show error');
                        message.html(response.data.message);
                    }
                },
                error: function () {
                    result.addClass('show error');
                    message.html('Erro de conexão com o servidor.');
                },
                complete: function () {
                    btn.prop('disabled', false).removeClass('loading');
                    btn.find('svg').removeClass('wec-spin');
                }
            });
        },

        /**
         * Abre modal de envio individual
         */
        openSingleModal: function (clientId, clientName, clientPhone) {
            this.elements.singleClientId.val(clientId);
            this.elements.singleClientName.text(clientName);
            this.elements.singleClientPhone.text(clientPhone);

            // Avatar com iniciais
            const initials = clientName.split(' ')
                .map(w => w.charAt(0))
                .slice(0, 2)
                .join('')
                .toUpperCase();
            this.elements.singleClientAvatar.text(initials);

            // Limpar campos
            this.elements.singleMessage.val('');
            this.elements.singleCharCount.text('0');
            this.clearImage('single');

            // Mostrar modal
            this.elements.singleModal.addClass('active');
            setTimeout(() => this.elements.singleMessage.focus(), 300);
        },

        /**
         * Fecha todos os modais
         */
        closeAllModals: function () {
            $('.wec-modal').removeClass('active');
            this.clearImage('single');
        },

        /**
         * Exporta leads para CSV
         */
        exportLeads: function ($btn) {
            const category = $btn.data('category') || '';
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update wec-spin" style="vertical-align: middle;"></span> Exportando...');

            $.ajax({
                url: wecAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_export_leads',
                    nonce: wecAjax.nonce,
                    category: category
                },
                success: (response) => {
                    if (response.success && response.data.leads.length > 0) {
                        this.downloadCSV(response.data.leads, response.data.category);
                        this.showToast(`${response.data.total} leads exportados com sucesso!`, 'success');
                    } else {
                        this.showToast('Nenhum lead encontrado para exportar.', 'error');
                    }
                },
                error: () => {
                    this.showToast('Erro ao exportar leads.', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Gera e baixa arquivo CSV
         */
        downloadCSV: function (leads, category) {
            const headers = ['ID', 'Nome', 'Email', 'WhatsApp', 'Descrição', 'Categorias', 'Data Cadastro'];
            const rows = leads.map(lead => [
                lead.id,
                `"${(lead.nome || '').replace(/"/g, '""')}"`,
                lead.email || '',
                lead.whatsapp || '',
                `"${(lead.descricao || '').replace(/"/g, '""')}"`,
                `"${(lead.categorias || '').replace(/"/g, '""')}"`,
                lead.data_cadastro || ''
            ]);

            let csv = '\uFEFF'; // BOM para UTF-8
            csv += headers.join(';') + '\n';
            csv += rows.map(row => row.join(';')).join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const date = new Date().toISOString().slice(0, 10);
            const filename = `zap-leads-${category.toLowerCase().replace(/\s+/g, '-')}-${date}.csv`;

            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        /**
         * Processa upload de imagem
         */
        handleImageUpload: function (file, context) {
            // Validar tipo
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                this.showToast('Tipo de arquivo não permitido. Use JPG, PNG ou GIF.', 'error');
                return;
            }

            // Validar tamanho (5MB)
            if (file.size > 5 * 1024 * 1024) {
                this.showToast('Arquivo muito grande. Máximo 5MB.', 'error');
                return;
            }

            const self = this;
            const reader = new FileReader();

            reader.onload = function (e) {
                const base64Full = e.target.result;
                const base64 = base64Full.split(',')[1]; // Remover prefixo data:

                self.state.imageBase64 = base64;
                self.state.imageMimetype = file.type;
                self.state.imageFilename = file.name;

                // Mostrar preview
                if (context === 'single') {
                    self.elements.singlePreviewImg.attr('src', base64Full);
                    self.elements.singleDropzone.find('.wec-dropzone-content').hide();
                    self.elements.singlePreview.show();
                }
            };

            reader.readAsDataURL(file);
        },

        /**
         * Limpa imagem selecionada
         */
        clearImage: function (context) {
            this.state.imageBase64 = null;
            this.state.imageMimetype = null;
            this.state.imageFilename = null;

            if (context === 'single') {
                this.elements.singleImage.val('');
                this.elements.singlePreviewImg.attr('src', '');
                this.elements.singlePreview.hide();
                this.elements.singleDropzone.find('.wec-dropzone-content').show();
            }
        },

        /**
         * Envia mensagem individual
         */
        sendSingleMessage: function () {
            const clientId = this.elements.singleClientId.val();
            const message = this.elements.singleMessage.val().trim();

            // Validar - precisa ter mensagem OU imagem
            if (!message && !this.state.imageBase64) {
                this.showToast(wecAjax.i18n.messageRequired, 'warning');
                this.elements.singleMessage.focus();
                return;
            }

            const self = this;
            const btn = this.elements.singleSendBtn;

            btn.prop('disabled', true).addClass('loading');

            const data = {
                action: 'wec_send_single',
                nonce: wecAjax.nonce,
                client_id: clientId,
                message: message
            };

            // Adicionar imagem se existir
            if (this.state.imageBase64) {
                data.image_base64 = this.state.imageBase64;
                data.image_mimetype = this.state.imageMimetype;
                data.image_filename = this.state.imageFilename;
            }

            $.ajax({
                url: wecAjax.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        self.closeAllModals();
                    } else {
                        self.showToast(response.data.message, 'error');
                    }
                },
                error: function () {
                    self.showToast('Erro ao enviar mensagem.', 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).removeClass('loading');
                }
            });
        }
    };

    // Expor função para abrir modal bulk
    window.wecOpenBulkModal = function (clientIds) {
        if (typeof window.WECBulkSender !== 'undefined') {
            window.WECBulkSender.open(clientIds);
        }
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function () {
        WEC.init();
    });

    // Expor para debug
    window.WEC = WEC;

})(jQuery);
