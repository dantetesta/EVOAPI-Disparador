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

// Dados do usuário atual
$current_user = wp_get_current_user();

// Buscar posts
$posts_args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 50,
    'orderby' => 'date',
    'order' => 'DESC',
];
$posts_query = new WP_Query($posts_args);

// Buscar total de leads
$leads_count = wp_count_posts(WEC_CPT::POST_TYPE);
$total_leads = $leads_count->publish ?? 0;

// Buscar interesses disponíveis
$interests = get_terms([
    'taxonomy' => 'wec_interest',
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
                <span>WEC Dispatcher</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-page="posts">
                <i class="fas fa-newspaper"></i>
                <span>Notícias</span>
            </a>
            <a href="#" class="nav-item" data-page="leads">
                <i class="fas fa-users"></i>
                <span>Contatos</span>
                <span class="badge"><?php echo $total_leads; ?></span>
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
                <h1 class="page-title">Notícias para Disparo</h1>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchPosts" placeholder="Buscar notícias...">
                </div>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn-logout" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $posts_query->found_posts; ?></span>
                        <span class="stat-label">Notícias</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $total_leads; ?></span>
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
                            <div class="post-card" data-post-id="<?php the_ID(); ?>">
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
            </div>
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
                <div class="preview-centered">
                    <div class="iphone-frame">
                        <div class="iphone-notch"></div>
                        <div class="iphone-screen">
                            <div class="wa-header">
                                <span class="wa-back"><i class="fas fa-arrow-left"></i></span>
                                <div class="wa-avatar">📰</div>
                                <div class="wa-contact">
                                    <strong>Sua Notícia</strong>
                                    <small>online</small>
                                </div>
                                <span class="wa-icons">
                                    <i class="fas fa-video"></i>
                                    <i class="fas fa-phone"></i>
                                    <i class="fas fa-ellipsis-v"></i>
                                </span>
                            </div>
                            <div class="wa-chat">
                                <div class="wa-bubble">
                                    <div class="wa-img" id="previewImage">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <div class="wa-title" id="previewTitle">Título da Notícia</div>
                                    <div class="wa-text" id="previewExcerpt">Resumo da notícia...</div>
                                    <div class="wa-link">🔗 <span id="previewUrl">link-da-noticia.com</span></div>
                                    <div class="wa-meta">
                                        <span class="wa-time">Agora</span>
                                        <span class="wa-check">✓✓</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="iphone-home"></div>
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
                    <div class="interests-list" id="interestsList">
                        <?php foreach ($interests as $interest): 
                            $leads_in_interest = get_posts([
                                'post_type' => WEC_CPT::POST_TYPE,
                                'posts_per_page' => -1,
                                'tax_query' => [['taxonomy' => 'wec_interest', 'field' => 'term_id', 'terms' => $interest->term_id]]
                            ]);
                        ?>
                            <label class="interest-item">
                                <input type="checkbox" name="interests[]" value="<?php echo $interest->term_id; ?>">
                                <span class="interest-name"><?php echo esc_html($interest->name); ?></span>
                                <span class="interest-count"><?php echo count($leads_in_interest); ?> leads</span>
                            </label>
                        <?php endforeach; ?>
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
