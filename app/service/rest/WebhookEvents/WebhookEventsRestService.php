<?php

use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Service\AdiantiRecordService;

class WebhookEventsRestService extends AdiantiRecordService
{
    const DATABASE      = 'data';
    const ACTIVE_RECORD = 'WebhookEvents';
    const ATTRIBUTES    = ['id', 'empresa_id', 'tipo_evento', 'payload', 'status_envio', 'tentativas', 'criado_em'];

    protected string $API_KEY;

    public function handle($param)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $headers = AdiantiCoreApplication::getHeaders();
        $this->API_KEY = $headers['api_key'] ?? null;
        
        if( is_null($this->API_KEY) ) { throw new Exception('Sem autorizacao.'); }

        unset($param['class']);
        unset($param['method']);
        
        
        switch( $method )
        {
            case 'GET':
                return $this->loadWebhookEvents($param);
                break;
        }
    }

    private function loadWebhookEvents($param) {
        try {            
            TTransaction::open('data');
            $empresa = Empresas::where('api_key', '=', $this->API_KEY)->first();

            if(is_null($empresa)) {
                http_response_code(403);
                throw new Exception('Chave de API invalida.');
            }

            $webhookEvents = WebhookEvents::getByEmpresaId($empresa->id);
            if(empty($webhookEvents)) {
                http_response_code(404);
                throw new Exception('Nenhum webhook encontrado.');
            }

            TTransaction::close();

            http_response_code(200);
            return [ 'data' => $webhookEvents ];
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
    }
}