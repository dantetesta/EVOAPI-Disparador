/**
 * Dashboard Admin - WhatsApp News Dispatcher
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.5.0
 * @created 2025-12-13 08:55:00
 */

(function() {
    'use strict';

    console.log('[WEC Dashboard] Iniciando...');

    const Dashboard = {
        // State
        currentPost: null,
        selectedLeads: [],
        selectedContacts: [],
        allContacts: [],
        selectionMode: 'interests',
        isDispatching: false,
        isPaused: false,
        currentBatchId: null,

        // Init
        init: function() {
            console.log('[WEC Dashboard] Init chamado');
            this.bindEvents();
            this.loadTodayStats();
            this.startMonitorPolling();
            this.checkUrlTab();
            console.log('[WEC Dashboard] Eventos bindados');
        },

        // Verifica parâmetro tab na URL para navegar à aba correta
        checkUrlTab: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                this.navigateTo(tab);
            }
        },

        // Bind Events
        bindEvents: function() {
            const self = this;

            // Mobile menu toggle
            document.getElementById('menuToggle')?.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
            });

            // Navegação do menu lateral
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.dataset.page;
                    self.navigateTo(page);
                });
            });

            // Filtros de interesse hierárquico na aba Contatos
            const parentSelect = document.getElementById('filterInterestParent');
            const childSelect = document.getElementById('filterInterestChild');
            
            if (parentSelect) {
                // Carregar dados de subcategorias
                const childrenDataEl = document.getElementById('interestChildrenData');
                const countsDataEl = document.getElementById('interestCountsData');
                self.interestChildren = childrenDataEl ? JSON.parse(childrenDataEl.textContent) : {};
                self.interestCounts = countsDataEl ? JSON.parse(countsDataEl.textContent) : {};
                
                parentSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const termId = selectedOption.dataset.termId;
                    const hasChildren = selectedOption.dataset.hasChildren === '1';
                    const interest = this.value;
                    
                    // Mostrar/esconder select de subcategorias
                    if (childSelect) {
                        if (hasChildren && termId && self.interestChildren[termId]) {
                            // Preencher subcategorias
                            let options = '<option value="">Todas as subcategorias</option>';
                            self.interestChildren[termId].forEach(child => {
                                const count = self.interestCounts[child.term_id] || 0;
                                options += `<option value="${child.slug}">${child.name} (${count})</option>`;
                            });
                            childSelect.innerHTML = options;
                            childSelect.style.display = 'block';
                            childSelect.value = '';
                        } else {
                            childSelect.style.display = 'none';
                        }
                    }
                    
                    // Filtrar leads
                    self.filterLeadsByInterest(interest);
                });
                
                // Evento do select de subcategorias
                if (childSelect) {
                    childSelect.addEventListener('change', function() {
                        const interest = this.value || parentSelect.value;
                        self.filterLeadsByInterest(interest);
                    });
                }
            }

            // Busca de leads
            document.getElementById('searchLeads')?.addEventListener('input', function(e) {
                self.filterLeadsByName(e.target.value);
            });

            // Limpar log do monitor
            document.getElementById('btnClearLog')?.addEventListener('click', function() {
                document.getElementById('realtimeLog').innerHTML = `
                    <div class="log-entry info">
                        <span class="log-time">${new Date().toLocaleTimeString()}</span>
                        <span class="log-message">Log limpo. Aguardando disparos...</span>
                    </div>
                `;
            });

            // Search posts
            document.getElementById('searchPosts')?.addEventListener('input', function(e) {
                self.filterPosts(e.target.value);
            });

            // Dispatch buttons
            const dispatchBtns = document.querySelectorAll('.btn-dispatch, .btn-dispatch-small');
            console.log('[WEC Dashboard] Botões de disparo encontrados:', dispatchBtns.length);
            
            dispatchBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const postId = this.dataset.postId;
                    const postTitle = this.dataset.postTitle;
                    console.log('[WEC Dashboard] Clicou em disparar:', postId, postTitle);
                    self.openDispatchPanel(postId, postTitle);
                });
            });

            // Off-canvas controls
            document.getElementById('offcanvasClose')?.addEventListener('click', () => self.closeDispatchPanel());
            document.getElementById('offcanvasOverlay')?.addEventListener('click', () => self.closeDispatchPanel());
            document.getElementById('btnCancel')?.addEventListener('click', () => self.closeDispatchPanel());

            // Tabs
            document.querySelectorAll('.offcanvas-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    self.switchTab(this.dataset.tab);
                });
            });

            // Selection mode
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    self.setSelectionMode(this.dataset.mode);
                });
            });

            // Send all checkbox
            document.getElementById('sendAll')?.addEventListener('change', function() {
                self.toggleSendAll(this.checked);
            });

            // Interests/Categories/Contacts selection
            document.querySelectorAll('input[name="interests[]"]').forEach(input => {
                input.addEventListener('change', () => self.updateRecipients());
            });
            document.querySelectorAll('input[name="categories[]"]').forEach(input => {
                input.addEventListener('change', () => self.updateRecipients());
            });

            // Search interests
            document.getElementById('searchInterests')?.addEventListener('input', function(e) {
                self.filterInterests(e.target.value);
            });

            // Select all / clear interests
            document.getElementById('selectAllInterests')?.addEventListener('click', () => self.selectAllInterests());
            document.getElementById('clearInterests')?.addEventListener('click', () => self.clearInterests());

            // Search contacts
            document.getElementById('searchContacts')?.addEventListener('input', function(e) {
                self.filterContacts(e.target.value);
            });

            // Select all / clear contacts
            document.getElementById('selectAllContacts')?.addEventListener('click', () => self.selectAllContacts());
            document.getElementById('clearContacts')?.addEventListener('click', () => self.clearContacts());

            // Start dispatch
            document.getElementById('btnStartDispatch')?.addEventListener('click', () => self.startDispatch());

            // Pause/Cancel dispatch
            document.getElementById('btnPause')?.addEventListener('click', () => self.togglePause());
            document.getElementById('btnCancelDispatch')?.addEventListener('click', () => self.cancelDispatch());
        },

        // Filtrar leads por interesse
        filterLeadsByInterest: function(interest) {
            const rows = document.querySelectorAll('#leadsTableBody tr');
            
            rows.forEach(row => {
                if (interest === 'all') {
                    row.style.display = '';
                } else {
                    const rowInterests = row.dataset.interests || '';
                    const hasInterest = rowInterests.split(',').includes(interest);
                    row.style.display = hasInterest ? '' : 'none';
                }
            });
        },

        // Filtrar leads por nome
        filterLeadsByName: function(query) {
            const rows = document.querySelectorAll('#leadsTableBody tr');
            const q = query.toLowerCase();
            
            rows.forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                row.style.display = name.includes(q) ? '' : 'none';
            });
        },

        // Navegar para uma página
        navigateTo: function(page) {
            console.log('[WEC Dashboard] Navegando para:', page);
            
            // Atualizar menu
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.toggle('active', item.dataset.page === page);
            });
            
            // Atualizar páginas
            document.querySelectorAll('.page-content').forEach(content => {
                content.classList.toggle('active', content.dataset.page === page);
            });
            
            // Atualizar título
            const titles = {
                'monitor': 'Monitor de Disparos',
                'posts': 'Notícias para Disparo',
                'leads': 'Contatos',
                'history': 'Histórico de Disparos',
                'settings': 'Configurações'
            };
            document.querySelector('.page-title').textContent = titles[page] || page;
            
            // Fechar sidebar no mobile
            document.querySelector('.sidebar').classList.remove('active');
        },

        // Atualiza opção de imagem baseado no post
        updateImageOption: function(post) {
            const checkbox = document.getElementById('includeImage');
            const status = document.getElementById('imageStatus');
            const imgPreview = document.getElementById('previewImage');
            
            if (!checkbox || !status) return;
            
            if (post.image) {
                checkbox.disabled = false;
                checkbox.checked = true;
                status.textContent = 'A imagem será otimizada (600px, 80%)';
                status.style.color = '';
                if (imgPreview) {
                    imgPreview.innerHTML = `<img src="${post.image}" alt="">`;
                    imgPreview.style.display = 'block';
                }
            } else {
                checkbox.disabled = true;
                checkbox.checked = false;
                status.textContent = 'Este post não possui imagem destacada';
                status.style.color = '#ef4444';
                if (imgPreview) {
                    imgPreview.innerHTML = '<i class="fas fa-image"></i>';
                    imgPreview.style.display = 'none';
                }
            }
            
            // Atualizar visibilidade ao mudar toggle
            checkbox.onchange = function() {
                if (imgPreview) {
                    imgPreview.style.display = this.checked && post.image ? 'block' : 'none';
                }
            };
        },

        // Filter posts
        filterPosts: function(query) {
            const cards = document.querySelectorAll('.post-card');
            const q = query.toLowerCase();
            
            cards.forEach(card => {
                const title = card.querySelector('.post-title')?.textContent.toLowerCase() || '';
                const excerpt = card.querySelector('.post-excerpt')?.textContent.toLowerCase() || '';
                const match = title.includes(q) || excerpt.includes(q);
                card.style.display = match ? '' : 'none';
            });
        },

        // Open dispatch panel
        openDispatchPanel: function(postId, postTitle) {
            const self = this;
            console.log('[WEC Dashboard] Abrindo painel para post:', postId);
            
            // Fetch post data
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_post_data',
                    nonce: WEC_DASHBOARD.nonce,
                    post_id: postId
                })
            })
            .then(res => res.json())
            .then(data => {
                console.log('[WEC Dashboard] Dados do post:', data);
                if (data.success) {
                    self.currentPost = data.data;
                    self.updatePreview(data.data);
                    self.updateImageOption(data.data);
                    self.showPanel();
                    self.loadAllContacts();
                } else {
                    console.error('[WEC Dashboard] Erro ao buscar post:', data);
                    alert('Erro ao carregar dados do post');
                }
            })
            .catch(err => {
                console.error('[WEC Dashboard] Erro fetch:', err);
                alert('Erro de conexão: ' + err.message);
            });
        },

        // Update preview
        updatePreview: function(post) {
            const imgEl = document.getElementById('previewImage');
            if (post.image) {
                imgEl.innerHTML = `<img src="${post.image}" alt="">`;
            } else {
                imgEl.innerHTML = '<i class="fas fa-image"></i>';
            }
            
            document.getElementById('previewTitle').textContent = post.title;
            document.getElementById('previewExcerpt').textContent = post.excerpt;
            document.getElementById('previewUrl').textContent = new URL(post.url).hostname + '/...';
            document.getElementById('previewLink').href = post.url;
        },

        // Show/hide panel
        showPanel: function() {
            document.getElementById('offcanvasOverlay').classList.add('active');
            document.getElementById('offcanvasPanel').classList.add('active');
            document.body.style.overflow = 'hidden';
        },

        closeDispatchPanel: function() {
            document.getElementById('offcanvasOverlay').classList.remove('active');
            document.getElementById('offcanvasPanel').classList.remove('active');
            document.body.style.overflow = '';
            this.resetState();
        },

        // Switch tab
        switchTab: function(tab) {
            document.querySelectorAll('.offcanvas-tab').forEach(t => {
                t.classList.toggle('active', t.dataset.tab === tab);
            });
            document.querySelectorAll('.offcanvas-content').forEach(c => {
                c.classList.toggle('active', c.dataset.tab === tab);
            });
        },

        // Selection mode
        setSelectionMode: function(mode) {
            this.selectionMode = mode;
            
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });
            
            document.getElementById('interestsWrapper').classList.toggle('active', mode === 'interests');
            document.getElementById('contactsWrapper').classList.toggle('active', mode === 'individual');
            
            this.updateRecipients();
        },

        // Toggle send all
        toggleSendAll: function(checked) {
            const modeDiv = document.getElementById('selectionMode');
            const interestsDiv = document.getElementById('interestsWrapper');
            const contactsDiv = document.getElementById('contactsWrapper');
            
            if (checked) {
                modeDiv.style.display = 'none';
                interestsDiv.classList.remove('active');
                contactsDiv.classList.remove('active');
            } else {
                modeDiv.style.display = 'grid';
                this.setSelectionMode(this.selectionMode);
            }
            
            this.updateRecipients();
        },

        // Load all contacts
        loadAllContacts: function() {
            const self = this;
            
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_all_contacts',
                    nonce: WEC_DASHBOARD.nonce
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    self.allContacts = data.data.contacts;
                    self.renderContacts();
                }
            });
        },

        // Render contacts list
        renderContacts: function() {
            const list = document.getElementById('contactsList');
            if (!list) return;
            
            let html = '';
            this.allContacts.forEach(contact => {
                const initials = contact.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                const checked = this.selectedContacts.includes(contact.id) ? 'checked' : '';
                
                html += `
                    <label class="contact-item">
                        <input type="checkbox" value="${contact.id}" ${checked}>
                        <div class="contact-avatar">${initials}</div>
                        <span class="contact-name">${contact.name}</span>
                        <span class="contact-phone">${contact.phone}</span>
                    </label>
                `;
            });
            
            list.innerHTML = html || '<p style="padding:15px;text-align:center;">Nenhum contato encontrado.</p>';
            
            // Bind events
            list.querySelectorAll('input[type="checkbox"]').forEach(input => {
                input.addEventListener('change', () => {
                    const id = parseInt(input.value);
                    if (input.checked) {
                        if (!this.selectedContacts.includes(id)) {
                            this.selectedContacts.push(id);
                        }
                    } else {
                        this.selectedContacts = this.selectedContacts.filter(c => c !== id);
                    }
                    this.updateRecipients();
                });
            });
        },

        // Filter interests
        filterInterests: function(query) {
            const items = document.querySelectorAll('.interest-item');
            const q = query.toLowerCase();
            
            items.forEach(item => {
                const name = item.querySelector('.interest-name')?.textContent.toLowerCase() || '';
                item.style.display = name.includes(q) ? '' : 'none';
            });
        },

        // Filter contacts
        filterContacts: function(query) {
            const items = document.querySelectorAll('.contact-item');
            const q = query.toLowerCase();
            
            items.forEach(item => {
                const name = item.querySelector('.contact-name')?.textContent.toLowerCase() || '';
                const phone = item.querySelector('.contact-phone')?.textContent.toLowerCase() || '';
                item.style.display = (name.includes(q) || phone.includes(q)) ? '' : 'none';
            });
        },

        // Select all / clear
        selectAllInterests: function() {
            document.querySelectorAll('input[name="interests[]"]').forEach(input => {
                input.checked = true;
            });
            this.updateRecipients();
        },

        clearInterests: function() {
            document.querySelectorAll('input[name="interests[]"]').forEach(input => {
                input.checked = false;
            });
            this.updateRecipients();
        },

        selectAllContacts: function() {
            this.selectedContacts = this.allContacts.map(c => c.id);
            this.renderContacts();
            this.updateRecipients();
        },

        clearContacts: function() {
            this.selectedContacts = [];
            this.renderContacts();
            this.updateRecipients();
        },

        // Update recipients
        updateRecipients: function() {
            const self = this;
            const sendAll = document.getElementById('sendAll')?.checked;

            if (sendAll) {
                this.fetchLeadsByInterest([], true);
                return;
            }

            if (this.selectionMode === 'individual') {
                const count = this.selectedContacts.length;
                this.selectedLeads = [];
                
                this.selectedContacts.forEach(id => {
                    const contact = this.allContacts.find(c => c.id === id);
                    if (contact) this.selectedLeads.push(contact);
                });

                this.updateRecipientsUI(count);
                return;
            }

            // By interests e categories (filtro combinado)
            const interests = [];
            document.querySelectorAll('input[name="interests[]"]:checked').forEach(input => {
                interests.push(input.value);
            });
            
            const categories = [];
            document.querySelectorAll('input[name="categories[]"]:checked').forEach(input => {
                categories.push(input.value);
            });

            if (interests.length === 0 && categories.length === 0) {
                this.selectedLeads = [];
                this.updateRecipientsUI(0);
                return;
            }

            this.fetchLeadsByFilters(interests, categories, false);
        },

        fetchLeadsByFilters: function(interests, categories, sendAll) {
            const self = this;
            
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_leads_by_filters',
                    nonce: WEC_DASHBOARD.nonce,
                    interests: JSON.stringify(interests),
                    categories: JSON.stringify(categories),
                    send_all: sendAll ? 'true' : 'false'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    self.selectedLeads = data.data.leads;
                    self.updateRecipientsUI(data.data.total);
                }
            });
        },

        fetchLeadsByInterest: function(interests, sendAll) {
            const self = this;
            
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_leads_by_interest',
                    nonce: WEC_DASHBOARD.nonce,
                    interests: JSON.stringify(interests),
                    send_all: sendAll ? 'true' : 'false'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    self.selectedLeads = data.data.leads;
                    self.updateRecipientsUI(data.data.total);
                }
            });
        },

        updateRecipientsUI: function(count) {
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('recipientsBadge').textContent = count;
            document.getElementById('interestsCount').textContent = document.querySelectorAll('input[name="interests[]"]:checked').length;
            document.getElementById('contactsCount').textContent = this.selectedContacts.length;

            const list = document.getElementById('recipientsList');
            if (count > 0 && this.selectedLeads.length > 0) {
                let html = '';
                this.selectedLeads.slice(0, 5).forEach(lead => {
                    html += `<span style="display:inline-block;background:#e5f6ed;color:#128C7E;padding:2px 8px;border-radius:10px;font-size:12px;margin:2px;">${lead.name}</span>`;
                });
                if (count > 5) {
                    html += `<span style="display:inline-block;background:#e5f6ed;color:#128C7E;padding:2px 8px;border-radius:10px;font-size:12px;margin:2px;">+${count - 5} mais</span>`;
                }
                list.innerHTML = html;
            } else {
                list.innerHTML = '<p>Selecione destinatários acima.</p>';
            }
        },

        // Start dispatch (background)
        startDispatch: function() {
            if (this.selectedLeads.length === 0) {
                alert(WEC_DASHBOARD.i18n.noRecipients);
                return;
            }

            if (!confirm(WEC_DASHBOARD.i18n.confirmDispatch.replace('%d', this.selectedLeads.length) + '\n\nO disparo será processado em segundo plano. Você pode fechar esta janela.')) {
                return;
            }

            const sendAll = document.getElementById('sendAll')?.checked;
            const interests = [];
            document.querySelectorAll('input[name="interests[]"]:checked').forEach(input => {
                interests.push(input.value);
            });

            const delayMin = parseInt(document.getElementById('delayMin')?.value) || 4;
            const delayMax = parseInt(document.getElementById('delayMax')?.value) || 20;
            const includeImage = document.getElementById('includeImage')?.checked;

            // Usar disparo em background
            const data = {
                action: 'wec_start_background_dispatch',
                nonce: WEC_DASHBOARD.nonce,
                post_id: this.currentPost.id,
                delay_min: delayMin,
                delay_max: delayMax,
                selection_mode: this.selectionMode,
                include_image: includeImage ? 'true' : 'false'
            };

            if (sendAll) {
                data.send_all = 'true';
            } else if (this.selectionMode === 'individual') {
                data.lead_ids = JSON.stringify(this.selectedContacts);
            } else {
                data.interests = JSON.stringify(interests);
            }

            const self = this;
            console.log('[WEC Dashboard] Iniciando disparo em background...', data);
            
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(res => res.json())
            .then(response => {
                console.log('[WEC Dashboard] Resposta:', response);
                if (response.success) {
                    self.currentBatchId = response.data.batch_id;
                    self.isDispatching = true;
                    self.showProgressPanel(response.data.total);
                    self.addLogEntry('🚀 Disparo iniciado em background!', 'success');
                    self.addLogEntry('Você pode fechar esta janela. O disparo continuará no servidor.', '');
                    // Iniciar polling para atualizar status
                    self.startStatusPolling();
                } else {
                    alert(response.data?.message || 'Erro ao iniciar disparo');
                }
            })
            .catch(err => {
                console.error('[WEC Dashboard] Erro:', err);
                alert('Erro de conexão: ' + err.message);
            });
        },

        // Show progress panel
        showProgressPanel: function(total) {
            document.getElementById('progressPanel').classList.add('active');
            document.getElementById('sentCount').textContent = '0';
            document.getElementById('failedCount').textContent = '0';
            document.getElementById('remainingCount').textContent = total;
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressLog').innerHTML = '';
            this.totalItems = total;
            this.sentItems = 0;
            this.failedItems = 0;
        },

        // Start status polling (para disparo em background)
        startStatusPolling: function() {
            const self = this;
            this.pollingInterval = setInterval(() => {
                self.checkDispatchStatus();
            }, 3000); // Atualiza a cada 3 segundos
        },

        // Stop status polling
        stopStatusPolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },

        // Check dispatch status
        checkDispatchStatus: function() {
            if (!this.currentBatchId) return;

            const self = this;
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_dispatch_status',
                    nonce: WEC_DASHBOARD.nonce,
                    batch_id: this.currentBatchId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    const data = response.data;
                    
                    // Atualizar contadores
                    document.getElementById('sentCount').textContent = data.sent;
                    document.getElementById('failedCount').textContent = data.failed;
                    document.getElementById('remainingCount').textContent = data.pending;
                    document.getElementById('progressBar').style.width = data.progress + '%';

                    // Adicionar logs recentes
                    if (data.recent_logs && data.recent_logs.length > 0) {
                        const lastLog = data.recent_logs[0];
                        if (lastLog && lastLog.lead_name !== self.lastLoggedName) {
                            self.lastLoggedName = lastLog.lead_name;
                            const status = lastLog.status === 'sent' ? 'success' : 'error';
                            const icon = lastLog.status === 'sent' ? '✓' : '✗';
                            self.addLogEntry(`${icon} ${lastLog.lead_name}`, status);
                        }
                    }

                    // Verificar se completou (só uma vez)
                    if (data.is_complete && self.isDispatching) {
                        self.stopStatusPolling();
                        self.finishDispatch();
                    }
                }
            })
            .catch(err => {
                console.error('[WEC Dashboard] Erro ao verificar status:', err);
            });
        },

        // Update progress
        updateProgress: function(data) {
            if (data.item.status === 'sent') {
                this.sentItems++;
                this.addLogEntry(`✓ Enviado para ${data.item.lead_name}`, 'success');
            } else {
                this.failedItems++;
                this.addLogEntry(`✗ Falha: ${data.item.lead_name} - ${data.item.error || 'Erro'}`, 'error');
            }

            document.getElementById('sentCount').textContent = this.sentItems;
            document.getElementById('failedCount').textContent = this.failedItems;
            document.getElementById('remainingCount').textContent = this.totalItems - this.sentItems - this.failedItems;
            
            const progress = ((this.sentItems + this.failedItems) / this.totalItems) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
        },

        // Add log entry
        addLogEntry: function(message, type) {
            const log = document.getElementById('progressLog');
            const entry = document.createElement('div');
            entry.className = 'log-entry ' + type;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
        },

        // Finish dispatch
        finishDispatch: function() {
            if (!this.isDispatching) return; // Evitar múltiplas chamadas
            
            this.isDispatching = false;
            this.currentBatchId = null;
            this.stopStatusPolling();
            this.addLogEntry('🎉 ' + WEC_DASHBOARD.i18n.dispatchComplete, 'success');
            
            // Mudar título e botões para "Concluído"
            const header = document.querySelector('.progress-header h3');
            if (header) header.innerHTML = '<i class="fas fa-check-circle"></i> Disparo Concluído!';
            
            // Substituir botões por "Fechar"
            const actionsDiv = document.querySelector('.progress-actions');
            if (actionsDiv) {
                actionsDiv.innerHTML = `
                    <button class="btn-close-dispatch" onclick="Dashboard.closeProgressPanel()">
                        <i class="fas fa-check"></i> Fechar
                    </button>
                `;
            }
            
            // Update today stats
            this.loadTodayStats();
        },
        
        // Fechar painel de progresso
        closeProgressPanel: function() {
            const panel = document.getElementById('progressPanel');
            if (panel) panel.classList.remove('active');
        },

        // Toggle pause
        togglePause: function() {
            this.isPaused = !this.isPaused;
            const btn = document.getElementById('btnPause');
            
            if (this.isPaused) {
                btn.innerHTML = '<i class="fas fa-play"></i> Continuar';
                this.addLogEntry('⏸ Disparo pausado', '');
            } else {
                btn.innerHTML = '<i class="fas fa-pause"></i> Pausar';
                this.addLogEntry('▶ Disparo retomado', '');
                this.processNextItem();
            }
        },

        // Cancel dispatch
        cancelDispatch: function() {
            if (!confirm('Cancelar o disparo?')) return;
            
            this.isDispatching = false;
            this.addLogEntry('🛑 ' + WEC_DASHBOARD.i18n.dispatchCancelled, 'error');
            
            // Update batch status
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_cancel_dispatch',
                    nonce: WEC_DASHBOARD.nonce,
                    batch_id: this.currentBatchId
                })
            });
        },

        // Reset state
        resetState: function() {
            this.currentPost = null;
            this.selectedLeads = [];
            this.selectedContacts = [];
            this.isDispatching = false;
            this.isPaused = false;
            this.currentBatchId = null;
            
            document.getElementById('progressPanel')?.classList.remove('active');
            document.getElementById('sendAll').checked = false;
            this.toggleSendAll(false);
            this.clearInterests();
            this.clearContacts();
        },

        // Load today stats
        loadTodayStats: function() {
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_today_stats',
                    nonce: WEC_DASHBOARD.nonce
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalDispatched').textContent = data.data.total || 0;
                }
            });
        },

        // Iniciar polling do monitor
        startMonitorPolling: function() {
            const self = this;
            // Atualizar imediatamente
            this.updateMonitor();
            // Polling a cada 3 segundos
            this.monitorInterval = setInterval(() => {
                self.updateMonitor();
            }, 3000);
        },

        // Atualizar monitor em tempo real
        updateMonitor: function() {
            const self = this;
            
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_get_monitor_data',
                    nonce: WEC_DASHBOARD.nonce
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    self.renderMonitorData(data.data);
                    
                    // Se há batches ativos, processar próximo item (fallback para cron)
                    if (data.data.active_batches && data.data.active_batches.length > 0) {
                        self.processNextItem();
                    }
                }
            })
            .catch(err => {
                console.error('[WEC Monitor] Erro:', err);
            });
        },

        // Processa próximo item da fila (fallback para WP Cron)
        processNextItem: function() {
            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_process_next_item',
                    nonce: WEC_DASHBOARD.nonce
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data.has_pending) {
                    console.log('[WEC] Processado:', data.data.message);
                }
            })
            .catch(err => {
                console.error('[WEC] Erro ao processar item:', err);
            });
        },

        // Renderizar dados do monitor
        renderMonitorData: function(data) {
            // Atualizar contadores
            document.getElementById('monitorSentToday').textContent = data.sent_today || 0;
            document.getElementById('monitorProcessing').textContent = data.processing || 0;
            document.getElementById('monitorFailed').textContent = data.failed_today || 0;
            document.getElementById('monitorPending').textContent = data.pending || 0;

            // Badge de disparo ativo
            const badge = document.getElementById('activeBatchesBadge');
            if (data.active_batches && data.active_batches.length > 0) {
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }

            // Renderizar disparos ativos
            const container = document.getElementById('activeDispatches');
            if (data.active_batches && data.active_batches.length > 0) {
                let html = '';
                data.active_batches.forEach(batch => {
                    const progress = batch.total > 0 ? Math.round(((batch.sent + batch.failed) / batch.total) * 100) : 0;
                    html += `
                        <div class="dispatch-card" data-batch-id="${batch.id}">
                            <div class="dispatch-header">
                                <h4><i class="fab fa-whatsapp"></i> ${batch.post_title}</h4>
                                <span class="dispatch-status status-${batch.status}">${this.getStatusLabel(batch.status)}</span>
                            </div>
                            <div class="dispatch-progress">
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: ${progress}%"></div>
                                </div>
                                <span class="progress-text">${progress}%</span>
                            </div>
                            <div class="dispatch-stats">
                                <span class="stat"><i class="fas fa-users"></i> ${batch.total} total</span>
                                <span class="stat success"><i class="fas fa-check"></i> ${batch.sent} enviados</span>
                                <span class="stat error"><i class="fas fa-times"></i> ${batch.failed} falhas</span>
                                <span class="stat pending"><i class="fas fa-clock"></i> ${batch.pending} pendentes</span>
                            </div>
                            <div class="dispatch-actions">
                                <button class="btn-delete-batch" onclick="Dashboard.deleteBatch(${batch.id})" title="Excluir disparo">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="no-active-dispatch">
                        <i class="fas fa-inbox"></i>
                        <p>Nenhum disparo em andamento</p>
                        <a href="#" class="btn-new-dispatch" onclick="Dashboard.navigateTo('posts'); return false;">
                            Iniciar Novo Disparo
                        </a>
                    </div>
                `;
            }

            // Adicionar logs recentes
            if (data.recent_logs && data.recent_logs.length > 0) {
                const logContainer = document.getElementById('realtimeLog');
                data.recent_logs.forEach(log => {
                    if (!this.loggedIds) this.loggedIds = new Set();
                    if (!this.loggedIds.has(log.id)) {
                        this.loggedIds.add(log.id);
                        const type = log.status === 'sent' ? 'success' : 'error';
                        const icon = log.status === 'sent' ? '✓' : '✗';
                        const entry = document.createElement('div');
                        entry.className = `log-entry ${type}`;
                        entry.innerHTML = `
                            <span class="log-time">${log.time}</span>
                            <span class="log-message">${icon} ${log.lead_name} - ${log.post_title}</span>
                        `;
                        logContainer.insertBefore(entry, logContainer.firstChild);
                        
                        // Limitar a 100 entradas
                        while (logContainer.children.length > 100) {
                            logContainer.removeChild(logContainer.lastChild);
                        }
                    }
                });
            }
        },

        // Label de status
        getStatusLabel: function(status) {
            const labels = {
                'pending': 'Pendente',
                'processing': 'Processando',
                'paused': 'Pausado',
                'completed': 'Concluído',
                'cancelled': 'Cancelado'
            };
            return labels[status] || status;
        },

        // Deletar batch (disparo)
        deleteBatch: function(batchId) {
            if (!confirm('Tem certeza que deseja excluir este disparo?\n\nIsso removerá todos os itens da fila associados.')) {
                return;
            }

            fetch(WEC_DASHBOARD.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wec_delete_batch',
                    nonce: WEC_DASHBOARD.nonce,
                    batch_id: batchId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Atualizar monitor
                    this.updateMonitor();
                    alert('Disparo excluído com sucesso!');
                } else {
                    alert(data.data?.message || 'Erro ao excluir disparo');
                }
            })
            .catch(err => {
                console.error('[WEC] Erro ao deletar batch:', err);
                alert('Erro de conexão');
            });
        }
    };

    // Expor globalmente para onclick
    window.Dashboard = Dashboard;

    // Init on DOM ready
    document.addEventListener('DOMContentLoaded', () => Dashboard.init());
})();
