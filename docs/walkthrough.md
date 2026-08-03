# Walkthrough - Implementação do Swagger / OpenAPI

Este documento detalha o que foi criado e modificado para implementar a documentação OpenAPI e disponibilizar o Swagger UI no projeto.

---

## 🛠️ Alterações Efetuadas

### 1. Documentação da API

- **[NEW] [openapi.yaml](file:///Users/edutronicos/Projetos/capturaDocumentos/docs/swagger/openapi.yaml)**:
  Contém toda a especificação OpenAPI 3.0.0 mapeada a partir do comportamento real do Adianti Framework e dos controllers PHP:
    - **Rotas de Negócio**: Cadastro, consulta e atualização de Empresas, Titulares (Usuários), Solicitações de Coleta e Eventos de Webhook.
    - **Esquema de Segurança Mapeado**: Combinação de segurança do gateway (HTTP Basic com `REST_KEY` global ou HTTP Bearer com `JWT_TOKEN`) com segurança interna de empresa parceira (cabeçalho `api_key`).
    - **Parâmetros Adianti**: Suporte total a `limit`, `offset`, `order`, `direction` e a estrutura de filtros `filters[index][value]`.

- **[NEW] [index.html](file:///Users/edutronicos/Projetos/capturaDocumentos/docs/swagger/index.html)**:
  Contém o contêiner Swagger UI interativo integrado que consome o arquivo `openapi.yaml`. Ele utiliza a biblioteca Swagger UI hospedada em CDN rápida para evitar a introdução de novos pacotes `npm` ou de dependências no `composer.json`.

---

### 2. Configurações de Rota no Servidor

- **[MODIFY] [.htaccess](file:///Users/edutronicos/Projetos/capturaDocumentos/.htaccess)**:
  Adição da regra de reescrita amigável para expor a interface de documentação:
  ```http
  # Swagger UI Route
  RewriteRule ^swagger$ docs/swagger/index.html [NC,L]
  ```

---

## 🧪 Validação Realizada

Para garantir a qualidade e exatidão da especificação OpenAPI, executamos o validador oficial através do `swagger-cli`:
```bash
npx --yes swagger-cli validate docs/swagger/openapi.yaml
```

**Resultado:**
```text
docs/swagger/openapi.yaml is valid
```

A especificação passou com 100% de conformidade com os padrões OpenAPI 3.0.0.

---

## 🚀 Como Acessar e Testar

1. **Swagger UI Interativo**:
   Acesse a URL do projeto seguida de `/swagger` no seu navegador:
   `http://localhost/swagger` (ou a URL equivalente do seu servidor de desenvolvimento).

2. **Acesso Direto à Especificação**:
   Para baixar ou visualizar o arquivo bruto YAML:
   `http://localhost/docs/swagger/openapi.yaml`
