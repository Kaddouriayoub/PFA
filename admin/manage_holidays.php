<?php
// manage_holidays.php
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

// Vérification d'accès admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_holidays':
                $stmt = $conn->prepare("
                    SELECT id_jour, date, type_jour
                    FROM Jour_Ferie_Greve 
                    ORDER BY date DESC
                ");
                $stmt->execute();
                $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'holidays' => $holidays]);
                exit;
                
            case 'add_holiday':
                $date = $_POST['date'];
                $type = $_POST['type_jour'];
                
                // Validation de la date
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    echo json_encode(['success' => false, 'message' => 'Format de date invalide']);
                    exit;
                }
                
                // Vérifier si la date existe déjà
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Jour_Ferie_Greve WHERE date = ?");
                $stmt->execute([$date]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cette date existe déjà']);
                    exit;
                }
                
                // Ajouter le jour (on utilise id_annee = 1 par défaut)
                $stmt = $conn->prepare("INSERT INTO Jour_Ferie_Greve (date, type_jour, id_annee) VALUES (?, ?, 1)");
                $stmt->execute([$date, $type]);
                
                echo json_encode(['success' => true, 'message' => 'Jour ajouté avec succès']);
                exit;
                
            case 'delete_holiday':
                $holiday_id = intval($_POST['holiday_id']);
                
                $stmt = $conn->prepare("DELETE FROM Jour_Ferie_Greve WHERE id_jour = ?");
                $stmt->execute([$holiday_id]);
                
                echo json_encode(['success' => true, 'message' => 'Jour supprimé avec succès']);
                exit;
                
            case 'get_blocked_dates':
                // Cette fonction sera utilisée par d'autres pages pour obtenir les dates bloquées
                $stmt = $conn->prepare("SELECT date, type_jour FROM Jour_Ferie_Greve");
                $stmt->execute();
                $blocked_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'blocked_dates' => $blocked_dates]);
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
    <title>Gestion des Jours Fériés et Grèves - ENSIAS</title>
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
        .header .logout-btn, .header .back-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        .header .back-btn {
            background: #95a5a6;
        }
        .header .logout-btn:hover {
            background: #c0392b;
        }
        .header .back-btn:hover {
            background: #7f8c8d;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
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
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: all 0.3s;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }
        
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
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-ferie {
            background: #3498db;
            color: white;
        }
        .badge-greve {
            background: #e74c3c;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }
        .stat-card.ferie .number { color: #3498db; }
        .stat-card.greve .number { color: #e74c3c; }
        
        .date-input {
            position: relative;
        }
        
        .blocked-dates-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .date-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            color: #004085;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .date-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Gestion des Jours Fériés et Grèves</h1>
            <div class="user-info">
                Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>
                <a href="admin_dashboard.php" class="back-btn">Retour au Dashboard</a>
                <a href="../logout.php" class="logout-btn">Déconnexion</a>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card ferie">
                <h4>Jours Fériés</h4>
                <div class="number" id="total-ferie">--</div>
            </div>
            <div class="stat-card greve">
                <h4>Jours de Grève</h4>
                <div class="number" id="total-greve">--</div>
            </div>
            <div class="stat-card">
                <h4>Total Jours</h4>
                <div class="number" id="total-holidays">--</div>
            </div>
        </div>

        <!-- Management Section -->
        <div class="management-section">
            <h3>Gestion des Jours Non Travaillés</h3>
            
            <div class="blocked-dates-info">
                <strong>Information:</strong> Les dates ajoutées ici seront automatiquement bloquées lors de la création des séances. 
                Les professeurs ne pourront pas sélectionner ces dates.
            </div>
            
            
            <button class="btn btn-success" onclick="openAddHolidayModal()">Ajouter un jour</button>
            <button class="btn btn-primary" onclick="loadHolidays()">Actualiser</button>
            
            <div id="alert-container"></div>
            
            <div id="holidays-table-container">
                <div class="loading">Chargement des jours...</div>
            </div>
        </div>
    </div>

    <!-- Add Holiday Modal -->
    <div id="addHolidayModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddHolidayModal()">&times;</span>
            <h3>Ajouter un jour férié/grève</h3>
            <form id="addHolidayForm">
                <div class="form-group">
                    <label for="add_date">Date:</label>
                    <input type="date" id="add_date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="add_type">Type:</label>
                    <select id="add_type" name="type_jour" required>
                        <option value="">Sélectionner un type</option>
                        <option value="ferie">Jour Férié</option>
                        <option value="greve">Jour de Grève</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Ajouter</button>
                <button type="button" class="btn" onclick="closeAddHolidayModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        let holidays = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadHolidays();
            setupDateInputHandlers();
        });

        // Setup date input event handlers
        function setupDateInputHandlers() {
            const dateInput = document.getElementById('add_date');
            const warning = document.getElementById('date-warning');
            
            dateInput.addEventListener('change', function() {
                if (this.value) {
                    warning.style.display = 'block';
                } else {
                    warning.style.display = 'none';
                }
            });
        }

        // Load holidays
        function loadHolidays() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_holidays'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    holidays = data.holidays;
                    displayHolidays();
                    updateStatistics();
                } else {
                    showAlert('Erreur lors du chargement des jours', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Display holidays in table
        function displayHolidays() {
            const container = document.getElementById('holidays-table-container');
            
            if (holidays.length === 0) {
                container.innerHTML = '<p>Aucun jour férié ou de grève enregistré.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            holidays.forEach(holiday => {
                const badgeClass = holiday.type_jour === 'ferie' ? 'badge-ferie' : 'badge-greve';
                const typeText = holiday.type_jour === 'ferie' ? 'Jour Férié' : 'Jour de Grève';
                
                html += `
                    <tr>
                        <td>${formatDate(holiday.date)}</td>
                        <td><span class="badge ${badgeClass}">${typeText}</span></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteHoliday(${holiday.id_jour}, '${holiday.date}')">
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

        // Update statistics
        function updateStatistics() {
            const ferieCount = holidays.filter(h => h.type_jour === 'ferie').length;
            const greveCount = holidays.filter(h => h.type_jour === 'greve').length;
            
            document.getElementById('total-ferie').textContent = ferieCount;
            document.getElementById('total-greve').textContent = greveCount;
            document.getElementById('total-holidays').textContent = holidays.length;
        }

        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString('fr-FR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Open add holiday modal
        function openAddHolidayModal() {
            document.getElementById('addHolidayModal').style.display = 'block';
            document.getElementById('addHolidayForm').reset();
            document.getElementById('date-warning').style.display = 'none';
        }

        // Close add holiday modal
        function closeAddHolidayModal() {
            document.getElementById('addHolidayModal').style.display = 'none';
        }

        // Handle add holiday form submission
        document.getElementById('addHolidayForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_holiday');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeAddHolidayModal();
                    loadHolidays();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de l\'ajout du jour', 'error');
            });
        });

        // Delete holiday
        function deleteHoliday(holidayId, date) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le jour du ${formatDate(date)} ?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_holiday');
                formData.append('holiday_id', holidayId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadHolidays();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Erreur lors de la suppression du jour', 'error');
                });
            }
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
            const addModal = document.getElementById('addHolidayModal');
            
            if (event.target === addModal) {
                closeAddHolidayModal();
            }
        }
    </script>
</body>
</html>