<?php

use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;

class ProcessoService {


public function criaDadosProcesso (Array $dadosProcesso) {

try {
    TTransaction::open('data');
    if(empty($dadosProcesso)) { 
        throw new Exception("Dados do processo não podem ser nulos.");
    }

    $novoProcesso = new DadosProcesso;
    $novoProcesso->fromArray($dadosProcesso);
    $novoProcesso->created_at = date('Y-m-d H:i:s');
    $novoProcesso->created_by = TSession::getValue('userid');
    $novoProcesso->store();

}catch (Exception $e) {
    throw new Exception("Erro ao criar dados do processo: " . $e->getMessage());

}

}
}