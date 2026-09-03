<?php

use Adianti\Control\TAction;
use Adianti\Control\TWindow;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TInputDialog;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TActionLink;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

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
        $col_recusar = new TDataGridColumn('', 'Recusa', 'left');

        $col_recusar->setTransformer(function($value, $object, $row) {
            if ($object->status == 'ENVIADO') {
                $action = new TAction(['DocumentoWindow', 'onRecusar'], ['collection_request_id' => $object->collection_request_id, 'tipo_documento_id' => $object->tipo_documento_id]);
                $button = new TActionLink('Action Link2', $action, 'white', 8, '', 'far:file #ffffffff');
                $button->class = 'btn btn-danger';
                $button->setLabel('Recusar');
                $button->setImage('fa:times');
                return $button;
            }
            return '';
        });

        

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
        $this->datagrid->addColumn($col_recusar);

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

        $documento = DocumentoService::obterDadosDocumento($param['tipo_documento_id'], $param['collection_request_id']);

        $link = DocumentoService::visualizarDocumento($documento['token_unico'], urldecode($documento['nome_arquivo']));

        if (empty($link)) {
            throw new Exception('Falha ao gerar link seguro de visualização.');
        }

        AdiantiCoreApplication::loadPage('DocumentoView', 'onReload', ['link' => $link]);
    }

    public static function onRecusar($param)
    {
        $documento = DocumentoService::obterDadosDocumento($param['tipo_documento_id'], $param['collection_request_id']);
    
        $motivo   = new TEntry('motivo');
        $form = new BootstrapFormBuilder('motivo_form');
        $form->addFields( [new TLabel('Motivo: ')], [$motivo] );

        $dados['id_documento'] = $documento['documento_id'];
        $dados['token'] = $documento['token_unico'];
        $dados['motivo'] = $motivo;
        
        $form->addAction('Confirmar', new TAction(array(__CLASS__, 'onConfirm'), $dados), 'fa:save green');
        
        new TInputDialog('Qual o motivo ?', $form);
    }

    public static function onConfirm( $param )
    {
        DocumentoService::recusarDocumento($param['id_documento'], $param['token'], $param['motivo']);
        new TMessage('info', 'Documento recusado com sucesso!');
    }

    
}