<?php
/**
 * Plugin Name: WhatsApp Evolution Clients
 * Plugin URI: https://dantetesta.com.br
 * Description: Gerenciamento de clientes com envio de mensagens WhatsApp via Evolution API
 * Version: 1.2.3
 * Author: Dante Testa
 * Author URI: https://dantetesta.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: whatsapp-evolution-clients
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11 09:49:22
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Constantes do plugin
define('WEC_VERSION', '1.2.3');
define('WEC_PLUGIN_FILE', __FILE__);
define('WEC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WEC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin
 * 
 * @since 1.0.0
 */
final class WhatsApp_Evolution_Clients {

    /**
     * Instância única (Singleton)
     * 
     * @var WhatsApp_Evolution_Clients
     */
    private static $instance = null;

    /**
     * Retorna a instância única do plugin
     * 
     * @return WhatsApp_Evolution_Clients
     */
    public static function instance(): WhatsApp_Evolution_Clients {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Carrega as dependências do plugin
     */
    private function load_dependencies(): void {
        // Includes
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-security.php';
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-cpt.php';
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-meta-boxes.php';
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-settings.php';
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-api.php';
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-ajax.php';
        require_once WEC_PLUGIN_DIR . 'includes/class-wec-queue.php';

        // Admin
        require_once WEC_PLUGIN_DIR . 'admin/class-wec-admin.php';
        require_once WEC_PLUGIN_DIR . 'admin/class-wec-list-actions.php';
        require_once WEC_PLUGIN_DIR . 'admin/class-wec-news-dispatcher.php';
    }

    /**
     * Inicializa os hooks do WordPress
     */
    private function init_hooks(): void {
        // Ativação e desativação
        register_activation_hook(WEC_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WEC_PLUGIN_FILE, [$this, 'deactivate']);

        // Inicialização
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init_classes']);
    }

    /**
     * Carrega o textdomain para traduções
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'whatsapp-evolution-clients',
            false,
            dirname(WEC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Inicializa as classes do plugin
     */
    public function init_classes(): void {
        // Security e Capabilities
        WEC_Security::instance();
        
        // CPT e Meta Boxes
        WEC_CPT::instance();
        WEC_Meta_Boxes::instance();
        
        // Settings
        WEC_Settings::instance();
        
        // API
        WEC_API::instance();
        
        // AJAX
        WEC_Ajax::instance();
        
        // Queue (fila de disparos)
        WEC_Queue::instance();
        
        // Admin
        if (is_admin()) {
            WEC_Admin::instance();
            WEC_List_Actions::instance();
            WEC_News_Dispatcher::instance();
        }
    }

    /**
     * Ativação do plugin
     */
    public function activate(): void {
        // Criar capabilities
        WEC_Security::add_capabilities();
        
        // Registrar CPT para flush de rewrite rules
        WEC_CPT::register_post_type();
        
        // Criar tabelas de fila
        WEC_Queue::create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Marcar versão instalada
        update_option('wec_version', WEC_VERSION);
    }

    /**
     * Desativação do plugin
     */
    public function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Previne clonagem
     */
    private function __clone() {}

    /**
     * Previne unserialize
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Retorna a instância principal do plugin
 * 
 * @return WhatsApp_Evolution_Clients
 */
function WEC(): WhatsApp_Evolution_Clients {
    return WhatsApp_Evolution_Clients::instance();
}

// Inicializa o plugin
WEC();
