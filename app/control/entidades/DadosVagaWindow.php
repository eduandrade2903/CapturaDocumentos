<?php

use Adianti\Control\TAction;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Wrapper\BootstrapFormBuilder;

class DadosVagaWindow extends TWindow{

    private $form;
    private $id_processo;
    
    public function __construct(){
        
        parent::__construct();
        $this->setTitle('Dados da Vaga');
        $this->setSize(0.4, 0.7);
        $this->setModal(true);

        $this->form = new BootstrapFormBuilder('form_dados_vaga');
        $this->form->setFieldSizes('100%');
        $this->form->generateAria();

        $processo_id = new TEntry('processo_id');
        $processo_id->setEditable(false);
        
        $contrato = new TEntry('contrato');
        $cliente = new TEntry('cliente');
        $cargo = new TEntry('cargo');
        $admissao = new TDate('admissao');
        $demissao = new TDate('demissao');
        $salario = new TNumeric('salario', '2', ',', '.');
        $valor_vt = new TNumeric('valor_vt', '2', ',', '.');
        $valor_vr = new TNumeric('valor_vr', '2', ',', '.');
        $local_trabalho = new TEntry('local_trabalho');
        $horario = new TEntry('horario');
        $frequencia = new TEntry('frequencia');


        $row = $this->form->addFields([new TLabel('ID Processo'), $processo_id]);
        $row->layout = ['col-sm-12'];

        $row = $this->form->addFields([new TLabel('Contrato'), $contrato],
                                       [new TLabel('Cliente'), $cliente]);
        $row->layout = ['col-sm-6', 'col-sm-6'];

        $row = $this->form->addFields([new TLabel('Cargo'), $cargo],
                                      [new TLabel('Admissao') ,$admissao],
                                      [new TLabel('Demissao'), $demissao]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields([new TLabel('Salario'), $salario],
                                       [new TLabel('Valor VT'), $valor_vt],
                                       [new TLabel('Valor VR'), $valor_vr]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields([new TLabel('Local Trabalho'), $local_trabalho]);
        $row->layout = ['col-sm-12'];

        $row = $this->form->addFields([new TLabel('Horario'), $horario], [new TLabel('Frequencia'), $frequencia]);
        $row->layout = ['col-sm-6', 'col-sm-6'];

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save #2dbe68');

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        parent::add($vbox);
        
    }

    public function onClose(){
        parent::close();
    }

    public function onReload($param = null){
        $data = $this->form->getData();
        if(isset($param['id'])){
            TTransaction::open('data');
            if($inf = InformacoesVagaProcesso::findCacheBy($param['id'], 'processo_id')){
                TTransaction::close();
                $this->form->setData($inf);
            } else {
                $data->processo_id = $param['id'] ?? null;
                $this->form->setData($data);
            }
            
        }
    }

    public function onSave($param){
        $data = $this->form->getData();
        try {

            $service = new ProcessoService();
            $service->criaInformacoesVaga($data);
            new TMessage('info', 'Informacoes da vaga criadas com sucesso.');
            
            $this->onReload();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
        
    }
}
    