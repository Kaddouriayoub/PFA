<?php
require_once '../config.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

try {
    // Récupérer les informations de base du professeur
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            g.nom_grade,
            g.salaire_par_seance
        FROM professeur p 
        INNER JOIN utilisateur u ON p.id_user = u.id_user 
        LEFT JOIN grade g ON p.id_grade = g.id_grade
        WHERE u.id_user = ?
    ");
    $stmt->execute([$user_id]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prof) {
        throw new Exception("Professeur non trouvé");
    }

    // Si le professeur n'a pas de grade, on vérifie dans la table grade
    if (!isset($prof['nom_grade']) || empty($prof['nom_grade'])) {
        // Récupérer tous les grades disponibles
        $stmt = $pdo->prepare("SELECT * FROM grade ORDER BY id_grade");
        $stmt->execute();
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($grades)) {
            // Mettre à jour le grade du professeur avec le premier grade disponible
            $stmt = $pdo->prepare("UPDATE professeur SET id_grade = ? WHERE id_prof = ?");
            $stmt->execute([$grades[0]['id_grade'], $prof['id_prof']]);
            
            // Mettre à jour les informations du professeur
            $prof['nom_grade'] = $grades[0]['nom_grade'];
            $prof['salaire_par_seance'] = $grades[0]['salaire_par_seance'];
            $prof['id_grade'] = $grades[0]['id_grade'];
        } else {
            $prof['nom_grade'] = 'Non défini';
            $prof['salaire_par_seance'] = 0.00;
        }
    }

    // S'assurer que le salaire est défini et synchronisé avec la table grade
    if (!isset($prof['salaire_par_seance']) || $prof['salaire_par_seance'] === null) {
        if (isset($prof['id_grade'])) {
            // Récupérer le salaire depuis la table grade
            $stmt = $pdo->prepare("SELECT salaire_par_seance FROM grade WHERE id_grade = ?");
            $stmt->execute([$prof['id_grade']]);
            $grade = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($grade) {
                $prof['salaire_par_seance'] = $grade['salaire_par_seance'];
                
                // Mettre à jour le salaire dans la table professeur
                $stmt = $pdo->prepare("UPDATE professeur SET salaire_par_seance = ? WHERE id_prof = ?");
                $stmt->execute([$grade['salaire_par_seance'], $prof['id_prof']]);
            } else {
                $prof['salaire_par_seance'] = 0.00;
            }
        } else {
            $prof['salaire_par_seance'] = 0.00;
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Traitement du formulaire de téléchargement de photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo_profil'])) {
    $file = $_FILES['photo_profil'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $error = null;

    if ($file['error'] === 0) {
        if (in_array($file['type'], $allowedTypes)) {
            if ($file['size'] <= $maxSize) {
                // Créer le dossier s'il n'existe pas
                $uploadDir = '../assets/images/professeurs/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Générer un nom de fichier unique
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'prof_' . $prof['id_prof'] . '_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;

                // Supprimer l'ancienne photo si elle existe
                if (!empty($prof['photo_profil'])) {
                    $oldPhotoPath = '../' . $prof['photo_profil'];
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }
                }

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    try {
                        // Mise à jour de la base de données avec le chemin relatif
                        $relativePath = 'assets/images/professeurs/' . $fileName;
                        $stmt = $pdo->prepare("UPDATE professeur SET photo_profil = ? WHERE id_prof = ?");
                        $stmt->execute([$relativePath, $prof['id_prof']]);

                        // Mettre à jour la variable $prof avec la nouvelle photo
                        $prof['photo_profil'] = $relativePath;
                        
                        // Redirection avec message de succès
                        header('Location: mon_profile.php?success=1');
                        exit();
                    } catch (Exception $e) {
                        $error = "Erreur lors de la mise à jour de la base de données : " . $e->getMessage();
                        // Si l'enregistrement en BD échoue, supprimer le fichier uploadé
                        if (file_exists($targetPath)) {
                            unlink($targetPath);
                        }
                    }
                } else {
                    $error = "Erreur lors du téléchargement du fichier.";
                }
            } else {
                $error = "Le fichier est trop volumineux. Taille maximum : 5MB.";
            }
        } else {
            $error = "Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.";
        }
    } else {
        $error = "Erreur lors du téléchargement.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - ENSIAS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #1a1a1a;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .profile-header {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .profile-content {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
        }
        .profile-photo-section {
            text-align: center;
        }
        .profile-photo-container {
            width: 250px;
            height: 250px;
            margin: 0 auto 20px;
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .profile-photo-large {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .profile-photo-container:hover .profile-photo-large {
            transform: scale(1.05);
        }
        .photo-upload-form {
            margin-top: 20px;
        }
        .photo-upload-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.2);
        }
        .photo-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }
        .info-sections {
            display: grid;
            gap: 30px;
        }
        .info-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .section-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title i {
            color: #4CAF50;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-color: #4CAF50;
        }
        .info-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .info-content {
            margin-top: 10px;
        }
        .info-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 1.1em;
            color: #2c3e50;
            font-weight: 600;
        }
        .error-message, .success-message {
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            font-weight: 500;
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
            .profile-photo-section {
                margin-bottom: 30px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .section-title {
                font-size: 1.3em;
            }
        }
        /* Style pour la section de développement de carrière */
        .career-section {
            margin-top: 30px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .career-timeline {
            position: relative;
            padding: 20px 0;
            margin-top: 20px;
        }
        
        .career-timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            width: 45%;
            position: relative;
            margin: 0 20px;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .timeline-content:hover {
            transform: translateY(-2px);
        }
        
        .timeline-marker {
            width: 20px;
            height: 20px;
            background: #f8f9fa;
            border-radius: 50%;
            border: 2px solid #e0e0e0;
            z-index: 1;
        }
        
        .timeline-date {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .timeline-grade {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Style pour le grade actuel */
        .timeline-content.current-grade {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .timeline-content.current-grade .timeline-date,
        .timeline-content.current-grade .timeline-grade {
            color: white;
        }
        
        .timeline-marker.current-marker {
            background: #4CAF50;
            border-color: white;
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.2);
        }

        .timeline-content.current-grade:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .timeline-content i {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'professor_header.php'; ?>
    
    <div class="container">
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-content">
                <div class="profile-photo-section">
                    <div class="profile-photo-container">
                        <img src="<?php 
                            if (!empty($prof['photo_profil'])) {
                                echo '../' . htmlspecialchars($prof['photo_profil']);
                            } else {
                                echo '../assets/images/default-profile.png';
                            }
                        ?>" 
                        alt="Photo de profil" 
                        class="profile-photo-large">
                    </div>
                    <form class="photo-upload-form" method="POST" enctype="multipart/form-data">
                        <input type="file" name="photo_profil" accept="image/*" style="display: none;" id="photo-input">
                        <button type="button" class="photo-upload-btn" onclick="document.getElementById('photo-input').click()">
                            <i class="fas fa-camera"></i> Changer la photo
                        </button>
                        <button type="submit" class="photo-upload-btn" style="display: none;" id="submit-btn">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>

                    <?php if (isset($error)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            Photo de profil mise à jour avec succès!
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-sections">
                    <div class="info-section">
                        <h2 class="section-title">
                            <i class="fas fa-user-circle"></i>
                            Informations Personnelles
                        </h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Nom complet</div>
                                    <div class="info-value"><?php echo htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Nom d'utilisateur</div>
                                    <div class="info-value"><?php echo htmlspecialchars($prof['username']); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Type de professeur</div>
                                    <div class="info-value"><?php echo htmlspecialchars($prof['type_prof']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h2 class="section-title">
                            <i class="fas fa-graduation-cap"></i>
                            Informations Professionnelles
                        </h2>
                        <div class="info-grid">
                            <?php
                            // Récupérer le grade actuel du professeur connecté
                            $stmt = $pdo->prepare("
                                SELECT 
                                    p.id_prof,
                                    g.id_grade,
                                    g.nom_grade,
                                    g.salaire_par_seance
                                FROM professeur p
                                LEFT JOIN grade g ON p.id_grade = g.id_grade
                                WHERE p.id_user = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $profInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Récupérer l'historique des développements professionnels
                            $stmt = $pdo->prepare("
                                SELECT 
                                    dp.id_prof,
                                    dp.date_debut_grade,
                                    dp.date_fin_grade,
                                    g.nom_grade,
                                    g.salaire_par_seance
                                FROM developpement_prof dp
                                LEFT JOIN grade g ON dp.id_grade = g.id_grade
                                WHERE dp.id_prof = ? 
                                ORDER BY dp.date_debut_grade DESC
                            ");
                            $stmt->execute([$profInfo['id_prof']]);
                            $devProf = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            // Utiliser les informations du grade actuel
                            $grade = $profInfo['nom_grade'] ?? 'Non défini';
                            $salaire = $profInfo['salaire_par_seance'] ?? 0.00;
                            ?>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Grade</div>
                                    <div class="info-value"><?php echo htmlspecialchars($grade); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Salaire par séance</div>
                                    <div class="info-value"><?php echo number_format($salaire, 2); ?> DH</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section pour le développement de carrière -->
        <div class="career-section">
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i>
                Évolution de Carrière
            </h2>
            <div class="career-timeline">
                <?php
                // Récupérer l'historique des grades du professeur
                $stmt = $pdo->prepare("
                    SELECT 
                        g.nom_grade as grade,
                        dp.date_debut_grade,
                        dp.date_fin_grade
                    FROM developpement_prof dp
                    LEFT JOIN grade g ON dp.id_grade = g.id_grade
                    WHERE dp.id_prof = ?
                    ORDER BY dp.date_debut_grade DESC
                ");
                $stmt->execute([$prof['id_prof']]);
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($grades as $grade):
                    // Un grade est considéré comme actuel uniquement s'il n'a pas de date de fin
                    // ET si sa date de début est la plus récente
                    $is_current = is_null($grade['date_fin_grade']) && 
                                strtotime($grade['date_debut_grade']) === strtotime($grades[0]['date_debut_grade']);
                ?>
                    <div class="timeline-item">
                        <div class="timeline-content <?php echo $is_current ? 'current-grade' : ''; ?>">
                            <div class="timeline-grade">
                                <i class="fas fa-star"></i> 
                                <?php echo htmlspecialchars($grade['grade']); ?>
                            </div>
                            <div class="timeline-date">
                                <i class="fas fa-calendar-alt"></i>
                                Du <?php echo date('d/m/Y', strtotime($grade['date_debut_grade'])); ?>
                                <?php if ($grade['date_fin_grade']): ?>
                                    au <?php echo date('d/m/Y', strtotime($grade['date_fin_grade'])); ?>
                                <?php else: ?>
                                    à aujourd'hui
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="timeline-marker <?php echo $is_current ? 'current-marker' : ''; ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('photo-input').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            if (this.files[0].size > 5 * 1024 * 1024) {
                alert('Le fichier est trop volumineux. Taille maximum : 5MB');
                this.value = '';
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(this.files[0].type)) {
                alert('Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.');
                this.value = '';
                return;
            }

            document.getElementById('submit-btn').style.display = 'block';
            
            var reader = new FileReader();
            reader.onload = function(e) {
                document.querySelector('.profile-photo-large').src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
    </script>
</body>
</html> 