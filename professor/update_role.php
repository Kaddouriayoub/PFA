<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("UPDATE role SET nom_role = 'professeur' WHERE nom_role = 'professor'");
    $stmt->execute();
    echo "Role updated successfully";
} catch (PDOException $e) {
    echo "Error updating role: " . $e->getMessage();
}
?> 