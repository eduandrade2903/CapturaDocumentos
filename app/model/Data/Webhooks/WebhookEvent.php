<?php

use Adianti\Database\TRecord;

class WebhookEvent extends TRecord
{

    const TABLENAME = 'webhook_event';
    const PRIMARYKEY = 'id';
    const IDPOLICY =  'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('cliente_id');
        parent::addAttribute('tipo_evento');
        parent::addAttribute('payload');
        parent::addAttribute('status_envio');
        parent::addAttribute('tentativas');
        parent::addAttribute('criado_em');
    }

    public static function getByClienteId(string $cliente_id)
    {
        $webhookEvent = self::where('cliente_id', '=', $cliente_id)->load();
        $data = array_map(function ($webhookEvent) {
            return $webhookEvent->toArray();
        }, $webhookEvent);

        return $data;
    }
}
