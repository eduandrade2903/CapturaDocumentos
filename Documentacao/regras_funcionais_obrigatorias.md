## OBJETIVO PRINCIPAL

Unificar o processo para que uma única requisição à rota:

```http
POST /documento
```

seja responsável por executar todo o fluxo:

1. autenticar o cliente;
2. validar a `CollectionRequest`;
3. verificar se o documento solicitado pertence àquela solicitação;
4. receber o arquivo via `multipart/form-data`;
5. analisar o conteúdo do documento utilizando a IA;
6. verificar se o documento analisado corresponde ao `tipo_documento_id` solicitado;
7. somente se a análise for válida, salvar o arquivo no S3;
8. persistir/atualizar o registro no banco;
9. atualizar o status do documento solicitado;
10. atualizar o status da `CollectionRequest` quando aplicável;
11. retornar a resposta da API;
12. independentemente do resultado, remover o arquivo temporário da pasta `tmp`.

A rota antiga de análise separada não deve mais ser necessária para realizar o fluxo de upload.

---

# REGRAS FUNCIONAIS OBRIGATÓRIAS

## 1. UMA ÚNICA ROTA PARA O PROCESSO COMPLETO

A implementação deve concentrar o fluxo de análise + validação + salvamento em:

```http
POST /documento
```

Não deve existir a necessidade de:

```http
POST /documentosAnalise
```

seguido de:

```http
POST /documento
```

O `POST /documento` deve fazer tudo em uma única requisição.

A análise deve acontecer antes do upload definitivo para o S3.

Fluxo esperado:

```text
REQUEST
   ↓
Validar api_key
   ↓
Validar token_unico
   ↓
Validar CollectionRequest + cliente
   ↓
Validar tipo_documento_id
   ↓
Receber multipart/form-data
   ↓
Arquivo temporário
   ↓
DocumentAnalyzerService::analisarDocumento()
   ↓
Documento corresponde ao tipo solicitado?
   ├── NÃO → retornar erro → NÃO salvar no S3
   │                         → NÃO criar/atualizar Documento como aprovado
   │                         → remover arquivo temporário
   │
   └── SIM
        ↓
      Upload S3
        ↓
      Persistência no banco
        ↓
      Atualizar Collectionrequestdocumento
        ↓
      Atualizar CollectionRequest se todos enviados
        ↓
      remover arquivo temporário
        ↓
      retornar resposta de sucesso
```

---

# 2. MANTER `multipart/form-data`

O endpoint deve continuar aceitando:

```http
Content-Type: multipart/form-data
```

O projeto já possui lógica para extrair arquivos de `$_FILES`, incluindo a estrutura:

```php
$_FILES['documento']
```

e outras variações. Preserve a compatibilidade dessa lógica sempre que possível.

A referência atual de extração dos arquivos está no método:

```php
extractUploadedFiles()
```

e já trabalha principalmente com `$_FILES['documento']`.

Priorizar `multipart/form-data`.

Não remover suporte existente sem necessidade.

---

# 3. AUTENTICAÇÃO OBRIGATÓRIA POR `api_key`

Sem `api_key`, nenhuma consulta ou operação pode ser realizada.

A implementação atual já extrai:

```php
$this->API_KEY = $headers['api_key'] ?? null;
```

e rejeita requisições sem chave. Preserve essa regra.

A autenticação deve acontecer antes de qualquer operação de negócio.

Regras:

```text
api_key ausente
    → HTTP 403
    → não executar análise
    → não consultar CollectionRequest
    → não salvar arquivo
```

Também deve validar se a `api_key` realmente corresponde a um cliente existente.

A lógica existente utiliza:

```php
Cliente::getClienteByApiKey($this->API_KEY)
```

e deve continuar sendo utilizada.

---

# 4. A `CollectionRequest` SEMPRE DEVE PERTENCER AO CLIENTE

Essa regra é obrigatória e deve ser aplicada tanto no `GET /documento` quanto no `POST /documento`.

Nunca consultar uma `CollectionRequest` apenas pelo:

```php
token_unico
```

sem verificar o cliente.

A consulta deve continuar seguindo o conceito:

