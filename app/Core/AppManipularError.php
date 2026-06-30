<?php

namespace App\Core;

class AppManipularError
{
    private string $arquivoLog;

    public function __construct(string $arquivoLog)
    {
        $diretorio = dirname($arquivoLog);
        $this->arquivoLog = $arquivoLog;

        // Se a pasta não existir, cria ela com permissão de leitura e escrita
        if (!is_dir($diretorio)) {
            @mkdir($diretorio, 0755, true);
        }

        if (!file_exists($this->arquivoLog)) {
            @touch($this->arquivoLog);
            if (file_exists($this->arquivoLog)) {
                @chmod($this->arquivoLog, 0664); // Dá permissão de leitura/escrita para o arquivo
            }
        }
    }

    public function manipuladorDeErros(
        $nivel,
        $mensagem,
        $arquivo,
        $linha
    ) {
        $dataHora = date('Y-m-d H:i:s');

        $linhaDoErro =
            "[{$dataHora}] Nível: {$nivel} | Erro: {$mensagem} | Arquivo: {$arquivo} | Linha: {$linha}" .
            PHP_EOL;

        error_log($linhaDoErro, 3, $this->arquivoLog);

        return false;
    }
}
