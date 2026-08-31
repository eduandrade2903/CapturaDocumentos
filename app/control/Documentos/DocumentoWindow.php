<?php

use Adianti\Control\TAction;
use Adianti\Control\TWindow;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Util\TActionLink;
use Adianti\Wrapper\BootstrapDatagridWrapper;

/**
 * SingleWindowView
 *
 * @version    1.0
 * @package    samples
 * @subpackage tutor
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-tutor
 */
class DocumentoWindow extends TWindow
{
    private $datagrid;
    protected $pageNavigation;
    use Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();
        parent::setTitle('Documentos Requisitados');
        parent::setSize(0.6, null);
        
        $this->setDatabase('data');
        $this->setActiveRecord('CollectionRequestDocumento');
        $this->setDefaultOrder('id', 'desc');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        $col_id = new TDataGridColumn('id', 'Id', 'right');
        $col_tipo_documento = new TDataGridColumn('tipo_documento->nome', 'Tipo Documento', 'left');
        $col_status = new TDataGridColumn('status', 'Status', 'left');
        $col_data_criacao = new TDataGridColumn('data_criacao', 'Data Upload', 'left');
        $col_storage_path = new TDataGridColumn('storage_path', 'Documento', 'left');

        

        $col_storage_path->setTransformer(function($value, $object, $row) {
            if ($object->status == 'ENVIADO') {
                $action = new TAction(['DocumentoWindow', 'onViewDocument'], ['collection_request_id' => $object->collection_request_id, 'tipo_documento_id' => $object->tipo_documento_id]);
                $button = new TActionLink('Action Link2', $action, 'white', 8, '', 'far:file #ffffffff');
                $button->class = 'btn btn-success';
                $button->setLabel('Visualizar');
                $button->setImage('fa:eye');
                return $button;
            }
            return '';
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_tipo_documento);
        $this->datagrid->addColumn($col_status);
        $this->datagrid->addColumn($col_data_criacao);
        $this->datagrid->addColumn($col_storage_path);

        $panel = new TPanelGroup('');
        $panel->add($this->datagrid);

        parent::add($panel);
                  
    }

    public function onReload($param) 
    {
        try 
        {
            TTransaction::open('data');
            
            $processo = Processo::find($param['id']);
            $collectionRequest = $processo->collection;
            $documentosRequisitados = CollectionRequestDocumento::where('collection_request_id', '=', $collectionRequest->id)->load();
            
            $this->datagrid->clear();
            $this->datagrid->createModel();

            if ($documentosRequisitados) {
                foreach ($documentosRequisitados as $documentoRequisitado) {
                    $documento = $documentoRequisitado;
                    $this->datagrid->addItem($documento);
                }
            }
            
            TTransaction::close();
            
        }catch(Exception $e){
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onViewDocument($param)
    {

        TTransaction::open('data');
        $documento = Documento::where('tipo_documento_id', '=', $param['tipo_documento_id'])->where('collection_request_id', '=', $param['collection_request_id'])->first();
        TTransaction::close();
        $recorte = $_ENV['AWS_LINK'];
        
        $caminho_nome = str_replace($recorte, '',$documento->storage_path );
        $dados = explode('/', $caminho_nome);
        $token = $dados[0];
        $nome_arquivo = urldecode($dados[1]);

        $awsService = new AwsService();
        $link = $awsService->getTemporaryUrl($token, $nome_arquivo);

        if (empty($link)) {
            throw new Exception('Falha ao gerar link seguro de visualização.');
        }

        AdiantiCoreApplication::loadPage('DocumentoView', 'onReload', ['link' => $link]);
    }

    
}