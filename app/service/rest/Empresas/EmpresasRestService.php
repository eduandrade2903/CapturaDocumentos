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
            $this->API_KEY = $headers['api_key'] ?? null;

            if( is_null($this->API_KEY) ) { throw new Exception('Sem autorizacao.'); }
                
            unset($param['class']);
            unset($param['method']);


            switch ($method) {
                case 'GET':
                    return $this->getByKey($param);
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
            return [ 'message' => $e->getMessage() ];
        }
    }

    /**
     * Cria uma nova empresa
     * @param array $request['data']=['razao_social', 'cnpj', 'nome_contato', 'email', 'telefone', 'webhook_url']
     * @return array dados da empresa com a chave de API gerada
     */
    public function create(array $request): array
    {
        try {
            

            $data = $request['data'] ?? [];

            if ($data['razao_social'] === null || $data['cnpj'] === null || $data['nome_contato'] === null || $data['email'] === null) {
                http_response_code(400); // Set HTTP status code to 400 Bad Request
                throw new Exception('Todos os campos são obrigatórios.');
            }

            $chave_api = bin2hex(random_bytes(64)); // Gera a chave da api 

            TTransaction::open('data');
            $empresa = new Empresas();
            $empresa->fromArray($data);
            $empresa->api_key = hash('sha256', $chave_api); //Armazena no banco o hash da chave
            $empresa->data_cadastro = date('Y-m-d H:i:s');
            $empresa->store();
            TTransaction::close();

            TTransaction::open('permission');
            $unidade = new SystemUnit;
            $unidade->name = $empresa->razao_social;
            $unidade->custom_code = $empresa->id;
            $unidade->store();

            $systemUser = new SystemUser;
            $systemUser->login = $empresa->email;
            $systemUser->name = $empresa->razao_social;
            $systemUser->email = $empresa->email;
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
            $empresa->system_user_id = $systemUser->id;
            $empresa->store();
            TTransaction::close();

            EmpresasService::enviaApiEmailEmpresa($empresa, $chave_api);

            http_response_code(201); // Set HTTP status code to 201 Created
            return [
                'message' => 'Empresa criada com sucesso',
                'api_key' => $chave_api
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500); // Set HTTP status code to 500 Internal Server Error
            return [ 'message' => $e->getMessage() ];
        }
    }

    /**
     * @description Obtem os dados de uma empresa pela chave de API
     * @param array $request['data']=['api_key']
     * @return array dados da empresa
     */
    public function getByKey(array $request): array
    {
        try {
            TTransaction::open('data');
            $empresa = Empresas::getEmpresaByApiKey($this->API_KEY);
            TTransaction::close();

            if (!$empresa) {
                http_response_code(403); // Set HTTP status code to 403 Forbidden
                throw new Exception('Chave de API invalida.');
            }

            unset($empresa->id);
            unset($empresa->api_key);

            http_response_code(200); // Set HTTP status code to 200 OK
            return [ 'empresa' => $empresa->toArray() ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return [ 'message' => $e->getMessage() ];
        }
    }

    /**
     * @description Atualiza os dados de uma empresa existente
     * @param array $request['data']=['razao_social', 'cnpj', 'nome_contato', 'email', 'telefone', 'webhook_url']
     * @return array dados atualizados da empresa
     */
    public function update(array $request): array
    {
        try {
            $data = $request['data'] ?? [];

            TTransaction::open('data');

            $empresa = Empresas::getEmpresaByApiKey($this->API_KEY);

            if (!$empresa) {
                http_response_code(403); // Set HTTP status code to 403 Forbidden
                throw new Exception('Chave de API invalida');
            }

            $empresa->fromArray($data);
            $empresa->store();
            TTransaction::close();

            unset($empresa->id);
            unset($empresa->api_key);

            http_response_code(200); // Set HTTP status code to 200 OK
            return [ 'empresa' => $empresa->toArray() ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(404); // Set HTTP status code to 404 Not Found
            return [ 'message' => $e->getMessage() ];
        }
    }
}
