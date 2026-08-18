<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class CollectionRequestsRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'CollectionRequest';
    const ATTRIBUTES    = ['id', 'empresa_id', 'usuario_id', 'token_unico', 'status', 'data_criacao', 'data_expiracao'];

    protected string $API_KEY;

    /**
     * Método padrão para tratamento de requisições HTTP.
     * Direciona para o método específico baseado no método HTTP da requisição.
     * @param mixed $param
     */
    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['api_key'] ?? null;
        
        if( is_null($this->API_KEY) ) { throw new Exception('Sem autorizacao.'); }

        TTransaction::open('data');
        $empresa = Empresas::getEmpresaByApiKey($this->API_KEY);
        TTransaction::close();
        if( empty($empresa->id ) ) { throw new Exception('Sem autorizacao.'); }

        unset($param['class']);
        unset($param['method']);

        if (isset($param['filters']) && is_string($param['filters'])) {
            $decodedFilters = json_decode($param['filters'], true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFilters)) {
                
                if (!empty($decodedFilters) && !is_array(reset($decodedFilters))) {
                    $decodedFilters = [$decodedFilters];
                }
                
                $param['filters'] = $decodedFilters;
            }
        }
        // Insere o filtro para trazer apenas solicitações da empresa autenticada
        $param['filters'][] = ['empresa_id', '=', $empresa->id];

        
        switch( $method )
        {
            case 'GET':
                if (!empty($param['token_unico'])) {
                    return $this->getByTokenUnico($param['token_unico']);
                } elseif (!empty($param['id'])) {
                    //return $this->getCollectionRequestDocumentosById($param);
                } else {
                    //return $this->getCollectionRequestDocumentos($param);
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
            $resultado = CollectionRequest::registra(
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

    public function getByTokenUnico($param) {
    try {
        TTransaction::open('data');
        $collectionRequest = CollectionRequest::getByToken($param);

        $dados = $collectionRequest;
        
        if ($collectionRequest){
            $collectionRequestDocumentos = CollectionRequestDocumentos::where('request_id', '=', $collectionRequest['id'])->load();
            $documentos = Documentos::where('request_id', '=', $collectionRequest['id'])->load();

            // AJUSTE AQUI: Injetando o nome do documento direto na resposta
            $dados['documentos_solicitados'] = array_map(function($item) {
                $arrayItem = $item->toArray();
                
                // Instancia o modelo TiposDocumentos baseado no ID
                $tipoDocumento = new TiposDocumentos($item->tipo_documento_id);
                
                // Cria o nó 'tipo_documento' que o Angular espera ler
                $arrayItem['tipo_documento'] = [
                    'id' => $tipoDocumento->id,
                    'nome' => $tipoDocumento->nome,
                    'descricao' => $tipoDocumento->descricao ?? 'Por favor, envie este documento.' // Se tiver a coluna descricao, ele manda.
                ];

                return $arrayItem;
            }, $collectionRequestDocumentos);

            $dados['documentos_enviados'] = array_map(function($item) {
                return $item->toArray();
            }, $documentos);

            if(empty($dados['documentos_enviados'])) { 
                $dados['documentos_enviados'] = 'Nenhum documento enviado.'; 
            }
        } else {
            throw new Exception("Token não encontrado.");
        }
        TTransaction::close();

        return [
            'status' => 'success',
            'data' => $dados
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