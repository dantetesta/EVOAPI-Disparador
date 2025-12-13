<?php
/**
 * Classe de Integração com Evolution API
 * 
 * Gerencia as requisições HTTP para a Evolution API
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
 * Classe WEC_API
 */
class WEC_API
{

    /**
     * Instância única
     * 
     * @var WEC_API
     */
    private static $instance = null;

    /**
     * Timeout padrão para requisições (segundos)
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * Retorna a instância única
     * 
     * @return WEC_API
     */
    public static function instance(): WEC_API
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
        // Nada a fazer
    }

    /**
     * Testa a conexão com a Evolution API
     * 
     * @return array
     */
    public static function test_connection(): array
    {
        // Verificar se pode testar (precisa da Global Key)
        if (!WEC_Settings::can_test_connection()) {
            return [
                'success' => false,
                'error' => __('Configurações incompletas. Preencha URL, Instance Name e Global API Key.', 'whatsapp-evolution-clients')
            ];
        }

        $api_url = WEC_Settings::get_api_url();
        $instance = WEC_Settings::get_instance_name();
        $global_key = WEC_Settings::get_global_key();

        // Endpoint para verificar status da instância (usa Global Key)
        $endpoint = $api_url . '/instance/connectionState/' . $instance;

        $response = wp_remote_get($endpoint, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'apikey' => $global_key,
            ],
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => sprintf(
                    __('Erro de conexão: %s', 'whatsapp-evolution-clients'),
                    $response->get_error_message()
                )
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Verificar código HTTP
        if ($http_code !== 200 && $http_code !== 201) {
            $error_msg = $data['message'] ?? $data['error'] ?? __('Erro desconhecido', 'whatsapp-evolution-clients');
            return [
                'success' => false,
                'error' => sprintf(
                    __('HTTP %d: %s', 'whatsapp-evolution-clients'),
                    $http_code,
                    $error_msg
                )
            ];
        }

        // Extrair status
        $state = $data['instance']['state'] ?? $data['state'] ?? 'unknown';
        $connected = ($state === 'open');

        return [
            'success' => true,
            'connected' => $connected,
            'state' => $state,
            'message' => $connected
                ? __('Conexão com Evolution API OK. WhatsApp conectado!', 'whatsapp-evolution-clients')
                : sprintf(__('Conexão OK, mas WhatsApp não conectado (estado: %s).', 'whatsapp-evolution-clients'), $state)
        ];
    }

    /**
     * Envia uma mensagem de texto via WhatsApp
     * 
     * @param string $phone_e164 Número do destinatário em E.164
     * @param string $message Mensagem a enviar
     * @return array
     */
    public static function send_text_message(string $phone_e164, string $message): array
    {
        // Verificar se está configurado
        if (!WEC_Settings::is_configured()) {
            return [
                'success' => false,
                'error' => __('Configurações da API não definidas.', 'whatsapp-evolution-clients')
            ];
        }

        // Validar telefone
        if (!WEC_Security::validate_phone($phone_e164)) {
            return [
                'success' => false,
                'error' => __('Número de telefone inválido.', 'whatsapp-evolution-clients')
            ];
        }

        // Validar mensagem
        if (empty(trim($message))) {
            return [
                'success' => false,
                'error' => __('Mensagem não pode estar vazia.', 'whatsapp-evolution-clients')
            ];
        }

        $api_url = WEC_Settings::get_api_url();
        $instance = WEC_Settings::get_instance_name();
        $token = WEC_Settings::get_token();

        // Formatar número para API (sem o +)
        $phone_for_api = WEC_Security::format_phone_for_api($phone_e164);

        // Endpoint de envio de texto
        $endpoint = $api_url . '/message/sendText/' . $instance;

        // Payload
        $payload = [
            'number' => $phone_for_api,
            'text' => $message,
        ];

        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC] Enviando mensagem para: ' . $phone_for_api);
            error_log('[WEC] Endpoint: ' . $endpoint);
        }

        // Enviar requisição
        $response = wp_remote_post($endpoint, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'apikey' => $token,
            ],
            'body' => wp_json_encode($payload),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => sprintf(
                    __('Erro de conexão: %s', 'whatsapp-evolution-clients'),
                    $response->get_error_message()
                )
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC] HTTP Code: ' . $http_code);
            error_log('[WEC] Response: ' . $body);
        }

        // Verificar código HTTP
        if ($http_code !== 200 && $http_code !== 201) {
            $error_msg = $data['message'] ?? $data['error'] ?? __('Erro desconhecido', 'whatsapp-evolution-clients');

            // Tratamento de erros específicos
            if ($http_code === 401) {
                $error_msg = __('Token inválido ou expirado.', 'whatsapp-evolution-clients');
            } elseif ($http_code === 404) {
                $error_msg = __('Instância não encontrada.', 'whatsapp-evolution-clients');
            } elseif ($http_code === 400) {
                $error_msg = __('Requisição inválida. Verifique o número de telefone.', 'whatsapp-evolution-clients');
            }

            return [
                'success' => false,
                'error' => sprintf(__('HTTP %d: %s', 'whatsapp-evolution-clients'), $http_code, $error_msg),
                'debug' => $data
            ];
        }

        // Sucesso
        return [
            'success' => true,
            'message_id' => $data['key']['id'] ?? null,
            'timestamp' => $data['messageTimestamp'] ?? null,
            'message' => __('Mensagem enviada com sucesso!', 'whatsapp-evolution-clients')
        ];
    }

    /**
     * Envia uma mensagem com mídia (imagem) via WhatsApp
     * 
     * @param string $phone_e164 Número do destinatário em E.164
     * @param string $image_base64 Imagem em base64 (sem prefixo data:)
     * @param string $mimetype Tipo MIME da imagem
     * @param string $filename Nome do arquivo
     * @param string $caption Legenda da imagem (opcional)
     * @return array
     */
    public static function send_media_message(
        string $phone_e164,
        string $image_base64,
        string $mimetype,
        string $filename,
        string $caption = ''
    ): array {
        // Verificar se está configurado
        if (!WEC_Settings::is_configured()) {
            return [
                'success' => false,
                'error' => __('Configurações da API não definidas.', 'whatsapp-evolution-clients')
            ];
        }

        // Validar telefone
        if (!WEC_Security::validate_phone($phone_e164)) {
            return [
                'success' => false,
                'error' => __('Número de telefone inválido.', 'whatsapp-evolution-clients')
            ];
        }

        // Validar imagem
        if (empty($image_base64)) {
            return [
                'success' => false,
                'error' => __('Imagem não fornecida.', 'whatsapp-evolution-clients')
            ];
        }

        // Validar mime type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mimetype, $allowed_types)) {
            return [
                'success' => false,
                'error' => __('Tipo de imagem não permitido. Use JPG, PNG ou GIF.', 'whatsapp-evolution-clients')
            ];
        }

        $api_url = WEC_Settings::get_api_url();
        $instance = WEC_Settings::get_instance_name();
        $token = WEC_Settings::get_token();

        // Formatar número para API (sem o +)
        $phone_for_api = WEC_Security::format_phone_for_api($phone_e164);

        // Endpoint de envio de mídia
        $endpoint = $api_url . '/message/sendMedia/' . $instance;

        // Payload
        $payload = [
            'number' => $phone_for_api,
            'mediatype' => 'image',
            'mimetype' => $mimetype,
            'media' => $image_base64, // Base64 puro, sem prefixo
            'fileName' => $filename,
        ];

        // Adicionar caption se existir
        if (!empty($caption)) {
            $payload['caption'] = $caption;
        }

        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC] Enviando imagem para: ' . $phone_for_api);
            error_log('[WEC] Endpoint: ' . $endpoint);
            error_log('[WEC] Filename: ' . $filename);
        }

        // Enviar requisição (timeout maior para upload)
        $response = wp_remote_post($endpoint, [
            'timeout' => 120, // 2 minutos para upload
            'headers' => [
                'Content-Type' => 'application/json',
                'apikey' => $token,
            ],
            'body' => wp_json_encode($payload),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => sprintf(
                    __('Erro de conexão: %s', 'whatsapp-evolution-clients'),
                    $response->get_error_message()
                )
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC] HTTP Code: ' . $http_code);
            error_log('[WEC] Response: ' . substr($body, 0, 500));
        }

        // Verificar código HTTP
        if ($http_code !== 200 && $http_code !== 201) {
            $error_msg = $data['message'] ?? $data['error'] ?? __('Erro desconhecido', 'whatsapp-evolution-clients');

            if ($http_code === 401) {
                $error_msg = __('Token inválido ou expirado.', 'whatsapp-evolution-clients');
            } elseif ($http_code === 404) {
                $error_msg = __('Instância não encontrada.', 'whatsapp-evolution-clients');
            } elseif ($http_code === 400) {
                $error_msg = __('Requisição inválida. Verifique a imagem.', 'whatsapp-evolution-clients');
            }

            return [
                'success' => false,
                'error' => sprintf(__('HTTP %d: %s', 'whatsapp-evolution-clients'), $http_code, $error_msg),
                'debug' => $data
            ];
        }

        // Sucesso
        return [
            'success' => true,
            'message_id' => $data['key']['id'] ?? null,
            'timestamp' => $data['messageTimestamp'] ?? null,
            'message' => __('Imagem enviada com sucesso!', 'whatsapp-evolution-clients')
        ];
    }


    /**
     * Envia mensagem de texto (wrapper)
     */
    public function send_message(string $phone, string $message): array
    {
        return self::send_text_message($phone, $message);
    }

    /**
     * Envia mídia via URL (Evolution API v2)
     */
    public function send_media(string $phone, string $media_url, string $type = 'image', string $caption = ''): array
    {
        if (!WEC_Settings::is_configured()) {
            return ['success' => false, 'error' => 'API não configurada'];
        }

        if (!WEC_Security::validate_phone($phone)) {
            return ['success' => false, 'error' => 'Telefone inválido'];
        }

        $api_url = WEC_Settings::get_api_url();
        $instance = WEC_Settings::get_instance_name();
        $token = WEC_Settings::get_token();
        $phone_for_api = WEC_Security::format_phone_for_api($phone);

        // Evolution API v2 - endpoint correto para mídia
        $endpoint = $api_url . '/message/sendMedia/' . $instance;

        // Payload correto para Evolution API v2
        $payload = [
            'number' => $phone_for_api,
            'mediatype' => $type,
            'mimetype' => 'image/jpeg',
            'media' => $media_url,
            'fileName' => 'noticia.jpg',
        ];

        if (!empty($caption)) {
            $payload['caption'] = $caption;
        }

        // Log debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC API] Endpoint: ' . $endpoint);
            error_log('[WEC API] Payload: ' . wp_json_encode($payload));
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 180,
            'headers' => [
                'Content-Type' => 'application/json',
                'apikey' => $token,
            ],
            'body' => wp_json_encode($payload),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Log debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC API] Response: ' . $http_code . ' - ' . substr($body, 0, 500));
        }

        if ($http_code !== 200 && $http_code !== 201) {
            return [
                'success' => false,
                'error' => $data['message'] ?? $data['error'] ?? "HTTP $http_code"
            ];
        }

        return [
            'success' => true,
            'message_id' => $data['key']['id'] ?? null,
        ];
    }

    /**
     * Envia apenas imagem (sem caption)
     */
    public function send_image(string $phone, string $image_url): array
    {
        if (!WEC_Settings::is_configured()) {
            return ['success' => false, 'error' => 'API não configurada'];
        }

        if (!WEC_Security::validate_phone($phone)) {
            return ['success' => false, 'error' => 'Telefone inválido'];
        }

        $api_url = WEC_Settings::get_api_url();
        $instance = WEC_Settings::get_instance_name();
        $token = WEC_Settings::get_token();
        $phone_for_api = WEC_Security::format_phone_for_api($phone);

        $endpoint = $api_url . '/message/sendMedia/' . $instance;

        $payload = [
            'number' => $phone_for_api,
            'mediatype' => 'image',
            'mimetype' => 'image/jpeg',
            'media' => $image_url,
            'fileName' => 'noticia.jpg',
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC API] Enviando imagem: ' . $endpoint);
            error_log('[WEC API] Payload: ' . wp_json_encode($payload));
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 180,
            'headers' => [
                'Content-Type' => 'application/json',
                'apikey' => $token,
            ],
            'body' => wp_json_encode($payload),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC API] Response imagem: ' . $http_code . ' - ' . substr($body, 0, 300));
        }

        if ($http_code !== 200 && $http_code !== 201) {
            return [
                'success' => false,
                'error' => $data['message'] ?? $data['error'] ?? "HTTP $http_code"
            ];
        }

        return [
            'success' => true,
            'message_id' => $data['key']['id'] ?? null,
        ];
    }

    /**
     * Formata um número de telefone para a API
     * Função auxiliar baseada na documentação
     * 
     * @param string $phone Número a formatar
     * @return string
     */
    public static function format_phone_number(string $phone): string
    {
        // Remover não-numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Já tem DDI (55 + 12-13 dígitos)
        if (strlen($phone) >= 12 && substr($phone, 0, 2) === '55') {
            return $phone;
        }

        // 11 dígitos (DDD + 9 + número)
        if (strlen($phone) === 11) {
            return '55' . $phone;
        }

        // 10 dígitos (DDD + número)
        if (strlen($phone) === 10) {
            $ddd = substr($phone, 0, 2);
            $firstDigit = substr($phone, 2, 1);

            // Celular (8 ou 9)
            if ($firstDigit === '8' || $firstDigit === '9') {
                // Adicionar 9 se começa com 8
                if ($firstDigit === '8') {
                    $phone = $ddd . '9' . substr($phone, 2);
                }
                return '55' . $phone;
            }

            // Fixo
            return '55' . $phone;
        }

        // 9 dígitos - adicionar DDD padrão (11)
        if (strlen($phone) === 9) {
            return '5511' . $phone;
        }

        // Outros casos
        return '5511' . $phone;
    }
}
