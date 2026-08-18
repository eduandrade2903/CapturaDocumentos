# 📄 Documentação da API: Serviço de Coleta e Análise de Documentos (`DocumentoRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

## 📌 Visão Geral da Arquitetura

O módulo de coleta e validação de documentos atende ao fluxo de envio dos arquivos por parte do titular e validação em tempo real por Inteligência Artificial / OCR.

1. O backend gera uma `CollectionRequest` contendo um `token_unico` e associa múltiplos registros em `CollectionRequestDocumento` (tipos de documentos exigidos, ex: RG, CPF, CNH).
2. O Frontend consome os dados dessa requisição (`GET /documento?token_unico=...`) para renderizar dinamicamente os formulários de upload.
3. O Frontend pode enviar cada foto para validação prévia de IA via `POST /documentosAnalise`.
4. O titular submete os arquivos finais via `POST /documento` (como `multipart/form-data` ou Base64).
5. O Backend valida o token, grava os arquivos em disco/storage, vincula ao respectivo `tipo_documento_id`, salva opcionalmente dados de OCR em `extrated_data` e atualiza a situação da coleta.

---

## 🔒 Autenticação e Headers

Todas as requisições exigem os cabeçalhos de segurança:

| Header | Tipo | Descrição |
| :--- | :--- | :--- |
| `Authorization` | `string` | Chave de autorização REST Basic ou Token Bearer JWT. |
| `api_key` | `string` | Chave de segurança da API para validar o cliente parceiro. |

---

## 🛣️ Sumário de Rotas

### 1. 🔍 Obter Informações da Coleta (`GET /documento`)

Retorna o status da solicitação de documentos e a lista de tipos de documentos exigidos.

- **URL**: `https://seu-dominio.com.br/documento`
- **Método**: `GET`
- **Query Params**: `token_unico` (string, obrigatório)

#### Resposta de Sucesso (`200 OK`)

```json
{
  "collection_request_id": 18,
  "token_unico": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "status_request": "pendente",
  "data_expiracao": "2026-08-30 23:59:59",
  "documentos_exigidos": [
    {
      "tipo_documento_id": 1,
      "descricao": "RG / CIN",
      "status": "pendente"
    },
    {
      "tipo_documento_id": 4,
      "descricao": "CNH",
      "status": "pendente"
    }
  ]
}
```

---

### 2. 📤 Enviar Arquivos / Upload (`POST /documento`)

Recebe os arquivos enviados pelo usuário via `multipart/form-data` ou payload JSON com Base64.

- **URL**: `https://seu-dominio.com.br/documento`
- **Método**: `POST`
- **Content-Type**: `multipart/form-data` ou `application/json`

#### Formato Multipart (`multipart/form-data`)

| Campo | Tipo | Descrição |
| :--- | :--- | :--- |
| `token_unico` | `string` | Token único de identificação da `CollectionRequest`. |
| `extracted_data` | `string (JSON)` | *(Opcional)* JSON string com dados extraídos pelo OCR. |
| `documento[{tipo_documento_id}]` | `File` | Arquivo binário enviado. Ex: `documento[1]` para o arquivo do RG. |

#### Formato JSON Base64 (`application/json`)

```json
{
  "token_unico": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "files": [
    {
      "tipo_documento_id": 1,
      "file": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
    }
  ],
  "extracted_data": {
    "nome": "Maria Oliveira Souza",
    "cpf": "12345678901"
  }
}
```

#### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "message": "Documento enviados e salvos com sucesso.",
  "data": [
    {
      "id": 5,
      "collection_request_id": 18,
      "tipo_documento_id": 1,
      "storage_path": "tmp/doc_18_1_1691350800_64cf7a2b.jpg",
      "status": "APROVADO",
      "motivo_rejeicao": null,
      "data_upload": "2026-08-18 12:00:00"
    }
  ]
}
```

---

### 3. 🤖 Analisar Documento com IA (`POST /documentosAnalise`)

Valida se a foto enviada confere com o tipo esperado e extrai dados cadastrais via OCR inteligente.

- **URL**: `https://seu-dominio.com.br/documentosAnalise` (ou `https://seu-dominio.com.br/documento?ia=true`)
- **Método**: `POST`
- **Content-Type**: `multipart/form-data`

#### Campos do FormData

| Campo | Tipo | Descrição |
| :--- | :--- | :--- |
| `file` | `File` | Arquivo físico da imagem/PDF do documento. |
| `tipo_documento_id` | `integer` | ID do tipo de documento esperado (ex: `4` para CNH). |

#### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "message": "Documento validado com sucesso!",
  "dados_extraidos": {
    "nome": "MARIA OLIVEIRA SOUZA",
    "cpf": "123.456.789-01",
    "rg": "12.345.678-9",
    "data_nascimento": "1990-05-15"
  }
}
```

#### Resposta de Documento Incorreto (`422 Unprocessable Entity`)

```json
{
  "status": "fail",
  "message": "Documento Incorreto.",
  "motivo_rejeicao": "404"
}
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)