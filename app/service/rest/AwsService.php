<?php

class AwsService 
{
    private $s3;
    private $bucket;

    public function __construct()
    {
        $this->s3 = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => $_ENV['AWS_DEFAULT_REGION'], // Altere para a região do seu bucket
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ]
        ]);

        $this->bucket = $_ENV['AWS_BUCKET_NAME'];
    }

    /**
     * Faz o upload de um arquivo para o S3
     */
    public static function uploadFile($tokenUnico, $nomeArquivoS3, $caminhoArquivoLocal)
    {
        $awsService = new AwsService();
        $caminhoS3 = "documentos/{$tokenUnico}/{$nomeArquivoS3}";

        try {
            $result = $awsService->s3->putObject([
                'Bucket' => $awsService->bucket,
                'Key'    => $caminhoS3,
                'SourceFile' => $caminhoArquivoLocal,
            ]);

            return $result['ObjectURL'];
            
        } catch (\Aws\Exception\AwsException $e) {
            throw new Exception("Erro ao fazer upload para o S3: " . $e->getMessage());
        }

    }

   
}