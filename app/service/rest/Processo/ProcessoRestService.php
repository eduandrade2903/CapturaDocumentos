<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class ProcessoRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'Processo';
    const ATTRIBUTES    = ['id', 'nome_completo', 'cpf', 'email', 'telefone'];

    protected string $API_KEY;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['api_key'] ?? null;

        if (is_null($this->API_KEY)) {
            throw new Exception('Sem autorizacao.');
        }

        unset($param['class']);
        unset($param['method']);

        if (isset($param['filters']) && is_string($param['filters'])) {
            $decodedFilters = json_decode($param['filters'], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFilters)) {
                $param['filters'] = $decodedFilters;
            }
        }

        switch ($method) {
            case 'GET':
                if (!empty($param['id'])) {
                    return $this->getById($param);
                } else {
                    return $this->loadAll($param);
                }
                break;
            case 'POST':
                return $this->create($param);
                break;
            case 'PUT':
                return $this->update($param);
                break;
            default:
                http_response_code(405);
                return [
                    'status' => 'error',
                    'message' => 'Método não permitido.'
                ];
        }
    }


    public function create(array $request)
    {
        try {
            TTransaction::open('data');
            $cliente = Cliente::getClienteByApiKey($this->API_KEY);

            if (empty($request['data']['tipos_documentos'])) {
                TTransaction::close();
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'Nenhum tipo de documento foi informado para o usuário.'
                ];
            }

            $processo = new Processo();
            $processo->fromArray($request['data'] ?? []);
            $processo->store(); // Chama o método verifica() para validar os campos obrigatórios

            $coletaRegistrada = CollectionRequest::registra($cliente->id, $processo->id, 'pendente');

            Collectionrequestdocumento::criaCollectionDocumento($coletaRegistrada['model']->id, $request['data']['tipos_documentos']);

            TTransaction::close();
            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Usuário criado com sucesso',
                'data' => $processo->toArray(),
                'token' => ['token_unico' => $coletaRegistrada['model']->token_unico]
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return [
                'status' => 'error',
                'message'=> 'Erro ao criar usuário: ' . $e->getMessage()
            ];
        }
    }

    public function loadAll($request)
    {
        try {
            TTransaction::open('data');
            $cliente = Cliente::getClienteByApiKey($this->API_KEY);
            $collectionRequests = CollectionRequest::where('cliente_id', '=', $cliente->id)->load();

            if(empty($collectionRequests)) {
                TTransaction::close();
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'Nenhuma solicitação foi encontrada.'
                ];
            }

            $processos_ids = array_map(function ($item) {
                return $item->processo_id;
            }, $collectionRequests);

            $processos = Processo::where('id', 'in', $processos_ids)->load();

            $dados = array_map(function ($item) {
                return $item->toArray();
            }, $processos);

            TTransaction::close();
            return [
                'status' => 'success',
                'data' => $dados
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return [
                'status' => 'error',
                'message'   => 'Erro ao consultar processo: ' . $e->getMessage()
            ];
        }
    }
    public function getById($param)
    {
        try {
            TTransaction::open('data');
            $cliente = Cliente::getClienteByApiKey($this->API_KEY);
            $collectionRequests = CollectionRequest::where('cliente_id', '=', $cliente->id)->where('processo_id', '=', $param['id'])->first();
            $processo = null;

            if(isset($collectionRequests)){
                $processo = Processo::find($param['id']);
            }

            if(empty($collectionRequests)) {
                TTransaction::close();
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'Nenhum processo encontrado.'
                ];
            }
            
            TTransaction::close();
            http_response_code(200);
            return [
                'status' => 'success',
                'data' => $processo->toArray()
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return [
                'status' => 'error',
                'message'   => 'Erro ao consultar processo: ' . $e->getMessage()
            ];
        }
    }

    public function update($param)
    {
        try {
            TTransaction::open('data');
            $cliente = Cliente::getClienteByApiKey($this->API_KEY);
            $collectionRequests = CollectionRequest::where('cliente_id', '=', $cliente->id)->where('processo_id', '=', $param['id'])->first() ? true : false;

            if($collectionRequests) {
                $processo = new Processo($param['id']);
                $processo->fromArray($param['data']);
                $processo->store();
            } else {
                TTransaction::close();
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'Nenhum processo encontrado.'
                ];
            }
            TTransaction::close();
            http_response_code(200);
            return [
                'status' => 'success',
                'data' => $processo->toArray()
            ];            
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return [
                'status' => 'error',
                'message'   => 'Erro ao atualizar processo: ' . $e->getMessage()
            ];
        }
    }
}
