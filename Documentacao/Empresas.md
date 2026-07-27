# 🏢 Documentação do Serviço REST - Empresas (`EmpresasRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Empresas** é responsável pelo cadastro inicial da empresa parceira no sistema, emissão da chave de acesso (`api_key`), consulta aos dados cadastrais e atualização de informações (incluindo URL de Webhook).

---

## 📌 Sumário das Rotas

| Verbo HTTP | Método | Endpoint / URL | Requer API Key? | Descrição |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `create` | `/restp.php?class=EmpresasRestService&method=handle` | ❌ Não | Cadastra nova empresa e gera a `api_key`. |
| `GET` | `getByKey` | `/restp.php?class=EmpresasRestService&method=handle` | ✅ Sim (`Header`) | Retorna os dados da empresa cadastrada. |
| `PUT` | `update` | `/restp.php?class=EmpresasRestService&method=handle` | ✅ Sim (`Header`) | Atualiza dados cadastrais da empresa. |

---

## 1. ➕ Cadastrar Empresa (`POST`)

Cria uma nova empresa no sistema, cria o usuário de acesso ao painel e envia o e-mail de confirmação.

> [!IMPORTANT]
> A chave de API (`api_key`) será retornada **apenas uma vez** na resposta do cadastro em texto claro. Certifique-se de salvá-la em local seguro.

### Requisição

- **URL**: `https://seu-dominio.com.br/restp.php?class=EmpresasRestService&method=handle`
- **Verbo**: `POST`
- **Cabeçalhos**:
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

| Campo | Tipo | Obrigatório? | Descrição |
| :--- | :--- | :--- | :--- |
| `razao_social` | `string` | ✅ Sim | Razão social completa da empresa. |
| `cnpj` | `string` | ✅ Sim | CNPJ da empresa (com ou sem pontuação). |
| `nome_contato` | `string` | ✅ Sim | Nome do responsável pelo contato. |
| `email` | `string` | ✅ Sim | E-mail principal de contato e login de acesso. |
| `telefone` | `string` | ❌ Não | Telefone comercial para contato. |
| `webhook_url` | `string` | ❌ Não | URL HTTP(S) onde os eventos de Webhook serão entregues. |

---

### Resposta de Sucesso (`201 Created`)

```json
{
  "status": "success",
  "data": {
    "message": "Empresa criada com sucesso",
    "api_key": "4f9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9b"
  }
}
```

### Resposta de Erro (`400 Bad Request`)

```json
{
  "status": "error",
  "data": {
    "message": "Todos os campos são obrigatórios."
  }
}
```

---

## 2. 🔍 Consultar Dados da Empresa (`GET`)

Obtém as informações cadastrais da empresa associada à `api_key` enviada no cabeçalho.

### Requisição

- **URL**: `https://seu-dominio.com.br/restp.php?class=EmpresasRestService&method=handle`
- **Verbo**: `GET`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`

---

### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "empresa": {
      "system_user_id": "15",
      "razao_social": "Tech Inovações LTDA",
      "cnpj": "12.345.678/0001-90",
      "nome_contato": "Carlos Eduardo",
      "email": "contato@techinovacoes.com.br",
      "telefone": "(11) 98765-4321",
      "webhook_url": "https://api.techinovacoes.com.br/webhooks/captura",
      "data_cadastro": "2026-07-27 10:00:00"
    }
  }
}
```

### Resposta de Erro (`403 Forbidden`)

```json
{
  "status": "error",
  "data": {
    "message": "Chave de API invalida."
  }
}
```

---

## 3. ✏️ Atualizar Empresa (`PUT`)

Atualiza as informações cadastrais da empresa associada à `api_key`.

### Requisição

- **URL**: `https://seu-dominio.com.br/restp.php?class=EmpresasRestService&method=handle`
- **Verbo**: `PUT`
- **Cabeçalhos**:
  - `api_key: SUA_CHAVE_API_AQUI`
  - `Content-Type: application/json`

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
  "status": "success",
  "data": {
    "empresa": {
      "system_user_id": "15",
      "razao_social": "Tech Inovações LTDA",
      "cnpj": "12.345.678/0001-90",
      "nome_contato": "Carlos Eduardo Silva",
      "email": "contato@techinovacoes.com.br",
      "telefone": "(11) 99999-8888",
      "webhook_url": "https://nova-api.techinovacoes.com.br/webhooks",
      "data_cadastro": "2026-07-27 10:00:00"
    }
  }
}
```

---

## 💻 Exemplos de Código

### Exemplo em cURL (Cadastro de Empresa)

```bash
curl -X POST "https://seu-dominio.com.br/restp.php?class=EmpresasRestService&method=handle" \
     -H "Content-Type: application/json" \
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
async function buscarDadosEmpresa(apiKey) {
  const response = await fetch("https://seu-dominio.com.br/restp.php?class=EmpresasRestService&method=handle", {
    method: "GET",
    headers: {
      "api_key": apiKey
    }
  });

  const result = await response.json();
  console.log(result);
}
```

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)
