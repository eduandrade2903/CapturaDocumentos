<?php

use Adianti\Database\TTransaction;

/**
 * Classe responsável por isolar as regras de negócio de Gestão de Documentos.
 */
class DocumentoService
{
    /**
     * Gera a URL temporária de visualização no S3
     */
    public static function visualizarDocumento(string $tokenUnico, string $nomeArquivo): string
    {
        return AwsService::getTemporaryUrl($tokenUnico, $nomeArquivo);
    }

    /**
     * Rejeita um documento: apaga do S3 e atualiza o status/motivo no banco
     */
    public static function recusarDocumento(int $idDocumento, string $tokenUnico, string $motivo): void
    {
        TTransaction::open('data');

        try {
            $documento = new Documento($idDocumento);
            if (empty($documento->id)) {
                throw new Exception("Documento não encontrado no banco de dados.");
            }

            $collectionRequestId = $documento->collection_request_id;
            $tipoDocumentoId = $documento->tipo_documento_id;

            // 1º: Deleta do S3
            $nomeArquivoS3 = basename($documento->storage_path);
            if (!empty($nomeArquivoS3)) {
                AwsService::deleteFile($tokenUnico, $nomeArquivoS3);
            }

            // 2º: Atualiza o Documento principal
            $documento->storage_path = null;
            $documento->status = 'REJEITADO';
            $documento->motivo_rejeicao = $motivo;
            $documento->store();

            // 3º: Atualiza a tabela auxiliar de requisitos
            $reqDoc = Collectionrequestdocumento::where('collection_request_id', '=', $collectionRequestId)
                ->where('tipo_documento_id', '=', $tipoDocumentoId)
                ->first();

            if ($reqDoc) {
                $reqDoc->status = 'REJEITADO';
                $reqDoc->store();
            }

            // 4º: Volta o Processo para pendente
            $collection = new CollectionRequest($collectionRequestId);
            if ($collection) {
                $collection->status = 'PENDENTE';
                $collection->store();
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e; // Repassa o erro para o RestService tratar
        }
    }
}
