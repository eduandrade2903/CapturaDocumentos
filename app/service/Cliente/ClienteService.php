<?php

use Adianti\Widget\Template\THtmlRenderer;

class ClienteService
{
    public static function enviaApiEmailCliente(Cliente $cliente, string $api_key): void
    {
        $replaces = [];
        $replaces['razao_social'] = $cliente->razao_social;
        $replaces['contato'] = $cliente->nome_contato;
        $replaces['api_key'] = $api_key;

        $assunto = 'CRH Digital - Cadastro de Cliente';

        $html = new THtmlRenderer('app/resources/Cliente/cadastro_cliente.html');
        $html->enableSection('main', $replaces);

        MailService::send($cliente->email, $assunto, $html->getContents(), 'html');
    }
}
