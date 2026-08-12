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
            $collectionRequestId = $param['tipos_documentos'] ?? null;

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
            TTransaction::open('data');

            $token = $param['token'] ?? null;
            if (is_null($token)) {
                throw new Exception("Token não fornecido.");
            }

            $request = CollectionRequest::where('token_unico', '=', $token)->first();
            if (!$request) {
                throw new Exception("Solicitação não encontrada.");
            }

            // 1. Busca todos os documentos da requisição em 1 query
            $documentos = CollectionRequestDocumentos::where('collection_request_id', '=', $request->id)->load();

            if (empty($documentos)) {
                TTransaction::close();
                return ['status' => 'success', 'data' => []];
            }

            // 2. Extrai todos os IDs de tipos e busca TODOS em uma única query SQL (evita o loop N+1)
            $tiposIds = array_unique(array_map(fn($d) => $d->tipo_documento_id, $documentos));
            $tiposObjetos = TiposDocumentos::where('id', 'in', $tiposIds)->load();

            // Mapeia os tipos por ID para busca instantânea
            $tiposIndexados = [];
            foreach ($tiposObjetos as $tipo) {
                $tiposIndexados[$tipo->id] = [
                    'id' => $tipo->id,
                    'nome' => $tipo->nome,
                    'descricao' => $tipo->descricao,
                    'ativo' => (bool)$tipo->ativo
                ];
            }

            // 3. Monta o resultado final sem consultas extras ao banco
            $documentosFormatados = [];
            foreach ($documentos as $doc) {
                $documentosFormatados[] = [
                    'id' => $doc->id,
                    'request_id' => $doc->collection_request_id,
                    'tipo_documento_id' => $doc->tipo_documento_id,
                    'status' => $doc->status,
                    'data_criacao' => $doc->data_criacao,
                    'tipo_documento' => $tiposIndexados[$doc->tipo_documento_id] ?? null
                ];
            }

            TTransaction::close();
            return ['status' => 'success', 'data' => $documentosFormatados];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(400);
            return ['status' => 'error', 'message' => $e->getMessage()];
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