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

        // 1. Identifica o cliente
        if (!empty($this->API_KEY)) {
            $this->cliente_logado = Cliente::getClienteByApiKey($this->API_KEY);
        } elseif (!empty($userid)) {
            $this->cliente_logado = Cliente::where('system_user_id', '=', $userid)->first();
        }

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
                // 💡 O DESVIO SEGURO: Se o Angular pedir a action 'salvar_dados', executa esta função.
                if (isset($param['action']) && $param['action'] === 'salvar_dados') {
                    return $this->createDadosProcesso($param);
                }

                // Caso contrário, segue o fluxo normal de criação de processo
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

            // 1. Cria o candidato (Processo)
            $processo = new Processo();
            $processo->fromArray($request['data'] ?? []);
            $processo->store();

            // 2. Registra a coleta e os documentos solicitados
            $coletaRegistrada = CollectionRequest::registra($cliente->id, $processo->id, 'pendente');
            Collectionrequestdocumento::criaCollectionDocumento($coletaRegistrada['model']->id, $request['data']['tipos_documentos']);

            // 3. NOVO: Salva os dados da Vaga (se forem enviados no payload)
            if (!empty($request['data']['dados_vaga'])) {
                $dadosVaga = $request['data']['dados_vaga'];

                // Injeta o ID do processo recém-criado na vaga
                $dadosVaga['processo_id'] = $processo->id;

                // O método criaInformacoesVaga espera um object
                $processoService = new ProcessoService();
                $processoService->criaInformacoesVaga((object) $dadosVaga);
            }

            TTransaction::close();
            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Processo e Vaga criados com sucesso',
                'data' => $processo->toArray(),
                'token' => ['token_unico' => $coletaRegistrada['model']->token_unico]
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Erro ao criar usuário: ' . $e->getMessage()
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

            if (empty($collectionRequests)) {
                TTransaction::close();
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'Nenhum processo encontrado.'
                ];
            }

            // 1. Carrega o processo base
            $processo = Processo::find($param['id']);
            $arrayProcesso = $processo->toArray();

            // 2. 💡 CARREGA OS DADOS DO CANDIDATO
            $dadosProcesso = DadosProcesso::where('processo_id', '=', $processo->id)->first();
            if ($dadosProcesso) {
                $arrayProcesso['dados_processo'] = $dadosProcesso->toArray();
            }

            // 3. 💡 CARREGA OS DADOS DA VAGA
            $dadosVaga = InformacoesVagaProcesso::where('processo_id', '=', $processo->id)->first();
            if ($dadosVaga) {
                $arrayProcesso['dados_vaga'] = $dadosVaga->toArray();
            }

            TTransaction::close();
            http_response_code(200);
            return [
                'status' => 'success',
                'data' => $arrayProcesso
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

            $coleta = CollectionRequest::where('cliente_id', '=', $cliente->id)
                ->where('processo_id', '=', $param['id'])
                ->first();

            if (!$coleta) {
                TTransaction::close();
                http_response_code(400);
                throw new Exception('Nenhum processo encontrado.');
            }

            // 1. Atualiza os dados do Processo normalmente
            $processo = new Processo($param['id']);
            $processo->fromArray($param['data'] ?? []);
            $processo->store();

            $concluir = isset($param['data']['status']) && strtoupper($param['data']['status']) === 'CONCLUIDO';

            // 2. Atualiza o status da coleta
            if ($concluir) {
                $coleta->status = 'CONCLUIDO';
                $coleta->store();
            }

            TTransaction::close();

            // 3. O GATILHO DO DOSSIÊ: Gera o PDF e envia para a AWS S3
            $dossie = null;
            if ($concluir) {
                $processoService = new ProcessoService();
                $dossie = $processoService->criaPdfProcesso($processo->id);
            }

            http_response_code(200);
            return [
                'status' => 'success',
                'data'   => $processo->toArray(),
                'dossie' => $dossie
            ];
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            http_response_code(500);
            return [
                'status'  => 'error',
                'message' => 'Erro ao atualizar processo: ' . $e->getMessage()
            ];
        }
    }
    public function createDadosProcesso($param)
    {
        try {
            TTransaction::open('data');
            $cliente = $this->cliente_logado;

            $dadosDoProcesso = $param['dadosProcesso'] ?? null;
            if (!empty($dadosDoProcesso)) {
                $dadosDoProcessoService = new ProcessoService();
                $resultado = $dadosDoProcessoService->criaDadosProcesso($dadosDoProcesso);
            } else {
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'Nenhum dado do processo foi informado.'
                ];
            }
            TTransaction::close();
            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Dados do processo criados com sucesso',
                'data' => $resultado ? $resultado->toArray() : []
            ];
        } catch (Exception $e) {
            TTransaction::rollback();
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Erro ao criar dados do processo: ' . $e->getMessage()
            ];
        }
    }
}
