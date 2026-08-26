<?php

use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Service\AdiantiRestService;

class PerfilRestService implements AdiantiRestService
{
    public static function getInfo($param)
    {
        $userid = TSession::getValue('userid');

        if (!$userid) {
            throw new Exception("Usuário não autenticado");
        }

        TTransaction::open('permission');
        $user = new SystemUser($userid);
        $grupos = $user->getSystemUserGroupIds();
        TTransaction::close();

        // Busca a empresa vinculada ao usuário logado
        TTransaction::open('data');
        $cliente = Cliente::where('system_user_id', '=', $userid)->first();
        TTransaction::close();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'groups' => $grupos,
            'cliente' => $cliente ? $cliente->toArray() : null // Devolve os dados e a api_key real[cite: 15]
        ];
    }
}
