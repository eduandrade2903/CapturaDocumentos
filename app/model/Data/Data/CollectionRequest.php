<?php

use Adianti\Database\TRecord;

class CollectionRequest extends TRecord
{
    const TABLENAME = 'collection_request';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}


    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('empresa_id');
        parent::addAttribute('usuario_id');
        parent::addAttribute('token_unico');
        parent::addAttribute('status');
        parent::addAttribute('data_criacao');
        parent::addAttribute('data_expiracao');
    }

    public function getEmpresa()
    {
        return new Empresas($this->empresa_id);
    }

    public function getUsuario()
    {
        return new Usuarios($this->usuario_id);
    }

    public static function isCollectionRequestExistente($id) {
        $existe = CollectionRequest::where('id', '=', $id)->first();
        if(!$existe) {
            return throw new Exception("Solicitação de coleta com ID $id não encontrada.");
        }
        else {
            return $existe;
        }
    }

    public static function verificaCamposObrigatorios($data) {
        if (empty($data['empresa_id']) || empty($data['usuario_id']) || empty($data['token_unico']) || empty($data['status'])) {
            return false;
        }
        return true;
    }
    
}