<?php
// auth.php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Requête simple pour récupérer l'utilisateur et son rôle
    $stmt = $pdo->prepare("
        SELECT u.id_user, u.username, r.nom_role 
        FROM Utilisateur u 
        JOIN Role r ON u.id_role = r.id_role 
        WHERE u.username = ? AND u.mot_de_passe = ?
    ");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Debug
        error_log("Authentification réussie pour: " . $user['username'] . " avec rôle: " . $user['nom_role']);
        
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['nom_role'];
        
        // Redirection selon le rôle
        switch($user['nom_role']) {
            case 'admin':
                error_log("Redirection vers admin_dashboard");
                header("Location: admin/admin_dashboard.php");
                break;
            case 'professeur':
                error_log("Redirection vers professor_dashboard");
                header("Location: professor/professor_dashboard.php");
                break;
            case 'financier':
                error_log("Redirection vers finance_dashboard");
                header("Location: finance/finance_dashboard.php");
                break;
            default:
                error_log("Rôle inconnu: " . $user['nom_role']);
                header("Location: index.php?error=role_unknown");
            exit;
        }
    } else {
        header("Location: index.php?error=invalid");
        exit;
    }
}
?>