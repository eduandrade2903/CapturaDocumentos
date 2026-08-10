<?php

use Adianti\Database\TRecord;

class ExtratedData extends TRecord {

    CONST TABLENAME = 'extrated_data';
    CONST PRIMARYKEY = 'id';
    CONST IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE) {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('collection_request_id'); 
        parent::addAttribute('payload_ocr');
        parent::addAttribute('score_confianca');
        parent::addAttribute('processado_em');

    }

}