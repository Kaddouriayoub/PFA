<?php
require_once '../config.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID du professeur
$stmt_prof = $pdo->prepare("SELECT id_prof FROM professeur WHERE id_user = ?");
$stmt_prof->execute([$user_id]);
$prof = $stmt_prof->fetch();

if (!$prof) {
    die("Accès refusé ou professeur non trouvé");
}

$professor_id = $prof['id_prof'];
$error_message = '';
$success_message = '';

// Vérifier si un ID de séance est fourni
if (!isset($_GET['id'])) {
    header('Location: professor_dashboard.php');
    exit();
}

$seance_id = $_GET['id'];

// Récupérer les informations de la séance
$query = "
    SELECT sr.*, em.nom_element, m.nom_module
    FROM seance_reelle sr
    JOIN element_module em ON sr.id_element = em.id_element
    JOIN module m ON em.id_module = m.id_module
    WHERE sr.id_seance = ? AND sr.id_prof = ?
";

$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id, $professor_id]);
$seance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    header('Location: professor_dashboard.php');
    exit();
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_seance = $_POST['id_seance'];
    $date_seance = $_POST['date_seance'];
    $type_seance = $_POST['type_seance'];
    $id_element = $_POST['id_element'];
    $statut = $_POST['statut'];

    try {
        // Vérifier si la séance n'est pas déjà validée
        if ($seance['status_admin'] === 'valide') {
            throw new Exception("Impossible de modifier une séance déjà validée.");
        }

        // Mettre à jour la séance
        $update_query = "
            UPDATE seance_reelle 
            SET date_seance = ?, 
                type_seance = ?, 
                id_element = ?,
                statut = ?
            WHERE id_seance = ? AND id_prof = ?
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            $date_seance,
            $type_seance,
            $id_element,
            $statut,
            $id_seance,
            $professor_id
        ]);

        $success_message = "La séance a été mise à jour avec succès.";
        
        // Recharger les informations de la séance
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id, $professor_id]);
        $seance = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Récupérer la liste des éléments de module du professeur
$query_elements = "
    SELECT DISTINCT em.id_element, em.nom_element, m.nom_module
    FROM element_module em
    JOIN module m ON em.id_module = m.id_module
    JOIN seance_reelle sr ON em.id_element = sr.id_element
    WHERE sr.id_prof = ?
";

$stmt = $pdo->prepare($query_elements);
$stmt->execute([$professor_id]);
$elements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la séance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        input[type="date"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .btn-secondary {
            background-color: #f44336;
            color: white;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #da190b;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .alert-danger {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        .seance-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .seance-info p {
            margin: 5px 0;
        }
        .buttons {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'professor_header.php'; ?>
    
    <div class="container">
        <h2>Modifier la séance</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="seance-info">
            <p><strong>Module:</strong> <?php echo htmlspecialchars($seance['nom_module']); ?></p>
            <p><strong>Élément:</strong> <?php echo htmlspecialchars($seance['nom_element']); ?></p>
            <p><strong>Statut administratif:</strong> <?php echo htmlspecialchars($seance['status_admin']); ?></p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="date_seance">Date de la séance</label>
                <input type="date" id="date_seance" name="date_seance" 
                       value="<?php echo htmlspecialchars($seance['date_seance']); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="type_seance">Type de séance</label>
                <select id="type_seance" name="type_seance" required>
                    <option value="Cours" <?php echo $seance['type_seance'] === 'Cours' ? 'selected' : ''; ?>>Cours</option>
                    <option value="TD" <?php echo $seance['type_seance'] === 'TD' ? 'selected' : ''; ?>>TD</option>
                    <option value="TP" <?php echo $seance['type_seance'] === 'TP' ? 'selected' : ''; ?>>TP</option>
                </select>
            </div>

            <div class="form-group">
                <label for="statut">Statut de la séance</label>
                <select id="statut" name="statut" required>
                    <option value="1" <?php echo $seance['statut'] === '1' ? 'selected' : ''; ?>>Validée</option>
                    <option value="0" <?php echo $seance['statut'] === '0' ? 'selected' : ''; ?>>Non validée</option>
                    <option value="" <?php echo $seance['statut'] === NULL ? 'selected' : ''; ?>>En attente</option>
                </select>
            </div>

            <div class="form-group">
                <label for="element_id">Élément de module</label>
                <select id="element_id" name="element_id" required>
                    <?php foreach ($elements as $element): ?>
                        <option value="<?php echo $element['id_element']; ?>" 
                                <?php echo $seance['id_element'] == $element['id_element'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($element['nom_element'] . ' (' . $element['nom_module'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="buttons">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                <a href="professor_dashboard.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</body>
</html> 