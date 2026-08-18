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

    public function verifica()
    {
        if (empty($this->nome_completo) || empty($this->num_documento)) {
            return false;
        }
        if (Processo::where('num_documento', '=', $this->num_documento)->first()) {
            throw new Exception("Número do documento já cadastrado no sistema.");
        }
        return true;
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

    public function store()
    {
        if (!$this->verifica()) {
            throw new Exception("Campos obrigatórios não preenchidos.");
        }
        parent::store();
    }
}
