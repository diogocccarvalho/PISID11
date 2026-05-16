<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>PISID LABIRINTO - PAINEL DE ACESSO</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>

<div class="background-overlay"></div>

<div class="login-container">
    <header>
        <h1>PISID LABIRINTO - PAINEL DE ACESSO</h1>
        <div class="team-badge">LOGIN DE UTILIZADOR</div>
    </header>

    <form action="processa_login.php" method="POST">
        
        <div class="input-group">
            <label>UTILIZADOR</label>
            <div class="input-field">
                <span class="icon">👤</span>
                <input type="email" name="username" required>
            </div>
        </div>

        <div class="input-group">
            <label>PALAVRA PASSE</label>
            <div class="input-field">
                <span class="icon">🔒</span>
                <input type="password" name="password" required>
            </div>
        </div>

        <button type="submit" class="btn-entrar">ENTRAR</button>
        
    </form>
</div>

</body>
</html>