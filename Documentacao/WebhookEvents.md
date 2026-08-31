# 🔔 Documentação do Serviço REST - Eventos de Webhook (`WebhookEventRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Eventos de Webhook** (`WebhookEvent`) permite que os clientes parceiros consultem o histórico e o status de entrega dos eventos assíncronos disparados pelo sistema (ex: conclusão de captura de documentos, falhas ou expirações).

---

## 📌 Sumário das Rotas

| Verbo HTTP  | Método              | Endpoint / URL    | Requer API Key?   | Descrição                              |
| :---        | :---                | :---              | :---              | :---                                   |
| `GET`       | `loadWebhookEvent`  | `/webhook-event`  | ✅ Sim (`Header`) | Lista os eventos de Webhook associados ao cliente autenticado. |

---

## 🔍 Consultar Eventos de Webhook (`GET /webhook-event`)

Retorna a lista completa de eventos disparados para a URL de Webhook cadastrada pelo cliente.

### Requisição

- **URL**: `https://seu-dominio.com.br/webhook-event`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

---

### Resposta de Sucesso (`200 OK`)

```json
{
  "data": [
    {
      "id": "101",
      "cliente_id": "1",
      "tipo_evento": "document.uploaded",
      "payload": "{\"solicitacao_id\": 18, \"processo_id\": 42, \"documento\": \"cnh.pdf\", \"status\": \"sucesso\"}",
      "status_envio": "sucesso",
      "tentativas": "1",
      "criado_em": "2026-07-27 12:00:00"
    },
    {
      "id": "102",
      "cliente_id": "1",
      "tipo_evento": "collection.expired",
      "payload": "{\"solicitacao_id\": 15, \"motivo\": \"tempo_limite_excedido\"}",
      "status_envio": "falha",
      "tentativas": "3",
      "criado_em": "2026-07-27 12:05:00"
    }
  ]
}
```

### Campos do Objeto de Evento

| Campo           | Tipo                  | Descrição                                                           |
| :---            | :---                  | :---                                                                |
| `id`            | `string` / `integer`  | Identificador único do evento de webhook.                           |
| `cliente_id`    | `string` / `integer`  | ID do cliente destinatário.                                         |
| `tipo_evento`   | `string`              | Categoria/tipo do evento disparado (ex: `document.uploaded`).       |
| `payload`       | `string` (JSON)       | Dados brutos enviados no corpo da requisição HTTP do Webhook.       |
| `status_envio`  | `string`              | Status do envio (`sucesso`, `falha`, `pendente`).                   |
| `tentativas`    | `string` / `integer`  | Quantidade de tentativas de entrega efetuadas pelo servidor.        |
| `criado_em`     | `string`              | Data e hora em que o evento foi registrado (`YYYY-MM-DD HH:MM:SS`). |

---

### Respostas de Erro

#### 1. Chave de API Inválida (`403 Forbidden`)

```json
{
  "status": "error",
  "data": "Chave de API invalida."
}
```

#### 2. Nenhum Evento Encontrado (`404 Not Found`)

```json
{
  "status": "error",
  "data": "Nenhum webhook encontrado."
}
```

---

## 💻 Exemplo de Código

### Exemplo em Python (Consultar Eventos de Webhook)

```python
import requests

url = "https://seu-dominio.com.br/webhook-event"
headers = {
    "api_key": "SUA_CHAVE_API_AQUI",
    "Authorization": "Basic cmVzdF9rZXk6",
    "Content-Type": "application/json"
}

response = requests.get(url, headers=headers)

if response.status_code == 200:
    events = response.json()
    print("Eventos recebidos:", events)
else:
    print(f"Erro {response.status_code}:", response.json())
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)
