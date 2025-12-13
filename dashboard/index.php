<?php
/**
 * Dashboard Admin Independente - WhatsApp News Dispatcher
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.5.0
 * @created 2025-12-13 08:55:00
 */

// Carregar WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../../wp-load.php',
    dirname(__FILE__) . '/../../../../../wp-load.php',
    dirname(__FILE__) . '/../../../../../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('WordPress não encontrado.');
}

// Verificar se usuário está logado e tem permissão
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/wp-content/plugins/whatsapp-evolution-clients/dashboard/')));
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('Você não tem permissão para acessar esta página.', 'Acesso Negado', ['response' => 403]);
}

// Garantir que tabelas existem
WEC_Queue::create_tables();

// Dados do usuário atual
$current_user = wp_get_current_user();

// Paginação de posts
$posts_per_page = 12;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

$posts_args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => $posts_per_page,
    'paged' => $current_page,
    'orderby' => 'date',
    'order' => 'DESC',
];
$posts_query = new WP_Query($posts_args);
$total_posts = $posts_query->found_posts;
$total_pages = ceil($total_posts / $posts_per_page);

// Buscar total de leads
$leads_count = wp_count_posts(WEC_CPT::POST_TYPE);
$total_leads = $leads_count->publish ?? 0;

// Buscar interesses disponíveis
$interests = get_terms([
    'taxonomy' => 'wec_interest',
    'hide_empty' => false,
]);

// Buscar categorias de clientes
$client_categories = get_terms([
    'taxonomy' => WEC_CPT::TAXONOMY,
    'hide_empty' => false,
]);

