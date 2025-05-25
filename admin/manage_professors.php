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
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php?error=access_denied");
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_professors':
                $stmt = $conn->prepare("
                    SELECT p.id_prof, p.nom, p.prenom, p.type_prof, p.salaire_par_seance, u.username
                    FROM Professeur p 
                    JOIN Utilisateur u ON p.id_user = u.id_user 
                    ORDER BY p.id_prof
                ");
                $stmt->execute();
                $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'professors' => $professors]);
                exit;
                
            case 'get_available_users':
                // Get users with professor role who are not already professors
                $stmt = $conn->prepare("
                    SELECT u.id_user, u.nom, u.prenom, u.username 
                    FROM Utilisateur u 
                    JOIN Role r ON u.id_role = r.id_role 
                    WHERE r.nom_role IN ('professor', 'professeur') 
                    AND u.id_user NOT IN (SELECT id_user FROM Professeur)
                    ORDER BY u.nom, u.prenom
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'users' => $users]);
                exit;
                
            case 'add_professor':
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $type_prof = $_POST['type_prof'];
                $salaire_par_seance = floatval($_POST['salaire_par_seance']);
                $id_user = intval($_POST['id_user']);
                
                // Validate input
                if (empty($nom) || empty($prenom) || empty($type_prof) || $salaire_par_seance <= 0 || $id_user <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires et le salaire doit être positif']);
                    exit;
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
                    INSERT INTO Professeur (nom, prenom, type_prof, salaire_par_seance, id_user) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $prenom, $type_prof, $salaire_par_seance, $id_user]);
                
                echo json_encode(['success' => true, 'message' => 'Professeur ajouté avec succès']);
                exit;
                
            case 'update_professor':
                $id_prof = intval($_POST['id_prof']);
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $type_prof = $_POST['type_prof'];
                $salaire_par_seance = floatval($_POST['salaire_par_seance']);
                
                // Validate input
                if ($id_prof <= 0 || empty($nom) || empty($prenom) || empty($type_prof) || $salaire_par_seance <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires et le salaire doit être positif']);
                    exit;
                }
                
                // Update professor
                $stmt = $conn->prepare("
                    UPDATE Professeur 
                    SET nom = ?, prenom = ?, type_prof = ?, salaire_par_seance = ? 
                    WHERE id_prof = ?
                ");
                $stmt->execute([$nom, $prenom, $type_prof, $salaire_par_seance, $id_prof]);
                
                echo json_encode(['success' => true, 'message' => 'Professeur modifié avec succès']);
                exit;
                
            case 'delete_professor':
                $id_prof = intval($_POST['id_prof']);
                
                // Check if professor has related records
                $tables_to_check = ['Seance_Reelle', 'Developpement_Prof'];
                foreach ($tables_to_check as $table) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE id_prof = ?");
                    $stmt->execute([$id_prof]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer ce professeur car il a des séances ou développements associés']);
                        exit;
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM Professeur WHERE id_prof = ?");
                $stmt->execute([$id_prof]);
                
                echo json_encode(['success' => true, 'message' => 'Professeur supprimé avec succès']);
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
                    <label for="add_id_user">Utilisateur associé:</label>
                    <select id="add_id_user" name="id_user" required>
                        <option value="">Sélectionner un utilisateur</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_type_prof">Type de professeur:</label>
                    <select id="add_type_prof" name="type_prof" required>
                        <option value="">Sélectionner le type</option>
                        <option value="permanent">Permanent</option>
                        <option value="vacataire">Vacataire</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_salaire_par_seance">Salaire par séance (DH):</label>
                    <input type="number" id="add_salaire_par_seance" name="salaire_par_seance" step="0.01" min="0" required>
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
                    <label for="edit_salaire_par_seance">Salaire par séance (DH):</label>
                    <input type="number" id="edit_salaire_par_seance" name="salaire_par_seance" step="0.01" min="0" required>
                </div>
                <button type="submit" class="btn btn-warning">Modifier</button>
                <button type="button" class="btn" onclick="closeEditProfessorModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let professors = [];
        let availableUsers = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableUsers();
            loadProfessors();
        });

        // Load all professors
        function loadProfessors() {
            fetch('', {
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
            fetch('', {
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
                    populateUserSelect();
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
            });
        }

        // Populate user select dropdown
        function populateUserSelect() {
            const userSelect = document.getElementById('add_id_user');
            userSelect.innerHTML = '<option value="">Sélectionner un utilisateur</option>';
            
            availableUsers.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id_user;
                option.textContent = `${user.nom} ${user.prenom} (${user.username})`;
                userSelect.appendChild(option);
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
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Username</th>
                            <th>Type</th>
                            <th>Salaire/Séance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            professors.forEach(prof => {
                const typeClass = prof.type_prof === 'permanent' ? 'type-permanent' : 'type-vacataire';
                html += `
                    <tr>
                        <td>${prof.id_prof}</td>
                        <td>${prof.nom}</td>
                        <td>${prof.prenom}</td>
                        <td>${prof.username}</td>
                        <td><span class="type-badge ${typeClass}">${prof.type_prof}</span></td>
                        <td class="salary">${parseFloat(prof.salaire_par_seance).toFixed(2)} DH</td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick="openEditProfessorModal(${prof.id_prof})">
                                Modifier
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

        // Open add professor modal
        function openAddProfessorModal() {
            document.getElementById('addProfessorModal').style.display = 'block';
            document.getElementById('addProfessorForm').reset();
            loadAvailableUsers(); // Refresh available users
        }

        // Close add professor modal
        function closeAddProfessorModal() {
            document.getElementById('addProfessorModal').style.display = 'none';
        }

        // Open edit professor modal
        function openEditProfessorModal(profId) {
            const prof = professors.find(p => p.id_prof == profId);
            if (!prof) return;

            document.getElementById('edit_id_prof').value = prof.id_prof;
            document.getElementById('edit_nom').value = prof.nom;
            document.getElementById('edit_prenom').value = prof.prenom;
            document.getElementById('edit_type_prof').value = prof.type_prof;
            document.getElementById('edit_salaire_par_seance').value = prof.salaire_par_seance;
            
            document.getElementById('editProfessorModal').style.display = 'block';
        }

        // Close edit professor modal
        function closeEditProfessorModal() {
            document.getElementById('editProfessorModal').style.display = 'none';
        }

        // Handle add professor form submission
        document.getElementById('addProfessorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_professor');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddProfessorModal();
                    loadProfessors();
                    loadAvailableUsers(); // Refresh available users
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout du professeur', 'error');
            });
        });

        // Handle edit professor form submission
        document.getElementById('editProfessorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_professor');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeEditProfessorModal();
                    loadProfessors();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de la modification du professeur', 'error');
            });
        });

        // Delete professor
        function deleteProfessor(profId, profName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le professeur "${profName}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_professor');
                formData.append('id_prof', profId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadProfessors();
                        loadAvailableUsers(); // Refresh available users
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression du professeur', 'error');
                });
            }
        }

        // Refresh professors
        function refreshProfessors() {
            loadProfessors();
            showAlert('Liste des professeurs actualisée', 'success');
        }

        // Show alert message
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addProfessorModal');
            const editModal = document.getElementById('editProfessorModal');
            
            if (event.target === addModal) {
                closeAddProfessorModal();
            }
            if (event.target === editModal) {
                closeEditProfessorModal();
            }
        }
    </script>
</body>
</html>