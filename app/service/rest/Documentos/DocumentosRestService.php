<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class DocumentosRestService extends AdiantiRecordService{

    protected ?string $API_KEY;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['Api-Key'] ?? $headers['api_key'] ?? null;

        if (is_null($this->API_KEY)) {
            throw new Exception('Sem autorizacao.');
        }

        unset($param['class']);
        unset($param['method']);

        switch ($method) {
            case 'GET':
                if (!empty($param['id'])) {
                    //return $this->getTipoDocumentosbyId($param);
                } else {
                    return $this->getDocumentos();
                }
                break;
            case 'POST':
                return $this->create($param);
                break;
            case 'PUT':
                //return $this->update($param);
                break;
        }
    }

    public static function getDocumentos() {
        try {
            TTransaction::open('data');
            $documentos = Documentos::all();

            $documentosArray = [];
            foreach ($documentos as $documento) {
                $documentosArray[] = $documento->toArray();
            }
            TTransaction::close();
            return $documentosArray;
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar documentos: " . $e->getMessage());  
        }
    }

    public static function create($param) {
        try {
            TTransaction::open('data');

            // 1. Identificar o request_id (ID da Solicitação de Coleta)
            $requestId = $param['request_id'] ?? $_POST['request_id'] ?? null;
            $tokenUnico = $param['token_unico'] ?? $_POST['token_unico'] ?? null;

            if (empty($requestId) && !empty($tokenUnico)) {
                $collectionRequest = CollectionRequest::where('token_unico', '=', $tokenUnico)->first();
                if ($collectionRequest) {
                    $requestId = $collectionRequest->id;
                }
            }

            if (empty($requestId)) {
                throw new Exception("ID da solicitação de coleta (request_id ou token_unico) não fornecido.");
            }

            // Validar se a solicitação existe
            $collectionRequest = CollectionRequest::find($requestId);
            if (!$collectionRequest) {
                throw new Exception("Solicitação de coleta de ID {$requestId} não encontrada.");
            }

            // 2. Mapear e extrair os arquivos enviados via $_FILES
            $uploadedFiles = [];

            // Caso 1: Array indexado por tipo de documento: documentos[tipo_documento_id]
            if (!empty($_FILES['documentos']) && is_array($_FILES['documentos']['name'])) {
                foreach ($_FILES['documentos']['name'] as $tipoId => $name) {
                    if ($_FILES['documentos']['error'][$tipoId] == UPLOAD_ERR_OK) {
                        $uploadedFiles[$tipoId] = [
                            'name'     => $name,
                            'tmp_name' => $_FILES['documentos']['tmp_name'][$tipoId],
                            'type'     => $_FILES['documentos']['type'][$tipoId],
                            'size'     => $_FILES['documentos']['size'][$tipoId]
                        ];
                    }
                }
            }

            // Caso 2: Array indexado por tipo de documento: files[tipo_documento_id]
            if (empty($uploadedFiles) && !empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
                foreach ($_FILES['files']['name'] as $tipoId => $name) {
                    if ($_FILES['files']['error'][$tipoId] == UPLOAD_ERR_OK) {
                        $uploadedFiles[$tipoId] = [
                            'name'     => $name,
                            'tmp_name' => $_FILES['files']['tmp_name'][$tipoId],
                            'type'     => $_FILES['files']['type'][$tipoId],
                            'size'     => $_FILES['files']['size'][$tipoId]
                        ];
                    }
                }
            }

            // Caso 3: Nomes de chaves contendo o ID, ex: documento_1, file_2, etc.
            if (empty($uploadedFiles)) {
                foreach ($_FILES as $key => $fileData) {
                    $tipoId = preg_replace('/[^0-9]/', '', $key);
                    if ($tipoId !== '' && is_numeric($tipoId) && $fileData['error'] == UPLOAD_ERR_OK) {
                        $uploadedFiles[$tipoId] = [
                            'name'     => $fileData['name'],
                            'tmp_name' => $fileData['tmp_name'],
                            'type'     => $fileData['type'],
                            'size'     => $fileData['size']
                        ];
                    }
                }
            }

            // Caso 4: Envio sob a chave única 'file' com 'tipo_documento_id' nos campos normais
            if (empty($uploadedFiles) && !empty($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
                $tipoId = $param['tipo_documento_id'] ?? $_POST['tipo_documento_id'] ?? null;
                if ($tipoId) {
                    $uploadedFiles[$tipoId] = [
                        'name'     => $_FILES['file']['name'],
                        'tmp_name' => $_FILES['file']['tmp_name'],
                        'type'     => $_FILES['file']['type'],
                        'size'     => $_FILES['file']['size']
                    ];
                }
            }

            if (empty($uploadedFiles)) {
                throw new Exception("Nenhum arquivo válido enviado ou erro no upload dos arquivos.");
            }

            // 3. Garantir a pasta tmp no root do projeto
            $destDir = getcwd() . '/tmp';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            $savedDocs = [];

            // 4. Salvar cada arquivo e salvar no banco de dados
            foreach ($uploadedFiles as $tipoId => $fileData) {
                // Verificar se o tipo de documento existe
                $tipoDoc = TiposDocumentos::find($tipoId);
                if (!$tipoDoc) {
                    throw new Exception("Tipo de documento com ID {$tipoId} não existe.");
                }

                $originalName = $fileData['name'];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = 'doc_' . $requestId . '_' . $tipoId . '_' . time() . '_' . uniqid() . ($extension ? '.' . $extension : '');
                $destinationPath = 'tmp/' . $filename;
                $absolutePath = $destDir . '/' . $filename;

                if (move_uploaded_file($fileData['tmp_name'], $absolutePath)) {
                    // Registrar no banco de dados
                    $documento = new Documentos();
                    $documento->request_id = $requestId;
                    $documento->tipo_documento_id = $tipoId;
                    $documento->storage_path = $destinationPath;
                    $documento->status = 'enviado';
                    $documento->motivo_rejeicao = null;
                    $documento->data_upload = date('Y-m-d H:i:s');
                    $documento->store();

                    // Atualizar o status da exigência na tabela collection_request_documentos
                    $reqDoc = CollectionRequestDocumentos::where('request_id', '=', $requestId)
                                                          ->where('tipo_documento_id', '=', $tipoId)
                                                          ->first();
                    if ($reqDoc) {
                        $reqDoc->status = 'enviado';
                        $reqDoc->store();
                    } else {
                        // Caso a exigência não estivesse pré-cadastrada, criamos para manter integridade
                        $reqDoc = new CollectionRequestDocumentos();
                        $reqDoc->request_id = $requestId;
                        $reqDoc->tipo_documento_id = $tipoId;
                        $reqDoc->status = 'enviado';
                        $reqDoc->data_criacao = date('Y-m-d H:i:s');
                        $reqDoc->store();
                    }

                    // Identificar e salvar o texto_documento enviado pela IA do frontend
                    $textoDocumento = null;
                    if (isset($param['texto_documento'][$tipoId])) {
                        $textoDocumento = $param['texto_documento'][$tipoId];
                    } elseif (isset($_POST['texto_documento'][$tipoId])) {
                        $textoDocumento = $_POST['texto_documento'][$tipoId];
                    } elseif (isset($param['texto_documento']) && !is_array($param['texto_documento'])) {
                        $textoDocumento = $param['texto_documento'];
                    } elseif (isset($_POST['texto_documento']) && !is_array($_POST['texto_documento'])) {
                        $textoDocumento = $_POST['texto_documento'];
                    }

                    if ($textoDocumento) {
                        $extratedData = new ExtratedData();
                        $extratedData->collection_request_id = $requestId;
                        $extratedData->payload_ocr = is_array($textoDocumento) ? json_encode($textoDocumento) : $textoDocumento;
                        $extratedData->processado_em = date('Y-m-d H:i:s');
                        $extratedData->store();
                    }

                    $savedDocs[] = $documento->toArray();
                } else {
                    throw new Exception("Falha ao mover arquivo temporário para o destino: {$originalName}");
                }
            }

            // 5. Atualizar o status da solicitação de coleta principal se todos os documentos exigidos estiverem enviados
            $allDocs = CollectionRequestDocumentos::where('request_id', '=', $requestId)->load();
            $allUploaded = true;
            foreach ($allDocs as $doc) {
                if ($doc->status === 'pendente') {
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
            throw new Exception("Erro ao criar documento: " . $e->getMessage());
        }
    }

}