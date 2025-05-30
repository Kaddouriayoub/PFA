<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

try {
    // Récupérer les informations du professeur avec son grade actuel
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
    $stmt->execute([$_SESSION['user_id']]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        throw new Exception("Professeur non trouvé");
    }

    // Compter les séances validées et calculer le montant total
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb_seances_validees
        FROM seance_reelle sr
        WHERE sr.id_prof = ? AND sr.statut = '1'
    ");
    $stmt->execute([$prof['id_prof']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nb_seances_validees = $result['nb_seances_validees'] ?? 0;
    
    // Calculer le montant total
    $montant_total = $nb_seances_validees * floatval($prof['salaire_par_seance']);
?>

<style>
    .professor-header {
        background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
        color: white;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        position: relative;
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 30px;
    }

    .professor-info {
        display: flex;
        align-items: flex-start;
        gap: 24px;
    }

    .professor-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid rgba(255,255,255,0.3);
        transition: transform 0.3s ease;
        cursor: pointer;
    }

    .professor-avatar:hover {
        transform: scale(1.05);
        border-color: rgba(255,255,255,0.5);
    }

    .professor-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .professor-details {
        flex-grow: 1;
    }

    .professor-name {
        font-size: 1.5em;
        margin: 0 0 4px 0;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .professor-title {
        font-size: 0.95em;
        margin: 0;
        opacity: 0.9;
        font-weight: 400;
    }

    .professor-stats {
        display: flex;
        gap: 16px;
        margin-top: 12px;
    }

    .stat-box {
        background-color: rgba(255,255,255,0.1);
        padding: 8px 16px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9em;
        transition: background-color 0.3s ease;
    }

    .stat-box:hover {
        background-color: rgba(255,255,255,0.2);
    }

    .stat-box i {
        font-size: 1em;
    }

    .nav-menu {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .nav-link {
        color: white;
        text-decoration: none;
        padding: 10px 18px;
        border-radius: 8px;
        background-color: rgba(255,255,255,0.1);
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        font-size: 0.95em;
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(5px);
    }

    .nav-link:hover {
        background-color: rgba(255,255,255,0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: rgba(255,255,255,0.2);
    }

    .nav-link.active {
        background-color: white;
        color: #4CAF50;
        font-weight: 600;
        border-color: white;
    }

    .nav-link.active:hover {
        background-color: #f8f9fa;
    }

    .nav-link i {
        font-size: 1.1em;
        transition: transform 0.3s ease;
    }

    .nav-link:hover i {
        transform: scale(1.1);
    }

    .profile-button {
        background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        border: 1px solid rgba(255,255,255,0.2);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .profile-button:hover {
        background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border-color: rgba(255,255,255,0.3);
    }

    .logout-button {
        background: rgba(255,59,48,0.1);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        font-weight: 500;
        backdrop-filter: blur(5px);
    }

    .logout-button:hover {
        background: rgba(255,59,48,0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(255,59,48,0.2);
        border-color: rgba(255,255,255,0.3);
    }

    .logout-button i, .profile-button i {
        font-size: 1.1em;
        transition: transform 0.3s ease;
    }

    .logout-button:hover i, .profile-button:hover i {
        transform: scale(1.1);
    }

    .professor-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .card-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: rgba(76,175,80,0.1);
        border-radius: 6px;
    }

    .info-item i {
        color: #4CAF50;
        font-size: 1.2em;
    }

    .info-label {
        font-size: 0.85em;
        color: #666;
    }

    .info-value {
        font-size: 0.95em;
        color: #333;
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .header-container {
            flex-direction: column;
            gap: 20px;
        }

        .professor-info {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .professor-stats {
            justify-content: center;
            flex-wrap: wrap;
        }

        .nav-menu {
            flex-direction: column;
            width: 100%;
            gap: 10px;
        }

        .nav-link, .profile-button, .logout-button {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
        }

        .card-info {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="professor-header">
    <div class="header-container">
        <div class="professor-info">
            <div class="professor-avatar" onclick="window.location.href='mon_profile.php'">
                <img src="<?php echo !empty($prof['photo_profil']) ? '../' . $prof['photo_profil'] : '../assets/images/default-avatar.jpg'; ?>" alt="Photo de profil">
            </div>
            <div class="professor-details">
                <h1 class="professor-name">
                    Pr. <?php echo htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']); ?>
                </h1>
                <p class="professor-title"><?php echo htmlspecialchars($prof['grade_actuel']); ?></p>
                <div class="professor-stats">
                    <div class="stat-box">
                        <i class="fas fa-money-bill-wave"></i>
                        <?php echo number_format($montant_total, 2); ?> DH
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $nb_seances_validees; ?> séances validées
                    </div>
                </div>
            </div>
        </div>
        <nav class="nav-menu">
            <a href="professor_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'professor_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Tableau de bord
            </a>
            <a href="reclamer_seance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reclamer_seance.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Réclamations
            </a>
            <a href="mon_profile.php" class="profile-button">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="../logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </nav>
    </div>
</div>

<?php
} catch (Exception $e) {
    echo "Une erreur est survenue : " . $e->getMessage();
}
?> 