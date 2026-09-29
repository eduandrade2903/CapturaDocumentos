<?php

use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;

class ProcessoService {

    private ?string $cliente_id;

    public function __construct()
    {
        $this->cliente_id = $_SESSION['template']['userunitcustomcode'] ?? null;
    }

    public function criaProcesso($param)
    {
        try {

            $param['num_documento'] = str_replace('/', '', str_replace('.', '', str_replace('-', '', $param['num_documento'])));
            $param['telefone'] = str_replace(')', '', str_replace('(', '', str_replace('-', '', str_replace(' ', '', $param['telefone']))));

            TTransaction::open('data');
            $processo = $param['id'] ? new Processo($param['id']) : new Processo;
            $processo->fromArray($param);
            $processo->store();


            $collectionRequest = CollectionRequest::where('cliente_id', '=', $this->cliente_id)
                ->where('processo_id', '=', $processo->id)
                ->first();

            $isNewRequest = false;

            if (!$collectionRequest) {
                $collectionRequestNew = CollectionRequest::registra($this->cliente_id, $processo->id, 'pendente');
                $collectionRequest = $collectionRequestNew['model'];
                $isNewRequest = true;
            }

            $documentos_existentes = [];
            if (!$isNewRequest) {
                $docs_atuais = CollectionRequestDocumento::where('collection_request_id', '=', $collectionRequest->id)->load();
                if ($docs_atuais) {
                    foreach ($docs_atuais as $doc) {
                        $documentos_existentes[] = $doc->tipo_documento_id;
                    }
                }
            }

            if (!empty($param['tipos_documentos'])) {
                foreach ($param['tipos_documentos'] as $tipo_documento_id) {
                    if (!in_array($tipo_documento_id, $documentos_existentes)) {
                        $collectionrequestdocumento = new CollectionRequestDocumento();
                        $collectionrequestdocumento->collection_request_id = $collectionRequest->id;
                        $collectionrequestdocumento->tipo_documento_id = $tipo_documento_id;
                        $collectionrequestdocumento->status = 'pendente';
                        $collectionrequestdocumento->data_criacao = date('Y-m-d H:i:s');
                        $collectionrequestdocumento->store();
                    }
                }
            }

            EmailService::enviaEmailProcessoNovo($processo);

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception($e->getMessage());
        }
    }
    

    public function criaDadosProcesso (Array $dadosProcesso) {

        try {
            if (isset($dadosProcesso['cpf'])) {
                $dadosProcesso['cpf'] = str_replace(['.', '-', '/'], '', (string) $dadosProcesso['cpf']);
            }
            if (isset($dadosProcesso['rg'])) {
                $dadosProcesso['rg'] = str_replace(['.', '-', '/'], '', (string) $dadosProcesso['rg']);
            }
            if (isset($dadosProcesso['ctps'])) {
                $dadosProcesso['ctps'] = str_replace(['.', '-', '/'], '', (string) $dadosProcesso['ctps']);
            }
            if (isset($dadosProcesso['serie_uf'])) {
                $dadosProcesso['serie_uf'] = str_replace(['.', '-', '/'], '', (string) $dadosProcesso['serie_uf']);
            }
            if (isset($dadosProcesso['titulo'])) {
                $dadosProcesso['titulo'] = str_replace(['.', '-', '/'], '', (string) $dadosProcesso['titulo']);
            }
            if (isset($dadosProcesso['cep'])) {
                $dadosProcesso['cep'] = str_replace(['.', '-', '/'], '', (string) $dadosProcesso['cep']);
            }
            TTransaction::open('data');
            if(empty($dadosProcesso)) { 
                throw new Exception("Dados do processo não podem ser nulos.");
            }

            $novoProcesso = new DadosProcesso;
            $novoProcesso->fromArray($dadosProcesso);
            $novoProcesso->created_at = date('Y-m-d H:i:s');
            $novoProcesso->created_by = TSession::getValue('userid');
            $novoProcesso->store();

            TTransaction::close();

            return $novoProcesso;
        }catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao criar dados do processo: " . $e->getMessage());

        }

    }

    public function criaInformacoesVaga (object $dadosVaga)
    {
        $arrayVaga = (array) $dadosVaga;
        try {
           
            $dados = new InformacoesVagaProcesso();
            $dados->fromArray($arrayVaga);
            $dados->created_at = date('Y-m-d H:i:s');
            $dados->created_by = TSession::getValue('userid');
            $dados->store();

            

            return $dados;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao criar informacoes da vaga: " . $e->getMessage());
        }
    }

    public function criaPdfProcesso($id_processo)
    {
        try {
            TTransaction::open('data');
            $dados_pessoais = DadosProcesso::where('processo_id', '=', $id_processo)->first();
            $dados_vaga = InformacoesVagaProcesso::where('processo_id', '=', $id_processo)->first();
            $coleta = CollectionRequest::where('processo_id', '=', $id_processo)->first();
            TTransaction::close();

            if (!$coleta || empty($coleta->token_unico)) {
                throw new Exception("Token da coleta não encontrado para enviar o PDF ao S3.");
            }

            $caminhoXlsx = GenericService::xlsx($id_processo, $dados_pessoais, $dados_vaga);
            $absXlsx = realpath($caminhoXlsx) ?: $caminhoXlsx;
            $nomeXlsxS3 = basename($caminhoXlsx);

            // 1. Gera o PDF localmente na pasta temporária (app/output)
            $caminhoRelativo = GenericService::pdf($id_processo, $dados_pessoais, $dados_vaga);
            $caminhoAbsoluto = realpath($caminhoRelativo) ?: $caminhoRelativo;
            $nomeArquivoS3 = basename($caminhoRelativo); // ficha_processo_{id}.pdf

            // 2. Faz o upload para a AWS S3 em: documentos/{token_unico}/ficha_processo_{id}.pdf
            $s3Url = AwsService::uploadFile($coleta->token_unico, $nomeArquivoS3, $caminhoAbsoluto);
            $s3UrlXlsx = AwsService::uploadFile($coleta->token_unico, $nomeXlsxS3, $absXlsx);

            // 3. Gera a URL pré-assinada temporária para o front-end abrir o PDF
            $presignedUrl = AwsService::getTemporaryUrl($coleta->token_unico, $nomeArquivoS3);
            $presignedUrlXlsx = AwsService::getTemporaryUrl($coleta->token_unico, $nomeXlsxS3);

            

            return [
                'nome_arquivo'  => $nomeArquivoS3,
                's3_url'        => $s3Url,
                'presigned_url' => $presignedUrl,
                'nome_arquivo_xlsx' => $nomeXlsxS3,
                's3_url_xlsx' => $s3UrlXlsx,
                'presigned_url_xlsx' => $presignedUrlXlsx
            ];
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            throw new Exception("Erro ao gerar e enviar PDF para S3: " . $e->getMessage());
        }
    }
}