```php
CollectionRequest::where('token_unico', '=', $tokenUnico)
    ->where('cliente_id', '=', $cliente->id)
    ->first();
```

A implementação atual já faz essa validação no GET e no POST. Preserve e fortaleça esse comportamento.
Isso deve impedir completamente que:

```text
Cliente A
```

consulte ou envie documentos para:

```text
CollectionRequest do Cliente B
```

Mesmo que possua o `token_unico`.

---

# 5. VALIDAR `tipo_documento_id`

O `tipo_documento_id` informado na requisição deve ser validado.

O documento somente pode ser processado se esse tipo estiver entre os documentos exigidos daquela `CollectionRequest`.

A implementação atual já carrega:

```php
Collectionrequestdocumento
```

e monta os tipos permitidos.

Preservar essa validação.

Se o tipo informado não fizer parte da solicitação:

```http
HTTP 422
```

e retornar uma resposta de erro coerente com o padrão atual da API.

Não executar upload para S3.

---

# 6. ANÁLISE COM IA ANTES DO SALVAMENTO

O serviço existente:

```php
DocumentAnalyzerService::analisarDocumento($file, $tipoDocumentoId)
```

deve continuar sendo utilizado como mecanismo de análise.

Atualmente o método `analizer()` utiliza exatamente esse serviço e interpreta:

```php
$resultadoAnalise['CODIGO']
```

para identificar o resultado da análise.

Integrar essa lógica ao fluxo do `POST /documento`.

Não criar uma segunda chamada HTTP interna para `documentosAnalise`.

A chamada deve acontecer diretamente no serviço PHP.

---

# 7. DOCUMENTO INCORRETO NÃO PODE SER SALVO

Essa é uma das regras mais importantes.

Quando:

```php
$resultadoAnalise['CODIGO'] === '404'
```

ou quando a análise indicar que o documento não corresponde ao `tipo_documento_id` solicitado:

### NÃO fazer:

```text
upload para S3
insert/update de Documento como aprovado
status = APROVADO
status da Collectionrequestdocumento = enviado
```

### Fazer:

```text
retornar erro de validação
remover arquivo temporário
manter o documento não enviado/não aprovado
```

O comportamento esperado deve ser semelhante ao padrão atual:

```json
{
    "status": "fail",
    "message": "Documento Incorreto.",
    "motivo_rejeicao": "..."
}
```

com:

```http
HTTP 422
```

O campo `motivo_rejeicao` pode utilizar a informação retornada pelo `DocumentAnalyzerService`, sempre que disponível.

Não salvar documento inválido no S3.

---

# 8. SOMENTE DOCUMENTO VALIDADO PODE IR PARA O S3

O upload para:

```text
AWS S3
```

deve acontecer somente depois de a IA confirmar que o documento corresponde ao tipo solicitado.

Portanto:

```text
recebe arquivo
     ↓
analisa
     ↓
IA aprovou?
     ↓
SIM
     ↓
upload S3
     ↓
persistência banco
```

Nunca:

```text
recebe arquivo
     ↓
upload S3
     ↓
analisa depois
```

O comportamento atual de `savePhysicalFile()` já realiza a transferência para S3 e remove o arquivo local depois do upload.

Reorganize o fluxo para garantir que essa função somente seja chamada depois da aprovação da análise.

---

# 9. LIMPEZA OBRIGATÓRIA DA PASTA `tmp`

Todo arquivo temporário recebido deve ser removido ao final do processamento.

Essa regra vale para TODOS os cenários:

### Cenário A — documento válido

```text
upload
→ análise aprovada
→ S3
→ banco
→ remover tmp
```

### Cenário B — documento inválido

```text
upload
→ análise rejeitada
→ NÃO S3
→ NÃO salvar como aprovado
→ remover tmp
```

### Cenário C — erro interno

```text
upload
→ exceção
→ remover tmp
→ retornar erro
```

### Cenário D — erro de autenticação/validação após arquivo já ter sido recebido

Caso o arquivo já tenha sido disponibilizado no servidor, garantir a limpeza também.

Não depender exclusivamente de:

```php
unlink()
```

em um único ponto do fluxo.

