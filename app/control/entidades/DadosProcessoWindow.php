<?php

use Adianti\Control\TAction;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Wrapper\BootstrapFormBuilder;

class DadosProcessoWindow extends TWindow{

    private $form;
    
    public function __construct(){
        
        parent::__construct();
        $this->setTitle('Dados do Processo');
        $this->setSize(0.6, 0.7);
        $this->setModal(true);

        $this->form = new BootstrapFormBuilder('form_dados_processo');
        $this->form->setFieldSizes('100%');
        $this->form->generateAria();

        $processo_id = new TEntry('processo_id');
        $processo_id->setEditable(false);
        
        $cpf = new TEntry('cpf');
        $rg = new TEntry('rg');
        $ctps = new TEntry('ctps');
        $serie_uf = new TEntry('serie_uf');
        $titulo = new TEntry('titulo');
        $data_nascimento = new TDate('data_nascimento');
        $nome_pai = new TEntry('nome_pai');
        $nome_mae = new TEntry('nome_mae');
        $grau_de_instrucao = new TCombo('grau_de_instrucao');
        $raca_cor = new TCombo('raca_cor');
        $estado_civil = new TCombo('estado_civil');
        $nome_banco = new TEntry('nome_banco');
        $agencia_banco = new TEntry('agencia_banco');
        $conta_banco = new TEntry('conta_banco');
        $tipo_conta = new TCombo('tipo_conta');
        $cep = new TEntry('cep');
        $rua = new TEntry('rua');
        $numero = new TEntry('numero');
        $bairro = new TEntry('bairro');
        $cidade = new TEntry('cidade');
        $estado = new TCombo('estado');
        $created_at = new TDate('created_at');
        $created_by = new TEntry('created_by');

        $created_at->setEditable(false);
        $created_by->setEditable(false);

        $grau_de_instrucao->addItems([
            'Analfabeto' => 'Analfabeto',
            'Fundamental Incompleto' => 'Fundamental Incompleto',
            'Fundamental Completo' => 'Fundamental Completo',
            'Médio Incompleto' => 'Médio Incompleto',
            'Médio Completo' => 'Médio Completo',
            'Superior Incompleto' => 'Superior Incompleto',
            'Superior Completo' => 'Superior Completo',
            'Pós-Graduação Incompleto' => 'Pós-Graduação Incompleto',
            'Pós-Graduação Completo' => 'Pós-Graduação Completo',
            'Mestrado Incompleto' => 'Mestrado Incompleto',
            'Mestrado Completo' => 'Mestrado Completo',
            'Doutorado Incompleto' => 'Doutorado Incompleto',
            'Doutorado Completo' => 'Doutorado Completo',
            'Outro' => 'Outro'
        ]);
        $raca_cor->addItems([
            'Branca' => 'Branca',
            'Preta' => 'Preta',
            'Parda' => 'Parda',
            'Amarela' => 'Amarela',
            'Indígena' => 'Indígena'
        ]);
        $estado_civil->addItems([
            'Solteiro(a)' => 'Solteiro(a)',
            'Casado(a)' => 'Casado(a)',
            'Divorciado(a)' => 'Divorciado(a)',
            'Viúvo(a)' => 'Viúvo(a)',
            'União Estável' => 'União Estável'
        ]);
        $estado->addItems([
            'AC' => 'AC',
            'AL' => 'AL',
            'AP' => 'AP',
            'AM' => 'AM',
            'BA' => 'BA',
            'CE' => 'CE',
            'DF' => 'DF',
            'ES' => 'ES',
            'GO' => 'GO',
            'MA' => 'MA',
            'MT' => 'MT',
            'MS' => 'MS',
            'MG' => 'MG',
            'PA' => 'PA',
            'PB' => 'PB',
            'PR' => 'PR',
            'PE' => 'PE',
            'PI' => 'PI',
            'RJ' => 'RJ',
            'RN' => 'RN',
            'RS' => 'RS',
            'RO' => 'RO',
            'RR' => 'RR',
            'SC' => 'SC',
            'SP' => 'SP',
            'SE' => 'SE',
            'TO' => 'TO'
        ]);
        $tipo_conta->addItems([
            'Corrente' => 'Conta Corrente',
            'Poupança' => 'Conta Poupança'
        ]);

        $row = $this->form->addFields([new TLabel('ID Processo'), $processo_id]);
        $row->layout = ['col-sm-12'];

        $row = $this->form->addFields([new TLabel('CPF'), $cpf],
                                       [new TLabel('RG'), $rg],
                                       [new TLabel('CTPS'), $ctps]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields([new TLabel('Serie UF') ,$serie_uf],
                                      [new TLabel('Titulo Eleitor'), $titulo]);
        $row->layout = ['col-sm-6', 'col-sm-6'];

        $row = $this->form->addFields([new TLabel('Data Nascimento'), $data_nascimento],
                                       [new TLabel('Nome do Pai'), $nome_pai],
                                       [new TLabel('Nome da Mae'), $nome_mae]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields([new TLabel('Grau de Instrucao'), $grau_de_instrucao],
                                       [new TLabel('Raca/Cor'), $raca_cor],
                                       [new TLabel('Estado Civil'), $estado_civil]);
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields([new TLabel('Nome do Banco'), $nome_banco],
                                       [new TLabel('Tipo Conta'), $tipo_conta],
                                       [new TLabel('Agencia'), $agencia_banco],
                                       [new TLabel('Conta'), $conta_banco]);
        $row->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];

        $row = $this->form->addFields([new TLabel('CEP'), $cep],
                                       [new TLabel('Rua'), $rua],
                                       [new TLabel('Numero'), $numero],
                                       [new TLabel('Bairro'), $bairro]);
        $row->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];

        $row = $this->form->addFields([new TLabel('Cidade'), $cidade],
                                       [new TLabel('Estado'), $estado]);
        $row->layout = ['col-sm-6', 'col-sm-6'];

        $row = $this->form->addFields([new TLabel('Cadastrado Por'), $created_by],
                                        [new TLabel('Data de Cadastro'), $created_at]);
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
            if($inf = DadosProcesso::findCacheBy($param['id'], 'processo_id')){
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
            $service->criaDadosProcesso((array) $data);
            new TMessage('info', 'Informacoes do processo criadas com sucesso.');
            
            $this->onReload();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
        
    }
}