<?php

use Adianti\Database\TRecord;

class Empresas extends TRecord {
    
    CONST TABLENAME = 'empresas';
    CONST PRIMARYKEY = 'id';
    CONST IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE) {
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


    public static function getEmpresaByApiKey($apiKey) {
        if (empty($apiKey)) {
            throw new Exception("API Key não fornecida.");
        }
        $apiKey = hash('sha256', $apiKey);
        $empresa = Empresas::where('api_key', '=', $apiKey)->first();

        if (!$empresa) {
            throw new Exception("Empresa não encontrada para a API Key informada.");
        }
        return $empresa;
    }

    public function isEmpresaExistente($id) {
        $existe = Empresas::where('id', '=', $id)->first();
        if(!$existe) {
            return throw new Exception("Empresa com ID $id não encontrada.");
        }
        else {
            return $existe;
        }
    }
}