Implementar uma estratégia segura de limpeza com `try/finally` ou mecanismo equivalente, garantindo que todos os arquivos temporários criados/movidos durante a requisição sejam removidos mesmo quando ocorrer:

```php
Exception
```

erro de validação ou falha no upload.

Ter cuidado para não tentar remover um arquivo que já foi movido/excluído.

Usar verificações como:

```php
if ($path && file_exists($path)) {
    unlink($path);
}
```

O objetivo é garantir que a pasta:

```text
/tmp
```

não acumule arquivos.

## O código atual já possui remoções após upload bem-sucedido em `savePhysicalFile()` e `saveBase64File()`, mas isso não cobre corretamente todos os caminhos de erro.

# 10. PERSISTÊNCIA NO BANCO SOMENTE APÓS A APROVAÇÃO

Quando a IA validar o documento:

Criar ou atualizar:

```php
Documento
```

mantendo o conceito atual:

```php
Documento::where('collection_request_id', '=', $requestId)
    ->where('tipo_documento_id', '=', $tipoId)
    ->first();
```

e:

```php
$status = 'APROVADO';
```

O fluxo atual faz isso depois de obter a URL do arquivo. Preserve esse comportamento, porém somente após a análise válida.

Salvar no banco:

```text
collection_request_id
tipo_documento_id
storage_path
status
motivo_rejeicao
data_upload
```

conforme o modelo já existente.

Não alterar desnecessariamente a estrutura das tabelas.

---

# 11. ATUALIZAR `Collectionrequestdocumento`

Após documento validado e efetivamente salvo:

```php
Collectionrequestdocumento
```

deve ser atualizado para:

```php
status = 'enviado'
```

A lógica atual já executa essa atualização.

Porém, isso somente pode ocorrer depois de:

```text
IA aprovou
+
S3 upload concluído
+
Documento salvo no banco
```

Um documento rejeitado pela IA NÃO deve ser marcado como `enviado`.

---

# 12. ATUALIZAR STATUS DA `CollectionRequest`

Manter o comportamento existente.

Depois de salvar o documento, verificar se todos os documentos exigidos estão:

```text
enviado
```

Se todos estiverem enviados, atualizar:

```php
$collectionRequest->status = 'enviado';
```

A implementação atual já possui essa lógica.

Preservar essa regra.

---

# 13. SALVAR OS DADOS EXTRAÍDOS DA IA

O serviço de análise pode retornar dados extraídos do documento.

Preservar a capacidade de persistir esses dados na estrutura:

```php
ExtractedData
```

utilizando:

```php
collection_request_id
payload_ocr
processado_em
```

A implementação existente já faz isso quando `extracted_data` está disponível.

Porém, adaptar o fluxo para utilizar os dados retornados pela própria análise integrada, quando disponíveis.

Evitar duplicidade de dados se a análise já retornar a mesma informação.

---

# 14. NÃO ALTERAR O GET `/documento`

Esta regra é obrigatória.

A rota:

```http
GET /documento
```

deve continuar funcionando como atualmente.

Ela deve continuar retornando:

```text
collection_request_id
token_unico
status_request
data_expiracao
documentos_exigidos
```

e os respectivos:

```text
tipo_documento_id
descricao
status
```

O comportamento existente de validação de:

```text
api_key
token_unico
cliente
CollectionRequest
expiração
```

deve ser preservado.

Não modificar o contrato de resposta do GET sem necessidade.

Não trocar o método HTTP.

Não remover campos.

Não alterar a finalidade dessa rota.

---

# 15. REMOVER A DEPENDÊNCIA DO PARÂMETRO `ia=true`

O fluxo anterior possui:

```php
if (isset($param['ia']) && $param['ia'] == 'true') {
    return $this->analizer($param);
}
```

Essa lógica representa o antigo fluxo separado de análise.

O novo fluxo não deve depender de:

```text
?ia=true
```

para analisar documentos.

A análise deve ser parte natural do:

```http
POST /documento
```

avaliando o arquivo enviado.

Depois da implementação, o código não deve exigir que o consumidor faça:

```text
POST /documentosAnalise?ia=true
```

antes de:

```text
POST /documento
```

