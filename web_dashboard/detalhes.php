<?php
session_start();
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: dashboard.php"); exit(); }
$idSimulacao = intval($_GET['id']);

$_cfg = parse_ini_file(__DIR__ . '/../config.ini', true);
$conn = new mysqli($_cfg['mysql_local']['host'] ?? 'localhost', $_cfg['mysql_local']['user'] ?? 'root', $_cfg['mysql_local']['password'] ?? '', $_cfg['mysql_local']['database'] ?? 'pisid');
if ($conn->connect_error) { die("Erro na ligação: " . $conn->connect_error); }

// Verificar se a simulação pertence ao utilizador
$stmt = $conn->prepare("SELECT idSimulacao, descricao, estado FROM Simulacao WHERE idSimulacao = ? AND utilizador_criador = ?");
$stmt->bind_param("is", $idSimulacao, $_SESSION['user_email']);
$stmt->execute();
$sim = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sim) { header("Location: dashboard.php"); exit(); }

// Últimas 20 temperaturas
$temperaturas = []; $labelsTemp = [];
$res = $conn->query("SELECT hora, temperatura FROM Temperatura WHERE idSimulacao = $idSimulacao ORDER BY hora DESC LIMIT 20");
while ($row = $res->fetch_assoc()) {
    $temperaturas[] = $row['temperatura'];
    $labelsTemp[]   = date('H:i:s', strtotime($row['hora']));
}
$temperaturas = array_reverse($temperaturas);
$labelsTemp   = array_reverse($labelsTemp);

// Últimos 20 sons
$sons = []; $labelsSom = [];
$res = $conn->query("SELECT hora, som FROM Som WHERE idSimulacao = $idSimulacao ORDER BY hora DESC LIMIT 20");
while ($row = $res->fetch_assoc()) {
    $sons[]      = $row['som'];
    $labelsSom[] = date('H:i:s', strtotime($row['hora']));
}
$sons      = array_reverse($sons);
$labelsSom = array_reverse($labelsSom);

// Últimas 10 mensagens de alerta
$mensagens = [];
$res = $conn->query("SELECT hora, mensagem, tipoALERTA, sala, leitura FROM Mensagens ORDER BY hora DESC LIMIT 10");
while ($row = $res->fetch_assoc()) { $mensagens[] = $row; }

// Ocupação das salas para esta simulação
$ocupacao = [];
$res = $conn->query("SELECT sala, NumeroOdd, NumeroEven FROM OcupacaoLabirinto WHERE simulacao = $idSimulacao ORDER BY sala ASC");
while ($row = $res->fetch_assoc()) { $ocupacao[] = $row; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Detalhes - Simulação #<?php echo $idSimulacao; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0; padding: 20px;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('fundo_dashboard.jpg') no-repeat center center fixed;
            background-size: cover; font-family: 'Segoe UI', sans-serif; color: white;
        }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px; background: rgba(0,0,0,0.85); border: 1px solid #00f2ff;
            border-radius: 10px; margin-bottom: 20px;
        }
        h1 { margin: 0; font-size: 1.4rem; color: #00f2ff; }
        .grid-charts { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .card {
            background: rgba(0,0,0,0.9); border: 1px solid #00f2ff;
            padding: 20px; border-radius: 15px; box-shadow: 0 0 15px rgba(0,242,255,0.1);
        }
        h2 { color: #00f2ff; text-transform: uppercase; font-size: 13px; letter-spacing: 2px; margin-top: 0; }
        .btn-back {
            color: #fff; text-decoration: none; border: 1px solid #00f2ff;
            padding: 8px 15px; border-radius: 5px; font-size: 0.85rem;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { color: #00f2ff; padding: 8px; border-bottom: 1px solid #00f2ff; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .badge-warn  { color: #ffaa00; font-weight: bold; }
        .badge-crit  { color: #ff4444; font-weight: bold; }
        .badge-info  { color: #00f2ff; }
        .empty { color: #aaa; text-align: center; padding: 20px; }
        .estado { font-size: 0.8rem; color: #00ff00; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>SIMULAÇÃO #<?php echo $idSimulacao; ?> — <?php echo htmlspecialchars($sim['descricao']); ?></h1>
        <span class="estado">Estado: <?php echo strtoupper($sim['estado']); ?></span>
    </div>
    <a href="dashboard.php" class="btn-back">VOLTAR AO PAINEL</a>
</div>

<div class="grid-charts">
    <div class="card">
        <h2>Temperatura (°C)</h2>
        <?php if (empty($temperaturas)): ?>
            <p class="empty">Sem dados de temperatura.</p>
        <?php else: ?>
            <canvas id="tempChart"></canvas>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Som (dB)</h2>
        <?php if (empty($sons)): ?>
            <p class="empty">Sem dados de som.</p>
        <?php else: ?>
            <canvas id="soundChart"></canvas>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
    <div class="card">
        <h2>Últimos Alertas</h2>
        <?php if (empty($mensagens)): ?>
            <p class="empty">Sem alertas registados.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Hora</th><th>Tipo</th><th>Sala</th><th>Leitura</th><th>Mensagem</th></tr></thead>
            <tbody>
            <?php foreach ($mensagens as $m):
                $cls = str_contains(strtolower($m['tipoALERTA'] ?? ''), 'critico') ? 'badge-crit' : 'badge-warn';
            ?>
                <tr>
                    <td><?php echo $m['hora']; ?></td>
                    <td class="<?php echo $cls; ?>"><?php echo htmlspecialchars($m['tipoALERTA'] ?? ''); ?></td>
                    <td><?php echo $m['sala']; ?></td>
                    <td><?php echo $m['leitura']; ?></td>
                    <td><?php echo htmlspecialchars($m['mensagem'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Ocupação das Salas</h2>
        <?php if (empty($ocupacao)): ?>
            <p class="empty">Sem dados de ocupação.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Sala</th><th>Odd</th><th>Even</th></tr></thead>
            <tbody>
            <?php foreach ($ocupacao as $o): ?>
                <tr>
                    <td><?php echo $o['sala']; ?></td>
                    <td><?php echo $o['NumeroOdd']; ?></td>
                    <td><?php echo $o['NumeroEven']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
    const chartOpts = {
        responsive: true,
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#fff' } },
            x: { grid: { display: false }, ticks: { color: '#fff', maxRotation: 45 } }
        },
        plugins: { legend: { labels: { color: '#fff' } } }
    };

    <?php if (!empty($temperaturas)): ?>
    new Chart(document.getElementById('tempChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labelsTemp); ?>,
            datasets: [{
                label: 'Temperatura',
                data: <?php echo json_encode($temperaturas); ?>,
                borderColor: '#00f2ff',
                backgroundColor: 'rgba(0,242,255,0.1)',
                fill: true, tension: 0.4
            }]
        },
        options: chartOpts
    });
    <?php endif; ?>

    <?php if (!empty($sons)): ?>
    new Chart(document.getElementById('soundChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labelsSom); ?>,
            datasets: [{
                label: 'Nível de Som',
                data: <?php echo json_encode($sons); ?>,
                backgroundColor: 'rgba(0,255,0,0.6)',
                borderColor: '#00ff00',
                borderWidth: 1
            }]
        },
        options: chartOpts
    });
    <?php endif; ?>

    // Auto-refresh a cada 5 segundos
    setTimeout(() => location.reload(), 5000);
</script>
</body>
</html>
