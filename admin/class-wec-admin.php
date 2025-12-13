<?php
/**
 * Classe Admin
 * 
 * Gerencia os scripts, estilos e configurações do admin
 *
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11 09:49:22
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WEC_Admin
 */
class WEC_Admin
{

    /**
     * Instância única
     * 
     * @var WEC_Admin
     */
    private static $instance = null;

    /**
     * Retorna a instância única
     * 
     * @return WEC_Admin
     */
    public static function instance(): WEC_Admin
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_footer', [$this, 'render_modals']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_link'], 100);
    }

    /**
     * Adiciona link do Dashboard na admin bar
     */
    public function add_admin_bar_link($wp_admin_bar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node([
            'id' => 'wec-dashboard',
            'title' => '<span class="ab-icon dashicons dashicons-whatsapp" style="margin-right:5px;"></span> WhatsApp Dispatcher',
            'href' => WEC_PLUGIN_URL . 'dashboard/',
            'meta' => [
                'target' => '_blank',
                'title' => 'Abrir Dashboard de Disparos',
            ],
        ]);
    }

    /**
     * Enfileira scripts e estilos do admin
     * 
     * @param string $hook Hook atual
     */
    public function enqueue_scripts(string $hook): void
    {
        $screen = get_current_screen();

        // Verificar se estamos nas páginas do plugin
        $is_plugin_page = (
            ($screen && $screen->post_type === WEC_CPT::POST_TYPE) ||
            strpos($hook, WEC_Settings::PAGE_SLUG) !== false
        );

        if (!$is_plugin_page) {
            return;
        }

        // intl-tel-input CSS (CDN para garantir compatibilidade das bandeiras)
        wp_enqueue_style(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
            [],
            '18.2.1'
        );

        // Plugin CSS
        wp_enqueue_style(
            'wec-admin',
            WEC_PLUGIN_URL . 'assets/css/wec-admin.css',
            ['intl-tel-input'],
            WEC_VERSION
        );

        // intl-tel-input JS (CDN)
        wp_enqueue_script(
            'intl-tel-input',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
            [],
            '18.2.1',
            true
        );

        // intl-tel-input utils (CDN)
        wp_enqueue_script(
            'intl-tel-input-utils',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
            ['intl-tel-input'],
            '18.2.1',
            true
        );

        // Plugin JS principal
        wp_enqueue_script(
            'wec-admin',
            WEC_PLUGIN_URL . 'assets/js/wec-admin.js',
            ['jquery', 'intl-tel-input'],
            WEC_VERSION,
            true
        );

        // Script para campo de telefone
        if ($screen && $screen->post_type === WEC_CPT::POST_TYPE && $screen->base === 'post') {
            wp_enqueue_script(
                'wec-intl-phone',
                WEC_PLUGIN_URL . 'assets/js/wec-intl-phone.js',
                ['jquery', 'intl-tel-input', 'intl-tel-input-utils'],
                WEC_VERSION,
                true
            );

            wp_localize_script('wec-intl-phone', 'wecPhoneConfig', [
                'utilsPath' => WEC_PLUGIN_URL . 'assets/vendor/intl-tel-input/utils.js',
            ]);
        }

        // Script para listagem (bulk sender)
        if ($screen && $screen->post_type === WEC_CPT::POST_TYPE && $screen->base === 'edit') {
            wp_enqueue_script(
                'wec-bulk-sender',
                WEC_PLUGIN_URL . 'assets/js/wec-bulk-sender.js',
                ['jquery', 'wec-admin'],
                WEC_VERSION,
                true
            );
        }

        // Localização para todos os scripts
        wp_localize_script('wec-admin', 'wecAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => WEC_Security::create_nonce(),
            'i18n' => [
                'testingConnection' => __('Testando conexão...', 'whatsapp-evolution-clients'),
                'connectionSuccess' => __('Conexão com Evolution API OK.', 'whatsapp-evolution-clients'),
                'connectionError' => __('Erro na conexão:', 'whatsapp-evolution-clients'),
                'sending' => __('Enviando...', 'whatsapp-evolution-clients'),
                'sent' => __('Enviado!', 'whatsapp-evolution-clients'),
                'failed' => __('Falhou', 'whatsapp-evolution-clients'),
                'pending' => __('Pendente', 'whatsapp-evolution-clients'),
                'noClientsSelected' => __('Nenhum cliente selecionado.', 'whatsapp-evolution-clients'),
                'selectClients' => __('Selecione pelo menos um cliente.', 'whatsapp-evolution-clients'),
                'confirmBulkSend' => __('Deseja enviar a mensagem para os clientes selecionados?', 'whatsapp-evolution-clients'),
                'messageRequired' => __('Por favor, digite uma mensagem.', 'whatsapp-evolution-clients'),
                'sendComplete' => __('Envio concluído!', 'whatsapp-evolution-clients'),
                'successCount' => __('Sucesso: %d', 'whatsapp-evolution-clients'),
                'failedCount' => __('Falhas: %d', 'whatsapp-evolution-clients'),
                'waitingDelay' => __('Aguardando %d segundos...', 'whatsapp-evolution-clients'),
                'cancel' => __('Cancelar', 'whatsapp-evolution-clients'),
                'close' => __('Fechar', 'whatsapp-evolution-clients'),
                'send' => __('Enviar', 'whatsapp-evolution-clients'),
                'phoneInvalid' => __('Número de telefone inválido.', 'whatsapp-evolution-clients'),
            ],
        ]);
    }

    /**
     * Renderiza os modais no footer
     */
    public function render_modals(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== WEC_CPT::POST_TYPE) {
            return;
        }

        // Modal de envio individual (mantido para disparo único)
        $this->render_single_send_modal();

        // Modal de disparo em massa removido - agora é feito pelo painel WP PostZap
    }

    /**
     * Renderiza o modal de envio individual
     */
    private function render_single_send_modal(): void
    {
        ?>
        <div id="wec-single-send-modal" class="wec-modal">
            <div class="wec-modal-overlay"></div>
            <div class="wec-modal-container">
                <div class="wec-modal-header">
                    <h2><?php esc_html_e('Enviar WhatsApp', 'whatsapp-evolution-clients'); ?></h2>
                    <button type="button" class="wec-modal-close"
                        aria-label="<?php esc_attr_e('Fechar', 'whatsapp-evolution-clients'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wec-modal-body">
                    <div class="wec-client-info">
                        <div class="wec-client-avatar" id="wec-single-client-avatar">JD</div>
                        <div class="wec-client-details">
                            <p><strong id="wec-single-client-name">Cliente</strong></p>
                            <p><span class="wec-phone" id="wec-single-client-phone">+55 11 99999-9999</span></p>
                        </div>
                    </div>

                    <!-- Campo de Mensagem -->
                    <div class="wec-form-field">
                        <label for="wec-single-message">
                            <?php esc_html_e('Mensagem:', 'whatsapp-evolution-clients'); ?>
                        </label>
                        <textarea id="wec-single-message" rows="4"
                            placeholder="<?php esc_attr_e('Digite sua mensagem aqui...', 'whatsapp-evolution-clients'); ?>"></textarea>
                        <div class="char-count"><span id="wec-single-char-count">0</span>/4096</div>
                    </div>

                    <!-- Campo de Imagem -->
                    <div class="wec-form-field">
                        <label><?php esc_html_e('Anexar Imagem (opcional):', 'whatsapp-evolution-clients'); ?></label>
                        <div class="wec-image-upload" id="wec-single-image-upload">
                            <input type="file" id="wec-single-image" accept="image/jpeg,image/png,image/gif"
                                style="display: none;">
                            <div class="wec-image-dropzone" id="wec-single-dropzone">
                                <div class="wec-dropzone-content">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <p><?php esc_html_e('Arraste uma imagem ou', 'whatsapp-evolution-clients'); ?></p>
                                    <button type="button" class="wec-btn wec-btn-sm wec-btn-secondary wec-select-image">
                                        <?php esc_html_e('Selecionar arquivo', 'whatsapp-evolution-clients'); ?>
                                    </button>
                                    <span
                                        class="wec-file-info"><?php esc_html_e('JPG, PNG ou GIF (máx. 5MB)', 'whatsapp-evolution-clients'); ?></span>
                                </div>
                                <div class="wec-image-preview" id="wec-single-preview" style="display: none;">
                                    <img src="" alt="Preview" id="wec-single-preview-img">
                                    <button type="button" class="wec-remove-image"
                                        title="<?php esc_attr_e('Remover imagem', 'whatsapp-evolution-clients'); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="wec-single-client-id" value="" />
                </div>
                <div class="wec-modal-footer">
                    <button type="button" class="wec-btn wec-btn-outline wec-modal-cancel">
                        <?php esc_html_e('Cancelar', 'whatsapp-evolution-clients'); ?>
                    </button>
                    <button type="button" class="wec-btn wec-btn-primary wec-single-send-btn">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.372-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                        </svg>
                        <?php esc_html_e('Enviar Mensagem', 'whatsapp-evolution-clients'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Renderiza o modal de envio em massa
     */
    private function render_bulk_send_modal(): void
    {
        ?>
        <div id="wec-bulk-send-modal" class="wec-modal">
            <div class="wec-modal-overlay"></div>
            <div class="wec-modal-container wec-modal-large">
                <div class="wec-modal-header">
                    <h2><?php esc_html_e('Disparo em massa via WhatsApp', 'whatsapp-evolution-clients'); ?></h2>
                    <button type="button" class="wec-modal-close"
                        aria-label="<?php esc_attr_e('Fechar', 'whatsapp-evolution-clients'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wec-modal-body">
                    <!-- Área de composição (Grid Layout) -->
                    <div id="wec-bulk-compose" class="wec-bulk-grid">

                        <!-- Coluna Esquerda: Formulário -->
                        <div class="wec-bulk-column-left">
                            <div class="wec-form-field">
                                <label
                                    for="wec-bulk-message"><?php esc_html_e('Mensagem a enviar:', 'whatsapp-evolution-clients'); ?></label>
                                <textarea id="wec-bulk-message" rows="12"
                                    placeholder="<?php esc_attr_e('Digite a mensagem que será enviada para todos os clientes selecionados...', 'whatsapp-evolution-clients'); ?>"></textarea>
                                <div class="char-count"><span id="wec-bulk-char-count">0</span>/4096</div>
                            </div>

                            <!-- Campo de Imagem para Bulk -->
                            <div class="wec-form-field">
                                <label><?php esc_html_e('Anexar Imagem (opcional):', 'whatsapp-evolution-clients'); ?></label>
                                <div class="wec-image-upload" id="wec-bulk-image-upload">
                                    <input type="file" id="wec-bulk-image" accept="image/jpeg,image/png,image/gif"
                                        style="display: none;">
                                    <div class="wec-image-dropzone" id="wec-bulk-dropzone">
                                        <div class="wec-dropzone-content">
                                            <span class="dashicons dashicons-format-image"></span>
                                            <p><?php esc_html_e('Arraste uma imagem ou', 'whatsapp-evolution-clients'); ?></p>
                                            <button type="button" class="wec-btn wec-btn-sm wec-btn-secondary wec-select-image">
                                                <?php esc_html_e('Selecionar arquivo', 'whatsapp-evolution-clients'); ?>
                                            </button>
                                            <span
                                                class="wec-file-info"><?php esc_html_e('JPG, PNG ou GIF (máx. 5MB)', 'whatsapp-evolution-clients'); ?></span>
                                        </div>
                                        <div class="wec-image-preview" id="wec-bulk-preview" style="display: none;">
                                            <img src="" alt="Preview" id="wec-bulk-preview-img">
                                            <button type="button" class="wec-remove-image wec-bulk-remove-image"
                                                title="<?php esc_attr_e('Remover imagem', 'whatsapp-evolution-clients'); ?>">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Coluna Direita: Lista de Contatos -->
                        <div class="wec-bulk-column-right">
                            <div class="wec-selected-clients-panel">
                                <h3>
                                    <?php esc_html_e('Destinatários', 'whatsapp-evolution-clients'); ?>
                                    <span class="wec-badge-count" id="wec-bulk-count">0</span>
                                </h3>
                                <div class="wec-clients-list-wrapper">
                                    <ul id="wec-bulk-clients-preview"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Área de progresso (Mantida Full Width) -->
                    <div id="wec-bulk-progress" style="display: none;">
                        <div class="wec-progress-bar-container">
                            <div class="wec-progress-bar">
                                <div class="wec-progress-fill" style="width: 0%;"></div>
                            </div>
                            <span class="wec-progress-text">0%</span>
                        </div>
                        <div class="wec-progress-status">
                            <p id="wec-current-status"><?php esc_html_e('Preparando...', 'whatsapp-evolution-clients'); ?></p>
                            <p id="wec-delay-status" style="display: none;"></p>
                        </div>
                        <div class="wec-progress-list">
                            <table class="wec-send-list">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Cliente', 'whatsapp-evolution-clients'); ?></th>
                                        <th><?php esc_html_e('WhatsApp', 'whatsapp-evolution-clients'); ?></th>
                                        <th><?php esc_html_e('Status', 'whatsapp-evolution-clients'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="wec-send-list-body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Resumo final -->
                    <div id="wec-bulk-summary" style="display: none;">
                        <div class="wec-summary-box">
                            <h3><?php esc_html_e('Resumo do Envio', 'whatsapp-evolution-clients'); ?></h3>
                            <div class="wec-summary-stats">
                                <div class="wec-stat wec-stat-success">
                                    <span class="wec-stat-number" id="wec-stat-success">0</span>
                                    <span
                                        class="wec-stat-label"><?php esc_html_e('Enviados', 'whatsapp-evolution-clients'); ?></span>
                                </div>
                                <div class="wec-stat wec-stat-failed">
                                    <span class="wec-stat-number" id="wec-stat-failed">0</span>
                                    <span
                                        class="wec-stat-label"><?php esc_html_e('Falhas', 'whatsapp-evolution-clients'); ?></span>
                                </div>
                                <div class="wec-stat wec-stat-total">
                                    <span class="wec-stat-number" id="wec-stat-total">0</span>
                                    <span
                                        class="wec-stat-label"><?php esc_html_e('Total', 'whatsapp-evolution-clients'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wec-modal-footer">
                    <button type="button" class="button wec-modal-cancel wec-bulk-cancel">
                        <?php esc_html_e('Cancelar', 'whatsapp-evolution-clients'); ?>
                    </button>
                    <button type="button" class="button button-primary wec-bulk-send-btn">
                        <span class="dashicons dashicons-email-alt" style="vertical-align: text-bottom;"></span>
                        <?php esc_html_e('Iniciar Envio', 'whatsapp-evolution-clients'); ?>
                    </button>
                    <button type="button" class="button button-secondary wec-bulk-close-btn" style="display: none;">
                        <?php esc_html_e('Fechar', 'whatsapp-evolution-clients'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
