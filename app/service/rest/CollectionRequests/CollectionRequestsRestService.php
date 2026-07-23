<?php

use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class CollectionRequestsRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'CollectionRequests';
    const ATTRIBUTES    = ['id', 'empresa_id', 'usuario_id', 'token_unico', 'status', 'data_criacao', 'data_expiracao'];
    // const REST_KEY      = 'public';

    /**
     *
     * @param mixed $param
     */
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


    public static function create(array $request) {

        try{
            TTransaction::open('data');

            // Chama a regra de negócio centralizada na Model!
            $resultado = CollectionRequest::registrar(
                $request['empresa_id'] ?? null,
                $request['usuario_id'] ?? null,
                $request['status']     ?? 'pendente'
            );

            TTransaction::close();

            http_response_code(201);

            // Monta o retorno com o token limpo
            $dados = $resultado['model']->toArray();
            $dados['token_unico'] = $resultado['token_limpo'];

            return [
                'status'  => 'success',
                'message' => 'Solicitação de coleta criada com sucesso',
                'data'    => $dados
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
              throw new Exception("Erro ao criar solicitação de coleta: " . $e->getMessage());
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