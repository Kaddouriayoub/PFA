<?php
// manage_seances.php
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
            case 'get_seances':
                $stmt = $conn->prepare("
                    SELECT sr.id_seance, sr.date_seance, sr.type_seance, 
                           p.nom AS prof_nom, p.prenom AS prof_prenom,
                           em.nom_element, m.nom_module,
                           s.nom_semestre, a.annee_universitaire, f.nom_filiere
                    FROM Seance_Reelle sr
                    JOIN Professeur p ON sr.id_prof = p.id_prof
                    JOIN Element_Module em ON sr.id_element = em.id_element
                    JOIN Module m ON em.id_module = m.id_module
                    JOIN Semestre s ON m.id_semestre = s.id_semestre
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    ORDER BY sr.date_seance DESC
                    LIMIT 100
                ");
                $stmt->execute();
                $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'seances' => $seances]);
                exit;
                
            case 'get_professeurs':
                $stmt = $conn->prepare("
                    SELECT p.id_prof, p.nom, p.prenom 
                    FROM Professeur p 
                    ORDER BY p.nom, p.prenom
                ");
                $stmt->execute();
                $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'professeurs' => $professeurs]);
                exit;
                
            case 'get_elements':
                $stmt = $conn->prepare("
                    SELECT em.id_element, em.nom_element, m.nom_module,
                           s.nom_semestre, a.annee_universitaire, f.nom_filiere
                    FROM Element_Module em
                    JOIN Module m ON em.id_module = m.id_module
                    JOIN Semestre s ON m.id_semestre = s.id_semestre
                    JOIN Annee a ON s.id_annee = a.id_annee
                    JOIN Filiere f ON a.id_filiere = f.id_filiere
                    ORDER BY f.nom_filiere, a.annee_universitaire, s.nom_semestre, m.nom_module, em.nom_element
                ");
                $stmt->execute();
                $elements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'elements' => $elements]);
                exit;
                
            case 'add_seance':
                $date_seance = $_POST['date_seance'];
                $type_seance = $_POST['type_seance'];
                $id_prof = intval($_POST['id_prof']);
                $id_element = intval($_POST['id_element']);
                
                if (empty($date_seance) || empty($type_seance) || $id_prof <= 0 || $id_element <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO Seance_Reelle (date_seance, type_seance, id_prof, id_element) VALUES (?, ?, ?, ?)");
                $stmt->execute([$date_seance, $type_seance, $id_prof, $id_element]);
                
                echo json_encode(['success' => true, 'message' => 'Séance ajoutée avec succès']);
                exit;
                
            case 'delete_seance':
                $id_seance = intval($_POST['id_seance']);
                
                // Check if seance has related records
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Seance_Effective WHERE seance_initiale_id = ?");
                $stmt->execute([$id_seance]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cette séance car elle a des séances effectives associées']);
                    exit;
                }
                
                $stmt = $conn->prepare("DELETE FROM Seance_Reelle WHERE id_seance = ?");
                $stmt->execute([$id_seance]);
                
                echo json_encode(['success' => true, 'message' => 'Séance supprimée avec succès']);
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
    <title>Gestion des Séances - ENSIAS</title>
    <style>
        /* Copiez le même style que manage_professors.php */
        /* ... */
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-cours {
            background-color: #3498db;
            color: white;
        }
        
        .badge-td {
            background-color: #27ae60;
            color: white;
        }
        
        .badge-tp {
            background-color: #f39c12;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Gestion des Séances Enseignées</h1>
        </div>
    </div>

    <div class="container">
        <a href="admin_dashboard.php" class="back-btn">← Retour au Dashboard</a>

        <div class="professor-management">
            <h3>Enregistrement des Séances</h3>
            <button class="btn btn-success" onclick="openAddSeanceModal()">Ajouter une séance</button>
            <button class="btn btn-primary" onclick="refreshSeances()">Actualiser</button>
            
            <div id="alert-container"></div>
            
            <div id="seances-table-container">
                <div class="loading">Chargement des séances...</div>
            </div>
        </div>
    </div>

    <!-- Add Seance Modal -->
    <div id="addSeanceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddSeanceModal()">&times;</span>
            <h3>Enregistrer une nouvelle séance</h3>
            <form id="addSeanceForm">
                <div class="form-group">
                    <label for="add_date_seance">Date de la séance:</label>
                    <input type="date" id="add_date_seance" name="date_seance" required>
                </div>
                <div class="form-group">
                    <label for="add_type_seance">Type de séance:</label>
                    <select id="add_type_seance" name="type_seance" required>
                        <option value="">Sélectionner le type</option>
                        <option value="Cours">Cours</option>
                        <option value="TD">TD</option>
                        <option value="TP">TP</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_id_prof">Professeur:</label>
                    <select id="add_id_prof" name="id_prof" required>
                        <option value="">Sélectionner un professeur</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_id_element">Élément de module:</label>
                    <select id="add_id_element" name="id_element" required>
                        <option value="">Sélectionner un élément</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <button type="button" class="btn" onclick="closeAddSeanceModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        let seances = [];
        let professeurs = [];
        let elements = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadProfesseurs();
            loadElements();
            loadSeances();
        });

        function loadSeances() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_seances'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    seances = data.seances;
                    displaySeances();
                } else {
                    showAlert('Erreur lors du chargement des séances', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        function loadProfesseurs() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_professeurs'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    professeurs = data.professeurs;
                    populateProfSelect();
                }
            })
            .catch(error => {
                console.error('Error loading professeurs:', error);
            });
        }

        function loadElements() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_elements'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    elements = data.elements;
                    populateElementSelect();
                }
            })
            .catch(error => {
                console.error('Error loading elements:', error);
            });
        }

        function populateProfSelect() {
            const select = document.getElementById('add_id_prof');
            select.innerHTML = '<option value="">Sélectionner un professeur</option>';
            
            professeurs.forEach(prof => {
                const option = document.createElement('option');
                option.value = prof.id_prof;
                option.textContent = `${prof.nom} ${prof.prenom}`;
                select.appendChild(option);
            });
        }

        function populateElementSelect() {
            const select = document.getElementById('add_id_element');
            select.innerHTML = '<option value="">Sélectionner un élément</option>';
            
            elements.forEach(element => {
                const option = document.createElement('option');
                option.value = element.id_element;
                option.textContent = `${element.nom_filiere} - ${element.annee_universitaire} - ${element.nom_semestre} - ${element.nom_module} - ${element.nom_element}`;
                select.appendChild(option);
            });
        }

        function displaySeances() {
            const container = document.getElementById('seances-table-container');
            
            if (seances.length === 0) {
                container.innerHTML = '<p>Aucune séance trouvée.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Professeur</th>
                            <th>Élément</th>
                            <th>Module</th>
                            <th>Filière/Année</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            seances.forEach(seance => {
                const typeClass = seance.type_seance === 'Cours' ? 'badge-cours' : 
                                 seance.type_seance === 'TD' ? 'badge-td' : 'badge-tp';
                
                html += `
                    <tr>
                        <td>${new Date(seance.date_seance).toLocaleDateString()}</td>
                        <td><span class="badge ${typeClass}">${seance.type_seance}</span></td>
                        <td>${seance.prof_nom} ${seance.prof_prenom}</td>
                        <td>${seance.nom_element}</td>
                        <td>${seance.nom_module}</td>
                        <td>${seance.nom_filiere} - ${seance.annee_universitaire}</td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteSeance(${seance.id_seance}, '${seance.nom_element}')">
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

        function openAddSeanceModal() {
            document.getElementById('addSeanceModal').style.display = 'block';
            document.getElementById('addSeanceForm').reset();
            // Set default date to today
            document.getElementById('add_date_seance').valueAsDate = new Date();
        }

        function closeAddSeanceModal() {
            document.getElementById('addSeanceModal').style.display = 'none';
        }

        // Handle add seance form submission
        document.getElementById('addSeanceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_seance');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddSeanceModal();
                    loadSeances();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout de la séance', 'error');
            });
        });

        function deleteSeance(seanceId, seanceName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer la séance pour "${seanceName}" ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_seance');
                formData.append('id_seance', seanceId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadSeances();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression de la séance', 'error');
                });
            }
        }

        function refreshSeances() {
            loadSeances();
            showAlert('Liste des séances actualisée', 'success');
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
            const addModal = document.getElementById('addSeanceModal');
            
            if (event.target === addModal) {
                closeAddSeanceModal();
            }
        }
    </script>
</body>
</html>