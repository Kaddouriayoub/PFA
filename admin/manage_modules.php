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
                    SELECT m.id_module, m.nom_module, s.id_semestre, s.nom_semestre, a.id_annee, a.niveau, f.id_filiere, f.nom_filiere 
                    FROM Module m
                    JOIN Semestre s ON m.id_semestre = s.id_semestre
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    ORDER BY f.nom_filiere, a.niveau, s.nom_semestre, m.nom_module
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
                    SELECT s.id_semestre, s.nom_semestre, a.niveau, f.id_filiere, f.nom_filiere 
                    FROM Semestre s
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    ORDER BY f.nom_filiere, a.niveau, s.nom_semestre
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
                
                // Verify the semester exists
                $stmt = $conn->prepare("
                    SELECT s.id_semestre 
                    FROM Semestre s
                    JOIN Annee a ON s.id_annee = a.id_annee
                    WHERE s.id_semestre = ?
                ");
                $stmt->execute([$id_semestre]);
                
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Semestre invalide']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Module (nom_module, id_semestre) VALUES (?, ?)");
                $stmt->execute([$nom_module, $id_semestre]);
                
                echo json_encode(['success' => true, 'message' => 'Module ajouté avec succès']);
                exit;
                
            case 'update_module':
                $id_module = intval($_POST['id_module']);
                $nom_module = trim($_POST['nom_module']);
                $id_semestre = intval($_POST['id_semestre']);
                
                if (empty($nom_module) || $id_semestre <= 0 || $id_module <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                // Vérifier que le semestre existe
                $stmt = $conn->prepare("SELECT id_semestre FROM Semestre WHERE id_semestre = ?");
                $stmt->execute([$id_semestre]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Semestre invalide']);
                    exit;
                }
                
                // Mettre à jour le module
                $stmt = $conn->prepare("UPDATE Module SET nom_module = ?, id_semestre = ? WHERE id_module = ?");
                $stmt->execute([$nom_module, $id_semestre, $id_module]);
                
                echo json_encode(['success' => true, 'message' => 'Module modifié avec succès']);
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
                
            case 'update_element':
                $id_element = intval($_POST['id_element']);
                $nom_element = trim($_POST['nom_element']);
                $volume_horaire = intval($_POST['volume_horaire']);
                
                if (empty($nom_element) || $volume_horaire <= 0 || $id_element <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                $stmt = $conn->prepare("UPDATE Element_Module SET nom_element = ?, volume_horaire = ? WHERE id_element = ?");
                $stmt->execute([$nom_element, $volume_horaire, $id_element]);
                
                echo json_encode(['success' => true, 'message' => 'Élément modifié avec succès']);
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
            
            case 'get_all_filieres':
                $stmt = $conn->prepare("SELECT id_filiere, nom_filiere FROM Filiere ORDER BY nom_filiere");
                $stmt->execute();
                $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'filieres' => $filieres]);
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
    <link rel="stylesheet" href="manage_modules.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <label for="add_niveau">Niveau:</label>
                    <select id="add_niveau" name="niveau" required onchange="loadFilieresByNiveau('add')">
                        <option value="">Sélectionner un niveau</option>
                        <option value="1A">1ère année (1A)</option>
                        <option value="2A">2ème année (2A)</option>
                        <option value="3A">3ème année (3A)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_id_filiere">Filière:</label>
                    <select id="add_id_filiere" name="id_filiere" required onchange="loadSemestresByFiliereAndNiveau('add')" disabled>
                        <option value="">Sélectionner une filière</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_id_semestre">Semestre:</label>
                    <select id="add_id_semestre" name="id_semestre" required disabled>
                        <option value="">Sélectionner un semestre</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddModuleModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Edit Module Modal -->
    <div id="editModuleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModuleModal()">&times;</span>
            <h3>Modifier le module</h3>
            <form id="editModuleForm">
                <input type="hidden" id="edit_id_module" name="id_module">
                <div class="form-group">
                    <label for="edit_nom_module">Nom du module:</label>
                    <input type="text" id="edit_nom_module" name="nom_module" required>
                </div>
                <div class="form-group">
                    <label for="edit_niveau">Niveau:</label>
                    <select id="edit_niveau" name="niveau" required onchange="loadFilieresByNiveau('edit')">
                        <option value="">Sélectionner un niveau</option>
                        <option value="1A">1ère année (1A)</option>
                        <option value="2A">2ème année (2A)</option>
                        <option value="3A">3ème année (3A)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_id_filiere">Filière:</label>
                    <select id="edit_id_filiere" name="id_filiere" required onchange="loadSemestresByFiliereAndNiveau('edit')">
                        <option value="">Sélectionner une filière</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_id_semestre">Semestre:</label>
                    <select id="edit_id_semestre" name="id_semestre" required>
                        <option value="">Sélectionner un semestre</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <button type="button" class="btn" onclick="closeEditModuleModal()">Annuler</button>
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

    <!-- Edit Element Modal -->
    <div id="editElementModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditElementModal()">&times;</span>
            <h3>Modifier l'élément</h3>
            <form id="editElementForm">
                <input type="hidden" id="edit_id_element" name="id_element">
                <div class="form-group">
                    <label for="edit_nom_element">Nom de l'élément:</label>
                    <input type="text" id="edit_nom_element" name="nom_element" required>
                </div>
                <div class="form-group">
                    <label for="edit_volume_horaire">Volume horaire (heures):</label>
                    <input type="number" id="edit_volume_horaire" name="volume_horaire" min="1" required>
                </div>
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <button type="button" class="btn" onclick="closeEditElementModal()">Annuler</button>
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
                    // Store the complete semestre objects with all properties
                    semestres = data.semestres.map(semestre => ({
                        id_semestre: semestre.id_semestre,
                        nom_semestre: semestre.nom_semestre,
                        niveau: semestre.niveau,
                        id_filiere: semestre.id_filiere,
                        nom_filiere: semestre.nom_filiere
                    }));
                }
            })
            .catch(error => {
                console.error('Error loading semestres:', error);
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
                            <th>Nom</th>
                            <th>Filière</th>
                            <th>Niveau</th>
                            <th>Semestre</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            modules.forEach(module => {
                html += `
                    <tr>
                        <td><strong>${module.nom_module}</strong></td>
                        <td>${module.nom_filiere}</td>
                        <td><span class="niveau-badge">${module.niveau}</span></td>
                        <td>${module.nom_semestre}</td>
                        <td>
                            <button class="btn btn-primary" onclick="showModuleElements(${module.id_module}, '${module.nom_module}')">
                                <i class="fas fa-list"></i> Éléments
                            </button>
                            <button class="btn btn-warning" onclick="openEditModuleModal(${module.id_module}, '${module.nom_module}', '${module.niveau}', '${module.id_annee}', '${module.id_filiere}', '${module.nom_filiere}', '${module.id_semestre}')">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-danger" onclick="deleteModule(${module.id_module}, '${module.nom_module}')">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </td>
                    </tr>
                    <tr id="module-elements-${module.id_module}" class="module-details" style="display:none;">
                        <td colspan="6">
                            <div id="elements-container-${module.id_module}">
                                Chargement des éléments...
                            </div>
                            <button class="btn btn-success" onclick="openAddElementModal(${module.id_module})">
                                <i class="fas fa-plus"></i> Ajouter un élément
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
                                        <td>${element.nom_element}</td>
                                        <td>${element.volume_horaire} heures</td>
                                        <td>
                                            <button class="btn btn-warning" onclick="openEditElementModal(${element.id_element}, '${element.nom_element}', ${element.volume_horaire})">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <button class="btn btn-danger" onclick="deleteElement(${element.id_element}, '${element.nom_element}')">
                                                <i class="fas fa-trash"></i> Supprimer
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
            document.getElementById('add_niveau').value = '';
            document.getElementById('add_id_filiere').innerHTML = '<option value="">Sélectionner une filière</option>';
            document.getElementById('add_id_filiere').disabled = true;
            document.getElementById('add_id_semestre').innerHTML = '<option value="">Sélectionner un semestre</option>';
            document.getElementById('add_id_semestre').disabled = true;
        }

        // Close add module modal
        function closeAddModuleModal() {
            document.getElementById('addModuleModal').style.display = 'none';
        }

        // Open edit module modal
        function openEditModuleModal(moduleId, moduleName, niveau, id_annee, filiereId, filiereName, semestreId) {
            document.getElementById('editModuleModal').style.display = 'block';
            document.getElementById('edit_id_module').value = moduleId;
            document.getElementById('edit_nom_module').value = moduleName;
            
            // Activer la modification du niveau
            const niveauSelect = document.getElementById('edit_niveau');
            niveauSelect.value = niveau;
            niveauSelect.disabled = false; // On active la modification du niveau
            
            // Charger les filières pour ce niveau
            loadFilieresByNiveau('edit');
            
            // Définir la filière
            const filiereSelect = document.getElementById('edit_id_filiere');
            filiereSelect.value = filiereId;
            filiereSelect.disabled = false; // On active la modification de la filière
            
            // Charger les semestres pour cette filière et niveau
            loadSemestresByFiliereAndNiveau('edit');
            
            // Définir le semestre
            document.getElementById('edit_id_semestre').value = semestreId;
        }

        // Close edit module modal
        function closeEditModuleModal() {
            document.getElementById('editModuleModal').style.display = 'none';
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

        // Open edit element modal
        function openEditElementModal(elementId, elementName, volumeHoraire) {
            document.getElementById('editElementModal').style.display = 'block';
            document.getElementById('edit_id_element').value = elementId;
            document.getElementById('edit_nom_element').value = elementName;
            document.getElementById('edit_volume_horaire').value = volumeHoraire;
        }

        // Close edit element modal
        function closeEditElementModal() {
            document.getElementById('editElementModal').style.display = 'none';
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

        // Handle edit module form submission
        document.getElementById('editModuleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_module');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeEditModuleModal();
                    loadModules();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de la modification du module', 'error');
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
                                            <td>${element.nom_element}</td>
                                            <td>${element.volume_horaire} heures</td>
                                            <td>
                                                <button class="btn btn-warning" onclick="openEditElementModal(${element.id_element}, '${element.nom_element}', ${element.volume_horaire})">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                                <button class="btn btn-danger" onclick="deleteElement(${element.id_element}, '${element.nom_element}')">
                                                    <i class="fas fa-trash"></i> Supprimer
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

        // Handle edit element form submission
        document.getElementById('editElementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_element');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeEditElementModal();
                    // Refresh the elements list for this module
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
                showAlert('Erreur lors de la modification de l\'élément', 'error');
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

        // Load filieres based on selected niveau
        function loadFilieresByNiveau(mode = 'add') {
            const prefix = mode === 'edit' ? 'edit_' : 'add_';
            const niveau = document.getElementById(`${prefix}niveau`).value;
            const filiereSelect = document.getElementById(`${prefix}id_filiere`);
            
            if (!niveau) {
                filiereSelect.disabled = true;
                filiereSelect.innerHTML = '<option value="">Sélectionner une filière</option>';
                document.getElementById(`${prefix}id_semestre`).disabled = true;
                document.getElementById(`${prefix}id_semestre`).innerHTML = '<option value="">Sélectionner un semestre</option>';
                return;
            }
            
            // Charger TOUTES les filières depuis la base de données
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_filieres'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    filiereSelect.disabled = false;
                    filiereSelect.innerHTML = '<option value="">Sélectionner une filière</option>';
                    
                    data.filieres.forEach(filiere => {
                        const option = document.createElement('option');
                        option.value = filiere.id_filiere;
                        option.textContent = filiere.nom_filiere;
                        filiereSelect.appendChild(option);
                    });
                    
                    // Réinitialiser le semestre
                    document.getElementById(`${prefix}id_semestre`).disabled = true;
                    document.getElementById(`${prefix}id_semestre`).innerHTML = '<option value="">Sélectionner un semestre</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Load semestres based on selected filiere and niveau
        function loadSemestresByFiliereAndNiveau(mode = 'add') {
            const prefix = mode === 'edit' ? 'edit_' : 'add_';
            const niveau = document.getElementById(`${prefix}niveau`).value;
            const filiereId = document.getElementById(`${prefix}id_filiere`).value;
            const semestreSelect = document.getElementById(`${prefix}id_semestre`);
            
            if (!filiereId) {
                semestreSelect.disabled = true;
                semestreSelect.innerHTML = '<option value="">Sélectionner un semestre</option>';
                return;
            }
            
            // Filter semestres for this niveau and filiere
            const filteredSemestres = semestres.filter(semestre => 
                semestre.niveau === niveau && semestre.id_filiere == filiereId
            );
            
            if (filteredSemestres.length === 0) {
                semestreSelect.disabled = true;
                semestreSelect.innerHTML = '<option value="">Aucun semestre disponible</option>';
                return;
            }
            
            semestreSelect.disabled = false;
            semestreSelect.innerHTML = '<option value="">Sélectionner un semestre</option>';
            
            filteredSemestres.forEach(semestre => {
                const option = document.createElement('option');
                option.value = semestre.id_semestre;
                option.textContent = semestre.nom_semestre;
                semestreSelect.appendChild(option);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModuleModal');
            const editModal = document.getElementById('editModuleModal');
            const addElementModal = document.getElementById('addElementModal');
            const editElementModal = document.getElementById('editElementModal');
            
            if (event.target === addModal) {
                closeAddModuleModal();
            }
            if (event.target === editModal) {
                closeEditModuleModal();
            }
            if (event.target === addElementModal) {
                closeAddElementModal();
            }
            if (event.target === editElementModal) {
                closeEditElementModal();
            }
        }
    </script>
</body>
</html>