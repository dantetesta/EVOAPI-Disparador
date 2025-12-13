<?php
/**
 * Disparo em Background - WP Cron
 * 
 * Processa disparos de notícias em segundo plano
 *
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.5.0
 * @created 2025-12-13 09:05:00
 */

if (!defined('ABSPATH')) {
    exit;
}

class WEC_Background_Dispatch
{
    const CRON_HOOK = 'wec_process_background_dispatch';
    const BATCH_SIZE = 5; // Processa 5 itens por vez

    private static $instance = null;

    public static function instance(): WEC_Background_Dispatch
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Registrar hook do cron
        add_action(self::CRON_HOOK, [$this, 'process_batch']);
        
        // AJAX para iniciar disparo em background
        add_action('wp_ajax_wec_start_background_dispatch', [$this, 'ajax_start_background_dispatch']);
        add_action('wp_ajax_wec_get_dispatch_status', [$this, 'ajax_get_dispatch_status']);
    }

    /**
     * Agenda o próximo processamento
     */
    public function schedule_next_process(int $batch_id, int $delay = 5): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK, [$batch_id])) {
            wp_schedule_single_event(time() + $delay, self::CRON_HOOK, [$batch_id]);
        }
    }

    /**
     * Processa um lote de itens em background
     */
    public function process_batch(int $batch_id): void
    {
        $queue = WEC_Queue::instance();
        $batch = $queue->get_batch($batch_id);

        if (!$batch || !in_array($batch->status, ['processing', 'pending'])) {
            error_log("[WEC Background] Batch $batch_id não está ativo ou não existe");
            return;
        }

        error_log("[WEC Background] Processando batch $batch_id");

        // Pegar próximos itens pendentes
        global $wpdb;
        $table = $wpdb->prefix . 'wec_dispatch_queue';
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE batch_id = %d AND status = 'pending' ORDER BY id ASC LIMIT %d",
            $batch_id,
            self::BATCH_SIZE
        ));

        if (empty($items)) {
            // Batch completo
            $queue->update_batch_status($batch_id, 'completed');
            error_log("[WEC Background] Batch $batch_id completo!");
            return;
        }

        $api = WEC_API::instance();
        $processed = 0;
        $delay_total = 0;

        foreach ($items as $item) {
            // Marcar como processing
            $wpdb->update($table, ['status' => 'processing'], ['id' => $item->id]);

            // Enviar mensagem
            $result = $this->send_news_message($batch, $item, $queue);

            if ($result['success']) {
                $wpdb->update($table, [
                    'status' => 'sent',
                    'sent_at' => current_time('mysql'),
                ], ['id' => $item->id]);
                
                $queue->increment_batch_counter($batch_id, 'sent_count');
                error_log("[WEC Background] Enviado para {$item->lead_name}");
            } else {
                $wpdb->update($table, [
                    'status' => 'failed',
                    'error_message' => $result['error'],
                ], ['id' => $item->id]);
                
                $queue->increment_batch_counter($batch_id, 'failed_count');
                error_log("[WEC Background] Falha para {$item->lead_name}: {$result['error']}");
            }

            $processed++;
            
            // Delay entre mensagens
            $delay = rand($batch->delay_min, $batch->delay_max);
            $delay_total += $delay;
            
            if ($processed < count($items)) {
                sleep($delay);
            }
        }

        // Verificar se ainda tem mais itens
        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE batch_id = %d AND status = 'pending'",
            $batch_id
        ));

        if ($remaining > 0) {
            // Agendar próximo lote
            $this->schedule_next_process($batch_id, 5);
            error_log("[WEC Background] Agendado próximo lote para batch $batch_id ($remaining restantes)");
        } else {
            // Batch completo
            $queue->update_batch_status($batch_id, 'completed');
            error_log("[WEC Background] Batch $batch_id completo!");
        }
    }

    /**
     * Envia mensagem de notícia
     */
    private function send_news_message($batch, $item, $queue): array
    {
        $api = WEC_API::instance();

        // Montar mensagem
        $message = "📰 *{$batch->post_title}*\n\n";
        if ($batch->post_excerpt) {
            $message .= "{$batch->post_excerpt}\n\n";
        }
        $message .= "🔗 Leia mais: {$batch->post_url}";

        // Se tem imagem, otimiza e envia
        if ($batch->post_image) {
            $optimized = $this->optimize_image($batch->post_image);
            
            if ($optimized && isset($optimized['base64'])) {
                $result = $api->send_image_base64($item->lead_phone, $optimized['base64'], $optimized['mimetype'], $message);
                
                if (!$result['success']) {
                    $result = $api->send_image_with_caption($item->lead_phone, $batch->post_image, $message);
                }
            } else {
                $result = $api->send_image_with_caption($item->lead_phone, $batch->post_image, $message);
            }
            
            if (!$result['success']) {
                $result = $api->send_message($item->lead_phone, $message);
            }
        } else {
            $result = $api->send_message($item->lead_phone, $message);
        }

        return $result;
    }

    /**
     * Otimiza imagem para WhatsApp
     */
    private function optimize_image(string $image_url): ?array
    {
        $upload_dir = wp_upload_dir();
        $local_path = null;
        
        if (strpos($image_url, $upload_dir['baseurl']) !== false) {
            $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        }
        
        if (!$local_path || !file_exists($local_path)) {
            $temp_file = download_url($image_url, 30);
            if (is_wp_error($temp_file)) {
                return null;
            }
            $local_path = $temp_file;
            $is_temp = true;
        } else {
            $is_temp = false;
        }

        $original_size = filesize($local_path);
        $max_size = 500 * 1024;

        $mime = mime_content_type($local_path);
        if ($original_size <= $max_size && in_array($mime, ['image/jpeg', 'image/jpg'])) {
            $base64 = base64_encode(file_get_contents($local_path));
            if ($is_temp) @unlink($local_path);
            return ['base64' => $base64, 'mimetype' => 'image/jpeg'];
        }

        $editor = wp_get_image_editor($local_path);
        if (is_wp_error($editor)) {
            if ($is_temp) @unlink($local_path);
            return null;
        }

        $size = $editor->get_size();
        if ($size['width'] > 1200) {
            $editor->resize(1200, null, false);
        }

        $temp_output = $upload_dir['basedir'] . '/wec-temp-' . uniqid() . '.jpg';
        $quality = 85;
        
        do {
            $editor->set_quality($quality);
            $saved = $editor->save($temp_output, 'image/jpeg');
            
            if (is_wp_error($saved)) {
                if ($is_temp) @unlink($local_path);
                return null;
            }
            
            $new_size = filesize($saved['path']);
            
            if ($new_size <= $max_size) break;
            
            $quality -= 10;
            @unlink($saved['path']);
            
        } while ($quality >= 30);

        $base64 = base64_encode(file_get_contents($saved['path']));
        
        @unlink($saved['path']);
        if ($is_temp) @unlink($local_path);

        return ['base64' => $base64, 'mimetype' => 'image/jpeg'];
    }

    /**
     * AJAX: Inicia disparo em background
     */
    public function ajax_start_background_dispatch(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $selection_mode = sanitize_text_field($_POST['selection_mode'] ?? 'interests');
        $send_all = isset($_POST['send_all']) && $_POST['send_all'] === 'true';
        $interests = isset($_POST['interests']) ? json_decode(stripslashes($_POST['interests']), true) : [];
        $lead_ids = isset($_POST['lead_ids']) ? json_decode(stripslashes($_POST['lead_ids']), true) : [];
        $delay_min = intval($_POST['delay_min'] ?? 4);
        $delay_max = intval($_POST['delay_max'] ?? 20);

        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID inválido']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }

        $queue = WEC_Queue::instance();

        // Buscar leads
        if ($selection_mode === 'individual' && !empty($lead_ids)) {
            $leads = $queue->get_leads_by_ids($lead_ids);
        } else {
            $leads = $queue->get_leads_by_interests($interests ?: [], $send_all);
        }
        
        if (empty($leads)) {
            wp_send_json_error(['message' => 'Nenhum lead encontrado']);
        }

        // Garantir que tabelas existem
        WEC_Queue::create_tables();

        // Criar batch
        global $wpdb;
        $batch_table = $wpdb->prefix . 'wec_dispatch_batches';
        
        $insert_result = $wpdb->insert($batch_table, [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_excerpt' => wp_trim_words(get_the_excerpt($post), 30, '...'),
            'post_url' => get_permalink($post_id),
            'post_image' => get_the_post_thumbnail_url($post_id, 'large') ?: '',
            'total_leads' => count($leads),
            'delay_min' => $delay_min,
            'delay_max' => $delay_max,
            'status' => 'processing',
            'created_at' => current_time('mysql'),
        ]);

        $batch_id = $wpdb->insert_id;

        if (!$batch_id || $insert_result === false) {
            error_log('[WEC Background] Erro ao criar batch: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Erro ao criar batch: ' . $wpdb->last_error]);
        }

        // Adicionar leads na fila
        $queue_table = $wpdb->prefix . 'wec_dispatch_queue';
        foreach ($leads as $lead) {
            $wpdb->insert($queue_table, [
                'batch_id' => $batch_id,
                'lead_id' => $lead['id'],
                'lead_name' => $lead['name'],
                'lead_phone' => $lead['phone'],
                'status' => 'pending',
            ]);
        }

        // Agendar processamento em background
        $this->schedule_next_process($batch_id, 2);

        wp_send_json_success([
            'batch_id' => $batch_id,
            'total' => count($leads),
            'message' => 'Disparo iniciado em background!',
        ]);
    }

    /**
     * AJAX: Busca status do disparo
     */
    public function ajax_get_dispatch_status(): void
    {
        if (!WEC_Security::verify_nonce($_POST['nonce'] ?? '')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        $batch_id = intval($_POST['batch_id'] ?? 0);
        if (!$batch_id) {
            wp_send_json_error(['message' => 'Batch ID inválido']);
        }

        global $wpdb;
        $batch_table = $wpdb->prefix . 'wec_dispatch_batches';
        $queue_table = $wpdb->prefix . 'wec_dispatch_queue';

        $batch = $wpdb->get_row($wpdb->prepare("SELECT * FROM $batch_table WHERE id = %d", $batch_id));
        
        if (!$batch) {
            wp_send_json_error(['message' => 'Batch não encontrado']);
        }

        // Contar itens
        $sent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE batch_id = %d AND status = 'sent'", $batch_id));
        $failed = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE batch_id = %d AND status = 'failed'", $batch_id));
        $pending = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE batch_id = %d AND status = 'pending'", $batch_id));
        $processing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE batch_id = %d AND status = 'processing'", $batch_id));

        // Últimos logs
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT lead_name, status, error_message, sent_at FROM $queue_table WHERE batch_id = %d AND status IN ('sent', 'failed') ORDER BY id DESC LIMIT 10",
            $batch_id
        ));

        wp_send_json_success([
            'batch_id' => $batch_id,
            'status' => $batch->status,
            'total' => $batch->total_leads,
            'sent' => intval($sent),
            'failed' => intval($failed),
            'pending' => intval($pending),
            'processing' => intval($processing),
            'progress' => $batch->total_leads > 0 ? round((($sent + $failed) / $batch->total_leads) * 100) : 0,
            'is_complete' => $batch->status === 'completed',
            'recent_logs' => $recent,
        ]);
    }
}
