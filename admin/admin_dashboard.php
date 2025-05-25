<?php
// admin_dashboard.php
session_start();

// Database connection
$servername = "localhost";
$username = "root"; // Adjust according to your database config
$password = "";     // Adjust according to your database config
$dbname = "ensias_payment"; // Replace with your actual database name

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Vérification simple
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
            case 'get_users':
                $stmt = $conn->prepare("
                    SELECT u.id_user, u.nom, u.prenom, u.username, r.nom_role 
                    FROM Utilisateur u 
                    JOIN Role r ON u.id_role = r.id_role 
                    ORDER BY u.id_user
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'users' => $users]);
                exit;
                
            case 'add_user':
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $username = trim($_POST['username']);
                $password = trim($_POST['password']);
                $role_id = intval($_POST['role_id']);
                
                // Check if username already exists
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Utilisateur WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Ce nom d\'utilisateur existe déjà']);
                    exit;
                }
                
                // Insert new user
                $stmt = $conn->prepare("
                    INSERT INTO Utilisateur (nom, prenom, username, mot_de_passe, id_role) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $prenom, $username, $password, $role_id]);
                
                echo json_encode(['success' => true, 'message' => 'Utilisateur ajouté avec succès']);
                exit;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                
                // Prevent deleting the current admin user
                if ($user_id == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte']);
                    exit;
                }
                
                // Check if user has related records
                $tables_to_check = ['Administration', 'Professeur', 'Financier'];
                foreach ($tables_to_check as $table) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE id_user = ?");
                    $stmt->execute([$user_id]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cet utilisateur car il est référencé dans d\'autres tables']);
                        exit;
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM Utilisateur WHERE id_user = ?");
                $stmt->execute([$user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
                exit;
                
            case 'get_roles':
                $stmt = $conn->prepare("SELECT id_role, nom_role FROM Role ORDER BY nom_role");
                $stmt->execute();
                $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'roles' => $roles]);
                exit;

            // Statistics for dashboard
            case 'get_statistics':
                $stats = [];
                
                // Total users
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Utilisateur");
                $stmt->execute();
                $stats['total_users'] = $stmt->fetchColumn();
                
                // Total professors
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Professeur");
                $stmt->execute();
                $stats['total_professors'] = $stmt->fetchColumn();
                
                // Total permanent professors
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Professeur WHERE type_prof = 'permanent'");
                $stmt->execute();
                $stats['permanent_professors'] = $stmt->fetchColumn();
                
                // Total vacataire professors
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Professeur WHERE type_prof = 'vacataire'");
                $stmt->execute();
                $stats['vacataire_professors'] = $stmt->fetchColumn();
                
                // Total modules
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Module");
                $stmt->execute();
                $stats['total_modules'] = $stmt->fetchColumn();
                
                // Total sessions
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Seance_Reelle");
                $stmt->execute();
                $stats['total_sessions'] = $stmt->fetchColumn();
                
                echo json_encode(['success' => true, 'stats' => $stats]);
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
    <title>Dashboard Administrateur - ENSIAS</title>
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
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header .user-info {
            float: right;
            margin-top: -30px;
        }
        .header .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        .header .logout-btn:hover {
            background: #c0392b;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 16px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .stat-card.users .number { color: #3498db; }
        .stat-card.professors .number { color: #27ae60; }
        .stat-card.permanent .number { color: #f39c12; }
        .stat-card.vacataire .number { color: #e74c3c; }
        
        .management-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .management-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .btn-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .management-btn {
            display: block;
            padding: 15px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            transition: background 0.3s;
        }
        .management-btn:hover {
            background: #2980b9;
        }
        .management-btn.professors {
            background: #27ae60;
        }
        .management-btn.professors:hover {
            background: #229954;
        }
        .management-btn.finance {
            background: #f39c12;
        }
        .management-btn.finance:hover {
            background: #e67e22;
        }
        .management-btn.modules {
            background: #9b59b6;
        }
        .management-btn.modules:hover {
            background: #8e44ad;
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
            margin: 10% auto;
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
        
        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #7f8c8d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Dashboard Administrateur ENSIAS</h1>
            <div class="user-info">
                Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>
                <a href="../logout.php" class="logout-btn">Déconnexion</a>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="dashboard-grid" id="stats-grid">
            <div class="stat-card users">
                <h3>Total Utilisateurs</h3>
                <div class="number" id="total-users">--</div>
            </div>
            <div class="stat-card professors">
                <h3>Total Professeurs</h3>
                <div class="number" id="total-professors">--</div>
            </div>
            <div class="stat-card permanent">
                <h3>Professeurs Permanents</h3>
                <div class="number" id="permanent-professors">--</div>
            </div>
            <div class="stat-card vacataire">
                <h3>Professeurs Vacataires</h3>
                <div class="number" id="vacataire-professors">--</div>
            </div>
        </div>

        <!-- Management Section -->
        <div class="management-section">
            <h3>Gestion du Système</h3>
            <div class="btn-grid">
                <a href="manage_professors.php" class="management-btn professors">
                    <strong>Gestion des Professeurs</strong><br>
                    <small>Ajouter, modifier, supprimer des professeurs</small>
                </a>
                <a href="#" class="management-btn" onclick="showUserManagement()">
                    <strong>Gestion des Utilisateurs</strong><br>
                    <small>Gérer les comptes utilisateurs</small>
                </a>
                <a href="manage_modules.php" class="management-btn modules">
                    <strong>Gestion des Modules</strong><br>
                    <small>Gérer les modules et éléments</small>
                </a>
                <a href="manage_filieres.php" class="management-btn finance">
                    <strong>Gestion des Filières</strong><br>
                    <small>Gérer les filières et années</small>
                </a>
                <a href="manage_seances.php" class="management-btn">
                    <strong>Gestion des Séances</strong><br>
                    <small>Enregistrer les séances enseignées</small>
                </a>
            </div>
        </div>

        <!-- User Management Section -->
        <div class="management-section" id="user-management" style="display: none;">
            <h3>Gestion des Utilisateurs</h3>
            <button class="btn btn-success" onclick="openAddUserModal()">Ajouter un utilisateur</button>
            <button class="btn btn-primary" onclick="refreshUsers()">Actualiser</button>
            <button class="btn" onclick="hideUserManagement()">Fermer</button>
            
            <div id="alert-container"></div>
            
            <div id="users-table-container">
                <div class="loading">Chargement des utilisateurs...</div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3>Activité Récente</h3>
            <div class="activity-item">
                <div>Nouveau professeur ajouté: Ahmed ZEROUAL</div>
                <div class="activity-time">Il y a 2 heures</div>
            </div>
            <div class="activity-item">
                <div>Module "Base de Données" modifié</div>
                <div class="activity-time">Il y a 1 jour</div>
            </div>
            <div class="activity-item">
                <div>Utilisateur finance1 connecté</div>
                <div class="activity-time">Il y a 2 jours</div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddUserModal()">&times;</span>
            <h3>Ajouter un nouvel utilisateur</h3>
            <form id="addUserForm">
                <div class="form-group">
                    <label for="add_nom">Nom:</label>
                    <input type="text" id="add_nom" name="nom" required>
                </div>
                <div class="form-group">
                    <label for="add_prenom">Prénom:</label>
                    <input type="text" id="add_prenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label for="add_username">Nom d'utilisateur:</label>
                    <input type="text" id="add_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="add_password">Mot de passe:</label>
                    <input type="password" id="add_password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="add_role_id">Rôle:</label>
                    <select id="add_role_id" name="role_id" required>
                        <option value="">Sélectionner un rôle</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddUserModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let users = [];
        let roles = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadRoles();
        });

        // Load statistics
        function loadStatistics() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_statistics'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('total-users').textContent = data.stats.total_users;
                    document.getElementById('total-professors').textContent = data.stats.total_professors;
                    document.getElementById('permanent-professors').textContent = data.stats.permanent_professors;
                    document.getElementById('vacataire-professors').textContent = data.stats.vacataire_professors;
                }
            })
            .catch(error => {
                console.error('Error loading statistics:', error);
            });
        }

        // Show user management section
        function showUserManagement() {
            document.getElementById('user-management').style.display = 'block';
            loadUsers();
        }

        // Hide user management section
        function hideUserManagement() {
            document.getElementById('user-management').style.display = 'none';
        }

        // Load all users
        function loadUsers() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    users = data.users;
                    displayUsers();
                } else {
                    showAlert('Erreur lors du chargement des utilisateurs', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Load roles
        function loadRoles() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_roles'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    roles = data.roles;
                    populateRoleSelect();
                }
            })
            .catch(error => {
                console.error('Error loading roles:', error);
            });
        }

        // Populate role select dropdown
        function populateRoleSelect() {
            const roleSelect = document.getElementById('add_role_id');
            roleSelect.innerHTML = '<option value="">Sélectionner un rôle</option>';
            
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id_role;
                option.textContent = role.nom_role;
                roleSelect.appendChild(option);
            });
        }

        // Display users in table
        function displayUsers() {
            const container = document.getElementById('users-table-container');
            
            if (users.length === 0) {
                container.innerHTML = '<p>Aucun utilisateur trouvé.</p>';
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
                            <th>Rôle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            users.forEach(user => {
                html += `
                    <tr>
                        <td>${user.id_user}</td>
                        <td>${user.nom}</td>
                        <td>${user.prenom}</td>
                        <td>${user.username}</td>
                        <td><span class="badge">${user.nom_role}</span></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id_user}, '${user.username}')">
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

        // Open add user modal
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
            document.getElementById('addUserForm').reset();
        }

        // Close add user modal
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        // Handle add user form submission
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_user');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddUserModal();
                    loadUsers();
                    loadStatistics(); // Refresh statistics
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout de l\'utilisateur', 'error');
            });
        });

        // Delete user
        function deleteUser(userId, username) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${username}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadUsers();
                        loadStatistics(); // Refresh statistics
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression de l\'utilisateur', 'error');
                });
            }
        }

        // Refresh users
        function refreshUsers() {
            loadUsers();
            loadStatistics();
            showAlert('Liste des utilisateurs actualisée', 'success');
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
            const addModal = document.getElementById('addUserModal');
            
            if (event.target === addModal) {
                closeAddUserModal();
            }
        }

        // Auto refresh statistics every 30 seconds
        setInterval(loadStatistics, 30000);
    </script>
</body>
</html>