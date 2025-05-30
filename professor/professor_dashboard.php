<?php
// professor_dashboard.php

// Assurez-vous d'avoir une connexion à la base de données
require_once '../config.php';

// Fonctions utilitaires pour le statut
function getStatusBadgeClass($status) {
    switch($status) {
        case '1':
            return 'status-validated';
        case '0':
            return 'status-rejected';
        default:
            return 'status-pending';
    }
}

function getStatusText($status) {
    switch($status) {
        case '1':
            return 'Validée';
        case '0':
            return 'Non validée';
        default:
            return 'En attente';
    }
}

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

try {
    // Vérifier si l'utilisateur est un professeur et récupérer ses informations
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.nom,
            u.prenom,
            g.nom_grade as grade_actuel,
            g.salaire_par_seance
        FROM professeur p 
        INNER JOIN utilisateur u ON p.id_user = u.id_user 
        LEFT JOIN grade g ON p.id_grade = g.id_grade
        WHERE u.id_user = ?
    ");
    $stmt->execute([$user_id]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        throw new Exception("Accès refusé. Vous devez être un professeur pour accéder à cette page.");
    }

    $professor_id = $prof['id_prof'];

    // Récupérer les séances réelles avec leur statut
    $query = "
        SELECT sr.id_seance, 
               sr.date_seance,
               sr.type_seance,
               COALESCE(sr.statut, '') as statut,
               em.nom_element, 
               m.nom_module, 
               s.nom_semestre,
               a.annee_universitaire, 
               f.nom_filiere
        FROM seance_reelle sr
        JOIN element_module em ON sr.id_element = em.id_element
        JOIN module m ON em.id_module = m.id_module
        JOIN semestre s ON m.id_semestre = s.id_semestre
        JOIN annee a ON s.id_annee = a.id_annee
        JOIN filiere f ON a.id_filiere = f.id_filiere
        WHERE sr.id_prof = ?
        ORDER BY sr.date_seance DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$professor_id]);
    $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques
    $total_seances = count($seances);
    $seances_validees = 0;
    $seances_non_validees = 0;
    $montant_valide = 0;

    // Calculer le montant total en fonction des séances validées
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb_seances_validees
        FROM seance_reelle sr
        WHERE sr.id_prof = ? AND sr.statut = '1'
    ");
    $stmt->execute([$professor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $seances_validees = $result['nb_seances_validees'];

    // Calculer le montant total
    $montant_valide = $seances_validees * floatval($prof['salaire_par_seance']);

    // Mettre à jour le montant total dans la base de données
    $stmt = $pdo->prepare("
        UPDATE professeur 
        SET montant_total = ? 
        WHERE id_prof = ?
    ");
    $stmt->execute([$montant_valide, $professor_id]);

    // Compter les séances non validées
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb_seances_non_validees
        FROM seance_reelle sr
        WHERE sr.id_prof = ? AND sr.statut = '0'
    ");
    $stmt->execute([$professor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $seances_non_validees = $result['nb_seances_non_validees'];
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
    error_log("PDO Error in professor_dashboard.php: " . $e->getMessage());
    $seances = [];
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Error in professor_dashboard.php: " . $e->getMessage());
    $seances = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord professeur - Séances réelles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f6fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .main-content {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 600;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            color: #2d3748;
            background-color: white;
            transition: all 0.3s ease;
        }

        .filter-group select:hover,
        .filter-group input:hover {
            border-color: #4CAF50;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background-color: #4CAF50;
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #f8fafb;
        }

        td {
            font-size: 14px;
            color: #4a5568;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .status-validated {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background-color: #ffebee;
            color: #c62828;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-validate, .btn-cancel {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .btn-validate {
            background-color: #4CAF50;
            color: white;
        }

        .btn-validate:hover {
            background-color: #43a047;
        }

        .btn-cancel {
            background-color: #ef5350;
            color: white;
        }

        .btn-cancel:hover {
            background-color: #e53935;
        }

        .btn-validate:disabled,
        .btn-cancel:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .no-seances {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }

        .no-seances svg {
            margin-bottom: 15px;
        }

        .no-seances p {
            margin: 0;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .main-content {
                padding: 15px;
            }

            .filters {
                grid-template-columns: 1fr;
                padding: 15px;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            th, td {
                padding: 8px 12px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-details {
            flex: 1;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .stat-card.total {
            border-left: 4px solid #2196F3;
        }
        
        .stat-card.total .stat-icon {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .stat-card.validated {
            border-left: 4px solid #4CAF50;
        }
        
        .stat-card.validated .stat-icon {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .stat-card.cancelled {
            border-left: 4px solid #f44336;
        }
        
        .stat-card.cancelled .stat-icon {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        .stat-card.earnings {
            border-left: 4px solid #ff9800;
        }
        
        .stat-card.earnings .stat-icon {
            background-color: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
            padding: 15px;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .stat-value {
                font-size: 20px;
            }
        }

        .profile-image {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #4CAF50;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .stat-card.profile {
            grid-column: span 2;
            background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
            color: white;
        }

        .stat-card.profile .stat-details {
            display: flex;
                flex-direction: column;
                gap: 5px;
            }

        .stat-card.profile .stat-title {
            font-size: 20px;
            font-weight: 600;
            color: white;
        }

        .stat-card.profile .prof-type {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .stat-card.profile .prof-salary {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 600;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .stat-card.profile {
                grid-column: span 1;
            }
        }

        .profile-overview {
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-image {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 15px;
            overflow: hidden;
            border: 4px solid #4CAF50;
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.2);
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h3 {
            font-size: 24px;
            color: #2c3e50;
            margin: 0 0 5px 0;
            font-weight: 600;
        }

        .grade {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .profile-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(76, 175, 80, 0.1);
            padding: 8px 15px;
            border-radius: 10px;
            color: #2c3e50;
        }

        .stat-item i {
            color: #4CAF50;
            font-size: 18px;
        }

        .stat-item span {
            font-size: 14px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-image {
                margin: 0 auto;
            }

            .profile-stats {
                flex-direction: column;
                align-items: stretch;
            }

            .stat-item {
                justify-content: center;
            }

            .grade {
                display: block;
                text-align: center;
                margin: 10px auto;
            }
        }

        .status-done {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            color: white;
            background-color: #757575;
            transition: all 0.3s ease;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-done i {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'professor_header.php'; ?>
    
    <div class="container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
                    </div>
        <?php endif; ?>

        <div class="main-content">
            <h2>Tableau de bord</h2>

            <div class="profile-overview">
            <div class="profile-card">
                <div class="profile-header">
                        <div class="profile-image">
                            <img src="<?php echo !empty($prof['photo_profil']) ? '../' . $prof['photo_profil'] : '../assets/images/default-avatar.jpg'; ?>" alt="Photo de profil">
                        </div>
                        <div class="profile-info">
                            <h3><?php echo htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']); ?></h3>
                            <span class="grade"><?php echo htmlspecialchars($prof['grade_actuel']); ?></span>
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <span><?php echo $seances_validees; ?> séances validées</span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span><?php echo number_format($montant_valide, 2); ?> DH</span>
                                </div>
                            </div>
                        </div>
                </div>
            </div>

            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $total_seances; ?></div>
                        <div class="stat-label">Séances Totales</div>
                    </div>
                </div>
                <div class="stat-card validated">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
            </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $seances_validees; ?></div>
                        <div class="stat-label">Séances Validées</div>
        </div>
            </div>
                <div class="stat-card cancelled">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
            </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $seances_non_validees; ?></div>
                        <div class="stat-label">Séances Non Validées</div>
            </div>
                </div>
                <div class="stat-card earnings">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($montant_valide, 2); ?> DH</div>
                        <div class="stat-label">Montant Total</div>
                    </div>
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <label for="filter-status">Statut</label>
                <select id="filter-status">
                    <option value="">Tous les statuts</option>
                    <option value="1">Validé</option>
                    <option value="0">Non validé</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-type">Type de séance</label>
                <select id="filter-type">
                    <option value="">Tous les types</option>
                    <option value="Cours">Cours</option>
                    <option value="TD">TD</option>
                    <option value="TP">TP</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="date-debut">Date début</label>
                <input type="date" id="date-debut">
            </div>
            <div class="filter-group">
                <label for="date-fin">Date fin</label>
                <input type="date" id="date-fin">
            </div>
        </div>
        
        <?php if (!empty($seances)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Élément</th>
                        <th>Module</th>
                        <th>Semestre</th>
                        <th>Filière</th>
                        <th>Année</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seances as $seance): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?></td>
                            <td><?php echo htmlspecialchars($seance['type_seance']); ?></td>
                            <td><?php echo htmlspecialchars($seance['nom_element']); ?></td>
                            <td><?php echo htmlspecialchars($seance['nom_module']); ?></td>
                            <td><?php echo htmlspecialchars($seance['nom_semestre']); ?></td>
                            <td><?php echo htmlspecialchars($seance['nom_filiere']); ?></td>
                            <td><?php echo htmlspecialchars($seance['annee_universitaire']); ?></td>
                            <td>
                                <?php
                                $status_class = getStatusBadgeClass($seance['statut']);
                                $status_text = getStatusText($seance['statut']);
                                ?>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="fas <?php echo $status_text === 'Validée' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $status_text; ?>
                                </div>
                            </td>
                            <td class="action-buttons">
                                <?php if ($seance['statut'] === '0'): ?>
                                        <div class="status-done cancelled">
                                            <i class="fas fa-check"></i>
                                            Done
                                        </div>
                                <?php elseif ($seance['statut'] === '1'): ?>
                                        <div class="status-done validated">
                                        <i class="fas fa-check"></i>
                                            Done
                                        </div>
                                <?php else: ?>
                                    <button 
                                        class="btn-validate"
                                        onclick="updateSeanceStatus(<?php echo $seance['id_seance']; ?>, '1')"
                                    >
                                        <i class="fas fa-check"></i>
                                        Valider
                                    </button>
                                    <button 
                                        class="btn-cancel"
                                        onclick="updateSeanceStatus(<?php echo $seance['id_seance']; ?>, '0')"
                                    >
                                        <i class="fas fa-times"></i>
                                        Annuler
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-seances">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                    <line x1="9" y1="9" x2="9.01" y2="9"/>
                    <line x1="15" y1="9" x2="15.01" y2="9"/>
                </svg>
                <p>Aucune séance réelle trouvée.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <script>
        // Fonction pour filtrer les séances
        function filterSeances() {
            const statusFilter = document.getElementById('filter-status').value.toLowerCase();
            const typeFilter = document.getElementById('filter-type').value;
            const dateDebut = document.getElementById('date-debut').value;
            const dateFin = document.getElementById('date-fin').value;

            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const status = row.querySelector('.status-badge').textContent.toLowerCase().trim();
                const type = row.cells[1].textContent.trim();
                const date = row.cells[0].textContent.split('/').reverse().join('-');

                let showRow = true;

                if (statusFilter && !status.includes(statusFilter)) showRow = false;
                if (typeFilter && type !== typeFilter) showRow = false;
                if (dateDebut && date < dateDebut) showRow = false;
                if (dateFin && date > dateFin) showRow = false;

                row.style.display = showRow ? '' : 'none';
            });
        }

        // Ajouter les écouteurs d'événements pour les filtres
        document.getElementById('filter-status').addEventListener('change', filterSeances);
        document.getElementById('filter-type').addEventListener('change', filterSeances);
        document.getElementById('date-debut').addEventListener('change', filterSeances);
        document.getElementById('date-fin').addEventListener('change', filterSeances);

        function updateSeanceStatus(id_seance, status) {
            if (!confirm('Êtes-vous sûr de vouloir ' + (status === '1' ? 'valider' : 'annuler') + ' cette séance ?')) {
                return;
            }

            // Convertir le statut en '1' ou '0'
            const statusValue = status === '1' ? '1' : '0';

            fetch('update_seance_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id_seance=${id_seance}&status=${statusValue}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'interface
                    const row = document.querySelector(`button[onclick*="${id_seance}"]`).closest('tr');
                    const statusCell = row.querySelector('.status-badge');
                    const actionCell = row.querySelector('.action-buttons');

                    // Mettre à jour le badge de statut
                    const statusText = statusValue === '1' ? 'Validée' : 'Non validée';
                    const statusClass = statusValue === '1' ? 'status-validated' : 'status-rejected';
                    const iconClass = statusValue === '1' ? 'fa-check-circle' : 'fa-times-circle';
                    
                    statusCell.className = `status-badge ${statusClass}`;
                    statusCell.innerHTML = `
                        <i class="fas ${iconClass}"></i>
                        ${statusText}
                    `;

                    // Mettre à jour les boutons d'action
                    actionCell.innerHTML = `
                        <div class="status-done ${statusValue === '1' ? 'validated' : 'cancelled'}">
                            <i class="fas fa-check"></i>
                            Done
                        </div>
                    `;

                    // Mettre à jour les statistiques
                    const seancesValideesElement = document.querySelector('.stat-card.validated .stat-value');
                    const seancesNonValideesElement = document.querySelector('.stat-card.cancelled .stat-value');
                    const montantTotalElement = document.querySelector('.stat-card.earnings .stat-value');
                    const montantTotalProfileElement = document.querySelector('.profile-stats .stat-item:nth-child(2) span');
                    const seancesValideesProfileElement = document.querySelector('.profile-stats .stat-item:nth-child(1) span');

                    let seancesValidees = parseInt(seancesValideesElement.textContent);
                    let seancesNonValidees = parseInt(seancesNonValideesElement.textContent);

                    if (statusValue === '1') {
                        seancesValidees++;
                        seancesNonValidees = Math.max(0, seancesNonValidees - 1);
                        seancesValideesElement.textContent = seancesValidees;
                        seancesNonValideesElement.textContent = seancesNonValidees;
                        seancesValideesProfileElement.textContent = seancesValidees + ' séances validées';
                    } else {
                        seancesValidees = Math.max(0, seancesValidees - 1);
                        seancesNonValidees++;
                        seancesValideesElement.textContent = seancesValidees;
                        seancesNonValideesElement.textContent = seancesNonValidees;
                        seancesValideesProfileElement.textContent = seancesValidees + ' séances validées';
                    }

                    // Mettre à jour le montant total
                    const nouveauMontant = parseFloat(data.montant_total).toFixed(2);
                    montantTotalElement.textContent = nouveauMontant + ' DH';
                    montantTotalProfileElement.textContent = nouveauMontant + ' DH';

                    // Mettre à jour le montant dans l'en-tête
                    const headerMontantElement = document.querySelector('.professor-stats .stat-box:first-child');
                    if (headerMontantElement) {
                        headerMontantElement.textContent = nouveauMontant + ' DH';
                    }
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la communication avec le serveur');
            });
        }
    </script>
</body>
</html>