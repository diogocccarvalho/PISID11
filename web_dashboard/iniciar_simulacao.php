<?php
session_start();
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit(); }

$idSimulacao = intval($_GET['id'] ?? 0);
if ($idSimulacao <= 0) { header("Location: dashboard.php"); exit(); }

$_cfg = parse_ini_file(__DIR__ . '/../config.ini', true);
$conn = new mysqli($_cfg['mysql_local']['host'] ?? 'localhost', $_cfg['mysql_local']['user'] ?? 'root', $_cfg['mysql_local']['password'] ?? '', $_cfg['mysql_local']['database'] ?? 'pisid');
if ($conn->connect_error) { die("Erro na ligação: " . $conn->connect_error); }

// Chamar SP Iniciar_simulacao
$stmt = $conn->prepare("CALL Iniciar_simulacao(?)");
$stmt->bind_param("i", $idSimulacao);

if (!$stmt->execute()) {
    die("Erro ao iniciar simulação: " . $conn->error);
}
$stmt->close();
$conn->close();

// Lançar mazerun em background (caminho curto sem caracteres especiais)
$mazerun = 'C:\\Users\\tomas\\OneDrive\\AMBIEN~1\\PROGAR~1\\PISID\\Projeto\\simulator\\windows\\mazerun.exe';
pclose(popen('start /B "" "' . $mazerun . '" 13', 'r'));

header("Location: dashboard.php");
exit();
?>
