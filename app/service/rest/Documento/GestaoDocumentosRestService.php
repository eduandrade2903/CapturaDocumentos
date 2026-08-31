<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;

class GestaoDocumentosRestService
{
    public function handle($param)
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
            $action = $param['action'] ?? '';

            
            // ==========================================
            // 1. GERAR URL DE VISUALIZAÇÃO
            // ==========================================
            if ($method === 'GET' && $action === 'visualizar') {
                $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");
                $nomeArquivo = $param['nome_arquivo'] ?? throw new Exception("Arquivo não informado");
                // Usa a sua classe AWS nativa para gerar o link temporário
                $url = AwsService::getTemporaryUrl($tokenUnico, $nomeArquivo);

                echo json_encode(['status' => 'success', 'url' => $url]);
                exit;
            }

            // ==========================================
            // 2. RECUSAR (EXCLUIR) DOCUMENTO
            // ==========================================
            if ($method === 'POST' && $action === 'recusar') {
                TTransaction::open('data');

            
                $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");
                $motivo = $param['motivo_rejeicao'] ?? throw new Exception("Motivo da rejeição não informado");
                $idDocumento = $param['id_documento'] ?? throw new Exception("ID do documento não informado");
                $documento = new Documento($idDocumento);
             

                if (empty($documento->id)) {
                    throw new Exception("Documento não encontrado no banco de dados.");
                }

                $collectionRequestId = $documento->collection_request_id;
                $tipoDocumentoId = $documento->tipo_documento_id;

                // Instancia o documento do banco (ajuste 'Documento' para o nome exato da sua model)
                $nomeArquivoS3 = basename($documento->storage_path);
                if(!empty($nomeArquivoS3)) {
                    AwsService::deleteFile($tokenUnico, $nomeArquivoS3);
                }

                $documento->storage_path = 'RECUSADO';
                $documento->status = 'REJEITADO';
                $documento->motivo_rejeicao = $motivo;
                $documento->store();

                $reqDoc = Collectionrequestdocumento::where('collection_request_id', '=', $collectionRequestId)
                    ->where('tipo_documento_id', '=', $tipoDocumentoId)
                    ->first();

                if($reqDoc) {
                    $reqDoc->status = 'REJEITADO';
                    $reqDoc->store();
                };

                $collection = new CollectionRequest($collectionRequestId);
                if ($collection) {
                    $collection->status = 'PENDENTE';
                    $collection->store();
                }
              



                TTransaction::close();

                echo json_encode(['status' => 'success', 'message' => 'Documento recusado com sucesso.']);
                exit;
            }

            throw new Exception("Ação não reconhecida.");
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            http_response_code(400);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
