<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDateTime;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TImage;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * EmpresasPage
 *
 */
class EmpresasPage extends TPage
{
    private $form;

    use Adianti\Base\AdiantiStandardFormTrait;
    
    function __construct()
    {
        parent::__construct();

        $this->setDatabase('data');
        $this->setActiveRecord('Empresas');

        
        // create the form
        $this->form = new BootstrapFormBuilder('form_empresas');
        $this->form->setFormTitle('Informações da Empresa');
        $this->form->generateAria();


        $empresa_logada = $_SESSION['template']['userunitcustomcode'];
        TTransaction::open('data');
        $empresa = new Empresas($empresa_logada);
        TTransaction::close();

        $id = new THidden('id');
        $razao_social = new TEntry('razao_social');
        $cnpj    = new TEntry('cnpj');
        $nome_contato    = new TEntry('nome_contato');
        $email    = new TEntry('email');
        $telefone    = new TEntry('telefone');
        $webhook_url    = new TEntry('webhook_url');
        $data_cadastro    = new TDateTime('data_cadastro');
        
        // $razao_social->enableSpeechRecognition();
        // $nome_contato->enableSpeechRecognition();
        
        $nome_contato->setInnerIcon(new TImage('fa:user blue'), 'left');

        $id->setValue($empresa->id);
        $razao_social->setValue($empresa->razao_social);
        $cnpj->setValue($empresa->cnpj);
        $nome_contato->setValue($empresa->nome_contato);
        $email->setValue($empresa->email);
        $telefone->setValue($empresa->telefone);
        $webhook_url->setValue($empresa->webhook_url);
        
        $data_formatada = new DateTime($empresa->data_cadastro);
        $data_cadastro->setValue($data_formatada->format('d/m/Y H:i'));
        $data_cadastro->setMask('dd/mm/yyyy hh:ii');
        
        $razao_social->setEditable(FALSE);
        $cnpj->setEditable(FALSE);
        $email->setEditable(FALSE);
        $data_cadastro->setEditable(FALSE);

        $cnpj->setMask('99.999.999/9999-99');
        $telefone->setMask('(99) 9999-9999');

        

        
        // add the fields inside the form
        $this->form->addFields([$id]);
        $this->form->addFields( [new TLabel('Razão Social')],[$razao_social]);
        $this->form->addFields( [new TLabel('CNPJ')], [$cnpj] );
        $this->form->addFields( [new TLabel('Nome de Contato')],  [$nome_contato], 
                                [new TLabel('Email')],  [$email]);
        $this->form->addFields( [new TLabel('Telefone')],       [$telefone]);
        $this->form->addFields( [new TLabel('Webhook URL')],  [$webhook_url]);
        $this->form->addFields( [new TLabel('Data de Cadastro')],  [$data_cadastro]);


        
        
        // define the form action 
        $this->form->addAction('Salvar', new TAction(array($this, 'onSave')), 'far:check-circle green');
        $this->form->addHeaderAction('Salvar', new TAction(array($this, 'onSave')), 'fa:rocket orange');
        

        
        // wrap the page content using vertical box
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $vbox->add($this->form);

        parent::add($vbox);
    }

    public function onSave( $param ) {
        try {
            TTransaction::open('data');
            $param['cnpj'] = str_replace('/','', str_replace('.', '', str_replace('-', '', $param['cnpj'])));
            $param['telefone'] = str_replace(')','', str_replace('(', '', str_replace('-', '', str_replace(' ', '', $param['telefone']))));

            $data_form = DateTime::createFromFormat('d/m/Y H:i', $param['data_cadastro']);
            $param['data_cadastro'] = $data_form->format('Y-m-d H:i:s');
            
            $empresa = new Empresas($param['id']);
            $empresa->fromArray($param);
            $empresa->store();

            new TMessage('info', 'Dados atualizados com sucesso');
            
            TTransaction::close();
            AdiantiCoreApplication::loadPage('EmpresasPage');
            
        }catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception('Erro ao recarregar empresa: ' . $e->getMessage());
        }
    }
    
    
}
