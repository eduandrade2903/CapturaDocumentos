<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

/**
 * DocumentosRestService
 * 
 * Serviço REST para Coleta de Documentos (Onboarding).
 * Conforme documentação em Documentacao/Documentos.md.
 */
class DocumentosRestService extends AdiantiRecordService
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

        return match ($method) {
            'GET' => $this->loadAll($param),
            'POST' => $this->create($param),
            default => throw new Exception("Método HTTP {$method} não suportado.")
        };
    }

    /**
     * GET /documentos
     * Retorna a solicitação de coleta e a lista de documentos exigidos.
     */
    public function loadAll($param)
    {
        try {
            TTransaction::open('data');

            // 1. Validar empresa via API_KEY
            $empresa = Empresas::getEmpresaByApiKey($this->API_KEY);
            if (empty($empresa->id)) {
                http_response_code(403);
                throw new Exception('Sem autorizacao.');
            }

            // 2. Obter token_unico dos parâmetros de query
            $tokenUnico = $param['token_unico'] ?? null;
            if (empty($tokenUnico)) {
                http_response_code(400);
                throw new Exception('Token único não fornecido.');
            }

            // 3. Buscar CollectionRequest vinculada ao token e à empresa
            $collectionRequest = CollectionRequest::where('token_unico', '=', $tokenUnico)
                                                  ->where('empresa_id', '=', $empresa->id)
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

            // 5. Buscar os documentos exigidos para a requisição
            $reqDocs = CollectionRequestDocumentos::where('request_id', '=', $requestId)->load();

            $documentosExigidos = [];
            foreach ($reqDocs as $reqDoc) {
                $tipoDocId = (int) $reqDoc->tipo_documento_id;
                $tipoDoc = TiposDocumentos::find($tipoDocId);

                $descricao = '';
                if ($tipoDoc) {
                    $descricao = !empty($tipoDoc->descricao) ? $tipoDoc->descricao : ($tipoDoc->nome ?? '');
                }

                $documentosExigidos[] = [
                    'tipo_documento_id' => $tipoDocId,
                    'descricao' => $descricao,
                    'status' => $reqDoc->status ?? 'pendente'
                ];
            }

            TTransaction::close();

            return [
                'request_id' => $requestId,
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
     * POST /documentos
     * Recebe arquivos do usuário (multipart/form-data ou base64) e grava na base/servidor.
     */
    public function create($param)
    {
        try {
            TTransaction::open('data');

            // 1. Validar empresa via API_KEY
            $empresa = Empresas::getEmpresaByApiKey($this->API_KEY);
            if (empty($empresa->id)) {
                http_response_code(403);
                throw new Exception('Sem autorizacao.');
            }

            // 2. Extrair parâmetros
            $tokenUnico = $param['token_unico'] ?? $_POST['token_unico'] ?? null;
            if (empty($tokenUnico)) {
                http_response_code(400);
                throw new Exception('Token único não fornecido.');
            }

            // 3. Buscar CollectionRequest
            $collectionRequest = CollectionRequest::where('token_unico', '=', $tokenUnico)
                                                  ->where('empresa_id', '=', $empresa->id)
                                                  ->first();
            if (!$collectionRequest) {
                http_response_code(404);
                throw new Exception('Solicitação de coleta não encontrada ou token único inválido.');
            }

            // 4. Verificar expiração
            if (!empty($collectionRequest->data_expiracao) && date('Y-m-d H:i:s') > $collectionRequest->data_expiracao) {
                http_response_code(404);
                throw new Exception('Solicitação de coleta expirada.');
            }

            $requestId = (int) $collectionRequest->id;

            // 5. Carregar tipos de documentos permitidos (exigidos) para validação (422)
            $reqDocs = CollectionRequestDocumentos::where('request_id', '=', $requestId)->load();
            $validTipoIds = array_map(fn($doc) => (int)$doc->tipo_documento_id, $reqDocs);

            // 6. Pasta de destino local para os arquivos
            $destDir = getcwd() . '/tmp';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            // 7. Extrair lista de arquivos enviada (seja via $_FILES multipart ou via Base64 em $param)
            $filesToProcess = $this->extractUploadedFiles($param);

            if (empty($filesToProcess)) {
                http_response_code(400);
                throw new Exception('Nenhum arquivo enviado.');
            }

            $savedDocs = [];

            // 8. Processar e salvar cada arquivo
            foreach ($filesToProcess as $fileItem) {
                $tipoId = (int) ($fileItem['tipo_documento_id'] ?? 0);
                if ($tipoId <= 0) {
                    http_response_code(400);
                    throw new Exception('tipo_documento_id não fornecido para um dos arquivos.');
                }

                // Validação 422: O tipo de documento enviado deve corresponder aos exigidos
                if (!in_array($tipoId, $validTipoIds, true)) {
                    http_response_code(422);
                    throw new Exception("Tipo de documento com ID {$tipoId} enviado não corresponde aos solicitados em CollectionRequestDocumentos.");
                }

                // Salvar o arquivo no disco
                if (isset($fileItem['tmp_name']) && file_exists($fileItem['tmp_name'])) {
                    $destinationPath = $this->savePhysicalFile($fileItem, $requestId, $tipoId, $destDir);
                } elseif (!empty($fileItem['base64'])) {
                    $destinationPath = $this->saveBase64File($fileItem['base64'], $requestId, $tipoId, $destDir);
                } else {
                    http_response_code(400);
                    throw new Exception("Arquivo inválido para o tipo de documento {$tipoId}.");
                }

                // Criar ou atualizar registro em Documentos
                $documento = Documentos::where('request_id', '=', $requestId)
                                        ->where('tipo_documento_id', '=', $tipoId)
                                        ->first();
                if (!$documento) {
                    $documento = new Documentos();
                    $documento->request_id = $requestId;
                    $documento->tipo_documento_id = $tipoId;
                }

                $documento->storage_path = $destinationPath;
                $documento->status = 'APROVADO';
                $documento->motivo_rejeicao = null;
                $documento->data_upload = date('Y-m-d H:i:s');
                $documento->store();

                // Atualizar status na tabela collection_request_documentos
                $reqDoc = CollectionRequestDocumentos::where('request_id', '=', $requestId)
                                                      ->where('tipo_documento_id', '=', $tipoId)
                                                      ->first();
                if ($reqDoc) {
                    $reqDoc->status = 'enviado';
                    $reqDoc->store();
                }

                $savedDocs[] = $documento->toArray();
            }

            // 9. Processar extracted_data (OCR) se fornecido
            $extractedDataParam = $param['extracted_data'] ?? $_POST['extracted_data'] ?? $param['extrated_data'] ?? null;
            if ($extractedDataParam) {
                $payloadOcr = $extractedDataParam;
                if (is_array($extractedDataParam) || is_object($extractedDataParam)) {
                    $payloadOcr = json_encode($extractedDataParam);
                }

                $extratedData = new ExtratedData();
                $extratedData->collection_request_id = $requestId;
                $extratedData->payload_ocr = $payloadOcr;
                $extratedData->processado_em = date('Y-m-d H:i:s');
                $extratedData->store();
            }

            // 10. Atualizar status da CollectionRequest se todos os documentos estiverem enviados
            $allDocs = CollectionRequestDocumentos::where('request_id', '=', $requestId)->load();
            $allUploaded = true;
            foreach ($allDocs as $doc) {
                if (($doc->status ?? 'pendente') !== 'enviado') {
                    $allUploaded = false;
                    break;
                }
            }
            if ($allUploaded && !empty($allDocs)) {
                $collectionRequest->status = 'enviado';
                $collectionRequest->store();
            }

            TTransaction::close();

            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Documentos enviados e salvos com sucesso.',
                'data' => $savedDocs
            ];

        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    /**
     * Normaliza e extrai os arquivos enviados via $_FILES (multipart/form-data) ou JSON base64
     */
    private function extractUploadedFiles(array $param): array
    {
        $filesToProcess = [];

        // A. Processar multipart/form-data via $_FILES['documentos']
        if (!empty($_FILES['documentos'])) {
            $uploaded = $_FILES['documentos'];
            if (isset($uploaded['name']) && is_array($uploaded['name'])) {
                foreach ($uploaded['name'] as $tipoId => $name) {
                    if (empty($name)) {
                        continue;
                    }
                    $tmpName = $uploaded['tmp_name'][$tipoId] ?? null;
                    $error = $uploaded['error'][$tipoId] ?? UPLOAD_ERR_NO_FILE;

                    if ($error === UPLOAD_ERR_OK && $tmpName && file_exists($tmpName)) {
                        $filesToProcess[] = [
                            'tipo_documento_id' => $tipoId,
                            'name' => $name,
                            'tmp_name' => $tmpName,
                            'type' => $uploaded['type'][$tipoId] ?? null
                        ];
                    }
                }
            }
        }

        // B. Processar chaves diretas em $_FILES (ex: $_FILES['documentos_1'] ou $_FILES['1'])
        if (empty($filesToProcess) && !empty($_FILES)) {
            foreach ($_FILES as $key => $fileInfo) {
                if ($key === 'documentos') {
                    continue;
                }
                $tipoId = null;
                if (preg_match('/^documentos\[?(\d+)\]?$/', $key, $m) || preg_match('/^doc_?(\d+)$/', $key, $m)) {
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

        // C. Processar payload JSON com array de arquivos em Base64 ($param['files'] ou $param['documentos'])
        if (empty($filesToProcess)) {
            $base64Files = $param['files'] ?? $param['documentos'] ?? null;
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
     * Salva um arquivo vindo de upload físico em disco
     */
    private function savePhysicalFile(array $fileInfo, int $requestId, int $tipoDocumentoId, string $destDir): string
    {
        $originalName = $fileInfo['name'] ?? 'file';
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'bin';
        }

        $filename = 'doc_' . $requestId . '_' . $tipoDocumentoId . '_' . time() . '_' . uniqid() . '.' . strtolower($extension);
        $destinationPath = 'tmp/' . $filename;
        $absolutePath = $destDir . '/' . $filename;

        if (is_uploaded_file($fileInfo['tmp_name'])) {
            if (!move_uploaded_file($fileInfo['tmp_name'], $absolutePath)) {
                throw new Exception("Falha ao mover arquivo enviado para o servidor.");
            }
        } else {
            if (!copy($fileInfo['tmp_name'], $absolutePath)) {
                throw new Exception("Falha ao salvar arquivo no servidor.");
            }
        }

        return $destinationPath;
    }

    /**
     * Salva um arquivo codificado em Base64 em disco
     */
    private function saveBase64File(string $base64String, int $requestId, int $tipoDocumentoId, string $destDir): string
    {
        $mimeType = null;
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
        $destinationPath = 'tmp/' . $filename;
        $absolutePath = $destDir . '/' . $filename;

        if (file_put_contents($absolutePath, $decodedData) === false) {
            throw new Exception("Falha ao salvar o arquivo decodificado no servidor.");
        }

        return $destinationPath;
    }
}