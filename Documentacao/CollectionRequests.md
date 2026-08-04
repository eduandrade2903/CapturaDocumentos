# 📋 Documentação do Serviço REST - Solicitações de Coleta (`CollectionRequestsRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Solicitações de Coleta** (`CollectionRequests`) gerencia o ciclo de vida dos pedidos de envio de documentos. Cada solicitação possui um token único seguro com validade de 24 horas, vinculando o usuário à empresa contratante.

---

## 📌 Sumário das Rotas

| Verbo HTTP  | Método               | Endpoint / URL               | Requer API Key?   | Descrição |
| :---        | :---                 | :---                         | :---              | :---      |
| `POST`      | `create`             | `/collections`               | ✅ Sim (`Header`) | Cria manualmente uma solicitação de coleta e token temporário. |
| `GET`       | `getById`/`loadAll`  | `/collections/{id}`          | ✅ Sim (`Header`) | Consulta solicitação por `id` ou lista todas. |
| `GET`       | `getByTokenUnico`    | `/collections/{token_unico}` | ✅ Sim (`Header`) | Consulta solicitação por token único de jornada e lista seus documentos. |
| `PUT`       | `store`              | `/collections/{id}`          | ✅ Sim (`Header`) | Atualiza os dados de uma solicitação. |
| `DELETE`    | `delete`/`deleteAll` | `/collections/{id}`          | ✅ Sim (`Header`) | Exclui solicitação por `id` ou em lote. |

---

## ⏳ Validade e Token Temporário

Cada solicitação gerada possui o seguinte comportamento:
- **Status Inicial**: `pendente`
- **Validade do Token**: 1 dia (24 horas a partir de `data_criacao`).
- **Função do Token**: Autenticar e autorizar a página do cliente final durante o upload de fotos/documentos.

---

## 1. ➕ Criar Solicitação de Coleta (`POST`)

Gera uma nova solicitação de coleta especificando a empresa e o usuário.

### Requisição

- **URL**: `https://seu-dominio.com.br/collections`
- **Verbo**: `POST`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Bas.. 76ast`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "empresa_id": 1,
  "usuario_id": 42,
  "status": "pendente"
}
```

### Parâmetros do Corpo

| Campo          | Tipo      | Obrigatório?     | Descrição              |
| :---           | :---      | :---             | :---                   |
| `empresa_id`   | `integer` | ✅ Sim           | ID da empresa cadastrada no sistema. |
| `usuario_id`   | `integer` | ✅ Sim           | ID do usuário cadastrado no sistema. |
| `status`       | `string`  | ❌ Não           | Status inicial da solicitação (Padrão: `"pendente"`). |

---

### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "data": {
    "status": "success",
    "message": "Solicitação de coleta criada com sucesso",
    "data": {
      "id": 18,
      "empresa_id": 1,
      "usuario_id": 42,
      "token_unico": "b8a97c6d5e4f3a2b1c0d9e8f7a6b5c4d",
      "status": "pendente",
      "data_criacao": "2026-07-27 11:35:00",
      "data_expiracao": "2026-07-28 11:35:00"
    }
  }
}
```

### Resposta de Erro (`400 Bad Request`)

```json
{
  "status": "error",
  "data": "Erro ao criar solicitação de coleta: Mensagem de detalhe da exceção"
}
```

---

## 2. 🔍 Consultar Solicitação de Coleta (`GET`)

### Consultar Por ID

- **URL**: `https://seu-dominio.com.br/collections/{id}`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Bas.. 76ast`
  - `Content-Type: application/json`

#### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "status": "success",
    "data": {
      "id": "18",
      "empresa_id": "1",
      "usuario_id": "42",
      "token_unico": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
      "status": "pendente",
      "data_criacao": "2026-07-27 11:35:00",
      "data_expiracao": "2026-07-28 11:35:00"
    }
  }
}
```

### Consultar Por Token Único

- **URL**: `https://seu-dominio.com.br/collections/{token_unico}`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic REST_KEY`
  - `Content-Type: application/json`

#### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "id": 18,
    "empresa_id": 1,
    "usuario_id": 42,
    "token_unico": "e3b0c442-98fc-1c14-9afb-f4c8996fb924",
    "status": "pendente",
    "data_criacao": "2026-07-27 11:35:00",
    "data_expiracao": "2026-07-28 11:35:00",
    "documentos": [
      {
        "id": 10,
        "request_id": 18,
        "tipo_documento_id": 2,
        "status": "pendente",
        "data_criacao": "2026-07-27 11:35:00"
      }
    ]
  }
}
```

#### Resposta de Erro (`400 Bad Request`)

```json
{
  "status": "error",
  "message": "Token não encontrado."
}
```

---

## 3. 🗑️ Remover Solicitação (`DELETE`)

Exclui uma solicitação de coleta específica pelo ID.

- **URL**: `https://seu-dominio.com.br/collections/{id}`
- **Verbo**: `DELETE`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Bas.. 76ast`
  - `Content-Type: application/json`

---

## 💻 Exemplo de Código

### Exemplo em JavaScript (Node.js / Axios - Criar Coleta)

```javascript
const axios = require('axios');

async function criarSolicitacaoColeta() {
  try {
    const response = await axios.post('https://seu-dominio.com.br/collections', {
      empresa_id: 1,
      usuario_id: 42,
      status: 'pendente',
      headers: {
        'api_key': 'SUA_CHAVE_API_AQUI',
        'Authorization': 'Bas.. 76ast'
      }
    });

    console.log('Coleta Criada:', response.data);
  } catch (error) {
    console.error('Erro ao criar coleta:', error.response ? error.response.data : error.message);
  }
}

criarSolicitacaoColeta();
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)
