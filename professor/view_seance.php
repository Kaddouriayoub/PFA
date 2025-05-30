<?php
require_once '../config.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Vérifier si l'ID de la séance est fourni
if (!isset($_GET['id'])) {
    header('Location: professor_dashboard.php');
    exit();
}

$seance_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Récupérer les informations de la séance
    $query = "
        SELECT sr.*, 
               em.nom_element,
               em.volume_horaire,
               m.nom_module,
               s.nom_semestre,
               a.annee_universitaire,
               f.nom_filiere,
               p.nom as prof_nom,
               p.prenom as prof_prenom
        FROM seance_reelle sr
        JOIN element_module em ON sr.id_element = em.id_element
        JOIN module m ON em.id_module = m.id_module
        JOIN semestre s ON m.id_semestre = s.id_semestre
        JOIN annee a ON s.id_annee = a.id_annee
        JOIN filiere f ON a.id_filiere = f.id_filiere
        JOIN professeur p ON sr.id_prof = p.id_prof
        WHERE sr.id_seance = ? AND p.id_user = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$seance_id, $user_id]);
    $seance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seance) {
        throw new Exception("Séance non trouvée ou accès non autorisé.");
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
    <title>Détails de la Séance</title>
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #1a1a1a;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        h2 {
            color: #1a1a1a;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 24px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .detail-label {
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .detail-value {
            color: #2c3e50;
            font-size: 16px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        .status-effectuee {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-en_attente {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        .status-contestee {
            background-color: #ffebee;
            color: #c62828;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .back-btn:hover {
            background-color: #388E3C;
        }
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'professor_header.php'; ?>
    
    <div class="container">
        <h2>Détails de la Séance</h2>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Date de la séance</div>
                    <div class="detail-value"><?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Type de séance</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['type_seance']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Élément</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['nom_element']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Module</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['nom_module']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Semestre</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['nom_semestre']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Filière</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['nom_filiere']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Année universitaire</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['annee_universitaire']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Volume horaire de l'élément</div>
                    <div class="detail-value"><?php echo htmlspecialchars($seance['volume_horaire']); ?> heures</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Statut</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo $seance['statut'] === '1' ? 'effectuee' : ($seance['statut'] === '0' ? 'contestee' : 'en_attente'); ?>">
                            <?php 
                            $status_text = [
                                '1' => 'Validée',
                                '0' => 'Non validée',
                                NULL => 'En attente'
                            ];
                            echo $status_text[$seance['statut']] ?? 'En attente';
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <a href="professor_dashboard.php" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Retour au tableau de bord
            </a>
        <?php endif; ?>
    </div>
</body>
</html> 