<?php
session_start();
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit(); }

$idSimulacao = intval($_GET['id'] ?? 0);
if ($idSimulacao <= 0) { header("Location: dashboard.php"); exit(); }

$conn = new mysqli('localhost', 'root', '', 'pisid');
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
$mazerun = 'C:\\Users\\tomas\\OneDrive\\AMBIEN~1\\PROGAR~1\\PISID\\mazerun\\mazerun.exe';
pclose(popen('start /B "" "' . $mazerun . '" 13', 'r'));

header("Location: dashboard.php");
exit();
?>
