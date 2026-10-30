<?php

use Adianti\Database\TRecord;


class DadosDependenteProcesso extends TRecord{
    CONST tablename = 'dados_dependente_processo';
    CONST primarykey = 'id';

    CONST idpolicy = 'max';


    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::addAttribute($id, $callObjectLoad);
        parent::addAttribute('processo_id');
        parent::addAttribute('nome_filho');
        parent::addAttribute('data_nascimento');
        parent::addAttribute('cpf');
    }
}