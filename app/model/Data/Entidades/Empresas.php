<?php

use Adianti\Database\TRecord;

class Empresas extends TRecord {
    
    CONST TABLENAME = 'empresas';
    CONST PRIMARYKEY = 'id';
    CONST IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE) {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('razao_social');
        parent::addAttribute('cnpj');
        parent::addAttribute('api_key');
        parent::addAttribute('nome_contato');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
        parent::addAttribute('webhook_url');
        parent::addAttribute('data_cadastro');
    }
}