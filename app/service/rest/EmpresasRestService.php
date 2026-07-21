<?php

use Adianti\Service\AdiantiRecordService;

/**
 * Empresas REST service
 */
class EmpresasRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'Empresas';
    const ATTRIBUTES    = ['id', 'razao_social', 'cnpj', 'api_key', 'nome_contato', 'email', 'telefone', 'webhook_url', 'data_cadastro'];
    const REST_KEY      = 'public';

    public static function newEmpresa($request)
    {
        $data = json_decode($request->getBody(), true);
        $empresa = new Empresas();
        $empresa->razao_social = $data['razao_social'];
        $empresa->cnpj = $data['cnpj'];
        $empresa->api_key = bin2hex(random_bytes(16)); // Generate a random API key
        $empresa->nome_contato = $data['nome_contato'];
        $empresa->email = $data['email'];
        $empresa->telefone = $data['telefone'];
        $empresa->webhook_url = $data['webhook_url'];
        $empresa->data_cadastro = date('Y-m-d H:i:s');
        $empresa->store();

        return [
            'status' => 'success',
            'message' => 'Empresa created successfully',
            'api_key' => $empresa->api_key
        ];
    }
}

