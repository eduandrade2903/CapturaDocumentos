<?php

use Adianti\Database\TRecord;

class Usuarios extends TRecord {

    CONST TABLENAME = 'usuarios';
    CONST PRIMARYKEY = 'id';
    CONST IDPOLICY = 'max';


    public function __construct($id = null, $ObjectLoad = true) {
        parent::__construct($id, $ObjectLoad);
        parent::addAttribute('nome_completo');
        parent::addAttribute('cpf');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
    }

    public function verifica() {
    if (empty($this->nome_completo) || empty($this->cpf)) {
       return false;
    }
    return true;
   }
}