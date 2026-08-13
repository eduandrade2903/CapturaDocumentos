Documentação da API: Serviço de Coleta de Documentos (Onboarding)

📌 Visão Geral da Arquitetura O módulo de coleta de documentos baseia-se no fluxo de requisição gerado na criação do usuário.

O backend gera uma CollectionRequest contendo um token_unico e associa múltiplos registros em CollectionRequestDocumentos (tipos de documentos exigidos, ex: RG, CPF).

O Frontend consome os dados dessa requisição (via GET) para renderizar dinamicamente os inputs de upload.

O usuário realiza o upload e o Frontend processa e envia via POST (como multipart/form-data).

O Backend (Adianti) valida o token_unico, processa os arquivos para a AWS S3, salva os caminhos no banco de dados vinculando ao respectivo tipo_documento_id e atualiza o status.


🔒 Autenticação e Headers (Global)

Todas as requisições para o endpoint /documentos exigem os seguintes cabeçalhos para garantir a segurança da API:

Header              Tipo       Descrição   
Authorization       string     Token Bearer JWT ou chave de sessão do usuário/sistema.
api-key.            string.    Chave de segurança da API para validar a origem da aplicação cliente.


🛣️ Sumário de Rotas

1. GET /documentos

Objetivo: Retornar o status da solicitação de documentos e a lista de documentos (inputs) que o Frontend precisa renderizar para o usuário.

URL: [https://seu-dominio.com.br/documentos](https://seu-dominio.com.br/documentos)
Método: GET
Query Params: token_unico (string, obrigatório): O token gerado no momento da criação do usuário.

Resposta de Sucesso (200 OK):JSON{
  "status": "success",
  "data": {
    "request_id": 1054,
    "token_unico": "abc123xyz890",
    "status_request": "pendente",
    "data_expiracao": "2026-08-30T23:59:59",
    "documentos_exigidos": [
      {
        "tipo_documento_id": 1,
        "descricao": "Documento de Identidade (RG)",
        "status": "pendente"
      },
      {
        "tipo_documento_id": 2,
        "descricao": "Comprovante de Residência",
        "status": "pendente"
      }
    ]
  }
}

2. POST /documentos

Objetivo: Receber os arquivos enviados pelo usuário, vinculá-los aos seus respectivos tipos, salvar no S3 e atualizar a base de dados.

URL: [https://seu-dominio.com.br/documentos](https://seu-dominio.com.br/documentos)
Método: POST
Content-Type: multipart/form-data

Payload (FormData)
Como a requisição exige envio de arquivos físicos simultaneamente com dados textuais, o Frontend deve construir um objeto FormData.

Campo no FormData                       Tipo                Descrição
token_unico.                            string              Token de identificação da CollectionRequest.
extracted_data                          JSON String         Objeto ou array de strings com dados extraídos. Ex: 
                                                            {"nome": "ana", "cpf": "129873"} ou ["nome: ana", "cpf: 129873"]. O backend fará o json_decode.
documentos[{tipo_documento_id}]         File (Binário)      O arquivo físico em si. A chave do input deve conter o ID do tipo 
                                                            de documento. Exemplo: documentos[1] para o arquivo do RG.



Respostas de Erro Comuns:
400 Bad Request: Formato inválido ou ausência do token_unico.
404 Not Found: token_unico inválido, expirado ou não encontrado na tabela CollectionRequest.
422 Unprocessable Entity: Tipo de documento enviado não corresponde aos solicitados em CollectionRequestDocumentos.