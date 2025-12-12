# WhatsApp Evolution Clients

**Versão:** 1.0.0  
**Autor:** [Dante Testa](https://dantetesta.com.br)  
**Data:** 2025-12-11

## 📋 Descrição

Plugin WordPress para gerenciamento de clientes com envio de mensagens WhatsApp via Evolution API. Permite cadastro de clientes com dados de contato, incluindo número de WhatsApp com validação internacional, e envio de mensagens individuais ou em massa.

## ✨ Funcionalidades

- ✅ **Custom Post Type "Clientes"**: Cadastro completo com e-mail, WhatsApp e descrição
- ✅ **Campo de telefone internacional**: Usando intl-tel-input com validação e formatação E.164
- ✅ **Página de configurações**: Configuração da Evolution API (URL, instância, token)
- ✅ **Teste de conexão**: Verificação da conexão com a API em tempo real
- ✅ **Envio individual**: Envio de mensagem para um cliente diretamente da listagem
- ✅ **Envio em massa**: Seleção de múltiplos clientes para envio com delay randômico
- ✅ **Barra de progresso**: Acompanhamento visual do envio em massa
- ✅ **Segurança**: Nonces, capabilities e criptografia de token

## 🛠️ Requisitos

- WordPress 5.8+
- PHP 8.0+
- Evolution API V2 configurada e funcionando

## 📦 Instalação

1. Faça o download do plugin ou clone o repositório
2. Copie a pasta `whatsapp-evolution-clients` para `/wp-content/plugins/`
3. Ative o plugin no painel WordPress
4. Configure as credenciais em **Configurações → Evolution WhatsApp**

## ⚙️ Configuração

### Evolution API

1. Acesse **Configurações → Evolution WhatsApp**
2. Preencha os campos:
   - **Evolution API Base URL**: URL da sua instância (ex: `https://api.evolution.com`)
   - **Instance Name**: Nome da instância configurada
   - **Instance Token**: Token de autenticação da instância
   - **Sender WhatsApp Number**: Número que enviará as mensagens (formato E.164)
3. Clique em **Testar conexão** para verificar

## 📱 Uso

### Cadastrar Clientes

1. Acesse **Clientes WEC → Adicionar Novo**
2. Preencha o nome do cliente (título)
3. Na seção **Dados do Cliente**, preencha:
   - E-mail
   - WhatsApp (selecione o país e digite o número)
   - Descrição/Observações

### Enviar Mensagem Individual

1. Na listagem de clientes, passe o mouse sobre o cliente desejado
2. Clique em **Enviar WhatsApp**
3. Digite a mensagem no modal
4. Clique em **Enviar**

### Enviar Mensagem em Massa

1. Na listagem, marque os clientes desejados
2. Selecione **Disparo em massa via WhatsApp** nas Ações em Lote
3. Clique em **Aplicar**
4. No modal, digite a mensagem
5. Clique em **Iniciar Envio**
6. Acompanhe o progresso (delay randômico de 4-20 segundos entre envios)

## 🔐 Segurança

- Tokens são armazenados de forma criptografada
- Todas as requisições AJAX são protegidas por nonces
- Verificação de capabilities em todas as operações
- Sanitização de inputs e escape de outputs

## 🏗️ Estrutura de Arquivos

```
whatsapp-evolution-clients/
├── whatsapp-evolution-clients.php    # Arquivo principal
├── includes/
│   ├── class-wec-cpt.php            # Custom Post Type
│   ├── class-wec-meta-boxes.php     # Meta boxes
│   ├── class-wec-settings.php       # Configurações
│   ├── class-wec-api.php            # Integração Evolution API
│   ├── class-wec-ajax.php           # Handlers AJAX
│   └── class-wec-security.php       # Segurança
├── admin/
│   ├── class-wec-admin.php          # Admin controller
│   └── class-wec-list-actions.php   # Bulk/Row actions
├── assets/
│   ├── css/
│   │   └── wec-admin.css            # Estilos admin
│   ├── js/
│   │   ├── wec-admin.js             # Scripts principais
│   │   ├── wec-intl-phone.js        # intl-tel-input
│   │   └── wec-bulk-sender.js       # Envio em massa
│   └── vendor/
│       └── intl-tel-input/          # Biblioteca de telefone
├── languages/                        # Traduções (futuro)
├── ROADMAP.md                        # Roadmap do projeto
└── README.md                         # Este arquivo
```

## 🔌 Hooks Disponíveis

### Filters

```php
// Modificar payload antes de enviar
apply_filters('wec_send_message_payload', $payload, $client_id);

// Modificar resposta da API
apply_filters('wec_api_response', $response, $endpoint);
```

### Actions

```php
// Após envio bem-sucedido
do_action('wec_message_sent', $client_id, $phone, $message);

// Após falha no envio
do_action('wec_message_failed', $client_id, $phone, $error);
```

## 📝 Changelog

### 1.0.0 (2025-12-11)
- Lançamento inicial
- CPT Clientes com campos personalizados
- Integração com Evolution API
- Envio individual e em massa
- Interface de administração completa

## 📄 Licença

GPL v2 or later

## 👨‍💻 Autor

**Dante Testa**  
Website: [dantetesta.com.br](https://dantetesta.com.br)  
E-mail: contato@dantetesta.com.br
