<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class DocumentosRestService extends AdiantiRecordService{

    protected string $API_KEY;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['Api-Key'] ?? null;

        if (is_null($this->API_KEY)) {
            throw new Exception('Sem autorizacao.');
        }

        unset($param['class']);
        unset($param['method']);


        switch ($method) {
            case 'GET':
                if (!empty($param['id'])) {
                    //return $this->getTipoDocumentosbyId($param);
                } else {
                    return $this->getDocumentos();
                }
                break;
            case 'POST':
                return $this->create($param);
                break;
            case 'PUT':
                //return $this->update($param);
                break;
            // case 'DELETE':
            //     if (!empty($param['id'])) {
            //         return $this->delete($param);
            //     } else {
            //         return $this->deleteAll($param);
            //     }
            //     break;
        }
    }

    public static function getDocumentos() {
        try {

            TTransaction::open('data');
            $documentos = Documentos::where('ativo', '=', 1)->load();

            $documentosArray = [];
            foreach ($documentos as $documento) {
                $documentosArray[] = [
                    'id' => $documento->id,
                    'nome' => $documento->nome,
                    'descricao' => $documento->descricao
                ];
            }
            TTransaction::close();
            return $documentosArray;
        }catch (Exception $e) {
            throw new Exception("Erro ao buscar documentos: " . $e->getMessage());  


        }

    }

    public static function create($param) {
        try {
            $teste = $param;
            
            // TTransaction::open('data');
            // $documento = new Documentos();
            // $documento->request_id = $param['request_id'];
            // $documento->tipo_documento_id = $param['tipo_documento_id'];
            // $documento->storage_path = $param['storage_path'];
            // $documento->status = $param['status'];
            // $documento->motivo_rejeicao = $param['motivo_rejeicao'];
            // $documento->data_upload = $param['data_upload'];
            // $documento->store();
            // TTransaction::close();
            return ['status' => 'success', 'message' => 'Documento criado com sucesso.'];
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao criar documento: " . $e->getMessage());
        }

    }         

}