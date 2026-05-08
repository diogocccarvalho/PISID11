<?php
// Configurações MySQL Locais
$mysql_host = 'localhost';
$mysql_user = 'root';
$mysql_pass = '';
$mysql_db   = 'maze';

$mensagem = "";

// Estabelecer conexão com a BD Local (MySQL)
$conn = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if ($conn->connect_error) {
    die("Falha na conexão à BD: " . $conn->connect_error);
}

// Processar Botão para Iniciar o Simulador
if (isset($_POST['start_simulator'])) {
    $cmd = 'python3 ../python_player/computer1.py > /dev/null 2>&1 &';
    exec($cmd);
    $mensagem = "O Software Jogador (computer1.py) foi iniciado.";
}

// Processar a edição de parâmetros (SetupMaze)
if (isset($_POST['update_setup'])) {
    $num_rooms = (int)$_POST['numberrooms'];
    $num_marsamis = (int)$_POST['numbermarsamis'];
    $norm_temp = (float)$_POST['normaltemperature'];
    $temp_tol_high = (float)$_POST['temperaturevarhightoleration'];
    $temp_tol_low = (float)$_POST['temperaturevarlowtoleration'];
    $norm_noise = (float)$_POST['normalnoise'];
    $noise_tol = (float)$_POST['noisevartoleration'];

    $sql = "UPDATE SetupMaze SET 
            numberrooms = $num_rooms, 
            numbermarsamis = $num_marsamis, 
            normaltemperature = $norm_temp, 
            temperaturevarhightoleration = $temp_tol_high, 
            temperaturevarlowtoleration = $temp_tol_low, 
            normalnoise = $norm_noise, 
            noisevartoleration = $noise_tol";
            
    if ($conn->query($sql) === TRUE) {
        $mensagem = "Parâmetros da simulação atualizados com sucesso!";
    } else {
        $mensagem = "Erro a atualizar: " . $conn->error;
    }
}

// Ir buscar os valores atuais para preencher o formulário
$current_setup = $conn->query("SELECT * FROM SetupMaze LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Labirinto - Equipa 13</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f4; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        .alert { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="number"], input[type="text"] { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        .btn { display: inline-block; background: #007bff; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; margin-top: 15px; font-size: 16px; }
        .btn-success { background: #28a745; width: 100%; text-align: center; }
        .btn:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="container">
    <h2>Gestão de Simulação - Labirinto</h2>
    
    <?php if ($mensagem): ?>
        <div class="alert"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <form method="POST">
        <h3>1. Controlo do Jogador</h3>
        <p>Inicie o seu software jogador para monitorizar movimentos, acionar portas e obter pontos.</p>
        <button type="submit" name="start_simulator" class="btn btn-success">Iniciar Jogador / Simulador</button>
    </form>

    <hr>

    <form method="POST">
        <h3>2. Configuração do Labirinto</h3>
        <p>Atenção: Os valores não devem ser alterados a meio de uma simulação.</p>
        
        <label>Número de Salas</label>
        <input type="number" name="numberrooms" value="<?php echo $current_setup['numberrooms'] ?? 10; ?>" required>

        <label>Número de Marsamis</label>
        <input type="number" name="numbermarsamis" value="<?php echo $current_setup['numbermarsamis'] ?? 40; ?>" required>

        <label>Temperatura Normal</label>
        <input type="number" step="0.1" name="normaltemperature" value="<?php echo $current_setup['normaltemperature'] ?? 20; ?>" required>

        <label>Tolerância Temperatura (Subida)</label>
        <input type="number" step="0.1" name="temperaturevarhightoleration" value="<?php echo $current_setup['temperaturevarhightoleration'] ?? 5; ?>" required>

        <label>Tolerância Temperatura (Descida)</label>
        <input type="number" step="0.1" name="temperaturevarlowtoleration" value="<?php echo $current_setup['temperaturevarlowtoleration'] ?? 5; ?>" required>

        <label>Ruído Normal</label>
        <input type="number" step="0.1" name="normalnoise" value="<?php echo $current_setup['normalnoise'] ?? 30; ?>" required>

        <label>Tolerância Ruído</label>
        <input type="number" step="0.1" name="noisevartoleration" value="<?php echo $current_setup['noisevartoleration'] ?? 10; ?>" required>

        <button type="submit" name="update_setup" class="btn">Atualizar Parâmetros</button>
    </form>
</div>

</body>
</html>