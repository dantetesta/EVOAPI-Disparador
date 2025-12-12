# WhatsApp Evolution Clients - Roadmap de Desenvolvimento

**Autor:** [Dante Testa](https://dantetesta.com.br)  
**Data de Criação:** 2025-12-11 09:49:22  
**Versão:** 1.0.0

---

## 📋 Visão Geral do Projeto

Plugin WordPress para gerenciamento de clientes com envio de mensagens WhatsApp via Evolution API.

---

## 🏗️ Estrutura de Arquivos

```
whatsapp-evolution-clients/
├── whatsapp-evolution-clients.php    # Arquivo principal do plugin
├── includes/
│   ├── class-wec-cpt.php            # Custom Post Type
│   ├── class-wec-meta-boxes.php     # Meta boxes do CPT
│   ├── class-wec-settings.php       # Página de configurações
│   ├── class-wec-ajax.php           # Handlers AJAX
│   ├── class-wec-api.php            # Integração Evolution API
│   └── class-wec-security.php       # Segurança e capabilities
├── admin/
│   ├── class-wec-admin.php          # Admin controller
│   └── class-wec-list-actions.php   # Ações na listagem
├── assets/
│   ├── css/
│   │   └── wec-admin.css            # Estilos admin
│   ├── js/
│   │   ├── wec-admin.js             # Scripts principais
│   │   ├── wec-intl-phone.js        # Inicialização intl-tel-input
│   │   └── wec-bulk-sender.js       # Lógica de disparo em massa
│   └── vendor/
│       └── intl-tel-input/          # Biblioteca de telefone
├── languages/                        # Traduções
└── README.md                         # Documentação
```

---

## 📅 Fases de Desenvolvimento

### Fase 1: Estrutura Base
- [x] Criar arquivo principal do plugin
- [x] Registrar hooks de ativação/desativação
- [x] Configurar autoload de classes
- [x] Definir constantes do plugin

### Fase 2: Custom Post Type
- [x] Criar CPT `wec_client`
- [x] Configurar labels em português
- [x] Configurar supports (title, editor, thumbnail)
- [x] Adicionar ícone e posição no menu

### Fase 3: Meta Boxes e Campos
- [x] Criar meta box "Dados do Cliente"
- [x] Campo de e-mail com validação
- [x] Campo de WhatsApp com intl-tel-input
- [x] Campo de descrição/observações
- [x] Salvar dados em formato E.164

### Fase 4: Página de Configurações
- [x] Criar página em Settings → Evolution WhatsApp
- [x] Campos: API Base URL, Instance Name, Token, Sender Number
- [x] Criptografia do token (se possível)
- [x] Botão "Testar Conexão" com feedback visual

### Fase 5: Integração Evolution API
- [x] Classe para requisições HTTP
- [x] Método de teste de conexão
- [x] Método de envio de mensagem individual
- [x] Tratamento de erros da API

### Fase 6: Ações na Listagem
- [x] Bulk action "Disparo em massa via WhatsApp"
- [x] Row action "Enviar WhatsApp"
- [x] Modais para composição de mensagens
- [x] Barra de progresso para envio em massa

### Fase 7: Lógica de Envio
- [x] Processamento via AJAX (um cliente por vez)
- [x] Delay randômico entre 4-20 segundos
- [x] Feedback em tempo real no frontend
- [x] Resumo final de envios

### Fase 8: Segurança
- [x] Verificação de nonces em todos os AJAX
- [x] Verificação de capabilities
- [x] Sanitização de inputs
- [x] Escape de outputs

### Fase 9: UI/UX
- [x] CSS minimalista para modais
- [x] Compatibilidade com temas claros/escuros
- [x] Mensagens em português brasileiro
- [x] Responsividade básica

### Fase 10: Testes e Finalização
- [x] Testar ativação/desativação (verificação de sintaxe OK)
- [x] Testar CRUD de clientes
- [x] Testar validação de telefone
- [x] Testar conexão com Evolution API
- [x] Testar envio individual e em massa

---

## 🔐 Requisitos de Segurança

1. **Nonces**: Todos os formulários e requisições AJAX
2. **Capabilities**: `manage_options` para configurações, `edit_wec_client` para clientes
3. **Sanitização**: `sanitize_email()`, `sanitize_text_field()`, regex para telefones
4. **Escape**: `esc_html()`, `esc_attr()`, `esc_url()` em todas as saídas

---

## 🌐 Integração Evolution API

Endpoints utilizados:
- `POST /message/sendText/{instance}` - Envio de mensagem de texto

Headers:
- `Content-Type: application/json`
- `apikey: {token}`

Payload:
```json
{
  "number": "5511999999999",
  "text": "Sua mensagem aqui"
}
```

---

## 📝 Notas de Implementação

- Telefones sempre salvos em formato E.164 (ex: +5519980219567)
- Delay randômico implementado no frontend com `setTimeout()`
- Fila de envio gerenciada por JavaScript
- PHP processa um cliente por requisição AJAX
