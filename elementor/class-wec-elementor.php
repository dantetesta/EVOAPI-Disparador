<?php
/**
 * Integração com Elementor
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 2.2.0
 * @created 2025-12-13 22:15:00
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se Elementor está carregado
if (!did_action('elementor/loaded')) {
    return;
}

class WEC_Elementor
{
    private static $instance = null;

    public static function instance(): WEC_Elementor
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_styles']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handler para submissão do formulário
        add_action('wp_ajax_wec_lead_form_submit', [$this, 'handle_form_submit']);
        add_action('wp_ajax_nopriv_wec_lead_form_submit', [$this, 'handle_form_submit']);
    }

    // Registra categoria do widget
    public function register_category($elements_manager)
    {
        $elements_manager->add_category(
            'wec-widgets',
            [
                'title' => __('WP PostZap', 'whatsapp-evolution-clients'),
                'icon' => 'fa fa-whatsapp',
            ]
        );
    }

    // Registra widgets
    public function register_widgets($widgets_manager)
    {
        require_once WEC_PLUGIN_DIR . 'elementor/widgets/class-wec-lead-form-widget.php';
        $widgets_manager->register(new WEC_Lead_Form_Widget());
    }

    // Enqueue styles
    public function enqueue_styles()
    {
        // Cropper.js CSS
        wp_enqueue_style(
            'cropperjs',
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css',
            [],
            '1.6.1'
        );

        wp_enqueue_style(
            'wec-elementor-form',
            WEC_PLUGIN_URL . 'elementor/assets/css/lead-form.css',
            ['cropperjs'],
            WEC_VERSION
        );
    }

    // Enqueue scripts
    public function enqueue_scripts()
    {
        // Cropper.js
        wp_enqueue_script(
            'cropperjs',
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js',
            [],
            '1.6.1',
            true
        );

        wp_enqueue_script(
            'wec-elementor-form',
            WEC_PLUGIN_URL . 'elementor/assets/js/lead-form.js',
            ['jquery', 'cropperjs'],
            WEC_VERSION,
            true
        );

        wp_localize_script('wec-elementor-form', 'WEC_FORM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wec_lead_form_nonce'),
            'cropperTexts' => [
                'title' => __('Recortar Imagem', 'whatsapp-evolution-clients'),
                'confirm' => __('Confirmar', 'whatsapp-evolution-clients'),
                'cancel' => __('Cancelar', 'whatsapp-evolution-clients'),
                'rotate' => __('Girar', 'whatsapp-evolution-clients'),
                'zoom' => __('Zoom', 'whatsapp-evolution-clients'),
            ],
        ]);
    }

    // Handler AJAX para submissão do formulário
    public function handle_form_submit()
    {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wec_lead_form_nonce')) {
            wp_send_json_error(['message' => __('Sessão expirada. Recarregue a página.', 'whatsapp-evolution-clients')]);
        }

        // Sanitizar dados
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $whatsapp = sanitize_text_field($_POST['whatsapp'] ?? '');
        $whatsapp_ddi = sanitize_text_field($_POST['whatsapp_ddi'] ?? '+55');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $categories = isset($_POST['categories']) ? array_map('intval', (array)$_POST['categories']) : [];
        $interests = isset($_POST['interests']) ? array_map('intval', (array)$_POST['interests']) : [];

        // Validações
        if (empty($name)) {
            wp_send_json_error(['message' => __('O nome é obrigatório.', 'whatsapp-evolution-clients')]);
        }

        if (!empty($email) && !is_email($email)) {
            wp_send_json_error(['message' => __('E-mail inválido.', 'whatsapp-evolution-clients')]);
        }

        // Formatar WhatsApp com DDI
        $whatsapp_e164 = '';
        if (!empty($whatsapp)) {
            // Remover caracteres não numéricos do telefone
            $phone_numbers = preg_replace('/[^0-9]/', '', $whatsapp);
            // Remover + do DDI e manter só números
            $ddi_numbers = preg_replace('/[^0-9]/', '', $whatsapp_ddi);
            
            if (strlen($phone_numbers) < 8) {
                wp_send_json_error(['message' => __('WhatsApp inválido.', 'whatsapp-evolution-clients')]);
            }
            
            // Formato E.164: código do país + número
            $whatsapp_e164 = $ddi_numbers . $phone_numbers;
        }

        // Verificar duplicidade por WhatsApp ou email
        if (!empty($whatsapp_e164)) {
            $existing = get_posts([
                'post_type' => WEC_CPT::POST_TYPE,
                'meta_key' => '_wec_whatsapp_e164',
                'meta_value' => $whatsapp_e164,
                'posts_per_page' => 1,
            ]);
            if (!empty($existing)) {
                wp_send_json_error(['message' => __('Este WhatsApp já está cadastrado.', 'whatsapp-evolution-clients')]);
            }
        }

        // Criar lead
        $post_data = [
            'post_type' => WEC_CPT::POST_TYPE,
            'post_title' => $name,
            'post_content' => $description,
            'post_status' => 'publish',
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => __('Erro ao cadastrar. Tente novamente.', 'whatsapp-evolution-clients')]);
        }

        // Salvar meta fields
        if (!empty($email)) {
            update_post_meta($post_id, '_wec_email', $email);
        }

        if (!empty($whatsapp_e164)) {
            update_post_meta($post_id, '_wec_whatsapp_e164', $whatsapp_e164);
            update_post_meta($post_id, '_wec_whatsapp_display', $whatsapp);
        }

        // Taxonomias
        if (!empty($categories)) {
            wp_set_post_terms($post_id, $categories, WEC_CPT::TAXONOMY);
        }

        if (!empty($interests)) {
            wp_set_post_terms($post_id, $interests, WEC_CPT::TAXONOMY_INTEREST);
        }

        // Upload de foto (cropada via base64 ou arquivo direto)
        $photo_cropped = sanitize_text_field($_POST['photo_cropped'] ?? '');
        
        if (!empty($photo_cropped) && strpos($photo_cropped, 'data:image') === 0) {
            // Processar imagem base64 cropada
            $attachment_id = $this->save_base64_image($photo_cropped, $post_id, $name);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        } elseif (!empty($_FILES['photo_original']) && $_FILES['photo_original']['error'] === UPLOAD_ERR_OK) {
            // Upload direto (cropper desabilitado)
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attachment_id = media_handle_upload('photo_original', $post_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        wp_send_json_success([
            'message' => __('Cadastro realizado com sucesso!', 'whatsapp-evolution-clients'),
            'lead_id' => $post_id,
        ]);
    }

    // Salvar imagem base64 como attachment
    private function save_base64_image($base64_string, $post_id, $title)
    {
        // Extrair dados da imagem
        $data = explode(',', $base64_string);
        if (count($data) !== 2) return false;
        
        $image_data = base64_decode($data[1]);
        if (!$image_data) return false;
        
        // Determinar extensão
        $extension = 'jpg';
        if (strpos($data[0], 'png') !== false) {
            $extension = 'png';
        }
        
        // Criar nome único
        $filename = sanitize_file_name($title) . '-' . time() . '.' . $extension;
        
        // Diretório de uploads
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // Salvar arquivo
        if (!file_put_contents($file_path, $image_data)) {
            return false;
        }
        
        // Criar attachment
        $attachment = [
            'post_mime_type' => 'image/' . ($extension === 'png' ? 'png' : 'jpeg'),
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // Gerar metadados
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        return $attachment_id;
    }
}
