<?php
/**
 * Classe de Ações na Listagem
 * 
 * Adiciona bulk actions e row actions na listagem de clientes
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
 * Classe WEC_List_Actions
 */
class WEC_List_Actions
{

    /**
     * Instância única
     * 
     * @var WEC_List_Actions
     */
    private static $instance = null;

    /**
     * Retorna a instância única
     * 
     * @return WEC_List_Actions
     */
    public static function instance(): WEC_List_Actions
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
        // Bulk actions
        add_filter('bulk_actions-edit-' . WEC_CPT::POST_TYPE, [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-' . WEC_CPT::POST_TYPE, [$this, 'handle_bulk_actions'], 10, 3);

        // Row actions
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);

        // Admin notices
        add_action('admin_notices', [$this, 'show_bulk_notices']);
    }

    /**
     * Adiciona bulk actions
     * 
     * @param array $actions Ações existentes
     * @return array
     */
    public function add_bulk_actions(array $actions): array
    {
        if (WEC_Security::can_send_messages()) {
            $actions['wec_bulk_send_whatsapp'] = __('Disparo em massa via WhatsApp', 'whatsapp-evolution-clients');
        }
        return $actions;
    }

    /**
     * Processa bulk actions
     * 
     * @param string $redirect_url URL de redirecionamento
     * @param string $action Ação selecionada
     * @param array $post_ids IDs selecionados
     * @return string
     */
    public function handle_bulk_actions(string $redirect_url, string $action, array $post_ids): string
    {
        if ($action !== 'wec_bulk_send_whatsapp') {
            return $redirect_url;
        }

        if (!WEC_Security::can_send_messages()) {
            return $redirect_url;
        }

        // Armazenar IDs em transient para o JavaScript recuperar
        set_transient('wec_bulk_client_ids_' . get_current_user_id(), $post_ids, 60);

        // Adicionar parâmetro para abrir modal
        $redirect_url = add_query_arg([
            'wec_bulk_action' => 'open_modal',
            'wec_bulk_count' => count($post_ids),
        ], $redirect_url);

        return $redirect_url;
    }

    /**
     * Adiciona row actions
     * 
     * @param array $actions Ações existentes
     * @param WP_Post $post Post atual
     * @return array
     */
    public function add_row_actions(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== WEC_CPT::POST_TYPE) {
            return $actions;
        }

        if (!WEC_Security::can_send_messages()) {
            return $actions;
        }

        // Obter WhatsApp do cliente
        $whatsapp = get_post_meta($post->ID, '_wec_whatsapp_e164', true);

        // Só mostrar se tiver WhatsApp
        if (!empty($whatsapp)) {
            $actions['wec_send_whatsapp'] = sprintf(
                '<a href="#" class="wec-send-single-action" data-client-id="%d" data-client-name="%s" data-client-phone="%s">
                    <span class="dashicons dashicons-whatsapp" style="font-size: 14px; vertical-align: middle;"></span>
                    %s
                </a>',
                $post->ID,
                esc_attr($post->post_title),
                esc_attr($whatsapp),
                esc_html__('Enviar WhatsApp', 'whatsapp-evolution-clients')
            );
        }

        return $actions;
    }

    /**
     * Exibe notices após bulk actions
     */
    public function show_bulk_notices(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== WEC_CPT::POST_TYPE) {
            return;
        }

        // Verificar se deve abrir modal
        if (isset($_GET['wec_bulk_action']) && $_GET['wec_bulk_action'] === 'open_modal') {
            $count = isset($_GET['wec_bulk_count']) ? intval($_GET['wec_bulk_count']) : 0;

            // Recuperar IDs
            $client_ids = get_transient('wec_bulk_client_ids_' . get_current_user_id());

            if ($client_ids && !empty($client_ids)) {
                ?>
                <script>
                    jQuery(document).ready(function ($) {
                        // Armazenar IDs para o modal
                        window.wecBulkClientIds = <?php echo wp_json_encode(array_map('intval', $client_ids)); ?>;

                        // Abrir modal automaticamente
                        setTimeout(function () {
                            if (typeof window.wecOpenBulkModal === 'function') {
                                window.wecOpenBulkModal(window.wecBulkClientIds);
                            }
                        }, 100);
                    });
                </script>
                <?php
            }

            // Limpar transient
            delete_transient('wec_bulk_client_ids_' . get_current_user_id());
        }
    }
}
