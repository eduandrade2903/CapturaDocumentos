<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;

/**
 * Controller/Endpoint REST.
 * Recebe a requisição HTTP e delega o processamento para a GestaoService.
 */
class GestaoDocumentosRestService
{
    public function handle($param)
    {
        // Força a saída em JSON para o Angular
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

                // DELEGA PARA A CAMADA DE SERVIÇO
                $url = GestaoService::visualizarDocumento($tokenUnico, $nomeArquivo);

                echo json_encode(['status' => 'success', 'url' => $url]);
                exit;
            }

            // ==========================================
            // 2. RECUSAR (REJEITAR) DOCUMENTO
            // ==========================================
            if ($method === 'POST' && $action === 'recusar') {

                $idDocumento = $param['id_documento'] ?? throw new Exception("ID do documento não informado");
                $tokenUnico = $param['token_unico'] ?? throw new Exception("Token não informado");
                $motivo = $param['motivo_rejeicao'] ?? 'Documento rejeitado. Motivo não informado.';

                // DELEGA PARA A CAMADA DE SERVIÇO (Sem sujar esta classe com queries e transactions)
                GestaoService::recusarDocumento((int) $idDocumento, $tokenUnico, $motivo);

                echo json_encode(['status' => 'success', 'message' => 'Documento recusado com sucesso.']);
                exit;
            }

            throw new Exception("Ação não reconhecida.");
        } catch (Exception $e) {
            // Se qualquer erro estourar na GestaoService, ele cai aqui automaticamente
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}
