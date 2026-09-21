<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

/**
 * Cliente REST service
 */
class ClienteRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'Cliente';
    const ATTRIBUTES    = ['id', 'razao_social', 'cnpj', 'api_key', 'nome_contato', 'email', 'telefone', 'webhook_url', 'data_cadastro'];
    // const REST_KEY      = 'public';

    protected string $API_KEY;

    /**
     * @description Endpoint principal para requisições REST
     * @param array $param
     * @return array
     */
    public function handle($param)
    {
        try {
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
            $headers = AdiantiCoreApplication::getHeaders();
            $this->API_KEY = $headers['api_key'] ?? 'oi';



            unset($param['class']);
            unset($param['method']);


            switch ($method) {
                case 'GET':
                    if(!empty($this->API_KEY) ){
                        return $this->getByKey($param);;
                    }else {
                        return $this->getAll($param);
                    }
                    
                    break;
                case 'POST':
                    return $this->create($param);
                    break;
                case 'PUT':
                    return $this->update($param);
                    break;
            }
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return ['message' => $e->getMessage()];
        }
    }

    /**
     * Cria uma nova cliente
     * @param array $request['data']=['razao_social', 'cnpj', 'nome_contato', 'email', 'telefone', 'webhook_url']
     * @return array dados da cliente com a chave de API gerada
     */
    public function create(array $request): array
    {
        try {
            $data = $request['data'] ?? [];

            if ($data['razao_social'] === null || $data['cnpj'] === null || $data['nome_contato'] === null || $data['email'] === null) {
                http_response_code(400); // Set HTTP status code to 400 Bad Request
                throw new Exception('Todos os campos são obrigatórios.');
            }

            //trata campos retirando "., -, /, , ( ,)"
            $data['cnpj'] = str_replace(['.', '-', '/', ',', '(', ')', ' '], '', $data['cnpj']);
            $data['telefone'] = str_replace(['.', '-', '/', ',', '(', ')', ' '], '', $data['telefone']);


            $chave_api = bin2hex(random_bytes(16)); // Gera a chave da api 

            TTransaction::open('data');
            $cliente = new Cliente();
            $cliente->fromArray($data);
            $cliente->api_key = hash('sha256', $chave_api); //Armazena no banco o hash da chave
            $cliente->data_cadastro = date('Y-m-d H:i:s');
            $cliente->store();
            TTransaction::close();

            TTransaction::open('permission');
            $unidade = new SystemUnit;
            $unidade->name = $cliente->razao_social;
            $unidade->custom_code = $cliente->id;
            $unidade->store();

            $systemUser = new SystemUser;
            $systemUser->login = $cliente->email;
            $systemUser->name = $cliente->razao_social;
            $systemUser->email = $cliente->email;
            $systemUser->password = SystemUser::passwordHash('Abcd@1234');
            $systemUser->frontpage_id = '30';
            $systemUser->active = 'Y';
            $systemUser->store();

            $systemUserUnit = new SystemUserUnit;
            $systemUserUnit->system_user_id = $systemUser->id;
            $systemUserUnit->system_unit_id = $unidade->id;
            $systemUserUnit->store();

            $systemUserGroup = new SystemUserGroup;
            $systemUserGroup->system_user_id = $systemUser->id;
            $systemUserGroup->system_group_id = '4';
            $systemUserGroup->store();

            TTransaction::close();

            TTransaction::open('data');
            $cliente->system_user_id = $systemUser->id;
            $cliente->store();
            TTransaction::close();

            EmailService::enviaApiEmailCliente($cliente, $chave_api);

            http_response_code(201); // Set HTTP status code to 201 Created
            return [];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500); // Set HTTP status code to 500 Internal Server Error
            return ['message' => $e->getMessage()];
        }
    }

    /**
     * @description Obtem os dados de uma cliente pela chave de API
     * @param array $request['data']=['api_key']
     * @return array dados da cliente
     */
    public function getByKey(array $request): array
    {
        try {
            if (is_null($this->API_KEY)) {
                throw new Exception('Sem autorizacao.');
            }
            TTransaction::open('data');
            $cliente = Cliente::getClienteByApiKey($this->API_KEY);
            TTransaction::close();

            if (!$cliente) {
                http_response_code(403); // Set HTTP status code to 403 Forbidden
                throw new Exception('Chave de API invalida.');
            }

            unset($cliente->id);
            unset($cliente->api_key);

            http_response_code(200); // Set HTTP status code to 200 OK
            return ['cliente' => $cliente->toArray()];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return ['message' => $e->getMessage()];
        }
    }

    /**
     * @description Atualiza os dados de uma cliente existente
     * @param array $request['data']=['razao_social', 'cnpj', 'nome_contato', 'email', 'telefone', 'webhook_url']
     * @return array dados atualizados da cliente
     */
    public function update(array $request): array
    {
        try {
            if (is_null($this->API_KEY)) {
                throw new Exception('Sem autorizacao.');
            }
            $data = $request['data'] ?? [];

            TTransaction::open('data');

            $cliente = Cliente::getClienteByApiKey($this->API_KEY);

            if (!$cliente) {
                http_response_code(403); // Set HTTP status code to 403 Forbidden
                throw new Exception('Chave de API invalida');
            }

            $cliente->fromArray($data);
            $cliente->store();
            TTransaction::close();

            unset($cliente->id);
            unset($cliente->api_key);

            http_response_code(200); // Set HTTP status code to 200 OK
            return ['cliente' => $cliente->toArray()];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return ['message' => $e->getMessage()];
        }
    }

    public function getAll(array $request): array
    {
        try {
            TTransaction::open('data');
            $clientes = Cliente::all();
            TTransaction::close();

            $clientesArray = [];
            foreach ($clientes as $cliente) {
                unset($cliente->id);
                //unset($cliente->api_key);
                $clientesArray[] = $cliente->toArray();
            }

            http_response_code(200); // Set HTTP status code to 200 OK
            return ['clientes' => $clientesArray];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return ['message' => $e->getMessage()];
        }
    }


}
