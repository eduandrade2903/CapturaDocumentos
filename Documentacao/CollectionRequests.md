# 📋 Documentação do Serviço REST - Solicitações de Coleta (`CollectionRequestsRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Solicitações de Coleta** (`CollectionRequests`) gerencia o ciclo de vida dos pedidos de envio de documentos. Cada solicitação possui um token único seguro com validade de 24 horas, vinculando o usuário à empresa contratante.

---

## 📌 Sumário das Rotas

| Verbo HTTP | Método | Endpoint / URL | Requer API Key? | Descrição |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `create` | `/restp.php?class=CollectionRequestsRestService&method=handle` | ❌ Não | Cria manualmente uma solicitação de coleta e token temporário. |
| `GET` | `getById` / `loadAll` | `/restp.php?class=CollectionRequestsRestService&method=handle` | ❌ Não | Consulta solicitação por `id` ou lista todas. |
| `PUT` | `store` | `/restp.php?class=CollectionRequestsRestService&method=handle` | ❌ Não | Atualiza os dados de uma solicitação. |
| `DELETE` | `delete` / `deleteAll` | `/restp.php?class=CollectionRequestsRestService&method=handle` | ❌ Não | Exclui solicitação por `id` ou em lote. |

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

- **URL**: `https://seu-dominio.com.br/restp.php?class=CollectionRequestsRestService&method=handle`
- **Verbo**: `POST`
- **Cabeçalhos**:
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

| Campo | Tipo | Obrigatório? | Descrição |
| :--- | :--- | :--- | :--- |
| `empresa_id` | `integer` | ✅ Sim | ID da empresa cadastrada no sistema. |
| `usuario_id` | `integer` | ✅ Sim | ID do usuário cadastrado no sistema. |
| `status` | `string` | ❌ Não | Status inicial da solicitação (Padrão: `"pendente"`). |

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

- **URL**: `https://seu-dominio.com.br/restp.php?class=CollectionRequestsRestService&method=handle&id=18`
- **Verbo**: `GET`

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

---

## 3. 🗑️ Remover Solicitação (`DELETE`)

Exclui uma solicitação de coleta específica pelo ID.

- **URL**: `https://seu-dominio.com.br/restp.php?class=CollectionRequestsRestService&method=handle&id=18`
- **Verbo**: `DELETE`

---

## 💻 Exemplo de Código

### Exemplo em JavaScript (Node.js / Axios - Criar Coleta)

```javascript
const axios = require('axios');

async function criarSolicitacaoColeta() {
  try {
    const response = await axios.post('https://seu-dominio.com.br/restp.php?class=CollectionRequestsRestService&method=handle', {
      empresa_id: 1,
      usuario_id: 42,
      status: 'pendente'
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
