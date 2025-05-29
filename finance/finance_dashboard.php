<?php
// finance_dashboard.php
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

// Vérification simple
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SESSION['role'] != 'financier') {
    header("Location: index.php?error=access_denied");
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_professors_payment':
                $stmt = $conn->prepare("
                SELECT 
                    p.id_prof,
                    p.nom,
                    p.prenom,
                    p.type_prof,
                    g.nom_grade,
                    g.salaire_par_seance,
                    COUNT(CASE WHEN sr.statut = 1 AND sr.payee = 0 THEN 1 END) as seances_validees_non_payees,
                    COUNT(CASE WHEN sr.statut = 0 AND sr.payee = 0 THEN 1 END) as seances_non_validees,
                    COUNT(CASE WHEN sr.statut IS NULL AND sr.payee = 0 THEN 1 END) as seances_en_attente,
                    COUNT(CASE WHEN sr.statut = 1 AND sr.payee = 0 THEN 1 END) * g.salaire_par_seance as montant_a_payer
                FROM Professeur p
                LEFT JOIN Grade g ON p.id_grade = g.id_grade
                LEFT JOIN Seance_Reelle sr ON p.id_prof = sr.id_prof
                GROUP BY p.id_prof, p.nom, p.prenom, p.type_prof, g.nom_grade, g.salaire_par_seance
                ORDER BY p.nom, p.prenom
            ");
            $stmt->execute();
            $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'professors' => $professors]);
            exit;
                
            case 'get_professor_sessions':
                $prof_id = intval($_POST['prof_id']);
                $stmt = $conn->prepare("
                    SELECT 
                        sr.id_seance,
                        sr.date_seance,
                        sr.type_seance,
                        em.nom_element,
                        sr.statut,
                        sr.payee,
                        CASE 
                            WHEN sr.statut = 1 THEN 'Validée'
                            WHEN sr.statut = 0 THEN 'Non validée'
                            ELSE 'En attente'
                        END as statut_text
                    FROM Seance_Reelle sr
                    JOIN Element_Module em ON sr.id_element = em.id_element
                    WHERE sr.id_prof = ? AND sr.payee = 0
                    ORDER BY sr.date_seance DESC
                ");
                $stmt->execute([$prof_id]);
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'sessions' => $sessions]);
                exit;
                
            case 'generate_payslip':
                $prof_id = intval($_POST['prof_id']);
                $mois_periode = $_POST['mois_periode'];
                
                // Vérifier qu'il n'y a pas de séances en attente
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM Seance_Reelle 
                    WHERE id_prof = ? AND statut IS NULL AND payee = 0
                ");
                $stmt->execute([$prof_id]);
                $seances_en_attente = $stmt->fetchColumn();
                
                if ($seances_en_attente > 0) {
                    echo json_encode(['success' => false, 'message' => 'Impossible de générer la fiche : il y a encore des séances en attente de validation']);
                    exit;
                }
                
                // Calculer les informations de paiement
                $stmt = $conn->prepare("
                SELECT 
                    p.nom, p.prenom, g.salaire_par_seance,
                    COUNT(CASE WHEN sr.statut = 1 THEN 1 END) as seances_validees
                FROM Professeur p
                LEFT JOIN Grade g ON p.id_grade = g.id_grade
                LEFT JOIN Seance_Reelle sr ON p.id_prof = sr.id_prof AND sr.payee = 0
                WHERE p.id_prof = ?
                GROUP BY p.id_prof
                ");
                $stmt->execute([$prof_id]);
                $prof_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$prof_info || $prof_info['seances_validees'] == 0) {
                    echo json_encode(['success' => false, 'message' => 'Aucune séance validée à payer pour ce professeur']);
                    exit;
                }
                
                $montant_total = $prof_info['seances_validees'] * $prof_info['salaire_par_seance'];
                
                // Générer la fiche de salaire
                $stmt = $conn->prepare("
                    INSERT INTO Fiche_Salaire (id_prof, date_generation, mois_periode, nombre_seances, montant_total, id_financier, signature_financier, signature_directeur)
                    VALUES (?, CURDATE(), ?, ?, ?, ?, 0, 0)
                ");
                $stmt->execute([$prof_id, $mois_periode, $prof_info['seances_validees'], $montant_total, $_SESSION['financier_id']]);
                
                $fiche_id = $conn->lastInsertId();
                
                // Marquer les séances comme payées
                $stmt = $conn->prepare("
                    UPDATE Seance_Reelle 
                    SET payee = 1 
                    WHERE id_prof = ? AND statut = 1 AND payee = 0
                ");
                $stmt->execute([$prof_id]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Fiche de salaire générée avec succès',
                    'fiche_id' => $fiche_id
                ]);
                exit;
                
            case 'get_payslips':
                $stmt = $conn->prepare("
                    SELECT 
                        fs.id_fiche,
                        fs.date_generation,
                        fs.mois_periode,
                        fs.nombre_seances,
                        fs.montant_total,
                        fs.signature_financier,
                        fs.signature_directeur,
                        p.nom,
                        p.prenom
                    FROM Fiche_Salaire fs
                    JOIN Professeur p ON fs.id_prof = p.id_prof
                    ORDER BY fs.date_generation DESC
                ");
                $stmt->execute();
                $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'payslips' => $payslips]);
                exit;
                
            case 'sign_payslip':
                $fiche_id = intval($_POST['fiche_id']);
                $signature_type = $_POST['signature_type']; // 'financier' or 'directeur'
                
                if ($signature_type === 'financier') {
                    $stmt = $conn->prepare("UPDATE Fiche_Salaire SET signature_financier = 1 WHERE id_fiche = ?");
                } else if ($signature_type === 'directeur') {
                    $stmt = $conn->prepare("UPDATE Fiche_Salaire SET signature_directeur = 1 WHERE id_fiche = ?");
                } else {
                    echo json_encode(['success' => false, 'message' => 'Type de signature invalide']);
                    exit;
                }
                
                $stmt->execute([$fiche_id]);
                echo json_encode(['success' => true, 'message' => 'Signature ajoutée avec succès']);
                exit;
                
            case 'get_statistics':
                $stats = [];
                
                // Total professeurs
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Professeur");
                $stmt->execute();
                $stats['total_professors'] = $stmt->fetchColumn();
                
                // Total séances en attente
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Seance_Reelle WHERE statut IS NULL");
                $stmt->execute();
                $stats['pending_sessions'] = $stmt->fetchColumn();
                
                // Total montant à payer
                $stmt = $conn->prepare("
                    SELECT SUM(g.salaire_par_seance) 
                    FROM Seance_Reelle sr 
                    JOIN Professeur p ON sr.id_prof = p.id_prof 
                    JOIN Grade g ON p.id_grade = g.id_grade
                    WHERE sr.statut = 1 AND sr.payee = 0
                    ");
                $stmt->execute();
                $stats['total_to_pay'] = $stmt->fetchColumn() ?: 0;
                
                // Fiches générées ce mois
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM Fiche_Salaire 
                    WHERE MONTH(date_generation) = MONTH(CURDATE()) 
                    AND YEAR(date_generation) = YEAR(CURDATE())
                ");
                $stmt->execute();
                $stats['payslips_this_month'] = $stmt->fetchColumn();
                
                echo json_encode(['success' => true, 'stats' => $stats]);
                exit;
                
            case 'print_payslip':
                $fiche_id = intval($_POST['fiche_id']);
                
                // Requête AJAX pour obtenir les détails de la fiche
                $stmt = $conn->prepare("
                    SELECT 
                        fs.id_fiche,
                        fs.date_generation,
                        fs.mois_periode,
                        fs.nombre_seances,
                        fs.montant_total,
                        fs.signature_financier,
                        fs.signature_directeur,
                        p.nom,
                        p.prenom
                    FROM Fiche_Salaire fs
                    JOIN Professeur p ON fs.id_prof = p.id_prof
                    WHERE fs.id_fiche = ?
                ");
                $stmt->execute([$fiche_id]);
                $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payslip) {
                    echo json_encode(['success' => false, 'message' => 'Fiche de salaire non trouvée']);
                    exit;
                }
                
                // Créer le contenu HTML pour l'impression
                $printContent = "
                    <html>
                    <head>
                        <title>Fiche de Salaire</title>
                        <style>
                            @page {
                                size: A4;
                                margin: 1cm;
                            }
                            body {
                                font-family: Arial, sans-serif;
                                line-height: 1.6;
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 20px;
                            }
                            .header h1 {
                                margin: 0;
                            }
                            .header h2 {
                                margin: 5px 0 20px;
                            }
                            .details {
                                margin-bottom: 20px;
                            }
                            .details p {
                                margin: 5px 0;
                            }
                            .signature {
                                margin-top: 30px;
                                text-align: center;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                            }
                            table, th, td {
                                border: 1px solid #000;
                            }
                            th, td {
                                padding: 10px;
                                text-align: left;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='header'>
                           <br>
                           <br> <h1>ENSIAS</h1>
                            <h2>Fiche de Salaire</h2>
                        </div>
                        <br>
                        <div class='details'>
                            <p><strong>Professeur:</strong> {$payslip['nom']} {$payslip['prenom']}</p>
                            <p><strong>Période:</strong> {$payslip['mois_periode']}</p>
                            <p><strong>Date de génération:</strong> {$payslip['date_generation']}</p>
                            <br>
                        <table>
                            <tr>
                            <td><strong>Nom du Professeur</strong></td>
                            <td>{$payslip['nom']} {$payslip['prenom']}</td>
                            </tr>
                            <tr>
                            <td><strong>Nombre de Séances</strong></td>
                            <td>{$payslip['nombre_seances']}</td>
                            </tr>
                            <tr>
                            <td><strong>Montant Total</strong></td>
                            <td>{$payslip['montant_total']} DH</td>
                            </tr>
                        </table>
                        </div>
                        <br>
                        <br>
                        
                        <div class='signature'>
<div class='signature'>
    <div class='signature-box'>
        <pre>Signature du Financier :                  Signature du Directeur : </pre>
    </div>

</div>
                        </div>
                    </body>
                    </html>
                ";
                
                // Injecter le contenu dans la fenêtre d'impression
                echo json_encode(['success' => true, 'printContent' => $printContent]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        exit;
    }
}

// Get financier ID
$stmt = $conn->prepare("SELECT id_financier FROM Financier WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$financier_data = $stmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['financier_id'] = $financier_data['id_financier'] ?? 1;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Financier - ENSIAS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
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
            margin: 10px 0;
        }
        .stat-card.professors .number { color: #27ae60; }
        .stat-card.pending .number { color: #e67e22; }
        .stat-card.payment .number { color: #f39c12; }
        .stat-card.payslips .number { color: #3498db; }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            border-bottom: 2px solid #f39c12;
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
            font-size: 14px;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.8; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #6c757d; }
        
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
            width: 800px;
            max-width: 95%;
            max-height: 90%;
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
        
        .payslip-preview {
            border: 2px solid #ddd;
            padding: 20px;
            margin: 20px 0;
            background: white;
        }
        .payslip-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .payslip-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box {
            border: 1px solid #ddd;
            padding: 20px;
            width: 150px;
            text-align: center;
            min-height: 60px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 3px solid transparent;
        }
        .tab.active {
            border-bottom-color: #f39c12;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Dashboard Financier ENSIAS</h1>
            <div class="user-info">
                Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>
                <a href="../logout.php" class="logout-btn">Déconnexion</a>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card professors">
                <h3>Total Professeurs</h3>
                <div class="number" id="total-professors">--</div>
            </div>
            <div class="stat-card pending">
                <h3>Séances en Attente</h3>
                <div class="number" id="pending-sessions">--</div>
            </div>
            <div class="stat-card payment">
                <h3>Montant à Payer</h3>
                <div class="number" id="total-to-pay">-- DH</div>
            </div>
            <div class="stat-card payslips">
                <h3>Fiches ce Mois</h3>
                <div class="number" id="payslips-this-month">--</div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="section">
            <div class="tabs">
                <button class="tab active" onclick="showTab('professors')">Paiement des Professeurs</button>
                <button class="tab" onclick="showTab('payslips')">Fiches de Salaire</button>
            </div>

            <!-- Professors Payment Tab -->
            <div class="tab-content active" id="professors-tab">
                <h3>Gestion des Paiements</h3>
                <button class="btn btn-primary" onclick="loadProfessorsPayment()">Actualiser</button>
                
                <div id="alert-container"></div>
                
                <div id="professors-table-container">
                    <div class="loading">Chargement des données...</div>
                </div>
            </div>

            <!-- Payslips Tab -->
            <div class="tab-content" id="payslips-tab">
                <h3>Fiches de Salaire Générées</h3>
                <button class="btn btn-primary" onclick="loadPayslips()">Actualiser</button>
                
                <div id="payslips-table-container">
                    <div class="loading">Chargement des fiches...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Payslip Modal -->
    <div id="generatePayslipModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeGeneratePayslipModal()">&times;</span>
            <h3>Générer Fiche de Salaire</h3>
            
            <form id="generatePayslipForm">
                <div class="form-group">
                    <label for="prof_name">Professeur:</label>
                    <input type="text" id="prof_name" readonly>
                    <input type="hidden" id="prof_id" name="prof_id">
                </div>
                <div class="form-group">
                    <label for="mois_periode">Période (Mois):</label>
                    <input type="month" id="mois_periode" name="mois_periode" required>
                </div>
                <div class="form-group">
                    <label>Résumé des séances:</label>
                    <div id="sessions-summary"></div>
                </div>
                <button type="submit" class="btn btn-success">Générer la Fiche</button>
                <button type="button" class="btn" onclick="closeGeneratePayslipModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- View Sessions Modal -->
    <div id="viewSessionsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeViewSessionsModal()">&times;</span>
            <h3>Détail des Séances</h3>
            <div id="sessions-detail-container">
                <div class="loading">Chargement...</div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let professors = [];
        let payslips = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadProfessorsPayment();
        });

        // Tab management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
            
            // Load content if needed
            if (tabName === 'payslips') {
                loadPayslips();
            }
        }

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
                    document.getElementById('total-professors').textContent = data.stats.total_professors;
                    document.getElementById('pending-sessions').textContent = data.stats.pending_sessions;
                    document.getElementById('total-to-pay').textContent = data.stats.total_to_pay + ' DH';
                    document.getElementById('payslips-this-month').textContent = data.stats.payslips_this_month;
                }
            })
            .catch(error => {
                console.error('Error loading statistics:', error);
            });
        }

        // Load professors payment data
        function loadProfessorsPayment() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_professors_payment'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    professors = data.professors;
                    displayProfessorsPayment();
                } else {
                    showAlert('Erreur lors du chargement des données', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Display professors payment table
        function displayProfessorsPayment() {
            const container = document.getElementById('professors-table-container');
            
            if (professors.length === 0) {
                container.innerHTML = '<p>Aucun professeur trouvé.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Professeur</th>
                            <th>Type</th>
                            <th>Salaire/Séance</th>
                            <th>Séances Validées</th>
                            <th>Séances Non Validées</th>
                            <th>Séances En Attente</th>
                            <th>Montant à Payer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            professors.forEach(prof => {
                const canGenerate = prof.seances_en_attente == 0 && prof.seances_validees_non_payees > 0;
                const statusBadge = prof.seances_en_attente > 0 ? 
                    '<span class="badge badge-warning">En attente</span>' : 
                    '<span class="badge badge-success">Prêt</span>';

                html += `
                    <tr>
                        <td><strong>${prof.nom} ${prof.prenom}</strong></td>
                        <td><span class="badge ${prof.type_prof === 'permanent' ? 'badge-success' : 'badge-secondary'}">${prof.type_prof}</span></td>
                        <td>${prof.salaire_par_seance} DH</td>
                        <td>${prof.seances_validees_non_payees}</td>
                        <td>${prof.seances_non_validees}</td>
                        <td>${prof.seances_en_attente}</td>
                        <td><strong>${prof.montant_a_payer} DH</strong></td>
                        <td>
                            <button class="btn btn-info btn-sm" onclick="viewProfessorSessions(${prof.id_prof}, '${prof.nom} ${prof.prenom}')">
                                Voir Séances
                            </button>
                            <button class="btn btn-success btn-sm" 
                                    onclick="openGeneratePayslipModal(${prof.id_prof}, '${prof.nom} ${prof.prenom}', ${prof.seances_validees_non_payees}, ${prof.montant_a_payer})"
                                    ${!canGenerate ? 'disabled' : ''}>
                                Générer Fiche
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

        // View professor sessions
        function viewProfessorSessions(profId, profName) {
            document.getElementById('viewSessionsModal').style.display = 'block';
            document.getElementById('sessions-detail-container').innerHTML = '<div class="loading">Chargement...</div>';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_professor_sessions&prof_id=${profId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayProfessorSessions(data.sessions, profName);
                } else {
                    document.getElementById('sessions-detail-container').innerHTML = '<p>Erreur lors du chargement des séances.</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('sessions-detail-container').innerHTML = '<p>Erreur de connexion.</p>';
            });
        }

        // Display professor sessions
        function displayProfessorSessions(sessions, profName) {
            const container = document.getElementById('sessions-detail-container');
            
            let html = `<h4>Séances de ${profName}</h4>`;
            
            if (sessions.length === 0) {
                html += '<p>Aucune séance non payée trouvée.</p>';
            } else {
                html += `
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Élément</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                sessions.forEach(session => {
                    let statusClass = 'badge-secondary';
                    let statusText = 'En attente';
                    if (session.statut === 1) {
                        statusClass = 'badge-success';
                        statusText = 'Validée';
                    } else if (session.statut === 0) {
                        statusClass = 'badge-danger';
                        statusText = 'Non validée';
                    }

                    html += `
                        <tr>
                            <td>${session.date_seance}</td>
                            <td>${session.type_seance}</td>
                            <td>${session.nom_element}</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;
            }
            
            container.innerHTML = html;
        }

        // Open generate payslip modal
        function openGeneratePayslipModal(profId, profName, sessionsCount, amount) {
            document.getElementById('generatePayslipModal').style.display = 'block';
            document.getElementById('prof_id').value = profId;
            document.getElementById('prof_name').value = profName;
            
            // Set current month as default
            const today = new Date();
            const currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('mois_periode').value = currentMonth;
            
            // Display sessions summary
            document.getElementById('sessions-summary').innerHTML = `
                <p>Nombre de séances validées: <strong>${sessionsCount}</strong></p>
                <p>Montant total à payer: <strong>${amount} DH</strong></p>
            `;
        }

        // Close generate payslip modal
        function closeGeneratePayslipModal() {
            document.getElementById('generatePayslipModal').style.display = 'none';
        }

        // Submit generate payslip form
        document.getElementById('generatePayslipForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const profId = document.getElementById('prof_id').value;
            const moisPeriode = document.getElementById('mois_periode').value;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_payslip&prof_id=${profId}&mois_periode=${moisPeriode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Fiche de salaire générée avec succès', 'success');
                    closeGeneratePayslipModal();
                    loadProfessorsPayment();
                    loadPayslips();
                    loadStatistics();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur lors de la génération de la fiche', 'error');
            });
        });

        // Load payslips
        function loadPayslips() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_payslips'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    payslips = data.payslips;
                    displayPayslips();
                } else {
                    showAlert('Erreur lors du chargement des fiches', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Display payslips table
        function displayPayslips() {
            const container = document.getElementById('payslips-table-container');
            
            if (payslips.length === 0) {
                container.innerHTML = '<p>Aucune fiche de salaire générée.</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date Génération</th>
                            <th>Période</th>
                            <th>Professeur</th>
                            <th>Séances</th>
                            <th>Montant (DH)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            payslips.forEach(payslip => {
                const signatures = [];
                if (payslip.signature_financier) signatures.push('Financier');
                if (payslip.signature_directeur) signatures.push('Directeur');
                
                const signatureText = signatures.length > 0 ? 
                    signatures.join(' + ') : 'Aucune';
                
                const canSignFinancier = !payslip.signature_financier;
                const canSignDirecteur = !payslip.signature_directeur;

                html += `
                    <tr>
                        <td>${payslip.id_fiche}</td>
                        <td>${payslip.date_generation}</td>
                        <td>${payslip.mois_periode}</td>
                        <td>${payslip.nom} ${payslip.prenom}</td>
                        <td>${payslip.nombre_seances}</td>
                        <td>${payslip.montant_total}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" 
                                    onclick="printPayslip(${payslip.id_fiche})">
                                Imprimer
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

        // View payslip details
        function viewPayslipDetails(ficheId) {
            const payslip = payslips.find(p => p.id_fiche == ficheId);
            if (!payslip) return;

            const modalContent = `
                <div class="payslip-preview">
                    <div class="payslip-header">
                        <h3>Fiche de Salaire #${payslip.id_fiche}</h3>
                        <p>ENSIAS - Paiement des enseignants</p>
                    </div>
                    
                    <div class="payslip-content">
                        <div>
                            <p><strong>Professeur:</strong> ${payslip.nom} ${payslip.prenom}</p>
                            <p><strong>Date de génération:</strong> ${payslip.date_generation}</p>
                        </div>
                        <div>
                            <p><strong>Période:</strong> ${payslip.mois_periode}</p>
                            <p><strong>Nombre de séances:</strong> ${payslip.nombre_seances}</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <h4>Montant total à payer: ${payslip.montant_total} DH</h4>
                    </div>
                    
                    <div class="signature-section">
                        <div class="signature-box">
                            <p>Signature Financier</p>
                            ${payslip.signature_financier ? '<p style="color: green;">Signé</p>' : '<p style="color: red;">Non signé</p>'}
                        </div>
                        <div class="signature-box">
                            <p>Signature Directeur</p>
                            ${payslip.signature_directeur ? '<p style="color: green;">Signé</p>' : '<p style="color: red;">Non signé</p>'}
                        </div>
                    </div>
                </div>
            `;

            // Create a new modal for payslip details
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
                    ${modalContent}
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        // Sign payslip
        function signPayslip(ficheId, signatureType) {
            if (!confirm(`Voulez-vous vraiment signer cette fiche en tant que ${signatureType}?`)) {
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=sign_payslip&fiche_id=${ficheId}&signature_type=${signatureType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Signature ajoutée avec succès', 'success');
                    loadPayslips();
                } else {
                    showAlert('Erreur lors de la signature', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Print payslip
        function printPayslip(ficheId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=print_payslip&fiche_id=${ficheId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(data.printContent);
                    printWindow.document.close();
                    printWindow.onload = () => {
                        printWindow.print();
                    };
                } else {
                    showAlert('Erreur lors de l\'impression de la fiche', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erreur de connexion', 'error');
            });
        }

        // Close view sessions modal
        function closeViewSessionsModal() {
            document.getElementById('viewSessionsModal').style.display = 'none';
        }

        // Show alert message
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            
            // Remove alert after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>
</body>
</html>