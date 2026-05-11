<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$response = array('success' => false, 'message' => '');

// Usamos $_REQUEST para aceitar tanto GET (browser) quanto POST (Android)
$username = $_REQUEST['username'] ?? '';
$password = $_REQUEST['password'] ?? '';
$database = $_REQUEST['database'] ?? '';

if (empty($username) || empty($password) || empty($database)) {
    $response['message'] = 'Preencha todos os campos (username, password, database).';
    echo json_encode($response);
    exit;
}

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

// 1. Criar conexão
$conn = new mysqli($host, $db_user, $db_pass, $database);

// 2. Verificar conexão
if ($conn->connect_error) {
    $response['message'] = "Erro de conexão MySQL: " . $conn->connect_error;
    echo json_encode($response);
    exit;
}

// 3. Consulta (Usando MySQLi preparada para evitar SQL Injection)
$sql = "SELECT equipa FROM utilizador WHERE email = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $response['success'] = true;
        $response['IDGrupo'] = $user['equipa'];
        $response['message'] = 'Login bem-sucedido.';
    } else {
        $response['message'] = 'Utilizador não encontrado.';
    }
    $stmt->close();
} else {
    $response['message'] = 'Erro na preparação da query: ' . $conn->error;
}

$conn->close();
echo json_encode($response);
?>