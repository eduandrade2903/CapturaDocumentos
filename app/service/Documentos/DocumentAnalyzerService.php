<?php

use Adianti\Database\TTransaction;

class DocumentAnalyzerService
{
    /**
     * Analisa o documento enviado usando Google Gemini 2.0 Flash.
     * 
     * @param array $file Array do $_FILES['arquivo']
     * @param int $tipoDocumentoId ID do tipo de documento
     * @return array Resposta formatada [CODIGO, DADOS...]
     */
    public static function analisarDocumento($file, $tipoDocumentoId)
    {
        try {
            // 1. Busca o nome do documento esperado no banco de dados
            TTransaction::open('data'); // Substitua pelo nome do seu .ini
            
            // Substitua 'TipoDocumento' pelo nome real do seu model
            $tipoDoc = TiposDocumentos::find($tipoDocumentoId); 
            if (!$tipoDoc) {
                throw new Exception("Tipo de documento não encontrado.");
            }
            $nomeDocumentoEsperado = $tipoDoc->nome; // Ex: "RG", "CNH", "COMPROVANTE DE ENDEREÇO"
            
            TTransaction::close();

            // 2. Prepara a imagem para envio (Base64)
            $mimeType = $file['type'];
            $base64Image = base64_encode(file_get_contents($file['tmp_name']));

            // 3. Monta o Prompt infalível para JSON
            $prompt = "Você é um especialista em análise e validação de documentos brasileiros. " .
                      "Sua tarefa é verificar se a imagem fornecida é um(a) '{$nomeDocumentoEsperado}'. " .
                      "REGRAS ESTABELECIDAS: " .
                      "1. Se a imagem NÃO FOR o documento esperado, retorne estritamente: {\"CODIGO\": \"404\"}. " .
                      "2. Se a imagem FOR o documento esperado, extraia os principais dados legíveis e retorne: {\"CODIGO\": \"200\", \"CAMPO_EXTRAIDO_1\": \"VALOR\", \"CAMPO_EXTRAIDO_2\": \"VALOR\"}. " .
                      "3. Responda APENAS com o JSON, sem nenhuma outra palavra, saudação ou bloco markdown.";

            // 4. Executa a chamada à API
            return self::chamarGeminiAPI($prompt, $mimeType, $base64Image);

        } catch (Exception $e) {
            TTransaction::rollback();
            return ['CODIGO' => '500', 'ERRO' => $e->getMessage()];
        }
    }

    /**
     * Método privado para isolar a requisição cURL
     */
    private static function chamarGeminiAPI($prompt, $mimeType, $base64Image)
    {
        // Pega a chave. Adicionei um fallback para string vazia para evitar warnings
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? ''; 
        
        if (empty($apiKey)) {
            throw new Exception("Chave da API do Gemini não encontrada no ambiente.");
        }

        // 1. URL limpa, sem query parameters e sem colchetes de formatação
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent";

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        [
                            "inline_data" => [
                                "mime_type" => $mimeType,
                                "data" => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "response_mime_type" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        
        // 2. Passando a chave pelo Header, exatamente como você viu no cURL de Início Rápido
        $headers = [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $apiKey
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Lembre-se de mudar para true em Produção

        $response = curl_exec($ch);
        
        // 3. Captura erros internos do cURL (ex: falha de DNS, timeout)
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erro interno do cURL: " . $error_msg);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 4. Tratamento melhorado para ver o motivo real da recusa do Google
        if ($httpCode !== 200) {
            $response_err = json_decode($response, true);
            $http_code = $httpCode;
            return [ 
                'CODIGO' => $http_code,
                'message' => $response_err
            ];
        }

        $responseData = json_decode($response, true);
        
        // Extrai o texto gerado pela IA
        $textoRespostaIA = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '{"CODIGO": "500"}';

        // Retorna o Array associativo pronto
        return json_decode($textoRespostaIA, true);
    }
}