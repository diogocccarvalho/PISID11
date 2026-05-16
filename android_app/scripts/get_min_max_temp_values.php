<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$response = array('success' => false, 'message' => '', 'data' => null);

$username = $_REQUEST['username'] ?? '';
$password = $_REQUEST['password'] ?? '';
$database = $_REQUEST['database'] ?? '';

$config = @parse_ini_file(__DIR__ . '/../../config.ini', true);
if ($database === 'maze') {
    $host = $config['cloud_mysql']['host'] ?? '194.210.86.10';
    $db_user = $config['cloud_mysql']['user'] ?? 'aluno';
    $db_pass = $config['cloud_mysql']['password'] ?? 'aluno';
} else {
    $host = $config['mysql_local']['host'] ?? 'localhost';
    $db_user = $config['mysql_local']['user'] ?? 'root';
    $db_pass = $config['mysql_local']['password'] ?? '';
}

$conn = new mysqli($host, $db_user, $db_pass, $database);

if ($conn->connect_error) {
    $response['message'] = "Erro de ligação: " . $conn->connect_error;
    echo json_encode($response);
    exit;
}

$sql = "SELECT limTemperaturaMin AS minimo, limTemperaturaMax AS maximo FROM Simulacao WHERE estado = 'ativo' LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    $response['success'] = true;
    $response['data'] = array(
        "minimo" => (float)$row['minimo'],
        "maximo" => (float)$row['maximo']
    );
} else {
    $response['message'] = "Não foram encontrados limites na tabela.";
}

$conn->close();
echo json_encode($response);