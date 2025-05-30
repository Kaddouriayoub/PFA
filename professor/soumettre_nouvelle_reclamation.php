<?php
session_start();
require_once '../config.php';

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

try {
    // Log des données reçues
    error_log("Données POST reçues : " . print_r($_POST, true));

    // Vérifier si l'utilisateur est un professeur
    $stmt = $pdo->prepare("
        SELECT p.id_prof 
        FROM professeur p 
        INNER JOIN utilisateur u ON p.id_user = u.id_user 
        WHERE u.id_user = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        throw new Exception('Accès refusé. Vous devez être un professeur pour effectuer cette action.');
    }

    // Vérifier si toutes les données nécessaires sont présentes
    $required_fields = ['date_seance', 'type_seance', 'filiere', 'module', 'element', 'motif'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Champs manquants : ' . implode(', ', $missing_fields));
    }

    // Créer la date_seance
    $date_seance = $_POST['date_seance'];
    if (!strtotime($date_seance)) {
        throw new Exception('Format de date invalide');
    }
    $date_seance = date('Y-m-d', strtotime($date_seance));

    // Log avant l'insertion
    error_log("Tentative d'insertion avec les données suivantes : ");
    error_log("id_prof: " . $prof['id_prof']);
    error_log("date_seance: " . $date_seance);
    error_log("type_seance: " . $_POST['type_seance']);
    error_log("element: " . $_POST['element']);
    error_log("motif: " . $_POST['motif']);

    // Insérer dans la table reclamation
    $stmt = $pdo->prepare("
        INSERT INTO reclamation (
            id_prof,
            id_seance,
            date_seance_reclamee,
            type_seance,
            element_module,
            date_reclamation,
            motif,
            status
        ) VALUES (?, NULL, ?, ?, ?, NOW(), ?, 'en_attente')
    ");

    $result = $stmt->execute([
        $prof['id_prof'],
        $date_seance,
        $_POST['type_seance'],
        $_POST['element'],
        $_POST['motif']
    ]);

    if (!$result) {
        throw new Exception('Erreur lors de l\'insertion dans la base de données');
    }

    $response = [
        'success' => true, 
        'message' => 'Réclamation soumise avec succès',
        'debug' => [
            'prof_id' => $prof['id_prof'],
            'date_seance' => $date_seance,
            'type_seance' => $_POST['type_seance'],
            'element' => $_POST['element'],
            'motif' => $_POST['motif']
        ]
    ];

    error_log("Réponse envoyée : " . print_r($response, true));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Erreur lors de la soumission de la réclamation: " . $e->getMessage());
    error_log("Trace complète : " . $e->getTraceAsString());
    
    $response = [
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage(),
        'debug' => [
            'post_data' => $_POST,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ];
    
    error_log("Réponse d'erreur envoyée : " . print_r($response, true));
    echo json_encode($response);
} 