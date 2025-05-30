<?php
require_once '../config.php';
session_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Debug - Log session info
error_log("Session info: " . print_r($_SESSION, true));

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    error_log("Erreur: Utilisateur non connecté");
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si les paramètres requis sont présents
if (!isset($_POST['id_seance']) || !isset($_POST['status'])) {
    error_log("Erreur: Paramètres manquants - POST data: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit();
}

$id_seance = intval($_POST['id_seance']);
// Convertir le statut en '1' ou '0'
$status = $_POST['status'] === '1' ? '1' : '0';
$user_id = $_SESSION['user_id'];

try {
    // Récupérer l'id_prof et le salaire par séance
    $stmt = $pdo->prepare("
        SELECT p.id_prof, g.salaire_par_seance
        FROM professeur p
        INNER JOIN grade g ON p.id_grade = g.id_grade
        WHERE p.id_user = ?
    ");
    $stmt->execute([$user_id]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        throw new Exception('Professeur non trouvé');
    }

    // Vérifier que la séance appartient bien à ce professeur
    $stmt = $pdo->prepare("
        SELECT * FROM seance_reelle 
        WHERE id_seance = ? AND id_prof = ?
    ");
    $stmt->execute([$id_seance, $prof['id_prof']]);
    $seance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seance) {
        throw new Exception('Séance non trouvée ou non autorisée');
    }

    $pdo->beginTransaction();
    try {
        // Mettre à jour le statut de la séance
        $stmt = $pdo->prepare("
            UPDATE seance_reelle 
            SET statut = ?
            WHERE id_seance = ? AND id_prof = ?
        ");
        
        $result = $stmt->execute([$status, $id_seance, $prof['id_prof']]);

        if (!$result) {
            throw new Exception('Erreur lors de la mise à jour du statut');
        }

        // Vérifier que la mise à jour a bien été effectuée
        $stmt = $pdo->prepare("
            SELECT statut FROM seance_reelle 
            WHERE id_seance = ? AND id_prof = ?
        ");
        $stmt->execute([$id_seance, $prof['id_prof']]);
        $updated_status = $stmt->fetchColumn();

        if ($updated_status !== $status) {
            throw new Exception('La mise à jour du statut a échoué');
        }

        // Recalculer le montant total en fonction des séances validées
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb_seances_validees
            FROM seance_reelle
            WHERE id_prof = ? AND statut = '1'
        ");
        $stmt->execute([$prof['id_prof']]);
        $nb_seances_validees = $stmt->fetchColumn();

        // Calculer le nouveau montant total
        $nouveau_montant = $nb_seances_validees * floatval($prof['salaire_par_seance']);

        // Mettre à jour le montant total dans la table professeur
        $stmt = $pdo->prepare("
            UPDATE professeur 
            SET montant_total = ?
            WHERE id_prof = ?
        ");
        $stmt->execute([$nouveau_montant, $prof['id_prof']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'montant_seance' => floatval($prof['salaire_par_seance']),
            'montant_total' => $nouveau_montant,
            'status' => $status,
            'message' => 'Statut mis à jour avec succès'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur dans update_seance_status.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

} catch (Exception $e) {
    error_log("Erreur dans update_seance_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 