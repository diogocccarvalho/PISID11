<?php
session_start();
session_destroy(); // Apaga as variáveis (Nome, Email, Equipa)
header("Location: login.php"); // Manda-te de volta para o início
exit();
?>