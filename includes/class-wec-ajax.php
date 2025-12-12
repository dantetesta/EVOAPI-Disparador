<?php
/**
 * Classe de Handlers AJAX
 * 
 * Processa requisições AJAX do plugin
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
 * Classe WEC_Ajax
 */
class WEC_Ajax
{

    /**
     * Instância única
     * 
     * @var WEC_Ajax
     */
    private static $instance = null;

    /**
     * Retorna a instância única
     * 
     * @return WEC_Ajax
     */
    public static function instance(): WEC_Ajax
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
        // Teste de conexão
        add_action('wp_ajax_wec_test_connection', [$this, 'ajax_test_connection']);

        // Envio individual
        add_action('wp_ajax_wec_send_single', [$this, 'ajax_send_single']);

        // Envio em massa (um por vez)
        add_action('wp_ajax_wec_send_bulk_single', [$this, 'ajax_send_bulk_single']);

        // Obter clientes para modal
        add_action('wp_ajax_wec_get_clients', [$this, 'ajax_get_clients']);
    }

    /**
     * Testa a conexão com a Evolution API
     */
    public function ajax_test_connection(): void
    {
        // Verificar nonce
        if (!check_ajax_referer('wec_ajax_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Nonce inválido.', 'whatsapp-evolution-clients')
            ]);
        }

        // Verificar permissões
        if (!WEC_Security::can_manage_settings()) {
            wp_send_json_error([
                'message' => __('Você não tem permissão para esta ação.', 'whatsapp-evolution-clients')
            ]);
        }

        // Testar conexão
        $result = WEC_API::test_connection();

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'connected' => $result['connected'] ?? false,
                'state' => $result['state'] ?? 'unknown'
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error']
            ]);
        }
    }

    /**
     * Envia mensagem individual
     */
    public function ajax_send_single(): void
    {
        // Verificar nonce
        if (!check_ajax_referer('wec_ajax_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Nonce inválido.', 'whatsapp-evolution-clients')
            ]);
        }

        // Verificar permissões
        if (!WEC_Security::can_send_messages()) {
            wp_send_json_error([
                'message' => __('Você não tem permissão para enviar mensagens.', 'whatsapp-evolution-clients')
            ]);
        }

        // Obter parâmetros
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $message = isset($_POST['message']) ? WEC_Security::sanitize_message($_POST['message']) : '';

        // Parâmetros de imagem
        $image_base64 = isset($_POST['image_base64']) ? sanitize_text_field($_POST['image_base64']) : '';
        $image_mimetype = isset($_POST['image_mimetype']) ? sanitize_text_field($_POST['image_mimetype']) : '';
        $image_filename = isset($_POST['image_filename']) ? sanitize_file_name($_POST['image_filename']) : '';

        // Validar cliente
        if ($client_id <= 0) {
            wp_send_json_error([
                'message' => __('ID do cliente inválido.', 'whatsapp-evolution-clients')
            ]);
        }

        // Validar - precisa ter mensagem OU imagem
        $has_message = !empty($message);
        $has_image = !empty($image_base64) && !empty($image_mimetype);

        if (!$has_message && !$has_image) {
            wp_send_json_error([
                'message' => __('Envie uma mensagem ou anexe uma imagem.', 'whatsapp-evolution-clients')
            ]);
        }

        // Obter dados do cliente
        $client = WEC_CPT::get_client($client_id);

        if (!$client) {
            wp_send_json_error([
                'message' => __('Cliente não encontrado.', 'whatsapp-evolution-clients')
            ]);
        }

        if (empty($client['whatsapp'])) {
            wp_send_json_error([
                'message' => __('Cliente não possui número de WhatsApp cadastrado.', 'whatsapp-evolution-clients')
            ]);
        }

        // Enviar
        if ($has_image) {
            // Enviar imagem (com caption se tiver mensagem)
            $result = WEC_API::send_media_message(
                $client['whatsapp'],
                $image_base64,
                $image_mimetype,
                $image_filename,
                $message // A mensagem se torna a legenda da imagem
            );
        } else {
            // Enviar só texto
            $result = WEC_API::send_text_message($client['whatsapp'], $message);
        }

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'client_name' => $client['name'],
                'whatsapp' => $client['whatsapp']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error']
            ]);
        }
    }

    /**
     * Envia mensagem para um cliente (parte do envio em massa)
     */
    public function ajax_send_bulk_single(): void
    {
        // Verificar nonce
        if (!check_ajax_referer('wec_ajax_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Nonce inválido.', 'whatsapp-evolution-clients')
            ]);
        }

        // Verificar permissões
        if (!WEC_Security::can_send_messages()) {
            wp_send_json_error([
                'message' => __('Você não tem permissão para enviar mensagens.', 'whatsapp-evolution-clients')
            ]);
        }

        // Obter parâmetros
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $message = isset($_POST['message']) ? WEC_Security::sanitize_message($_POST['message']) : '';

        // Parâmetros de imagem
        $image_base64 = isset($_POST['image_base64']) ? sanitize_text_field($_POST['image_base64']) : '';
        $image_mimetype = isset($_POST['image_mimetype']) ? sanitize_text_field($_POST['image_mimetype']) : '';
        $image_filename = isset($_POST['image_filename']) ? sanitize_file_name($_POST['image_filename']) : '';

        // Validar cliente
        if ($client_id <= 0) {
            wp_send_json_error([
                'client_id' => $client_id,
                'status' => 'failed',
                'message' => __('ID do cliente inválido.', 'whatsapp-evolution-clients')
            ]);
        }

        // Validar - precisa ter mensagem OU imagem
        $has_message = !empty($message);
        $has_image = !empty($image_base64) && !empty($image_mimetype);

        if (!$has_message && !$has_image) {
            wp_send_json_error([
                'client_id' => $client_id,
                'status' => 'failed',
                'message' => __('Envie uma mensagem ou anexe uma imagem.', 'whatsapp-evolution-clients')
            ]);
        }

        // Obter dados do cliente
        $client = WEC_CPT::get_client($client_id);

        if (!$client) {
            wp_send_json_error([
                'client_id' => $client_id,
                'status' => 'failed',
                'message' => __('Cliente não encontrado.', 'whatsapp-evolution-clients')
            ]);
        }

        if (empty($client['whatsapp']) || !WEC_Security::validate_phone($client['whatsapp'])) {
            wp_send_json_error([
                'client_id' => $client_id,
                'client_name' => $client['name'],
                'whatsapp' => $client['whatsapp'] ?? '',
                'status' => 'failed',
                'message' => __('Número de WhatsApp inválido ou não cadastrado.', 'whatsapp-evolution-clients')
            ]);
        }

        // Enviar
        if ($has_image) {
            // Enviar imagem (com caption se tiver mensagem)
            $result = WEC_API::send_media_message(
                $client['whatsapp'],
                $image_base64,
                $image_mimetype,
                $image_filename,
                $message // A mensagem se torna a legenda da imagem
            );
        } else {
            // Enviar só texto
            $result = WEC_API::send_text_message($client['whatsapp'], $message);
        }

        if ($result['success']) {
            wp_send_json_success([
                'client_id' => $client_id,
                'client_name' => $client['name'],
                'whatsapp' => $client['whatsapp'],
                'status' => 'sent',
                'message' => $result['message']
            ]);
        } else {
            wp_send_json_error([
                'client_id' => $client_id,
                'client_name' => $client['name'],
                'whatsapp' => $client['whatsapp'],
                'status' => 'failed',
                'message' => $result['error']
            ]);
        }
    }

    /**
     * Obtém clientes para o modal de envio em massa
     */
    public function ajax_get_clients(): void
    {
        // Verificar nonce
        if (!check_ajax_referer('wec_ajax_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Nonce inválido.', 'whatsapp-evolution-clients')
            ]);
        }

        // Verificar permissões
        if (!WEC_Security::can_send_messages()) {
            wp_send_json_error([
                'message' => __('Você não tem permissão para esta ação.', 'whatsapp-evolution-clients')
            ]);
        }

        // Obter IDs
        $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];

        if (empty($ids)) {
            wp_send_json_error([
                'message' => __('Nenhum cliente selecionado.', 'whatsapp-evolution-clients')
            ]);
        }

        // Obter clientes
        $clients = WEC_CPT::get_clients_with_whatsapp($ids);

        wp_send_json_success([
            'clients' => $clients,
            'total' => count($clients)
        ]);
    }
}
