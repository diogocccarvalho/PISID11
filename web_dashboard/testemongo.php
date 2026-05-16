<?php
try {
    // Liga ao servidor local do MongoDB
    $manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");
    
    // Tenta listar as bases de dados para ver se está vivo
    $listdatabases = new MongoDB\Driver\Command(["listDatabases" => 1]);
    $res = $manager->executeCommand("admin", $listdatabases);
    
    echo "<h3>✅ Conexão com MongoDB realizada com sucesso!</h3>";
} catch (Exception $e) {
    echo "<h3>❌ Erro na conexão: " . $e->getMessage() . "</h3>";
}
?>