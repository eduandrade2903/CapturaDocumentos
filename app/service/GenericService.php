<?php



class GenericService{
    
    /**
     * Limpa os arquivos da pasta
     * @param string $pasta Caminho da pasta
     * @param string $extensao Extensão do arquivo
     * @param int $horasVida Tempo em horas para o arquivo expirar
     */
    public static function limparArquivos(string $pasta, string $extensao, int $horasVida = 2): void
    {
        if (!is_dir($pasta)) {
            return;
        }

        // Calcula o timestamp limite (agora - 2 horas)
        $tempoLimite = time() - ($horasVida * 3600);

        // Busca apenas arquivos com a extensão enviada
        $arquivos = glob($pasta . '/*.' . $extensao);

        if ($arquivos) {
            foreach ($arquivos as $arquivo) {
                // filemtime pega a data de modificação/criação do arquivo
                if (is_file($arquivo) && filemtime($arquivo) < $tempoLimite) {
                    @unlink($arquivo); // O @ oculta warnings caso o arquivo esteja travado
                }
            }
        }
    }
}