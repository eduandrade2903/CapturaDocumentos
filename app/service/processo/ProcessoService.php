<?php

use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;

class ProcessoService {


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
            TTransaction::open('data');
            $dados = new InformacoesVagaProcesso();
            $dados->fromArray($arrayVaga);
            $dados->created_at = date('Y-m-d H:i:s');
            $dados->created_by = TSession::getValue('userid');
            $dados->store();

            TTransaction::close();

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
            TTransaction::close();

            $pdf = GenericService::pdf($id_processo, $dados_pessoais, $dados_vaga);
            return $pdf;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao gerar PDF: " . $e->getMessage());
        }
    }
}