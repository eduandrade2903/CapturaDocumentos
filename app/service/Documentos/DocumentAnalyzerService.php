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
        // Como você já configurou o carregamento do .env no init.php do Adianti,
        // podemos acessar a chave da API com getenv() com segurança.
        $apiKey = $_ENV['GEMINI_API_KEY']; 
        $url = "[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=){$apiKey}";

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
                "response_mime_type" => "application/json" // Força o Gemini a devolver JSON puro
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas em dev, recomendo true em prod

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erro de comunicação com a IA: " . $response);
        }

        $responseData = json_decode($response, true);
        
        // Extrai o texto gerado pela IA
        $textoRespostaIA = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '{"CODIGO": "500"}';

        // O Gemini já vai devolver a string em formato JSON graças ao response_mime_type
        return json_decode($textoRespostaIA, true);
    }
}