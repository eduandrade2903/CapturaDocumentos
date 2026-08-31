# 📋 Documentação do Serviço REST - Solicitações de Coleta (`CollectionRequestRestService` e `CollectionRequestDocumentoRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Solicitações de Coleta** (`CollectionRequest`) gerencia o ciclo de vida dos pedidos de envio de documentos. Cada solicitação possui um token único seguro com validade de 24 horas, vinculando o processo/titular ao cliente contratante.

---

## 📌 Sumário das Rotas

| Verbo HTTP  | Método               | Endpoint / URL               | Requer API Key?   | Descrição |
| :---        | :---                 | :---                         | :---              | :---      |
| `POST`      | `create`             | `/collection`                | ✅ Sim (`Header`) | Cria manualmente uma solicitação de coleta e token temporário. |
| `GET`       | `loadAll`            | `/collection`                | ✅ Sim (`Header`) | Consulta lista de solicitações com filtros para o cliente logado. |
| `GET`       | `getById`            | `/collection/{id}`           | ✅ Sim (`Header`) | Consulta solicitação por `id`. |
| `GET`       | `getByTokenUnico`    | `/collection/{token_unico}`  | ✅ Sim (`Header`) | Consulta jornada completa por token único (dados do processo, cliente e lista de documentos). |
| `PUT`       | `store`              | `/collection`                | ✅ Sim (`Header`) | Atualiza os dados de uma solicitação. |
| `DELETE`    | `delete`/`deleteAll` | `/collection/{id}`           | ✅ Sim (`Header`) | Exclui solicitação por `id` ou em lote. |
| `GET`       | `getCollection...`   | `/collectionDocumento`       | ✅ Sim (`Header`) | Consulta os tipos de documentos exigidos por `tipos_documentos={id}` ou `token={token_unico}`. |
| `POST`      | `createCollection...`| `/collectionDocumento`       | ✅ Sim (`Header`) | Vincula um novo tipo de documento a uma solicitação existente. |

---

## ⏳ Validade e Token Temporário

Cada solicitação gerada possui o seguinte comportamento:
- **Status Inicial**: `pendente`
- **Validade do Token**: 1 dia (24 horas a partir de `data_criacao`).
- **Função do Token**: Identificar e autorizar a jornada do titular durante o upload de fotos/documentos.

---

## 1. ➕ Criar Solicitação de Coleta Manual (`POST /collection`)

Gera uma nova solicitação de coleta especificando o `cliente_id` e o `processo_id`.

### Requisição

- **URL**: `https://seu-dominio.com.br/collection`
- **Verbo**: `POST`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "cliente_id": 1,
  "processo_id": 42,
  "status": "pendente"
}
```

### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "message": "Solicitação de coleta criada com sucesso",
  "data": {
    "id": 18,
    "cliente_id": 1,
    "processo_id": 42,
    "token_unico": "b8a97c6d5e4f3a2b1c0d9e8f7a6b5c4d",
    "status": "pendente",
    "data_criacao": "2026-07-27 11:35:00",
    "data_expiracao": "2026-07-28 11:35:00"
  }
}
```

---

## 2. 🔍 Consultar Solicitação por Token Único (`GET /collection/{token_unico}`)

Retorna todas as informações necessárias para renderizar a tela de captura para o usuário final.

### Requisição

- **URL**: `https://seu-dominio.com.br/collection/e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`

### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "id": 18,
    "status": "pendente",
    "data_criacao": "2026-07-27 11:35:00",
    "data_expiracao": "2026-07-28 11:35:00",
    "processo": "Maria Oliveira Souza",
    "cliente": "Tech Inovações LTDA",
    "documentos_solicitados": [
      {
        "id": 10,
        "collection_request_id": 18,
        "tipo_documento_id": 2,
        "status": "pendente",
        "tipo_documento": {
          "id": 2,
          "nome": "CPF",
          "descricao": "Cadastro de Pessoas Físicas ou comprovante de situação cadastral."
        }
      }
    ],
    "documentos_enviados": "Nenhum documento enviado."
  }
}
```

---

## 3. 📄 Documentos da Coleta (`/collectionDocumento`)

### Consultar Documentos Requeridos (`GET`)

- **URL**: `https://seu-dominio.com.br/collectionDocumento?token=e3b0c442...` ou `https://seu-dominio.com.br/collectionDocumento?tipos_documentos=18`
- **Verbo**: `GET`

### Vincular Novo Documento à Solicitação (`POST`)

- **URL**: `https://seu-dominio.com.br/collectionDocumento`
- **Verbo**: `POST`
- **Body**:

```json
{
  "data": {
    "collection_request_id": 18,
    "tipo_documento_id": 1
  }
}
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)
