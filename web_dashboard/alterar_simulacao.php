<?php
session_start();
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit(); }

$idSimulacao = intval($_GET['id'] ?? 0);
if ($idSimulacao <= 0) { header("Location: dashboard.php"); exit(); }

$conn = new mysqli('localhost', 'root', '', 'pisid');
if ($conn->connect_error) { die("Erro na ligação: " . $conn->connect_error); }

// Processar o formulário de alteração
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc  = $_POST['descricao'];
    $email = $_SESSION['user_email'];
    $s_max = floatval($_POST['s_max']);
    $t_max = floatval($_POST['t_max']);
    $t_min = floatval($_POST['t_min']);

    $stmt = $conn->prepare("CALL Alterar_simulacao(?, ?, ?, ?, ?)");
    $stmt->bind_param("issddd", $idSimulacao, $desc, $email, $s_max, $t_max, $t_min);

    if (!$stmt->execute()) {
        die("Erro ao alterar simulação: " . $conn->error);
    }
    $stmt->close();
    $conn->close();
    header("Location: dashboard.php");
    exit();
}

// Carregar dados atuais da simulação
$stmt = $conn->prepare("SELECT descricao, limSom, limTemperaturaMax, limTemperaturaMin FROM Simulacao WHERE idSimulacao = ? AND utilizador_criador = ?");
$stmt->bind_param("is", $idSimulacao, $_SESSION['user_email']);
$stmt->execute();
$res = $stmt->get_result();
$sim = $res->fetch_assoc();
$stmt->close();

if (!$sim) { header("Location: dashboard.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Alterar Simulação</title>
    <style>
        body {
            margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.25)), url('fundo_dashboard.jpg') no-repeat center center fixed;
            background-size: cover; font-family: 'Segoe UI', sans-serif; color: white;
        }
        .glass-panel {
            background: rgba(0,0,0,0.85); backdrop-filter: blur(10px);
            padding: 30px; border-radius: 15px; width: 450px; border: 1px solid #8800ff;
            text-align: center; box-shadow: 0 0 20px rgba(136,0,255,0.3);
        }
        h2 { text-transform: uppercase; letter-spacing: 3px; color: #8800ff; margin-bottom: 25px; }
        .section-title { font-size: 10px; text-transform: uppercase; color: #aaa; margin: 15px 0 5px; text-align: left; }
        .input-group { margin-bottom: 15px; }
        .input-group input {
            width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: none;
            border-bottom: 2px solid #8800ff; color: #fff; box-sizing: border-box; text-align: center; outline: none;
        }
        .input-group label { display: block; font-size: 11px; color: #8800ff; margin-top: 5px; }
        .row { display: flex; gap: 10px; }
        .row .input-group { flex: 1; }
        .btn-save {
            background: #8800ff; color: #fff; border: none; padding: 12px; width: 100%;
            font-weight: bold; text-transform: uppercase; cursor: pointer; margin-top: 15px; transition: 0.3s;
        }
        .btn-save:hover { box-shadow: 0 0 15px #8800ff; }
        .btn-back { display: block; margin-top: 15px; color: #fff; text-decoration: none; font-size: 11px; opacity: 0.6; }
    </style>
</head>
<body>
    <div class="glass-panel">
        <h2>Alterar Simulação #<?php echo $idSimulacao; ?></h2>
        <form method="POST">
            <div class="input-group">
                <input type="text" name="descricao" required value="<?php echo htmlspecialchars($sim['descricao']); ?>">
                <label>Descrição</label>
            </div>

            <div class="section-title">Parâmetros de Temperatura (°C)</div>
            <div class="row">
                <div class="input-group">
                    <input type="number" step="0.1" name="t_max" required value="<?php echo $sim['limTemperaturaMax']; ?>">
                    <label>Máximo</label>
                </div>
                <div class="input-group">
                    <input type="number" step="0.1" name="t_min" required value="<?php echo $sim['limTemperaturaMin']; ?>">
                    <label>Mínimo</label>
                </div>
            </div>

            <div class="section-title">Parâmetros de Som (dB)</div>
            <div class="input-group">
                <input type="number" step="0.1" name="s_max" required value="<?php echo $sim['limSom']; ?>">
                <label>Limite Máximo</label>
            </div>

            <button type="submit" class="btn-save">Guardar Alterações</button>
            <a href="dashboard.php" class="btn-back">VOLTAR AO PAINEL</a>
        </form>
    </div>
</body>
</html>
