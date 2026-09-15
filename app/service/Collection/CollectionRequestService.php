<?php

use Adianti\Database\TTransaction;

class CollectionRequestService 
{
    private string $token_unico;

    /*
    * Construtor
    */
    public function __construct($token_unico = null)
    {
        if ($token_unico) {
            $this->token_unico = $token_unico;
        }
    }

    /*
    * Busca todas as collections de um cliente
    * @param string $id_cliente
    * @return array<CollectionRequest>
    */
    public function buscarCollectionsCliente(string $id_cliente): array
    {
        try {
            TTransaction::open('data');
            $collections = CollectionRequest::where('cliente_id', '=', $id_cliente)->load();
            TTransaction::close();
            
            return $collections;
        }catch(Exception $e){
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
    }

    /*
    * Renova a collection para status pendente e data de expiração para +2 dias se a data atual for maior que a data de expiração
    * @return bool
    */
    public function renovarCollection(): bool
    {
        try {

            TTransaction::open('data');
            $collection = CollectionRequest::where('token_unico', '=', $this->token_unico)->first();

            if(!$collection){
                TTransaction::close();
                throw new Exception('CollectionRequest não encontrada.');
            }

            $dataExpiracao = new DateTime($collection->data_expiracao);
            $agora = new DateTime();
            
            if($dataExpiracao < $agora) 
            {
                $collection->status = 'PENDENTE';
                $collection->data_expiracao = (new DateTime())->modify('+2 days')->format('Y-m-d H:i:s');
                $collection->store();
                TTransaction::close();
                return true;
            } else {
                TTransaction::close();
                return false;
            }
            
        }catch(Exception $e){
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
       
    }

    /**
     * Faz o download de todos os documentos de uma collection em formato zip.
     * @author Eduardo Guimarães
     * @return string
     */
    public function baixarDocumentosZip(): string
    {
        if(!$this->token_unico){
            throw new Exception('Token único não informado.');
        }
        try {
            $nomeZip = "collection_{$this->token_unico}_" . date('Ymd_His') . ".zip";
            $caminhoRelativo = "tmp/" . $nomeZip;

            $caminhoZip = AwsService::downloadFolderAsZip($this->token_unico, $caminhoRelativo);
            return $caminhoZip;
        } catch (Exception $e) {
            throw new Exception("Falha ao baixar zip da coleção: " . $e->getMessage());
        } finally {
            GenericService::limparArquivos('tmp', 'zip', 2);
        }
    }
}

?>