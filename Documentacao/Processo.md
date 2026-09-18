# 👤 Documentação do Serviço REST - Processo (`ProcessoRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Processo** permite o gerenciamento dos processos/titulares que necessitam ter seus documentos capturados. Ao criar um processo através da API, o sistema valida seus dados e **gera automaticamente uma Solicitação de Coleta (`CollectionRequest`)** e os registros em `CollectionRequestDocumento` com um token único para a jornada de captura.

---

## 📌 Sumário das Rotas

| Verbo HTTP  | Método                  | Endpoint / URL           | Requer API Key?   | Descrição                                                              |
| :---        | :---                    | :---                     | :---              | :---                                                                   |
| `POST`      | `create`                | `/processo`              | ✅ Sim (`Header`) | Cria um novo processo e gera solicitação de coleta automática.        |
| `POST`      | `createDadosProcesso`   | `/processo/dadosProcesso`| ✅ Sim (`Header`) | Cadastra dados pessoais e profissionais complementares do processo.    |
| `GET`       | `getById`/`loadAll`     | `/processo`              | ✅ Sim (`Header`) | Busca processo por `id` ou lista todos os processos com filtros.       |
| `PUT`       | `update`                | `/processo/{id}`         | ✅ Sim (`Header`) | Atualiza dados de um processo existente pelo `id`.                      |
| `DELETE`    | `delete`/`deleteAll`    | `/processo/{id}`         | ✅ Sim (`Header`) | Remove um processo por `id` ou todos em lote.                           |

---

## 1. ➕ Criar Processo (`POST`)

Cadastra um novo processo/titular para o cliente solicitante, informando a lista de `tipos_documentos` (IDs) e dispara a criação automática da solicitação de coleta.

> [!TIP]
> O token retornado em `token.token_unico` deve ser utilizado para construir a URL da jornada de captura de documentos entregue ao titular.

### Requisição

- **URL**: `https://seu-dominio.com.br/processo`
- **Verbo**: `POST`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "data": {
    "nome_completo": "Maria Oliveira Souza",
    "cpf": "12345678901",
    "email": "maria.souza@email.com",
    "telefone": "11988887777",
    "tipos_documentos": [1, 2, 4]
  }
}
```

### Parâmetros do Corpo (`data`)

| Campo              | Tipo     | Obrigatório?   | Descrição                                                                        |
| :---               | :---     | :---           | :---                                                                             |
| `nome_completo`    | `string` | ✅ Sim         | Nome completo do titular do processo.                                            |
| `cpf`              | `string` | ✅ Sim         | CPF do titular (deve ser único no sistema).                                      |
| `email`            | `string` | ✅ Sim         | E-mail do titular.                                                               |
| `telefone`         | `string` | ✅ Sim         | Telefone de contato do titular.                                                  |
| `tipos_documentos` | `array`  | ✅ Sim         | Array de IDs dos tipos de documentos requeridos (ex: `[1, 2, 4]`).               |

---

### Resposta de Sucesso (`201 Created`)

```json
{
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
```

### Resposta de Erro (`400 Bad Request`)

```json
{
  "status": "error",
  "data": "Erro ao criar usuário: CPF já cadastrado no sistema."
}
```

---

## 2. 📝 Cadastrar Dados Pessoais do Processo (`POST /processo/dadosProcesso`)

Cadastra os dados pessoais e complementares do processo (`DadosProcesso`), como CPF, RG, CTPS, dados bancários, endereço, filiação e características cadastrais.

> [!NOTE]
> Caracteres de pontuação em `cpf`, `rg`, `ctps`, `serie_uf`, `titulo` e `cep` são automaticamente higienizados e salvos contendo apenas números/letras.

### Requisição

- **URL**: `https://seu-dominio.com.br/processo/dadosProcesso`
- **Verbo**: `POST`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "dadosProcesso": {
    "processo_id": 1,
    "cpf": "123.456.789-00",
    "rg": "12.345.678-9",
    "ctps": "1234567",
    "serie_uf": "SP",
    "titulo": "123456789012",
    "data_nascimento": "1990-05-15",
    "nome_pai": "José da Silva",
    "nome_mae": "Maria da Silva",
    "grau_de_instrucao": "Superior Completo",
    "raca_cor": "Branca",
    "estado_civil": "Solteiro(a)",
    "nome_banco": "Banco do Brasil",
    "agencia_banco": "1234",
    "conta_banco": "56789-0",
    "cep": "01310-100",
    "rua": "Avenida Paulista",
    "bairro": "Bela Vista",
    "cidade": "São Paulo",
    "estado": "SP"
  }
}
```

### Parâmetros do Corpo (`dadosProcesso`)

| Campo               | Tipo      | Obrigatório? | Descrição |
| :---                | :---      | :---         | :--- |
| `processo_id`       | `integer` | ✅ Sim       | ID do processo ao qual os dados pertencem. |
| `cpf`               | `string`  | ✅ Sim       | CPF do titular (com ou sem pontuação). |
| `rg`                | `string`  | ❌ Não       | Documento de identidade (RG). |
| `ctps`              | `string`  | ❌ Não       | Carteira de trabalho e previdência social. |
| `serie_uf`          | `string`  | ❌ Não       | UF da série da carteira de trabalho (máx 2 caracteres, ex: `SP`). |
| `titulo`            | `string`  | ❌ Não       | Título de eleitor. |
| `data_nascimento`   | `string`  | ❌ Não       | Data de nascimento (`AAAA-MM-DD`). |
| `nome_pai`          | `string`  | ❌ Não       | Nome completo do pai. |
| `nome_mae`          | `string`  | ❌ Não       | Nome completo da mãe. |
| `grau_de_instrucao` | `string`  | ❌ Não       | Escolaridade (`Fundamental Incompleto`, `Médio Completo`, `Superior Completo`, etc.). |
| `raca_cor`          | `string`  | ❌ Não       | Cor/raça (`Branca`, `Preta`, `Parda`, `Amarela`, `Indígena`). |
| `estado_civil`      | `string`  | ❌ Não       | Estado civil (`Solteiro(a)`, `Casado(a)`, `Divorciado(a)`, `Viúvo(a)`, `União Estável`). |
| `nome_banco`        | `string`  | ❌ Não       | Nome da instituição bancária. |
| `agencia_banco`     | `string`  | ❌ Não       | Número da agência. |
| `conta_banco`       | `string`  | ❌ Não       | Número da conta com dígito. |
| `cep`               | `string`  | ❌ Não       | CEP residencial (com ou sem hífen). |
| `rua`               | `string`  | ❌ Não       | Logradouro / Rua. |
| `bairro`            | `string`  | ❌ Não       | Bairro. |
| `cidade`            | `string`  | ❌ Não       | Cidade. |
| `estado`            | `string`  | ❌ Não       | UF do estado (`SP`, `RJ`, etc. máx 2 caracteres). |

### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "data": {
    "status": "success",
    "message": "Dados do processo criados com sucesso",
    "data": {
      "id": 1,
      "processo_id": 1,
      "cpf": "12345678900",
      "rg": "123456789",
      "ctps": "1234567",
      "serie_uf": "SP",
      "titulo": "123456789012",
      "data_nascimento": "1990-05-15",
      "nome_pai": "José da Silva",
      "nome_mae": "Maria da Silva",
      "grau_de_instrucao": "Superior Completo",
      "raca_cor": "Branca",
      "estado_civil": "Solteiro(a)",
      "nome_banco": "Banco do Brasil",
      "agencia_banco": "1234",
      "conta_banco": "56789-0",
      "cep": "01310100",
      "rua": "Avenida Paulista",
      "bairro": "Bela Vista",
      "cidade": "São Paulo",
      "estado": "SP",
      "created_at": "2026-09-16 16:40:43",
      "created_by": null
    }
  }
}
```

