<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Service\AdiantiRecordService;

class ProcessoRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'Processo';
    const ATTRIBUTES    = ['id', 'nome_completo', 'cpf', 'email', 'telefone'];

    protected string $API_KEY;

    protected $cliente_logado;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['api_key'] ?? null;

        $userid = TSession::getValue('userid');

        TTransaction::open('data');

        // 1. Tenta identificar o cliente pela API Key (Integração de sistemas externos)
        if (!empty($this->API_KEY)) {
            $this->cliente_logado = Cliente::getClienteByApiKey($this->API_KEY);
        }
        // 2. Se não tem API Key, tenta identificar pelo Token JWT (Dashboard Angular)
        elseif (!empty($userid)) {
            $this->cliente_logado = Cliente::where('system_user_id', '=', $userid)->first();
        }

        // Se nenhuma das duas formas encontrar um cliente, barra o acesso
        if (empty($this->cliente_logado)) {
            TTransaction::close();
            throw new Exception('Sem autorização. Forneça uma API Key válida ou faça login no sistema.');
        }

        TTransaction::close();

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
            $cliente = $this->cliente_logado;

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

            // SUBSTITUÍDO: Usando o cliente já validado
            $cliente = $this->cliente_logado;

            $collectionRequests = CollectionRequest::where('cliente_id', '=', $cliente->id)->load();

            if (empty($collectionRequests)) {
                TTransaction::close();
                http_response_code(200);
                return ['status' => 'success', 'data' => []]; // Retorna array vazio para o Angular
            }

            // Mesclando as informações de Processo e CollectionRequest
            $coletasMap = [];
            foreach ($collectionRequests as $coleta) {
                $coletasMap[$coleta->processo_id] = $coleta;
            }

            $processos_ids = array_keys($coletasMap);
            $processos = Processo::where('id', 'in', $processos_ids)->load();

            $dados = array_map(function ($item) use ($coletasMap) {
                $arrayProcesso = $item->toArray();
                $coletaRelacionada = $coletasMap[$item->id];

                $arrayProcesso['status'] = $coletaRelacionada->status;
                $arrayProcesso['data_criacao'] = $coletaRelacionada->data_criacao;
                $arrayProcesso['token_unico'] = $coletaRelacionada->token_unico;
                $arrayProcesso['data_expiracao'] = $coletaRelacionada->data_expiracao;

                return $arrayProcesso;
            }, $processos);

            TTransaction::close();
            return ['status' => 'success', 'data' => $dados];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return ['status' => 'error', 'message'   => 'Erro ao consultar processo: ' . $e->getMessage()];
        }
    }
    public function getById($param)
    {
        try {
            TTransaction::open('data');
            $cliente = $this->cliente_logado;
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
            $cliente = $this->cliente_logado;
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
