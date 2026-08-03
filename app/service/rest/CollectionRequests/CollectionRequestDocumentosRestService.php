<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class CollectionRequestDocumentosRestService extends AdiantiRecordService
{
    

    protected string $API_KEY;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['Api-Key'] ?? null;

        if (is_null($this->API_KEY)) {
            throw new Exception('Sem autorizacao.');
        }

        switch ($method) {
            case 'GET':
                if (!empty($param['id'])) {
                    return $this->getCollectionRequestDocumentosById($param);
                } else {
                    return $this->getCollectionRequestDocumentos($param);
                }
                break;
            case 'POST':
                return $this->createCollectionRequestDocumentos($param);
                break;
            // case 'PUT':
            //     return $this->updateCollectionRequestDocumentos($param);
            //     break;
            // case 'DELETE':
            //     return $this->deleteCollectionRequestDocumentos($param);
            //     break;
            default:
                throw new Exception("Método HTTP não suportado.");
        }
    }


    public static function getCollectionRequestDocumentos($param)
    {
        try {
            $collectionRequestId = $param['collection_request_id'] ?? null;

            if (is_null($collectionRequestId)) {
                throw new Exception("ID da solicitação de coleta não fornecido.");
            }

            $collectionRequestDocumentos = CollectionRequestDocumentos::where('collection_request_id', '=', $collectionRequestId)->load();

            return $collectionRequestDocumentos;
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar documentos da solicitação de coleta: " . $e->getMessage());
        }
    }

    public static function getCollectionRequestDocumentosById($param)
    {
        try {
            $id = $param['id'] ?? null;

            if (is_null($id)) {
                throw new Exception("ID do documento da solicitação de coleta não fornecido.");
            }

            $collectionRequestDocumento = CollectionRequestDocumentos::find($id);

            if (!$collectionRequestDocumento) {
                throw new Exception("Documento da solicitação de coleta não encontrado.");
            }

            return $collectionRequestDocumento;
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar documento da solicitação de coleta: " . $e->getMessage());
        }
    }

    public static function createCollectionRequestDocumentos($param)
    {
        try {
            TTransaction::open('data');

            $collectionRequestDocumento = new CollectionRequestDocumentos();

            $collectionRequestDocumento->fromArray($param['data'] ?? []);
            $collectionRequestDocumento->data_criacao = date('Y-m-d H:i:s');
            $collectionRequestDocumento->store();

            TTransaction::close();

            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Documento da solicitação de coleta criado com sucesso',
                'data' => $collectionRequestDocumento->toArray()
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao criar documento da solicitação de coleta: " . $e->getMessage());
        }
    }
}