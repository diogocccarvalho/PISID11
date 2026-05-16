<?php
/* Iniciamos a sessão para o sistema saber quem és nas outras páginas */
session_start();

/* 1. LIGAÇÃO À BASE DE DADOS */
$host = 'localhost';
$user = 'root';
$pass = ''; 
$db   = 'pisid';

$conn = new mysqli($host, $user, $pass, $db);

/* Verificamos se a ligação falhou */
if ($conn->connect_error) {
    die("Erro na ligação: " . $conn->connect_error);
}

/* 2. RECOLHA DOS DADOS DO TEU FORMULÁRIO */
$email_inserido = $_POST['username'] ?? '';
$pass_inserida  = $_POST['password'] ?? ''; // Para já não validamos pass, só o email

/* 3. CONSULTA À TABELA UTILIZADOR */
/* Repara: usei 'idEquipa' porque é como está na tua tabela */
$sql = "SELECT Nome, Email, idEquipa FROM utilizador WHERE Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email_inserido);
$stmt->execute();
$resultado = $stmt->get_result();

/* 4. VERIFICAÇÃO FINAL */
if ($user_data = $resultado->fetch_assoc()) {
    /* Se o email existe, guardamos os dados na "memória" do servidor (Sessão) */
    $_SESSION['user_email']  = $user_data['Email'];
    $_SESSION['user_nome']   = $user_data['Nome'];
    $_SESSION['user_equipa'] = $user_data['idEquipa'];

    /* Mensagem de sucesso futurista */
    echo "<body style='background:#050a10; color:#00f2ff; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; flex-direction:column;'>";
    echo "<h1 style='text-shadow: 0 0 10px #00f2ff;'>ACESSO CONCEDIDO</h1>";
    echo "<p>Bem-vindo, <b>" . $_SESSION['user_nome'] . "</b>.</p>";
    echo "<p>A carregar sistema da Equipa " . $_SESSION['user_equipa'] . "...</p>";
    echo "</body>";
    
    /* Daqui a 3 segundos, ele podia saltar para a Dashboard */
    header("Refresh: 3; url=dashboard.php");
} else {
    /* Se o email não existe */
    echo "<body style='background:#050a10; color:#ff4444; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; flex-direction:column;'>";
    echo "<h1>ACESSO NEGADO</h1>";
    echo "<p>Utilizador não encontrado.</p>";
    echo "<a href='login.php' style='color:white;'>Tentar novamente</a>";
    echo "</body>";
}

$stmt->close();
$conn->close();
?>