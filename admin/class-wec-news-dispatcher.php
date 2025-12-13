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
        ?>
        <div id="wec-news-dispatch-modal" class="wec-modal">
            <div class="wec-modal-overlay"></div>
            <div class="wec-modal-container wec-news-modal">
                <div class="wec-modal-header">
                    <h2>
                        <span class="dashicons dashicons-share" style="color:#25D366;"></span>
                        <?php _e('Disparar Notícia via WhatsApp', 'whatsapp-evolution-clients'); ?>
                    </h2>
                    <button type="button" class="wec-modal-close">&times;</button>
                </div>

                <div class="wec-modal-body">
                    <!-- Preview da notícia -->
                    <div class="wec-news-preview">
                        <div class="wec-news-preview-image" id="wec-news-image">
                            <span class="dashicons dashicons-format-image"></span>
                        </div>
                        <div class="wec-news-preview-content">
                            <h3 id="wec-news-title">Título da Notícia</h3>
                            <p id="wec-news-excerpt">Resumo da notícia...</p>
                            <a id="wec-news-url" href="#" target="_blank" class="wec-news-link">
                                <span class="dashicons dashicons-external"></span> Ver notícia
                            </a>
                        </div>
                    </div>

                    <!-- Seleção de interesses -->
                    <div class="wec-interests-section">
                        <h4><?php _e('Filtrar por Interesses', 'whatsapp-evolution-clients'); ?></h4>
                        
                        <div class="wec-interests-list">
                            <?php if (!empty($interests)): ?>
                                <?php foreach ($interests as $interest): ?>
                                <label class="wec-interest-item">
                                    <input type="checkbox" name="wec_interests[]" value="<?php echo esc_attr($interest['slug']); ?>">
                                    <span class="wec-interest-name"><?php echo esc_html($interest['name']); ?></span>
                                    <span class="wec-interest-count">(<?php echo $interest['leads_count']; ?>)</span>
                                </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="wec-no-interests"><?php _e('Nenhum interesse cadastrado.', 'whatsapp-evolution-clients'); ?></p>
                            <?php endif; ?>
                        </div>

                        <label class="wec-send-all-option">
                            <input type="checkbox" id="wec-send-all" name="wec_send_all">
                            <span><?php _e('Enviar para TODOS os contatos', 'whatsapp-evolution-clients'); ?></span>
                            <span class="wec-total-count">(<?php echo $total_leads; ?> contatos)</span>
                        </label>
                    </div>

                    <!-- Configuração de delay -->
                    <div class="wec-delay-section">
                        <h4><?php _e('Intervalo entre Disparos', 'whatsapp-evolution-clients'); ?></h4>
                        <div class="wec-delay-inputs">
                            <label>
                                <span><?php _e('Mínimo:', 'whatsapp-evolution-clients'); ?></span>
                                <input type="number" id="wec-delay-min" value="4" min="2" max="60"> seg
                            </label>
                            <label>
                                <span><?php _e('Máximo:', 'whatsapp-evolution-clients'); ?></span>
                                <input type="number" id="wec-delay-max" value="20" min="5" max="120"> seg
                            </label>
                        </div>
                        <p class="wec-delay-tip">
                            <?php _e('⏱️ O sistema escolhe um valor aleatório entre o mínimo e máximo para cada mensagem, simulando envio humanizado.', 'whatsapp-evolution-clients'); ?>
                        </p>
                    </div>

                    <!-- Resumo de destinatários -->
                    <div class="wec-recipients-summary">
                        <div class="wec-recipients-count">
                            <span class="wec-count-number" id="wec-selected-count">0</span>
                            <span class="wec-count-label"><?php _e('contatos selecionados', 'whatsapp-evolution-clients'); ?></span>
                        </div>
                        <div class="wec-recipients-list" id="wec-recipients-list">
                            <p><?php _e('Selecione interesses para ver os contatos.', 'whatsapp-evolution-clients'); ?></p>
                        </div>
                    </div>

                    <!-- Progresso do disparo -->
                    <div class="wec-dispatch-progress" id="wec-dispatch-progress" style="display:none;">
                        <h4><?php _e('Progresso do Disparo', 'whatsapp-evolution-clients'); ?></h4>
                        <div class="wec-progress-bar">
                            <div class="wec-progress-fill" id="wec-progress-fill"></div>
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
                    <button type="button" class="button wec-modal-cancel"><?php _e('Cancelar', 'whatsapp-evolution-clients'); ?></button>
                    <button type="button" class="button button-primary" id="wec-start-dispatch">
                        <span class="dashicons dashicons-share" style="vertical-align:middle;margin-right:5px;"></span>
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
