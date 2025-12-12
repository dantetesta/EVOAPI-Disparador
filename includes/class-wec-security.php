<?php
/**
 * Classe de Segurança do Plugin
 * 
 * Gerencia capabilities, nonces e sanitização
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
 * Classe WEC_Security
 */
class WEC_Security
{

    /**
     * Instância única
     * 
     * @var WEC_Security
     */
    private static $instance = null;

    /**
     * Nonce action para operações AJAX
     */
    const NONCE_ACTION = 'wec_ajax_nonce';

    /**
     * Capability para gerenciar clientes
     */
    const CAP_MANAGE_CLIENTS = 'manage_wec_clients';

    /**
     * Capability para editar clientes
     */
    const CAP_EDIT_CLIENTS = 'edit_wec_client';

    /**
     * Retorna a instância única
     * 
     * @return WEC_Security
     */
    public static function instance(): WEC_Security
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
        // Nada a fazer por enquanto
    }

    /**
     * Adiciona capabilities aos roles
     */
    public static function add_capabilities(): void
    {
        $admin = get_role('administrator');

        if ($admin) {
            $admin->add_cap(self::CAP_MANAGE_CLIENTS);
            $admin->add_cap(self::CAP_EDIT_CLIENTS);
            $admin->add_cap('edit_wec_clients');
            $admin->add_cap('delete_wec_client');
            $admin->add_cap('delete_wec_clients');
            $admin->add_cap('publish_wec_clients');
            $admin->add_cap('read_wec_client');
        }

        // Editor também pode gerenciar clientes
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap(self::CAP_EDIT_CLIENTS);
            $editor->add_cap('edit_wec_clients');
            $editor->add_cap('read_wec_client');
        }
    }

    /**
     * Remove capabilities dos roles
     */
    public static function remove_capabilities(): void
    {
        $roles = ['administrator', 'editor'];
        $caps = [
            self::CAP_MANAGE_CLIENTS,
            self::CAP_EDIT_CLIENTS,
            'edit_wec_clients',
            'delete_wec_client',
            'delete_wec_clients',
            'publish_wec_clients',
            'read_wec_client'
        ];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }

    /**
     * Verifica se o usuário pode gerenciar configurações
     * 
     * @return bool
     */
    public static function can_manage_settings(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_MANAGE_CLIENTS);
    }

    /**
     * Verifica se o usuário pode enviar mensagens
     * 
     * @return bool
     */
    public static function can_send_messages(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_MANAGE_CLIENTS);
    }

    /**
     * Verifica se o usuário pode editar clientes
     * 
     * @return bool
     */
    public static function can_edit_clients(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_EDIT_CLIENTS);
    }

    /**
     * Gera um nonce
     * 
     * @return string
     */
    public static function create_nonce(): string
    {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    /**
     * Verifica um nonce
     * 
     * @param string $nonce Nonce a verificar
     * @return bool
     */
    public static function verify_nonce(string $nonce): bool
    {
        return wp_verify_nonce($nonce, self::NONCE_ACTION) !== false;
    }

    /**
     * Sanitiza e valida um número de telefone E.164
     * 
     * @param string $phone Número a sanitizar
     * @return string
     */
    public static function sanitize_phone(string $phone): string
    {
        // Remover espaços
        $phone = trim($phone);

        // Se começa com +, manter
        $has_plus = (substr($phone, 0, 1) === '+');

        // Remover tudo que não é dígito
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Re-adicionar o +
        if ($has_plus && !empty($phone)) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Valida um número de telefone E.164
     * 
     * @param string $phone Número a validar
     * @return bool
     */
    public static function validate_phone(string $phone): bool
    {
        // Deve começar com + e ter entre 8 e 15 dígitos
        if (empty($phone)) {
            return false;
        }

        // Regex para E.164
        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }

    /**
     * Formata número para envio via Evolution API (sem o +)
     * 
     * @param string $phone Número E.164
     * @return string
     */
    public static function format_phone_for_api(string $phone): string
    {
        // Remover o + para a API
        return ltrim($phone, '+');
    }

    /**
     * Sanitiza texto de mensagem
     * 
     * @param string $message Mensagem a sanitizar
     * @return string
     */
    public static function sanitize_message(string $message): string
    {
        // Remover tags HTML mas manter quebras de linha
        $message = wp_kses($message, []);

        // Sanitizar texto mantendo quebras de linha
        $message = sanitize_textarea_field($message);

        return $message;
    }

    /**
     * Criptografa um valor (para tokens)
     * 
     * @param string $value Valor a criptografar
     * @return string
     */
    public static function encrypt(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Usar LOGGED_IN_KEY como base se disponível
        $key = defined('LOGGED_IN_KEY') && LOGGED_IN_KEY ? LOGGED_IN_KEY : 'wec_default_key';
        $key = substr(hash('sha256', $key), 0, 32);

        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);

        if ($encrypted === false) {
            return $value; // Fallback: retornar valor original
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa um valor
     * 
     * @param string $encrypted Valor criptografado
     * @return string
     */
    public static function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }

        $key = defined('LOGGED_IN_KEY') && LOGGED_IN_KEY ? LOGGED_IN_KEY : 'wec_default_key';
        $key = substr(hash('sha256', $key), 0, 32);

        $data = base64_decode($encrypted);
        if ($data === false || strlen($data) < 16) {
            return $encrypted; // Não está criptografado ou inválido
        }

        $iv = substr($data, 0, 16);
        $encrypted_data = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);

        if ($decrypted === false) {
            return $encrypted; // Fallback: retornar valor original
        }

        return $decrypted;
    }
}
