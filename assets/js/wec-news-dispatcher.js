/**
 * WhatsApp Evolution Clients - News Dispatcher
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.2.0
 * @updated 2025-12-13 00:15:00
 */

(function($) {
    'use strict';

    const NewsDispatcher = {
        currentPost: null,
        currentBatchId: null,
        isDispatching: false,
        isPaused: false,
        selectedLeads: [],
        allContacts: [],
        selectedContacts: [],
        selectionMode: 'interests',

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            // Abrir off-canvas ao clicar no botão WhatsApp
            $(document).on('click', '.wec-news-dispatch-btn', function(e) {
                e.preventDefault();
                const postData = $(this).data('post');
                self.openPanel(postData);
            });

            // Fechar off-canvas
            $(document).on('click', '.wec-offcanvas-overlay, .wec-offcanvas-close, .wec-offcanvas-cancel', function() {
                if (!self.isDispatching) {
                    self.closePanel();
                }
            });

            // ESC para fechar
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && !self.isDispatching) {
                    self.closePanel();
                }
            });

            // Sistema de Tabs
            $(document).on('click', '.wec-tab', function() {
                const tab = $(this).data('tab');
                self.switchTab(tab);
            });

            // Modo de seleção (por interesse ou individual)
            $(document).on('click', '.wec-mode-btn', function() {
                const mode = $(this).data('mode');
                self.switchSelectionMode(mode);
            });

            // Busca de interesses
            $(document).on('input', '#wec-interest-search', function() {
                self.filterInterests($(this).val());
            });

            // Busca de contatos individuais
            $(document).on('input', '#wec-contact-search', function() {
                self.filterContacts($(this).val());
            });

            // Limpar busca
            $(document).on('click', '#wec-clear-search', function() {
                $('#wec-interest-search').val('').trigger('input');
            });

            // Selecionar todos interesses
            $(document).on('click', '#wec-select-all', function() {
                $('.wec-interest-item:visible input[type="checkbox"]').prop('checked', true);
                self.updateInterestCount();
                self.updateRecipients();
            });

            // Limpar seleção interesses
            $(document).on('click', '#wec-deselect-all', function() {
                $('input[name="wec_interests[]"]').prop('checked', false);
                self.updateInterestCount();
                self.updateRecipients();
            });

            // Selecionar todos contatos
            $(document).on('click', '#wec-select-all-contacts', function() {
                self.selectAllContacts();
            });

            // Limpar seleção contatos
            $(document).on('click', '#wec-deselect-all-contacts, #wec-clear-contacts', function() {
                self.clearContactSelection();
            });

            // Clique em contato individual
            $(document).on('click', '.wec-contact-item', function() {
                self.toggleContact($(this));
            });

            // Checkbox de interesses
            $(document).on('change', 'input[name="wec_interests[]"]', function() {
                self.updateInterestCount();
                self.updateRecipients();
            });

            // Checkbox enviar para todos
            $(document).on('change', '#wec-send-all', function() {
                const isChecked = $(this).is(':checked');
                $('#wec-selection-mode').toggleClass('disabled', isChecked);
                $('#wec-interests-wrapper, #wec-contacts-selection').toggleClass('disabled', isChecked);
                self.updateRecipients();
            });

            // Presets de delay
            $(document).on('click', '.wec-preset', function() {
                const min = $(this).data('min');
                const max = $(this).data('max');
                $('#wec-delay-min').val(min);
                $('#wec-delay-max').val(max);
                $('.wec-preset').removeClass('active');
                $(this).addClass('active');
            });

            // Iniciar disparo
            $(document).on('click', '#wec-start-dispatch', function() {
                self.startDispatch();
            });

            // Pausar disparo
            $(document).on('click', '#wec-pause-dispatch', function() {
                self.pauseDispatch();
            });

            // Cancelar disparo
            $(document).on('click', '#wec-cancel-dispatch', function() {
                if (confirm('Tem certeza que deseja cancelar o disparo?')) {
                    self.cancelDispatch();
                }
            });
        },

        switchTab: function(tab) {
            $('.wec-tab').removeClass('active');
            $('.wec-tab[data-tab="' + tab + '"]').addClass('active');
            $('.wec-tab-content').removeClass('active');
            $('.wec-tab-content[data-tab="' + tab + '"]').addClass('active');
        },

        filterInterests: function(search) {
            const term = search.toLowerCase().trim();
            let visibleCount = 0;

            $('.wec-interest-item').each(function() {
                const name = $(this).data('name');
                const matches = !term || name.indexOf(term) !== -1;
                $(this).toggleClass('hidden', !matches);
                if (matches) visibleCount++;
            });

            // Mostrar/ocultar botão de limpar
            $('#wec-clear-search').toggle(term.length > 0);

            // Mostrar mensagem se não houver resultados
            $('#wec-no-results').toggle(visibleCount === 0 && term.length > 0);
            $('#wec-interests-list').toggle(visibleCount > 0 || term.length === 0);
        },

        updateInterestCount: function() {
            const count = $('input[name="wec_interests[]"]:checked').length;
            $('#wec-interests-selected').text(count);
            $('#wec-tab-badge').text(count).toggle(count > 0);
        },

        // Alternar modo de seleção
        switchSelectionMode: function(mode) {
            this.selectionMode = mode;
            $('#wec-selection-mode').val(mode);
            
            $('.wec-mode-btn').removeClass('active');
            $('.wec-mode-btn[data-mode="' + mode + '"]').addClass('active');
            
            if (mode === 'interests') {
                $('#wec-interests-wrapper').addClass('active');
                $('#wec-contacts-selection').removeClass('active');
            } else {
                $('#wec-interests-wrapper').removeClass('active');
                $('#wec-contacts-selection').addClass('active');
                this.loadAllContacts();
            }
            
            this.updateRecipients();
        },

        // Carregar todos os contatos
        loadAllContacts: function() {
            const self = this;
            
            if (this.allContacts.length > 0) {
                this.renderContacts();
                return;
            }

            $('#wec-contacts-list').html('<div class="wec-contacts-empty"><span class="dashicons dashicons-update spin"></span><p>Carregando contatos...</p></div>');

            $.ajax({
                url: wecNewsDispatcher.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_get_all_contacts',
                    nonce: wecNewsDispatcher.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.allContacts = response.data.contacts;
                        self.renderContacts();
                    } else {
                        $('#wec-contacts-list').html('<div class="wec-contacts-empty"><p>Erro ao carregar contatos.</p></div>');
                    }
                }
            });
        },

        // Renderizar lista de contatos
        renderContacts: function() {
            const self = this;
            let html = '';

            this.allContacts.forEach(function(contact) {
                const isSelected = self.selectedContacts.includes(contact.id);
                html += '<div class="wec-contact-item' + (isSelected ? ' selected' : '') + '" data-id="' + contact.id + '" data-name="' + contact.name.toLowerCase() + '" data-phone="' + contact.phone + '">';
                html += '<span class="wec-contact-check"></span>';
                html += '<span class="wec-contact-avatar">' + contact.initials + '</span>';
                html += '<div class="wec-contact-info">';
                html += '<div class="wec-contact-name">' + contact.name + '</div>';
                html += '<div class="wec-contact-phone">' + contact.phone + '</div>';
                html += '</div>';
                html += '</div>';
            });

            if (html === '') {
                html = '<div class="wec-contacts-empty"><p>Nenhum contato encontrado.</p></div>';
            }

            $('#wec-contacts-list').html(html);
            this.updateContactsInfo();
        },

        // Filtrar contatos
        filterContacts: function(search) {
            const term = search.toLowerCase().trim();
            
            $('.wec-contact-item').each(function() {
                const name = $(this).data('name');
                const phone = $(this).data('phone');
                const matches = !term || name.indexOf(term) !== -1 || phone.indexOf(term) !== -1;
                $(this).toggle(matches);
            });
        },

        // Toggle seleção de contato
        toggleContact: function($item) {
            const id = parseInt($item.data('id'));
            const index = this.selectedContacts.indexOf(id);
            
            if (index > -1) {
                this.selectedContacts.splice(index, 1);
                $item.removeClass('selected');
            } else {
                this.selectedContacts.push(id);
                $item.addClass('selected');
            }
            
            this.updateContactsInfo();
            this.updateRecipients();
        },

        // Selecionar todos contatos
        selectAllContacts: function() {
            const self = this;
            this.selectedContacts = [];
            
            $('.wec-contact-item:visible').each(function() {
                const id = parseInt($(this).data('id'));
                self.selectedContacts.push(id);
                $(this).addClass('selected');
            });
            
            this.updateContactsInfo();
            this.updateRecipients();
        },

        // Limpar seleção de contatos
        clearContactSelection: function() {
            this.selectedContacts = [];
            $('.wec-contact-item').removeClass('selected');
            this.updateContactsInfo();
            this.updateRecipients();
        },

        // Atualizar info de contatos selecionados
        updateContactsInfo: function() {
            const count = this.selectedContacts.length;
            $('#wec-individual-count').text(count);
            $('#wec-selected-contacts-info').toggle(count > 0);
        },

        openPanel: function(postData) {
            this.currentPost = postData;
            
            // Preencher preview do telefone
            $('#wec-preview-title').text(postData.title);
            $('#wec-preview-excerpt').text(postData.excerpt);
            
            // Extrair domínio do URL
            try {
                const url = new URL(postData.url);
                $('#wec-preview-url').text(url.hostname + url.pathname.substring(0, 20) + '...');
            } catch (e) {
                $('#wec-preview-url').text(postData.url.substring(0, 30) + '...');
            }
            
            $('#wec-news-url').attr('href', postData.url);
            $('#wec-news-post-id').val(postData.id);
            $('#wec-news-title').val(postData.title);
            $('#wec-news-excerpt').val(postData.excerpt);

            // Imagem
            if (postData.image) {
                $('#wec-preview-image').html('<img src="' + postData.image + '" alt="">');
            } else {
                $('#wec-preview-image').html('<span class="dashicons dashicons-format-image"></span>');
            }

            // Reset
            this.selectionMode = 'interests';
            this.selectedContacts = [];
            $('input[name="wec_interests[]"]').prop('checked', false);
            $('#wec-send-all').prop('checked', false);
            $('#wec-interests-wrapper').addClass('active');
            $('#wec-contacts-selection').removeClass('active');
            $('.wec-mode-btn').removeClass('active').filter('[data-mode="interests"]').addClass('active');
            $('#wec-interest-search').val('');
            $('#wec-contact-search').val('');
            $('#wec-clear-search').hide();
            $('.wec-interest-item').removeClass('hidden');
            $('#wec-no-results').hide();
            $('#wec-delay-min').val(4);
            $('#wec-delay-max').val(20);
            $('.wec-preset').removeClass('active').filter('[data-min="4"]').addClass('active');
            $('#wec-selected-count').text('0');
            $('#wec-tab-badge').text('0').hide();
            $('#wec-interests-selected').text('0');
            $('#wec-recipients-list').html('<p>Selecione destinatários acima.</p>');
            $('#wec-dispatch-progress').hide();
            $('.wec-tabs, .wec-tab-content').show();
            $('#wec-start-dispatch').show().prop('disabled', false);
            $('#wec-pause-dispatch, #wec-cancel-dispatch').hide();
            $('.wec-offcanvas-cancel').show().text('Fechar');

            // Ir para primeira tab
            this.switchTab('preview');

            // Mostrar off-canvas
            $('#wec-offcanvas-overlay').addClass('active');
            $('#wec-news-dispatch-panel').addClass('active');
        },

        closePanel: function() {
            $('#wec-offcanvas-overlay').removeClass('active');
            $('#wec-news-dispatch-panel').removeClass('active');
            this.currentPost = null;
            this.currentBatchId = null;
            this.isDispatching = false;
            this.isPaused = false;
            this.selectedLeads = [];
            this.selectedContacts = [];
        },

        updateRecipients: function() {
            const self = this;
            const sendAll = $('#wec-send-all').is(':checked');

            // Se é "enviar para todos"
            if (sendAll) {
                $.ajax({
                    url: wecNewsDispatcher.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wec_get_leads_by_interest',
                        nonce: wecNewsDispatcher.nonce,
                        interests: [],
                        send_all: 'true'
                    },
                    success: function(response) {
                        if (response.success) {
                            self.selectedLeads = response.data.leads;
                            $('#wec-selected-count').text(response.data.total);
                            $('#wec-tab-badge').text(response.data.total).show();
                            let html = '';
                            response.data.leads.slice(0, 10).forEach(function(lead) {
                                html += '<span class="wec-lead-item">' + lead.name + '</span>';
                            });
                            if (response.data.leads.length > 10) {
                                html += '<span class="wec-lead-item">+' + (response.data.leads.length - 10) + ' mais</span>';
                            }
                            $('#wec-recipients-list').html(html || '<p>Nenhum contato.</p>');
                        }
                    }
                });
                return;
            }

            // Modo seleção individual
            if (this.selectionMode === 'individual') {
                const count = this.selectedContacts.length;
                this.selectedLeads = [];
                
                // Converter IDs selecionados para objetos lead
                this.selectedContacts.forEach(function(id) {
                    const contact = self.allContacts.find(c => c.id === id);
                    if (contact) {
                        self.selectedLeads.push(contact);
                    }
                });

                $('#wec-selected-count').text(count);
                $('#wec-tab-badge').text(count).toggle(count > 0);

                if (count > 0) {
                    let html = '';
                    this.selectedLeads.slice(0, 10).forEach(function(lead) {
                        html += '<span class="wec-lead-item">' + lead.name + '</span>';
                    });
                    if (count > 10) {
                        html += '<span class="wec-lead-item">+' + (count - 10) + ' mais</span>';
                    }
                    $('#wec-recipients-list').html(html);
                } else {
                    $('#wec-recipients-list').html('<p>Selecione contatos acima.</p>');
                }
                return;
            }

            // Modo seleção por interesse
            const interests = [];
            $('input[name="wec_interests[]"]:checked').each(function() {
                interests.push($(this).val());
            });

            if (interests.length === 0) {
                $('#wec-selected-count').text('0');
                $('#wec-tab-badge').text('0').hide();
                $('#wec-recipients-list').html('<p>Selecione interesses acima.</p>');
                this.selectedLeads = [];
                return;
            }

            $.ajax({
                url: wecNewsDispatcher.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_get_leads_by_interest',
                    nonce: wecNewsDispatcher.nonce,
                    interests: interests,
                    send_all: 'false'
                },
                success: function(response) {
                    if (response.success) {
                        self.selectedLeads = response.data.leads;
                        $('#wec-selected-count').text(response.data.total);
                        $('#wec-tab-badge').text(response.data.total).toggle(response.data.total > 0);

                        if (response.data.leads.length > 0) {
                            let html = '';
                            response.data.leads.slice(0, 10).forEach(function(lead) {
                                html += '<span class="wec-lead-item">' + lead.name + '</span>';
                            });
                            if (response.data.leads.length > 10) {
                                html += '<span class="wec-lead-item">+' + (response.data.leads.length - 10) + ' mais</span>';
                            }
                            $('#wec-recipients-list').html(html);
                        } else {
                            $('#wec-recipients-list').html('<p>' + wecNewsDispatcher.i18n.noLeads + '</p>');
                        }
                    }
                }
            });
        },

        startDispatch: function() {
            const self = this;

            // Validar seleção
            if (this.selectedLeads.length === 0) {
                alert('Selecione ao menos um destinatário.');
                return;
            }

            // Confirmar
            if (!confirm(wecNewsDispatcher.i18n.confirmDispatch.replace('%d', this.selectedLeads.length))) {
                return;
            }

            // Coletar dados
            const sendAll = $('#wec-send-all').is(':checked');
            const interests = [];
            $('input[name="wec_interests[]"]:checked').each(function() {
                interests.push($(this).val());
            });

            const delayMin = parseInt($('#wec-delay-min').val()) || 4;
            const delayMax = parseInt($('#wec-delay-max').val()) || 20;

            // Dados do disparo
            const dispatchData = {
                action: 'wec_create_news_dispatch',
                nonce: wecNewsDispatcher.nonce,
                post_id: this.currentPost.id,
                delay_min: delayMin,
                delay_max: delayMax,
                selection_mode: this.selectionMode
            };

            if (sendAll) {
                dispatchData.send_all = 'true';
            } else if (this.selectionMode === 'individual') {
                dispatchData.lead_ids = this.selectedContacts;
            } else {
                dispatchData.interests = interests;
            }

            // Criar disparo
            $.ajax({
                url: wecNewsDispatcher.ajaxUrl,
                type: 'POST',
                data: dispatchData,
                success: function(response) {
                    if (response.success) {
                        self.currentBatchId = response.data.batch_id;
                        self.isDispatching = true;
                        self.showDispatchProgress(response.data.total);
                        self.processNextItem();
                    } else {
                        alert(response.data.message || 'Erro ao iniciar disparo');
                    }
                },
                error: function() {
                    alert('Erro de conexão');
                }
            });
        },

        showDispatchProgress: function(total) {
            // Esconder tabs e mostrar progresso
            $('.wec-tabs, .wec-tab-content').hide();
            $('#wec-dispatch-progress').show();
            $('#wec-start-dispatch').hide();
            $('#wec-pause-dispatch, #wec-cancel-dispatch').show();
            $('.wec-modal-cancel').hide();

            // Reset progresso
            $('#wec-progress-fill').css('width', '0%');
            $('#wec-progress-percent').text('0%');
            $('#wec-stat-sent').text('0');
            $('#wec-stat-failed').text('0');
            $('#wec-stat-pending').text(total);
            $('#wec-dispatch-log').empty();
            $('#wec-next-dispatch').show().text('Iniciando disparos...');
        },

        processNextItem: function() {
            const self = this;

            if (!this.isDispatching || this.isPaused) {
                return;
            }

            $.ajax({
                url: wecNewsDispatcher.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_process_queue_item',
                    nonce: wecNewsDispatcher.nonce,
                    batch_id: this.currentBatchId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.completed) {
                            // Disparo completo
                            self.isDispatching = false;
                            self.showCompleted();
                        } else {
                            // Atualizar UI
                            self.updateProgressUI(response.data);

                            // Aguardar delay e processar próximo
                            const delay = response.data.delay * 1000;
                            self.showNextDelay(response.data.delay);

                            setTimeout(function() {
                                self.processNextItem();
                            }, delay);
                        }
                    } else {
                        // Erro ou batch pausado/cancelado
                        if (response.data.status === 'paused') {
                            self.isPaused = true;
                        } else if (response.data.status === 'cancelled') {
                            self.isDispatching = false;
                            self.showCancelled();
                        }
                    }
                },
                error: function() {
                    // Tentar novamente após 5 segundos
                    setTimeout(function() {
                        self.processNextItem();
                    }, 5000);
                }
            });
        },

        updateProgressUI: function(data) {
            const item = data.item;
            const progress = data.progress;

            // Atualizar barra de progresso e porcentagem
            $('#wec-progress-fill').css('width', progress.percentage + '%');
            $('#wec-progress-percent').text(Math.round(progress.percentage) + '%');

            // Atualizar estatísticas
            $('#wec-stat-sent').text(progress.sent);
            $('#wec-stat-failed').text(progress.failed);
            $('#wec-stat-pending').text(progress.pending);

            // Adicionar log
            const statusClass = item.status === 'sent' ? 'sent' : 'failed';
            const statusIcon = item.status === 'sent' ? '✅' : '❌';
            const time = new Date().toLocaleTimeString();
            
            let logHtml = '<div class="wec-log-item ' + statusClass + '">';
            logHtml += statusIcon + ' ' + time + ' - ' + item.lead_name + ' (' + item.lead_phone + ')';
            if (item.error) {
                logHtml += ' - ' + item.error;
            }
            logHtml += '</div>';

            $('#wec-dispatch-log').prepend(logHtml);
        },

        showNextDelay: function(seconds) {
            const self = this;
            let remaining = seconds;

            const updateCountdown = function() {
                if (remaining > 0 && self.isDispatching && !self.isPaused) {
                    $('#wec-next-dispatch').html(
                        '⏳ ' + wecNewsDispatcher.i18n.waitingNext.replace('%d', remaining)
                    ).show();
                    remaining--;
                    setTimeout(updateCountdown, 1000);
                } else {
                    $('#wec-next-dispatch').hide();
                }
            };

            updateCountdown();
        },

        showCompleted: function() {
            $('#wec-progress-fill').css('width', '100%');
            $('#wec-progress-percent').text('100%');
            $('#wec-next-dispatch')
                .removeClass('wec-next-dispatch')
                .addClass('wec-dispatch-completed')
                .html('🎉 ' + wecNewsDispatcher.i18n.completed)
                .show();
            $('#wec-pause-dispatch, #wec-cancel-dispatch').hide();
            $('.wec-modal-cancel').show().text('Fechar');
        },

        showCancelled: function() {
            $('#wec-next-dispatch').html('❌ Disparo cancelado').show();
            $('#wec-pause-dispatch, #wec-cancel-dispatch').hide();
            $('.wec-modal-cancel').show().text('Fechar');
        },

        pauseDispatch: function() {
            const self = this;
            this.isPaused = !this.isPaused;

            if (this.isPaused) {
                $('#wec-pause-dispatch').html('<span class="dashicons dashicons-controls-play"></span> Retomar');
                $('#wec-next-dispatch').html('⏸️ Disparo pausado').show();

                // Notificar servidor
                $.ajax({
                    url: wecNewsDispatcher.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wec_pause_batch',
                        nonce: wecNewsDispatcher.nonce,
                        batch_id: this.currentBatchId
                    }
                });
            } else {
                $('#wec-pause-dispatch').html('<span class="dashicons dashicons-controls-pause"></span> Pausar');
                this.processNextItem();
            }
        },

        cancelDispatch: function() {
            const self = this;
            this.isDispatching = false;

            $.ajax({
                url: wecNewsDispatcher.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wec_cancel_batch',
                    nonce: wecNewsDispatcher.nonce,
                    batch_id: this.currentBatchId
                },
                success: function() {
                    self.showCancelled();
                }
            });
        }
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        NewsDispatcher.init();
    });

})(jQuery);