---

## 3. 🔍 Consultar Processo (`GET`)

### Buscar por ID Específico

- **URL**: `https://seu-dominio.com.br/processo/{id}`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

#### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "id": 42,
    "nome_completo": "Maria Oliveira Souza",
    "cpf": "12345678901",
    "email": "maria.souza@email.com",
    "telefone": "11988887777"
  }
}
```

### Listar Todos os Processos

- **URL**: `https://seu-dominio.com.br/processo`
- **Verbo**: `GET`
- **Parâmetros de Consulta**: `offset`, `limit`, `order`, `direction`, `filters`

---

## 4. ✏️ Atualizar Processo (`PUT`)

Atualiza os dados de um processo existente pelo seu ID.

### Requisição

- **URL**: `https://seu-dominio.com.br/processo/{id}`
- **Verbo**: `PUT`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

### Body (JSON)

```json
{
  "data": {
    "nome_completo": "Maria Oliveira Silva",
    "email": "maria.silva@email.com",
    "telefone": "11977776666"
  }
}
```

---

## 5. 🗑️ Remover Processo (`DELETE`)

Exclui um processo do sistema.

### Requisição

- **URL**: `https://seu-dominio.com.br/processo/{id}`
- **Verbo**: `DELETE`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Authorization: Basic <REST_KEY>`
  - `Content-Type: application/json`

---

## 💻 Exemplo de Código

### Exemplo em PHP (Criar Processo + Obter Token de Coleta)

```php
<?php

$url = "https://seu-dominio.com.br/processo";
$apiKey = "SUA_CHAVE_API_AQUI";
$authorization = "Basic <REST_KEY>";

$payload = json_encode([
    "data" => [
        "nome_completo" => "Fernanda Costa",
        "cpf" => "98765432100",
        "email" => "fernanda@exemplo.com",
        "telefone" => "21988889999",
        "tipos_documentos" => [1, 2, 4]
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
