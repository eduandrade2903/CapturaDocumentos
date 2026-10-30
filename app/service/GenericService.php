<?php

use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Base\TElement;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GenericService{
    
    /**
     * Limpa os arquivos da pasta
     * @param string $pasta Caminho da pasta
     * @param string $extensao Extensão do arquivo
     * @param int $horasVida Tempo em horas para o arquivo expirar
     */
    public static function limparArquivos(string $pasta, string $extensao, int $horasVida = 2): void
    {
        // Limpa o cache de status de arquivos do PHP para ler o filemtime real atualizado
        clearstatcache();

        // Garante no mínimo 2 horas (7200 segundos) de retenção antes de apagar
        $horas = max($horasVida, 2);
        $tempoLimite = time() - ($horas * 3600);

        // Verifica sempre dentro de app/output e tmp
        $pastasParaVerificar = array_unique([$pasta, 'app/output', 'tmp']);

        foreach ($pastasParaVerificar as $diretorio) {
            $diretorio = rtrim($diretorio, '/\\');
            if (!is_dir($diretorio)) {
                continue;
            }

            // Busca apenas arquivos com a extensão enviada (ou qualquer arquivo temporário dentro de tmp)
            $arquivos = glob($diretorio . '/*.' . ltrim($extensao, '.'));

            if ($arquivos) {
                foreach ($arquivos as $arquivo) {
                    // Apaga SOMENTE se a última modificação for anterior a (agora - 2 horas)
                    if (is_file($arquivo) && filemtime($arquivo) < $tempoLimite) {
                        @unlink($arquivo);
                    }
                }
            }
        }
    }

    /**
     * Gera o PDF da Ficha do Processo
     * @param int $processo_id
     * @param mixed $dados_pessoais
     * @param mixed $dados_vaga
     * @return TElement
     */
    public static function pdf($processo_id, $dados_pessoais = null, $dados_vaga = null)
    {
        // 1. Limpa arquivos PDF com mais de 1 hora
        self::limparArquivos('app/output', 'pdf', 1);

        $openedData = false;
        try {
            if (!TTransaction::get()) {
                TTransaction::open('data');
                $openedData = true;
            }

            $processo = Processo::find($processo_id);

            if (!$dados_pessoais) {
                $dados_pessoais = DadosProcesso::where('processo_id', '=', $processo_id)->first();
            }

            if (!$dados_vaga) {
                $dados_vaga = InformacoesVagaProcesso::where('processo_id', '=', $processo_id)->first();
            }
        } catch (Exception $e) {
            // Registros não encontrados ou transação já gerenciada
        } finally {
            if ($openedData) {
                try {
                    TTransaction::close();
                } catch (Exception $e) {}
            }
        }

        // Busca nome do usuário que cadastrou
        $userName = '';
        $userId = $dados_pessoais->created_by ?? $dados_vaga->created_by ?? null;
        if ($userId) {
            try {
                TTransaction::open('permission');
                $systemUser = new SystemUser($userId);
                if ($systemUser) {
                    $userName = $systemUser->name;
                }
                TTransaction::close();
            } catch (Exception $e) {
                TTransaction::rollback();
            }
        }

        // Funções auxiliares de formatação
        $formatCurrency = function($value) {
            if ($value === null || $value === '') return '';
            if (is_numeric($value)) {
                return 'R$ ' . number_format((float)$value, 2, ',', '.');
            }
            return (string)$value;
        };

        $formatDate = function($date) {
            if (empty($date)) return '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                return date('d/m/Y', strtotime($date));
            }
            return $date;
        };

        $formatCpf = function($cpf) {
            $cpf = preg_replace('/\D/', '', (string)$cpf);
            if (strlen($cpf) === 11) {
                return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
            }
            return $cpf;
        };

        $formatCep = function($cep) {
            $cep = preg_replace('/\D/', '', (string)$cep);
            if (strlen($cep) === 8) {
                return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
            }
            return $cep;
        };

        $formatPhone = function($phone) {
            $phone = preg_replace('/\D/', '', (string)$phone);
            if (strlen($phone) === 11) {
                return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
            } elseif (strlen($phone) === 10) {
                return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6, 4);
            }
            return $phone;
        };

        // Dados da Vaga e Funcionário
        $empresa = !empty($_SESSION['template']['userunitname']) ? $_SESSION['template']['userunitname'] : 'NOME EMPRESA';
        $contrato = $dados_vaga->contrato ?? '';
        $cliente = $dados_vaga->cliente ?? '';
        $nome_candidato = $processo->nome_completo ?? '';
        $cargo = $dados_vaga->cargo ?? '';
        $admissao = $formatDate($dados_vaga->admissao ?? null);
        $salario = $formatCurrency($dados_vaga->salario ?? null);
        $valor_vt = $formatCurrency($dados_vaga->valor_vt ?? null);
        $valor_vr = $formatCurrency($dados_vaga->valor_vr ?? null);
        $local_unidade = $dados_vaga->local_trabalho ?? '';
        $horario = $dados_vaga->horario ?? '';
        $frequencia = $dados_vaga->frequencia ?? '';
        $telefone_cel = $formatPhone($processo->telefone ?? '');
        $email = $processo->email ?? '';
        $obs = $dados_vaga->obs ?? '';
        $ass = $userName;

        // Documentos
        $rg = $dados_pessoais->rg ?? '';
        $cpf = $formatCpf($dados_pessoais->cpf ?? $processo->num_documento ?? '');
        $ctps = $dados_pessoais->ctps ?? '';
        $serie = $dados_pessoais->serie_uf ?? '';
        $pis = $dados_pessoais->pis ?? '';
        $dt_nasc = $formatDate($dados_pessoais->data_nascimento ?? null);
        $pai = $dados_pessoais->nome_pai ?? '';
        $mae = $dados_pessoais->nome_mae ?? '';
        $grau_instrucao = $dados_pessoais->grau_de_instrucao ?? '';
        $raca_cor = $dados_pessoais->raca_cor ?? '';
        $estado_civil = $dados_pessoais->estado_civil ?? '';

        // Endereço
        $endereco = trim(($dados_pessoais->rua ?? '') . (!empty($dados_pessoais->numero) ? ', ' . $dados_pessoais->numero : ''));
        $bairro = $dados_pessoais->bairro ?? '';
        $cep = $formatCep($dados_pessoais->cep ?? '');
        $cidade = trim(($dados_pessoais->cidade ?? '') . (!empty($dados_pessoais->estado) ? ' - ' . $dados_pessoais->estado : ''));

        // Dados Bancários
        $banco = $dados_pessoais->nome_banco ?? '';
        $tipo_conta = $dados_pessoais->tipo_conta ?? '';
        $ag = $dados_pessoais->agencia_banco ?? '';
        $num_conta = $dados_pessoais->conta_banco ?? '';

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 15px 20px 20px 20px; size: A4 portrait; }
* { box-sizing: border-box; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #000; margin: 0; padding: 0; }
.company-header { text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 4px; text-transform: uppercase; }
.section-header { width: 100%; text-align: center; font-weight: bold; font-size: 8pt; padding: 2.5px 0; border: 1px solid #000; }
.red-header { background-color: #d32f2f; color: #fff; }
.dark-header { background-color: #555555; color: #fff; }
.data-table { width: 100%; border-collapse: collapse; margin-top: -1px; margin-bottom: 2px; }
.data-table td { border: 1px solid #000; padding: 2px 4px; vertical-align: middle; }
.cell-label { width: 30%; font-weight: bold; font-size: 7.8pt; text-transform: uppercase; }
.cell-value { width: 70%; font-size: 7.8pt; }
.text-blue { color: #0056b3; }
.grid-boxes-table { width: 100%; border-collapse: collapse; margin-top: -1px; }
.grid-boxes-table td { width: 25%; border: 1px solid #000; height: 14px; }
.footer-page { position: fixed; bottom: 5px; left: 0; right: 0; text-align: center; font-size: 9pt; }
</style>
</head>
<body>
<div class="company-header">' . htmlspecialchars($empresa) . '</div>

<div class="section-header red-header">INFORMAÇÕES VAGA E FUNCIONÁRIO</div>
<table class="data-table">
<tr><td class="cell-label">CONTRATO:</td><td class="cell-value">' . htmlspecialchars($contrato) . '</td></tr>
<tr><td class="cell-label">CLIENTE:</td><td class="cell-value">' . htmlspecialchars($cliente) . '</td></tr>
<tr><td class="cell-label">NOME CANDIDATO</td><td class="cell-value">' . htmlspecialchars($nome_candidato) . '</td></tr>
<tr><td class="cell-label">CARGO</td><td class="cell-value">' . htmlspecialchars($cargo) . '</td></tr>
<tr><td class="cell-label">ADMISSÃO:</td><td class="cell-value">' . htmlspecialchars($admissao) . '</td></tr>
<tr><td class="cell-label">SALÁRIO:</td><td class="cell-value">' . htmlspecialchars($salario) . '</td></tr>
<tr><td class="cell-label">VALOR VT:</td><td class="cell-value">' . htmlspecialchars($valor_vt) . '</td></tr>
<tr><td class="cell-label">VALOR VR:</td><td class="cell-value">' . htmlspecialchars($valor_vr) . '</td></tr>
<tr><td class="cell-label">LOCAL/UNIDADE</td><td class="cell-value">' . htmlspecialchars($local_unidade) . '</td></tr>
<tr><td class="cell-label">HORARIO:</td><td class="cell-value">' . htmlspecialchars($horario) . '</td></tr>
<tr><td class="cell-label">FREQUÊNCIA:</td><td class="cell-value">' . htmlspecialchars($frequencia) . '</td></tr>
<tr><td class="cell-label">TELEFONE CEL:</td><td class="cell-value">' . htmlspecialchars($telefone_cel) . '</td></tr>
<tr><td class="cell-label">E-MAIL</td><td class="cell-value">' . htmlspecialchars($email) . '</td></tr>
<tr><td class="cell-label">OBS</td><td class="cell-value">' . htmlspecialchars($obs) . '</td></tr>
<tr><td class="cell-label">ASS:</td><td class="cell-value text-blue">' . htmlspecialchars($ass) . '</td></tr>
</table>

<div class="section-header red-header">DOCUMENTOS</div>
<table class="data-table">
<tr><td class="cell-label">RG:</td><td class="cell-value">' . htmlspecialchars($rg) . '</td></tr>
<tr><td class="cell-label">CPF:</td><td class="cell-value">' . htmlspecialchars($cpf) . '</td></tr>
<tr><td class="cell-label">CTPS:</td><td class="cell-value">' . htmlspecialchars($ctps) . '</td></tr>
<tr><td class="cell-label">SERIE:</td><td class="cell-value">' . htmlspecialchars($serie) . '</td></tr>
<tr><td class="cell-label">PIS:</td><td class="cell-value">' . htmlspecialchars($pis) . '</td></tr>
<tr><td class="cell-label">DT. DE NASC:</td><td class="cell-value">' . htmlspecialchars($dt_nasc) . '</td></tr>
<tr><td class="cell-label">PAI:</td><td class="cell-value">' . htmlspecialchars($pai) . '</td></tr>
<tr><td class="cell-label">MÃE:</td><td class="cell-value">' . htmlspecialchars($mae) . '</td></tr>
<tr><td class="cell-label">GRAU DE INSTRUÇÃO</td><td class="cell-value">' . htmlspecialchars($grau_instrucao) . '</td></tr>
<tr><td class="cell-label">RAÇA/COR</td><td class="cell-value">' . htmlspecialchars($raca_cor) . '</td></tr>
<tr><td class="cell-label">ESTADO CIVIL:</td><td class="cell-value">' . htmlspecialchars($estado_civil) . '</td></tr>
</table>

<div class="section-header red-header">ENDEREÇO:</div>
<table class="data-table">
<tr><td class="cell-label">ENDEREÇO:</td><td class="cell-value">' . htmlspecialchars($endereco) . '</td></tr>
<tr><td class="cell-label">BAIRRO:</td><td class="cell-value">' . htmlspecialchars($bairro) . '</td></tr>
<tr><td class="cell-label">CEP:</td><td class="cell-value">' . htmlspecialchars($cep) . '</td></tr>
<tr><td class="cell-label">CIDADE</td><td class="cell-value">' . htmlspecialchars($cidade) . '</td></tr>
</table>

<div class="section-header red-header">DADOS PARA SALARIO FAMILIA MENORES DE 14 ANOS</div>
<table class="data-table">
<tr><td class="cell-label">NOME DO FILHO</td><td class="cell-value"></td></tr>
<tr><td class="cell-label">DATA DE NASC.</td><td class="cell-value"></td></tr>
<tr><td class="cell-label">CPF:</td><td class="cell-value"></td></tr>
</table>

<div class="section-header dark-header"><span style="color:#ffeb3b;">DADOS BANCARIOS (</span><span style="color:#29b6f6;">ITAU</span><span style="color:#ffffff;">/</span><span style="color:#ff3333;">BRADESCO</span><span style="color:#ffeb3b;">)</span></div>
<table class="data-table">
<tr><td class="cell-label">BANCO:</td><td class="cell-value">' . htmlspecialchars($banco) . '</td></tr>
<tr><td class="cell-label">TIPO DE CONTA</td><td class="cell-value">' . htmlspecialchars($tipo_conta) . '</td></tr>
<tr><td class="cell-label">AG:</td><td class="cell-value">' . htmlspecialchars($ag) . '</td></tr>
<tr><td class="cell-label">Nº DA CONTA</td><td class="cell-value">' . htmlspecialchars($num_conta) . '</td></tr>
</table>

<div class="section-header red-header">UNIFORMES</div>
<table class="data-table">
<tr><td class="cell-label">TAMANHO CAMISA</td><td class="cell-value"></td></tr>
<tr><td class="cell-label">TAMANHO CALÇA</td><td class="cell-value"></td></tr>
<tr><td class="cell-label">TAMANHO SAPATO</td><td class="cell-value"></td></tr>
</table>
<table class="grid-boxes-table">
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>

<div class="footer-page">1</div>
</body>
</html>';

        if (!is_dir('app/output')) {
            @mkdir('app/output', 0777, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nome fixo por processo (sobrescreve o arquivo anterior em vez de criar centenas com uniqid)
        $caminhoRelativo = 'app/output/ficha_processo_' . $processo_id . '.pdf';
        $salvou = @file_put_contents($caminhoRelativo, $dompdf->output());

        if ($salvou === false) {
            throw new Exception("Erro de permissão: Não foi possível salvar o PDF em {$caminhoRelativo}");
        }

        return $caminhoRelativo;
    }

    /**
     * Gera o XLSX da Ficha do Processo
     * @param int $processo_id
     * @param mixed $dados_pessoais
     * @param mixed $dados_vaga
     * @return string Caminho relativo do arquivo XLSX gerado
     */
    public static function xlsx($processo_id, $dados_pessoais = null, $dados_vaga = null)
    {
        // 1. Limpa arquivos XLSX com mais de 1 hora em app/output e tmp
        self::limparArquivos('app/output', 'xlsx', 1);

        $openedData = false;
        try {
            if (!TTransaction::get()) {
                TTransaction::open('data');
                $openedData = true;
            }

            $processo = Processo::find($processo_id);

            if (!$dados_pessoais) {
                $dados_pessoais = DadosProcesso::where('processo_id', '=', $processo_id)->first();
            }

            if (!$dados_vaga) {
                $dados_vaga = InformacoesVagaProcesso::where('processo_id', '=', $processo_id)->first();
            }
        } catch (Exception $e) {
            // Registros não encontrados ou transação já gerenciada
        } finally {
            if ($openedData) {
                try {
                    TTransaction::close();
                } catch (Exception $e) {
                }
            }
        }

        // Busca nome do usuário que cadastrou
        $userName = '';
        $userId = $dados_pessoais->created_by ?? $dados_vaga->created_by ?? null;
        if ($userId) {
            try {
                TTransaction::open('permission');
                $systemUser = new SystemUser($userId);
                if ($systemUser) {
                    $userName = $systemUser->name;
                }
                TTransaction::close();
            } catch (Exception $e) {
                TTransaction::rollback();
            }
        }

        // Funções auxiliares de formatação
        $formatCurrency = function ($value) {
            if ($value === null || $value === '') return '';
            if (is_numeric($value)) {
                return 'R$ ' . number_format((float)$value, 2, ',', '.');
            }
            return (string)$value;
        };

        $formatDate = function ($date) {
            if (empty($date)) return '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                return date('d/m/Y', strtotime($date));
            }
            return $date;
        };

        $formatCpf = function ($cpf) {
            $cpf = preg_replace('/\D/', '', (string)$cpf);
            if (strlen($cpf) === 11) {
                return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
            }
            return $cpf;
        };

        $formatCep = function ($cep) {
            $cep = preg_replace('/\D/', '', (string)$cep);
            if (strlen($cep) === 8) {
                return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
            }
            return $cep;
        };

        $formatPhone = function ($phone) {
            $phone = preg_replace('/\D/', '', (string)$phone);
            if (strlen($phone) === 11) {
                return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
            } elseif (strlen($phone) === 10) {
                return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6, 4);
            }
            return $phone;
        };

        // Dados da Vaga e Funcionário
        $empresa = !empty($_SESSION['template']['userunitname']) ? $_SESSION['template']['userunitname'] : 'NOME EMPRESA';
        $contrato = $dados_vaga->contrato ?? '';
        $cliente = $dados_vaga->cliente ?? '';
        $nome_candidato = $processo->nome_completo ?? '';
        $cargo = $dados_vaga->cargo ?? '';
        $admissao = $formatDate($dados_vaga->admissao ?? null);
        $salario = $formatCurrency($dados_vaga->salario ?? null);
        $valor_vt = $formatCurrency($dados_vaga->valor_vt ?? null);
        $valor_vr = $formatCurrency($dados_vaga->valor_vr ?? null);
        $local_unidade = $dados_vaga->local_trabalho ?? '';
        $horario = $dados_vaga->horario ?? '';
        $frequencia = $dados_vaga->frequencia ?? '';
        $telefone_cel = $formatPhone($processo->telefone ?? '');
        $email = $processo->email ?? '';
        $obs = $dados_vaga->obs ?? '';
        $ass = $userName;

        // Documentos
        $rg = $dados_pessoais->rg ?? '';
        $cpf = $formatCpf($dados_pessoais->cpf ?? $processo->num_documento ?? '');
        $ctps = $dados_pessoais->ctps ?? '';
        $serie = $dados_pessoais->serie_uf ?? '';
        $pis = $dados_pessoais->pis ?? '';
        $dt_nasc = $formatDate($dados_pessoais->data_nascimento ?? null);
        $pai = $dados_pessoais->nome_pai ?? '';
        $mae = $dados_pessoais->nome_mae ?? '';
        $grau_instrucao = $dados_pessoais->grau_de_instrucao ?? '';
        $raca_cor = $dados_pessoais->raca_cor ?? '';
        $estado_civil = $dados_pessoais->estado_civil ?? '';

        // Endereço
        $endereco = trim(($dados_pessoais->rua ?? '') . (!empty($dados_pessoais->numero) ? ', ' . $dados_pessoais->numero : ''));
        $bairro = $dados_pessoais->bairro ?? '';
        $cep = $formatCep($dados_pessoais->cep ?? '');
        $cidade = trim(($dados_pessoais->cidade ?? '') . (!empty($dados_pessoais->estado) ? ' - ' . $dados_pessoais->estado : ''));

        // Dados Bancários
        $banco = $dados_pessoais->nome_banco ?? '';
        $tipo_conta = $dados_pessoais->tipo_conta ?? '';
        $ag = $dados_pessoais->agencia_banco ?? '';
        $num_conta = $dados_pessoais->conta_banco ?? '';

        // Montagem da Planilha XLSX
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ficha Processo');

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(55);

        $row = 1;

        // Título da Empresa
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", mb_strtoupper($empresa));
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $addHeader = function (string $titulo, string $bgColor = 'D32F2F', string $textColor = 'FFFFFF') use ($sheet, &$row) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $titulo);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => $textColor], 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $row++;
        };

        $addRows = function (array $linhas) use ($sheet, &$row) {
            foreach ($linhas as [$label, $valor]) {
                $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("B{$row}", (string)$valor, DataType::TYPE_STRING);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                    'font'    => ['size' => 9],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $row++;
            }
        };

        $addHeader('INFORMAÇÕES VAGA E FUNCIONÁRIO');
        $addRows([
            ['CONTRATO:', $contrato],
            ['CLIENTE:', $cliente],
            ['NOME CANDIDATO', $nome_candidato],
            ['CARGO', $cargo],
            ['ADMISSÃO:', $admissao],
            ['SALÁRIO:', $salario],
            ['VALOR VT:', $valor_vt],
            ['VALOR VR:', $valor_vr],
            ['LOCAL/UNIDADE', $local_unidade],
            ['HORARIO:', $horario],
            ['FREQUÊNCIA:', $frequencia],
            ['TELEFONE CEL:', $telefone_cel],
            ['E-MAIL', $email],
            ['OBS', $obs],
            ['ASS:', $ass],
        ]);

        $addHeader('DOCUMENTOS');
        $addRows([
            ['RG:', $rg],
            ['CPF:', $cpf],
            ['CTPS:', $ctps],
            ['SERIE:', $serie],
            ['PIS:', $pis],
            ['DT. DE NASC:', $dt_nasc],
            ['PAI:', $pai],
            ['MÃE:', $mae],
            ['GRAU DE INSTRUÇÃO', $grau_instrucao],
            ['RAÇA/COR', $raca_cor],
            ['ESTADO CIVIL:', $estado_civil],
        ]);

        $addHeader('ENDEREÇO:');
        $addRows([
            ['ENDEREÇO:', $endereco],
            ['BAIRRO:', $bairro],
            ['CEP:', $cep],
            ['CIDADE', $cidade],
        ]);

        $addHeader('DADOS PARA SALARIO FAMILIA MENORES DE 14 ANOS');
        $addRows([
            ['NOME DO FILHO', ''],
            ['DATA DE NASC.', ''],
            ['CPF:', ''],
        ]);

        $addHeader('DADOS BANCARIOS (ITAU/BRADESCO)', '555555', 'FFEB3B');
        $addRows([
            ['BANCO:', $banco],
            ['TIPO DE CONTA', $tipo_conta],
            ['AG:', $ag],
            ['Nº DA CONTA', $num_conta],
        ]);

        $addHeader('UNIFORMES');
        $addRows([
            ['TAMANHO CAMISA', ''],
            ['TAMANHO CALÇA', ''],
            ['TAMANHO SAPATO', ''],
        ]);

        if (!is_dir('app/output')) {
            @mkdir('app/output', 0777, true);
        }

        $caminhoRelativo = 'app/output/ficha_processo_' . $processo_id . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($caminhoRelativo);

        if (!file_exists($caminhoRelativo)) {
            throw new Exception("Erro de permissão: Não foi possível salvar o XLSX em {$caminhoRelativo}");
        }

        return $caminhoRelativo;
    }
}


