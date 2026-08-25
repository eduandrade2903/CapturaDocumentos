<?php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Service responsável pela integração e manipulação de arquivos no AWS S3.
 * Estrutura no bucket: documentos/{tokenUnico}/{nomeArquivo}
 */
class AwsService 
{
    private S3Client $s3;
    private string $bucket;
    private const string BASE_FOLDER = 'documentos';

    public function __construct()
    {
        $region = $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
        $key    = $_ENV['AWS_ACCESS_KEY_ID'] ?? '';
        $secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '';

        $this->s3 = new S3Client([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ]
        ]);

        $this->bucket = $_ENV['AWS_BUCKET_NAME'] ?? '';
    }

    /**
     * Retorna a instância interna do S3Client
     */
    public function getS3Client(): S3Client
    {
        return $this->s3;
    }

    /**
     * Retorna o nome do bucket configurado
     */
    public function getBucket(): string
    {
        return $this->bucket;
    }

    /**
     * Monta o caminho (Key) padrão do arquivo no S3
     */
    public static function buildKey(string $tokenUnico, string $nomeArquivoS3): string
    {
        $tokenLimpo = trim($tokenUnico, '/');
        $arquivoLimpo = ltrim($nomeArquivoS3, '/');
        return self::BASE_FOLDER . "/{$tokenLimpo}/{$arquivoLimpo}";
    }

    /**
     * Monta o prefixo da pasta de uma collection no S3
     */
    public static function buildPrefix(string $tokenUnico): string
    {
        $tokenLimpo = trim($tokenUnico, '/');
        return self::BASE_FOLDER . "/{$tokenLimpo}/";
    }

    /**
     * Faz o upload de um arquivo para o S3
     * 
     * @param string $tokenUnico Token único da Collection Request
     * @param string $nomeArquivoS3 Nome do arquivo que será salvo no S3
     * @param string $caminhoArquivoLocal Caminho absoluto do arquivo no servidor local
     * @return string URL do objeto no S3
     * @throws Exception Em caso de erro na operação
     */
    public static function uploadFile(string $tokenUnico, string $nomeArquivoS3, string $caminhoArquivoLocal): string
    {
        $instance = new self();
        $caminhoS3 = self::buildKey($tokenUnico, $nomeArquivoS3);

        try {
            $result = $instance->s3->putObject([
                'Bucket'     => $instance->bucket,
                'Key'        => $caminhoS3,
                'SourceFile' => $caminhoArquivoLocal,
            ]);

            return (string) ($result['ObjectURL'] ?? '');
        } catch (AwsException $e) {
            throw new Exception("Erro ao fazer upload para o S3: " . $e->getAwsErrorMessage());
        } catch (\Throwable $e) {
            throw new Exception("Erro inesperado no upload para o S3: " . $e->getMessage());
        }
    }

    /**
     * Deleta um arquivo específico do S3
     * 
     * @param string $tokenUnico Token único da Collection Request
     * @param string $nomeArquivoS3 Nome do arquivo ou caminho dentro da pasta do token
     * @return bool True se deletado com sucesso
     * @throws Exception Em caso de falha na exclusão
     */
    public static function deleteFile(string $tokenUnico, string $nomeArquivoS3): bool
    {
        $instance = new self();
        $caminhoS3 = self::buildKey($tokenUnico, $nomeArquivoS3);

        try {
            $instance->s3->deleteObject([
                'Bucket' => $instance->bucket,
                'Key'    => $caminhoS3,
            ]);

            return true;
        } catch (AwsException $e) {
            throw new Exception("Erro ao deletar arquivo no S3 ({$caminhoS3}): " . $e->getAwsErrorMessage());
        } catch (\Throwable $e) {
            throw new Exception("Erro inesperado ao deletar arquivo no S3: " . $e->getMessage());
        }
    }

    /**
     * Deleta toda a pasta referente a um token único da Collection Request e todos os seus arquivos
     * 
     * @param string $tokenUnico Token único da Collection Request
     * @return int Quantidade de objetos deletados
     * @throws Exception Em caso de falha na exclusão
     */
    public static function deleteFolderByToken(string $tokenUnico): int
    {
        $instance = new self();
        $prefix = self::buildPrefix($tokenUnico);

        try {
            // deleteMatchingObjects busca por prefixo e deleta em lotes com paginação automática
            $deletedCount = $instance->s3->deleteMatchingObjects($instance->bucket, $prefix);
            return (int) $deletedCount;
        } catch (AwsException $e) {
            throw new Exception("Erro ao deletar a pasta do token {$tokenUnico} no S3: " . $e->getAwsErrorMessage());
        } catch (\Throwable $e) {
            throw new Exception("Erro inesperado ao deletar pasta no S3: " . $e->getMessage());
        }
    }

    /**
     * Gera um link de visualização/download temporário (Presigned URL) para um arquivo no S3
     * 
     * @param string $tokenUnico Token único da Collection Request
     * @param string $nomeArquivoS3 Nome do arquivo no S3
     * @param string|int|\DateTimeInterface $expires Tempo de expiração (Ex: '+20 minutes', '+1 hour', 1200)
     * @return string URL pré-assinada temporária
     * @throws Exception Em caso de falha na geração da URL
     */
    public static function getTemporaryUrl(string $tokenUnico, string $nomeArquivoS3, string|int|\DateTimeInterface $expires = '+20 minutes'): string
    {
        $instance = new self();
        $caminhoS3 = self::buildKey($tokenUnico, $nomeArquivoS3);

        try {
            $cmd = $instance->s3->getCommand('GetObject', [
                'Bucket' => $instance->bucket,
                'Key'    => $caminhoS3,
            ]);

            $request = $instance->s3->createPresignedRequest($cmd, $expires);
            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new Exception("Erro ao gerar link temporário no S3: " . $e->getAwsErrorMessage());
        } catch (\Throwable $e) {
            throw new Exception("Erro inesperado ao gerar URL temporária no S3: " . $e->getMessage());
        }
    }

    /**
     * Lista todos os arquivos existentes dentro da pasta do token único no S3
     * 
     * @param string $tokenUnico Token único da Collection Request
     * @return array Lista com metadados dos arquivos (Key, Size, LastModified, Name)
     * @throws Exception
     */
    public static function listFilesByToken(string $tokenUnico): array
    {
        $instance = new self();
        $prefix = self::buildPrefix($tokenUnico);
        $files = [];

        try {
            $results = $instance->s3->getPaginator('ListObjectsV2', [
                'Bucket' => $instance->bucket,
                'Prefix' => $prefix,
            ]);

            foreach ($results as $result) {
                if (empty($result['Contents'])) {
                    continue;
                }

                foreach ($result['Contents'] as $object) {
                    $key = $object['Key'];
                    // Ignora marcador de diretório vazio se existir
                    if (str_ends_with($key, '/')) {
                        continue;
                    }

                    $files[] = [
                        'key'           => $key,
                        'name'          => basename($key),
                        'size'          => $object['Size'] ?? 0,
                        'last_modified' => isset($object['LastModified']) ? (string) $object['LastModified'] : null,
                    ];
                }
            }

            return $files;
        } catch (AwsException $e) {
            throw new Exception("Erro ao listar arquivos do token {$tokenUnico} no S3: " . $e->getAwsErrorMessage());
        } catch (\Throwable $e) {
            throw new Exception("Erro inesperado ao listar arquivos no S3: " . $e->getMessage());
        }
    }

    /**
     * Faz o download de todos os arquivos dentro da pasta referente ao token único,
     * compacta-os em um arquivo .zip e retorna o caminho local do arquivo ZIP gerado.
     * 
     * @param string $tokenUnico Token único da Collection Request
     * @param string|null $caminhoDestinoZip Caminho local opcional para salvar o ZIP (se null, cria em diretório temporário)
     * @return string Caminho absoluto do arquivo ZIP criado
     * @throws Exception Se não houver arquivos ou falha ao criar o ZIP
     */
    public static function downloadFolderAsZip(string $tokenUnico, ?string $caminhoDestinoZip = null): string
    {
        $arquivos = self::listFilesByToken($tokenUnico);

        if (empty($arquivos)) {
            throw new Exception("Nenhum arquivo encontrado no S3 para a requisição informada.");
        }

        if (!extension_loaded('zip')) {
            throw new Exception("A extensão PHP ZipArchive não está habilitada no servidor.");
        }

        // Define o caminho do arquivo zip
        if (empty($caminhoDestinoZip)) {
            $tempDir = sys_get_temp_dir();
            $tokenSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tokenUnico);
            $caminhoDestinoZip = $tempDir . DIRECTORY_SEPARATOR . "collection_{$tokenSanitizado}_" . date('Ymd_His') . ".zip";
        }

        $zip = new \ZipArchive();
        $openStatus = $zip->open($caminhoDestinoZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($openStatus !== true) {
            throw new Exception("Não foi possível criar o arquivo ZIP local no caminho: {$caminhoDestinoZip}. Código de erro: {$openStatus}");
        }

        $instance = new self();
        $arquivosAdicionados = 0;

        try {
            foreach ($arquivos as $arq) {
                $key = $arq['key'];
                $nomeArquivo = $arq['name'];

                // Baixa o conteúdo do objeto S3 diretamente em memória/stream
                $result = $instance->s3->getObject([
                    'Bucket' => $instance->bucket,
                    'Key'    => $key,
                ]);

                if (isset($result['Body'])) {
                    $corpoArquivo = (string) $result['Body']->getContents();
                    $zip->addFromString($nomeArquivo, $corpoArquivo);
                    $arquivosAdicionados++;
                }
            }

            $zip->close();

            if ($arquivosAdicionados === 0) {
                if (file_exists($caminhoDestinoZip)) {
                    @unlink($caminhoDestinoZip);
                }
                throw new Exception("Nenhum arquivo válido pôde ser adicionado ao arquivo compactado.");
            }

            return $caminhoDestinoZip;
        } catch (\Throwable $e) {
            // Em caso de erro, fecha e remove o zip incompleto
            if ($zip->filename) {
                @$zip->close();
            }
            if (file_exists($caminhoDestinoZip)) {
                @unlink($caminhoDestinoZip);
            }
            throw new Exception("Erro ao gerar o arquivo ZIP dos documentos do S3: " . $e->getMessage());
        }
    }
}