<?php
/* 1. SEGURANÇA: Verificamos se o utilizador está logado */
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

/* 2. LIGAÇÃO À BASE DE DADOS */
$host = 'localhost';
$user = 'root';
$pass = ''; 
$db   = 'pisid';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro de ligação: " . $conn->connect_error);
}

/* 3. QUERY: Vamos buscar apenas as simulações criadas por quem está logado */
$email_logado = $_SESSION['user_email'];
$sql = "SELECT idSimulacao, descricao, dataHoraInicio, estado FROM simulacao WHERE utilizador_criador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email_logado);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - PISID Labirinto</title>
    <link rel="stylesheet" href="style_login.css"> 
    <style>
        /* Ajustes específicos para a Dashboard */
        body { 
            margin: 0;
            padding: 0;
            /* Aplicamos a tua nova imagem aqui */
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.25)), url('fundo_dashboard.jpg') no-repeat center center fixed;
            background-size: cover;
            overflow-y: auto; 
            display: flex;
            flex-direction: column; 
            justify-content: flex-start; 
            align-items: center;
            padding-top: 50px; 
            min-height: 100vh;
        }
        
        .dashboard-container {
            width: 90%;
            max-width: 1000px;
            /* Fundo ligeiramente mais sólido para destacar os dados da imagem de trás */
            background: rgba(10, 15, 20, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid #00f2ff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 242, 255, 0.3);
            margin-bottom: 50px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(0, 242, 255, 0.5);
            padding-bottom: 15px;
        }

        h1 {
            font-size: 1.4rem;
            margin: 0;
            color: #00f2ff;
            text-shadow: 0 0 10px rgba(0, 242, 255, 0.5);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            color: white;
            text-align: left;
        }

        th { 
            color: #00f2ff; 
            padding: 15px; 
            border-bottom: 2px solid #00f2ff; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }

        td { 
            padding: 15px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            font-size: 0.95rem; 
        }

        /* Hover nas linhas para facilitar a leitura */
        tr:hover td {
            background: rgba(0, 242, 255, 0.05);
        }

        .btn-detalhes {
            background: #00f2ff;
            color: #000;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 0.75rem;
            transition: 0.3s;
            display: inline-block;
        }

        .btn-detalhes:hover { 
            background: #00ff00; 
            box-shadow: 0 0 15px #00ff00; 
            transform: translateY(-2px);
        }
        
        .logout-link { 
            color: #ff4444; 
            text-decoration: none; 
            font-size: 0.85rem; 
            border: 1px solid #ff4444;
            padding: 5px 10px;
            border-radius: 4px;
            transition: 0.3s;
        }

        .logout-link:hover {
            background: #ff4444;
            color: white;
            box-shadow: 0 0 10px #ff4444;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <header>
            <h1>PAINEL DE CONTROLO</h1>
            <div>
                <span>Bem-vindo, <b><?php echo $_SESSION['user_nome']; ?></b></span> | 
                <a href="logout.php" class="logout-link">SAIR</a>
            </div>
        </header>


        <div style="margin-bottom: 20px; text-align: right;">
            <a href="nova_simulacao.php" class="btn-detalhes" style="background:#00ff00;">+ NOVA SIMULAÇÃO</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Descrição</th>
                    <th>Data Início</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($resultado->num_rows > 0) {
                    while($row = $resultado->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td><b style='color:#00f2ff;'>#" . $row['idSimulacao'] . "</b></td>";
                        echo "<td>" . $row['descricao'] . "</td>";
                        echo "<td>" . $row['dataHoraInicio'] . "</td>";
                        echo "<td>" . $row['estado'] . "</td>";
                        echo "<td style='display:flex; gap:5px; flex-wrap:wrap;'>";
                        if ($row['estado'] === 'pendente') {
                            echo "<a href='iniciar_simulacao.php?id=" . $row['idSimulacao'] . "' class='btn-detalhes' style='background:#ffaa00;'>INICIAR</a>";
                            echo "<a href='alterar_simulacao.php?id=" . $row['idSimulacao'] . "' class='btn-detalhes' style='background:#8800ff;'>ALTERAR</a>";
                        }
                        if ($row['estado'] === 'ativo') {
                            echo "<a href='terminar_simulacao.php?id=" . $row['idSimulacao'] . "' class='btn-detalhes' style='background:#ff8800;' onclick=\"return confirm('Tens a certeza que queres terminar a simulação?')\">TERMINAR</a>";
                        }
                        if ($row['estado'] !== 'pendente') {
                            echo "<a href='reiniciar_simulacao.php?id=" . $row['idSimulacao'] . "' class='btn-detalhes' style='background:#ff4444;' onclick=\"return confirm('Tens a certeza? Apaga todos os dados da simulação.')\">REINICIAR</a>";
                        }
                        echo "<a href='detalhes.php?id=" . $row['idSimulacao'] . "' class='btn-detalhes'>VER DETALHES</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center; padding: 40px;'>Nenhuma simulação encontrada para o teu utilizador.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>
<?php $conn->close(); ?>