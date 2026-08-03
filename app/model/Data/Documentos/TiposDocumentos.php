<?php

use Adianti\Database\TRecord;

class TiposDocumentos extends TRecord {

    CONST TABLENAME = 'tipos_documentos';
    CONST PRIMARYKEY = 'id';
    CONST IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE) {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('nome');
        parent::addAttribute('descricao');
    }
}