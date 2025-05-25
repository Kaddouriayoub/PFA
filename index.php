<?php
// index.php
session_start();

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/admin_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] == 'professeur') {
        header("Location: professor/professor_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] == 'financier') {
        header("Location: finance/finance_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Connexion - ENSIAS Payment</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 350px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border: none;
            width: 100%;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        button:hover {
            opacity: 0.9;
        }
        .test-accounts {
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 12px;
        }
        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #fdf2f2;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Connexion ENSIAS</h2>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error">
                <?php 
                if ($_GET['error'] == 'invalid') {
                    echo "Identifiants incorrects";
                } elseif ($_GET['error'] == 'role_unknown') {
                    echo "Rôle non reconnu";
                } else {
                    echo "Erreur de connexion";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <form action="auth.php" method="post">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
        
        <div class="test-accounts">
            <strong>Identifiants de test:</strong><br>
            Admin: admin / admin123<br>
            Professeur: prof / prof123<br>
            Financier: financier / finance123
        </div>
    </div>
</body>
</html>