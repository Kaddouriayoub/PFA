<?php
require_once '../config.php';
session_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log de début
error_log("Début de update_status.php");
error_log("POST data reçue: " . print_r($_POST, true));
error_log("Session user_id: " . $_SESSION['user_id']);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    error_log("Erreur: Utilisateur non connecté");
    die(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['id_seance']) || !isset($_POST['status'])) {
    error_log("Erreur: Données manquantes dans POST");
    die(json_encode(['success' => false, 'message' => 'Données manquantes']));
}

$id_seance = $_POST['id_seance'];
$status = $_POST['status'] == '1' ? '1' : '0';

error_log("Traitement pour séance ID: " . $id_seance . " avec nouveau statut: " . $status);

try {
    // D'abord, obtenir l'id_prof de l'utilisateur connecté
    $stmt = $pdo->prepare("
        SELECT p.id_prof 
        FROM professeur p 
        INNER JOIN utilisateur u ON p.id_user = u.id_user 
        WHERE u.id_user = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $prof = $stmt->fetch();
    
    if (!$prof) {
        error_log("Erreur: Professeur non trouvé pour user_id: " . $_SESSION['user_id']);
        die(json_encode(['success' => false, 'message' => 'Professeur non trouvé']));
    }

    error_log("ID du professeur trouvé: " . $prof['id_prof']);

    // Vérifier que la séance appartient bien au professeur connecté
    $stmt = $pdo->prepare("
        SELECT id_seance, statut 
        FROM seance_reelle 
        WHERE id_seance = ? AND id_prof = ?
    ");
    $stmt->execute([$id_seance, $prof['id_prof']]);
    $seance = $stmt->fetch();
    
    if (!$seance) {
        error_log("Erreur: Séance non trouvée - ID séance: " . $id_seance . ", ID prof: " . $prof['id_prof']);
        die(json_encode(['success' => false, 'message' => 'Séance non trouvée']));
    }

    error_log("Ancien statut de la séance: " . $seance['statut']);

    // Mettre à jour le statut
    $stmt = $pdo->prepare("
        UPDATE seance_reelle 
        SET statut = ? 
        WHERE id_seance = ?
    ");
    $stmt->execute([$status, $id_seance]);
    
    // Vérifier si la mise à jour a réussi
    $rowCount = $stmt->rowCount();
    error_log("Nombre de lignes mises à jour: " . $rowCount);

    if ($rowCount > 0) {
        error_log("Mise à jour réussie - Nouveau statut: " . $status);
        echo json_encode([
            'success' => true, 
            'message' => 'Statut mis à jour',
            'details' => [
                'id_seance' => $id_seance,
                'ancien_statut' => $seance['statut'],
                'nouveau_statut' => $status
            ]
        ]);
    } else {
        error_log("Aucune ligne mise à jour");
        die(json_encode(['success' => false, 'message' => 'Aucune modification effectuée']));
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans update_status.php: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]));
}
?> 