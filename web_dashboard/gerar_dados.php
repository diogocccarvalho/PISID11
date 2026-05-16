<?php

date_default_timezone_set('Europe/Lisbon');

$manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");
$bulk = new MongoDB\Driver\BulkWrite;

// Inserir 3 leituras de teste para a simulação #1 (ou o ID que criaste)
$bulk->insert(['idSimulacao' => 1, 'temperatura' => 100.5, 'som' => 30, 'dataHora' => date('Y-m-d H:i:s')]);
$bulk->insert(['idSimulacao' => 1, 'temperatura' => 24.0, 'som' => 85, 'dataHora' => date('Y-m-d H:i:s', strtotime('+1 minute'))]);
$bulk->insert(['idSimulacao' => 1, 'temperatura' => 23.2, 'som' => 60, 'dataHora' => date('Y-m-d H:i:s', strtotime('+2 minutes'))]);

$manager->executeBulkWrite('maze.sensor_data', $bulk);
echo "Dados de teste inseridos com sucesso!";
?>