// Nonce para AJAX (deve usar a mesma action da classe WEC_Security)
$nonce = wp_create_nonce('wec_ajax_nonce');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp News Dispatcher - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fab fa-whatsapp"></i>
                <span>WP PostZap</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-page="monitor">
                <i class="fas fa-desktop"></i>
                <span>Monitor</span>
                <span class="badge badge-live" id="activeBatchesBadge" style="display:none;">●</span>
            </a>
            <a href="#" class="nav-item" data-page="posts">
                <i class="fas fa-newspaper"></i>
                <span>Notícias</span>
            </a>
            <a href="#" class="nav-item" data-page="leads">
                <i class="fas fa-users"></i>
                <span>Contatos</span>
                <span class="badge"><?php echo absint($total_leads); ?></span>
            </a>
            <a href="#" class="nav-item" data-page="history">
                <i class="fas fa-history"></i>
                <span>Histórico</span>
            </a>
            <a href="#" class="nav-item" data-page="settings">
                <i class="fas fa-cog"></i>
                <span>Configurações</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="<?php echo get_avatar_url($current_user->ID, ['size' => 40]); ?>" alt="Avatar" class="user-avatar">
                <div class="user-details">
                    <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
                    <span class="user-role">Administrador</span>
                </div>
            </div>
            <a href="<?php echo admin_url(); ?>" class="back-to-wp" title="Voltar ao WordPress">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Monitor de Disparos</h1>
            </div>
            <div class="header-right">
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn-logout" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            
            <!-- PAGE: MONITOR -->
            <div class="page-content active" data-page="monitor">
                
                <!-- Stats Resumo -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="monitorSentToday">0</span>
                            <span class="stat-label">Enviados Hoje</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="monitorProcessing">0</span>
                            <span class="stat-label">Em Andamento</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-red">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="monitorFailed">0</span>
                            <span class="stat-label">Falhas Hoje</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-value" id="monitorPending">0</span>
                            <span class="stat-label">Pendentes</span>
                        </div>
                    </div>
                </div>
                
                <!-- Disparos Ativos -->
                <div class="monitor-section">
                    <div class="section-header">
                        <h2><i class="fas fa-broadcast-tower"></i> Disparos em Andamento</h2>
                        <span class="live-indicator" id="liveIndicator">
                            <span class="live-dot"></span> Atualização automática
                        </span>
                    </div>
                    
                    <div class="active-dispatches" id="activeDispatches">
                        <!-- Carregado via JavaScript -->
                        <div class="no-active-dispatch">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhum disparo em andamento</p>
                            <a href="#" class="btn-new-dispatch" onclick="Dashboard.navigateTo('posts'); return false;">
                                Iniciar Novo Disparo
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Log em Tempo Real -->
                <div class="monitor-section">
                    <div class="section-header">
                        <h2><i class="fas fa-terminal"></i> Log em Tempo Real</h2>
                        <button class="btn-clear-log" id="btnClearLog">
                            <i class="fas fa-trash"></i> Limpar
                        </button>
                    </div>
                    
                    <div class="realtime-log" id="realtimeLog">
                        <div class="log-entry info">
                            <span class="log-time"><?php echo date('H:i:s'); ?></span>
                            <span class="log-message">Monitor iniciado. Aguardando disparos...</span>
                        </div>
                    </div>
                </div>
                
            </div><!-- END PAGE: MONITOR -->
            
            <!-- PAGE: POSTS -->
            <div class="page-content" data-page="posts">
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo absint($posts_query->found_posts); ?></span>
                        <span class="stat-label">Notícias</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo absint($total_leads); ?></span>
                        <span class="stat-label">Contatos</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo count($interests); ?></span>
                        <span class="stat-label">Interesses</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" id="totalDispatched">0</span>
                        <span class="stat-label">Disparados Hoje</span>
                    </div>
                </div>
            </div>

            <!-- Posts List -->
            <div class="posts-section">
                <div class="section-header">
                    <h2>Notícias Recentes</h2>
                    <div class="section-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchPosts" placeholder="Buscar notícias...">
                        </div>
                        <select id="filterCategory" class="filter-select">
                            <option value="">Todas as categorias</option>
                            <?php
                            $categories = get_categories(['hide_empty' => false]);
                            foreach ($categories as $cat) {
                                echo '<option value="' . $cat->term_id . '">' . esc_html($cat->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="posts-grid" id="postsGrid">
                    <?php if ($posts_query->have_posts()): ?>
                        <?php while ($posts_query->have_posts()): $posts_query->the_post(); ?>
                            <?php $post_cats = get_the_category(); $cat_ids = array_map(function($c) { return $c->term_id; }, $post_cats); ?>
                            <div class="post-card" data-post-id="<?php the_ID(); ?>" data-categories="<?php echo esc_attr(implode(',', $cat_ids)); ?>">
                                <div class="post-image">
                                    <?php if (has_post_thumbnail()): ?>
                                        <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'medium'); ?>" alt="<?php the_title_attribute(); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="post-overlay">
                                        <button class="btn-dispatch" data-post-id="<?php the_ID(); ?>" data-post-title="<?php the_title_attribute(); ?>">
                                            <i class="fab fa-whatsapp"></i>
                                            Disparar
                                        </button>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <div class="post-meta">
                                        <span class="post-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo get_the_date('d/m/Y'); ?>
                                        </span>
                                        <span class="post-category">
                                            <?php 
                                            $cats = get_the_category();
                                            echo $cats ? esc_html($cats[0]->name) : 'Sem categoria';
                                            ?>
                                        </span>
                                    </div>
                                    <h3 class="post-title"><?php the_title(); ?></h3>
                                    <p class="post-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 15); ?></p>
                                    <div class="post-actions">
                                        <a href="<?php the_permalink(); ?>" target="_blank" class="btn-view">
                                            <i class="fas fa-external-link-alt"></i>
                                            Ver
                                        </a>
                                        <button class="btn-dispatch-small" data-post-id="<?php the_ID(); ?>" data-post-title="<?php the_title_attribute(); ?>">
                                            <i class="fab fa-whatsapp"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php else: ?>
                        <div class="no-posts">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhuma notícia encontrada.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <!-- Paginação -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Mostrando <?php echo (($current_page - 1) * $posts_per_page) + 1; ?>-<?php echo min($current_page * $posts_per_page, $total_posts); ?> de <?php echo $total_posts; ?> notícias
                    </div>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=wec-dashboard&tab=posts&paged=1" class="page-btn" title="Primeira">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=wec-dashboard&tab=posts&paged=<?php echo $current_page - 1; ?>" class="page-btn" title="Anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=wec-dashboard&tab=posts&paged=<?php echo $i; ?>" class="page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=wec-dashboard&tab=posts&paged=<?php echo $current_page + 1; ?>" class="page-btn" title="Próxima">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=wec-dashboard&tab=posts&paged=<?php echo $total_pages; ?>" class="page-btn" title="Última">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
            </div><!-- END PAGE: POSTS -->
            
            <!-- PAGE: LEADS/CONTATOS -->
            <div class="page-content" data-page="leads">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Contatos</h2>
                    <div class="section-actions">
                        <input type="text" id="searchLeads" class="search-input-inline" placeholder="Buscar contato...">
                    </div>
                </div>
                
                <!-- Filtros de Interesse (Hierárquico) -->
                <?php
                // Organizar interesses por hierarquia
                $parent_interests = [];
                $child_interests = [];
                $has_hierarchy = false;
                
                foreach ($interests as $interest) {
                    if ($interest->parent == 0) {
                        $parent_interests[] = $interest;
                    } else {
                        $has_hierarchy = true;
                        if (!isset($child_interests[$interest->parent])) {
                            $child_interests[$interest->parent] = [];
                        }
                        $child_interests[$interest->parent][] = $interest;
                    }
                }
                
                // Contar leads por interesse
                function wec_count_leads_by_interest($term_id) {
                    return count(get_posts([
                        'post_type' => WEC_CPT::POST_TYPE,
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                        'tax_query' => [['taxonomy' => 'wec_interest', 'field' => 'term_id', 'terms' => $term_id]]
                    ]));
                }
                ?>
                <div class="leads-filters">
                    <div class="filter-label"><i class="fas fa-filter"></i> Filtrar por interesse:</div>
                    <div class="filter-selects">
                        <!-- Select Principal (Categorias) -->
                        <select id="filterInterestParent" class="filter-select-interest">
                            <option value="all">Todos os interesses (<?php echo $total_leads; ?>)</option>
                            <?php foreach ($parent_interests as $parent): 
                                $count = wec_count_leads_by_interest($parent->term_id);
                                $has_children = isset($child_interests[$parent->term_id]);
                            ?>
                                <option value="<?php echo esc_attr($parent->slug); ?>" 
                                        data-term-id="<?php echo $parent->term_id; ?>"
                                        data-has-children="<?php echo $has_children ? '1' : '0'; ?>">
                                    <?php echo esc_html($parent->name); ?> (<?php echo $count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($has_hierarchy): ?>
                        <!-- Select Secundário (Subcategorias) - aparece dinamicamente -->
                        <select id="filterInterestChild" class="filter-select-interest" style="display: none;">
                            <option value="">Todas as subcategorias</option>
                        </select>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dados das subcategorias para JavaScript -->
                    <script type="application/json" id="interestChildrenData">
                    <?php echo json_encode($child_interests); ?>
                    </script>
                    
                    <!-- Contagens para JavaScript -->
                    <script type="application/json" id="interestCountsData">
                    <?php 
                    $counts = [];
                    foreach ($interests as $int) {
                        $counts[$int->term_id] = wec_count_leads_by_interest($int->term_id);
                    }
                    echo json_encode($counts);
                    ?>
                    </script>
                </div>
                
                <div class="leads-table-wrapper">
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>WhatsApp</th>
                                <th>Interesses</th>
                                <th>Cadastro</th>
                            </tr>
                        </thead>
                        <tbody id="leadsTableBody">
                            <?php
                            $leads_query = new WP_Query([
                                'post_type' => WEC_CPT::POST_TYPE,
                                'post_status' => 'publish',
                                'posts_per_page' => 100,
                                'orderby' => 'date',
                                'order' => 'DESC',
                            ]);
                            if ($leads_query->have_posts()):
                                while ($leads_query->have_posts()): $leads_query->the_post();
                                    $phone = get_post_meta(get_the_ID(), '_wec_whatsapp_e164', true);
                                    $lead_interests = wp_get_post_terms(get_the_ID(), 'wec_interest');
                                    $interest_slugs = array_map(function($t) { return $t->slug; }, $lead_interests);
                                    $interest_names = array_map(function($t) { return $t->name; }, $lead_interests);
                            ?>
                                <tr data-interests="<?php echo esc_attr(implode(',', $interest_slugs)); ?>" data-name="<?php echo esc_attr(get_the_title()); ?>">
                                    <td>
                                        <div class="lead-name">
                                            <div class="lead-avatar"><?php echo strtoupper(substr(get_the_title(), 0, 2)); ?></div>
                                            <span><?php the_title(); ?></span>
                                        </div>
                                    </td>
                                    <td><code><?php echo esc_html($phone ?: '-'); ?></code></td>
                                    <td>
                                        <?php if ($interest_names): ?>
                                            <?php foreach ($interest_names as $int_name): ?>
                                                <span class="interest-tag"><?php echo esc_html($int_name); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="no-interest">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo get_the_date('d/m/Y'); ?></td>
                                </tr>
                            <?php
                                endwhile;
                                wp_reset_postdata();
                            else:
                            ?>
                                <tr>
                                    <td colspan="4" class="no-data">Nenhum contato cadastrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- END PAGE: LEADS -->
            
            <!-- PAGE: HISTÓRICO -->
            <div class="page-content" data-page="history">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Histórico de Disparos</h2>
                </div>
                
                <div class="history-list" id="historyList">
                    <?php
                    global $wpdb;
                    $batch_table = $wpdb->prefix . 'wec_dispatch_batches';
                    $batches = $wpdb->get_results("SELECT * FROM $batch_table ORDER BY created_at DESC LIMIT 50");
                    
                    if ($batches):
                        foreach ($batches as $batch):
                            $status_class = $batch->status;
                            $status_label = [
                                'pending' => 'Pendente',
                                'processing' => 'Processando',
                                'paused' => 'Pausado',
                                'completed' => 'Concluído',
                                'cancelled' => 'Cancelado',
                            ][$batch->status] ?? $batch->status;
                    ?>
                        <div class="history-item status-<?php echo esc_attr($status_class); ?>">
                            <div class="history-icon">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="history-info">
                                <h4><?php echo esc_html($batch->post_title); ?></h4>
                                <p>
                                    <span class="history-stat"><i class="fas fa-users"></i> <?php echo absint($batch->total_leads); ?> destinatários</span>
                                    <span class="history-stat"><i class="fas fa-check"></i> <?php echo absint($batch->sent_count); ?> enviados</span>
                                    <span class="history-stat"><i class="fas fa-times"></i> <?php echo absint($batch->failed_count); ?> falhas</span>
                                </p>
                            </div>
                            <div class="history-meta">
                                <span class="history-status status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                <span class="history-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($batch->created_at))); ?></span>
                                <button class="btn-delete-history" onclick="Dashboard.deleteBatch(<?php echo absint($batch->id); ?>)" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <div class="no-history">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhum disparo realizado ainda.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!-- END PAGE: HISTORY -->
            
            <!-- PAGE: CONFIGURAÇÕES -->
            <div class="page-content" data-page="settings">
                <div class="section-header">
                    <h2><i class="fas fa-cog"></i> Configurações</h2>
                </div>
                
                <div class="settings-cards">
                    <div class="settings-card">
                        <h3><i class="fas fa-server"></i> Evolution API</h3>
                        <p>Configure as credenciais da Evolution API para envio de mensagens.</p>
                        <a href="<?php echo admin_url('admin.php?page=wec-settings'); ?>" target="_blank" class="btn-settings">
                            <i class="fas fa-external-link-alt"></i> Abrir Configurações
                        </a>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-users"></i> Gerenciar Contatos</h3>
                        <p>Adicione, edite ou remova contatos do sistema.</p>
                        <a href="<?php echo admin_url('edit.php?post_type=' . WEC_CPT::POST_TYPE); ?>" target="_blank" class="btn-settings">
                            <i class="fas fa-external-link-alt"></i> Abrir Contatos
                        </a>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-tags"></i> Gerenciar Interesses</h3>
                        <p>Crie e gerencie categorias de interesses para segmentação.</p>
                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=wec_interest&post_type=' . WEC_CPT::POST_TYPE); ?>" target="_blank" class="btn-settings">
                            <i class="fas fa-external-link-alt"></i> Abrir Interesses
                        </a>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fab fa-wordpress"></i> Voltar ao WordPress</h3>
                        <p>Acesse o painel administrativo completo do WordPress.</p>
                        <a href="<?php echo admin_url(); ?>" class="btn-settings">
                            <i class="fas fa-arrow-left"></i> Ir para WP Admin
                        </a>
                    </div>
                </div>
            </div><!-- END PAGE: SETTINGS -->
            
        </div>
    </main>

    <!-- Off-Canvas Dispatch Panel -->
    <div class="offcanvas-overlay" id="offcanvasOverlay"></div>
    <div class="offcanvas-panel" id="offcanvasPanel">
        <div class="offcanvas-header">
            <h2><i class="fab fa-whatsapp"></i> Disparar Notícia</h2>
            <button class="offcanvas-close" id="offcanvasClose">&times;</button>
        </div>
        
        <div class="offcanvas-tabs">
            <button class="offcanvas-tab active" data-tab="preview">
                <i class="fas fa-eye"></i> Preview
            </button>
            <button class="offcanvas-tab" data-tab="recipients">
                <i class="fas fa-users"></i> Destinatários
                <span class="tab-badge" id="recipientsBadge">0</span>
            </button>
            <button class="offcanvas-tab" data-tab="settings">
                <i class="fas fa-cog"></i> Configurações
            </button>
        </div>
        
        <div class="offcanvas-body">
            <!-- Tab Preview -->
            <div class="offcanvas-content active" data-tab="preview">
                <div class="preview-whatsapp">
                    <!-- Toggle de imagem -->
                    <div class="image-toggle-wrapper">
                        <label class="toggle-switch">
                            <input type="checkbox" id="includeImage" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="toggle-info">
                            <strong>Incluir imagem destacada</strong>
                            <small id="imageStatus">A imagem será otimizada (600px, 80%)</small>
                        </div>
                    </div>
                    
                    <!-- Preview estilo WhatsApp -->
                    <div class="wa-conversation">
                        <div class="wa-bubble sent">
                            <div class="wa-img" id="previewImage">
                                <i class="fas fa-image"></i>
                            </div>
                            <div class="wa-greeting" id="previewGreeting">Olá! Boa tarde 👋</div>
                            <div class="wa-title" id="previewTitle">📰 *Título da Notícia*</div>
                            <div class="wa-text" id="previewExcerpt">Resumo da notícia aparece aqui...</div>
                            <div class="wa-link">🔗 <span id="previewUrl">Leia mais: link-da-noticia.com</span></div>
                            <div class="wa-meta">
                                <span class="wa-time">Agora</span>
                                <span class="wa-check">✓✓</span>
                            </div>
                        </div>
                    </div>
                    
                    <a href="#" target="_blank" class="preview-link" id="previewLink">
                        <i class="fas fa-external-link-alt"></i>
                        Ver notícia original
                    </a>
                </div>
            </div>
            
            <!-- Tab Recipients -->
            <div class="offcanvas-content" data-tab="recipients">
                <!-- Send All Option -->
                <label class="send-all-option">
                    <input type="checkbox" id="sendAll">
                    <span class="send-all-text">
                        <strong>Enviar para TODOS os contatos</strong>
                        <small><?php echo $total_leads; ?> contatos com WhatsApp válido</small>
                    </span>
                </label>
                
                <!-- Selection Mode -->
                <div class="selection-mode" id="selectionMode">
                    <button class="mode-btn active" data-mode="interests">
                        <i class="fas fa-tags"></i>
                        <span>Por Interesse</span>
                        <small>Selecionar grupos</small>
                    </button>
                    <button class="mode-btn" data-mode="individual">
                        <i class="fas fa-user"></i>
                        <span>Individual</span>
                        <small>Escolher contatos</small>
                    </button>
                </div>
                
                <!-- Interests Selection -->
                <div class="interests-wrapper active" id="interestsWrapper">
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInterests" placeholder="Buscar interesse...">
                    </div>
                    <div class="selection-actions">
                        <button class="btn-sm" id="selectAllInterests">Selecionar Todos</button>
                        <button class="btn-sm" id="clearInterests">Limpar</button>
                        <span class="selection-count"><span id="interestsCount">0</span> / <?php echo count($interests); ?> selecionados</span>
                    </div>
                    <!-- Filtros em duas colunas -->
                    <div class="filters-grid">
                        <!-- Coluna: Interesses -->
                        <div class="filter-column">
                            <h5><i class="fas fa-tags"></i> Interesses</h5>
                            <div class="filter-list" id="interestsList">
                                <?php foreach ($interests as $interest): 
                                    $leads_in_interest = get_posts([
                                        'post_type' => WEC_CPT::POST_TYPE,
                                        'posts_per_page' => -1,
                                        'fields' => 'ids',
                                        'tax_query' => [['taxonomy' => 'wec_interest', 'field' => 'term_id', 'terms' => $interest->term_id]]
                                    ]);
                                ?>
                                    <label class="filter-item">
                                        <input type="checkbox" name="interests[]" value="<?php echo $interest->term_id; ?>">
                                        <span class="filter-name"><?php echo esc_html($interest->name); ?></span>
                                        <span class="filter-count"><?php echo count($leads_in_interest); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Coluna: Categorias de Clientes -->
                        <div class="filter-column">
                            <h5><i class="fas fa-folder"></i> Categorias</h5>
                            <div class="filter-list" id="categoriesList">
                                <?php foreach ($client_categories as $category): 
                                    $leads_in_category = get_posts([
                                        'post_type' => WEC_CPT::POST_TYPE,
                                        'posts_per_page' => -1,
                                        'fields' => 'ids',
                                        'tax_query' => [['taxonomy' => WEC_CPT::TAXONOMY, 'field' => 'term_id', 'terms' => $category->term_id]]
                                    ]);
                                ?>
                                    <label class="filter-item">
                                        <input type="checkbox" name="categories[]" value="<?php echo $category->term_id; ?>">
                                        <span class="filter-name"><?php echo esc_html($category->name); ?></span>
                                        <span class="filter-count"><?php echo count($leads_in_category); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Selecione interesses E/OU categorias. O sistema traz leads que correspondem a TODOS os filtros selecionados.</span>
                    </div>
                </div>
                
                <!-- Individual Selection -->
                <div class="contacts-wrapper" id="contactsWrapper">
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchContacts" placeholder="Buscar contato...">
                    </div>
                    <div class="selection-actions">
                        <button class="btn-sm" id="selectAllContacts">Selecionar Todos</button>
                        <button class="btn-sm" id="clearContacts">Limpar</button>
                        <span class="selection-count"><span id="contactsCount">0</span> selecionados</span>
                    </div>
                    <div class="contacts-list" id="contactsList">
                        <!-- Loaded via AJAX -->
                    </div>
                </div>
                
                <!-- Recipients Summary -->
                <div class="recipients-summary">
                    <div class="summary-number">
                        <span class="number" id="selectedCount">0</span>
                        <span class="label">Destinatários</span>
                    </div>
                    <div class="summary-list" id="recipientsList">
                        <p>Selecione destinatários acima.</p>
                    </div>
                </div>
            </div>
            
            <!-- Tab Settings -->
            <div class="offcanvas-content" data-tab="settings">
                <!-- Intervalo -->
                <div class="settings-section">
                    <h4><i class="fas fa-clock"></i> Intervalo entre mensagens</h4>
                    <p class="settings-desc">Define o tempo de espera entre cada envio para evitar bloqueios.</p>
                    <div class="delay-inputs">
                        <div class="delay-field">
                            <label>Mínimo</label>
                            <input type="number" id="delayMin" value="4" min="1" max="60">
                            <span>segundos</span>
                        </div>
                        <div class="delay-field">
                            <label>Máximo</label>
                            <input type="number" id="delayMax" value="20" min="1" max="120">
                            <span>segundos</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="offcanvas-footer">
            <button class="btn-cancel" id="btnCancel">Cancelar</button>
            <button class="btn-dispatch-main" id="btnStartDispatch">
                <i class="fab fa-whatsapp"></i>
                Iniciar Disparo
            </button>
        </div>
        
        <!-- Progress Panel -->
        <div class="progress-panel" id="progressPanel">
            <div class="progress-header">
                <h3><i class="fas fa-paper-plane"></i> Enviando...</h3>
                <div class="progress-stats">
                    <span class="stat"><span id="sentCount">0</span> enviados</span>
                    <span class="stat"><span id="failedCount">0</span> falhas</span>
                    <span class="stat"><span id="remainingCount">0</span> restantes</span>
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="progress-log" id="progressLog">
                <!-- Log entries -->
            </div>
            <div class="progress-actions">
                <button class="btn-pause" id="btnPause">
                    <i class="fas fa-pause"></i> Pausar
                </button>
                <button class="btn-cancel-dispatch" id="btnCancelDispatch">
                    <i class="fas fa-stop"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const WEC_DASHBOARD = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo $nonce; ?>',
            pluginUrl: '<?php echo WEC_PLUGIN_URL; ?>',
            i18n: {
                confirmDispatch: 'Deseja enviar para %d contatos?',
                noRecipients: 'Selecione ao menos um destinatário.',
                dispatchComplete: 'Disparo concluído!',
                dispatchCancelled: 'Disparo cancelado.',
            }
        };
    </script>
    <script src="assets/dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>
