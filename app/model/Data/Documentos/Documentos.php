<?php

use Adianti\Database\TRecord;

class Documentos extends TRecord {

    CONST TABLENAME = 'documentos';
    CONST PRIMARYKEY = 'id';
    CONST IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE) {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('request_id');
        parent::addAttribute('tipo_documento');
        parent::addAttribute('storage_path');
        parent::addAttribute('status');
        parent::addAttribute('motivo_rejeicao');
        parent::addAttribute('data_upload');
    }
}