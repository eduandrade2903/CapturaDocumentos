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
    
}