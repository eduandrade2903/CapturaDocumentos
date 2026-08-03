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

    protected string $API_KEY;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['Api-Key'] ?? null;

        if( is_null($this->API_KEY) ) { throw new Exception('Sem autorizacao.'); }

        unset($param['class']);
        unset($param['method']);

        if (isset($param['filters']) && is_string($param['filters'])) {
            $decodedFilters = json_decode($param['filters'], true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFilters)) {
                $param['filters'] = $decodedFilters;
            }
        }
        
        
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


    public function create(array $request)
    {
        try {
            TTransaction::open('data');
            $empresa = Empresas::getEmpresaByApiKey($this->API_KEY);
            $empresa->isEmpresaExistente($empresa->id); // Verifica se a empresa existe antes de criar o usuário
            $usuario = new Usuarios();
            $usuario->fromArray($request['data'] ?? []);
            $usuario->store(); // Chama o método verifica() para validar os campos obrigatórios

            
            $coletaRegistrada = CollectionRequest::registra($empresa->id ?? null, $usuario->id ?? null, 'pendente');
            
            if(empty($request['data']['tipos_documentos'])) throw new Exception("Nenhum tipo de documento foi informado para o usuário.");

            CollectionRequestDocumentos::criaCollectionDocumento($coletaRegistrada['model']->id, $request['data']['tipos_documentos']);
            

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