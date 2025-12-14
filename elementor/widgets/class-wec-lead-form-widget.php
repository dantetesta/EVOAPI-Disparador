<?php
/**
 * Widget Elementor - Formulário de Lead
 * 
 * @package WhatsAppEvolutionClients
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 2.2.0
 * @created 2025-12-13 22:20:00
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se Elementor está carregado
if (!did_action('elementor/loaded')) {
    return;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;

class WEC_Lead_Form_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'wec_lead_form';
    }

    public function get_title()
    {
        return __('Formulário de Lead', 'whatsapp-evolution-clients');
    }

    public function get_icon()
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories()
    {
        return ['wec-widgets'];
    }

    public function get_keywords()
    {
        return ['lead', 'form', 'whatsapp', 'contact', 'formulário', 'contato'];
    }

    // Lista de códigos de país (DDI)
    private function get_country_codes()
    {
        return [
            '+55' => '🇧🇷 Brasil (+55)',
            '+1' => '🇺🇸 EUA/Canadá (+1)',
            '+54' => '🇦🇷 Argentina (+54)',
            '+56' => '🇨🇱 Chile (+56)',
            '+57' => '🇨🇴 Colômbia (+57)',
            '+58' => '🇻🇪 Venezuela (+58)',
            '+51' => '🇵🇪 Peru (+51)',
            '+52' => '🇲🇽 México (+52)',
            '+591' => '🇧🇴 Bolívia (+591)',
            '+595' => '🇵🇾 Paraguai (+595)',
            '+598' => '🇺🇾 Uruguai (+598)',
            '+351' => '🇵🇹 Portugal (+351)',
            '+34' => '🇪🇸 Espanha (+34)',
            '+33' => '🇫🇷 França (+33)',
            '+49' => '🇩🇪 Alemanha (+49)',
            '+39' => '🇮🇹 Itália (+39)',
            '+44' => '🇬🇧 Reino Unido (+44)',
            '+81' => '🇯🇵 Japão (+81)',
            '+86' => '🇨🇳 China (+86)',
            '+91' => '🇮🇳 Índia (+91)',
            '+61' => '🇦🇺 Austrália (+61)',
            '+27' => '🇿🇦 África do Sul (+27)',
            '+971' => '🇦🇪 Emirados Árabes (+971)',
            '+972' => '🇮🇱 Israel (+972)',
            '+7' => '🇷🇺 Rússia (+7)',
            '+82' => '🇰🇷 Coreia do Sul (+82)',
            '+65' => '🇸🇬 Singapura (+65)',
            '+66' => '🇹🇭 Tailândia (+66)',
            '+84' => '🇻🇳 Vietnã (+84)',
            '+63' => '🇵🇭 Filipinas (+63)',
            '+62' => '🇮🇩 Indonésia (+62)',
            '+60' => '🇲🇾 Malásia (+60)',
            '+31' => '🇳🇱 Holanda (+31)',
            '+32' => '🇧🇪 Bélgica (+32)',
            '+41' => '🇨🇭 Suíça (+41)',
            '+43' => '🇦🇹 Áustria (+43)',
            '+48' => '🇵🇱 Polônia (+48)',
            '+420' => '🇨🇿 República Tcheca (+420)',
            '+30' => '🇬🇷 Grécia (+30)',
            '+90' => '🇹🇷 Turquia (+90)',
            '+20' => '🇪🇬 Egito (+20)',
            '+212' => '🇲🇦 Marrocos (+212)',
            '+234' => '🇳🇬 Nigéria (+234)',
            '+254' => '🇰🇪 Quênia (+254)',
        ];
    }

    // Controles do widget
    protected function register_controls()
    {
        // ========================================
        // TAB: CONTEÚDO
        // ========================================
        
        // Seção: Campos do Formulário
        $this->start_controls_section(
            'section_fields',
            [
                'label' => __('Campos do Formulário', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_name',
            [
                'label' => __('Nome', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'name_required',
            [
                'label' => __('Nome Obrigatório', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => ['show_name' => 'yes'],
            ]
        );

        $this->add_control(
            'name_label',
            [
                'label' => __('Label do Nome', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Nome Completo', 'whatsapp-evolution-clients'),
                'condition' => ['show_name' => 'yes'],
            ]
        );

        $this->add_control(
            'name_placeholder',
            [
                'label' => __('Placeholder do Nome', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Digite seu nome...', 'whatsapp-evolution-clients'),
                'condition' => ['show_name' => 'yes'],
            ]
        );

        $this->add_control(
            'divider_email',
            ['type' => Controls_Manager::DIVIDER]
        );

        $this->add_control(
            'show_email',
            [
                'label' => __('E-mail', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'email_required',
            [
                'label' => __('E-mail Obrigatório', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'condition' => ['show_email' => 'yes'],
            ]
        );

        $this->add_control(
            'email_label',
            [
                'label' => __('Label do E-mail', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('E-mail', 'whatsapp-evolution-clients'),
                'condition' => ['show_email' => 'yes'],
            ]
        );

        $this->add_control(
            'email_placeholder',
            [
                'label' => __('Placeholder do E-mail', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('seu@email.com', 'whatsapp-evolution-clients'),
                'condition' => ['show_email' => 'yes'],
            ]
        );

        $this->add_control(
            'divider_whatsapp',
            ['type' => Controls_Manager::DIVIDER]
        );

        $this->add_control(
            'show_whatsapp',
            [
                'label' => __('WhatsApp', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'whatsapp_required',
            [
                'label' => __('WhatsApp Obrigatório', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => ['show_whatsapp' => 'yes'],
            ]
        );

        $this->add_control(
            'whatsapp_label',
            [
                'label' => __('Label do WhatsApp', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('WhatsApp', 'whatsapp-evolution-clients'),
                'condition' => ['show_whatsapp' => 'yes'],
            ]
        );

        $this->add_control(
            'whatsapp_placeholder',
            [
                'label' => __('Placeholder do WhatsApp', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('(11) 99999-9999', 'whatsapp-evolution-clients'),
                'condition' => ['show_whatsapp' => 'yes'],
            ]
        );

        $this->add_control(
            'whatsapp_ddi_default',
            [
                'label' => __('DDI Padrão', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SELECT,
                'default' => '+55',
                'options' => $this->get_country_codes(),
                'condition' => ['show_whatsapp' => 'yes'],
            ]
        );

        $this->add_control(
            'whatsapp_show_ddi_selector',
            [
                'label' => __('Permitir trocar DDI', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => __('Se desativado, usa apenas o DDI padrão', 'whatsapp-evolution-clients'),
                'condition' => ['show_whatsapp' => 'yes'],
            ]
        );

        $this->add_control(
            'divider_photo',
            ['type' => Controls_Manager::DIVIDER]
        );

        $this->add_control(
            'show_photo',
            [
                'label' => __('Foto de Perfil', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
            ]
        );

        $this->add_control(
            'photo_label',
            [
                'label' => __('Label da Foto', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Foto de Perfil', 'whatsapp-evolution-clients'),
                'condition' => ['show_photo' => 'yes'],
            ]
        );

        $this->add_control(
            'photo_enable_cropper',
            [
                'label' => __('Habilitar Cropper', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => __('Permite recortar a imagem antes do upload', 'whatsapp-evolution-clients'),
                'condition' => ['show_photo' => 'yes'],
            ]
        );

        $this->add_control(
            'photo_aspect_ratio',
            [
                'label' => __('Proporção do Crop', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SELECT,
                'default' => '1:1',
                'options' => [
                    '1:1' => __('1:1 (Quadrado)', 'whatsapp-evolution-clients'),
                    '4:3' => __('4:3 (Paisagem)', 'whatsapp-evolution-clients'),
                    '3:4' => __('3:4 (Retrato)', 'whatsapp-evolution-clients'),
                    '16:9' => __('16:9 (Widescreen)', 'whatsapp-evolution-clients'),
                    '9:16' => __('9:16 (Stories)', 'whatsapp-evolution-clients'),
                    '3:2' => __('3:2 (Foto)', 'whatsapp-evolution-clients'),
                    '2:3' => __('2:3 (Retrato Foto)', 'whatsapp-evolution-clients'),
                    'free' => __('Livre (Sem proporção)', 'whatsapp-evolution-clients'),
                    'custom' => __('Personalizado', 'whatsapp-evolution-clients'),
                ],
                'condition' => [
                    'show_photo' => 'yes',
                    'photo_enable_cropper' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'photo_aspect_custom_width',
            [
                'label' => __('Proporção - Largura', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1,
                'min' => 1,
                'max' => 100,
                'condition' => [
                    'show_photo' => 'yes',
                    'photo_enable_cropper' => 'yes',
                    'photo_aspect_ratio' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'photo_aspect_custom_height',
            [
                'label' => __('Proporção - Altura', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1,
                'min' => 1,
                'max' => 100,
                'condition' => [
                    'show_photo' => 'yes',
                    'photo_enable_cropper' => 'yes',
                    'photo_aspect_ratio' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'photo_output_width',
            [
                'label' => __('Largura Final (px)', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::NUMBER,
                'default' => 400,
                'min' => 50,
                'max' => 2000,
                'description' => __('Largura da imagem após o crop', 'whatsapp-evolution-clients'),
                'condition' => [
                    'show_photo' => 'yes',
                    'photo_enable_cropper' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'photo_output_quality',
            [
                'label' => __('Qualidade (%)', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SLIDER,
                'default' => ['size' => 85],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 100,
                        'step' => 5,
                    ],
                ],
                'description' => __('Qualidade da compressão JPEG', 'whatsapp-evolution-clients'),
                'condition' => [
                    'show_photo' => 'yes',
                    'photo_enable_cropper' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'divider_description',
            ['type' => Controls_Manager::DIVIDER]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Descrição/Observações', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
            ]
        );

        $this->add_control(
            'description_label',
            [
                'label' => __('Label da Descrição', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Observações', 'whatsapp-evolution-clients'),
                'condition' => ['show_description' => 'yes'],
            ]
        );

        $this->add_control(
            'description_placeholder',
            [
                'label' => __('Placeholder da Descrição', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Digite suas observações...', 'whatsapp-evolution-clients'),
                'condition' => ['show_description' => 'yes'],
            ]
        );

        $this->add_control(
            'divider_categories',
            ['type' => Controls_Manager::DIVIDER]
        );

        $this->add_control(
            'show_categories',
            [
                'label' => __('Categorias', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
            ]
        );

        $this->add_control(
            'categories_label',
            [
                'label' => __('Label das Categorias', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Categoria', 'whatsapp-evolution-clients'),
                'condition' => ['show_categories' => 'yes'],
            ]
        );

        $this->add_control(
            'categories_type',
            [
                'label' => __('Tipo de Exibição', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SELECT,
                'default' => 'select',
                'options' => [
                    'select' => __('Select (Dropdown)', 'whatsapp-evolution-clients'),
                    'checkbox' => __('Checkboxes', 'whatsapp-evolution-clients'),
                    'radio' => __('Radio Buttons', 'whatsapp-evolution-clients'),
                ],
                'condition' => ['show_categories' => 'yes'],
            ]
        );

        $this->add_control(
            'divider_interests',
            ['type' => Controls_Manager::DIVIDER]
        );

        $this->add_control(
            'show_interests',
            [
                'label' => __('Interesses', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'interests_label',
            [
                'label' => __('Label dos Interesses', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Interesses', 'whatsapp-evolution-clients'),
                'condition' => ['show_interests' => 'yes'],
            ]
        );

        $this->add_control(
            'interests_type',
            [
                'label' => __('Tipo de Exibição', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SELECT,
                'default' => 'checkbox',
                'options' => [
                    'select' => __('Select (Dropdown)', 'whatsapp-evolution-clients'),
                    'checkbox' => __('Checkboxes', 'whatsapp-evolution-clients'),
                    'radio' => __('Radio Buttons', 'whatsapp-evolution-clients'),
                ],
                'condition' => ['show_interests' => 'yes'],
            ]
        );

        $this->end_controls_section();

        // Seção: Botão
        $this->start_controls_section(
            'section_button',
            [
                'label' => __('Botão de Envio', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Texto do Botão', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Cadastrar', 'whatsapp-evolution-clients'),
            ]
        );

        $this->add_control(
            'button_loading_text',
            [
                'label' => __('Texto Carregando', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Enviando...', 'whatsapp-evolution-clients'),
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label' => __('Ícone', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-paper-plane',
                    'library' => 'fa-solid',
                ],
            ]
        );

        $this->add_control(
            'button_icon_position',
            [
                'label' => __('Posição do Ícone', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SELECT,
                'default' => 'left',
                'options' => [
                    'left' => __('Esquerda', 'whatsapp-evolution-clients'),
                    'right' => __('Direita', 'whatsapp-evolution-clients'),
                ],
            ]
        );

        $this->add_responsive_control(
            'button_width',
            [
                'label' => __('Largura', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SELECT,
                'default' => 'full',
                'options' => [
                    'auto' => __('Auto', 'whatsapp-evolution-clients'),
                    'full' => __('100%', 'whatsapp-evolution-clients'),
                ],
                'selectors_dictionary' => [
                    'auto' => 'auto',
                    'full' => '100%',
                ],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-submit' => 'width: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Seção: Mensagens
        $this->start_controls_section(
            'section_messages',
            [
                'label' => __('Mensagens', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label' => __('Mensagem de Sucesso', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Cadastro realizado com sucesso! Obrigado.', 'whatsapp-evolution-clients'),
            ]
        );

        $this->add_control(
            'error_message',
            [
                'label' => __('Mensagem de Erro', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Ocorreu um erro. Por favor, tente novamente.', 'whatsapp-evolution-clients'),
            ]
        );

        $this->add_control(
            'required_message',
            [
                'label' => __('Mensagem Campo Obrigatório', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Este campo é obrigatório.', 'whatsapp-evolution-clients'),
            ]
        );

        $this->end_controls_section();

        // ========================================
        // TAB: ESTILO
        // ========================================

        // Seção: Estilo do Formulário
        $this->start_controls_section(
            'section_style_form',
            [
                'label' => __('Formulário', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'form_background',
                'label' => __('Fundo', 'whatsapp-evolution-clients'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .wec-lead-form',
            ]
        );

        $this->add_responsive_control(
            'form_padding',
            [
                'label' => __('Padding', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .wec-lead-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'form_margin',
            [
                'label' => __('Margin', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .wec-lead-form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'label' => __('Borda', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-lead-form',
            ]
        );

        $this->add_responsive_control(
            'form_border_radius',
            [
                'label' => __('Border Radius', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .wec-lead-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'form_box_shadow',
                'label' => __('Sombra', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-lead-form',
            ]
        );

        $this->end_controls_section();

        // Seção: Estilo dos Labels
        $this->start_controls_section(
            'section_style_labels',
            [
                'label' => __('Labels', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Cor', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'label' => __('Tipografia', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-label',
            ]
        );

        $this->add_responsive_control(
            'label_spacing',
            [
                'label' => __('Espaçamento', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 30],
                ],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'required_indicator_color',
            [
                'label' => __('Cor do Asterisco (*)', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e74c3c',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-required' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Seção: Estilo dos Inputs
        $this->start_controls_section(
            'section_style_inputs',
            [
                'label' => __('Campos de Entrada', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __('Cor do Texto', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-input, {{WRAPPER}} .wec-form-textarea, {{WRAPPER}} .wec-form-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_placeholder_color',
            [
                'label' => __('Cor do Placeholder', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-input::placeholder, {{WRAPPER}} .wec-form-textarea::placeholder' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Cor de Fundo', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-input, {{WRAPPER}} .wec-form-textarea, {{WRAPPER}} .wec-form-select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'label' => __('Tipografia', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-input, {{WRAPPER}} .wec-form-textarea, {{WRAPPER}} .wec-form-select',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'label' => __('Borda', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-input, {{WRAPPER}} .wec-form-textarea, {{WRAPPER}} .wec-form-select',
            ]
        );

        $this->add_responsive_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-input, {{WRAPPER}} .wec-form-textarea, {{WRAPPER}} .wec-form-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __('Padding', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-input, {{WRAPPER}} .wec-form-textarea, {{WRAPPER}} .wec-form-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'input_focus_heading',
            [
                'label' => __('Estado: Foco', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'input_focus_border_color',
            [
                'label' => __('Cor da Borda (Foco)', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-input:focus, {{WRAPPER}} .wec-form-textarea:focus, {{WRAPPER}} .wec-form-select:focus' => 'border-color: {{VALUE}}; outline: none;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'input_focus_shadow',
                'label' => __('Sombra (Foco)', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-input:focus, {{WRAPPER}} .wec-form-textarea:focus, {{WRAPPER}} .wec-form-select:focus',
            ]
        );

        $this->end_controls_section();

        // Seção: Estilo do Botão
        $this->start_controls_section(
            'section_style_button',
            [
                'label' => __('Botão', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab(
            'button_normal',
            ['label' => __('Normal', 'whatsapp-evolution-clients')]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Cor do Texto', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-submit' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'button_background',
                'label' => __('Fundo', 'whatsapp-evolution-clients'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .wec-form-submit',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover',
            ['label' => __('Hover', 'whatsapp-evolution-clients')]
        );

        $this->add_control(
            'button_hover_text_color',
            [
                'label' => __('Cor do Texto', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-submit:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'button_hover_background',
                'label' => __('Fundo', 'whatsapp-evolution-clients'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .wec-form-submit:hover',
            ]
        );

        $this->add_control(
            'button_hover_border_color',
            [
                'label' => __('Cor da Borda', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .wec-form-submit:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => __('Tipografia', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-submit',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => __('Borda', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-submit',
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'label' => __('Sombra', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-submit',
            ]
        );

        $this->end_controls_section();

        // Seção: Estilo das Mensagens
        $this->start_controls_section(
            'section_style_messages',
            [
                'label' => __('Mensagens', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'success_heading',
            [
                'label' => __('Mensagem de Sucesso', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'success_text_color',
            [
                'label' => __('Cor do Texto', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#155724',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message.success' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'success_background',
            [
                'label' => __('Cor de Fundo', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#d4edda',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message.success' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'success_border_color',
            [
                'label' => __('Cor da Borda', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#c3e6cb',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message.success' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'error_heading',
            [
                'label' => __('Mensagem de Erro', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'error_text_color',
            [
                'label' => __('Cor do Texto', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#721c24',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message.error' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'error_background',
            [
                'label' => __('Cor de Fundo', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8d7da',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message.error' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'error_border_color',
            [
                'label' => __('Cor da Borda', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f5c6cb',
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message.error' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'message_typography',
                'label' => __('Tipografia', 'whatsapp-evolution-clients'),
                'selector' => '{{WRAPPER}} .wec-form-message',
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'message_padding',
            [
                'label' => __('Padding', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'message_border_radius',
            [
                'label' => __('Border Radius', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Seção: Espaçamento entre campos
        $this->start_controls_section(
            'section_style_spacing',
            [
                'label' => __('Espaçamento', 'whatsapp-evolution-clients'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'field_spacing',
            [
                'label' => __('Espaço entre Campos', 'whatsapp-evolution-clients'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 60],
                ],
                'default' => ['size' => 20, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .wec-form-group' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    // Renderização do widget
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        
        // Buscar taxonomias
        $categories = get_terms([
            'taxonomy' => WEC_CPT::TAXONOMY,
            'hide_empty' => false,
        ]);
        
        $interests = get_terms([
            'taxonomy' => WEC_CPT::TAXONOMY_INTEREST,
            'hide_empty' => false,
        ]);
        ?>
        <form class="wec-lead-form" id="wec-lead-form-<?php echo $this->get_id(); ?>" enctype="multipart/form-data">
            <div class="wec-form-message" style="display: none;"></div>
            
            <?php if ($settings['show_name'] === 'yes'): ?>
            <div class="wec-form-group">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['name_label']); ?>
                    <?php if ($settings['name_required'] === 'yes'): ?>
                        <span class="wec-form-required">*</span>
                    <?php endif; ?>
                </label>
                <input type="text" 
                       name="name" 
                       class="wec-form-input" 
                       placeholder="<?php echo esc_attr($settings['name_placeholder']); ?>"
                       <?php echo $settings['name_required'] === 'yes' ? 'required' : ''; ?>>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_email'] === 'yes'): ?>
            <div class="wec-form-group">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['email_label']); ?>
                    <?php if ($settings['email_required'] === 'yes'): ?>
                        <span class="wec-form-required">*</span>
                    <?php endif; ?>
                </label>
                <input type="email" 
                       name="email" 
                       class="wec-form-input" 
                       placeholder="<?php echo esc_attr($settings['email_placeholder']); ?>"
                       <?php echo $settings['email_required'] === 'yes' ? 'required' : ''; ?>>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_whatsapp'] === 'yes'): ?>
            <div class="wec-form-group">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['whatsapp_label']); ?>
                    <?php if ($settings['whatsapp_required'] === 'yes'): ?>
                        <span class="wec-form-required">*</span>
                    <?php endif; ?>
                </label>
                <div class="wec-phone-wrapper">
                    <?php if ($settings['whatsapp_show_ddi_selector'] === 'yes'): ?>
                    <select name="whatsapp_ddi" class="wec-form-select wec-ddi-select">
                        <?php foreach ($this->get_country_codes() as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['whatsapp_ddi_default'], $code); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="whatsapp_ddi" value="<?php echo esc_attr($settings['whatsapp_ddi_default']); ?>">
                    <span class="wec-ddi-fixed"><?php echo esc_html($settings['whatsapp_ddi_default']); ?></span>
                    <?php endif; ?>
                    <input type="tel" 
                           name="whatsapp" 
                           class="wec-form-input wec-phone-input" 
                           placeholder="<?php echo esc_attr($settings['whatsapp_placeholder']); ?>"
                           <?php echo $settings['whatsapp_required'] === 'yes' ? 'required' : ''; ?>>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_photo'] === 'yes'): 
                $enable_cropper = $settings['photo_enable_cropper'] === 'yes';
                $aspect_ratio = $settings['photo_aspect_ratio'] ?? '1:1';
                $custom_w = $settings['photo_aspect_custom_width'] ?? 1;
                $custom_h = $settings['photo_aspect_custom_height'] ?? 1;
                $output_width = $settings['photo_output_width'] ?? 400;
                $output_quality = isset($settings['photo_output_quality']['size']) ? $settings['photo_output_quality']['size'] / 100 : 0.85;
            ?>
            <div class="wec-form-group wec-photo-group" 
                 data-cropper="<?php echo $enable_cropper ? 'true' : 'false'; ?>"
                 data-aspect="<?php echo esc_attr($aspect_ratio); ?>"
                 data-custom-w="<?php echo esc_attr($custom_w); ?>"
                 data-custom-h="<?php echo esc_attr($custom_h); ?>"
                 data-output-width="<?php echo esc_attr($output_width); ?>"
                 data-output-quality="<?php echo esc_attr($output_quality); ?>">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['photo_label']); ?>
                </label>
                <div class="wec-photo-upload-area">
                    <input type="file" 
                           name="photo_original" 
                           class="wec-form-input wec-form-file wec-photo-input" 
                           accept="image/*">
                    <input type="hidden" name="photo_cropped" class="wec-photo-cropped">
                    <div class="wec-photo-preview" style="display:none;">
                        <img src="" alt="Preview">
                        <button type="button" class="wec-photo-change"><?php _e('Trocar', 'whatsapp-evolution-clients'); ?></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_description'] === 'yes'): ?>
            <div class="wec-form-group">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['description_label']); ?>
                </label>
                <textarea name="description" 
                          class="wec-form-textarea" 
                          rows="4"
                          placeholder="<?php echo esc_attr($settings['description_placeholder']); ?>"></textarea>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_categories'] === 'yes' && !empty($categories) && !is_wp_error($categories)): ?>
            <div class="wec-form-group">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['categories_label']); ?>
                </label>
                <?php $this->render_taxonomy_field('categories', $categories, $settings['categories_type']); ?>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_interests'] === 'yes' && !empty($interests) && !is_wp_error($interests)): ?>
            <div class="wec-form-group wec-interests-hierarchical" data-type="<?php echo esc_attr($settings['interests_type']); ?>">
                <label class="wec-form-label">
                    <?php echo esc_html($settings['interests_label']); ?>
                </label>
                <?php $this->render_hierarchical_interests($interests, $settings['interests_type']); ?>
            </div>
            <?php endif; ?>

            <div class="wec-form-group wec-form-submit-wrapper">
                <button type="submit" class="wec-form-submit" 
                        data-text="<?php echo esc_attr($settings['button_text']); ?>"
                        data-loading="<?php echo esc_attr($settings['button_loading_text']); ?>"
                        data-success="<?php echo esc_attr($settings['success_message']); ?>"
                        data-error="<?php echo esc_attr($settings['error_message']); ?>">
                    <?php if ($settings['button_icon_position'] === 'left' && !empty($settings['button_icon']['value'])): ?>
                        <i class="<?php echo esc_attr($settings['button_icon']['value']); ?>"></i>
                    <?php endif; ?>
                    <span><?php echo esc_html($settings['button_text']); ?></span>
                    <?php if ($settings['button_icon_position'] === 'right' && !empty($settings['button_icon']['value'])): ?>
                        <i class="<?php echo esc_attr($settings['button_icon']['value']); ?>"></i>
                    <?php endif; ?>
                </button>
            </div>
        </form>
        <?php
    }

    // Renderiza campo de taxonomia
    private function render_taxonomy_field($name, $terms, $type)
    {
        if ($type === 'select') {
            echo '<select name="' . esc_attr($name) . '[]" class="wec-form-select">';
            echo '<option value="">' . __('Selecione...', 'whatsapp-evolution-clients') . '</option>';
            foreach ($terms as $term) {
                echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<div class="wec-form-' . esc_attr($type) . '-group">';
            foreach ($terms as $term) {
                $input_type = $type === 'radio' ? 'radio' : 'checkbox';
                echo '<label class="wec-form-' . esc_attr($type) . '-label">';
                echo '<input type="' . $input_type . '" name="' . esc_attr($name) . '[]" value="' . esc_attr($term->term_id) . '">';
                echo '<span>' . esc_html($term->name) . '</span>';
                echo '</label>';
            }
            echo '</div>';
        }
    }

    // Renderiza interesses hierárquicos (até 3 níveis)
    private function render_hierarchical_interests($terms, $type = 'select')
    {
        // Organizar por hierarquia
        $parents = [];
        $children = [];
        
        foreach ($terms as $term) {
            if ($term->parent == 0) {
                $parents[] = $term;
            } else {
                if (!isset($children[$term->parent])) {
                    $children[$term->parent] = [];
                }
                $children[$term->parent][] = $term;
            }
        }
        
        // Dados dos filhos em JSON para JavaScript (usado em todos os tipos)
        $children_data = [];
        foreach ($children as $parent_id => $child_terms) {
            $children_data[$parent_id] = [];
            foreach ($child_terms as $child) {
                $has_grandchildren = isset($children[$child->term_id]);
                $children_data[$parent_id][] = [
                    'id' => $child->term_id,
                    'name' => $child->name,
                    'has_children' => $has_grandchildren,
                ];
                if ($has_grandchildren) {
                    foreach ($children[$child->term_id] as $grandchild) {
                        if (!isset($children_data[$child->term_id])) {
                            $children_data[$child->term_id] = [];
                        }
                        $children_data[$child->term_id][] = [
                            'id' => $grandchild->term_id,
                            'name' => $grandchild->name,
                            'has_children' => false,
                        ];
                    }
                }
            }
        }
        
        $has_hierarchy = !empty($children);
        
        // Renderizar baseado no tipo
        if ($type === 'select') {
            $this->render_interests_select($parents, $children, $has_hierarchy);
        } elseif ($type === 'radio') {
            $this->render_interests_radio_checkbox($parents, $children, $has_hierarchy, 'radio');
        } else {
            $this->render_interests_radio_checkbox($parents, $children, $has_hierarchy, 'checkbox');
        }
        
        // JSON data para hierarquia dinâmica
        if ($has_hierarchy) {
            echo '<script type="application/json" class="wec-interests-children-data">' . json_encode($children_data) . '</script>';
        }
    }

    // Renderiza interesses em formato select
    private function render_interests_select($parents, $children, $has_hierarchy)
    {
        if (!$has_hierarchy) {
            echo '<select name="interests[]" class="wec-form-select">';
            echo '<option value="">' . __('Selecione...', 'whatsapp-evolution-clients') . '</option>';
            foreach ($parents as $term) {
                echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
            }
            echo '</select>';
            return;
        }
        
        echo '<div class="wec-hierarchical-selects">';
        
        // Nível 1
        echo '<select name="interests[]" class="wec-form-select wec-interest-level" data-level="1">';
        echo '<option value="">' . __('Selecione...', 'whatsapp-evolution-clients') . '</option>';
        foreach ($parents as $term) {
            $has_children = isset($children[$term->term_id]);
            echo '<option value="' . esc_attr($term->term_id) . '" data-has-children="' . ($has_children ? '1' : '0') . '">' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
        
        // Níveis 2 e 3 (ocultos)
        echo '<select name="interests[]" class="wec-form-select wec-interest-level" data-level="2" style="display:none;">';
        echo '<option value="">' . __('Selecione subcategoria...', 'whatsapp-evolution-clients') . '</option>';
        echo '</select>';
        
        echo '<select name="interests[]" class="wec-form-select wec-interest-level" data-level="3" style="display:none;">';
        echo '<option value="">' . __('Selecione opção...', 'whatsapp-evolution-clients') . '</option>';
        echo '</select>';
        
        echo '</div>';
    }

    // Renderiza interesses em formato radio ou checkbox
    private function render_interests_radio_checkbox($parents, $children, $has_hierarchy, $input_type)
    {
        $type_class = $input_type === 'radio' ? 'radio' : 'checkbox';
        
        echo '<div class="wec-hierarchical-' . $type_class . '">';
        
        // Nível 1 (pais)
        echo '<div class="wec-interest-level-group" data-level="1">';
        echo '<div class="wec-form-' . $type_class . '-group">';
        foreach ($parents as $term) {
            $has_child = isset($children[$term->term_id]);
            echo '<label class="wec-form-' . $type_class . '-label">';
            echo '<input type="' . $input_type . '" name="interests_level1[]" value="' . esc_attr($term->term_id) . '" data-has-children="' . ($has_child ? '1' : '0') . '" class="wec-interest-input" data-level="1">';
            echo '<span>' . esc_html($term->name) . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
        
        if ($has_hierarchy) {
            // Nível 2 (filhos) - inicialmente oculto
            echo '<div class="wec-interest-level-group" data-level="2" style="display:none;">';
            echo '<div class="wec-form-' . $type_class . '-group wec-interest-children"></div>';
            echo '</div>';
            
            // Nível 3 (netos) - inicialmente oculto
            echo '<div class="wec-interest-level-group" data-level="3" style="display:none;">';
            echo '<div class="wec-form-' . $type_class . '-group wec-interest-grandchildren"></div>';
            echo '</div>';
        }
        
        // Campo oculto para coletar interesses selecionados
        echo '<input type="hidden" name="interests[]" class="wec-interests-collector" value="">';
        
        echo '</div>';
    }
}