Avaliar se o método `analizer()` pode ser removido, incorporado ao `create()` ou transformado em método interno reutilizável.

Priorizar reutilização da implementação existente sem duplicar código.

---

# 16. MANTER O PADRÃO DE RESPOSTA DA API

Não criar um padrão totalmente novo.

Seguir o padrão já utilizado na classe.

Sucesso:

```json
{
    "status": "success",
    "message": "Documento validado, salvo e enviado com sucesso.",
    "data": [...]
}
```

Documento inválido:

```json
{
    "status": "fail",
    "message": "Documento Incorreto.",
    "motivo_rejeicao": "..."
}
```

Erro:

```json
{
    "status": "error",
    "message": "..."
}
```

Manter compatibilidade conceitual com as respostas existentes.

Atualmente o método de análise utiliza `success`, `fail` e `error`, e o POST de criação utiliza `success` com `data`.
Não retornar dados sensíveis desnecessariamente.

---

# 17. TRANSAÇÃO DO BANCO

Avaliar cuidadosamente o uso de:

```php
TTransaction::open('data');
TTransaction::close();
TTransaction::rollback();
```

A operação de banco deve permanecer consistente.

Uma preocupação importante é o relacionamento entre banco e S3:

```text
Banco
+
S3
```

Se o upload para S3 falhar, não marcar o documento como aprovado.

Se a persistência do banco falhar depois de um upload S3 bem-sucedido, evitar deixar o sistema em estado inconsistente sempre que tecnicamente possível.

Não implementar comportamento destrutivo no S3 sem verificar previamente se existe método seguro no projeto para exclusão/rollback de objetos.

Antes de criar qualquer novo método AWS, procurar primeiro por serviços existentes no projeto.

---

# 18. NÃO QUEBRAR O CÓDIGO EXISTENTE DE AWS

A aplicação já possui:

```php
AwsService::uploadFile(...)
```

e o fluxo atual passa:

```text
token_unico
filename
absolutePath
```

para esse serviço.

Reutilizar:

```php
AwsService::uploadFile()
```

em vez de implementar uma segunda integração AWS.

Não criar credenciais AWS hardcoded.

Não alterar configuração de bucket sem necessidade.

Não expor:

```text
AWS_ACCESS_KEY
AWS_SECRET_KEY
bucket
credenciais
```

em resposta HTTP ou logs.

---

# 19. TRATAMENTO DE ERROS

Mapear corretamente os principais cenários:

### API key ausente/inválida

```http
403
```

```json
{
    "status": "error",
    "message": "Sem autorizacao."
}
```

### token ausente

```http
400
```

### CollectionRequest inexistente ou pertencente a outro cliente

```http
404
```

ou manter exatamente o código já adotado pelo projeto para esse cenário.

### CollectionRequest expirada

```http
404
```

### tipo de documento inválido para a solicitação

```http
422
```

### documento não corresponde ao tipo solicitado

```http
422
```

### falha interna/serviço

```http
500
```

Nunca retornar:

```http
201
```

quando a IA reprovar o documento.

---

# 20. NÃO EXPOR DETALHES INTERNOS

Não retornar stack trace.

Não retornar consultas SQL.

Não retornar credenciais.

Não retornar caminhos absolutos do servidor.

Não retornar nomes internos de arquivos temporários.

Não retornar informações internas da AWS.

Mensagens devem ser adequadas para consumo da API.

---

# 21. VALIDAÇÃO DO ARQUIVO

Antes da análise, validar minimamente o upload:

```text
arquivo existe
upload_error == UPLOAD_ERR_OK
arquivo possui tmp_name válido
arquivo pode ser lido
```

Considerar também validações já existentes no projeto para MIME/type/extensão.

Não implementar uma whitelist incompatível com o `DocumentAnalyzerService` sem verificar como esse serviço processa os arquivos.

---

# 22. SUPORTE A MÚLTIPLOS DOCUMENTOS

A implementação atual possui suporte para múltiplos arquivos através de `extractUploadedFiles()`.

Não remover essa capacidade sem necessidade.

Porém, o fluxo deve garantir que cada arquivo seja processado individualmente:

