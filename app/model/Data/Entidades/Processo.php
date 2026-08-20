<?php

use Adianti\Database\TRecord;

class Processo extends TRecord
{

    const TABLENAME = 'processo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';


    public function __construct($id = null, $ObjectLoad = true)
    {
        parent::__construct($id, $ObjectLoad);
        parent::addAttribute('nome_completo');
        parent::addAttribute('num_documento');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
    }

    public function get_collection()
    {
        $collection = CollectionRequest::where('processo_id', '=', $this->id)->first();
        return $collection;
    }

    public static function isProcessoExistente($id)
    {
        $existe = Processo::where('id', '=', $id)->first();
        if (!$existe) {
            return throw new Exception("Usuário com ID $id não encontrado.");
        } else {
            return $existe;
        }
    }
}
