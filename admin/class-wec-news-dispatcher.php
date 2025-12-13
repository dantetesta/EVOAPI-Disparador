<?php
/**
 * Classe para Disparo de Notícias via WhatsApp
 *
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.2.0
 * @created 2025-12-12 21:30:00
 */

if (!defined('ABSPATH')) {
    exit;
}

class WEC_News_Dispatcher
{
    private static $instance = null;

    public static function instance(): WEC_News_Dispatcher
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Adicionar coluna de ação na listagem de posts
        add_filter('post_row_actions', [$this, 'add_whatsapp_action'], 10, 2);
        
        // Adicionar modal no footer do admin
        add_action('admin_footer', [$this, 'render_news_dispatch_modal']);
        
        // Enfileirar scripts na página de posts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Adiciona ação de WhatsApp na linha do post
     */
    public function add_whatsapp_action(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== 'post' || $post->post_status !== 'publish') {
            return $actions;
        }

        if (!current_user_can('manage_options')) {
            return $actions;
        }

        $post_data = [
            'id' => $post->ID,
            'title' => esc_attr($post->post_title),
            'excerpt' => esc_attr(wp_trim_words(get_the_excerpt($post), 20, '...')),
            'url' => get_permalink($post->ID),
            'image' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
        ];

        $actions['wec_dispatch'] = sprintf(
            '<a href="#" class="wec-news-dispatch-btn" data-post=\'%s\' title="%s">
                <span class="dashicons dashicons-whatsapp" style="font-size:14px;vertical-align:middle;color:#25D366;"></span> %s
            </a>',
            esc_attr(wp_json_encode($post_data)),
            __('Disparar via WhatsApp', 'whatsapp-evolution-clients'),
            __('WhatsApp', 'whatsapp-evolution-clients')
        );

