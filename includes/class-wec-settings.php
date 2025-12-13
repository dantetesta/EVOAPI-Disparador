<?php
/**
 * Classe de Configurações
 * 
 * Gerencia a página de configurações do plugin
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
 * Classe WEC_Settings
 */
class WEC_Settings
{

    /**
     * Instância única
     * 
     * @var WEC_Settings
     */
    private static $instance = null;

    /**
     * Slug da página
     */
    const PAGE_SLUG = 'wec-settings';

    /**
     * Grupo de opções
     */
    const OPTION_GROUP = 'wec_settings_group';

    /**
     * Nomes das opções
     */
    const OPT_API_URL = 'wec_api_base_url';
    const OPT_GLOBAL_KEY = 'wec_global_api_key';
    const OPT_INSTANCE = 'wec_instance_name';
    const OPT_TOKEN = 'wec_instance_token';
    const OPT_SENDER = 'wec_sender_number';

    /**
     * Retorna a instância única
     * 
     * @return WEC_Settings
     */
    public static function instance(): WEC_Settings
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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Adiciona a página de configurações como submenu do Zap Leads
     */
    public function add_settings_page(): void
    {
        // Painel de Disparo (link externo)
        add_submenu_page(
            'edit.php?post_type=wec_client',
            __('Painel de Disparo', 'whatsapp-evolution-clients'),
            __('📢 Painel de Disparo', 'whatsapp-evolution-clients'),
            'manage_options',
            'wec-dispatch-panel',
            [$this, 'redirect_to_dashboard']
        );

        // Configurações
        add_submenu_page(
            'edit.php?post_type=wec_client',
            __('Configurações', 'whatsapp-evolution-clients'),
            __('Configurações', 'whatsapp-evolution-clients'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Redireciona para o dashboard de disparo
     */
    public function redirect_to_dashboard(): void
    {
        wp_redirect(WEC_PLUGIN_URL . 'dashboard/');
        exit;
    }

    /**
     * Registra as configurações
     */
    public function register_settings(): void
    {
        // Registrar opções
        register_setting(
            self::OPTION_GROUP,
            self::OPT_API_URL,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_api_url'],
                'default' => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPT_GLOBAL_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_global_key'],
                'default' => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPT_INSTANCE,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPT_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_token'],
                'default' => '',
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPT_SENDER,
            [
                'type' => 'string',
                'sanitize_callback' => [WEC_Security::class, 'sanitize_phone'],
                'default' => '',
            ]
        );

        // Seção principal
        add_settings_section(
            'wec_main_section',
            __('Configurações da Evolution API', 'whatsapp-evolution-clients'),
            [$this, 'render_section_description'],
            self::PAGE_SLUG
        );

        // Campos
        add_settings_field(
            self::OPT_API_URL,
            __('Evolution API Base URL', 'whatsapp-evolution-clients'),
            [$this, 'render_api_url_field'],
            self::PAGE_SLUG,
            'wec_main_section'
        );

        add_settings_field(
            self::OPT_GLOBAL_KEY,
            __('Global API Key', 'whatsapp-evolution-clients'),
            [$this, 'render_global_key_field'],
            self::PAGE_SLUG,
            'wec_main_section'
        );

        add_settings_field(
            self::OPT_INSTANCE,
            __('Instance Name', 'whatsapp-evolution-clients'),
            [$this, 'render_instance_field'],
            self::PAGE_SLUG,
            'wec_main_section'
        );

        add_settings_field(
            self::OPT_TOKEN,
            __('Instance Token', 'whatsapp-evolution-clients'),
            [$this, 'render_token_field'],
            self::PAGE_SLUG,
            'wec_main_section'
        );

        add_settings_field(
            self::OPT_SENDER,
            __('Sender WhatsApp Number', 'whatsapp-evolution-clients'),
            [$this, 'render_sender_field'],
            self::PAGE_SLUG,
            'wec_main_section'
        );
    }

    /**
     * Sanitiza a URL da API
     * 
     * @param string $value Valor a sanitizar
     * @return string
     */
    public function sanitize_api_url(string $value): string
    {
        $value = esc_url_raw(trim($value));
        // Remover barra final
        return rtrim($value, '/');
    }

    /**
     * Sanitiza e criptografa o token
     * 
     * @param string $value Valor a sanitizar
     * @return string
     */
    public function sanitize_token(string $value): string
    {
        $value = sanitize_text_field(trim($value));

        // Se vazio, retornar vazio
        if (empty($value)) {
            return '';
        }

        // Se é o placeholder de asteriscos, manter o token existente
        if ($value === '••••••••••••••••' || preg_match('/^•+$/', $value)) {
            return get_option(self::OPT_TOKEN, '');
        }

        // Criptografar o novo token
        return WEC_Security::encrypt($value);
    }

    /**
     * Renderiza a descrição da seção
     */
    public function render_section_description(): void
    {
        echo '<p>' . esc_html__('Configure as credenciais da sua instância Evolution API para envio de mensagens WhatsApp.', 'whatsapp-evolution-clients') . '</p>';
    }

    /**
     * Renderiza o campo API URL
     */
    public function render_api_url_field(): void
    {
        $value = get_option(self::OPT_API_URL, '');
        ?>
        <input type="url" id="<?php echo esc_attr(self::OPT_API_URL); ?>" name="<?php echo esc_attr(self::OPT_API_URL); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="https://sua-api.evolution.com" />
        <p class="description">
            <?php esc_html_e('URL base da sua instância Evolution API (sem barra no final).', 'whatsapp-evolution-clients'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza o campo Instance Name
     */
    public function render_instance_field(): void
    {
        $value = get_option(self::OPT_INSTANCE, '');
        ?>
        <input type="text" id="<?php echo esc_attr(self::OPT_INSTANCE); ?>" name="<?php echo esc_attr(self::OPT_INSTANCE); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="minha_instancia" />
        <p class="description">
            <?php esc_html_e('Nome ou ID da sua instância Evolution API.', 'whatsapp-evolution-clients'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza o campo Global API Key
     */
    public function render_global_key_field(): void
    {
        $encrypted = get_option(self::OPT_GLOBAL_KEY, '');
        $has_key = !empty($encrypted);
        ?>
        <input type="password" id="<?php echo esc_attr(self::OPT_GLOBAL_KEY); ?>"
            name="<?php echo esc_attr(self::OPT_GLOBAL_KEY); ?>" value="<?php echo $has_key ? '••••••••••••••••' : ''; ?>"
            class="regular-text"
            placeholder="<?php echo $has_key ? esc_attr__('API Key configurada', 'whatsapp-evolution-clients') : ''; ?>" />
        <p class="description">
            <?php esc_html_e('Token Global da Evolution API (usado para gerenciar instâncias e testar conexão).', 'whatsapp-evolution-clients'); ?>
            <?php if ($has_key): ?>
                <br><em><?php esc_html_e('Deixe em branco para manter a chave atual.', 'whatsapp-evolution-clients'); ?></em>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Sanitiza e criptografa o Global API Key
     * 
     * @param string $value Valor a sanitizar
     * @return string
     */
    public function sanitize_global_key(string $value): string
    {
        $value = sanitize_text_field(trim($value));

        // Se vazio, retornar vazio
        if (empty($value)) {
            return '';
        }

        // Se é o placeholder de asteriscos, manter a chave existente
        if ($value === '••••••••••••••••' || preg_match('/^•+$/', $value)) {
            return get_option(self::OPT_GLOBAL_KEY, '');
        }

        // Criptografar a nova chave
        return WEC_Security::encrypt($value);
    }

    /**
     * Renderiza o campo Token
     */
    public function render_token_field(): void
    {
        $encrypted = get_option(self::OPT_TOKEN, '');
        // Para exibição, mostramos asteriscos se há token salvo
        $has_token = !empty($encrypted);
        ?>
        <input type="password" id="<?php echo esc_attr(self::OPT_TOKEN); ?>" name="<?php echo esc_attr(self::OPT_TOKEN); ?>"
            value="<?php echo $has_token ? '••••••••••••••••' : ''; ?>" class="regular-text"
            placeholder="<?php echo $has_token ? esc_attr__('Token já configurado', 'whatsapp-evolution-clients') : ''; ?>" />
        <p class="description">
            <?php esc_html_e('Token de autenticação da instância (será armazenado de forma criptografada).', 'whatsapp-evolution-clients'); ?>
            <?php if ($has_token): ?>
                <br><em><?php esc_html_e('Deixe em branco para manter o token atual.', 'whatsapp-evolution-clients'); ?></em>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Renderiza o campo Sender Number
     */
    public function render_sender_field(): void
    {
        $value = get_option(self::OPT_SENDER, '');
        ?>
        <input type="text" id="<?php echo esc_attr(self::OPT_SENDER); ?>" name="<?php echo esc_attr(self::OPT_SENDER); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="+5511999999999" />
        <p class="description">
            <?php esc_html_e('Número WhatsApp que enviará as mensagens, no formato E.164 (ex: +5511999999999).', 'whatsapp-evolution-clients'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza a página de configurações
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $api_url = get_option(self::OPT_API_URL, '');
        $instance = get_option(self::OPT_INSTANCE, '');
        $sender = get_option(self::OPT_SENDER, '');
        $has_global_key = !empty(get_option(self::OPT_GLOBAL_KEY, ''));
        $has_token = !empty(get_option(self::OPT_TOKEN, ''));
        ?>
        <div class="wec-settings-wrapper">
            <!-- Header -->
            <div class="wec-settings-header">
                <div class="wec-header-content">
                    <div class="wec-header-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <div class="wec-header-text">
                        <h1><?php esc_html_e('WP PostZap', 'whatsapp-evolution-clients'); ?></h1>
                        <p><?php esc_html_e('Setup da Evolution API', 'whatsapp-evolution-clients'); ?></p>
                    </div>
                </div>
                <div class="wec-header-badge">
                    <span class="wec-version-badge">v<?php echo esc_html(WEC_VERSION); ?></span>
                </div>
            </div>

            <div class="wec-settings-grid">
                <!-- Card Principal de Configurações -->
                <div class="wec-card wec-card-main">
                    <div class="wec-card-header">
                        <div class="wec-card-icon wec-icon-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </div>
                        <div class="wec-card-title">
                            <h2><?php esc_html_e('Configurações da API', 'whatsapp-evolution-clients'); ?></h2>
                            <p><?php esc_html_e('Credenciais da Evolution API', 'whatsapp-evolution-clients'); ?></p>
                        </div>
                    </div>

                    <form action="options.php" method="post" class="wec-settings-form">
                        <?php settings_fields(self::OPTION_GROUP); ?>
                        
                        <!-- API URL -->
                        <div class="wec-form-group">
                            <label for="<?php echo esc_attr(self::OPT_API_URL); ?>">
                                <span class="wec-label-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                    </svg>
                                </span>
                                <?php esc_html_e('URL Base da API', 'whatsapp-evolution-clients'); ?>
                            </label>
                            <input type="url" 
                                id="<?php echo esc_attr(self::OPT_API_URL); ?>" 
                                name="<?php echo esc_attr(self::OPT_API_URL); ?>"
                                value="<?php echo esc_attr($api_url); ?>" 
                                class="wec-input" 
                                placeholder="https://sua-api.evolution.com" />
                            <span class="wec-input-hint"><?php esc_html_e('URL da sua instância Evolution API (sem barra no final)', 'whatsapp-evolution-clients'); ?></span>
                        </div>

                        <!-- Instance Name -->
                        <div class="wec-form-group">
                            <label for="<?php echo esc_attr(self::OPT_INSTANCE); ?>">
                                <span class="wec-label-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                </span>
                                <?php esc_html_e('Nome da Instância', 'whatsapp-evolution-clients'); ?>
                            </label>
                            <input type="text" 
                                id="<?php echo esc_attr(self::OPT_INSTANCE); ?>" 
                                name="<?php echo esc_attr(self::OPT_INSTANCE); ?>"
                                value="<?php echo esc_attr($instance); ?>" 
                                class="wec-input" 
                                placeholder="minha_instancia" />
                            <span class="wec-input-hint"><?php esc_html_e('Nome ou ID da sua instância na Evolution API', 'whatsapp-evolution-clients'); ?></span>
                        </div>

                        <!-- Instance Token -->
                        <div class="wec-form-group">
                            <label for="<?php echo esc_attr(self::OPT_TOKEN); ?>">
                                <span class="wec-label-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </span>
                                <?php esc_html_e('Token da Instância', 'whatsapp-evolution-clients'); ?>
                                <?php if ($has_token): ?>
                                    <span class="wec-status-dot wec-status-success" title="<?php esc_attr_e('Configurado', 'whatsapp-evolution-clients'); ?>"></span>
                                <?php endif; ?>
                            </label>
                            <div class="wec-input-wrapper">
                                <input type="password" 
                                    id="<?php echo esc_attr(self::OPT_TOKEN); ?>" 
                                    name="<?php echo esc_attr(self::OPT_TOKEN); ?>"
                                    value="<?php echo $has_token ? '••••••••••••••••' : ''; ?>" 
                                    class="wec-input wec-input-password"
                                    placeholder="<?php echo $has_token ? '' : esc_attr__('Token de autenticação', 'whatsapp-evolution-clients'); ?>" />
                                <button type="button" class="wec-toggle-password" aria-label="<?php esc_attr_e('Mostrar/ocultar senha', 'whatsapp-evolution-clients'); ?>">
                                    <svg class="wec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="wec-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                            <span class="wec-input-hint">
                                <?php esc_html_e('Token criptografado para autenticação', 'whatsapp-evolution-clients'); ?>
                                <?php if ($has_token): ?>
                                    · <em><?php esc_html_e('Deixe em branco para manter', 'whatsapp-evolution-clients'); ?></em>
                                <?php endif; ?>
                            </span>
                        </div>

                        <!-- Sender Number -->
                        <div class="wec-form-group">
                            <label for="<?php echo esc_attr(self::OPT_SENDER); ?>">
                                <span class="wec-label-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                </span>
                                <?php esc_html_e('Número do Remetente', 'whatsapp-evolution-clients'); ?>
                            </label>
                            <input type="text" 
                                id="<?php echo esc_attr(self::OPT_SENDER); ?>" 
                                name="<?php echo esc_attr(self::OPT_SENDER); ?>"
                                value="<?php echo esc_attr($sender); ?>" 
                                class="wec-input" 
                                placeholder="+5511999999999" />
                            <span class="wec-input-hint"><?php esc_html_e('Número WhatsApp no formato E.164 (ex: +5511999999999)', 'whatsapp-evolution-clients'); ?></span>
                        </div>

                        <div class="wec-form-actions">
                            <button type="submit" class="wec-btn wec-btn-primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                <?php esc_html_e('Salvar Configurações', 'whatsapp-evolution-clients'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card de Teste de Conexão -->
                <div class="wec-card wec-card-test">
                    <div class="wec-card-header">
                        <div class="wec-card-icon wec-icon-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <div class="wec-card-title">
                            <h2><?php esc_html_e('Testar Conexão', 'whatsapp-evolution-clients'); ?></h2>
                            <p><?php esc_html_e('Verificar status da API', 'whatsapp-evolution-clients'); ?></p>
                        </div>
                    </div>

                    <div class="wec-test-content">
                        <p class="wec-test-description">
                            <?php esc_html_e('Clique no botão abaixo para verificar se a conexão com a Evolution API está funcionando corretamente.', 'whatsapp-evolution-clients'); ?>
                        </p>

                        <button type="button" id="wec-test-connection" class="wec-btn wec-btn-outline">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            <?php esc_html_e('Testar Conexão', 'whatsapp-evolution-clients'); ?>
                        </button>

                        <div id="wec-test-result" class="wec-test-result">
                            <div class="wec-result-content">
                                <span class="wec-result-icon"></span>
                                <p id="wec-test-message"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card de Informações -->
                <div class="wec-card wec-card-info">
                    <div class="wec-card-header">
                        <div class="wec-card-icon wec-icon-info">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </div>
                        <div class="wec-card-title">
                            <h2><?php esc_html_e('Informações', 'whatsapp-evolution-clients'); ?></h2>
                            <p><?php esc_html_e('Links úteis e documentação', 'whatsapp-evolution-clients'); ?></p>
                        </div>
                    </div>

                    <div class="wec-info-links">
                        <a href="https://doc.evolution-api.com/" target="_blank" rel="noopener" class="wec-info-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            <span><?php esc_html_e('Documentação Evolution API', 'whatsapp-evolution-clients'); ?></span>
                            <svg class="wec-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                        <a href="https://dantetesta.com.br" target="_blank" rel="noopener" class="wec-info-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span><?php esc_html_e('Suporte do Desenvolvedor', 'whatsapp-evolution-clients'); ?></span>
                            <svg class="wec-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    </div>

                    <div class="wec-credits">
                        <p>
                            <?php esc_html_e('Desenvolvido por', 'whatsapp-evolution-clients'); ?>
                            <a href="https://dantetesta.com.br" target="_blank" rel="noopener">Dante Testa</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtém a URL da API
     * 
     * @return string
     */
    public static function get_api_url(): string
    {
        return get_option(self::OPT_API_URL, '');
    }

    /**
     * Obtém o nome da instância
     * 
     * @return string
     */
    public static function get_instance_name(): string
    {
        return get_option(self::OPT_INSTANCE, '');
    }

    /**
     * Obtém o token descriptografado
     * 
     * @return string
     */
    public static function get_token(): string
    {
        $encrypted = get_option(self::OPT_TOKEN, '');
        if (empty($encrypted)) {
            return '';
        }
        return WEC_Security::decrypt($encrypted);
    }

    /**
     * Obtém o número do remetente
     * 
     * @return string
     */
    public static function get_sender_number(): string
    {
        return get_option(self::OPT_SENDER, '');
    }

    /**
     * Obtém a Global API Key descriptografada
     * 
     * @return string
     */
    public static function get_global_key(): string
    {
        $encrypted = get_option(self::OPT_GLOBAL_KEY, '');
        if (empty($encrypted)) {
            return '';
        }
        return WEC_Security::decrypt($encrypted);
    }

    /**
     * Verifica se as configurações estão completas
     * 
     * @return bool
     */
    public static function is_configured(): bool
    {
        return !empty(self::get_api_url()) &&
            !empty(self::get_instance_name()) &&
            !empty(self::get_token());
    }

    /**
     * Verifica se pode testar conexão (precisa da Global Key)
     * 
     * @return bool
     */
    public static function can_test_connection(): bool
    {
        return !empty(self::get_api_url()) &&
            !empty(self::get_instance_name()) &&
            !empty(self::get_global_key());
    }
}

