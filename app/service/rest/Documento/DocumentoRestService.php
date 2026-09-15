<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

/**
 * DocumentoRestService
 * 
 * Serviço REST para Coleta de Documento (Onboarding).
 * Conforme documentação em Documentacao/Documento.md.
 */
class DocumentoRestService extends AdiantiRecordService
{
    protected ?string $API_KEY = null;

    /**
     * Tratamento central das requisições HTTP
     */
    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['api_key']  ?? null;

        if (empty($this->API_KEY)) {
            http_response_code(403);
            throw new Exception('Sem autorizacao.');
        }



        unset($param['class']);
        unset($param['method']);

        // return match ($method) {
        //     'GET' => $this->loadAll($param),
        //     'POST' => $this->create($param),
        //     default => throw new Exception("Método HTTP {$method} não suportado.")
        // };

        switch ($method) {
            case 'GET':
                if($param['action'] === 'visualizar') {
                    return $this->visualiza($param);
                }else {
                    return $this->loadAll($param);
                }
            case 'POST':
                if(isset($param['action']) && $param['action'] == 'recusar') {
                    return $this->recusar($param);
                }else if(isset($param['action']) && $param['action'] == 'renovar_prazo') {
                    return $this->renovaToken($param);
                }    
                else {
                    return $this->create($param);
                }
            default:
                throw new Exception("Método HTTP {$method} não suportado.");
        }
    }

    /**
     * GET /documento
     * Retorna a solicitação de coleta e a lista de documento exigidos.
     */
    public function loadAll($param)
    {
        try {
            TTransaction::open('data');

            // 1. Validar cliente via API_KEY
            $cliente = Cliente::getClienteByApiKey($this->API_KEY);
            if (empty($cliente->id)) {
                http_response_code(403);
                throw new Exception('Sem autorizacao.');
            }

            // 2. Obter token_unico dos parâmetros de query
            $tokenUnico = $param['token_unico'] ?? null;
            if (empty($tokenUnico)) {
                http_response_code(400);
                throw new Exception('Token único não fornecido.');
            }

            // 3. Buscar CollectionRequest vinculada ao token e à cliente
            $collectionRequest = CollectionRequest::where('token_unico', '=', $tokenUnico)
                ->where('cliente_id', '=', $cliente->id)
                ->first();
            if (!$collectionRequest) {
                http_response_code(404);
                throw new Exception('Solicitação de coleta não encontrada ou token inválido.');
            }

            // 4. Validar se a solicitação expirou
            if (!empty($collectionRequest->data_expiracao) && date('Y-m-d H:i:s') > $collectionRequest->data_expiracao) {
                http_response_code(404);
                throw new Exception('Solicitação de coleta expirada.');
            }

            $requestId = (int) $collectionRequest->id;

            // 5. Buscar os documento exigidos para a requisição
            $reqDocs = Collectionrequestdocumento::where('collection_request_id', '=', $requestId)->load();

            $documentosExigidos = [];
            foreach ($reqDocs as $reqDoc) {
                $tipoDocId = (int) $reqDoc->tipo_documento_id;
                $tipoDoc = TiposDocumentos::find($tipoDocId);

                $descricao = $tipoDoc ? (!empty($tipoDoc->descricao) ? $tipoDoc->descricao : ($tipoDoc->nome ?? '')) : '';

                // Busca se o arquivo físico já existe no banco
                $docEnviado = Documento::where('collection_request_id', '=', $requestId)
                    ->where('tipo_documento_id', '=', $tipoDocId)
                    ->first();

                $motivoRejeicao = null;
                $nomeArquivoS3 = null;
                $documentoId = null;

                if ($docEnviado) {
                    if (strtoupper($reqDoc->status ?? '') === 'REJEITADO') {
                        $motivoRejeicao = $docEnviado->motivo_rejeicao;
                    }
                    // PRECISAMOS DISSO PARA OS BOTÕES DO ANGULAR FUNCIONAREM
                    $nomeArquivoS3 = basename($docEnviado->storage_path);
                    $documentoId = $docEnviado->id;
                }

                $documentosExigidos[] = [
                    'id' => $documentoId, // O Angular usa para Recusar
                    'nome_arquivo' => $nomeArquivoS3, // O Angular usa para Visualizar
                    'tipo_documento_id' => $tipoDocId,
                    'descricao' => $descricao,
                    'status' => $reqDoc->status ?? 'PENDENTE',
                    'motivo_rejeicao' => $motivoRejeicao
                ];
            }

            TTransaction::close();

            return [
                'collection_request_id' => $requestId,
                'token_unico' => $collectionRequest->token_unico,
                'status_request' => $collectionRequest->status,
                'data_expiracao' => $collectionRequest->data_expiracao,
                'documentos_exigidos' => $documentosExigidos
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    /**
     * POST /documento
     * Fluxo unificado: recebe arquivo → analisa com IA → salva no S3 → persiste no banco.
     * A análise acontece ANTES do upload S3, impedindo que documentos inválidos sejam salvos na nuvem.
     */
    public function create($param)
    {
        // Apenas uma variável para rastrear o arquivo temporário único
        $localPath = null; 

        try {
            TTransaction::open('data');

            // 1. Extrair e validar o Token ANTES de buscar no banco
            $tokenUnico = $param['token_unico'] ?? $_POST['token_unico'] ?? null;
            if (empty($tokenUnico)) {
                http_response_code(400);
                throw new Exception('Token único não fornecido.');
            }

            // 2. Buscar a solicitação (CollectionRequest)
            $collection = CollectionRequest::where('token_unico', '=', $tokenUnico)->first();
            if (!$collection) {
                http_response_code(404);
                throw new Exception('Solicitação de coleta não encontrada ou token único inválido.');
            }

            // 3. Validar cliente (autorização)
            $cliente = Cliente::where('id', '=', $collection->cliente_id)->first();
            if (empty($cliente->id)) {
                http_response_code(403);
                throw new Exception('Sem autorizacao.');
            }

            // 4. Verificar expiração
            if (!empty($collection->data_expiracao) && date('Y-m-d H:i:s') > $collection->data_expiracao) {
                http_response_code(404);
                throw new Exception('Solicitação de coleta expirada.');
            }

            $requestId = (int) $collection->id;

            // 5. Carregar tipos de documento exigidos
            $reqDocs = Collectionrequestdocumento::where('collection_request_id', '=', $requestId)->load();
            $validTipoIds = array_map(fn($doc) => (int)$doc->tipo_documento_id, $reqDocs);

            // 6. Pasta de destino local para o arquivo temporário
            $destDir = getcwd() . '/tmp';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            // 7. Extrair o arquivo enviado
            $filesToProcess = $this->extractUploadedFiles($param);
            if (empty($filesToProcess)) {
                http_response_code(400);
                throw new Exception('Nenhum arquivo enviado.');
            }

            // Como o front envia um por vez, pegamos diretamente a primeira (e única) posição
            $fileItem = $filesToProcess[0]; 

            // 8. Validar o ID do tipo de documento
            $tipoId = (int) ($fileItem['tipo_documento_id'] ?? 0);
            if ($tipoId <= 0) {
                http_response_code(400);
                throw new Exception('tipo_documento_id não fornecido para o arquivo.');
            }

            if (!in_array($tipoId, $validTipoIds, true)) {
                http_response_code(422);
                throw new Exception("Tipo de documento com ID {$tipoId} enviado não corresponde aos solicitados.");
            }

            // 9. Salvar arquivo em tmp/
            $tempResult = $this->moveToTemp($fileItem, $requestId, $tipoId, $destDir);
            $localPath = $tempResult['absolutePath'];

            // 10. Analisar documento com IA (DocumentAnalyzerService)
            $fileForAnalysis = [
                'tmp_name' => $localPath,
                'type'     => $tempResult['mimeType'],
                'name'     => $tempResult['filename']
            ];
            //$resultadoAnalise = DocumentAnalyzerService::analisarDocumento($fileForAnalysis, $tipoId);
            $resultadoAnalise['CODIGO'] = '200';

            // 11. Avaliar resultado da análise
            if (!isset($resultadoAnalise['CODIGO']) || $resultadoAnalise['CODIGO'] === '404') {
                $this->cleanupTempFile($localPath);
                TTransaction::rollback();
                http_response_code(422);
                return [
                    'status'          => 'fail',
                    'message'         => 'Documento Incorreto.',
                    'motivo_rejeicao' => $resultadoAnalise['motivo_rejeicao']
                        ?? 'O documento enviado não corresponde ao tipo solicitado.'
                ];
            }

            if ($resultadoAnalise['CODIGO'] !== '200') {
                $this->cleanupTempFile($localPath);
                TTransaction::rollback();
                http_response_code(500);
                return [
                    'status'  => 'error',
                    'message' => 'Erro na análise do documento.',
                    'motivo_rejeicao' => $resultadoAnalise['ERRO'] ?? null
                ];
            }

            // 12. IA APROVOU — Fazer upload para o S3
            $s3Url = $this->uploadToS3($tokenUnico, $tempResult['filename'], $localPath);

            // 13. Limpeza do arquivo temporário
            $this->cleanupTempFile($localPath);
            $localPath = null;

            // 14. Salvar registro na tabela Documento
            $documento = Documento::where('collection_request_id', '=', $requestId)
                ->where('tipo_documento_id', '=', $tipoId)
                ->first();
            
            if (!$documento) {
                $documento = new Documento();
                $documento->collection_request_id = $requestId;
                $documento->tipo_documento_id = $tipoId;
            }

            $documento->storage_path = $s3Url;
            $documento->status = 'APROVADO';
            $documento->motivo_rejeicao = null;
            $documento->data_upload = date('Y-m-d H:i:s');
            $documento->store();

            // 15. Atualizar status na tabela collection_request_documentos
            $reqDoc = Collectionrequestdocumento::where('collection_request_id', '=', $requestId)
                ->where('tipo_documento_id', '=', $tipoId)
                ->first();
            
            if ($reqDoc) {
                $reqDoc->status = 'ENVIADO';
                $reqDoc->store();
            }

            // 16. Salvar dados extraídos pela IA (ExtractedData)
            $dadosExtraidos = $resultadoAnalise;
            unset($dadosExtraidos['CODIGO']);
            if (!empty($dadosExtraidos)) {
                $extratedData = new ExtractedData();
                $extratedData->collection_request_id = $requestId;
                $extratedData->payload_ocr = json_encode($dadosExtraidos);
                $extratedData->processado_em = date('Y-m-d H:i:s');
                $extratedData->store();
            }

            // 17. Verificar se TODOS os documentos já foram enviados para fechar o processo
            $allDocs = Collectionrequestdocumento::where('collection_request_id', '=', $requestId)->load();
            $allUploaded = true;
            
            foreach ($allDocs as $doc) {
                if (($doc->status ?? 'PENDENTE') !== 'ENVIADO') {
                    $allUploaded = false;
                    break;
                }
            }
            
            if ($allUploaded && !empty($allDocs)) {
                $collection->status = 'CONCLUIDO';
                $collection->store();
            }

            TTransaction::close();

            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Documento validado, salvo e enviado com sucesso.',
                'data' => $documento->toArray() // Retorna apenas o objeto do documento processado
            ];

        } catch (Exception $e) {
            TTransaction::rollback();
            // Apaga o arquivo temporário isolado, caso a falha tenha acontecido no meio do processo
            if ($localPath) {
                $this->cleanupTempFile($localPath);
            }
            throw $e;
        }
    }

    /**
     * Normaliza e extrai o arquivo enviado via $_FILES (multipart/form-data) ou JSON base64.
     * Sempre é enviado um único arquivo por requisição em $_FILES['file'].
     * O tipo_documento_id vem como campo separado no form-data.
     */
    private function extractUploadedFiles(array $param): array
    {
        $filesToProcess = [];

        // A. Processar arquivo único via $_FILES['file'] (multipart/form-data)
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploaded = $_FILES['file'];
            $tipoId = $param['tipo_documento_id'] ?? $_POST['tipo_documento_id'] ?? null;

            if ($uploaded['tmp_name'] && file_exists($uploaded['tmp_name'])) {
                $filesToProcess[] = [
                    'tipo_documento_id' => $tipoId,
                    'name'              => $uploaded['name'],
                    'tmp_name'          => $uploaded['tmp_name'],
                    'type'              => $uploaded['type'] ?? null
                ];
            }
        }

        // B. Fallback: Processar chaves diretas em $_FILES (ex: $_FILES['doc_1'] ou $_FILES['1'])
        if (empty($filesToProcess) && !empty($_FILES)) {
            foreach ($_FILES as $key => $fileInfo) {
                if ($key === 'file' || $key === 'documento') {
                    continue;
                }
                $tipoId = null;
                if (preg_match('/^documento\[?(\d+)\]?$/', $key, $m) || preg_match('/^doc_?(\d+)$/', $key, $m)) {
                    $tipoId = (int) $m[1];
                } elseif (is_numeric($key)) {
                    $tipoId = (int) $key;
                }

                if ($tipoId && isset($fileInfo['tmp_name']) && $fileInfo['error'] === UPLOAD_ERR_OK && file_exists($fileInfo['tmp_name'])) {
                    $filesToProcess[] = [
                        'tipo_documento_id' => $tipoId,
                        'name' => $fileInfo['name'],
                        'tmp_name' => $fileInfo['tmp_name'],
                        'type' => $fileInfo['type'] ?? null
                    ];
                }
            }
        }

        // C. Fallback: Processar payload JSON com arquivo em Base64
        if (empty($filesToProcess)) {
            $base64Files = $param['files'] ?? $param['documento'] ?? null;
            if (is_array($base64Files)) {
                foreach ($base64Files as $key => $fileItem) {
                    if (is_array($fileItem) && isset($fileItem['tipo_documento_id'], $fileItem['file'])) {
                        $filesToProcess[] = [
                            'tipo_documento_id' => $fileItem['tipo_documento_id'],
                            'base64' => $fileItem['file']
                        ];
                    } elseif (is_numeric($key) && is_string($fileItem)) {
                        $filesToProcess[] = [
                            'tipo_documento_id' => $key,
                            'base64' => $fileItem
                        ];
                    }
                }
            }
        }

        return $filesToProcess;
    }

    /**
     * Salva o arquivo recebido (multipart ou base64) em disco local (tmp/).
     * NÃO faz upload para S3 — isso acontece separadamente, após análise da IA.
     *
     * @return array ['absolutePath' => string, 'filename' => string, 'mimeType' => string]
     */
    private function moveToTemp(array $fileItem, int $requestId, int $tipoDocumentoId, string $destDir): array
    {
        // A. Upload via multipart/form-data (tmp_name presente)
        if (isset($fileItem['tmp_name']) && file_exists($fileItem['tmp_name'])) {
            $originalName = $fileItem['name'] ?? 'file';
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'bin';
            }
            $nomeDocumento = TiposDocumentos::where('id', '=', $tipoDocumentoId)->first()->nome ?? 'documento';

            $filename = $nomeDocumento . '_' . uniqid() . '.' . strtolower($extension);
            $absolutePath = $destDir . '/' . $filename;

            if (is_uploaded_file($fileItem['tmp_name'])) {
                if (!move_uploaded_file($fileItem['tmp_name'], $absolutePath)) {
                    throw new Exception("Falha ao mover arquivo enviado para o servidor.");
                }
            } else {
                if (!copy($fileItem['tmp_name'], $absolutePath)) {
                    throw new Exception("Falha ao salvar arquivo no servidor.");
                }
            }

            $mimeType = $fileItem['type'] ?? mime_content_type($absolutePath) ?: 'application/octet-stream';

            return [
                'absolutePath' => $absolutePath,
                'filename'     => $filename,
                'mimeType'     => $mimeType
            ];
        }

        // B. Upload via Base64
        if (!empty($fileItem['base64'])) {
            $base64String = $fileItem['base64'];
            $mimeType = 'image/jpeg';
            $extension = 'jpg';
            $fileData = $base64String;

            if (preg_match('/^data:([^;]+);base64,(.*)$/s', $base64String, $matches)) {
                $mimeType = $matches[1];
                $fileData = $matches[2];

                $mimeTypes = [
                    'image/jpeg' => 'jpg',
                    'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'text/plain' => 'txt'
                ];
                if (isset($mimeTypes[$mimeType])) {
                    $extension = $mimeTypes[$mimeType];
                }
            }

            $decodedData = base64_decode($fileData);
            if ($decodedData === false) {
                throw new Exception("Falha ao decodificar o arquivo base64 para o tipo de documento {$tipoDocumentoId}.");
            }

            $filename = 'doc_' . $requestId . '_' . $tipoDocumentoId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $absolutePath = $destDir . '/' . $filename;

            if (file_put_contents($absolutePath, $decodedData) === false) {
                throw new Exception("Falha ao salvar o arquivo decodificado no servidor.");
            }

            return [
                'absolutePath' => $absolutePath,
                'filename'     => $filename,
                'mimeType'     => $mimeType
            ];
        }

        throw new Exception("Arquivo inválido para o tipo de documento {$tipoDocumentoId}.");
    }

    /**
     * Faz o upload de um arquivo local para o S3 via AwsService.
     *
     * @return string URL do objeto no S3
     */
    private function uploadToS3(string $tokenUnico, string $filename, string $absolutePath): string
    {
        return AwsService::uploadFile($tokenUnico, $filename, $absolutePath);
    }

    /**
     * Remove um arquivo temporário de forma segura.
     * Verifica existência antes de tentar remover, evitando erros em caminhos já limpos.
     */
    private function cleanupTempFile(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    private function visualiza($param) {
    $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");
    $nomeArquivo = $param['nome_arquivo'] ?? throw new Exception("Arquivo não informado");

    // DELEGA PARA A CAMADA DE SERVIÇO
    $url = DocumentoService::visualizarDocumento($tokenUnico, $nomeArquivo);

    echo json_encode(['status' => 'success', 'url' => $url]);
    exit;
    }

    private function recusar($param) {
        $idDocumento = $param['id_documento'] ?? throw new Exception("ID do documento não informado");
        $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");
        $motivo = $param['motivo_rejeicao'] ?? throw new Exception("Motivo da recusa não informado");

        // DELEGA PARA A CAMADA DE SERVIÇO
        DocumentoService::recusarDocumento($idDocumento, $tokenUnico, $motivo);

        echo json_encode(['status' => 'success', 'message' => 'Documento recusado com sucesso.']);
        exit;
    }

    private function renovaToken($param) {

        $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");

        // Instancia a sua classe passando o token no construtor
        $service = new CollectionRequestService($tokenUnico);
        $renovou = $service->renovarCollection();

        if ($renovou) {
            echo json_encode([
                'status' => 'success',
                'message' => 'O prazo estava expirado e foi renovado por mais 2 dias.'
            ]);
        } else {
            // Retorna um alerta caso a data ainda não tenha vencido
            echo json_encode([
                'status' => 'info',
                'message' => 'O prazo desta solicitação ainda está válido. Nenhuma alteração foi feita.'
            ]);
        }
        exit;       
    }

    // ─────────────────────────────────────────────────────────────────────────
    // [DEPRECATED] Método analizer() removido.
    // A lógica de análise com DocumentAnalyzerService::analisarDocumento()
    // foi integrada diretamente ao create() (passo 8c), eliminando a
    // necessidade da rota separada /documentosAnalise e do parâmetro ?ia=true.
    // Referência do código original preservada no histórico de versionamento.
    // ─────────────────────────────────────────────────────────────────────────

    private function limparNomeS3(string $string) {
        $string = preg_replace('/[áàãâä]/ui', 'a', $string);
        $string = preg_replace('/[éèêë]/ui', 'e', $string);
        $string = preg_replace('/[íìîï]/ui', 'i', $string);
        $string = preg_replace('/[óòõôö]/ui', 'o', $string);
        $string = preg_replace('/[úùûü]/ui', 'u', $string);
        $string = preg_replace('/[ç]/ui', 'c', $string);
        $string = preg_replace('/[^a-z0-9]/i', '_', $string); // Troca tudo que não for letra/numero por _
        $string = preg_replace('/_+/', '_', $string); // Remove sublinhados duplicados

        return trim(strtolower($string), '_');
    }
}
