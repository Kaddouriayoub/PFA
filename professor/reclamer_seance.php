<?php
require_once '../config.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

try {
    // Récupérer les informations du professeur
    $stmt = $pdo->prepare("
        SELECT p.*, u.username 
        FROM professeur p 
        INNER JOIN utilisateur u ON p.id_user = u.id_user 
        WHERE u.id_user = ?
    ");
    $stmt->execute([$user_id]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        throw new Exception("Accès refusé. Vous devez être un professeur pour accéder à cette page.");
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réclamations des Séances - ENSIAS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        h1, h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 600;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
            max-width: 300px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 0.9em;
        }
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-effectuee {
            background-color: #d4edda;
            color: #155724;
        }
        .status-annulee {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-en-attente {
            background-color: #fff3cd;
            color: #856404;
        }
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .alert i {
            font-size: 18px;
        }
        .btn-nouvelle-reclamation {
            background-color: #4CAF50;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-nouvelle-reclamation:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }
        .close:hover {
            color: #333;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            flex: 1;
            min-width: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .btn-submit {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        .btn-submit:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-submit:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .seance-details {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .seance-details p {
            margin: 5px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
            resize: vertical;
        }
        .btn-reclamer {
            background-color: #ffc107;
            color: #000;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-reclamer:hover {
            background-color: #e0a800;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        input[type="date"], input[type="time"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            .modal-content {
                margin: 20px;
                padding: 20px;
                width: auto;
            }
            .btn-nouvelle-reclamation {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'professor_header.php'; ?>

    <div class="container">
        <h1>Réclamations des Séances</h1>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <button class="btn-nouvelle-reclamation" onclick="ouvrirNouvelleReclamation()">
            <i class="fas fa-plus-circle"></i>
            Réclamer une nouvelle séance
        </button>
    </div>

    <!-- Modal pour nouvelle réclamation -->
    <div id="nouvelleReclamationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeNouvelleReclamationModal()">&times;</span>
            <h2>Réclamer une nouvelle séance</h2>
            
            <form id="nouvelleReclamationForm" method="POST" onsubmit="return soumettraNouvelleReclamation(event);">
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_seance">
                            <i class="fas fa-calendar"></i>
                            Date de la séance
                        </label>
                        <input type="date" id="date_seance" name="date_seance" required>
                    </div>
                    <div class="form-group">
                        <label for="type_seance">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Type de séance
                        </label>
                        <select id="type_seance" name="type_seance" required>
                            <option value="">Sélectionner un type</option>
                            <option value="cours">Cours</option>
                            <option value="td">TD</option>
                            <option value="tp">TP</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="filiere">
                            <i class="fas fa-graduation-cap"></i>
                            Filière
                        </label>
                        <select id="filiere" name="filiere" required>
                            <option value="">Sélectionner une filière</option>
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT nom_filiere FROM Filiere");
                            while ($row = $stmt->fetch()) {
                                echo '<option value="' . htmlspecialchars($row['nom_filiere']) . '">' . 
                                     htmlspecialchars($row['nom_filiere']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="module">
                            <i class="fas fa-book"></i>
                            Module
                        </label>
                        <select id="module" name="module" required>
                            <option value="">Sélectionner un module</option>
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT nom_module FROM Module");
                            while ($row = $stmt->fetch()) {
                                echo '<option value="' . htmlspecialchars($row['nom_module']) . '">' . 
                                     htmlspecialchars($row['nom_module']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="element">
                            <i class="fas fa-bookmark"></i>
                            Élément
                        </label>
                        <select id="element" name="element" required>
                            <option value="">Sélectionner un élément</option>
                            <?php
                            $stmt = $pdo->query("SELECT DISTINCT nom_element FROM Element_Module");
                            while ($row = $stmt->fetch()) {
                                echo '<option value="' . htmlspecialchars($row['nom_element']) . '">' . 
                                     htmlspecialchars($row['nom_element']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="motif">
                        <i class="fas fa-comment-alt"></i>
                        Motif de la réclamation
                    </label>
                    <textarea id="motif" name="motif" required 
                              placeholder="Veuillez expliquer le motif de votre réclamation..."></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i>
                    Soumettre la réclamation
                </button>
            </form>
        </div>
    </div>

    <script>
        function soumettraNouvelleReclamation(event) {
            event.preventDefault();
            
            const form = document.getElementById('nouvelleReclamationForm');
            const formData = new FormData(form);

            // Désactiver le bouton de soumission
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

            // Supprimer les anciennes alertes
            form.querySelectorAll('.alert').forEach(alert => alert.remove());

            fetch('soumettre_nouvelle_reclamation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.text();
            })
            .then(text => {
                console.log('Réponse brute:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erreur parsing JSON:', e);
                    throw new Error('Réponse invalide du serveur');
                }
            })
            .then(data => {
                console.log('Données reçues:', data);
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${data.success ? 'success' : 'error'}`;
                alertDiv.innerHTML = `
                    <i class="fas fa-${data.success ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${data.message}
                `;
                form.insertBefore(alertDiv, form.firstChild);

                if (data.success) {
                    setTimeout(() => {
                        closeNouvelleReclamationModal();
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    ${error.message}
                `;
                form.insertBefore(alertDiv, form.firstChild);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Soumettre la réclamation';
            });

            return false;
        }

        function closeNouvelleReclamationModal() {
            const modal = document.getElementById('nouvelleReclamationModal');
            modal.style.display = 'none';
            document.getElementById('nouvelleReclamationForm').reset();
        }

        function ouvrirNouvelleReclamation() {
            const modal = document.getElementById('nouvelleReclamationModal');
            modal.style.display = 'block';
        }

        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('nouvelleReclamationModal');
            if (event.target == modal) {
                closeNouvelleReclamationModal();
            }
        }
    </script>
</body>
</html> 