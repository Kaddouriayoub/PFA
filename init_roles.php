<?php
// init_roles.php
require 'config.php';

try {
    // Insérer les rôles s'ils n'existent pas déjà
    $roles = ['admin', 'professeur', 'financier'];
    
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO Role (nom_role) VALUES (?)");
        $stmt->execute([$role]);
    }
    
    echo "Rôles initialisés avec succès!";
} catch (PDOException $e) {
    die("Erreur lors de l'initialisation des rôles: " . $e->getMessage());
}
?>
