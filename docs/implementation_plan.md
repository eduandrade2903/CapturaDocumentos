# Plano de Implementação - Documentação da API com OpenAPI/Swagger

Este plano descreve a estratégia para implementar a documentação completa das APIs REST existentes do projeto usando a especificação OpenAPI 3.0.0 e a interface interativa do Swagger UI.

## Contexto e Estrutura do Projeto

1. **Framework:** Adianti Framework (PHP).
2. **Pontos de Entrada REST:** 
   - `rest.php`: Endpoint com autenticação e validação obrigatória (Basic ou Bearer).
   - `restp.php`: Endpoint público limitado a subclasses de `AdiantiRecordService`.
3. **Mapeamento de Rotas (`.htaccess`):** Redirecionamentos URL amigáveis para chamadas internas do framework via `class` e `method`.
4. **Mecanismos de Segurança:**
   - **Geral (rest.php):** Requer cabeçalho `Authorization` do tipo `Basic <REST_KEY>` (estática do app) ou `Bearer <JWT_TOKEN>` (token temporário gerado pós-login).
   - **Interno dos Serviços (api_key):** Requer cabeçalho `api_key` contendo a chave única da empresa (guardada criptografada em banco por hash `sha256`).

---

## Estratégia Escolhida

Para garantir facilidade de manutenção, compatibilidade com o ambiente atual e evitar o acoplamento de dependências desnecessárias de terceiros no PHP, adotaremos a seguinte estratégia:

1. **Especificação OpenAPI em YAML (`openapi.yaml`):** Escrita manual da especificação OpenAPI 3.0 baseada no comportamento real das APIs encontradas nas classes e modelos PHP.
2. **Interface Swagger UI Estática (`index.html`):** Uma página HTML simples utilizando a biblioteca Swagger UI via CDN (unpkg ou cdnjs). Isso garante que o Swagger UI funcione instantaneamente sem exigir dependências no `composer.json` ou compilação local.
3. **Roteamento Nativo no Servidor Web (`.htaccess`):** Criação de uma regra de reescrita dedicada para expor a URL `/swagger` de forma limpa e amigável.

---

## Alterações Propostas

### 1. Documentação e Assets

#### [NEW] [openapi.yaml](file:///Users/edutronicos/Projetos/capturaDocumentos/docs/swagger/openapi.yaml)
Criação do arquivo de definição da API contendo:
- Metadados gerais (Título, Versão, Descrição, URL base).
- Configuração de esquemas de segurança:
  - `basicAuth` (Authorization: Basic)
  - `bearerAuth` (Authorization: Bearer)
  - `apiKeyHeader` (api_key)
- Documentação detalhada dos seguintes endpoints e seus respectivos métodos:
  - `GET /auth/{login}/{password}` (Autenticação do usuário e geração de token JWT)
  - `POST /empresas` (Cadastro de nova empresa parceira)
  - `GET /empresas` (Consulta dados cadastrais da empresa ativa)
  - `PUT /empresas` (Atualização de dados cadastrais da empresa ativa)
  - `GET /usuarios` e `GET /usuarios/{id}` (Listagem e busca de usuários por ID)
  - `POST /usuarios` (Criação de usuário e geração automática de solicitação de coleta)
  - `PUT /usuarios/{id}` (Atualização de usuário)
  - `DELETE /usuarios` e `DELETE /usuarios/{id}` (Exclusão em lote ou unitária)
  - `GET /collections` e `GET /collections/{id}` (Listagem e busca de solicitações de coleta)
  - `POST /collections` (Criação de solicitação de coleta)
  - `PUT /collections/{id}` (Atualização de solicitação de coleta)
  - `DELETE /collections` e `DELETE /collections/{id}` (Exclusão em lote ou unitária)
  - `GET /webhook-events` (Listagem de eventos de Webhook processados pela empresa)
- Modelagem de Schemas reutilizáveis (`components/schemas`) para respostas padrão (`status: success|error`), paginação/filtros (offset, limit, order, direction) e payloads de entrada.

#### [NEW] [index.html](file:///Users/edutronicos/Projetos/capturaDocumentos/docs/swagger/index.html)
Página HTML autônoma configurada para carregar e renderizar o Swagger UI, apontando para o arquivo `openapi.yaml`.

---

### 2. Configurações de Rota

#### [MODIFY] [.htaccess](file:///Users/edutronicos/Projetos/capturaDocumentos/.htaccess)
Adicionar regra de redirecionamento no topo do arquivo `.htaccess`:
```http
# Swagger UI Route
RewriteRule ^swagger$ docs/swagger/index.html [NC,L]
```

---

## Validações e Inconsistências Identificadas

Durante o mapeamento e pesquisa inicial das classes do projeto, identificamos as seguintes inconsistências e particularidades a serem listadas na documentação oficial:
1. **Retorno de Cadastro de Empresa:** O arquivo `Documentacao/Empresas.md` descreve que a chamada `POST /empresas` retorna o token em texto claro no corpo do JSON (`api_key: "4f9..."`). No entanto, o arquivo `EmpresasRestService.php` realiza o envio do token unicamente via e-mail e retorna somente uma mensagem de sucesso no JSON. A especificação OpenAPI refletirá o comportamento real do código-fonte (não retorna a chave no JSON).
2. **Inconsistência de Método em `CollectionRequestsRestService`:** O método `create` do service tenta chamar `CollectionRequest::registrar()`, mas o modelo `CollectionRequest` possui apenas a assinatura `registra()`. Esta inconsistência do código original impede a criação manual pela rota `/collections`, mas está fora do escopo de alteração de regras de negócio, conforme as regras estabelecidas. Documentaremos a rota conforme ela está implementada sintaticamente para fins de representação.
3. **Cabeçalhos de Segurança em Cascata:** Todos os endpoints sob o gateway `rest.php` necessitam obrigatoriamente de autenticação dupla (a autorização geral do framework em `rest.php` por `Basic` ou `Bearer`, mais o cabeçalho específico de domínio `api_key` exigido em cada controller REST).

---

## Plano de Verificação

### Testes Manuais
1. Validar o arquivo `openapi.yaml` usando o linter OpenAPI para garantir que não há erros de sintaxe ou referências quebradas.
2. Acessar o endpoint `/swagger` localmente para garantir que o Swagger UI carrega a documentação do projeto sem erros de CORS.
3. Testar a funcionalidade de "Authorize" no Swagger UI simulando o envio de tokens JWT e chaves de API.
