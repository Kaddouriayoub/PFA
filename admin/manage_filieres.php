<?php
// manage_filieres.php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ensias_payment";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    header("Location: index.php?error=access_denied");
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_filieres':
                $stmt = $conn->prepare("SELECT * FROM Filiere ORDER BY nom_filiere");
                $stmt->execute();
                $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'filieres' => $filieres]);
                exit;
                
            case 'get_annees':
                $filiere_id = intval($_POST['filiere_id']);
                $stmt = $conn->prepare("
                    SELECT a.id_annee, a.annee_universitaire, a.niveau, f.nom_filiere 
                    FROM Annee a
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    WHERE a.id_filiere = ?
                    ORDER BY a.annee_universitaire DESC, a.niveau
                ");
                $stmt->execute([$filiere_id]);
                $annees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'annees' => $annees]);
                exit;
                
            case 'get_semestres':
                $annee_id = intval($_POST['annee_id']);
                $stmt = $conn->prepare("
                    SELECT s.id_semestre, s.nom_semestre, a.annee_universitaire, a.niveau, f.nom_filiere 
                    FROM Semestre s
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    WHERE s.id_annee = ?
                    ORDER BY s.nom_semestre
                ");
                $stmt->execute([$annee_id]);
                $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'semestres' => $semestres]);
                exit;
                
            case 'add_filiere':
                $nom_filiere = trim($_POST['nom_filiere']);
                
                if (empty($nom_filiere)) {
                    echo json_encode(['success' => false, 'message' => 'Le nom de la filière est obligatoire']);
                    exit;
                }
                
                // Check if filiere already exists
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Filiere WHERE nom_filiere = ?");
                $stmt->execute([$nom_filiere]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cette filière existe déjà']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Filiere (nom_filiere) VALUES (?)");
                $stmt->execute([$nom_filiere]);
                
                echo json_encode(['success' => true, 'message' => 'Filière ajoutée avec succès']);
                exit;
                
            case 'add_annee':
                $annee_universitaire = trim($_POST['annee_universitaire']);
                $niveau = trim($_POST['niveau']);
                $id_filiere = intval($_POST['id_filiere']);
                
                if (empty($annee_universitaire) || empty($niveau) || $id_filiere <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                // Check if annee already exists for this filiere
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Annee WHERE annee_universitaire = ? AND niveau = ? AND id_filiere = ?");
                $stmt->execute([$annee_universitaire, $niveau, $id_filiere]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cette année existe déjà pour cette filière']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Annee (annee_universitaire, niveau, id_filiere) VALUES (?, ?, ?)");
                $stmt->execute([$annee_universitaire, $niveau, $id_filiere]);
                
                echo json_encode(['success' => true, 'message' => 'Année ajoutée avec succès']);
                exit;
                
            case 'add_semestre':
                $nom_semestre = trim($_POST['nom_semestre']);
                $id_annee = intval($_POST['id_annee']);
                
                if (empty($nom_semestre) || $id_annee <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                // Check if semestre already exists for this annee
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Semestre WHERE nom_semestre = ? AND id_annee = ?");
                $stmt->execute([$nom_semestre, $id_annee]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Ce semestre existe déjà pour cette année']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Semestre (nom_semestre, id_annee) VALUES (?, ?)");
                $stmt->execute([$nom_semestre, $id_annee]);
                
                echo json_encode(['success' => true, 'message' => 'Semestre ajouté avec succès']);
                exit;
                
            case 'delete_filiere':
                $id_filiere = intval($_POST['id_filiere']);
                
                // Check if filiere has related records
                $tables_to_check = ['Annee'];
                foreach ($tables_to_check as $table) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE id_filiere = ?");
                    $stmt->execute([$id_filiere]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cette filière car elle a des années associées']);
                        exit;
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM Filiere WHERE id_filiere = ?");
                $stmt->execute([$id_filiere]);
                
                echo json_encode(['success' => true, 'message' => 'Filière supprimée avec succès']);
                exit;
                
            case 'delete_annee':
                $id_annee = intval($_POST['id_annee']);
                
                // Check if annee has related records
                $tables_to_check = ['Semestre'];
                foreach ($tables_to_check as $table) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE id_annee = ?");
                    $stmt->execute([$id_annee]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cette année car elle a des semestres associés']);
                        exit;
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM Annee WHERE id_annee = ?");
                $stmt->execute([$id_annee]);
                
                echo json_encode(['success' => true, 'message' => 'Année supprimée avec succès']);
                exit;
                
            case 'delete_semestre':
                $id_semestre = intval($_POST['id_semestre']);
                
                // Check if semestre has related records
                $tables_to_check = ['Module'];
                foreach ($tables_to_check as $table) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE id_semestre = ?");
                    $stmt->execute([$id_semestre]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer ce semestre car il a des modules associés']);
                        exit;
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM Semestre WHERE id_semestre = ?");
                $stmt->execute([$id_semestre]);
                
                echo json_encode(['success' => true, 'message' => 'Semestre supprimé avec succès']);
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
    <title>Gestion des Filières - ENSIAS</title>
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
            max-width: 1200px;
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
        }
        
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { opacity: 0.8; }
        
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
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
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
            margin: 5% auto;
            padding: 20px;
            border: none;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
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
        
        .filiere-details, .annee-details {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Gestion des Filières et Années</h1>
        </div>
    </div>

    <div class="container">
        <a href="admin_dashboard.php" class="back-btn">← Retour au Dashboard</a>

        <div class="professor-management">
            <h3>Liste des Filières</h3>
            <button class="btn btn-success" onclick="openAddFiliereModal()">Ajouter une filière</button>
            <button class="btn btn-primary" onclick="refreshFilieres()">Actualiser</button>
            
            <div id="alert-container"></div>
            
            <div id="filieres-table-container">
                <div class="loading">Chargement des filières...</div>
            </div>
        </div>
    </div>

    <!-- Add Filiere Modal -->
    <div id="addFiliereModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddFiliereModal()">&times;</span>
            <h3>Ajouter une nouvelle filière</h3>
            <form id="addFiliereForm">
                <div class="form-group">
                    <label for="add_nom_filiere">Nom de la filière:</label>
                    <input type="text" id="add_nom_filiere" name="nom_filiere" required>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddFiliereModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Add Annee Modal -->
    <div id="addAnneeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddAnneeModal()">&times;</span>
            <h3>Ajouter une nouvelle année</h3>
            <form id="addAnneeForm">
                <input type="hidden" id="add_annee_filiere_id" name="id_filiere">
                <div class="form-group">
                    <label for="add_annee_universitaire">Année universitaire:</label>
                    <input type="text" id="add_annee_universitaire" name="annee_universitaire" placeholder="2023/2024" required>
                </div>
                <div class="form-group">
                    <label for="add_niveau">Niveau:</label>
                    <input type="text" id="add_niveau" name="niveau" placeholder="1A, 2A, etc." required>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddAnneeModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Add Semestre Modal -->
    <div id="addSemestreModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddSemestreModal()">&times;</span>
            <h3>Ajouter un nouveau semestre</h3>
            <form id="addSemestreForm">
                <input type="hidden" id="add_semestre_annee_id" name="id_annee">
                <div class="form-group">
                    <label for="add_nom_semestre">Nom du semestre:</label>
                    <input type="text" id="add_nom_semestre" name="nom_semestre" placeholder="S1, S2, etc." required>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddSemestreModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        let filieres = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadFilieres();
        });

        function loadFilieres() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_filieres'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    filieres = data.filieres;
                    displayFilieres();
                } else {
                    showAlert('Erreur lors du chargement des filières', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        function displayFilieres() {
            const container = document.getElementById('filieres-table-container');
            
            if (filieres.length === 0) {
                container.innerHTML = '<p>Aucune filière trouvée.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            filieres.forEach(filiere => {
                html += `
                    <tr>
                        <td>${filiere.id_filiere}</td>
                        <td>${filiere.nom_filiere}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="showFiliereAnnees(${filiere.id_filiere}, '${filiere.nom_filiere}')">
                                Années
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteFiliere(${filiere.id_filiere}, '${filiere.nom_filiere}')">
                                Supprimer
                            </button>
                        </td>
                    </tr>
                    <tr id="filiere-annees-${filiere.id_filiere}" class="filiere-details">
                        <td colspan="3">
                            <div id="annees-container-${filiere.id_filiere}">
                                Chargement des années...
                            </div>
                            <button class="btn btn-success btn-sm" onclick="openAddAnneeModal(${filiere.id_filiere}, '${filiere.nom_filiere}')">
                                Ajouter une année
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

        function showFiliereAnnees(filiereId, filiereName) {
            const row = document.getElementById(`filiere-annees-${filiereId}`);
            const container = document.getElementById(`annees-container-${filiereId}`);
            
            // Toggle display
            if (row.style.display === 'none' || !row.style.display) {
                row.style.display = 'table-row';
                
                // Load annees if not already loaded
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_annees&filiere_id=${filiereId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let anneesHtml = `<h4>Années de la filière ${filiereName}:</h4>`;
                        
                        if (data.annees.length === 0) {
                            anneesHtml += '<p>Aucune année trouvée pour cette filière.</p>';
                        } else {
                            anneesHtml += `
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Année Universitaire</th>
                                            <th>Niveau</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            
                            data.annees.forEach(annee => {
                                anneesHtml += `
                                    <tr>
                                        <td>${annee.id_annee}</td>
                                        <td>${annee.annee_universitaire}</td>
                                        <td>${annee.niveau}</td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="showAnneeSemestres(${annee.id_annee}, '${annee.annee_universitaire} - ${annee.niveau}')">
                                                Semestres
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteAnnee(${annee.id_annee}, '${annee.annee_universitaire} - ${annee.niveau}')">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="annee-semestres-${annee.id_annee}" class="annee-details">
                                        <td colspan="4">
                                            <div id="semestres-container-${annee.id_annee}">
                                                Chargement des semestres...
                                            </div>
                                            <button class="btn btn-success btn-sm" onclick="openAddSemestreModal(${annee.id_annee}, '${annee.annee_universitaire} - ${annee.niveau}')">
                                                Ajouter un semestre
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            
                            anneesHtml += `
                                    </tbody>
                                </table>
                            `;
                        }
                        
                        container.innerHTML = anneesHtml;
                    } else {
                        container.innerHTML = '<p class="error">Erreur lors du chargement des années</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="error">Erreur de connexion</p>';
                });
            } else {
                row.style.display = 'none';
            }
        }

        function showAnneeSemestres(anneeId, anneeName) {
            const row = document.getElementById(`annee-semestres-${anneeId}`);
            const container = document.getElementById(`semestres-container-${anneeId}`);
            
            // Toggle display
            if (row.style.display === 'none' || !row.style.display) {
                row.style.display = 'table-row';
                
                // Load semestres if not already loaded
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_semestres&annee_id=${anneeId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let semestresHtml = `<h4>Semestres de l'année ${anneeName}:</h4>`;
                        
                        if (data.semestres.length === 0) {
                            semestresHtml += '<p>Aucun semestre trouvé pour cette année.</p>';
                        } else {
                            semestresHtml += `
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            
                            data.semestres.forEach(semestre => {
                                semestresHtml += `
                                    <tr>
                                        <td>${semestre.id_semestre}</td>
                                        <td>${semestre.nom_semestre}</td>
                                        <td>
                                            <button class="btn btn-danger btn-sm" onclick="deleteSemestre(${semestre.id_semestre}, '${semestre.nom_semestre}')">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            
                            semestresHtml += `
                                    </tbody>
                                </table>
                            `;
                        }
                        
                        container.innerHTML = semestresHtml;
                    } else {
                        container.innerHTML = '<p class="error">Erreur lors du chargement des semestres</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="error">Erreur de connexion</p>';
                });
            } else {
                row.style.display = 'none';
            }
        }

        function openAddFiliereModal() {
            document.getElementById('addFiliereModal').style.display = 'block';
            document.getElementById('addFiliereForm').reset();
        }

        function closeAddFiliereModal() {
            document.getElementById('addFiliereModal').style.display = 'none';
        }

        function openAddAnneeModal(filiereId, filiereName) {
            document.getElementById('add_annee_filiere_id').value = filiereId;
            document.getElementById('addAnneeModal').style.display = 'block';
            document.getElementById('addAnneeForm').reset();
        }

        function closeAddAnneeModal() {
            document.getElementById('addAnneeModal').style.display = 'none';
        }

        function openAddSemestreModal(anneeId, anneeName) {
            document.getElementById('add_semestre_annee_id').value = anneeId;
            document.getElementById('addSemestreModal').style.display = 'block';
            document.getElementById('addSemestreForm').reset();
        }

        function closeAddSemestreModal() {
            document.getElementById('addSemestreModal').style.display = 'none';
        }

        // Handle add filiere form submission
        document.getElementById('addFiliereForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_filiere');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddFiliereModal();
                    loadFilieres();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout de la filière', 'error');
            });
        });

        // Handle add annee form submission
        document.getElementById('addAnneeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_annee');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddAnneeModal();
                    // Refresh the annees list for this filiere
                    const filiereId = document.getElementById('add_annee_filiere_id').value;
                    const container = document.getElementById(`annees-container-${filiereId}`);
                    container.innerHTML = 'Chargement des années...';
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_annees&filiere_id=${filiereId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let anneesHtml = '';
                            
                            if (data.annees.length === 0) {
                                anneesHtml = '<p>Aucune année trouvée pour cette filière.</p>';
                            } else {
                                anneesHtml = `
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Année Universitaire</th>
                                                <th>Niveau</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;
                                
                                data.annees.forEach(annee => {
                                    anneesHtml += `
                                        <tr>
                                            <td>${annee.id_annee}</td>
                                            <td>${annee.annee_universitaire}</td>
                                            <td>${annee.niveau}</td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="showAnneeSemestres(${annee.id_annee}, '${annee.annee_universitaire} - ${annee.niveau}')">
                                                    Semestres
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteAnnee(${annee.id_annee}, '${annee.annee_universitaire} - ${annee.niveau}')">
                                                    Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                        <tr id="annee-semestres-${annee.id_annee}" class="annee-details">
                                            <td colspan="4">
                                                <div id="semestres-container-${annee.id_annee}">
                                                    Chargement des semestres...
                                                </div>
                                                <button class="btn btn-success btn-sm" onclick="openAddSemestreModal(${annee.id_annee}, '${annee.annee_universitaire} - ${annee.niveau}')">
                                                    Ajouter un semestre
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                                
                                anneesHtml += `
                                        </tbody>
                                    </table>
                                `;
                            }
                            
                            container.innerHTML = anneesHtml;
                        }
                    });
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout de l\'année', 'error');
            });
        });

        // Handle add semestre form submission
        document.getElementById('addSemestreForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_semestre');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddSemestreModal();
                    // Refresh the semestres list for this annee
                    const anneeId = document.getElementById('add_semestre_annee_id').value;
                    const container = document.getElementById(`semestres-container-${anneeId}`);
                    container.innerHTML = 'Chargement des semestres...';
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_semestres&annee_id=${anneeId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let semestresHtml = '';
                            
                            if (data.semestres.length === 0) {
                                semestresHtml = '<p>Aucun semestre trouvé pour cette année.</p>';
                            } else {
                                semestresHtml = `
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nom</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;
                                
                                data.semestres.forEach(semestre => {
                                    semestresHtml += `
                                        <tr>
                                            <td>${semestre.id_semestre}</td>
                                            <td>${semestre.nom_semestre}</td>
                                            <td>
                                                <button class="btn btn-danger btn-sm" onclick="deleteSemestre(${semestre.id_semestre}, '${semestre.nom_semestre}')">
                                                    Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                                
                                semestresHtml += `
                                        </tbody>
                                    </table>
                                `;
                            }
                            
                            container.innerHTML = semestresHtml;
                        }
                    });
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout du semestre', 'error');
            });
        });

        function deleteFiliere(filiereId, filiereName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer la filière "${filiereName}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_filiere');
                formData.append('id_filiere', filiereId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadFilieres();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression de la filière', 'error');
                });
            }
        }

        function deleteAnnee(anneeId, anneeName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'année "${anneeName}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_annee');
                formData.append('id_annee', anneeId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        // Find which filiere this annee belongs to and refresh its annees
                        const filiereRow = document.querySelector(`tr[id^="filiere-annees-"]`);
                        if (filiereRow) {
                            const filiereId = filiereRow.id.split('-')[2];
                            showFiliereAnnees(filiereId, '');
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression de l\'année', 'error');
                });
            }
        }

        function deleteSemestre(semestreId, semestreName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le semestre "${semestreName}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_semestre');
                formData.append('id_semestre', semestreId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        // Find which annee this semestre belongs to and refresh its semestres
                        const anneeRow = document.querySelector(`tr[id^="annee-semestres-"]`);
                        if (anneeRow) {
                            const anneeId = anneeRow.id.split('-')[2];
                            showAnneeSemestres(anneeId, '');
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression du semestre', 'error');
                });
            }
        }

        function refreshFilieres() {
            loadFilieres();
            showAlert('Liste des filières actualisée', 'success');
        }

        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        window.onclick = function(event) {
            const addFiliereModal = document.getElementById('addFiliereModal');
            const addAnneeModal = document.getElementById('addAnneeModal');
            const addSemestreModal = document.getElementById('addSemestreModal');
            
            if (event.target === addFiliereModal) {
                closeAddFiliereModal();
            }
            if (event.target === addAnneeModal) {
                closeAddAnneeModal();
            }
            if (event.target === addSemestreModal) {
                closeAddSemestreModal();
            }
        }
    </script>
</body>
</html>