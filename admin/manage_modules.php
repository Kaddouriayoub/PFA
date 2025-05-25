<?php
// manage_modules.php
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
            case 'get_modules':
                $stmt = $conn->prepare("
                    SELECT m.id_module, m.nom_module, s.nom_semestre, a.annee_universitaire, f.nom_filiere 
                    FROM Module m
                    JOIN Semestre s ON m.id_semestre = s.id_semestre
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    ORDER BY f.nom_filiere, a.annee_universitaire, s.nom_semestre, m.nom_module
                ");
                $stmt->execute();
                $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'modules' => $modules]);
                exit;
                
            case 'get_elements':
                $module_id = intval($_POST['module_id']);
                $stmt = $conn->prepare("
                    SELECT id_element, nom_element, volume_horaire 
                    FROM Element_Module 
                    WHERE id_module = ?
                    ORDER BY nom_element
                ");
                $stmt->execute([$module_id]);
                $elements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'elements' => $elements]);
                exit;
                
            case 'get_semestres':
                $stmt = $conn->prepare("
                    SELECT s.id_semestre, s.nom_semestre, a.annee_universitaire, f.nom_filiere 
                    FROM Semestre s
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    ORDER BY f.nom_filiere, a.annee_universitaire, s.nom_semestre
                ");
                $stmt->execute();
                $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'semestres' => $semestres]);
                exit;
                
            case 'add_module':
                $nom_module = trim($_POST['nom_module']);
                $id_semestre = intval($_POST['id_semestre']);
                
                if (empty($nom_module) || $id_semestre <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Module (nom_module, id_semestre) VALUES (?, ?)");
                $stmt->execute([$nom_module, $id_semestre]);
                
                echo json_encode(['success' => true, 'message' => 'Module ajouté avec succès']);
                exit;
                
            case 'add_element':
                $nom_element = trim($_POST['nom_element']);
                $volume_horaire = intval($_POST['volume_horaire']);
                $id_module = intval($_POST['id_module']);
                
                if (empty($nom_element) || $volume_horaire <= 0 || $id_module <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Element_Module (nom_element, volume_horaire, id_module) VALUES (?, ?, ?)");
                $stmt->execute([$nom_element, $volume_horaire, $id_module]);
                
                echo json_encode(['success' => true, 'message' => 'Élément ajouté avec succès']);
                exit;
                
            case 'delete_module':
                $id_module = intval($_POST['id_module']);
                
                // Check if module has elements
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Element_Module WHERE id_module = ?");
                $stmt->execute([$id_module]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer ce module car il contient des éléments']);
                    exit;
                }
                
                $stmt = $conn->prepare("DELETE FROM Module WHERE id_module = ?");
                $stmt->execute([$id_module]);
                
                echo json_encode(['success' => true, 'message' => 'Module supprimé avec succès']);
                exit;
                
            case 'delete_element':
                $id_element = intval($_POST['id_element']);
                
                // Check if element has sessions
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Seance_Reelle WHERE id_element = ?");
                $stmt->execute([$id_element]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cet élément car il a des séances associées']);
                    exit;
                }
                
                $stmt = $conn->prepare("DELETE FROM Element_Module WHERE id_element = ?");
                $stmt->execute([$id_element]);
                
                echo json_encode(['success' => true, 'message' => 'Élément supprimé avec succès']);
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
    <title>Gestion des Modules - ENSIAS</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #7f8c8d;
        }
        
        .management-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary { 
            background: #3498db; 
            color: white; 
        }
        
        .btn-success { 
            background: #27ae60; 
            color: white; 
        }
        
        .btn-warning { 
            background: #f39c12; 
            color: white; 
        }
        
        .btn-danger { 
            background: #e74c3c; 
            color: white; 
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        tr:hover {
            background-color: #f5f7fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .module-details {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            margin: 10px 0;
            padding: 15px;
            border-radius: 0 5px 5px 0;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border 0.3s;
        }
        
        .form-group input:focus, 
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .alert {
            padding: 12px 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-size: 14px;
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
            padding: 30px;
            color: #7f8c8d;
        }
        
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .elements-table {
            margin-top: 15px;
            width: 100%;
        }
        
        .elements-table th {
            background-color: #e8f4fc;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Gestion des Modules et Éléments</h1>
        </div>
    </div>

    <div class="container">
        <a href="admin_dashboard.php" class="back-btn">← Retour au Dashboard</a>

        <div class="management-section">
            <h3 class="section-title">Liste des Modules</h3>
            <button class="btn btn-success" onclick="openAddModuleModal()">
                <i class="fas fa-plus"></i> Ajouter un module
            </button>
            <button class="btn btn-primary" onclick="refreshModules()">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
            
            <div id="alert-container"></div>
            
            <div id="modules-table-container">
                <div class="loading">Chargement des modules...</div>
            </div>
        </div>
    </div>

    <!-- Add Module Modal -->
    <div id="addModuleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModuleModal()">&times;</span>
            <h3>Ajouter un nouveau module</h3>
            <form id="addModuleForm">
                <div class="form-group">
                    <label for="add_nom_module">Nom du module:</label>
                    <input type="text" id="add_nom_module" name="nom_module" required>
                </div>
                <div class="form-group">
                    <label for="add_id_semestre">Semestre:</label>
                    <select id="add_id_semestre" name="id_semestre" required>
                        <option value="">Sélectionner un semestre</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddModuleModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Add Element Modal -->
    <div id="addElementModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddElementModal()">&times;</span>
            <h3>Ajouter un nouvel élément</h3>
            <form id="addElementForm">
                <input type="hidden" id="add_element_module_id" name="id_module">
                <div class="form-group">
                    <label for="add_nom_element">Nom de l'élément:</label>
                    <input type="text" id="add_nom_element" name="nom_element" required>
                </div>
                <div class="form-group">
                    <label for="add_volume_horaire">Volume horaire (heures):</label>
                    <input type="number" id="add_volume_horaire" name="volume_horaire" min="1" required>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddElementModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let modules = [];
        let semestres = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadSemestres();
            loadModules();
        });

        // Load all modules
        function loadModules() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_modules'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modules = data.modules;
                    displayModules();
                } else {
                    showAlert('Erreur lors du chargement des modules', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Load semestres for dropdown
        function loadSemestres() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_semestres'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    semestres = data.semestres;
                    populateSemestreSelect();
                }
            })
            .catch(error => {
                console.error('Error loading semestres:', error);
            });
        }

        // Populate semestre select dropdown
        function populateSemestreSelect() {
            const select = document.getElementById('add_id_semestre');
            select.innerHTML = '<option value="">Sélectionner un semestre</option>';
            
            semestres.forEach(semestre => {
                const option = document.createElement('option');
                option.value = semestre.id_semestre;
                option.textContent = `${semestre.nom_filiere} - ${semestre.annee_universitaire} - ${semestre.nom_semestre}`;
                select.appendChild(option);
            });
        }

        // Display modules in table
        function displayModules() {
            const container = document.getElementById('modules-table-container');
            
            if (modules.length === 0) {
                container.innerHTML = '<p>Aucun module trouvé.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Filière</th>
                            <th>Année</th>
                            <th>Semestre</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            modules.forEach(module => {
                html += `
                    <tr>
                        <td>${module.id_module}</td>
                        <td><strong>${module.nom_module}</strong></td>
                        <td>${module.nom_filiere}</td>
                        <td>${module.annee_universitaire}</td>
                        <td>${module.nom_semestre}</td>
                        <td>
                            <button class="btn btn-primary" onclick="showModuleElements(${module.id_module}, '${module.nom_module}')">
                                Voir éléments
                            </button>
                            <button class="btn btn-danger" onclick="deleteModule(${module.id_module}, '${module.nom_module}')">
                                Supprimer
                            </button>
                        </td>
                    </tr>
                    <tr id="module-elements-${module.id_module}" class="module-details" style="display:none;">
                        <td colspan="6">
                            <div id="elements-container-${module.id_module}">
                                Chargement des éléments...
                            </div>
                            <button class="btn btn-success" onclick="openAddElementModal(${module.id_module})">
                                Ajouter un élément
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

        // Show module elements
        function showModuleElements(moduleId, moduleName) {
            const row = document.getElementById(`module-elements-${moduleId}`);
            const container = document.getElementById(`elements-container-${moduleId}`);
            
            // Toggle display
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                
                // Load elements if not already loaded
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_elements&module_id=${moduleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let elementsHtml = `<h4>Éléments du module "${moduleName}"</h4>`;
                        
                        if (data.elements.length === 0) {
                            elementsHtml += '<p>Aucun élément trouvé pour ce module.</p>';
                        } else {
                            elementsHtml += `
                                <table class="elements-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Volume Horaire</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            
                            data.elements.forEach(element => {
                                elementsHtml += `
                                    <tr>
                                        <td>${element.id_element}</td>
                                        <td>${element.nom_element}</td>
                                        <td>${element.volume_horaire} heures</td>
                                        <td>
                                            <button class="btn btn-danger" onclick="deleteElement(${element.id_element}, '${element.nom_element}')">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            
                            elementsHtml += `
                                    </tbody>
                                </table>
                            `;
                        }
                        
                        container.innerHTML = elementsHtml;
                    } else {
                        container.innerHTML = '<p class="alert-error">Erreur lors du chargement des éléments</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="alert-error">Erreur de connexion</p>';
                });
            } else {
                row.style.display = 'none';
            }
        }

        // Open add module modal
        function openAddModuleModal() {
            document.getElementById('addModuleModal').style.display = 'block';
            document.getElementById('addModuleForm').reset();
        }

        // Close add module modal
        function closeAddModuleModal() {
            document.getElementById('addModuleModal').style.display = 'none';
        }

        // Open add element modal
        function openAddElementModal(moduleId) {
            document.getElementById('add_element_module_id').value = moduleId;
            document.getElementById('addElementModal').style.display = 'block';
            document.getElementById('addElementForm').reset();
        }

        // Close add element modal
        function closeAddElementModal() {
            document.getElementById('addElementModal').style.display = 'none';
        }

        // Handle add module form submission
        document.getElementById('addModuleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_module');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddModuleModal();
                    loadModules();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout du module', 'error');
            });
        });

        // Handle add element form submission
        document.getElementById('addElementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_element');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddElementModal();
                    // Refresh the elements list for this module
                    const moduleId = document.getElementById('add_element_module_id').value;
                    const container = document.getElementById(`elements-container-${moduleId}`);
                    container.innerHTML = 'Chargement des éléments...';
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_elements&module_id=${moduleId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let elementsHtml = '';
                            
                            if (data.elements.length === 0) {
                                elementsHtml = '<p>Aucun élément trouvé pour ce module.</p>';
                            } else {
                                elementsHtml = `
                                    <table class="elements-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nom</th>
                                                <th>Volume Horaire</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;
                                
                                data.elements.forEach(element => {
                                    elementsHtml += `
                                        <tr>
                                            <td>${element.id_element}</td>
                                            <td>${element.nom_element}</td>
                                            <td>${element.volume_horaire} heures</td>
                                            <td>
                                                <button class="btn btn-danger" onclick="deleteElement(${element.id_element}, '${element.nom_element}')">
                                                    Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                                
                                elementsHtml += `
                                        </tbody>
                                    </table>
                                `;
                            }
                            
                            container.innerHTML = elementsHtml;
                        }
                    });
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout de l\'élément', 'error');
            });
        });

        // Delete module
        function deleteModule(moduleId, moduleName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le module "${moduleName}" ? Cette action est irréversible.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_module');
                formData.append('id_module', moduleId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadModules();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression du module', 'error');
                });
            }
        }

        // Delete element
        function deleteElement(elementId, elementName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'élément "${elementName}" ? Cette action est irréversible.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_element');
                formData.append('id_element', elementId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        // Find which module this element belongs to and refresh its elements
                        const moduleRow = document.querySelector(`tr[id^="module-elements-"]`);
                        if (moduleRow) {
                            const moduleId = moduleRow.id.split('-')[2];
                            showModuleElements(moduleId, '');
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression de l\'élément', 'error');
                });
            }
        }

        // Refresh modules
        function refreshModules() {
            loadModules();
            showAlert('Liste des modules actualisée', 'success');
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
            const addModal = document.getElementById('addModuleModal');
            const addElementModal = document.getElementById('addElementModal');
            
            if (event.target === addModal) {
                closeAddModuleModal();
            }
            if (event.target === addElementModal) {
                closeAddElementModal();
            }
        }
    </script>
</body>
</html>