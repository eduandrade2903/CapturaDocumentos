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

    if (empty($this->nome_completo) || empty($this->cpf )) {
       return false;
    }
     if(Usuarios::where('cpf', '=', $this->cpf)->first()) {
        throw new Exception("CPF já cadastrado no sistema.");
     }
     return true;
   }

   public static function isUsuarioExistente($id) {
    $existe = Usuarios::where('id', '=', $id)->first();
    if(!$existe) {
        return throw new Exception("Usuário com ID $id não encontrado.");
    }
    else {
        return $existe;
    }
   }

   public function store() { 
    if (!$this->verifica()) {
        throw new Exception("Campos obrigatórios não preenchidos.");
    }
    parent::store();
   }
}