        return $actions;
    }

    /**
     * Enfileira scripts na página de posts
     */
    public function enqueue_scripts(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'post') {
            return;
        }

        // CSS (depende do wec-admin para estilos do modal)
        wp_enqueue_style(
            'wec-admin',
            WEC_PLUGIN_URL . 'assets/css/wec-admin.css',
            [],
            WEC_VERSION
        );
        
        wp_enqueue_style(
            'wec-news-dispatcher',
            WEC_PLUGIN_URL . 'assets/css/wec-news-dispatcher.css',
            ['wec-admin'],
            WEC_VERSION
        );

        // JS
        wp_enqueue_script(
            'wec-news-dispatcher',
            WEC_PLUGIN_URL . 'assets/js/wec-news-dispatcher.js',
            ['jquery'],
            WEC_VERSION,
            true
        );

        // Localizar dados
        wp_localize_script('wec-news-dispatcher', 'wecNewsDispatcher', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => WEC_Security::create_nonce(),
            'interests' => WEC_Queue::get_all_interests(),
            'totalLeads' => WEC_Queue::get_total_leads(),
            'i18n' => [
                'dispatching' => __('Disparando...', 'whatsapp-evolution-clients'),
                'sent' => __('Enviado', 'whatsapp-evolution-clients'),
                'failed' => __('Falhou', 'whatsapp-evolution-clients'),
                'pending' => __('Na fila', 'whatsapp-evolution-clients'),
                'completed' => __('Disparo concluído!', 'whatsapp-evolution-clients'),
                'noLeads' => __('Nenhum lead encontrado com os interesses selecionados.', 'whatsapp-evolution-clients'),
                'selectInterest' => __('Selecione ao menos um interesse ou marque "Enviar para todos".', 'whatsapp-evolution-clients'),
                'confirmDispatch' => __('Deseja disparar esta notícia para %d contatos?', 'whatsapp-evolution-clients'),
                'waitingNext' => __('Próximo disparo em %ds...', 'whatsapp-evolution-clients'),
            ],
        ]);
    }

    /**
     * Renderiza o modal de disparo de notícias
     */
    public function render_news_dispatch_modal(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'post' || $screen->base !== 'edit') {
            return;
        }

        $interests = WEC_Queue::get_all_interests();
        $total_leads = WEC_Queue::get_total_leads();
        
        // Ordenar interesses alfabeticamente
        usort($interests, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        ?>
        <div id="wec-news-dispatch-modal" class="wec-modal">
            <div class="wec-modal-overlay"></div>
            <div class="wec-modal-container wec-news-modal">
                <div class="wec-modal-header">
                    <h2>
                        <span class="dashicons dashicons-whatsapp" style="color:#25D366;"></span>
                        <?php _e('Disparar Notícia via WhatsApp', 'whatsapp-evolution-clients'); ?>
                    </h2>
                    <button type="button" class="wec-modal-close">&times;</button>
                </div>

                <!-- Sistema de Tabs -->
                <div class="wec-tabs">
                    <button type="button" class="wec-tab active" data-tab="preview">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Preview', 'whatsapp-evolution-clients'); ?>
                    </button>
                    <button type="button" class="wec-tab" data-tab="interests">
                        <span class="dashicons dashicons-tag"></span>
                        <?php _e('Destinatários', 'whatsapp-evolution-clients'); ?>
                        <span class="wec-tab-badge" id="wec-tab-badge">0</span>
                    </button>
                    <button type="button" class="wec-tab" data-tab="settings">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Configurações', 'whatsapp-evolution-clients'); ?>
                    </button>
                </div>

                <div class="wec-modal-body">
                    <!-- Tab 1: Preview da Mensagem -->
                    <div class="wec-tab-content active" data-tab="preview">
                        <div class="wec-preview-container">
                            <div class="wec-phone-mockup">
                                <div class="wec-phone-header">
                                    <span class="wec-phone-avatar">📰</span>
                                    <span class="wec-phone-name">Sua Notícia</span>
                                </div>
                                <div class="wec-phone-body">
                                    <div class="wec-whatsapp-message">
                                        <div class="wec-msg-image" id="wec-preview-image">
                                            <span class="dashicons dashicons-format-image"></span>
                                        </div>
                                        <div class="wec-msg-title" id="wec-preview-title">Título da Notícia</div>
                                        <div class="wec-msg-text" id="wec-preview-excerpt">Resumo da notícia aparecerá aqui...</div>
                                        <div class="wec-msg-link">🔗 <span id="wec-preview-url">link-da-noticia.com</span></div>
                                        <div class="wec-msg-time">Agora</div>
                                    </div>
                                </div>
                            </div>
                            <div class="wec-preview-info">
                                <h4><?php _e('A mensagem enviada será:', 'whatsapp-evolution-clients'); ?></h4>
                                <ul>
                                    <li>📷 <?php _e('Imagem de capa do post', 'whatsapp-evolution-clients'); ?></li>
                                    <li>📝 <?php _e('Título em negrito', 'whatsapp-evolution-clients'); ?></li>
                                    <li>📄 <?php _e('Resumo (excerpt)', 'whatsapp-evolution-clients'); ?></li>
                                    <li>🔗 <?php _e('Link da notícia', 'whatsapp-evolution-clients'); ?></li>
                                </ul>
                                <a id="wec-news-url" href="#" target="_blank" class="button button-secondary">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php _e('Ver notícia original', 'whatsapp-evolution-clients'); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Seleção de Destinatários -->
                    <div class="wec-tab-content" data-tab="interests">
                        <!-- Opção enviar para todos -->
                        <label class="wec-send-all-option">
                            <input type="checkbox" id="wec-send-all" name="wec_send_all">
                            <span class="wec-send-all-text">
                                <strong><?php _e('Enviar para TODOS os contatos', 'whatsapp-evolution-clients'); ?></strong>
                                <small><?php echo $total_leads; ?> contatos com WhatsApp válido</small>
                            </span>
                        </label>

                        <div class="wec-interests-wrapper" id="wec-interests-wrapper">
                            <!-- Busca -->
                            <div class="wec-search-box">
                                <span class="dashicons dashicons-search"></span>
                                <input type="text" id="wec-interest-search" placeholder="<?php _e('Buscar interesse...', 'whatsapp-evolution-clients'); ?>">
                                <button type="button" id="wec-clear-search" class="wec-clear-btn" style="display:none;">&times;</button>
                            </div>

                            <!-- Ações rápidas -->
                            <div class="wec-quick-actions">
                                <button type="button" id="wec-select-all" class="button button-small">
                                    <?php _e('Selecionar Todos', 'whatsapp-evolution-clients'); ?>
                                </button>
                                <button type="button" id="wec-deselect-all" class="button button-small">
                                    <?php _e('Limpar Seleção', 'whatsapp-evolution-clients'); ?>
                                </button>
                                <span class="wec-selected-info">
                                    <span id="wec-interests-selected">0</span> / <?php echo count($interests); ?> selecionados
                                </span>
                            </div>

                            <!-- Lista de interesses -->
                            <div class="wec-interests-list" id="wec-interests-list">
                                <?php if (!empty($interests)): ?>
                                    <?php foreach ($interests as $interest): ?>
                                    <label class="wec-interest-item" data-name="<?php echo esc_attr(strtolower($interest['name'])); ?>">
                                        <input type="checkbox" name="wec_interests[]" value="<?php echo esc_attr($interest['slug']); ?>" data-count="<?php echo $interest['leads_count']; ?>">
                                        <span class="wec-interest-check"></span>
                                        <span class="wec-interest-name"><?php echo esc_html($interest['name']); ?></span>
                                        <span class="wec-interest-count"><?php echo $interest['leads_count']; ?> leads</span>
                                    </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="wec-no-interests">
                                        <span class="dashicons dashicons-info"></span>
                                        <p><?php _e('Nenhum interesse cadastrado.', 'whatsapp-evolution-clients'); ?></p>
                                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=wec_interest'); ?>" class="button">
                                            <?php _e('Criar Interesses', 'whatsapp-evolution-clients'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Sem resultados na busca -->
                            <div class="wec-no-results" id="wec-no-results" style="display:none;">
                                <span class="dashicons dashicons-search"></span>
                                <p><?php _e('Nenhum interesse encontrado.', 'whatsapp-evolution-clients'); ?></p>
                            </div>
                        </div>

                        <!-- Resumo de contatos -->
                        <div class="wec-contacts-summary">
                            <div class="wec-summary-number">
                                <span id="wec-selected-count">0</span>
                                <small><?php _e('contatos', 'whatsapp-evolution-clients'); ?></small>
                            </div>
                            <div class="wec-summary-list" id="wec-recipients-list">
                                <p><?php _e('Selecione interesses para ver os contatos.', 'whatsapp-evolution-clients'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Configurações -->
                    <div class="wec-tab-content" data-tab="settings">
                        <div class="wec-settings-section">
                            <h4>
                                <span class="dashicons dashicons-clock"></span>
                                <?php _e('Intervalo entre Disparos', 'whatsapp-evolution-clients'); ?>
                            </h4>
                            <p class="wec-settings-desc">
                                <?php _e('Define o intervalo aleatório entre cada envio para simular comportamento humanizado e evitar bloqueios.', 'whatsapp-evolution-clients'); ?>
                            </p>
                            <div class="wec-delay-inputs">
                                <div class="wec-delay-field">
                                    <label><?php _e('Mínimo', 'whatsapp-evolution-clients'); ?></label>
                                    <div class="wec-input-group">
                                        <input type="number" id="wec-delay-min" value="4" min="2" max="60">
                                        <span>seg</span>
                                    </div>
                                </div>
                                <div class="wec-delay-separator">~</div>
                                <div class="wec-delay-field">
                                    <label><?php _e('Máximo', 'whatsapp-evolution-clients'); ?></label>
                                    <div class="wec-input-group">
                                        <input type="number" id="wec-delay-max" value="20" min="5" max="120">
                                        <span>seg</span>
                                    </div>
                                </div>
                            </div>
                            <div class="wec-delay-presets">
                                <span><?php _e('Presets:', 'whatsapp-evolution-clients'); ?></span>
                                <button type="button" class="wec-preset" data-min="2" data-max="5"><?php _e('Rápido', 'whatsapp-evolution-clients'); ?></button>
                                <button type="button" class="wec-preset" data-min="4" data-max="20"><?php _e('Normal', 'whatsapp-evolution-clients'); ?></button>
                                <button type="button" class="wec-preset" data-min="15" data-max="45"><?php _e('Seguro', 'whatsapp-evolution-clients'); ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Progresso do disparo (aparece sobre as tabs) -->
                    <div class="wec-dispatch-progress" id="wec-dispatch-progress" style="display:none;">
                        <h4>📤 <?php _e('Progresso do Disparo', 'whatsapp-evolution-clients'); ?></h4>
                        
                        <div class="wec-progress-wrapper">
                            <div class="wec-progress-bar">
                                <div class="wec-progress-fill" id="wec-progress-fill"></div>
                                <div class="wec-progress-percent" id="wec-progress-percent">0%</div>
                            </div>
                        </div>
                        
                        <div class="wec-progress-stats">
                            <span class="wec-stat-sent">✅ <span id="wec-stat-sent">0</span> enviados</span>
                            <span class="wec-stat-failed">❌ <span id="wec-stat-failed">0</span> falhas</span>
                            <span class="wec-stat-pending">⏳ <span id="wec-stat-pending">0</span> pendentes</span>
                        </div>
                        
                        <div class="wec-dispatch-log" id="wec-dispatch-log"></div>
                        <div class="wec-next-dispatch" id="wec-next-dispatch"></div>
                    </div>
                </div>

                <div class="wec-modal-footer">
                    <input type="hidden" id="wec-news-post-id" value="">
                    <input type="hidden" id="wec-news-title" value="">
                    <input type="hidden" id="wec-news-excerpt" value="">
                    <button type="button" class="button wec-modal-cancel"><?php _e('Cancelar', 'whatsapp-evolution-clients'); ?></button>
                    <button type="button" class="button button-primary" id="wec-start-dispatch">
                        <span class="dashicons dashicons-share"></span>
                        <?php _e('Iniciar Disparo', 'whatsapp-evolution-clients'); ?>
                    </button>
                    <button type="button" class="button" id="wec-pause-dispatch" style="display:none;">
                        <span class="dashicons dashicons-controls-pause"></span> <?php _e('Pausar', 'whatsapp-evolution-clients'); ?>
                    </button>
                    <button type="button" class="button button-link-delete" id="wec-cancel-dispatch" style="display:none;">
                        <?php _e('Cancelar Disparo', 'whatsapp-evolution-clients'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
