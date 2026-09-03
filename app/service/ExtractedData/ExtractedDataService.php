<?php

use Adianti\Database\TTransaction;

class ExtractedDataService
{
    private array $extractedData;
    private array $documentos = [];
    private array $organizado = [];
    private $collectionRequestId;
    private string $diretorioTmp;

    public function __construct($collectionRequestId)
    {
        $this->collectionRequestId = $collectionRequestId;

        // __DIR__ retorna: /var/www/html/app/service/ExtractedData
        // dirname(__DIR__, 3) sobe 3 níveis, retornando: /var/www/html/app
        // Assim, forçamos o PHP a olhar para a pasta tmp correta na raiz!
        $this->diretorioTmp = dirname(__DIR__, 2) . '/tmp';

        // Garante que a pasta na raiz exista
        if (!is_dir($this->diretorioTmp)) {
            @mkdir($this->diretorioTmp, 0775, true);
        }

        $conn = TTransaction::get();
        $sql = "SELECT JSON_ARRAYAGG(payload_ocr) as json_unificado 
                FROM extracted_data 
                WHERE collection_request_id = " . (int)$collectionRequestId;

        $result = $conn->query($sql);
        $row = $result->fetch(PDO::FETCH_ASSOC);

        $jsonUnificado = $row['json_unificado'] ?? '[]';
        $dadosBrutos = json_decode($jsonUnificado, true) ?? [];

        $this->documentos = array_map(function ($doc) {
            if (is_string($doc)) {
                $jsonStringLimpa = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $doc));
                $doc = json_decode($jsonStringLimpa, true) ?? [];
            }
            return is_array($doc) ? array_change_key_case($doc, CASE_UPPER) : [];
        }, $dadosBrutos);

        $this->organizarDados();
    }

    /**
     * Busca um valor dentro de um grupo de documentos filtrados
     */
    private function buscarChaves(array $grupoDocumentos, array $possibilidades)
    {
        foreach ($grupoDocumentos as $doc) {
            foreach ($possibilidades as $chave) {
                // 1. Procura no nível principal (Para documentos antigos/legados)
                if (isset($doc[$chave]) && trim((string)$doc[$chave]) !== '') {
                    return trim((string)$doc[$chave]);
                }

                // 2. Procura dentro dos blocos do Novo Padrão Canônico
                $blocos = ['METADADOS', 'DADOS_PESSOAIS', 'DADOS_COMUNS', 'DADOS_EXTRAS'];
                foreach ($blocos as $bloco) {
                    if (isset($doc[$bloco]) && is_array($doc[$bloco])) {
                        $blocoUpper = array_change_key_case($doc[$bloco], CASE_UPPER);
                        if (isset($blocoUpper[$chave]) && trim((string)$blocoUpper[$chave]) !== '') {
                            return trim((string)$blocoUpper[$chave]);
                        }
                    }
                }
            }
        }
        return null;
    }

    public function organizarDados()
    {
        // ==========================================
        // HELPERS PARA OS FILTROS E MAPEAMENTOS
        // ==========================================
        $hasKey = function ($doc, $chaves) {
            return $this->buscarChaves([$doc], (array)$chaves) !== null;
        };
        $getValue = function ($doc, $chaves) {
            return $this->buscarChaves([$doc], (array)$chaves);
        };

        // ==========================================
        // 3. SEPARAÇÃO SEMÂNTICA BLINDADA
        // ==========================================
        $dependentesDocs = array_filter($this->documentos, function ($doc) use ($getValue, $hasKey) {
            $tipo = strtoupper((string)$getValue($doc, ['TIPO_DOCUMENTO', 'DOCUMENTO', 'VINCULO']));
            return strpos($tipo, 'DEPENDENTE') !== false || $hasKey($doc, 'AVOS');
        });

        // Filtro mais restrito para escolaridade (evita lixo de outros documentos)
        $escolaridadeDocs = array_filter($this->documentos, function ($doc) use ($getValue, $hasKey) {
            $tipo = strtoupper((string)$getValue($doc, ['TIPO_DOCUMENTO', 'DOCUMENTO']));
            return in_array($tipo, ['HISTORICO ESCOLAR', 'DIPLOMA', 'COMPROVANTE DE ESCOLARIDADE', 'CERTIFICADO DE CURSO'])
                || strpos($tipo, 'HISTORICO') !== false
                || $hasKey($doc, ['INSTITUICAO', 'NOME_ESCOLA', 'CURSO']);
        });

        $bancariosDocs = array_filter($this->documentos, function ($doc) use ($getValue, $hasKey) {
            $tipo = strtoupper((string)$getValue($doc, ['TIPO_DOCUMENTO', 'DOCUMENTO']));
            return strpos($tipo, 'BANCARIA') !== false || $hasKey($doc, ['BANCO', 'AGENCIA', 'CONTA_CORRENTE', 'NUMERO_CONTA']);
        });

        $trabalhistasDocs = array_filter($this->documentos, function ($doc) use ($getValue, $hasKey) {
            $tipo = strtoupper((string)$getValue($doc, ['TIPO_DOCUMENTO', 'DOCUMENTO']));
            return in_array($tipo, ['CARTEIRA DE TRABALHO', 'PIS PASEP NIT', 'TITULO DE ELEITOR', 'CNH']) || $hasKey($doc, ['PIS_PASEP', 'ZONA', 'SECAO']);
        });

        $titularDocs = array_filter($this->documentos, function ($doc) use ($getValue, $hasKey) {
            $tipo = strtoupper((string)$getValue($doc, ['TIPO_DOCUMENTO', 'DOCUMENTO', 'VINCULO']));
            return strpos($tipo, 'DEPENDENTE') === false && !$hasKey($doc, 'AVOS');
        });

        // ==========================================
        // 4. TRATAMENTO DE FILIAÇÃO DO TITULAR
        // ==========================================
        $nomeMae = $this->buscarChaves($titularDocs, ['FILIACAO_MAE', 'MAE', 'NOME_MAE']);
        $nomePai = $this->buscarChaves($titularDocs, ['FILIACAO_PAI', 'PAI', 'NOME_PAI']);
        $filiacaoGeral = $this->buscarChaves($titularDocs, ['FILIACAO']);

        if ((!$nomeMae || !$nomePai) && $filiacaoGeral) {
            if (is_array($filiacaoGeral)) {
                $nomePai = $nomePai ?? ($filiacaoGeral[0] ?? null);
                $nomeMae = $nomeMae ?? ($filiacaoGeral[1] ?? null);
            } elseif (stripos($filiacaoGeral, ' e ') !== false) {
                $partes = preg_split('/ e /i', $filiacaoGeral);
                if (count($partes) >= 2) {
                    $nomePai = $nomePai ?? trim($partes[0]);
                    $nomeMae = $nomeMae ?? trim($partes[1]);
                }
            } else {
                $nomeMae = $nomeMae ?? $filiacaoGeral;
            }
        }

        // ==========================================
        // 5. DEDUPLICAÇÃO DE DEPENDENTES E ESCOLARIDADE
        // ==========================================

        // Agrupa dependentes pelo nome para mesclar RG + Certidão da mesma pessoa
        $dependentesAgrupados = [];
        foreach ($dependentesDocs as $dep) {
            $nome = $getValue($dep, ['NOME_PRINCIPAL', 'NOME', 'NOME_DEPENDENTE']);
            if (!$nome) continue;

            $chave = strtoupper(trim($nome));
            if (!isset($dependentesAgrupados[$chave])) {
                $dependentesAgrupados[$chave] = [
                    'nome'               => $nome,
                    'data_nascimento'    => $getValue($dep, ['DATA_DE_NASCIMENTO', 'DATA_NASCIMENTO']),
                    'cpf'                => $getValue($dep, ['CPF', 'CPF_DEPENDENTE']), // NOVA LINHA
                    'rg'                 => $getValue($dep, ['RG', 'RG_CIN', 'REGISTRO_GERAL']), // NOVA LINHA
                    'sexo'               => $getValue($dep, ['SEXO', 'GENERO']),
                    'certidao_matricula' => $getValue($dep, ['MATRICULA', 'NUMERO_MATRICULA'])
                ];
            } else {
                // Preenche os dados que faltam caso o dependente já esteja no array (Ex: mesclar Certidão com CPF)
                if (empty($dependentesAgrupados[$chave]['data_nascimento'])) $dependentesAgrupados[$chave]['data_nascimento'] = $getValue($dep, ['DATA_DE_NASCIMENTO', 'DATA_NASCIMENTO']);
                if (empty($dependentesAgrupados[$chave]['cpf'])) $dependentesAgrupados[$chave]['cpf'] = $getValue($dep, ['CPF', 'CPF_DEPENDENTE']); // NOVA LINHA
                if (empty($dependentesAgrupados[$chave]['rg'])) $dependentesAgrupados[$chave]['rg'] = $getValue($dep, ['RG', 'RG_CIN', 'REGISTRO_GERAL']); // NOVA LINHA
                if (empty($dependentesAgrupados[$chave]['sexo'])) $dependentesAgrupados[$chave]['sexo'] = $getValue($dep, ['SEXO', 'GENERO']);
                if (empty($dependentesAgrupados[$chave]['certidao_matricula'])) $dependentesAgrupados[$chave]['certidao_matricula'] = $getValue($dep, ['MATRICULA', 'NUMERO_MATRICULA']);
            }
        }

        // Limpa escolas vazias e puxa chaves corretas
        $escolaridadeAgrupada = [];
        foreach ($escolaridadeDocs as $esc) {
            $instituicao = $getValue($esc, ['INSTITUICAO', 'MUNICIPIO_ESCOLA', 'NOME_ESCOLA', 'ESCOLA', 'NOME_INSTITUICAO']);
            if (!$instituicao) continue; // Ignora se não achou nome da instituição

            $chave = strtoupper(trim($instituicao));
            $escolaridadeAgrupada[$chave] = [
                'instituicao'   => $instituicao,
                'ano_conclusao' => $getValue($esc, ['ANO_CONCLUSAO_MEDIO', 'DATA_EMISSAO', 'ANO_CONCLUSAO', 'DATA_CONCLUSAO', 'ANO']),
                'estado'        => $getValue($esc, ['ESTADO', 'UF'])
            ];
        }

        $dadosExtrasDinamicos = [];

        // Passa pelos documentos (trabalhistas, bancários e gerais do titular)
        $todosDocsTitular = array_merge($titularDocs, $trabalhistasDocs, $bancariosDocs);

        foreach ($todosDocsTitular as $doc) {
            // Verifica se a IA gerou o bloco de DADOS_EXTRAS neste documento
            $extras = $doc['DADOS_EXTRAS'] ?? ($doc['dados_extras'] ?? null);

            if (is_array($extras)) {
                foreach ($extras as $chave => $valor) {
                    $chaveUpper = strtoupper($chave);
                    // Ignora chaves que nós já mapeamos hardcoded para não duplicar no PDF
                    $chavesIgnoradas = ['BANCO', 'AGENCIA', 'CONTA', 'CONTA_CORRENTE', 'NUMERO', 'SERIE', 'ZONA', 'SECAO', 'PIS_PASEP'];

                    if (!in_array($chaveUpper, $chavesIgnoradas) && trim((string)$valor) !== '' && $valor !== null) {
                        // Formata a chave para ficar bonita (ex: "cnh_categoria" vira "Cnh Categoria")
                        $chaveFormatada = ucwords(str_replace('_', ' ', strtolower($chave)));
                        $dadosExtrasDinamicos[$chaveFormatada] = $valor;
                    }
                }
            }
        }

        // ==========================================
        // 6. MONTAGEM FINAL DO GOLDEN RECORD
        // ==========================================
        $this->organizado = [
            'dados_pessoais' => [
                'nome_completo'   => $this->buscarChaves($titularDocs, ['NOME_PRINCIPAL', 'NOME', 'NOME_COMPLETO']),
                'cpf'             => $this->buscarChaves($titularDocs, ['CPF', 'CPF_CNPJ']),
                'data_nascimento' => $this->buscarChaves($titularDocs, ['DATA_DE_NASCIMENTO', 'DATA_NASCIMENTO', 'NASCIMENTO']),
                'nome_mae'        => $nomeMae,
                'nome_pai'        => $nomePai,
                'estado_civil'    => $this->buscarChaves($titularDocs, ['ESTADO_CIVIL', 'REGIME_DE_BENS']) ?: 'Não Informado'
            ],
            'documentacao_trabalhista' => [
                'ctps_numero'    => $this->buscarChaves($trabalhistasDocs, ['NUMERO_CTPS', 'NUMERO', 'SERIE']),
                'pis_pasep'      => $this->buscarChaves($trabalhistasDocs, ['PIS_PASEP', 'PIS', 'NIS']),
                'eleitor_numero' => $this->buscarChaves($trabalhistasDocs, ['NUMERO_TITULO', 'TITULO_ELEITOR', 'NUMERO_INSCRICAO']),
                'eleitor_zona'   => $this->buscarChaves($trabalhistasDocs, ['ZONA']),
                'eleitor_secao'  => $this->buscarChaves($trabalhistasDocs, ['SECAO'])
            ],
            'dados_bancarios' => [
                'banco'   => $this->buscarChaves($bancariosDocs, ['BANCO', 'NOME_BANCO']),
                'agencia' => $this->buscarChaves($bancariosDocs, ['AGENCIA', 'NUMERO_AGENCIA']),
                'conta'   => $this->buscarChaves($bancariosDocs, ['CONTA', 'CONTA_CORRENTE', 'NUMERO_CONTA']),
                'titular' => $this->buscarChaves($bancariosDocs, ['TITULAR', 'NOME_PRINCIPAL', 'NOME'])
            ],

            // INSERE O BLOCO DINÂMICO NO FINAL DO JSON
            'dados_especificos' => $dadosExtrasDinamicos,

            'escolaridade' => array_values($escolaridadeAgrupada),
            'dependentes' => array_values($dependentesAgrupados)
        ];
    }

    
    public function createPdf()
    {
        $d = $this->organizado;

        $html = '
        <html>
        <head>
            <style>
                body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #334155; margin: 0; padding: 0; }
                .header { background-color: #0f172a; color: #ffffff; padding: 25px 20px; text-align: center; border-bottom: 4px solid #3b82f6; }
                .header h1 { margin: 0; font-size: 22px; font-weight: 500; letter-spacing: 1px; }
                .header p { margin: 5px 0 0 0; font-size: 11px; color: #94a3b8; }
                .content { padding: 20px; }
                .section-title { color: #1e293b; font-size: 14px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; margin-top: 20px; margin-bottom: 10px; text-transform: uppercase; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 5px; }
                td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
                .label { font-weight: bold; color: #64748b; width: 35%; text-transform: uppercase; font-size: 9px; }
                .value { color: #0f172a; font-weight: 500; }
                .row-striped { background-color: #f8fafc; }
                .footer { text-align: center; font-size: 9px; color: #cbd5e1; margin-top: 30px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>DOSSIÊ DE ADMISSÃO COMPLETO</h1>
                <p>GOLDEN RECORD MULTIDOMÍNIO • GERADO EM ' . date('d/m/Y H:i') . '</p>
            </div>

            <div class="content">
                <div class="section-title">👤 Dados Pessoais</div>
                <table>
                    <tr class="row-striped"><td class="label">Nome Completo</td><td class="value">' . ($d['dados_pessoais']['nome_completo'] ?? 'Não Informado') . '</td></tr>
                    <tr><td class="label">CPF</td><td class="value">' . ($d['dados_pessoais']['cpf'] ?? 'Não Informado') . '</td></tr>
                    <tr class="row-striped"><td class="label">Data de Nascimento</td><td class="value">' . ($d['dados_pessoais']['data_nascimento'] ?? 'Não Informado') . '</td></tr>
                    <tr><td class="label">Nome da Mãe</td><td class="value">' . ($d['dados_pessoais']['nome_mae'] ?? 'Não Informado') . '</td></tr>
                    <tr class="row-striped"><td class="label">Nome do Pai</td><td class="value">' . ($d['dados_pessoais']['nome_pai'] ?? 'Não Informado') . '</td></tr>
                </table>

                <div class="section-title">💼 Documentação Trabalhista & Eleitoral</div>
                <table>
                    <tr class="row-striped"><td class="label">PIS / PASEP</td><td class="value">' . ($d['documentacao_trabalhista']['pis_pasep'] ?? 'Não Informado') . '</td></tr>
                    <tr><td class="label">Título de Eleitor</td><td class="value">' . ($d['documentacao_trabalhista']['eleitor_numero'] ?? 'Não Informado') . ' (Zona: ' . ($d['documentacao_trabalhista']['eleitor_zona'] ?? '-') . ' / Seção: ' . ($d['documentacao_trabalhista']['eleitor_secao'] ?? '-') . ')</td></tr>
                </table>

                <div class="section-title">🏦 Dados Bancários</div>
                <table>
                    <tr class="row-striped"><td class="label">Banco / Agência / Conta</td><td class="value">' . ($d['dados_bancarios']['banco'] ?? '-') . ' / Ag: ' . ($d['dados_bancarios']['agencia'] ?? '-') . ' / Conta: ' . ($d['dados_bancarios']['conta'] ?? '-') . '</td></tr>
                    <tr><td class="label">Titular</td><td class="value">' . ($d['dados_bancarios']['titular'] ?? 'Não Informado') . '</td></tr>
                </table>';

        if (!empty($d['dados_especificos'])) {
            $html .= '<div class="section-title">📌 Dados Complementares e Específicos</div><table>';
            $striped = true;
            foreach ($d['dados_especificos'] as $label => $valor) {
                $class = $striped ? 'class="row-striped"' : '';
                $html .= '<tr ' . $class . '><td class="label">' . htmlspecialchars($label) . '</td><td class="value">' . htmlspecialchars($valor) . '</td></tr>';
                $striped = !$striped;
            }
            $html .= '</table>';
        }


        if (!empty($d['escolaridade'])) {
            $html .= '<div class="section-title">🎓 Histórico Escolar</div><table>';
            foreach ($d['escolaridade'] as $esc) {
                $html .= '<tr class="row-striped"><td class="label">Instituição</td><td class="value">' . ($esc['instituicao'] ?? 'Escola') . ' (Conclusão/Ano: ' . ($esc['ano_conclusao'] ?? '-') . ')</td></tr>';
            }
            $html .= '</table>';
        }

        // Se houver dependentes mapeados
        if (!empty($d['dependentes'])) {
            $html .= '<div class="section-title">👶 Dependentes</div><table>';
            $striped = true;

            foreach ($d['dependentes'] as $dep) {
                $class = $striped ? 'class="row-striped"' : '';

                // Imprime o Nome
                $html .= '<tr ' . $class . '><td class="label">Nome do Dependente</td><td class="value">' . htmlspecialchars($dep['nome'] ?? '-') . '</td></tr>';

                // Monta uma linha agrupada com as informações básicas
                $detalhes = [];
                if (!empty($dep['data_nascimento'])) $detalhes[] = 'Nasc: ' . htmlspecialchars($dep['data_nascimento']);
                if (!empty($dep['sexo'])) $detalhes[] = 'Sexo: ' . htmlspecialchars($dep['sexo']);
                if (!empty($dep['cpf'])) $detalhes[] = 'CPF: ' . htmlspecialchars($dep['cpf']);
                if (!empty($dep['rg'])) $detalhes[] = 'RG: ' . htmlspecialchars($dep['rg']);

                $infoTexto = implode(' &nbsp;|&nbsp; ', $detalhes);
                if (!empty($infoTexto)) {
                    $html .= '<tr><td class="label">Documentação</td><td class="value">' . $infoTexto . '</td></tr>';
                }

                // Imprime a matrícula da certidão de nascimento isolada, por ser um dado longo
                if (!empty($dep['certidao_matricula'])) {
                    $html .= '<tr><td class="label">Matrícula (Certidão)</td><td class="value">' . htmlspecialchars($dep['certidao_matricula']) . '</td></tr>';
                }

                // Linha em branco para separar visualmente múltiplos dependentes
                $html .= '<tr><td colspan="2" style="border-bottom: 2px solid #e2e8f0; height: 10px;"></td></tr>';

                $striped = !$striped;
            }
            $html .= '</table>';
        }

        $html .= '<div class="footer">Documento gerado automaticamente pelo sistema de admissão integrada.</div>
            </div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nomeArquivo = 'dossie_' . $this->collectionRequestId . '.pdf';

        // MUDANÇA AQUI: Utiliza a propriedade $this->diretorioTmp que configuramos no __construct
        $caminhoAbsoluto = $this->diretorioTmp . '/' . $nomeArquivo;

        // MUDANÇA AQUI: Operador @ para não vazar HTML de erro na API caso falhe a permissão
        $salvou = @file_put_contents($caminhoAbsoluto, $dompdf->output());

        if ($salvou === false) {
            throw new Exception("Erro de permissão: Não foi possível salvar o PDF em {$caminhoAbsoluto}");
        }

        return $caminhoAbsoluto;
    }

    // public function webhook($urlCliente)
    // {
    //     $payloadJson = json_encode([
    //         'status' => 'CONCLUIDO',
    //         'collection_request_id' => $this->collectionRequestId,
    //         'data_processamento' => date('Y-m-d H:i:s'),
    //         'dossie' => $this->organizado
    //     ]);

    //     $ch = curl_init($urlCliente);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payloadJson)]);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    //     $result = curl_exec($ch);
    //     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //     curl_close($ch);

    //     return [
    //         'sucesso' => ($httpCode >= 200 && $httpCode < 300),
    //         'http_code' => $httpCode,
    //         'response' => $result
    //     ];
    // }

    public function salvarJsonLocal()
    {
        $payload = [
            'status' => 'CONCLUIDO',
            'collection_request_id' => $this->collectionRequestId,
            'data_processamento' => date('Y-m-d H:i:s'),
            'dossie' => $this->organizado
        ];

        $jsonString = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $nomeArquivo = 'dossie_' . $this->collectionRequestId . '.json';

        // Usa o caminho absoluto montado no construtor
        $caminhoAbsoluto = $this->diretorioTmp . '/' . $nomeArquivo;

        // Tenta salvar e blinda o retorno
        $salvou = @file_put_contents($caminhoAbsoluto, $jsonString);

        if ($salvou === false) {
            throw new Exception("Erro de permissão: Não foi possível salvar o JSON em {$caminhoAbsoluto}");
        }

        return $caminhoAbsoluto;
    }
}
