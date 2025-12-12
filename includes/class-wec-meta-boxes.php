<?php
/**
 * Classe de Meta Boxes
 * 
 * Gerencia os campos personalizados do CPT Cliente
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
 * Classe WEC_Meta_Boxes
 */
class WEC_Meta_Boxes
{

    /**
     * Instância única
     * 
     * @var WEC_Meta_Boxes
     */
    private static $instance = null;

    /**
     * Meta keys
     */
    const META_EMAIL = '_wec_email';
    const META_WHATSAPP = '_wec_whatsapp_e164';
    const META_DESCRIPTION = '_wec_description';

    /**
     * Retorna a instância única
     * 
     * @return WEC_Meta_Boxes
     */
    public static function instance(): WEC_Meta_Boxes
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
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . WEC_CPT::POST_TYPE, [$this, 'save_meta_boxes'], 10, 2);
        add_action('admin_notices', [$this, 'show_validation_notices']);
    }

    /**
     * Adiciona meta boxes
     */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'wec_client_details',
            __('Dados do Cliente', 'whatsapp-evolution-clients'),
            [$this, 'render_meta_box'],
            WEC_CPT::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza o meta box
     * 
     * @param WP_Post $post Post atual
     */
    public function render_meta_box(WP_Post $post): void
    {
        // Nonce para verificação
        wp_nonce_field('wec_save_client_meta', 'wec_client_meta_nonce');

        // Valores atuais
        $email = get_post_meta($post->ID, self::META_EMAIL, true);
        $whatsapp = get_post_meta($post->ID, self::META_WHATSAPP, true);
        $description = get_post_meta($post->ID, self::META_DESCRIPTION, true);

        ?>
        <div class="wec-meta-box-wrapper">
            <style>
                .wec-meta-box-wrapper {
                    padding: 10px 0;
                }

                .wec-field-group {
                    margin-bottom: 20px;
                }

                .wec-field-group label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                    color: #1d2327;
                }

                .wec-field-group input[type="email"],
                .wec-field-group input[type="tel"],
                .wec-field-group textarea {
                    width: 100%;
                    max-width: 400px;
                }

                .wec-field-group textarea {
                    min-height: 100px;
                }

                .wec-field-description {
                    color: #646970;
                    font-size: 12px;
                    margin-top: 5px;
                }

                .wec-phone-wrapper {
                    max-width: 400px;
                }

                .wec-phone-wrapper .iti {
                    width: 100%;
                }

                .wec-phone-wrapper .iti__tel-input {
                    width: 100%;
                }
            </style>

            <!-- Campo E-mail -->
            <div class="wec-field-group">
                <label for="wec_email"><?php esc_html_e('E-mail', 'whatsapp-evolution-clients'); ?></label>
                <input type="email" id="wec_email" name="wec_email" value="<?php echo esc_attr($email); ?>"
                    placeholder="cliente@exemplo.com" />
                <p class="wec-field-description">
                    <?php esc_html_e('E-mail de contato do cliente.', 'whatsapp-evolution-clients'); ?>
                </p>
            </div>

            <!-- Campo WhatsApp com intl-tel-input -->
            <div class="wec-field-group">
                <label for="wec_whatsapp"><?php esc_html_e('WhatsApp (com DDI)', 'whatsapp-evolution-clients'); ?></label>
                <div class="wec-phone-wrapper">
                    <input type="tel" id="wec_whatsapp" name="wec_whatsapp_display" class="wec-phone-input"
                        value="<?php echo esc_attr($whatsapp); ?>" placeholder="(11) 99999-9999" />
                    <!-- Campo hidden para armazenar o valor E.164 -->
                    <input type="hidden" id="wec_whatsapp_e164" name="wec_whatsapp"
                        value="<?php echo esc_attr($whatsapp); ?>" />
                </div>
                <p class="wec-field-description">
                    <?php esc_html_e('Número de WhatsApp com código do país. Será salvo no formato internacional (ex: +5519980219567).', 'whatsapp-evolution-clients'); ?>
                </p>
            </div>

            <!-- Campo Descrição -->
            <div class="wec-field-group">
                <label
                    for="wec_description"><?php esc_html_e('Descrição / Observações', 'whatsapp-evolution-clients'); ?></label>
                <textarea id="wec_description" name="wec_description" rows="4"
                    placeholder="<?php esc_attr_e('Observações sobre o cliente...', 'whatsapp-evolution-clients'); ?>"><?php echo esc_textarea($description); ?></textarea>
            </div>
        </div>
        <?php
    }

    /**
     * Salva os meta dados
     * 
     * @param int $post_id ID do post
     * @param WP_Post $post Objeto do post
     */
    public function save_meta_boxes(int $post_id, WP_Post $post): void
    {
        // Verificar nonce
        if (
            !isset($_POST['wec_client_meta_nonce']) ||
            !wp_verify_nonce($_POST['wec_client_meta_nonce'], 'wec_save_client_meta')
        ) {
            return;
        }

        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verificar permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Salvar E-mail
        if (isset($_POST['wec_email'])) {
            $email = sanitize_email($_POST['wec_email']);
            update_post_meta($post_id, self::META_EMAIL, $email);
        }

        // Salvar WhatsApp (E.164)
        if (isset($_POST['wec_whatsapp'])) {
            $whatsapp = WEC_Security::sanitize_phone($_POST['wec_whatsapp']);

            // Validar se não está vazio
            if (!empty($whatsapp)) {
                // Verificar se é um número válido E.164
                if (WEC_Security::validate_phone($whatsapp)) {
                    update_post_meta($post_id, self::META_WHATSAPP, $whatsapp);
                } else {
                    // Marcar erro para exibir notice
                    set_transient('wec_phone_error_' . $post_id, true, 30);
                    // Salvar mesmo assim para não perder o valor digitado
                    update_post_meta($post_id, self::META_WHATSAPP, $whatsapp);
                }
            } else {
                delete_post_meta($post_id, self::META_WHATSAPP);
            }
        }

        // Salvar Descrição
        if (isset($_POST['wec_description'])) {
            $description = sanitize_textarea_field($_POST['wec_description']);
            update_post_meta($post_id, self::META_DESCRIPTION, $description);
        }
    }

    /**
     * Exibe notices de validação
     */
    public function show_validation_notices(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== WEC_CPT::POST_TYPE) {
            return;
        }

        // Verificar se há erro de telefone
        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            $error = get_transient('wec_phone_error_' . $post_id);

            if ($error) {
                delete_transient('wec_phone_error_' . $post_id);
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong><?php esc_html_e('Atenção:', 'whatsapp-evolution-clients'); ?></strong>
                        <?php esc_html_e('O número de WhatsApp informado pode não estar no formato E.164 correto. Verifique se o número inclui o código do país (ex: +5519980219567).', 'whatsapp-evolution-clients'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
}
