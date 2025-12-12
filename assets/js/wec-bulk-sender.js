/**
 * WhatsApp Evolution Clients - Bulk Sender
 * 
 * Sistema de envio em massa de mensagens WhatsApp com suporte a imagens
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11
 */

(function ($) {
    'use strict';

    /**
     * Bulk Sender Controller
     */
    const WECBulkSender = {
        // Estado
        clients: [],
        currentIndex: 0,
        isSending: false,
        isCancelled: false,
        stats: {
            success: 0,
            failed: 0,
            total: 0
        },

        // Estado da imagem
        imageState: {
            base64: null,
            mimetype: null,
            filename: null
        },

        /**
         * Inicialização
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind de eventos
         */
        bindEvents: function () {
            const self = this;

            // Iniciar envio em massa
            $(document).on('click', '.wec-bulk-send-btn', function () {
                self.startBulkSend();
            });

            // Cancelar envio
            $(document).on('click', '.wec-bulk-cancel', function () {
                self.cancelBulkSend();
            });

            // Fechar modal após conclusão
            $(document).on('click', '.wec-bulk-close-btn', function () {
                self.closeModal();
            });

            // Upload de imagem - Click no botão
            $(document).on('click', '#wec-bulk-image-upload .wec-select-image', function () {
                $('#wec-bulk-image').click();
            });

            // Upload de imagem - Change
            $(document).on('change', '#wec-bulk-image', function (e) {
                const file = e.target.files[0];
                if (file) {
                    self.handleImageUpload(file);
                }
            });

            // Drag and Drop para bulk
            $(document).on('dragover', '#wec-bulk-dropzone', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            }).on('dragleave drop', '#wec-bulk-dropzone', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            }).on('drop', '#wec-bulk-dropzone', function (e) {
                e.preventDefault();
                const file = e.originalEvent.dataTransfer.files[0];
                if (file && file.type.startsWith('image/')) {
                    self.handleImageUpload(file);
                }
            });

            // Remover imagem bulk
            $(document).on('click', '.wec-bulk-remove-image', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.clearImage();
            });

            // Contador de caracteres bulk
            $(document).on('input', '#wec-bulk-message', function () {
                const count = $(this).val().length;
                $('#wec-bulk-char-count').text(count);
                if (count > 4096) {
                    $('#wec-bulk-char-count').css('color', 'var(--wec-error)');
                } else {
                    $('#wec-bulk-char-count').css('color', '');
                }
            });
        },

        /**
         * Processa upload de imagem
         */
        handleImageUpload: function (file) {
            const self = this;

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

            const reader = new FileReader();

            reader.onload = function (e) {
                const base64Full = e.target.result;
                const base64 = base64Full.split(',')[1]; // Remover prefixo data:

                self.imageState.base64 = base64;
                self.imageState.mimetype = file.type;
                self.imageState.filename = file.name;

                // Mostrar preview
                $('#wec-bulk-preview-img').attr('src', base64Full);
                $('#wec-bulk-dropzone .wec-dropzone-content').hide();
                $('#wec-bulk-preview').show();

                self.showToast('Imagem anexada com sucesso!', 'success');
            };

            reader.readAsDataURL(file);
        },

        /**
         * Limpa imagem selecionada
         */
        clearImage: function () {
            this.imageState = {
                base64: null,
                mimetype: null,
                filename: null
            };

            $('#wec-bulk-image').val('');
            $('#wec-bulk-preview-img').attr('src', '');
            $('#wec-bulk-preview').hide();
            $('#wec-bulk-dropzone .wec-dropzone-content').show();
        },

        /**
         * Abre o modal de envio em massa
         */
        open: function (clientIds) {
            const self = this;

            // Reset state
            this.clients = [];
            this.currentIndex = 0;
            this.isSending = false;
            this.isCancelled = false;
            this.stats = { success: 0, failed: 0, total: 0 };
            this.clearImage();

            // Mostrar área de composição, esconder outras
            $('#wec-bulk-compose').show();
            $('#wec-bulk-progress').hide();
            $('#wec-bulk-summary').hide();
            $('.wec-bulk-send-btn').show().prop('disabled', false).removeClass('loading');
            $('.wec-bulk-cancel').show();
            $('.wec-bulk-close-btn').hide();

            // Limpar
            $('#wec-bulk-message').val('');
            $('#wec-bulk-char-count').text('0');
            $('#wec-bulk-clients-preview').empty();
            $('#wec-send-list-body').empty();

            // Mostrar modal usando classe active
            $('#wec-bulk-send-modal').addClass('active');

            // Buscar dados dos clientes
            $.ajax({
                url: wecAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_get_clients',
                    nonce: wecAjax.nonce,
                    ids: clientIds
                },
                success: function (response) {
                    if (response.success && response.data.clients) {
                        self.clients = response.data.clients;
                        self.stats.total = self.clients.length;

                        // Atualizar contagem
                        $('#wec-bulk-count').text(self.clients.length);

                        // Preencher preview com avatares
                        const $preview = $('#wec-bulk-clients-preview');
                        self.clients.forEach(function (client) {
                            const initials = client.name.split(' ')
                                .map(w => w.charAt(0))
                                .slice(0, 2)
                                .join('')
                                .toUpperCase();

                            $preview.append(`
                                <li>
                                    <span class="client-avatar">${self.escapeHtml(initials)}</span>
                                    <div class="client-info">
                                        <span class="client-name">${self.escapeHtml(client.name)}</span>
                                        <span class="client-phone">${self.escapeHtml(client.whatsapp)}</span>
                                    </div>
                                </li>
                            `);
                        });

                        // Preencher tabela de progresso
                        const $tbody = $('#wec-send-list-body');
                        self.clients.forEach(function (client) {
                            $tbody.append(self.createClientRow(client));
                        });
                    } else {
                        self.showToast(response.data?.message || 'Erro ao carregar clientes', 'error');
                        self.closeModal();
                    }
                },
                error: function () {
                    self.showToast('Erro de conexão', 'error');
                    self.closeModal();
                }
            });
        },

        /**
         * Cria linha da tabela para um cliente
         */
        createClientRow: function (client) {
            return `
                <tr id="wec-client-row-${client.id}">
                    <td>${this.escapeHtml(client.name)}</td>
                    <td><code>${this.escapeHtml(client.whatsapp)}</code></td>
                    <td class="wec-client-status">
                        <span class="wec-status-badge wec-status-pending">
                            <span class="dashicons dashicons-clock"></span>
                            Pendente
                        </span>
                    </td>
                </tr>
            `;
        },

        /**
         * Inicia o envio em massa
         */
        startBulkSend: function () {
            const message = $('#wec-bulk-message').val().trim();
            const hasImage = this.imageState.base64 !== null;

            // Validar - precisa ter mensagem OU imagem
            if (!message && !hasImage) {
                this.showToast('Digite uma mensagem ou anexe uma imagem', 'warning');
                $('#wec-bulk-message').focus();
                return;
            }

            if (this.clients.length === 0) {
                this.showToast('Nenhum cliente selecionado', 'warning');
                return;
            }

            // Iniciar
            this.isSending = true;
            this.isCancelled = false;
            this.currentIndex = 0;
            this.stats.success = 0;
            this.stats.failed = 0;

            // Atualizar UI
            $('#wec-bulk-compose').hide();
            $('#wec-bulk-progress').show();
            $('.wec-bulk-send-btn').hide();
            $('.wec-progress-fill').css('width', '0%');
            $('.wec-progress-text').text('0%');

            // Processar primeiro cliente
            this.processNextClient(message);
        },

        /**
         * Processa o próximo cliente da fila
         */
        processNextClient: function (message) {
            const self = this;

            // Verificar se foi cancelado
            if (this.isCancelled) {
                this.showSummary();
                return;
            }

            // Verificar se terminou
            if (this.currentIndex >= this.clients.length) {
                this.showSummary();
                return;
            }

            const client = this.clients[this.currentIndex];
            const $row = $('#wec-client-row-' + client.id);

            // Atualizar status para "enviando"
            $row.find('.wec-client-status').html(`
                <span class="wec-status-badge wec-status-sending">
                    <span class="dashicons dashicons-update wec-spin"></span>
                    Enviando...
                </span>
            `);

            // Atualizar progresso
            this.updateProgress();
            $('#wec-current-status').text('Enviando para ' + client.name + '...');

            // Preparar dados
            const data = {
                action: 'wec_send_bulk_single',
                nonce: wecAjax.nonce,
                client_id: client.id,
                message: message
            };

            // Adicionar imagem se existir
            if (this.imageState.base64) {
                data.image_base64 = this.imageState.base64;
                data.image_mimetype = this.imageState.mimetype;
                data.image_filename = this.imageState.filename;
            }

            // Enviar
            $.ajax({
                url: wecAjax.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        self.stats.success++;
                        $row.find('.wec-client-status').html(`
                            <span class="wec-status-badge wec-status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                Enviado!
                            </span>
                        `);
                    } else {
                        self.stats.failed++;
                        $row.find('.wec-client-status').html(`
                            <span class="wec-status-badge wec-status-failed">
                                <span class="dashicons dashicons-warning"></span>
                                Falhou
                            </span>
                        `);
                    }
                },
                error: function () {
                    self.stats.failed++;
                    $row.find('.wec-client-status').html(`
                        <span class="wec-status-badge wec-status-failed">
                            <span class="dashicons dashicons-warning"></span>
                            Erro
                        </span>
                    `);
                },
                complete: function () {
                    // Próximo cliente
                    self.currentIndex++;
                    self.updateProgress();

                    // Se ainda tem clientes e não foi cancelado
                    if (self.currentIndex < self.clients.length && !self.isCancelled) {
                        // Delay aleatório entre 4 e 20 segundos
                        const delay = self.getRandomDelay();
                        self.showDelayCountdown(delay, message);
                    } else {
                        self.showSummary();
                    }
                }
            });
        },

        /**
         * Gera delay aleatório entre 4 e 20 segundos
         */
        getRandomDelay: function () {
            return Math.floor(Math.random() * (20 - 4 + 1)) + 4;
        },

        /**
         * Mostra countdown do delay
         */
        showDelayCountdown: function (seconds, message) {
            const self = this;
            const $delayStatus = $('#wec-delay-status');
            let remaining = seconds;

            $delayStatus.show().text(`Aguardando ${remaining} segundos...`);

            const countdown = setInterval(function () {
                if (self.isCancelled) {
                    clearInterval(countdown);
                    $delayStatus.hide();
                    self.showSummary();
                    return;
                }

                remaining--;
                $delayStatus.text(`Aguardando ${remaining} segundos...`);

                if (remaining <= 0) {
                    clearInterval(countdown);
                    $delayStatus.hide();
                    self.processNextClient(message);
                }
            }, 1000);
        },

        /**
         * Atualiza barra de progresso
         */
        updateProgress: function () {
            const progress = Math.round((this.currentIndex / this.stats.total) * 100);
            $('.wec-progress-fill').css('width', progress + '%');
            $('.wec-progress-text').text(progress + '%');
        },

        /**
         * Cancela o envio em massa
         */
        cancelBulkSend: function () {
            if (this.isSending) {
                this.isCancelled = true;
                $('#wec-current-status').text('Cancelando...');
            } else {
                this.closeModal();
            }
        },

        /**
         * Exibe resumo final
         */
        showSummary: function () {
            this.isSending = false;

            // Atualizar progresso para 100%
            $('.wec-progress-fill').css('width', '100%');
            $('.wec-progress-text').text('100%');

            // Atualizar status
            $('#wec-current-status').text('Envio concluído!');
            $('#wec-delay-status').hide();

            // Mostrar resumo
            $('#wec-bulk-summary').show();
            $('#wec-stat-success').text(this.stats.success);
            $('#wec-stat-failed').text(this.stats.failed);
            $('#wec-stat-total').text(this.stats.total);

            // Atualizar botões
            $('.wec-bulk-cancel').hide();
            $('.wec-bulk-close-btn').show();

            // Toast
            if (this.stats.failed === 0) {
                this.showToast(`Concluído! ${this.stats.success} mensagens enviadas.`, 'success');
            } else {
                this.showToast(`Concluído: ${this.stats.success} enviadas, ${this.stats.failed} falhas.`, 'warning');
            }
        },

        /**
         * Fecha o modal
         */
        closeModal: function () {
            $('#wec-bulk-send-modal').removeClass('active');
            this.isSending = false;
            this.isCancelled = false;
            this.clearImage();
        },

        /**
         * Mostra toast notification
         */
        showToast: function (message, type) {
            if (window.WEC && typeof window.WEC.showToast === 'function') {
                window.WEC.showToast(message, type);
            } else {
                console.log(`[WEC ${type}] ${message}`);
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function (text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Função global para abrir modal
     */
    window.wecOpenBulkModal = function (clientIds) {
        WECBulkSender.open(clientIds);
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function () {
        WECBulkSender.init();
    });

    // Expor para uso externo
    window.WECBulkSender = WECBulkSender;

})(jQuery);

// Adicionar animação de spin via CSS
(function () {
    if (document.getElementById('wec-bulk-styles')) return;

    const style = document.createElement('style');
    style.id = 'wec-bulk-styles';
    style.textContent = `
        .wec-spin {
            animation: wec-spin-animation 1s linear infinite;
        }
        @keyframes wec-spin-animation {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
})();
