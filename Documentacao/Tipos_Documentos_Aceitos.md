# 📑 Catálogo e Serviço REST - Tipos de Documentos Aceitos (`TiposDocumentosRestService`)

[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)

O serviço **Tipos de Documentos** permite consultar e cadastrar os tipos de documentos aceitos pelo sistema de captura.

---

## 📌 Sumário das Rotas REST

| Verbo HTTP | Método               | Endpoint / URL       | Requer API Key?   | Descrição |
| :---       | :---                 | :---                 | :---              | :---      |
| `GET`      | `getTipoDocumentos`  | `/tiposDocumentos`   | ✅ Sim (`Header`) | Lista todos os tipos de documentos ativos no sistema. |
| `POST`     | `create`             | `/tiposDocumentos`   | ✅ Sim (`Header`) | Cadastra um novo tipo de documento aceito. |

---

## 🔍 Consultar Tipos de Documentos (`GET /tiposDocumentos`)

### Resposta de Sucesso (`200 OK`)

```json
[
  {
    "id": 1,
    "nome": "RG / CIN",
    "descricao": "Registro Geral RG ou Carteira de Identidade Nacional CIN."
  },
  {
    "id": 2,
    "nome": "CPF",
    "descricao": "Cadastro de Pessoas Físicas ou comprovante de situação cadastral."
  },
  {
    "id": 4,
    "nome": "CNH",
    "descricao": "Carteira Nacional de Habilitação."
  }
]
```

---

## ➕ Cadastrar Novo Tipo de Documento (`POST /tiposDocumentos`)

### Body (JSON)

```json
{
  "nome": "COMPROVANTE DE RENDA",
  "descricao": "Holerite, extrato bancário ou declaração de IRPF recente."
}
```

### Resposta de Sucesso (`200 OK`)

```json
{
  "status": "success",
  "message": "Tipo de documento cadastrado com sucesso."
}
```

---

## 📋 Catálogo Padrão de Tipos Suportados

Abaixo a relação completa dos tipos de documentos cadastrados por padrão no sistema:

| ID | Nome | Descrição |
| :--- | :--- | :--- |
| 1 | RG / CIN | Registro Geral RG ou Carteira de Identidade Nacional CIN. |
| 2 | CPF | Cadastro de Pessoas Físicas ou comprovante de situação cadastral. |
| 3 | CERTIDAO DE NASCIMENTO | Certidão de nascimento. |
| 4 | CNH | Carteira Nacional de Habilitação. |
| 5 | CARTEIRA DE TRABALHO | Carteira de Trabalho e Previdência Social CTPS, física ou digital. |
| 6 | COMPROVANTE DE ENDERECO | Comprovante de residência ou endereço atualizado. |
| 7 | HISTORICO ESCOLAR - ENSINO MEDIO | Histórico escolar referente ao ensino médio. |
| 8 | HISTORICO ESCOLAR - ENSINO FUNDAMENTAL | Histórico escolar referente ao ensino fundamental. |
| 9 | RESERVISTA OU CERTIFICADO DE DISPENSA | Certificado de reservista, certificado de dispensa de incorporação ou equivalente. |
| 10 | CERTIDAO DE CASAMENTO | Certidão de casamento, incluindo averbação de divórcio quando aplicável. |
| 11 | CERTIDAO DE OBITO | Certidão de óbito, quando necessária para fins cadastrais. |
| 12 | TITULO DE ELEITOR | Título de eleitor ou documento equivalente. |
| 13 | CERTIDAO DE QUITACAO ELEITORAL | Certidão de quitação eleitoral. |
| 14 | PIS PASEP NIT | Comprovante contendo o número do PIS, PASEP ou NIT. |
| 15 | COMPROVANTE DE ESCOLARIDADE | Diploma, certificado de conclusão ou outro comprovante de escolaridade. |
| 16 | COMPROVANTE DE CONTA BANCARIA | Comprovante de conta bancária ou conta salário de titularidade do candidato. |
| 17 | CERTIDAO DE UNIAO ESTAVEL | Documento comprobatório de união estável, quando aplicável. |
| 18 | CERTIDAO DE NASCIMENTO DE DEPENDENTE | Certidão de nascimento de filho ou dependente. |
| 19 | CPF DE DEPENDENTE | Comprovante de CPF de filho ou dependente. |
| 20 | DOCUMENTO DE IDENTIFICACAO DO DEPENDENTE | Documento de identificação de filho ou dependente. |
| 21 | CURRICULO | Currículo profissional atualizado. |
| 22 | REGISTRO PROFISSIONAL | Registro ou carteira do conselho profissional competente (ex: OAB, CRM, CREA). |
| 23 | CERTIDAO DE ANTECEDENTES CRIMINAIS | Certidão de antecedentes criminais. |
| 24 | EXAME ADMISSIONAL | Atestado de Saúde Ocupacional (ASO) / Exame Admissional. |
| 25 | COMPROVANTE DE EXPERIENCIA PROFISSIONAL | Documento comprobatório de experiência profissional anterior. |
| 26 | PASSAPORTE | Passaporte para identificação de nacionais ou estrangeiros. |
| 27 | DOCUMENTO DE IDENTIFICACAO DE ESTRANGEIRO | RNM, RNE ou documento equivalente. |
| 28 | CERTIFICADO DE CURSO | Certificado de conclusão de curso ou capacitação profissional. |
| 29 | DIPLOMA | Diploma de formação acadêmica ou profissional. |
| 30 | FOTO 3X4 | Fotografia 3x4 recente. |

---
[⬅️ Voltar ao Índice Principal](file:///Users/edutronicos/Projetos/capturaDocumentos/Documentacao/README.md)