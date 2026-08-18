<?php

use Adianti\Database\TRecord;

class Cliente extends TRecord
{

    const TABLENAME = 'cliente';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('system_user_id');
        parent::addAttribute('razao_social');
        parent::addAttribute('cnpj');
        parent::addAttribute('api_key');
        parent::addAttribute('nome_contato');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
        parent::addAttribute('webhook_url');
        parent::addAttribute('data_cadastro');
    }


    public static function getClienteByApiKey($apiKey)
    {
        if (empty($apiKey)) {
            throw new Exception("API Key não fornecida.");
        }
        $apiKey = hash('sha256', $apiKey);
        $cliente = Cliente::where('api_key', '=', $apiKey)->first();

        if (!$cliente) {
            throw new Exception("Cliente não encontrada para a API Key informada.");
        }
        return $cliente;
    }

    public function isClienteExistente($id)
    {
        $existe = Cliente::where('id', '=', $id)->first();
        if (!$existe) {
            return throw new Exception("Cliente com ID $id não encontrada.");
        } else {
            return $existe;
        }
    }
}
