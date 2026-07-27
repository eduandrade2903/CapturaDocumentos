# 🔔 Documentação do Serviço REST - Eventos de Webhook (`WebhookEventsRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Eventos de Webhook** (`WebhookEvents`) permite que as empresas parceiras consultem o histórico e o status de entrega dos eventos assíncronos disparados pelo sistema (ex: conclusão de captura de documentos, falhas ou atualizações de solicitações).

---

## 📌 Sumário das Rotas

| Verbo HTTP | Método | Endpoint / URL | Requer API Key? | Descrição |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `loadWebhookEvents` | `/restp.php?class=WebhookEventsRestService&method=handle` | ✅ Sim (`Header`) | Lista os eventos de Webhook associados à empresa autenticada. |

---

## 🔍 Consultar Eventos de Webhook (`GET`)

Retorna a lista completa de eventos disparados para a URL de Webhook cadastrada pela sua empresa.

### Requisição

- **URL**: `https://seu-dominio.com.br/restp.php?class=WebhookEventsRestService&method=handle`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`

---

### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "data": [
      {
        "id": "101",
        "empresa_id": "1",
        "tipo_evento": "document.uploaded",
        "payload": "{\"solicitacao_id\": 18, \"usuario_id\": 42, \"documento\": \"cnh.pdf\", \"status\": \"sucesso\"}",
        "status_envio": "sucesso",
        "tentativas": "1",
        "criado_em": "2026-07-27 12:00:00"
      },
      {
        "id": "102",
        "empresa_id": "1",
        "tipo_evento": "collection.expired",
        "payload": "{\"solicitacao_id\": 15, \"motivo\": \"tempo_limite_excedido\"}",
        "status_envio": "falha",
        "tentativas": "3",
        "criado_em": "2026-07-27 12:05:00"
      }
    ]
  }
}
```

### Campos do Objeto de Evento

| Campo | Tipo | Descrição |
| :--- | :--- | :--- |
| `id` | `string` / `integer` | Identificador único do evento de webhook. |
| `empresa_id` | `string` / `integer` | ID da empresa destinatária. |
| `tipo_evento` | `string` | Categoria/tipo do evento disparado (ex: `document.uploaded`). |
| `payload` | `string` (JSON) | Dados brutos enviados no corpo da requisição HTTP do Webhook. |
| `status_envio` | `string` | Status do envio (`sucesso`, `falha`, `pendente`). |
| `tentativas` | `string` / `integer` | Quantidade de tentativas de entrega efetuadas pelo servidor. |
| `criado_em` | `string` | Data e hora em que o evento foi registrado (`YYYY-MM-DD HH:MM:SS`). |

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

url = "https://seu-dominio.com.br/restp.php?class=WebhookEventsRestService&method=handle"
headers = {
    "api_key": "SUA_CHAVE_API_AQUI"
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