```text
arquivo 1
→ validar tipo
→ analisar
→ salvar se aprovado
→ limpar tmp

arquivo 2
→ validar tipo
→ analisar
→ salvar se aprovado
→ limpar tmp
```

Cada documento deve ter sua própria decisão de:

```text
aprovado
ou
rejeitado
```

Evitar que um documento inválido seja salvo simplesmente porque outro documento da mesma requisição foi aprovado.

Se a implementação atual não suportar transação lógica de múltiplos arquivos de maneira segura, analisar o comportamento atual e preservar compatibilidade, documentando no código a decisão adotada.

---

# 23. NÃO ALTERAR DESNECESSARIAMENTE A URL DO GET

A rota:

```text
GET /documento
```

continua existindo.

O mesmo:

```text
DocumentoRestService::handle()
```

deve continuar fazendo o roteamento por método HTTP:

```php
'GET' => $this->loadAll($param),
'POST' => $this->create($param),
```

ou equivalente.

O foco da alteração é mudar o comportamento do POST.

---

# 24. EXEMPLO DO NOVO CONTRATO DE POST

Considerar como referência:

```http
POST /documento
Content-Type: multipart/form-data
api_key: CHAVE_DO_CLIENTE
```

Form-data:

```text
token_unico = abc123
documento[1] = arquivo.pdf
```

ou conforme a convenção já suportada pelo método `extractUploadedFiles()`.

O servidor deve:

```text
1. validar api_key
2. encontrar cliente
3. validar token_unico
4. localizar CollectionRequest pelo token + cliente
5. verificar expiração
6. verificar tipo_documento_id
7. receber arquivo
8. executar DocumentAnalyzerService
9. avaliar resultado
10. se aprovado:
      upload S3
      salvar Documento
      atualizar Collectionrequestdocumento
      atualizar CollectionRequest se necessário
      salvar dados extraídos
11. limpar tmp
12. retornar JSON
```

---

# 25. COMPORTAMENTO ESPERADO QUANDO A IA REPROVAR

Exemplo:

```text
tipo_documento_id = CPF
arquivo enviado = CNH
```

A IA identifica:

```text
documento incompatível
```

Resultado:

```http
422 Unprocessable Entity
```

Resposta:

```json
{
    "status": "fail",
    "message": "Documento Incorreto.",
    "motivo_rejeicao": "O documento enviado não corresponde ao tipo solicitado."
}
```

E obrigatoriamente:

```text
não enviar para S3
não criar Documento aprovado
não marcar Collectionrequestdocumento como enviado
remover arquivo temporário
```

---

# 26. COMPORTAMENTO ESPERADO QUANDO A IA APROVAR

Exemplo:

```text
tipo_documento_id = CPF
arquivo = CPF
```

Fluxo:

```text
IA aprova
↓
upload S3
↓
Documento.storage_path = URL S3
↓
Documento.status = APROVADO
↓
Collectionrequestdocumento.status = enviado
↓
ExtractedData, quando houver dados
↓
CollectionRequest.status = enviado, caso todos estejam enviados
↓
limpar tmp
↓
HTTP 201
```

Resposta:

```json
{
    "status": "success",
    "message": "Documento validado, salvo e enviado com sucesso.",
    "data": [...]
}
```

---

# 27. ESTRUTURA DO CÓDIGO

Não fazer uma reescrita completa da classe.

Preferir:

```text
handle()
    ↓
loadAll()               // manter
create()                // adaptar
extractUploadedFiles()  // manter/adaptar
método de análise      // reaproveitar
savePhysicalFile()      // adaptar para novo fluxo
saveBase64File()        // manter apenas se ainda necessário
cleanup                 // reforçar
```

Evitar duplicação entre:

```text
analizer()
create()
savePhysicalFile()
```

Centralizar o fluxo de decisão em métodos privados pequenos e reutilizáveis.

---

# 28. VERIFICAR O CÓDIGO EXISTENTE ANTES DE ALTERAR

Antes de implementar, analisar:

```text
DocumentoRestService.php
DocumentAnalyzerService.php
AwsService.php
Cliente
CollectionRequest
Collectionrequestdocumento
Documento
TiposDocumentos
ExtractedData
```

