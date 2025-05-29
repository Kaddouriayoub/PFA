<?php
//get_payslip_details.php
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
    die(json_encode(['success' => false, 'message' => 'Non authentifié']));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $fiche_id = intval($_GET['id']);
    
    // Récupérer les détails de la fiche de salaire
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
        die(json_encode(['success' => false, 'message' => 'Fiche de salaire non trouvée']));
    }
    
    echo json_encode(['success' => true, 'fiche' => $payslip, 'professeur' => ['nom' => $payslip['nom'], 'prenom' => $payslip['prenom']]]);
} else {
    die(json_encode(['success' => false, 'message' => 'Requête invalide']));
}
?>
