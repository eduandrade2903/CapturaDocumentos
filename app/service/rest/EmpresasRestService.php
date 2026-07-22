<?php

use Adianti\Database\TTransaction;
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
        TTransaction::open('data');
        $data = $request['data'] ?? [];
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

        TTransaction::close();

        http_response_code(201); // Set HTTP status code to 201 Created
        return [
            'status' => 'success',
            'message' => 'Empresa criada com sucesso',
            'api_key' => $empresa->api_key
        ];
    }
}

