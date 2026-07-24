<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class UsuariosRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'Usuarios';
    const ATTRIBUTES    = ['id', 'nome_completo', 'cpf', 'email', 'telefone'];
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
                }else {
                    return $this->loadAll($param);
                }
                break;
            case 'POST':
                return $this->create($param);
                break;
            case 'PUT':
                return $this->update($param);
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


    public function create(array $request)    {       
        $headers = AdiantiCoreApplication::getHeaders();
        $apiKey = $headers['api_key'] ?? null;

        try {
            TTransaction::open('data');
            $empresa = Empresas::getEmpresaByApiKey($apiKey);
            $empresa->isEmpresaExistente($empresa->id); // Verifica se a empresa existe antes de criar o usuário

            $usuario = new Usuarios();
            $usuario->fromArray($request['data'] ?? []);
            $usuario->store(); // Chama o método verifica() para validar os campos obrigatórios

            
            $coletaRegistrada = CollectionRequest::registra($empresa->id ?? null, $usuario->id ?? null, 'pendente');

            TTransaction::close();
            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Usuário criado com sucesso',
                'data' => $usuario->toArray(),
                    'token' => [ 'token_unico' => $coletaRegistrada['model']->token_unico ]
            ];

        }catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(400);
            throw new Exception('Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    public function getById($param) {
        try {
            TTransaction::open('data');
            $usuario = new Usuarios($param['id']);
            TTransaction::close();

            return [
                'status' => 'success',
                'data' => $usuario->toArray()
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception('Erro ao buscar usuário: ' . $e->getMessage());
        }
    }

    public function update($param) {
        try {
            TTransaction::open('data');
            $usuario = Usuarios::isUsuarioExistente($param['id']); // Verifica se o usuário existe antes de atualizar
            $usuario->fromArray($param['data'] ?? []);
            $usuario->store();
            TTransaction::close();
        }catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception('Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }


}