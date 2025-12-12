<?php
/**
 * Classe de Custom Post Type
 * 
 * Registra o CPT wec_client
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
 * Classe WEC_CPT
 */
class WEC_CPT
{

    /**
     * Instância única
     * 
     * @var WEC_CPT
     */
    private static $instance = null;

    /**
     * Slug do CPT
     */
    const POST_TYPE = 'wec_client';

    /**
     * Retorna a instância única
     * 
     * @return WEC_CPT
     */
    public static function instance(): WEC_CPT
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Taxonomia de categorias
     */
    const TAXONOMY = 'wec_client_category';

    /**
     * Construtor privado
     */
    private function __construct()
    {
        // Registrar CPT e Taxonomia diretamente (já estamos no hook init)
        self::register_post_type();
        self::register_taxonomy();

        // Colunas personalizadas
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'sortable_columns']);

        // Filtro de categorias no admin
        add_action('restrict_manage_posts', [$this, 'add_category_filter']);
        add_filter('parse_query', [$this, 'filter_by_category']);

        // Botão de exportar
        add_action('restrict_manage_posts', [$this, 'add_export_button']);
        add_action('wp_ajax_wec_export_leads', [$this, 'handle_export_leads']);
    }

    /**
     * Registra o Custom Post Type
     */
    public static function register_post_type(): void
    {
        $labels = [
            'name' => __('Leads', 'whatsapp-evolution-clients'),
            'singular_name' => __('Lead', 'whatsapp-evolution-clients'),
            'menu_name' => __('Zap Leads', 'whatsapp-evolution-clients'),
            'name_admin_bar' => __('Lead', 'whatsapp-evolution-clients'),
            'add_new' => __('Adicionar Novo', 'whatsapp-evolution-clients'),
            'add_new_item' => __('Adicionar Novo Lead', 'whatsapp-evolution-clients'),
            'new_item' => __('Novo Lead', 'whatsapp-evolution-clients'),
            'edit_item' => __('Editar Lead', 'whatsapp-evolution-clients'),
            'view_item' => __('Ver Lead', 'whatsapp-evolution-clients'),
            'all_items' => __('Todos os Leads', 'whatsapp-evolution-clients'),
            'search_items' => __('Buscar Leads', 'whatsapp-evolution-clients'),
            'parent_item_colon' => __('Lead Pai:', 'whatsapp-evolution-clients'),
            'not_found' => __('Nenhum lead encontrado.', 'whatsapp-evolution-clients'),
            'not_found_in_trash' => __('Nenhum lead encontrado na lixeira.', 'whatsapp-evolution-clients'),
            'featured_image' => __('Foto do Lead', 'whatsapp-evolution-clients'),
            'set_featured_image' => __('Definir foto do lead', 'whatsapp-evolution-clients'),
            'remove_featured_image' => __('Remover foto do lead', 'whatsapp-evolution-clients'),
            'use_featured_image' => __('Usar como foto do lead', 'whatsapp-evolution-clients'),
            'archives' => __('Arquivo de Leads', 'whatsapp-evolution-clients'),
            'insert_into_item' => __('Inserir no lead', 'whatsapp-evolution-clients'),
            'uploaded_to_this_item' => __('Enviado para este lead', 'whatsapp-evolution-clients'),
            'filter_items_list' => __('Filtrar lista de leads', 'whatsapp-evolution-clients'),
            'items_list_navigation' => __('Navegação da lista de leads', 'whatsapp-evolution-clients'),
            'items_list' => __('Lista de leads', 'whatsapp-evolution-clients'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-groups',
            'supports' => ['title', 'thumbnail'],
            'show_in_rest' => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Registra a taxonomia de categorias de clientes
     */
    public static function register_taxonomy(): void
    {
        $labels = [
            'name' => __('Categorias', 'whatsapp-evolution-clients'),
            'singular_name' => __('Categoria', 'whatsapp-evolution-clients'),
            'search_items' => __('Buscar Categorias', 'whatsapp-evolution-clients'),
            'all_items' => __('Todas as Categorias', 'whatsapp-evolution-clients'),
            'parent_item' => __('Categoria Pai', 'whatsapp-evolution-clients'),
            'parent_item_colon' => __('Categoria Pai:', 'whatsapp-evolution-clients'),
            'edit_item' => __('Editar Categoria', 'whatsapp-evolution-clients'),
            'update_item' => __('Atualizar Categoria', 'whatsapp-evolution-clients'),
            'add_new_item' => __('Adicionar Nova Categoria', 'whatsapp-evolution-clients'),
            'new_item_name' => __('Nome da Nova Categoria', 'whatsapp-evolution-clients'),
            'menu_name' => __('Categorias', 'whatsapp-evolution-clients'),
        ];

        $args = [
            'labels' => $labels,
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => false,
        ];

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, $args);
    }

    /**
     * Adiciona filtro de categorias na listagem
     */
    public function add_category_filter(): void
    {
        global $typenow;

        if ($typenow !== self::POST_TYPE) {
            return;
        }

        $taxonomy = get_taxonomy(self::TAXONOMY);
        $selected = isset($_GET[self::TAXONOMY]) ? sanitize_text_field($_GET[self::TAXONOMY]) : '';

        wp_dropdown_categories([
            'show_option_all' => sprintf(__('Todas as %s', 'whatsapp-evolution-clients'), $taxonomy->labels->name),
            'taxonomy' => self::TAXONOMY,
            'name' => self::TAXONOMY,
            'orderby' => 'name',
            'selected' => $selected,
            'show_count' => true,
            'hide_empty' => false,
            'value_field' => 'slug',
        ]);
    }

    /**
     * Filtra a query por categoria
     * 
     * @param WP_Query $query Query atual
     */
    public function filter_by_category($query): void
    {
        global $pagenow, $typenow;

        if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== self::POST_TYPE) {
            return;
        }

        if (isset($_GET[self::TAXONOMY]) && !empty($_GET[self::TAXONOMY]) && $_GET[self::TAXONOMY] !== '0') {
            $query->query_vars['tax_query'] = [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET[self::TAXONOMY]),
                ],
            ];
        }
    }

    /**
     * Adiciona colunas personalizadas na listagem
     * 
     * @param array $columns Colunas existentes
     * @return array
     */
    public function add_custom_columns(array $columns): array
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Inserir após o título
            if ($key === 'title') {
                $new_columns['wec_email'] = __('E-mail', 'whatsapp-evolution-clients');
                $new_columns['wec_whatsapp'] = __('WhatsApp', 'whatsapp-evolution-clients');
            }
        }

        return $new_columns;
    }

    /**
     * Renderiza o conteúdo das colunas personalizadas
     * 
     * @param string $column Nome da coluna
     * @param int $post_id ID do post
     */
    public function render_custom_columns(string $column, int $post_id): void
    {
        switch ($column) {
            case 'wec_email':
                $email = get_post_meta($post_id, '_wec_email', true);
                echo esc_html($email ?: '—');
                break;

            case 'wec_whatsapp':
                $whatsapp = get_post_meta($post_id, '_wec_whatsapp_e164', true);
                if ($whatsapp) {
                    echo '<code>' . esc_html($whatsapp) . '</code>';
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;
        }
    }

    /**
     * Define colunas ordenáveis
     * 
     * @param array $columns Colunas existentes
     * @return array
     */
    public function sortable_columns(array $columns): array
    {
        $columns['wec_email'] = 'wec_email';
        $columns['wec_whatsapp'] = 'wec_whatsapp';
        return $columns;
    }

    /**
     * Obtém todos os clientes com WhatsApp válido
     * 
     * @param array $ids IDs específicos (opcional)
     * @return array
     */
    public static function get_clients_with_whatsapp(array $ids = []): array
    {
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_wec_whatsapp_e164',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];

        if (!empty($ids)) {
            $args['post__in'] = array_map('intval', $ids);
        }

        $query = new WP_Query($args);
        $clients = [];

        foreach ($query->posts as $post) {
            $whatsapp = get_post_meta($post->ID, '_wec_whatsapp_e164', true);

            if (!empty($whatsapp) && WEC_Security::validate_phone($whatsapp)) {
                $clients[] = [
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'email' => get_post_meta($post->ID, '_wec_email', true),
                    'whatsapp' => $whatsapp,
                ];
            }
        }

        return $clients;
    }

    /**
     * Obtém um cliente pelo ID
     * 
     * @param int $id ID do cliente
     * @return array|null
     */
    public static function get_client(int $id): ?array
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return [
            'id' => $post->ID,
            'name' => $post->post_title,
            'email' => get_post_meta($post->ID, '_wec_email', true),
            'whatsapp' => get_post_meta($post->ID, '_wec_whatsapp_e164', true),
            'description' => get_post_meta($post->ID, '_wec_description', true),
        ];
    }

    /**
     * Adiciona botão de exportar na listagem
     */
    public function add_export_button(string $post_type): void
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        $category = isset($_GET['wec_client_category']) ? sanitize_text_field($_GET['wec_client_category']) : '';
        ?>
        <button type="button" id="wec-export-btn" class="button" data-category="<?php echo esc_attr($category); ?>">
            <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
            Exportar CSV
        </button>
        <?php
    }

    /**
     * Handler AJAX para exportar leads
     */
    public function handle_export_leads(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão']);
        }

        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field' => 'slug',
                    'terms' => $category,
                ]
            ];
        }

        $query = new WP_Query($args);
        $leads = [];

        foreach ($query->posts as $post) {
            $categories = wp_get_post_terms($post->ID, self::TAXONOMY, ['fields' => 'names']);
            
            $leads[] = [
                'id' => $post->ID,
                'nome' => $post->post_title,
                'email' => get_post_meta($post->ID, '_wec_email', true),
                'whatsapp' => get_post_meta($post->ID, '_wec_whatsapp_e164', true),
                'descricao' => get_post_meta($post->ID, '_wec_description', true),
                'categorias' => implode(', ', $categories),
                'data_cadastro' => get_the_date('d/m/Y H:i', $post->ID),
            ];
        }

        wp_send_json_success([
            'leads' => $leads,
            'total' => count($leads),
            'category' => $category ?: 'Todas',
        ]);
    }
}
