# 👤 Documentação do Serviço REST - Usuários (`UsuariosRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Usuários** permite o gerenciamento de clientes/titulares que necessitam ter seus documentos capturados. Ao criar um usuário através da API, o sistema valida seus dados e **gera automaticamente uma Solicitação de Coleta (`CollectionRequest`)** atrelada à empresa com um token único válido por 24h.

---

## 📌 Sumário das Rotas

| Verbo HTTP  | Método                  | Endpoint / URL      | Requer API Key?   | Descrição                                                       |
| :---        | :---                    | :---                | :---              | :---                                                            |
| `POST`      | `create`                | `/usuarios`         | ✅ Sim (`Header`) | Cria um novo usuário e gera solicitação de coleta automática.  |
| `GET`       | `getById`/`loadAll`     | `/usuarios`         | ✅ Sim (`Header`) | Busca usuário por `id` ou lista todos os usuários.              |
| `PUT`       | `update`                | `/usuarios`         | ✅ Sim (`Header`) | Atualiza dados de um usuário existente pelo `id`.               |
| `DELETE`    | `delete`/`deleteAll`    | `/usuarios`         | ✅ Sim (`Header`) | Remove um usuário por `id` ou todos em lote.                    |

---

## 1. ➕ Criar Usuário (`POST`)

Cadastra um novo usuário para a empresa solicitante, enviando `tipos_documentos` na requisição e dispara a criação da solicitação de coleta.

> [!TIP]
> O token único retornado em `token.token_unico` deve ser utilizado para redirecionar o usuário ou construir o link da jornada de captura de documentos.

### Requisição

- **URL**: `https://seu-dominio.com.br/usuarios`
- **Verbo**: `POST`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Bas.. 76ast`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "data": {
    "nome_completo": "Maria Oliveira Souza",
    "cpf": "12345678901",
    "email": "maria.souza@email.com",
    "telefone": "11988887777",
    "tipos_documentos": ["RG", "CNH"]
  }
}
```

### Parâmetros do Corpo (`data`)

| Campo              | Tipo     | Obrigatório?   | Descrição                                                                        |
| :---               | :---     | :---           | :---                                                                             |
| `nome_completo`    | `string` | ✅ Sim         | Nome completo do usuário.                                                        |
| `cpf`              | `string` | ✅ Sim         | CPF do usuário (deve ser único no sistema).                                      |
| `email`            | `string` | ✅ Sim         | E-mail do usuário.                                                               |
| `telefone`         | `string` | ✅ Sim         | Telefone com DDD do usuário.                                                     |
| `tipos_documentos` | `array`  | ✅ Sim         | Array de tipos de documentos a serem capturados. Ex: ["RG", "CNH", "Passaporte"] |

---

### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "data": {
    "status": "success",
    "message": "Usuário criado com sucesso",
    "data": {
      "id": 42,
      "nome_completo": "Maria Oliveira Souza",
      "cpf": "12345678901",
      "email": "maria.souza@email.com",
      "telefone": "11988887777"
    },
    "token": {
      "token_unico": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
    }
  }
}
```

### Resposta de Erro (`400 Bad Request`)

Ocorre quando o CPF já está cadastrado ou campos obrigatórios faltam:

```json
{
  "status": "error",
  "data": "Erro ao criar usuário: CPF já cadastrado no sistema."
}
```

---

## 2. 🔍 Consultar Usuário (`GET`)

### Buscar por ID Específico

- **URL**: `https://seu-dominio.com.br/usuarios/{id}`
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
      "id": "42",
      "nome_completo": "Maria Oliveira Souza",
      "cpf": "12345678901",
      "email": "maria.souza@email.com",
      "telefone": "11988887777"
    }
  }
}
```

### Listar Todos os Usuários

- **URL**: `https://seu-dominio.com.br/usuarios`
- **Verbo**: `GET`

---

## 3. ✏️ Atualizar Usuário (`PUT`)

Atualiza os dados de um usuário pelo seu ID.

### Requisição

- **URL**: `https://seu-dominio.com.br/usuarios/{id}`
- **Verbo**: `PUT`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Bas.. 76ast`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "id": 42,
  "data": {
    "nome_completo": "Maria Oliveira Silva",
    "email": "maria.silva@email.com",
    "telefone": "11977776666"
  }
}
```

---

## 4. 🗑️ Remover Usuário (`DELETE`)

Exclui um usuário do sistema.

### Requisição

- **URL**: `https://seu-dominio.com.br/usuarios/{id}`
- **Verbo**: `DELETE`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Bas.. 76ast`
  - `Content-Type: application/json`

---

## 💻 Exemplo de Código

### Exemplo em PHP (Criar Usuário + Obter Token de Coleta)

```php
<?php

$url = "https://seu-dominio.com.br/usuarios";
$apiKey = "SUA_CHAVE_API_AQUI";
$authorization = "Bas.. 76ast";

$payload = json_encode([
    "data" => [
        "nome_completo" => "Fernanda Costa",
        "cpf" => "98765432100",
        "email" => "fernanda@exemplo.com",
        "telefone" => "21988889999"
    ]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "api_key: " . $apiKey,
    "Authorization: " . $authorization
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)
