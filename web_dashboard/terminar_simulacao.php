<?php
session_start();
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit(); }

$idSimulacao = intval($_GET['id'] ?? 0);
if ($idSimulacao <= 0) { header("Location: dashboard.php"); exit(); }

$conn = new mysqli('localhost', 'root', '', 'pisid');
if ($conn->connect_error) { die("Erro na ligação: " . $conn->connect_error); }

$stmt = $conn->prepare("CALL Terminar_simulacao(?)");
$stmt->bind_param("i", $idSimulacao);

if (!$stmt->execute()) {
    die("Erro ao terminar simulação: " . $conn->error);
}
$stmt->close();
$conn->close();

header("Location: dashboard.php");
exit();
?>
