<?php

use Adianti\Database\TRecord;

class CollectionRequestDocumento extends TRecord
{
    const TABLENAME = 'collection_request_documento';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';


    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('collection_request_id');
        parent::addAttribute('tipo_documento_id');
        parent::addAttribute('status');
        parent::addAttribute('data_criacao');
    }

    /**
     * Cria uma collection de documento para uma requisição.
     * @param mixed $requestId
     * @param array $tipoDocumentoId -> array de ids de tipos de documento
     * @return void
     */
    public static function criaCollectionDocumento($requestId, array $tipoDocumentoId)
    {
        try {
            foreach ($tipoDocumentoId as $tipoDocumento) 
            {
                $colletionRequestDocumentos = new self();
                $colletionRequestDocumentos->collection_request_id = $requestId;
                $colletionRequestDocumentos->tipo_documento_id = $tipoDocumento;
                $colletionRequestDocumentos->status = 'pendente';
                $colletionRequestDocumentos->data_criacao = date('Y-m-d H:i:s');
                $colletionRequestDocumentos->store();
            }
        } catch (Exception $e) {
            throw new Exception('Erro ao criar collection de documentos: ' . $e->getMessage());
        }
    }
}