Também procurar no projeto:

```text
documentosAnalise
DocumentoRestService
analisarDocumento
uploadFile
CollectionRequest
Collectionrequestdocumento
```

para descobrir dependências existentes.

Não assumir que um método não existe apenas porque não está presente no arquivo atual.

---

# 29. REGRAS DE SEGURANÇA IMPORTANTES

Sempre aplicar a seguinte sequência:

```text
api_key
→ cliente
→ token_unico
→ CollectionRequest + cliente_id
→ validade da CollectionRequest
→ tipo_documento_id pertencente à CollectionRequest
→ arquivo
→ análise IA
→ S3
→ banco
```

Nunca permitir:

```text
api_key A
+
token de cliente B
```

resultar em acesso ou alteração de documentos do cliente B.

Nunca confiar somente em parâmetros enviados pelo cliente.

O `cliente_id` deve vir do cliente identificado pela `api_key`.

---

# 30. CRITÉRIOS DE ACEITE

A implementação só deve ser considerada concluída quando todos os casos abaixo funcionarem:

### Caso 1 — Sem api_key

```text
POST /documento
```

Resultado:

```http
403
```

e nenhuma operação de documento deve ocorrer.

### Caso 2 — api_key válida + token pertencente a outro cliente

Resultado:

```text
acesso negado / solicitação não encontrada
```

e nenhum documento deve ser salvo.

### Caso 3 — tipo_documento_id não pertence à CollectionRequest

Resultado:

```http
422
```

sem upload para S3.

### Caso 4 — documento incompatível com tipo solicitado

Resultado:

```http
422
```

sem salvar no S3, sem marcar como enviado e com remoção do arquivo temporário.

### Caso 5 — documento válido

Resultado:

```http
201
```

com documento no S3, banco atualizado e status atualizado.

### Caso 6 — erro durante upload S3

Não marcar o documento como aprovado.

Remover arquivo temporário.

Retornar erro apropriado.

### Caso 7 — erro durante persistência

Não mascarar a exceção.

Evitar inconsistências.

Remover arquivo temporário.

### Caso 8 — `GET /documento`

Continuar funcionando sem alteração do contrato atual.

### Caso 9 — arquivo temporário

Depois de qualquer processamento:

```text
sucesso
erro
rejeição IA
falha S3
falha banco
```

o arquivo temporário deve ser removido.

---

# 31. O QUE NÃO FAZER

Não criar novamente o fluxo de duas requisições.

Não obrigar o consumidor a chamar `documentosAnalise` antes do POST.

Não salvar arquivo no S3 antes da validação da IA.

Não marcar documento inválido como `APROVADO`.

Não marcar documento inválido como `enviado`.

Não permitir acesso cruzado entre clientes.

Não remover a autenticação por `api_key`.

Não remover a validação de `CollectionRequest`.

Não alterar desnecessariamente o GET `/documento`.

Não remover `multipart/form-data`.

Não criar uma nova integração AWS se `AwsService::uploadFile()` já atende ao projeto.

Não deixar arquivos na pasta `tmp`.

Não gravar credenciais ou dados sensíveis em código.

Não fazer uma grande refatoração sem necessidade.

---

# 32. ENTREGA ESPERADA

Implemente a alteração diretamente no código existente.

Antes de modificar, explique brevemente quais métodos serão alterados e por quê.

Depois da implementação:

1. apresente os arquivos modificados;
2. explique o novo fluxo;
3. mostre o comportamento do `POST /documento`;
4. confirme que o `GET /documento` foi preservado;
5. confirme a validação `api_key → cliente → CollectionRequest`;
6. confirme que documento inválido não é salvo no S3;
7. confirme que o `tmp` é limpo em todos os caminhos;
8. apresente exemplos de request/response;
9. destaque qualquer ponto que não pôde ser validado por falta de código/dependência no projeto.

IMPORTANTE: não invente classes, tabelas, campos ou métodos quando já existir implementação no projeto. Primeiro procure e reutilize o que já existe.

O resultado final deve ser uma implementação segura, compatível com a arquitetura atual e com o novo requisito de realizar análise + validação + salvamento em uma única chamada `POST /documento`.
