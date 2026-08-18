<?php

use Adianti\Database\TRecord;

class Documento extends TRecord
{

    const TABLENAME = 'documento';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('collection_request_id');
        parent::addAttribute('tipo_documento_id');
        parent::addAttribute('storage_path');
        parent::addAttribute('status');
        parent::addAttribute('motivo_rejeicao');
        parent::addAttribute('data_upload');
    }
}
