<?php

use Adianti\Database\TRecord;

class CollectionRequest extends TRecord
{
    const TABLENAME = 'collection_request';
    const PRIMARYKEY = 'id';
    const IDPOLICY =  'max'; // {max, serial}


    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('cliente_id');
        parent::addAttribute('processo_id');
        parent::addAttribute('token_unico');
        parent::addAttribute('status');
        parent::addAttribute('data_criacao');
        parent::addAttribute('data_expiracao');
    }

    public function get_cliente()
    {
        return new Cliente($this->cliente_id);
    }

    public function get_processo()
    {
        return new Processo($this->processo_id);
    }

    public static function getByToken(string $token)
    {
        $collection = CollectionRequest::where('token_unico', '=', $token)->first();

        if (!$collection) {
            throw new Exception("Erro ao lacalizar Collection.");
        }

        $collection->processo = $collection->processo->nome_completo;
        $collection->cliente = $collection->cliente->razao_social;

        unset($collection->cliente_id);
        unset($collection->processo_id);
        return $collection;
    }

    public static function isCollectionRequestExistente($id)
    {
        $existe = CollectionRequest::where('id', '=', $id)->first();
        if (!$existe) {
            return throw new Exception("Solicitação de coleta com ID $id não encontrada.");
        } else {
            return $existe;
        }
    }


    public function geraToken()
    {
        $token = bin2hex(random_bytes(8));
        $this->token_unico = hash('sha256', $token);
        $this->data_criacao = date('Y-m-d H:i:s');
        $this->data_expiracao = date('Y-m-d H:i:s', strtotime('+1 day')); // Token válido por 1 dia
    }

    public static function registra($clienteId, $processoId, $status = 'pendente')
    {
        try {
            $coleta = new self();
            $coleta->cliente_id = $clienteId;
            $coleta->processo_id = $processoId;
            $coleta->status = $status;
            $coleta->geraToken();
            $coleta->store();
            return [
                'model'       => $coleta,
                'token_limpo' => $coleta->token_unico
            ];
        } catch (Exception $e) {
            throw new Exception('Erro ao registrar coleta: ' . $e->getMessage());
        }
    }
}
