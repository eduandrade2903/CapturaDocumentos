<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;

class GestaoDocumentosRestService
{
    public function handle($param)
    {
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

                return ['status' => 'success', 'url' => $url];
            }

            // ==========================================
            // 2. RECUSAR (EXCLUIR) DOCUMENTO
            // ==========================================
            if ($method === 'POST' && $action === 'recusar') {
                TTransaction::open('data');

                $idDocumento = $param['id_documento'] ?? throw new Exception("ID do documento não informado");
                $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");

                // Instancia o documento do banco (ajuste 'Documento' para o nome exato da sua model)
                $documento = new Documento($idDocumento);

                if (empty($documento->id)) {
                    throw new Exception("Documento não encontrado no banco de dados.");
                }

                $nomeArquivo = $documento->arquivo; // Ajuste 'arquivo' para o nome da coluna que guarda o nome real do arquivo

                // 1º: Tenta deletar fisicamente do S3 usando a sua classe
                if (!empty($nomeArquivo)) {
                    AwsService::deleteFile($tokenUnico, $nomeArquivo);
                }

                // 2º: Deleta do banco de dados para forçar o usuário a enviar novamente
                $documento->delete();

                TTransaction::close();

                return ['status' => 'success', 'message' => 'Documento recusado. O candidato precisará reenviá-lo.'];
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
