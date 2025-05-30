<?php
require_once 'config.php';

try {
    // Vérifier d'abord si le professeur 10 existe
    $stmt = $pdo->prepare("SELECT id_prof FROM professeur WHERE id_prof = ?");
    $stmt->execute([10]);
    if (!$stmt->fetch()) {
        die("Erreur: Le professeur avec l'ID 10 n'existe pas.");
    }

    // Mettre à jour les séances
    $stmt = $pdo->prepare("UPDATE seance_reelle SET id_prof = ? WHERE id_prof = ?");
    $stmt->execute([10, 8]);
    
    $count = $stmt->rowCount();
    echo "Nombre de séances mises à jour : " . $count;
    
} catch (PDOException $e) {
    echo "Erreur lors de la mise à jour : " . $e->getMessage();
}
?> 