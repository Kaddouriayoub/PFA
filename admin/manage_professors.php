<?php
// manage_professors.php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ensias_payment";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if required tables exist
    $tables = [
        'Professeur',
        'Utilisateur',
        'Developpement_Prof'
    ];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            die("Table $table n'existe pas dans la base de données!");
        }
    }
    
    // Check if there are any professors
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Professeur");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] == 0) {
        echo "<div class='alert alert-info'>Aucun professeur n'est actuellement enregistré dans la base de données.</div>";
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php?error=access_denied");
    exit;
}

// Function to get all grades
function getGrades($conn) {
    $stmt = $conn->prepare("SELECT id_grade, nom_grade FROM Grade ORDER BY nom_grade");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllGradesForSalaryGrid($conn) {
    try {
        $stmt = $conn->prepare("SELECT id_grade, nom_grade, salaire_par_seance FROM Grade ORDER BY salaire_par_seance DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error in getAllGradesForSalaryGrid: " . $e->getMessage());
        return [];
    }
}

// Get all grades for dropdowns
$grades = getGrades($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_professors':
                try {
                    $stmt = $conn->prepare("
                    SELECT 
                        p.id_prof,
                        p.nom,
                        p.prenom,
                        p.type_prof,
                        u.username,
                        g.id_grade,
                        g.nom_grade,
                        g.salaire_par_seance,
                        d.date_debut_grade,
                        d.date_fin_grade
                    FROM Professeur p
                    INNER JOIN Utilisateur u ON p.id_user = u.id_user
                    LEFT JOIN (
                        SELECT d1.*
                        FROM Developpement_Prof d1
                        WHERE d1.date_debut_grade = (
                            SELECT MAX(d2.date_debut_grade)
                            FROM Developpement_Prof d2
                            WHERE d2.id_prof = d1.id_prof
                              AND d2.date_debut_grade <= CURDATE()
                              AND (d2.date_fin_grade IS NULL OR d2.date_fin_grade >= CURDATE())
                        )
                          AND (d1.date_fin_grade IS NULL OR d1.date_fin_grade >= CURDATE())
                          AND d1.date_debut_grade <= CURDATE()
                    ) d ON p.id_prof = d.id_prof
                    LEFT JOIN Grade g ON d.id_grade = g.id_grade
                    ORDER BY p.id_prof
                ");

                    $stmt->execute();
                    $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($professors)) {
                        echo json_encode(['success' => true, 'professors' => []]);
                    } else {
                        $unique_profs = [];
                        foreach ($professors as $prof) {
                            $unique_profs[$prof['id_prof']] = $prof;
                        }
                        $professors = array_values($unique_profs);

                        echo json_encode(['success' => true, 'professors' => $professors]);
                    }
                } catch (PDOException $e) {
                    error_log("Database error in get_professors: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
                }
                exit;
            
            case 'get_available_users':
                try {
                    $stmt = $conn->prepare("
                    SELECT u.id_user, u.nom, u.prenom, u.username
                    FROM Utilisateur u
                    WHERE u.id_role = 8  -- ID du rôle professeur
                    AND u.id_user NOT IN (
                        SELECT DISTINCT id_user 
                        FROM Professeur 
                        WHERE id_user IS NOT NULL
                    )
                    ORDER BY u.nom, u.prenom
                ");
                    $stmt->execute();
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Debug: log the results
                    error_log("Found " . count($users) . " available users");
                    
                    echo json_encode(['success' => true, 'users' => $users]);
                } catch (PDOException $e) {
                    error_log("Error in get_available_users: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
                }
                exit;

            case 'get_grades':
                $stmt = $conn->prepare("SELECT id_grade, nom_grade, salaire_par_seance FROM Grade ORDER BY nom_grade");
                $stmt->execute();
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'grades' => $grades]);
                exit;
                
                case 'get_professor_grades':
                    $id_prof = intval($_POST['id_prof']);
                    $stmt = $conn->prepare("
                        SELECT dp.*, g.nom_grade, g.salaire_par_seance
                        FROM Developpement_Prof dp
                        JOIN Grade g ON dp.id_grade = g.id_grade
                        WHERE dp.id_prof = ?
                        ORDER BY dp.date_debut_grade DESC
                    ");
                    $stmt->execute([$id_prof]);
                    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'grades' => $grades]);
                    exit;
                
            case 'add_professor':
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $type_prof = $_POST['type_prof'];
                $id_user = intval($_POST['id_user']);
                $id_grade = isset($_POST['id_grade']) && !empty($_POST['id_grade']) ? intval($_POST['id_grade']) : null;
                
                // Validate input
                if (empty($nom) || empty($prenom) || empty($type_prof) || $id_user <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
                    exit;
                }
                
                // Validate grade if provided
                if ($id_grade !== null) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM Grade WHERE id_grade = ?");
                    $stmt->execute([$id_grade]);
                    if ($stmt->fetchColumn() == 0) {
                        echo json_encode(['success' => false, 'message' => 'Grade sélectionné invalide']);
                        exit;
                    }
                }
                
                // Check if user is already a professor
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Professeur WHERE id_user = ?");
                $stmt->execute([$id_user]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cet utilisateur est déjà enregistré comme professeur']);
                    exit;
                }
                
                // Insert new professor
                $stmt = $conn->prepare("
                    INSERT INTO Professeur (nom, prenom, type_prof, id_user, id_grade) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $prenom, $type_prof, $id_user, $id_grade]);
                
                // Get the last inserted ID
                $id_prof = $conn->lastInsertId();
                
                // Add initial grade in Developpement_Prof if specified
                if ($id_grade !== null) {
                    $stmt = $conn->prepare("
                        INSERT INTO Developpement_Prof (id_prof, id_grade, date_debut_grade)
                        VALUES (?, ?, CURDATE())
                    ");
                    $stmt->execute([$id_prof, $id_grade]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Professeur ajouté avec succès']);
                exit;
                
            case 'update_professor':
                $id_prof = intval($_POST['id_prof']);
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $type_prof = $_POST['type_prof'];
                $id_grade = isset($_POST['id_grade']) && !empty($_POST['id_grade']) ? intval($_POST['id_grade']) : null;
                
                // Validate input
                if ($id_prof <= 0 || empty($nom) || empty($prenom) || empty($type_prof)) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                // Update professor
                $stmt = $conn->prepare("
                    UPDATE Professeur 
                    SET nom = ?, prenom = ?, type_prof = ?, id_grade = ?
                    WHERE id_prof = ?
                ");
                $stmt->execute([$nom, $prenom, $type_prof, $id_grade, $id_prof]);
                
                // Update grade in Developpement_Prof if specified
                if ($id_grade !== null) {
                    // Récupérer l'actuel grade actif
                    $stmt = $conn->prepare("
                        SELECT id_dev, id_grade FROM Developpement_Prof
                        WHERE id_prof = ? AND (date_fin_grade IS NULL OR date_fin_grade >= CURDATE())
                        ORDER BY date_debut_grade DESC LIMIT 1
                    ");
                    $stmt->execute([$id_prof]);
                    $activeGrade = $stmt->fetch(PDO::FETCH_ASSOC);
                
                    if ($activeGrade && $activeGrade['id_grade'] != $id_grade) {
                        // Fermer l'ancien grade
                        // Fermer tous les grades actifs du prof
                        $stmt = $conn->prepare("UPDATE Developpement_Prof SET date_fin_grade = CURDATE() WHERE id_prof = ? AND (date_fin_grade IS NULL OR date_fin_grade >= CURDATE())");
                        $stmt->execute([$id_prof]);
                
                        // Insérer le nouveau grade
                        $stmt = $conn->prepare("INSERT INTO Developpement_Prof (id_prof, id_grade, date_debut_grade) VALUES (?, ?, CURDATE())");
                        $stmt->execute([$id_prof, $id_grade]);
                    } elseif (!$activeGrade) {
                        // Aucun grade actif, on en ajoute un
                        $stmt = $conn->prepare("
                            INSERT INTO Developpement_Prof (id_prof, id_grade, date_debut_grade)
                            VALUES (?, ?, CURDATE())
                        ");
                        $stmt->execute([$id_prof, $id_grade]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Professeur modifié avec succès']);
                exit;
                
            case 'add_grade':
                $id_prof = intval($_POST['id_prof']);
                $grade = trim($_POST['grade']);
                $date_debut = $_POST['date_debut_grade'];
                $date_fin = !empty($_POST['date_fin_grade']) ? $_POST['date_fin_grade'] : null;
                
                // Validate input
                if ($id_prof <= 0 || empty($grade) || empty($date_debut)) {
                    echo json_encode(['success' => false, 'message' => 'Le professeur, le grade et la date de début sont obligatoires']);
                    exit;
                }
                
                // Check if grade exists in our salary grid
                global $grades_salaires;
                if (!array_key_exists($grade, $grades_salaires)) {
                    echo json_encode(['success' => false, 'message' => 'Grade non reconnu']);
                    exit;
                }
                
                // Begin transaction
                $conn->beginTransaction();
                
                try {
                    // Close any current active grade for this professor
                    $stmt = $conn->prepare("
                        UPDATE Developpement_Prof 
                        SET date_fin_grade = DATE_SUB(?, INTERVAL 1 DAY)
                        WHERE id_prof = ? AND date_fin_grade IS NULL
                    ");
                    $stmt->execute([$date_debut, $id_prof]);
                    
                    // Insert new grade
                    $stmt = $conn->prepare("
                        INSERT INTO Developpement_Prof (grade, date_debut_grade, date_fin_grade, id_prof) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$grade, $date_debut, $date_fin, $id_prof]);
                    
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Grade ajouté avec succès']);
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                exit;
                
            case 'update_grade':
                $id_dev = intval($_POST['id_dev']); // id de la ligne à fermer
                $id_prof = intval($_POST['id_prof']);
                $id_grade_new = intval($_POST['id_grade']);
                $date_debut = $_POST['date_debut_grade']; // nouvelle date de début (date de changement)
            
                // Sécurité
                if ($id_dev <= 0 || $id_prof <= 0 || $id_grade_new <= 0 || empty($date_debut)) {
                    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
                    exit;
                }
            
                try {
                    $conn->beginTransaction();
            
                    // 1. Fermer l'ancien grade (ligne actuelle)
                    $stmt = $conn->prepare("UPDATE Developpement_Prof SET date_fin_grade = ? WHERE id_dev = ?");
                    $stmt->execute([$date_debut, $id_dev]);
            
                    // 2. Insérer le nouveau grade (début = date_debut, fin = NULL)
                    $stmt = $conn->prepare("INSERT INTO Developpement_Prof (date_debut_grade, date_fin_grade, id_prof, id_grade) VALUES (?, NULL, ?, ?)");
                    $stmt->execute([$date_debut, $id_prof, $id_grade_new]);
            
                    // 3. Mettre à jour le champ id_grade courant dans Professeur
                    $stmt = $conn->prepare("UPDATE Professeur SET id_grade = ? WHERE id_prof = ?");
                    $stmt->execute([$id_grade_new, $id_prof]);
            
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Grade modifié et historique mis à jour']);
                } catch(Exception $e) {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
                }
                exit;
                
            case 'delete_grade':
                $id_dev = intval($_POST['id_dev']);
                
                $stmt = $conn->prepare("DELETE FROM Developpement_Prof WHERE id_dev = ?");
                $stmt->execute([$id_dev]);
                
                echo json_encode(['success' => true, 'message' => 'Grade supprimé avec succès']);
                exit;
                
                case 'delete_professor':
                    $id_prof = intval($_POST['id_prof']);
                    
                    // Commencer une transaction pour assurer l'intégrité des données
                    $conn->beginTransaction();
                    
                    try {
                        // 1. Supprimer d'abord toutes les séances réelles du professeur
                        $stmt = $conn->prepare("DELETE FROM Seance_Reelle WHERE id_prof = ?");
                        $stmt->execute([$id_prof]);
                        $deleted_seances = $stmt->rowCount();
                        
                        // 2. Supprimer tous les développements/grades du professeur
                        $stmt = $conn->prepare("DELETE FROM Developpement_Prof WHERE id_prof = ?");
                        $stmt->execute([$id_prof]);
                        $deleted_grades = $stmt->rowCount();
                        
                        // 3. Supprimer les paiements liés au professeur (si table existe)
                        $stmt = $conn->prepare("SHOW TABLES LIKE 'Paiement'");
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $stmt = $conn->prepare("DELETE FROM Paiement WHERE id_prof = ?");
                            $stmt->execute([$id_prof]);
                            $deleted_payments = $stmt->rowCount();
                        }
                        
                        // 4. Supprimer les absences du professeur (si table existe)
                        $stmt = $conn->prepare("SHOW TABLES LIKE 'Absence'");
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $stmt = $conn->prepare("DELETE FROM Absence WHERE id_prof = ?");
                            $stmt->execute([$id_prof]);
                            $deleted_absences = $stmt->rowCount();
                        }
                        
                        // 5. Supprimer les enseignements du professeur (si table existe)
                        $stmt = $conn->prepare("SHOW TABLES LIKE 'Enseignement'");
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $stmt = $conn->prepare("DELETE FROM Enseignement WHERE id_prof = ?");
                            $stmt->execute([$id_prof]);
                            $deleted_enseignements = $stmt->rowCount();
                        }
                        
                        // 6. Supprimer les affectations du professeur (si table existe)
                        $stmt = $conn->prepare("SHOW TABLES LIKE 'Affectation'");
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $stmt = $conn->prepare("DELETE FROM Affectation WHERE id_prof = ?");
                            $stmt->execute([$id_prof]);
                            $deleted_affectations = $stmt->rowCount();
                        }
                        
                        // 7. Récupérer l'id_user avant de supprimer le professeur
                        $stmt = $conn->prepare("SELECT id_user FROM Professeur WHERE id_prof = ?");
                        $stmt->execute([$id_prof]);
                        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
                        $id_user = $professor ? $professor['id_user'] : null;
                        
                        // 8. Finalement, supprimer le professeur lui-même
                        $stmt = $conn->prepare("DELETE FROM Professeur WHERE id_prof = ?");
                        $stmt->execute([$id_prof]);
                        $deleted_professor = $stmt->rowCount();
                        
                        // 9. Optionnel : Supprimer aussi l'utilisateur associé si souhaité
                        // Décommentez les lignes suivantes si vous voulez aussi supprimer l'utilisateur
                        /*
                        if ($id_user) {
                            $stmt = $conn->prepare("DELETE FROM Utilisateur WHERE id_user = ?");
                            $stmt->execute([$id_user]);
                        }
                        */
                        
                        // Valider la transaction
                        $conn->commit();
                        
                        // Préparer le message de confirmation avec détails
                        $message = "Professeur supprimé avec succès.";
                        $details = [];
                        
                        if ($deleted_seances > 0) $details[] = "$deleted_seances séance(s)";
                        if ($deleted_grades > 0) $details[] = "$deleted_grades grade(s)";
                        if (isset($deleted_payments) && $deleted_payments > 0) $details[] = "$deleted_payments paiement(s)";
                        if (isset($deleted_absences) && $deleted_absences > 0) $details[] = "$deleted_absences absence(s)";
                        if (isset($deleted_enseignements) && $deleted_enseignements > 0) $details[] = "$deleted_enseignements enseignement(s)";
                        if (isset($deleted_affectations) && $deleted_affectations > 0) $details[] = "$deleted_affectations affectation(s)";
                        
                        if (!empty($details)) {
                            $message .= " Éléments supprimés : " . implode(', ', $details) . ".";
                        }
                        
                        echo json_encode(['success' => true, 'message' => $message]);
                        
                    } catch (Exception $e) {
                        // Annuler la transaction en cas d'erreur
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
                    }
                    exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestion des Professeurs - ENSIAS</title>
    <style>
       body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .back-btn {
            background: #95a5a6;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #7f8c8d;
        }
        .professor-management {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            font-size: 12px;
        }
        
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.8; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 12px;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .type-permanent {
            background-color: #27ae60;
            color: white;
        }
        
        .type-vacataire {
            background-color: #f39c12;
            color: white;
        }
        
        .grade-badge {
            padding: 3px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
            background-color: #6c757d;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 20px;
            border: none;
            border-radius: 8px;
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
        }
        
        .salary {
            font-weight: bold;
            color: #27ae60;
        }
        
        .grades-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .grade-item {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        .grade-current {
            border-color: #27ae60;
            background-color: #d4edda;
        }
        
        .salary-grid {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .salary-grid h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .salary-grid table {
            margin-top: 10px;
        }
        
        .salary-grid td {
            padding: 5px 10px;
        }

        .loading-spinner {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 8px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .alert {
        padding: 12px 20px;
        margin: 10px 0;
        border-radius: 4px;
        border-left: 4px solid;
    }

    .alert-success {
        background-color: #d4edda;
        border-color: #28a745;
        color: #155724;
    }

    .alert-danger {
        background-color: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }

    .alert-info {
        background-color: #d1ecf1;
        border-color: #17a2b8;
        color: #0c5460;
    }

    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
        transition: all 0.2s ease;
    }

    .btn-danger:hover {
        background-color: #c82333;
        border-color: #bd2130;
        transform: translateY(-1px);
    }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Gestion des Professeurs</h1>
        </div>
    </div>

    <div class="container">
        <a href="admin_dashboard.php" class="back-btn">← Retour au Dashboard</a>

        <div class="professor-management">
            <h3>Liste des Professeurs</h3>
            <button class="btn btn-success" onclick="openAddProfessorModal()">Ajouter un professeur</button>
            <button class="btn btn-primary" onclick="refreshProfessors()">Actualiser</button>
            <button class="btn btn-info" onclick="showSalaryGrid()">Grille des salaires</button>
            
            <div id="alert-container"></div>
            
            <div id="professors-table-container">
                <div class="loading">Chargement des professeurs...</div>
            </div>
        </div>
    </div>

    <!-- Add Professor Modal -->
    <div id="addProfessorModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddProfessorModal()">&times;</span>
            <h3>Ajouter un nouveau professeur</h3>
            <form id="addProfessorForm">
                <div class="form-group">
                    <label for="add_nom">Nom:</label>
                    <input type="text" id="add_nom" name="nom" required>
                </div>
                <div class="form-group">
                    <label for="add_prenom">Prénom:</label>
                    <input type="text" id="add_prenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label for="add_type_prof">Type de professeur:</label>
                    <select id="add_type_prof" name="type_prof" required>
                        <option value="permanent">Permanent</option>
                        <option value="vacataire">Vacataire</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_id_user">Utilisateur associé:</label>
                    <select id="add_id_user" name="id_user" required>
                        <option value="">Sélectionner un utilisateur</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_id_grade">Grade:</label>
                    <select id="add_id_grade" name="id_grade" required>
                        <option value="">Sélectionner un grade</option>
                        <!-- Les grades seront chargés dynamiquement -->
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddProfessorModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Edit Professor Modal -->
    <div id="editProfessorModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditProfessorModal()">&times;</span>
            <h3>Modifier le professeur</h3>
            <form id="editProfessorForm">
                <input type="hidden" id="edit_id_prof" name="id_prof">
                <div class="form-group">
                    <label for="edit_nom">Nom:</label>
                    <input type="text" id="edit_nom" name="nom" required>
                </div>
                <div class="form-group">
                    <label for="edit_prenom">Prénom:</label>
                    <input type="text" id="edit_prenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label for="edit_type_prof">Type de professeur:</label>
                    <select id="edit_type_prof" name="type_prof" required>
                        <option value="permanent">Permanent</option>
                        <option value="vacataire">Vacataire</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_id_grade">Grade:</label>
                    <select id="edit_id_grade" name="id_grade" required>
                        <option value="">Sélectionner un grade</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning">Modifier</button>
                <button type="button" class="btn" onclick="closeEditProfessorModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Grades Management Modal -->
    <div id="gradesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeGradesModal()">&times;</span>
            <h3 id="grades-modal-title">Gestion des grades</h3>
            
            <!-- Add Grade Form -->

            
            <!-- Grades List -->
            <!-- <h4>Historique des grades</h4> -->
            <div id="grades-list" class="grades-list">
                <div class="loading">Chargement des grades...</div>
            </div>
        </div>
    </div>

    <!-- Edit Grade Modal -->
    <div id="editGradeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditGradeModal()">&times;</span>
            <h3>Modifier le grade</h3>
            <form id="editGradeForm">
                <input type="hidden" id="edit_id_dev" name="id_dev">
                <div class="form-group">
                    <label for="edit_id_grade">Grade:</label>
                    <select id="edit_id_grade" name="id_grade" required>
                        <option value="">Sélectionner un grade</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_date_debut_grade">Date de début:</label>
                    <input type="date" id="edit_date_debut_grade" name="date_debut_grade" required>
                </div>
                <div class="form-group">
                    <label for="edit_date_fin_grade">Date de fin (optionnel):</label>
                    <input type="date" id="edit_date_fin_grade" name="date_fin_grade">
                </div>
                <button type="submit" class="btn btn-warning">Modifier</button>
                <button type="button" class="btn" onclick="closeEditGradeModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Salary Grid Modal -->
    <div id="salaryGridModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeSalaryGridModal()">&times;</span>
        <h3>Grille des salaires par grade</h3>
        <div class="salary-grid">
            <div id="salary-grid-loading" class="loading">Chargement de la grille des salaires...</div>
            <table id="salary-grid-table" style="display: none;">
                <thead>
                    <tr>
                        <th>Grade</th>
                        <th>Salaire par séance (DH)</th>
                    </tr>
                </thead>
                <tbody id="salary-grid-tbody">
                    <!-- Les grades seront chargés dynamiquement ici -->
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <script>
        // Global variables
        let professors = [];
        let availableUsers = [];
        let currentProfessorGrades = [];
        let grades = [];

        document.addEventListener('DOMContentLoaded', function() {
            // Mettre à jour le HTML des modals
            updateAddGradeModalHTML();
            updateEditGradeModalHTML();
            
            // Charger les données
            refreshProfessors();
            loadAvailableUsers();
            loadGrades();
        });

        function validateGradeDates(dateDebut, dateFin) {
            if (!dateDebut) {
                return { valid: false, message: 'La date de début est obligatoire' };
            }
            
            if (dateFin && new Date(dateFin) <= new Date(dateDebut)) {
                return { valid: false, message: 'La date de fin doit être postérieure à la date de début' };
            }
            
            return { valid: true };
        }

        function debugUsers() {
            console.log('Available users:', availableUsers);
            console.log('User dropdown element:', document.getElementById('add_id_user'));
        }

        function refreshProfessors() {
            console.log('Refreshing professors...');
            document.getElementById('professors-table-container').innerHTML = '<div class="loading">Chargement des professeurs...</div>';
            
            fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_professors'
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                if (data.success) {
                    console.log('Professors loaded successfully:', data.professors);
                    professors = data.professors;
                    displayProfessors();
                } else {
                    console.error('Error:', data.message);
                    showErrorMessage(data.message || 'Erreur lors du chargement des professeurs');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Erreur lors du chargement des professeurs');
            });
        }

        

        function deleteProfessor(profId, profName) {
            const confirmMessage = `Êtes-vous sûr de vouloir supprimer le professeur ${profName}?`;
            
            if (confirm(confirmMessage)) {
                // Demande une double confirmation pour les suppressions importantes
                const doubleConfirm = confirm(`Dernière confirmation :\n\nTapez "OUI" pour confirmer la suppression définitive de ${profName}`);
                
                if (doubleConfirm) {
                    const formData = new FormData();
                    formData.append('action', 'delete_professor');
                    formData.append('id_prof', profId);

                    // Afficher un indicateur de chargement
                    showLoadingMessage('Suppression en cours...');

                    fetch('manage_professors.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoadingMessage();
                        if (data.success) {
                            showSuccessMessage(data.message);
                            refreshProfessors();
                            // Recharger aussi les utilisateurs disponibles
                            loadAvailableUsers();
                        } else {
                            showErrorMessage(data.message || 'Erreur lors de la suppression du professeur');
                        }
                    })
                    .catch(error => {
                        hideLoadingMessage();
                        showErrorMessage('Erreur lors de la communication avec le serveur');
                        console.error('Error:', error);
                    });
                }
            }
        }

        function showLoadingMessage(message) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="loading-spinner"></i> ${message}
                </div>
            `;
        }

        function hideLoadingMessage() {
            // Le message sera remplacé par le message de succès ou d'erreur
        }



        document.getElementById('editProfessorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_professor');

            fetch('manage_professors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message);
                    refreshProfessors();
                    closeEditProfessorModal();
                } else {
                    showErrorMessage(data.message || 'Erreur lors de la modification du professeur');
                }
            })
            .catch(error => {
                showErrorMessage('Erreur lors de la communication avec le serveur');
                console.error('Error:', error);
            });
        });
        // Load available users for the dropdown
        function loadAvailableUsers() {
            fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_available_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    availableUsers = data.users;
                    displayAvailableUsers();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors du chargement des utilisateurs', 'error');
            });
        }

        // Display available users in the dropdown
        function populateUserDropdown() {
            const userSelect = document.getElementById('add_id_user');
            if (!userSelect) {
                console.error('User select element not found');
                return;
            }

            console.log('Populating user dropdown with', availableUsers.length, 'users');
            userSelect.innerHTML = '<option value="">Sélectionner un utilisateur</option>';
            availableUsers.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id_user;
                option.textContent = `${user.nom} ${user.prenom} (${user.username})`;
                userSelect.appendChild(option);
            });
        }

        // Load grades for the dropdown
        function loadGrades() {
            return fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_grades'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    grades = data.grades;
                    populateAddGradeDropdown(); // Pour les autres dropdowns
                    console.log('Grades loaded:', grades);
                    return grades;
                } else {
                    console.error('Error loading grades:', data.message);
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                throw error;
            });
        }

        // Populate the grade dropdown
        // Nouvelle fonction pour charger la grille des salaires
        function loadSalaryGrid() {
            const loadingDiv = document.getElementById('salary-grid-loading');
            const tableDiv = document.getElementById('salary-grid-table');
            
            loadingDiv.style.display = 'block';
            tableDiv.style.display = 'none';
            
            fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_salary_grid'
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.style.display = 'none';
                
                if (data.success) {
                    displaySalaryGrid(data.grades);
                    tableDiv.style.display = 'table';
                } else {
                    showErrorMessage('Erreur lors du chargement de la grille des salaires: ' + data.message);
                }
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                console.error('Error:', error);
                showErrorMessage('Erreur lors du chargement de la grille des salaires');
            });
        }

        // Nouvelle fonction pour afficher la grille des salaires
        function displaySalaryGrid(grades) {
            const tbody = document.getElementById('salary-grid-tbody');
            tbody.innerHTML = '';
            
            if (grades.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2">Aucun grade trouvé dans la base de données</td></tr>';
                return;
            }
            
            grades.forEach(grade => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${grade.nom_grade}</td>
                    <td class="salary">${parseFloat(grade.salaire_par_seance).toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Modifiez aussi la fonction populateGradeDropdown pour utiliser les données de la base :
        function populateAddGradeDropdown() {
            const gradeSelect = document.getElementById('add_id_grade');
            if (!gradeSelect) {
                console.error('Add grade select element not found');
                return;
            }
            
            gradeSelect.innerHTML = '<option value="">Sélectionner un grade</option>';
            
            if (grades && grades.length > 0) {
                grades.forEach(grade => {
                    const option = document.createElement('option');
                    option.value = grade.id_grade;
                    option.textContent = `${grade.nom_grade} (${parseFloat(grade.salaire_par_seance).toFixed(2)} DH/séance)`;
                    gradeSelect.appendChild(option);
                });
            }
        }

        // Open add professor modal
        function openAddProfessorModal() {
            document.getElementById('addProfessorModal').style.display = 'block';
            // Clear form
            document.getElementById('addProfessorForm').reset();
            // Load available users and grades
            loadAvailableUsers();
            loadGrades(); // Charger les grades depuis la base de données
        }

        function populateUserDropdown() {
            const userSelect = document.getElementById('add_id_user');
            if (!userSelect) {
                console.error('User select element not found');
                return;
            }

            console.log('Populating user dropdown with', availableUsers.length, 'users');
            userSelect.innerHTML = '<option value="">Sélectionner un utilisateur</option>';
            
            if (availableUsers && availableUsers.length > 0) {
                availableUsers.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id_user;
                    option.textContent = `${user.nom} ${user.prenom} (${user.username})`;
                    userSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Aucun utilisateur disponible';
                userSelect.appendChild(option);
            }
        }

        // Close add professor modal
        function closeAddProfessorModal() {
            document.getElementById('addProfessorModal').style.display = 'none';
        }

        // Close add professor modal
        function closeAddProfessorModal() {
            document.getElementById('addProfessorModal').style.display = 'none';
        }

        // Handle add professor form submission
        document.getElementById('addProfessorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_professor');

            fetch('manage_professors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message);
                    refreshProfessors();
                    closeAddProfessorModal();
                } else {
                    showErrorMessage(data.message || 'Erreur lors de l\'ajout du professeur');
                }
            })
            .catch(error => {
                showErrorMessage('Erreur lors de la communication avec le serveur');
                console.error('Error:', error);
            });
        });

        // Show success message
        function showSuccessMessage(message) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-success">
                    ${message}
                </div>
            `;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 3000);
        }

        // Show error message
        function showErrorMessage(message) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-danger">
                    ${message}
                </div>
            `;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 3000);
        }
        const gradesSalaires = {
            'grade A': 200.00,
            'grade B': 300.00,
            'grade C': 400.00
        };

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableUsers();
            loadProfessors();
        });

        // Load all professors
        function loadProfessors() {
            fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_professors'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    professors = data.professors;
                    displayProfessors();
                } else {
                    showAlert('Erreur lors du chargement des professeurs', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Load available users
        function loadAvailableUsers() {
            fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_available_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    availableUsers = data.users;
                    populateUserDropdown(); // Appeler cette fonction ici
                } else {
                    console.error('Error loading users:', data.message);
                    showErrorMessage('Erreur lors du chargement des utilisateurs');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Erreur lors du chargement des utilisateurs');
            });
        }

        // Display professors in table
        function displayProfessors() {
            const container = document.getElementById('professors-table-container');
            
            if (professors.length === 0) {
                container.innerHTML = '<p>Aucun professeur trouvé.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Username</th>
                            <th>Type</th>
                            <th>Grade</th>
                            <th>Salaire/Séance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            professors.forEach(prof => {
                const typeClass = prof.type_prof === 'permanent' ? 'type-permanent' : 'type-vacataire';
                const gradeDisplay = prof.id_grade ? 
                    `<span class="grade-badge">${prof.nom_grade}</span>` : 
                    '<span class="grade-badge">Aucun grade</span>';
                const salaryDisplay = prof.id_grade ? 
                    `<span class="salary">${parseFloat(prof.salaire_par_seance).toFixed(2)} DH</span>` : 
                    '<span class="salary">0.00 DH</span>';
                
                html += `
                    <tr>
                        <td>${prof.nom}</td>
                        <td>${prof.prenom}</td>
                        <td>${prof.username}</td>
                        <td><span class="type-badge ${typeClass}">${prof.type_prof}</span></td>
                        <td>${gradeDisplay}</td>
                        <td>${salaryDisplay}</td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick="openEditProfessorModal(${prof.id_prof})">
                                Modifier
                            </button>
                            <button class="btn btn-info btn-sm" onclick="openGradesModal(${prof.id_prof})">
                                Grades
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteProfessor(${prof.id_prof}, '${prof.nom} ${prof.prenom}')">
                                Supprimer
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        // Open grades modal
// 3. Modifier le HTML du modal d'ajout de grade
function updateAddGradeModalHTML() {
    const addGradeForm = document.getElementById('addGradeForm');
    addGradeForm.innerHTML = `
        <input type="hidden" id="grade_id_prof" name="id_prof">
        <div class="form-group">
            <label for="add_id_grade">Grade:</label>
            <select id="add_id_grade" name="id_grade" required>
                <option value="">Sélectionner un grade</option>
                <!-- Les grades seront chargés dynamiquement -->
            </select>
        </div>
        <div class="form-group">
            <label for="add_date_debut_grade">Date de début:</label>
            <input type="date" id="add_date_debut_grade" name="date_debut_grade" required>
        </div>
        <div class="form-group">
            <label for="add_date_fin_grade">Date de fin (optionnel):</label>
            <input type="date" id="add_date_fin_grade" name="date_fin_grade">
            <small>Laissez vide pour un grade actuel</small>
        </div>
        <button type="submit" class="btn btn-success">Ajouter le grade</button>
    `;
}

// 4. Modifier le HTML du modal d'édition de grade
function updateEditGradeModalHTML() {
    const editGradeForm = document.getElementById('editGradeForm');
    editGradeForm.innerHTML = `
        <input type="hidden" id="edit_id_dev" name="id_dev">
        <div class="form-group">
            <label for="edit_id_grade">Grade:</label>
            <select id="edit_id_grade" name="id_grade" required>
                <option value="">Sélectionner un grade</option>
                <!-- Les grades seront chargés dynamiquement -->
            </select>
        </div>
        <div class="form-group">
            <label for="edit_date_debut_grade">Date de début:</label>
            <input type="date" id="edit_date_debut_grade" name="date_debut_grade" required>
        </div>
        <div class="form-group">
            <label for="edit_date_fin_grade">Date de fin (optionnel):</label>
            <input type="date" id="edit_date_fin_grade" name="date_fin_grade">
            <small>Laissez vide pour un grade actuel</small>
        </div>
        <button type="submit" class="btn btn-warning">Modifier</button>
        <button type="button" class="btn" onclick="closeEditGradeModal()">Annuler</button>
    `;
}

// 5. Fonction pour peupler les dropdowns de grades dans les modals
function populateGradeDropdowns() {
    // Pour le modal d'ajout
    const addGradeSelect = document.getElementById('add_id_grade');
    if (addGradeSelect) {
        addGradeSelect.innerHTML = '<option value="">Sélectionner un grade</option>';
        grades.forEach(grade => {
            const option = document.createElement('option');
            option.value = grade.id_grade;
            option.textContent = `${grade.nom_grade} (${parseFloat(grade.salaire_par_seance).toFixed(2)} DH/séance)`;
            addGradeSelect.appendChild(option);
        });
    }

    // Pour le modal d'édition
    const editGradeSelect = document.getElementById('edit_id_grade');
    if (editGradeSelect) {
        editGradeSelect.innerHTML = '<option value="">Sélectionner un grade</option>';
        grades.forEach(grade => {
            const option = document.createElement('option');
            option.value = grade.id_grade;
            option.textContent = `${grade.nom_grade} (${parseFloat(grade.salaire_par_seance).toFixed(2)} DH/séance)`;
            editGradeSelect.appendChild(option);
        });
    }
}

        // 6. Modifier openGradesModal pour inclure le peuplement des dropdowns
        function openGradesModal(profId) {
            const prof = professors.find(p => p.id_prof == profId);
            if (!prof) return;

            document.getElementById('grades-modal-title').textContent = `Historique des grades pour ${prof.nom} ${prof.prenom}`;
            // document.getElementById('grade_id_prof').value = prof.id_prof;
            
            // Charger les grades disponibles et peupler les dropdowns
            if (grades.length === 0) {
                loadGrades().then(() => {
                    populateGradeDropdowns();
                });
            } else {
                populateGradeDropdowns();
            }
            
            // Load current grades
            fetch('manage_professors.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_professor_grades&id_prof=' + prof.id_prof
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentProfessorGrades = data.grades;
                    displayGrades(data.grades);
                } else {
                    showErrorMessage('Erreur lors du chargement des grades: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Erreur lors du chargement des grades');
            });

            document.getElementById('gradesModal').style.display = 'block';
        }

        // CORRECTION 15: Ajouter la fonction showAlert manquante
        function showAlert(message, type) {
            if (type === 'success') {
                showSuccessMessage(message);
            } else {
                showErrorMessage(message);
            }
        }

        // Close grades modal
        function closeGradesModal() {
            document.getElementById('gradesModal').style.display = 'none';
        }

        // Display grades in list
        function displayGrades(grades) {
            const gradesList = document.getElementById('grades-list');
            gradesList.innerHTML = '';

            if (grades.length === 0) {
                gradesList.innerHTML = '<p>Aucun grade trouvé pour ce professeur.</p>';
                return;
            }

            grades.forEach(grade => {
                const gradeItem = document.createElement('div');
                gradeItem.className = `grade-item ${grade.date_fin_grade ? '' : 'grade-current'}`;
                gradeItem.innerHTML = `
                    <div class="grade-info">
                        <div>
                            <strong>Grade:</strong> ${grade.nom_grade} 
                            <span class="salary">(${parseFloat(grade.salaire_par_seance).toFixed(2)} DH/séance)</span>
                        </div>
                        <div>
                            <strong>Date de début:</strong> ${grade.date_debut_grade}
                        </div>
                        ${grade.date_fin_grade ? 
                            `<div><strong>Date de fin:</strong> ${grade.date_fin_grade}</div>` : 
                            '<div class="current-badge">Grade actuel</div>'
                        }
                    </div>
                    <div class="grade-actions">
                        <button class="btn btn-warning btn-sm" onclick="openEditGradeModal(${grade.id_dev})">
                            Modifier
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteGrade(${grade.id_dev})">
                            Supprimer
                        </button>
                    </div>
                `;
                gradesList.appendChild(gradeItem);
            });
        }



        function openEditProfessorModal(profId) {
            const prof = professors.find(p => p.id_prof == profId);
            if (!prof) {
                console.error('Professor not found:', profId);
                return;
            }

            // Remplir le formulaire avec les données du professeur
            document.getElementById('edit_id_prof').value = prof.id_prof;
            document.getElementById('edit_nom').value = prof.nom;
            document.getElementById('edit_prenom').value = prof.prenom;
            document.getElementById('edit_type_prof').value = prof.type_prof;
            
            // Charger les grades dynamiquement dans le dropdown
            populateEditGradeDropdown(prof.id_grade);

            document.getElementById('editProfessorModal').style.display = 'block';
        }

        function populateEditGradeDropdown(currentGradeId = null) {
            const gradeSelect = document.getElementById('edit_id_grade');
            if (!gradeSelect) {
                console.error('Edit grade select element not found');
                return;
            }
            
            gradeSelect.innerHTML = '<option value="">Sélectionner un grade</option>';
            
            if (grades && grades.length > 0) {
                grades.forEach(grade => {
                    const option = document.createElement('option');
                    option.value = grade.id_grade;
                    option.textContent = `${grade.nom_grade} (${parseFloat(grade.salaire_par_seance).toFixed(2)} DH/séance)`;
                    if (currentGradeId && grade.id_grade == currentGradeId) {
                        option.selected = true;
                    }
                    gradeSelect.appendChild(option);
                });
            }
        }


        // Open edit grade modal
        function openEditGradeModal(id_dev) {
            const grade = currentProfessorGrades.find(g => g.id_dev == id_dev);
            if (!grade) {
                console.error('Grade not found:', id_dev);
                return;
            }

            document.getElementById('edit_id_dev').value = grade.id_dev;
            document.getElementById('edit_id_grade').value = grade.id_grade; // Utiliser id_grade
            document.getElementById('edit_date_debut_grade').value = grade.date_debut_grade;
            document.getElementById('edit_date_fin_grade').value = grade.date_fin_grade || '';

            document.getElementById('editGradeModal').style.display = 'block';
        }
        // Close edit grade modal
        function closeEditGradeModal() {
            document.getElementById('editGradeModal').style.display = 'none';
        }

        function closeEditProfessorModal() {
            document.getElementById('editProfessorModal').style.display = 'none';
        }

        // Show salary grid
        function showSalaryGrid() {
            document.getElementById('salaryGridModal').style.display = 'block';
            loadSalaryGrid();
        }

        // Close salary grid modal
        function closeSalaryGridModal() {
            document.getElementById('salaryGridModal').style.display = 'none';
        }

        // Handle add grade form submission
        document.getElementById('addGradeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const dateDebut = document.getElementById('add_date_debut_grade').value;
            const dateFin = document.getElementById('add_date_fin_grade').value;
            
            const validation = validateGradeDates(dateDebut, dateFin);
            if (!validation.valid) {
                showErrorMessage(validation.message);
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_grade');
            
            fetch('manage_professors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message);
                    // Recharger les grades et actualiser l'affichage
                    openGradesModal(document.getElementById('grade_id_prof').value);
                    // Actualiser aussi la liste des professeurs pour voir le grade mis à jour
                    refreshProfessors();
                } else {
                    showErrorMessage(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Erreur lors de l\'ajout du grade');
            });
        });


        // Handle edit grade form submission
        document.getElementById('editGradeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_grade');

            fetch('manage_professors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeEditGradeModal();
                    openGradesModal(document.getElementById('grade_id_prof').value);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de la modification du grade', 'error');
            });
        });

        // Delete grade
        function deleteGrade(id_dev) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce grade ?')) {
                const formData = new FormData();
                formData.append('action', 'delete_grade');
                formData.append('id_dev', id_dev);

                fetch('manage_professors.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        openGradesModal(document.getElementById('grade_id_prof').value);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression du grade', 'error');
                });
            }
        }
    </script>
</body>
</html>