# 🏢 Documentação do Serviço REST - Cliente (`ClienteRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Cliente** é responsável pelo cadastro inicial do cliente parceiro no sistema, emissão da chave de acesso (`api_key`), consulta aos dados cadastrais e atualização de informações (incluindo URL de Webhook).

---

## 📌 Sumário das Rotas

| Verbo HTTP | Método     | Endpoint / URL | Requer API Key?    | Descrição          |
| :---       | :---       | :---           | :---               | :---               |
| `POST`     | `create`   | `/cliente`     | ❌ Não             | Cadastra novo cliente e envia a `api_key` por e-mail. |
| `GET`      | `getByKey` | `/cliente`     | ✅ Sim (`Header`)  | Retorna os dados do cliente cadastrado. |
| `PUT`      | `update`   | `/cliente`     | ✅ Sim (`Header`)  | Atualiza dados cadastrais do cliente.   |
| `GET`      | `getById`  | `/cliente/{id}`| ✅ Sim (`Header`)  | Consulta dados de um cliente pelo ID.   |

---

## 1. ➕ Cadastrar Cliente (`POST`)

Cria um novo cliente no sistema, cria o usuário de acesso ao painel e envia o e-mail de confirmação com a chave de API gerada.

> [!IMPORTANT]
> A chave de API (`api_key`) gerada é enviada com segurança para o e-mail cadastrado. Guarde sua chave com cuidado, pois ela autentica as demais operações de negócio.

### Requisição

- **URL**: `https://seu-dominio.com.br/cliente`
- **Verbo**: `POST`
- **Cabeçalhos**:
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "data": {
    "razao_social": "Tech Inovações LTDA",
    "cnpj": "12.345.678/0001-90",
    "nome_contato": "Carlos Eduardo",
    "email": "contato@techinovacoes.com.br",
    "telefone": "(11) 98765-4321",
    "webhook_url": "https://api.techinovacoes.com.br/webhooks/captura"
  }
}
```

### Parâmetros do Corpo (`data`)

| Campo          | Tipo     | Obrigatório?    | Descrição                                               |
| :---           | :---     | :---            | :---                                                    |
| `razao_social` | `string` | ✅ Sim          | Razão social completa da empresa/cliente.               |
| `cnpj`         | `string` | ✅ Sim          | CNPJ do cliente (com ou sem pontuação).                 |
| `nome_contato` | `string` | ✅ Sim          | Nome do responsável pelo contato.                       |
| `email`        | `string` | ✅ Sim          | E-mail principal de contato e login de acesso.          |
| `telefone`     | `string` | ❌ Não          | Telefone comercial para contato.                        |
| `webhook_url`  | `string` | ❌ Não          | URL HTTP(S) onde os eventos de Webhook serão entregues. |

---

### Resposta de Sucesso (`201 Created`)

```json
[]
```

### Resposta de Erro (`400 Bad Request`)

```json
{
  "message": "Todos os campos são obrigatórios."
}
```

---

## 2. 🔍 Consultar Dados do Cliente (`GET`)

Obtém as informações cadastrais do cliente associado à `api_key` enviada no cabeçalho.

### Requisição

- **URL**: `https://seu-dominio.com.br/cliente`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `Content-Type: application/json`
  - `Authorization: Basic <REST_KEY>`
  - `api_key: SUA_CHAVE_API_AQUI`

---

### Resposta de Sucesso (`200 OK`)

```json
{
  "cliente": {
    "system_user_id": "15",
    "razao_social": "Tech Inovações LTDA",
    "cnpj": "12345678000190",
    "nome_contato": "Carlos Eduardo",
    "email": "contato@techinovacoes.com.br",
    "telefone": "11987654321",
    "webhook_url": "https://api.techinovacoes.com.br/webhooks/captura",
    "data_cadastro": "2026-07-27 10:00:00"
  }
}
```

### Resposta de Erro (`403 Forbidden`)

```json
{
  "message": "Chave de API invalida."
}
```

---

## 3. ✏️ Atualizar Cliente (`PUT`)

Atualiza as informações cadastrais do cliente associado à `api_key`.

### Requisição

- **URL**: `https://seu-dominio.com.br/cliente`
- **Verbo**: `PUT`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Content-Type: application/json`
  - `Authorization: Basic <REST_KEY>`

### Body (JSON)

```json
{
  "data": {
    "nome_contato": "Carlos Eduardo Silva",
    "telefone": "(11) 99999-8888",
    "webhook_url": "https://nova-api.techinovacoes.com.br/webhooks"
  }
}
```

---

### Resposta de Sucesso (`200 OK`)

```json
{
  "cliente": {
    "system_user_id": "15",
    "razao_social": "Tech Inovações LTDA",
    "cnpj": "12345678000190",
    "nome_contato": "Carlos Eduardo Silva",
    "email": "contato@techinovacoes.com.br",
    "telefone": "11999998888",
    "webhook_url": "https://nova-api.techinovacoes.com.br/webhooks",
    "data_cadastro": "2026-07-27 10:00:00"
  }
}
```

---

## 💻 Exemplos de Código

### Exemplo em cURL (Cadastro de Cliente)

```bash
curl -X POST "https://seu-dominio.com.br/cliente" \
     -H "Content-Type: application/json" \
     -H "Authorization: Basic cmVzdF9rZXk6" \
     -d '{
           "data": {
             "razao_social": "Empresa Exemplo LTDA",
             "cnpj": "12345678000199",
             "nome_contato": "João da Silva",
             "email": "joao@exemplo.com.br",
             "telefone": "11999999999",
             "webhook_url": "https://exemplo.com.br/webhook"
           }
         }'
```

### Exemplo em JavaScript (Fetch - Consulta por API Key)

```javascript
async function buscarDadosCliente(apiKey) {
  const response = await fetch("https://seu-dominio.com.br/cliente", {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
      "Authorization": "Basic cmVzdF9rZXk6",
      "api_key": apiKey
    }
  });

  const result = await response.json();
  console.log(result);
}
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)
