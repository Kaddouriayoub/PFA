<?php
session_start();
require_once '../config.php';

// Vérifier si l'utilisateur est connecté en tant que professeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['id_seance']) || !isset($_POST['motif']) || empty($_POST['motif'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$id_seance = intval($_POST['id_seance']);
$id_prof = $_SESSION['user_id'];
$motif = htmlspecialchars($_POST['motif']);
$date_reclamation = date('Y-m-d H:i:s');
$status_reclamation = 'en_attente';

try {
    // Vérifier si une réclamation existe déjà pour cette séance
    $stmt = $conn->prepare("SELECT id_reclamation FROM reclamations WHERE id_seance = ? AND id_prof = ?");
    $stmt->execute([$id_seance, $id_prof]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Une réclamation existe déjà pour cette séance']);
        exit;
    }

    // Insérer la nouvelle réclamation
    $stmt = $conn->prepare("INSERT INTO reclamations (id_seance, id_prof, date_reclamation, motif, status_reclamation) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id_seance, $id_prof, $date_reclamation, $motif, $status_reclamation]);

    echo json_encode(['success' => true, 'message' => 'Réclamation soumise avec succès']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la soumission de la réclamation']);
} 