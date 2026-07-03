<?php

use App\Controllers\TarefaController;

date_default_timezone_set('America/Sao_Paulo');

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


// Instancia o controlador e inicia o loop do terminal
$controller = new TarefaController();
$controller->processar_dados();

$controller->processar_dados_email();
