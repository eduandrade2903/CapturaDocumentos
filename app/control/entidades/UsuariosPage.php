<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Widget\Wrapper\TDBMultiCombo;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * StandardFormDataGridCurtainView
 */
class UsuariosPage extends TPage
{
    protected $form;      // form
    protected $datagrid;  // datagrid
    protected $loaded;
    protected $pageNavigation;  // pagination component

    // trait with onSave, onEdit, onDelete, onReload, onSearch...
    use Adianti\Base\AdiantiStandardFormListTrait;

    /**
     * Class constructor
     * Creates the page, the form and the listing
     */
    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('data'); // define the database
        $this->setActiveRecord('Usuarios'); // define the Active Record
        $this->setDefaultOrder('id', 'asc'); // define the default order
        $this->setLimit(-1); // turn off limit for datagrid

        // create the form
        $this->form = new BootstrapFormBuilder('form_usuarios');
        $this->form->setFormTitle('Controle de Usuarios');

        // create the form fields
        $id     = new TEntry('id');
        $nome   = new TEntry('nome_completo');
        $cpf    = new TEntry('cpf');
        $email  = new TEntry('email');
        $telefone = new TEntry('telefone');
        $tipos_documentos = new TDBMultiCombo('tipos_documentos', 'data', 'TiposDocumentos', 'id', 'nome');

        $cpf->setMask('000.000.000-00');
        $telefone->setMask('(00) 00000-0000');

        // add the form fields
        $this->form->addFields([new TLabel('ID')],    [$id]);
        $this->form->addFields([new TLabel('Name', 'red')],  [$nome]);
        $this->form->addFields([new TLabel('CPF', 'red')], [$cpf]);
        $this->form->addFields([new TLabel('Email', 'red')], [$email]);
        $this->form->addFields([new TLabel('Telefone', 'red')], [$telefone]);
        $this->form->addFields([new TLabel('Documentos Requeridos', 'red')], [$tipos_documentos]);

        $nome->addValidation('Name', new TRequiredValidator);
        $cpf->addValidation('CPF', new TRequiredValidator);
        $email->addValidation('Email', new TRequiredValidator);
        $telefone->addValidation('Telefone', new TRequiredValidator);

        // define the form actions
        $this->form->addAction('Save', new TAction([$this, 'onSave']), 'fa:save green');

        // make id not editable
        $id->setEditable(FALSE);

        // create the datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        // add the columns        
        $col_id    = new TDataGridColumn('id', 'Id', 'right', '10%');
        $col_nome  = new TDataGridColumn('nome_completo', 'Nome', 'left', '50%');
        $cpf       = new TDataGridColumn('cpf', 'CPF', 'left', '20%');
        $status    = new TDataGridColumn('collection->status', 'Status', 'left', '20%');


        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_nome);
        $this->datagrid->addColumn($cpf);
        $this->datagrid->addColumn($status);

        $col_id->setAction(new TAction([$this, 'onReload']),   ['order' => 'id']);
        $col_nome->setAction(new TAction([$this, 'onReload']), ['order' => 'nome']);
        $status->setAction(new TAction([$this, 'onReload']), ['order' => 'CollectionStatus']);

        // define row actions
        $action1 = new TDataGridAction([$this, 'onEditCurtain'],   ['key' => '{id}']);
        $action2 = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);

        $this->datagrid->addAction($action1, 'Edit',   'far:edit blue');
        $this->datagrid->addAction($action2, 'Delete', 'far:trash-alt red');

        // create the datagrid model
        $this->datagrid->createModel();

        // wrap objects inside a table
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        //$vbox->add($this->form);

        $vbox->add($panel = TPanelGroup::pack('', $this->datagrid));



        // search box
        $input_search = new TEntry('input_search');
        $input_search->placeholder = _t('Search');
        $input_search->setSize('100%');

        // enable fuse search by column name
        $this->datagrid->enableSearch($input_search, 'id, name');
        $panel->addHeaderWidget($input_search);

        $panel->addHeaderActionLink(_t('New'), new TAction([$this, 'onEditCurtain']), 'fa:plus green');

        // pack the table inside the page
        parent::add($vbox);
    }

    public function onSave(array $param)
    {
        try {
            $empresa_id = $_SESSION['template']['userunitcustomcode'];

            $param['cpf'] = str_replace('/', '', str_replace('.', '', str_replace('-', '', $param['cpf'])));
            $param['telefone'] = str_replace(')', '', str_replace('(', '', str_replace('-', '', str_replace(' ', '', $param['telefone']))));

            TTransaction::open('data');
            $usuario = $param['id'] ? new Usuarios($param['id']) : new Usuarios;
            $usuario->fromArray($param);
            $usuario->store();


            $collectionRequest = CollectionRequest::where('empresa_id', '=', $empresa_id)
                ->where('usuario_id', '=', $usuario->id)
                ->first();

            if (!$collectionRequest) {
                $collectionRequestNew = CollectionRequest::registra($empresa_id, $usuario->id, 'pendente');

                foreach ($param['tipos_documentos'] as $tipo_documento_id) {
                    $collectionRequestDocumentos = new CollectionRequestDocumentos();
                    $collectionRequestDocumentos->request_id = $collectionRequestNew['model']->id;
                    $collectionRequestDocumentos->tipo_documento_id = $tipo_documento_id;
                    $collectionRequestDocumentos->status = 'pendente';
                    $collectionRequestDocumentos->data_criacao = date('Y-m-d H:i:s');
                    $collectionRequestDocumentos->store();
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
            $empresa_logada = $_SESSION['template']['userunitcustomcode'];
            $collectionsRequest = CollectionRequest::where('empresa_id', '=', $empresa_logada)->load();

            $usuarios_ids = [];
            if (!empty($collectionsRequest)) {
                foreach ($collectionsRequest as $collection) {
                    $usuarios_ids[] = $collection->usuario_id;
                }
            }
            $usuarios = $usuarios_ids ?  Usuarios::where('id', 'in', $usuarios_ids)->load() : [];

            $this->datagrid->clear();
            $this->datagrid->addItems($usuarios);

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
    }

    /**
     *
     */
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
}
