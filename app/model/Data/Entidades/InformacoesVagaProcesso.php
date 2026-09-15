<?php


use Adianti\Database\TRecord;

class InformacoesVagaProcesso extends TRecord {
    CONST tablename = 'informacoes_vaga_processo';
    CONST primarykey = 'id';
    CONST idpolicy = 'max';



    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('processo_id');
        parent::addAttribute('contrato');
        parent::addAttribute('cliente');
        parent::addAttribute('cargo');
        parent::addAttribute('admissao');
        parent::addAttribute('demissao');
        parent::addAttribute('salario');
        parent::addAttribute('valor_vt');
        parent::addAttribute('valor_vr');
        parent::addAttribute('local_trabalho');
        parent::addAttribute('horario');
        parent::addAttribute('frequencia');
        parent::addAttribute('created_at');
        parent::addAttribute('created_by');

    }


}