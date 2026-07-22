<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

/**
 * Empresas REST service
 */
class EmpresasRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'Empresas';
    const ATTRIBUTES    = ['id', 'razao_social', 'cnpj', 'api_key', 'nome_contato', 'email', 'telefone', 'webhook_url', 'data_cadastro'];
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

            if($data['razao_social'] === null || $data['cnpj'] === null || $data['nome_contato'] === null || $data['email'] === null) {
                http_response_code(400); // Set HTTP status code to 400 Bad Request
                throw new Exception('Todos os campos são obrigatórios.');
            }
            
            $empresa = new Empresas();
            $empresa->fromArray($data);
            $empresa->api_key = bin2hex(random_bytes(128)); // Generate a random API key
            $empresa->data_cadastro = date('Y-m-d H:i:s');
            
            $empresa->store();

            TTransaction::close();

            http_response_code(201); // Set HTTP status code to 201 Created
            return [
                'status' => 'success',
                'message' => 'Empresa criada com sucesso',
                'api_key' => $empresa->api_key
            ];
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            http_response_code(500); // Set HTTP status code to 500 Internal Server Error
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function getById(array $request)
    {
        try{
            
            TTransaction::open('data');

            $empresa = new Empresas($request['id']);
            if(!$empresa) {
                http_response_code(404); // Set HTTP status code to 404 Not Found
                throw new Exception('Parametro invalido.');
            }
            TTransaction::close();

            $headers  = AdiantiCoreApplication::getHeaders();
            if($empresa->api_key !== $headers['api_key']) {
                http_response_code(403); // Set HTTP status code to 403 Forbidden
                throw new Exception('Chave de API invalida.');
            }         

            http_response_code(200); // Set HTTP status code to 200 OK
            return [
                'status' => 'success',
                'data' => $empresa->toArray()
            ];
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

