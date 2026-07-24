<?php

use Adianti\Widget\Template\THtmlRenderer;

class EmpresasService 
{
    public static function enviaApiEmailEmpresa(Empresas $empresa, string $api_key): void
    {
        $replaces = [];
        $replaces['razao_social'] = $empresa->razao_social;
        $replaces['contato'] = $empresa->nome_contato;
        $replaces['api_key'] = $api_key;

        $assunto = 'CRH Digital - Cadastro de Empresa';

        $html = new THtmlRenderer('app/resources/Empresas/cadastro_empresa.html');
        $html->enableSection('main', $replaces);

        MailService::send($empresa->email, $assunto, $html->getContents(), 'html');
    }
}