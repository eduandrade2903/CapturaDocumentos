<?php

use Adianti\Database\TRecord;

class DadosProcesso extends TRecord
{
    const TABLENAME = 'dados_processo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('processo_id');
        parent::addAttribute('cpf');
        parent::addAttribute('rg');
        parent::addAttribute('ctps');
        parent::addAttribute('serie_uf');
        parent::addAttribute('titulo');
        parent::addAttribute('pis');
        parent::addAttribute('data_nascimento');
        parent::addAttribute('nome_pai');
        parent::addAttribute('nome_mae');
        parent::addAttribute('grau_de_instrucao');
        parent::addAttribute('raca_cor');
        parent::addAttribute('estado_civil');
        parent::addAttribute('nome_banco');
        parent::addAttribute('agencia_banco');
        parent::addAttribute('conta_banco');
        parent::addAttribute('cep');
        parent::addAttribute('rua');
        parent::addAttribute('bairro');
        parent::addAttribute('cidade');
        parent::addAttribute('estado');
        parent::addAttribute('created_at');
        parent::addAttribute('created_by');
    }
}