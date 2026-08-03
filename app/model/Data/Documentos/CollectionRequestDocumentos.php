<?php

use Adianti\Database\TRecord;

class CollectionRequestDocumentos extends TRecord
{
    const TABLENAME = 'collection_request_documentos';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

    
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('request_id');
        parent::addAttribute('tipo_documento_id');
        parent::addAttribute('status');
        parent::addAttribute('data_criacao');
    }
}
