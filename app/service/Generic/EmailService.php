<?php

use Adianti\Widget\Template\THtmlRenderer;

class EmailService
{
    /**
     * Envia e-mail para o cliente com a chave da API.
     * 
     * @param \Cliente $cliente
     * @param string $api_key
     * @return void
     * @throws Exception
     */
    public static function enviaApiEmailCliente(Cliente $cliente, string $api_key): void
    {
        try {
            $replaces = [];
            $replaces['razao_social'] = $cliente->razao_social;
            $replaces['contato'] = $cliente->nome_contato;
            $replaces['api_key'] = $api_key;

            $assunto = 'CRH Digital - Cadastro de Cliente';

            $html = new THtmlRenderer('app/resources/Cliente/cadastro_cliente.html');
            $html->enableSection('main', $replaces);

            MailService::send($cliente->email, $assunto, $html->getContents(), 'html');

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function enviaEmailProcessoNovo(Processo $processo)
    {
        try{
            $replaces = [];
            $replaces['nome_completo'] = $processo->nome_completo;
            $replaces['email'] = $processo->email;
            $replaces['link'] = $processo->collection->token_unico;

            $assunto = 'CRH Digital - Cadastro de Processo';

            $html = new THtmlRenderer('app/resources/Processo/processo_novo.html');
            $html->enableSection('main', $replaces);

            MailService::send($processo->email, $assunto, $html->getContents(), 'html');
        
        }catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
