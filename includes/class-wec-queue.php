<?php
/**
 * Classe de Gerenciamento de Fila de Disparos
 *
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.2.0
 * @created 2025-12-12 21:30:00
 */

if (!defined('ABSPATH')) {
    exit;
}

class WEC_Queue
{
    private static $instance = null;
    
    const TABLE_NAME = 'wec_dispatch_queue';
    const BATCH_TABLE = 'wec_dispatch_batch';

    public static function instance(): WEC_Queue
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // AJAX handlers
        add_action('wp_ajax_wec_get_leads_by_interest', [$this, 'ajax_get_leads_by_interest']);
        add_action('wp_ajax_wec_get_all_contacts', [$this, 'ajax_get_all_contacts']);
        add_action('wp_ajax_wec_create_news_dispatch', [$this, 'ajax_create_news_dispatch']);
        add_action('wp_ajax_wec_process_queue_item', [$this, 'ajax_process_queue_item']);
        add_action('wp_ajax_wec_get_batch_status', [$this, 'ajax_get_batch_status']);
        add_action('wp_ajax_wec_pause_batch', [$this, 'ajax_pause_batch']);
        add_action('wp_ajax_wec_cancel_batch', [$this, 'ajax_cancel_batch']);
    }

    /**
     * Cria as tabelas no banco
     */
    public static function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Tabela de batches (campanhas de disparo)
        $batch_table = $wpdb->prefix . self::BATCH_TABLE;
        $sql_batch = "CREATE TABLE IF NOT EXISTS $batch_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            post_title varchar(255) NOT NULL,
            post_excerpt text,
            post_url varchar(500) NOT NULL,
            post_image varchar(500),
            total_leads int(11) NOT NULL DEFAULT 0,
            sent_count int(11) NOT NULL DEFAULT 0,
            failed_count int(11) NOT NULL DEFAULT 0,
            status enum('pending','processing','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
            delay_min int(11) NOT NULL DEFAULT 4,
            delay_max int(11) NOT NULL DEFAULT 20,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";

        // Tabela de itens da fila
        $queue_table = $wpdb->prefix . self::TABLE_NAME;
        $sql_queue = "CREATE TABLE IF NOT EXISTS $queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            lead_id bigint(20) unsigned NOT NULL,
            lead_name varchar(255) NOT NULL,
            lead_phone varchar(50) NOT NULL,
            status enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
            error_message text,
            scheduled_at datetime,
            sent_at datetime,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY lead_id (lead_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_batch);
        dbDelta($sql_queue);
    }

    /**
     * Remove as tabelas
     */
    public static function drop_tables(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_NAME);
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::BATCH_TABLE);
    }

    /**
     * AJAX: Busca leads por interesse
     */
    public function ajax_get_leads_by_interest(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão']);
        }

        $interests = isset($_POST['interests']) ? array_map('sanitize_text_field', $_POST['interests']) : [];
        $send_all = isset($_POST['send_all']) && $_POST['send_all'] === 'true';

        $leads = $this->get_leads_by_interests($interests, $send_all);

        wp_send_json_success([
            'leads' => $leads,
            'total' => count($leads),
        ]);
    }

    /**
     * AJAX: Busca todos os contatos para seleção individual
     */
    public function ajax_get_all_contacts(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão']);
        }

        $args = [
            'post_type' => WEC_CPT::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_wec_whatsapp_e164',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];

        $query = new WP_Query($args);
        $contacts = [];

        foreach ($query->posts as $post) {
            $phone = get_post_meta($post->ID, '_wec_whatsapp_e164', true);
            if (!empty($phone)) {
                $contacts[] = [
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'phone' => $phone,
                    'initials' => strtoupper(substr($post->post_title, 0, 2)),
                ];
            }
        }

        wp_send_json_success([
            'contacts' => $contacts,
            'total' => count($contacts),
        ]);
    }

    /**
     * Busca leads por IDs específicos (seleção individual)
     */
    public function get_leads_by_ids(array $lead_ids): array
    {
        if (empty($lead_ids)) {
            return [];
        }

        $args = [
            'post_type' => WEC_CPT::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $lead_ids,
            'orderby' => 'post__in',
            'meta_query' => [
                [
                    'key' => '_wec_whatsapp_e164',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];

        $query = new WP_Query($args);
        $leads = [];

        foreach ($query->posts as $post) {
            $phone = get_post_meta($post->ID, '_wec_whatsapp_e164', true);
            if (!empty($phone)) {
                $leads[] = [
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'phone' => $phone,
                ];
            }
        }

        return $leads;
    }

    /**
     * Busca leads por interesses
     */
    public function get_leads_by_interests(array $interests = [], bool $send_all = false): array
    {
        $args = [
            'post_type' => WEC_CPT::POST_TYPE,
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

        // Filtrar por interesses se não for "enviar para todos"
        if (!$send_all && !empty($interests)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => WEC_CPT::TAXONOMY_INTEREST,
                    'field' => 'slug',
                    'terms' => $interests,
                    'operator' => 'IN'
                ]
            ];
        }

        $query = new WP_Query($args);
        $leads = [];

        foreach ($query->posts as $post) {
            $phone = get_post_meta($post->ID, '_wec_whatsapp_e164', true);
            if (!empty($phone)) {
                $interest_terms = wp_get_post_terms($post->ID, WEC_CPT::TAXONOMY_INTEREST, ['fields' => 'names']);
                $leads[] = [
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'phone' => $phone,
                    'interests' => $interest_terms,
                ];
            }
        }

        return $leads;
    }

    /**
     * AJAX: Cria disparo de notícia
     */
    public function ajax_create_news_dispatch(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $interests = isset($_POST['interests']) ? array_map('sanitize_text_field', $_POST['interests']) : [];
        $send_all = isset($_POST['send_all']) && $_POST['send_all'] === 'true';
        $selection_mode = sanitize_text_field($_POST['selection_mode'] ?? 'interests');
        $lead_ids = isset($_POST['lead_ids']) ? array_map('intval', (array)$_POST['lead_ids']) : [];
        $delay_min = intval($_POST['delay_min'] ?? 4);
        $delay_max = intval($_POST['delay_max'] ?? 20);

        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID inválido']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }

        // Buscar leads baseado no modo de seleção
        if ($selection_mode === 'individual' && !empty($lead_ids)) {
            $leads = $this->get_leads_by_ids($lead_ids);
        } else {
            $leads = $this->get_leads_by_interests($interests, $send_all);
        }
        
        if (empty($leads)) {
            wp_send_json_error(['message' => 'Nenhum lead encontrado']);
        }

        // Criar batch
        $batch_data = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_excerpt' => wp_trim_words(get_the_excerpt($post), 30, '...'),
            'post_url' => get_permalink($post_id),
            'post_image' => get_the_post_thumbnail_url($post_id, 'large'),
            'total_leads' => count($leads),
            'delay_min' => $delay_min,
            'delay_max' => $delay_max,
        ];
        
        $batch_id = $this->create_batch($batch_data);

        if (!$batch_id) {
            global $wpdb;
            wp_send_json_error([
                'message' => 'Erro ao criar batch',
                'debug' => $wpdb->last_error,
            ]);
        }

        // Adicionar leads na fila
        foreach ($leads as $lead) {
            $this->add_to_queue($batch_id, $lead);
        }

        // Atualizar status para processing
        $this->update_batch_status($batch_id, 'processing');

        wp_send_json_success([
            'batch_id' => $batch_id,
            'total' => count($leads),
            'message' => 'Disparo iniciado com sucesso!',
        ]);
    }

    /**
     * Cria um batch de disparo
     */
    private function create_batch(array $data): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::BATCH_TABLE;

        // Verificar se tabela existe, se não criar
        $this->ensure_tables_exist();

        $result = $wpdb->insert($table, [
            'post_id' => $data['post_id'],
            'post_title' => $data['post_title'],
            'post_excerpt' => $data['post_excerpt'],
            'post_url' => $data['post_url'],
            'post_image' => $data['post_image'] ?: null,
            'total_leads' => $data['total_leads'],
            'delay_min' => $data['delay_min'],
            'delay_max' => $data['delay_max'],
            'status' => 'pending',
            'started_at' => current_time('mysql'),
        ]);

        // Log de debug
        if (!$result) {
            error_log('[WEC Queue] Erro ao criar batch: ' . $wpdb->last_error);
            error_log('[WEC Queue] Query: ' . $wpdb->last_query);
        }

        return $result ? $wpdb->insert_id : null;
    }

    /**
     * Garante que as tabelas existem
     */
    private function ensure_tables_exist(): void
    {
        global $wpdb;
        $batch_table = $wpdb->prefix . self::BATCH_TABLE;
        
        // Verificar se tabela existe
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$batch_table'");
        
        if (!$exists) {
            self::create_tables();
            error_log('[WEC Queue] Tabelas criadas automaticamente');
        }
    }

    /**
     * Adiciona lead na fila
     */
    private function add_to_queue(int $batch_id, array $lead): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        return (bool) $wpdb->insert($table, [
            'batch_id' => $batch_id,
            'lead_id' => $lead['id'],
            'lead_name' => $lead['name'],
            'lead_phone' => $lead['phone'],
            'status' => 'pending',
        ]);
    }

    /**
     * Atualiza status do batch
     */
    public function update_batch_status(int $batch_id, string $status): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::BATCH_TABLE;

        $data = ['status' => $status];
        if ($status === 'completed' || $status === 'cancelled') {
            $data['completed_at'] = current_time('mysql');
        }

        return (bool) $wpdb->update($table, $data, ['id' => $batch_id]);
    }

    /**
     * AJAX: Processa item da fila
     */
    public function ajax_process_queue_item(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        $batch_id = intval($_POST['batch_id'] ?? 0);
        if (!$batch_id) {
            wp_send_json_error(['message' => 'Batch ID inválido']);
        }

        // Verificar se batch está ativo
        $batch = $this->get_batch($batch_id);
        if (!$batch || !in_array($batch->status, ['processing', 'pending'])) {
            wp_send_json_error(['message' => 'Batch não está ativo', 'status' => $batch->status ?? 'not_found']);
        }

        // Pegar próximo item pendente
        $item = $this->get_next_pending_item($batch_id);
        if (!$item) {
            // Batch completo
            $this->update_batch_status($batch_id, 'completed');
            wp_send_json_success([
                'completed' => true,
                'message' => 'Todos os disparos foram concluídos!',
            ]);
        }

        // Marcar como processing
        $this->update_item_status($item->id, 'processing');

        // Enviar mensagem
        $result = $this->send_news_message($batch, $item);

        if ($result['success']) {
            $this->update_item_status($item->id, 'sent');
            $this->increment_batch_counter($batch_id, 'sent_count');
        } else {
            $this->update_item_status($item->id, 'failed', $result['error']);
            $this->increment_batch_counter($batch_id, 'failed_count');
        }

        // Calcular delay para próxima mensagem
        $delay = rand($batch->delay_min, $batch->delay_max);

        // Verificar progresso
        $progress = $this->get_batch_progress($batch_id);

        wp_send_json_success([
            'completed' => false,
            'item' => [
                'id' => $item->id,
                'lead_name' => $item->lead_name,
                'lead_phone' => $item->lead_phone,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error' => $result['error'] ?? null,
            ],
            'delay' => $delay,
            'progress' => $progress,
        ]);
    }

    /**
     * Envia mensagem de notícia (imagem com texto como legenda)
     */
    private function send_news_message($batch, $item): array
    {
        $api = WEC_API::instance();

        // Montar mensagem de texto
        $message = "📰 *{$batch->post_title}*\n\n";
        if ($batch->post_excerpt) {
            $message .= "{$batch->post_excerpt}\n\n";
        }
        $message .= "🔗 Leia mais: {$batch->post_url}";

        // Log debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC News] Enviando para: ' . $item->lead_phone);
            error_log('[WEC News] Imagem: ' . ($batch->post_image ?: 'nenhuma'));
        }

        // Se tem imagem, otimiza e envia como base64
        if ($batch->post_image) {
            $optimized = $this->optimize_image_for_whatsapp($batch->post_image, $batch->post_id);
            
            if ($optimized && isset($optimized['base64'])) {
                $result = $api->send_image_base64($item->lead_phone, $optimized['base64'], $optimized['mimetype'], $message);
                
                if (!$result['success']) {
                    error_log('[WEC News] Erro base64, tentando URL: ' . $result['error']);
                    $result = $api->send_image_with_caption($item->lead_phone, $batch->post_image, $message);
                }
            } else {
                $result = $api->send_image_with_caption($item->lead_phone, $batch->post_image, $message);
            }
            
            // Se falhou, tenta só texto
            if (!$result['success']) {
                error_log('[WEC News] Erro imagem, tentando só texto: ' . $result['error']);
                $result = $api->send_message($item->lead_phone, $message);
            }
        } else {
            // Sem imagem, envia só texto
            $result = $api->send_message($item->lead_phone, $message);
        }

        return $result;
    }

    /**
     * Otimiza imagem para envio no WhatsApp (max 500KB, JPEG)
     */
    private function optimize_image_for_whatsapp(string $image_url, int $post_id = 0): ?array
    {
        // Tentar pegar o caminho local da imagem primeiro
        $upload_dir = wp_upload_dir();
        $local_path = null;
        
        // Se é uma URL do próprio site, converter para caminho local
        if (strpos($image_url, $upload_dir['baseurl']) !== false) {
            $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        }
        
        // Se não encontrou caminho local, baixar a imagem
        if (!$local_path || !file_exists($local_path)) {
            $temp_file = download_url($image_url, 30);
            if (is_wp_error($temp_file)) {
                error_log('[WEC Image] Erro ao baixar: ' . $temp_file->get_error_message());
                return null;
            }
            $local_path = $temp_file;
            $is_temp = true;
        } else {
            $is_temp = false;
        }

        // Verificar tamanho original
        $original_size = filesize($local_path);
        $max_size = 500 * 1024; // 500KB

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC Image] Tamanho original: ' . round($original_size / 1024) . 'KB');
        }

        // Se já é pequena o suficiente e é JPEG, usar direto
        $mime = mime_content_type($local_path);
        if ($original_size <= $max_size && in_array($mime, ['image/jpeg', 'image/jpg'])) {
            $base64 = base64_encode(file_get_contents($local_path));
            if ($is_temp) @unlink($local_path);
            return [
                'base64' => $base64,
                'mimetype' => 'image/jpeg',
                'size' => $original_size,
            ];
        }

        // Precisa otimizar - usar WP Image Editor
        $editor = wp_get_image_editor($local_path);
        if (is_wp_error($editor)) {
            error_log('[WEC Image] Erro editor: ' . $editor->get_error_message());
            if ($is_temp) @unlink($local_path);
            return null;
        }

        // Redimensionar se muito grande (max 1200px de largura)
        $size = $editor->get_size();
        if ($size['width'] > 1200) {
            $editor->resize(1200, null, false);
        }

        // Salvar como JPEG com qualidade progressivamente menor até caber
        $temp_output = $upload_dir['basedir'] . '/wec-temp-' . uniqid() . '.jpg';
        $quality = 85;
        
        do {
            $editor->set_quality($quality);
            $saved = $editor->save($temp_output, 'image/jpeg');
            
            if (is_wp_error($saved)) {
                error_log('[WEC Image] Erro ao salvar: ' . $saved->get_error_message());
                if ($is_temp) @unlink($local_path);
                return null;
            }
            
            $new_size = filesize($saved['path']);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WEC Image] Quality ' . $quality . ': ' . round($new_size / 1024) . 'KB');
            }
            
            if ($new_size <= $max_size) {
                break;
            }
            
            $quality -= 10;
            @unlink($saved['path']);
            
        } while ($quality >= 30);

        // Converter para base64
        $base64 = base64_encode(file_get_contents($saved['path']));
        $final_size = filesize($saved['path']);
        
        // Limpar arquivos temporários
        @unlink($saved['path']);
        if ($is_temp) @unlink($local_path);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC Image] Final: ' . round($final_size / 1024) . 'KB (quality: ' . $quality . ')');
        }

        return [
            'base64' => $base64,
            'mimetype' => 'image/jpeg',
            'size' => $final_size,
            'quality' => $quality,
        ];
    }

    /**
     * Busca batch por ID
     */
    public function get_batch(int $batch_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::BATCH_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $batch_id));
    }

    /**
     * Próximo item pendente
     */
    private function get_next_pending_item(int $batch_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE batch_id = %d AND status = 'pending' ORDER BY id ASC LIMIT 1",
            $batch_id
        ));
    }

    /**
     * Atualiza status do item
     */
    private function update_item_status(int $item_id, string $status, ?string $error = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $data = ['status' => $status];
        if ($status === 'sent') {
            $data['sent_at'] = current_time('mysql');
        }
        if ($error) {
            $data['error_message'] = $error;
        }

        return (bool) $wpdb->update($table, $data, ['id' => $item_id]);
    }

    /**
     * Incrementa contador do batch
     */
    private function increment_batch_counter(int $batch_id, string $field): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::BATCH_TABLE;
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET $field = $field + 1 WHERE id = %d",
            $batch_id
        ));
    }

    /**
     * Progresso do batch
     */
    public function get_batch_progress(int $batch_id): array
    {
        $batch = $this->get_batch($batch_id);
        if (!$batch) {
            return [];
        }

        $processed = $batch->sent_count + $batch->failed_count;
        $percentage = $batch->total_leads > 0 ? round(($processed / $batch->total_leads) * 100) : 0;

        return [
            'total' => $batch->total_leads,
            'sent' => $batch->sent_count,
            'failed' => $batch->failed_count,
            'pending' => $batch->total_leads - $processed,
            'percentage' => $percentage,
            'status' => $batch->status,
        ];
    }

    /**
     * AJAX: Status do batch
     */
    public function ajax_get_batch_status(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        $batch_id = intval($_POST['batch_id'] ?? 0);
        $progress = $this->get_batch_progress($batch_id);

        if (empty($progress)) {
            wp_send_json_error(['message' => 'Batch não encontrado']);
        }

        wp_send_json_success($progress);
    }

    /**
     * AJAX: Pausar batch
     */
    public function ajax_pause_batch(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        $batch_id = intval($_POST['batch_id'] ?? 0);
        $this->update_batch_status($batch_id, 'paused');

        wp_send_json_success(['message' => 'Disparo pausado']);
    }

    /**
     * AJAX: Cancelar batch
     */
    public function ajax_cancel_batch(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        $batch_id = intval($_POST['batch_id'] ?? 0);
        $this->update_batch_status($batch_id, 'cancelled');

        wp_send_json_success(['message' => 'Disparo cancelado']);
    }

    /**
     * Busca todos os interesses
     */
    public static function get_all_interests(): array
    {
        $terms = get_terms([
            'taxonomy' => WEC_CPT::TAXONOMY_INTEREST,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $interests = [];
        foreach ($terms as $term) {
            // Contar leads com esse interesse
            $leads_count = new WP_Query([
                'post_type' => WEC_CPT::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => WEC_CPT::TAXONOMY_INTEREST,
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                    ]
                ],
                'meta_query' => [
                    [
                        'key' => '_wec_whatsapp_e164',
                        'value' => '',
                        'compare' => '!='
                    ]
                ]
            ]);

            $interests[] = [
                'id' => $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name,
                'leads_count' => $leads_count->found_posts,
            ];
        }

        return $interests;
    }

    /**
     * Total de leads com WhatsApp
     */
    public static function get_total_leads(): int
    {
        $query = new WP_Query([
            'post_type' => WEC_CPT::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_wec_whatsapp_e164',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ]);

        return $query->found_posts;
    }
}
