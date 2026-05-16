<?php
session_start();
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = new mysqli('localhost', 'root', '', 'pisid');

    if ($conn->connect_error) {
        die("Erro na ligação: " . $conn->connect_error);
    }

    $desc    = $_POST['descricao'];
    $email   = $_SESSION['user_email'];
    $t_max   = floatval($_POST['t_max']);
    $t_min   = floatval($_POST['t_min']);
    $s_max   = floatval($_POST['s_max']);

    // Criar simulação (fica em estado 'pendente')
    $stmt = $conn->prepare("CALL Criar_simulacao(?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddd", $desc, $email, $s_max, $t_max, $t_min);

    if (!$stmt->execute()) {
        die("Erro ao criar simulação: " . $conn->error);
    }
    $stmt->close();

    $conn->close();
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Nova Simulação</title>
    <style>
        body {
            margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.25)), url('fundo_dashboard.jpg') no-repeat center center fixed;
            background-size: cover; font-family: 'Segoe UI', sans-serif; color: white;
        }
        .glass-panel {
            background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px);
            padding: 30px; border-radius: 15px; width: 450px; border: 1px solid #00f2ff;
            text-align: center; box-shadow: 0 0 20px rgba(0, 242, 255, 0.2);
        }
        h2 { text-transform: uppercase; letter-spacing: 3px; color: #00f2ff; margin-bottom: 25px; }
        .section-title { font-size: 10px; text-transform: uppercase; color: #aaa; margin: 15px 0 5px; text-align: left; }
        .input-group { margin-bottom: 15px; }
        .input-group input {
            width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: none;
            border-bottom: 2px solid #00f2ff; color: #fff; box-sizing: border-box; text-align: center; outline: none;
        }
        .input-group label { display: block; font-size: 11px; color: #00f2ff; margin-top: 5px; }
        .row { display: flex; gap: 10px; }
        .row .input-group { flex: 1; }
        .btn-launch {
            background: #00f2ff; color: #000; border: none; padding: 12px; width: 100%;
            font-weight: bold; text-transform: uppercase; cursor: pointer; margin-top: 15px; transition: 0.3s;
        }
        .btn-launch:hover { box-shadow: 0 0 15px #00f2ff; }
        .btn-back { display: block; margin-top: 15px; color: #fff; text-decoration: none; font-size: 11px; opacity: 0.6; }
    </style>
</head>
<body>
    <div class="glass-panel">
        <h2>Nova Simulação</h2>
        <form method="POST">
            <div class="input-group">
                <input type="text" name="descricao" required autofocus>
                <label>Descrição do Labirinto</label>
            </div>

            <div class="section-title">Parâmetros de Temperatura (°C)</div>
            <div class="row">
                <div class="input-group">
                    <input type="number" step="0.1" name="t_max" required value="30.0">
                    <label>Máximo</label>
                </div>
                <div class="input-group">
                    <input type="number" step="0.1" name="t_min" required value="15.0">
                    <label>Mínimo</label>
                </div>
            </div>

            <div class="section-title">Parâmetros de Som (dB)</div>
            <div class="input-group">
                <input type="number" step="0.1" name="s_max" required value="80.0">
                <label>Limite Máximo</label>
            </div>

            <button type="submit" class="btn-launch">Lançar Simulação</button>
            <a href="dashboard.php" class="btn-back">VOLTAR AO PAINEL</a>
        </form>
    </div>
</body>
</html>
