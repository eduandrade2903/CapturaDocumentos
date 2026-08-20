<?php

use Adianti\Database\TRecord;

class ExtractedData extends TRecord {

    CONST TABLENAME = 'extracted_data';
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