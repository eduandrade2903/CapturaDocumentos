<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TDropDown;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Widget\Wrapper\TDBMultiCombo;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class ProcessoPage extends TPage
{
    protected $form;
    protected $datagrid;
    protected $loaded;
    protected $pageNavigation;

    use Adianti\Base\AdiantiStandardFormListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('data'); 
        $this->setActiveRecord('Processo'); 
        $this->setDefaultOrder('id', 'asc'); 
        $this->setLimit(-1); 

        $this->form = new BootstrapFormBuilder('form_processo');
        $this->form->setFormTitle('Controle de Processo');

        $id     = new TEntry('id');
        $nome   = new TEntry('nome_completo');
        $cpf    = new TEntry('num_documento');
        $email  = new TEntry('email');
        $telefone = new TEntry('telefone');
        $tipos_documentos = new TDBMultiCombo('tipos_documentos', 'data', 'TiposDocumentos', 'id', 'nome');

        $cpf->setMask('000.000.000-00');
        $telefone->setMask('(00) 00000-0000');

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Name', 'red')],  [$nome]);
        $this->form->addFields([new TLabel('Num. Documento', 'red')], [$cpf]);
        $this->form->addFields([new TLabel('Email', 'red')], [$email]);
        $this->form->addFields([new TLabel('Telefone', 'red')], [$telefone]);
        $this->form->addFields([new TLabel('Documento Requeridos', 'red')], [$tipos_documentos]);

        $nome->addValidation('Name', new TRequiredValidator);
        $cpf->addValidation('Num. Documento', new TRequiredValidator);
        $email->addValidation('Email', new TRequiredValidator);
        $telefone->addValidation('Telefone', new TRequiredValidator);

        $this->form->addAction('Save', new TAction([$this, 'onSave']), 'fa:save green');

        $id->setEditable(FALSE);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';
     
        $col_id    = new TDataGridColumn('id', 'Id', 'left', '5%');
        $col_nome  = new TDataGridColumn('nome_completo', 'Nome', 'left', '30%');
        $col_email = new TDataGridColumn('email', 'Email', 'left', '15%');
        $cpf       = new TDataGridColumn('num_documento', 'Num. Documento', 'left', '10%');
        $token     = new TDataGridColumn('collection->token_unico', 'Link', 'center', '10%');
        $status    = new TDataGridColumn('collection->status', 'Status', 'left', '30%');

        $status->setTransformer(function($value, $object, $row) {
            if($value == 'PENDENTE'){
                return '<span class="label label-danger">' . $value . '</span>';
            }
            return '<span class="label label-success">' . $value . '</span>';
        });
        
        //Cria um botão dropdown com as ações
        $token->setTransformer(function($value, $object, $row) {
            $dropdown = new TDropDown('Ações', 'fa:list');
            $dropdown->addAction('Copiar Link', new TAction(['ProcessoPage', 'copiaLinkCollection'], ['token_unico' => $value]), 'fa:copy blue');
            $dropdown->addAction('Renovar Link', new TAction(['ProcessoPage', 'renovarTokenCollection'], ['token_unico' => $value]), 'fa:refresh blue');
            $dropdown->addAction('Conferir Documentos', new TAction(['DocumentoWindow', 'onReload'], ['id' => $object->id]), 'fa:eye blue');
            $dropdown->addAction('Baixar Documentos', new TAction(['ProcessoPage', 'baixarDocumentosZip'], ['token_unico' => $value]), 'fa:download blue');
            
            return $dropdown;
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_nome);
        $this->datagrid->addColumn($col_email);
        $this->datagrid->addColumn($cpf);
        $this->datagrid->addColumn($token);
        $this->datagrid->addColumn($status);

        $col_id->setAction(new TAction([$this, 'onReload']),   ['order' => 'id']);
        $col_nome->setAction(new TAction([$this, 'onReload']), ['order' => 'nome']);
        $status->setAction(new TAction([$this, 'onReload']),   ['order' => 'CollectionStatus']);

        $action1 = new TDataGridAction([$this, 'onEditCurtain'], ['key' => '{id}']);
        $action2 = new TDataGridAction([$this, 'onDelete'],      ['key' => '{id}']);

        $this->datagrid->addAction($action1, 'Edit',   'far:edit blue');
        $this->datagrid->addAction($action2, 'Delete', 'far:trash-alt red');

        $this->datagrid->createModel();
        $this->datagrid->disableDefaultClick();

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        $vbox->add($panel = TPanelGroup::pack('', $this->datagrid));

        $input_search = new TEntry('input_search');
        $input_search->placeholder = _t('Search');
        $input_search->setSize('100%');

        $this->datagrid->enableSearch($input_search, 'id, name');
        $panel->addHeaderWidget($input_search);

        $panel->addHeaderActionLink(_t('New'), new TAction([$this, 'onEditCurtain']), 'fa:plus green');

        parent::add($vbox);
    }

    public function onSave(array $param)
    {
        try {
            $cliente_id = $_SESSION['template']['userunitcustomcode'];

            $param['num_documento'] = str_replace('/', '', str_replace('.', '', str_replace('-', '', $param['num_documento'])));
            $param['telefone'] = str_replace(')', '', str_replace('(', '', str_replace('-', '', str_replace(' ', '', $param['telefone']))));

            TTransaction::open('data');
            $processo = $param['id'] ? new Processo($param['id']) : new Processo;
            $processo->fromArray($param);
            $processo->store();


            $collectionRequest = CollectionRequest::where('cliente_id', '=', $cliente_id)
                ->where('processo_id', '=', $processo->id)
                ->first();

            if (!$collectionRequest) {
                $collectionRequestNew = CollectionRequest::registra($cliente_id, $processo->id, 'pendente');

                foreach ($param['tipos_documentos'] as $tipo_documento_id) {
                    $collectionrequestdocumento = new CollectionRequestDocumento();
                    $collectionrequestdocumento->collection_request_id = $collectionRequestNew['model']->id;
                    $collectionrequestdocumento->tipo_documento_id = $tipo_documento_id;
                    $collectionrequestdocumento->status = 'pendente';
                    $collectionrequestdocumento->data_criacao = date('Y-m-d H:i:s');
                    $collectionrequestdocumento->store();
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
        $this->onReload();
    }

    public function onReload($param = null)
    {

        try {
            TTransaction::open('data');
            $cliente_logada = $_SESSION['template']['userunitcustomcode'];
            $collectionService = new CollectionRequestService;
            $collections = $collectionService->buscarCollectionsCliente($cliente_logada);

            $processo_ids = [];
            if (!empty($collections)) {
                foreach ($collections as $collection) {
                    $processo_ids[] = $collection->processo_id;
                }
            }
            $processo = $processo_ids ?  Processo::where('id', 'in', $processo_ids)->load() : [];

            $this->datagrid->clear();
            $this->datagrid->addItems($processo);

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
    }

    public static function copiaLinkCollection($param)
    {
        try{
            $link = 'http://localhost:3000/public/' . $param['token_unico'];
            
            TScript::create("__adianti_copy_to_clipboard('{$link}');");
        }catch(Exception $e){
            new TMessage('error', $e->getMessage());
        }
    }

    public static function renovarTokenCollection($param)
    {
        
        try {
            $service = new CollectionRequestService($param['token_unico']);
            $result = $service->renovarCollection();
            
            if($result){
                TToast::show('success', 'Token renovado com sucesso');
            } else {
                TToast::show('info', 'Token já válido');
            }
            
        }catch(Exception $e){
            new TMessage('error', $e->getMessage());
        }
       
    }
    
    public static function onEditCurtain($param = null)
    {
        try {
            $page = TPage::create();
            $page->setTargetContainer('adianti_right_panel');
            $page->setProperty('override', 'true');
            $page->setPageName(__CLASS__);

            $btn_close = new TButton('closeCurtain');
            $btn_close->onClick = "Template.closeRightPanel();";
            $btn_close->setLabel("Fechar");
            $btn_close->setImage('fas:times');

            $embed = new self($param);
            $embed->form->addHeaderWidget($btn_close);
            $embed->onEdit($param);

            $page->add($embed->form);
            $page->show();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public static function baixarDocumentosZip($param)
    {
        try {
            $service = new CollectionRequestService($param['token_unico']);
            
            $caminhoZip = $service->baixarDocumentosZip();
            
            TPage::openFile($caminhoZip, 'documentos.zip');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

}
