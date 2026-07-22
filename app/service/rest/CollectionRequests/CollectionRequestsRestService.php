<?php

use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class CollectionRequestsRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'CollectionRequests';
    const ATTRIBUTES    = ['id', 'empresa_id', 'usuario_id', 'token_unico', 'status', 'data_criacao', 'data_expiracao'];
    // const REST_KEY      = 'public';

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        
        unset($param['class']);
        unset($param['method']);
        
        
        switch( $method )
        {
            case 'GET':
                if (!empty($param['id']))
                {
                    return $this->getById($param);
                } else {
                    return $this->loadAll($param);
                }
                break;
            case 'POST':
                return $this->create($param);
                break;
            case 'PUT':
                return $this->store($param);
                break;        
            case 'DELETE':
                if (!empty($param['id']))
                {
                    return $this->delete($param);
                }
                else
                {
                    return $this->deleteAll($param);
                }
                break;
        }
    }


    public function create(array $request)
    {
        try{
            TTransaction::open('data');

            $data = $request['data'] ?? [];
            $collectionRequest = new CollectionRequest();
            $collectionRequest::verificaCamposObrigatorios($request);          

            
            $collectionRequest->fromArray($data);
            $collectionRequest->store();

            TTransaction::close();

            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Solicitação de coleta criada com sucesso',
                'data' => $collectionRequest->toArray()
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }


    public function getById($param) {
        try {
            TTransaction::open('data');
            $collectionRequest = new CollectionRequest($param['id']);
            TTransaction::close();

            return [
                'status' => 'success',
                'data' => $collectionRequest->toArray()
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}