<?php
// debug.php - Fichier pour diagnostiquer les problèmes de session
session_start();
require 'config.php';

echo "<h2>Debug des sessions et données</h2>";

echo "<h3>Informations de session :</h3>";
if (isset($_SESSION['user_id'])) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Username: " . $_SESSION['username'] . "<br>";
    echo "Role: '" . $_SESSION['role'] . "'<br>";
    echo "Longueur du rôle: " . strlen($_SESSION['role']) . "<br>";
} else {
    echo "Aucune session active<br>";
}

echo "<h3>Données en base de données :</h3>";
try {
    $stmt = $pdo->query("
        SELECT u.id_user, u.username, u.mot_de_passe, r.nom_role, u.id_role
        FROM Utilisateur u 
        JOIN Role r ON u.id_role = r.id_role
        ORDER BY u.id_user
    ");
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Role</th><th>Role ID</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['id_user'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['mot_de_passe'] . "</td>";
        echo "<td>'" . $row['nom_role'] . "' (longueur: " . strlen($row['nom_role']) . ")</td>";
        echo "<td>" . $row['id_role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}

echo "<h3>Test de connexion :</h3>";
echo '<form method="post" action="">
    <input type="text" name="test_username" placeholder="Username" value="prof">
    <input type="password" name="test_password" placeholder="Password" value="prof123">
    <button type="submit">Tester</button>
</form>';

if (isset($_POST['test_username'])) {
    $username = $_POST['test_username'];
    $password = $_POST['test_password'];
    
    $stmt = $pdo->prepare("
        SELECT u.id_user, u.username, u.mot_de_passe, r.nom_role 
        FROM Utilisateur u 
        JOIN Role r ON u.id_role = r.id_role 
        WHERE u.username = ? AND u.mot_de_passe = ?
    ");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<br><strong>Utilisateur trouvé :</strong><br>";
        echo "ID: " . $user['id_user'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Role: '" . $user['nom_role'] . "' (longueur: " . strlen($user['nom_role']) . ")<br>";
        echo "Caractères ASCII: ";
        for ($i = 0; $i < strlen($user['nom_role']); $i++) {
            echo ord($user['nom_role'][$i]) . " ";
        }
    } else {
        echo "<br><strong>Aucun utilisateur trouvé</strong>";
    }
}
?>

<style>
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
form { margin: 10px 0; }
input { margin: 5px; padding: 5px; }
</style>