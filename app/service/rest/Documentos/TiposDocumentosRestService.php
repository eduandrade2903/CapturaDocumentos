<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class TiposDocumentosRestService extends AdiantiRecordService{

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
                    return $this->getTipoDocumentos();
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

    public static function getTipoDocumentos() {
        try {

            TTransaction::open('data');
            $documentos = TiposDocumentos::where('ativo', '=', 1)->load();

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
            TTransaction::open('data');
            $documento = new TiposDocumentos();
            $documento->nome = $param['nome'];
            $documento->descricao = $param['descricao'];
            $documento->store();
            TTransaction::close();
            return ['status' => 'success', 'message' => 'Tipo de documento cadastrado com sucesso.'];
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao cadastrar tipo de documento: " . $e->getMessage());
        }

    }         

}