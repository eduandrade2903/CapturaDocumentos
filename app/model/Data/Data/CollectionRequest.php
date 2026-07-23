<?php

use Adianti\Database\TRecord;

class CollectionRequest extends TRecord
{
    const TABLENAME = 'collection_requests';
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


    public function geraToken(){
        $token = bin2hex(random_bytes(16));
        $this->token_unico = hash('sha256', $token);
        $this->data_criacao = date('Y-m-d H:i:s');
        $this->data_expiracao = date('Y-m-d H:i:s', strtotime('+1 day')); // Token válido por 1 dia
    }

    public static function registra($empresaId, $usuarioId, $status = 'pendente') {
        $coleta = new self();
        $coleta->empresa_id = $empresaId;
        $coleta->usuario_id = $usuarioId;
        $coleta->status = $status;

        $token = $coleta->geraToken();
        $coleta->store();
        return [
            'model'       => $coleta,
            'token_limpo' => $token
        ];
    }
    
}