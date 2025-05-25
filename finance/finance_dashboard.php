<?php
// finance_dashboard.php
session_start();

// Vérification simple et debug
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SESSION['role'] != 'financier') {
    // Debug: afficher les informations avant de rediriger
    // echo "Rôle actuel: '" . $_SESSION['role'] . "' - Attendu: 'financier'"; exit;
    header("Location: index.php?error=access_denied");
    exit;
}
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .welcome {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            float: right;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
        .debug {
            background: #f39c12;
            color: white;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Dashboard Financier</h1>
            <a href="../logout.php" class="logout-btn">Déconnexion</a>
            <div style="clear: both;"></div>
        </div>
    </div>
    
    <div class="container">
        <div class="debug">
            Session ID: <?= $_SESSION['user_id'] ?> | Username: <?= $_SESSION['username'] ?> | Role: <?= $_SESSION['role'] ?>
        </div>
        
        <div class="welcome">
            <h2>Bienvenue Financier <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
            <p>Gérez les aspects financiers et les paiements des professeurs.</p>
            
            <h3>Fonctionnalités disponibles :</h3>
            <ul>
                <li>Calculer les paiements</li>
                <li>Générer les bulletins de paie</li>
                <li>Consulter les rapports financiers</li>
                <li>Valider les séances</li>
                <li>Gestion des vacataires</li>
            </ul>
        </div>
    </div>
</body>
</html>