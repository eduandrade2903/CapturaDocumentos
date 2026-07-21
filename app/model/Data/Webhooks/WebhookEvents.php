<?php

use Adianti\Database\TRecord;

class WebhookEvents extends TRecord {

    const TABLENAME = 'webhook_events';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('empresa_id');
        parent::addAttribute('tipo_evento');
        parent::addAttribute('payload');
        parent::addAttribute('status_envio');
        parent::addAttribute('tentativas');
        parent::addAttribute('criado_em');
    }
}