<?php
// check_date.php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ensias_payment";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get the date from GET parameter
$date = isset($_GET['date']) ? $_GET['date'] : '';
$annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : null;

if (empty($date)) {
    echo json_encode(['blocked' => false]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    // Check if the date is blocked (holiday or strike)
    $query = "SELECT j.type_jour, CONCAT(a.niveau, ' - ', f.nom_filiere, ' (', a.annee_universitaire, ')') as annee_info 
              FROM Jour_Ferie_Greve j
              JOIN Annee a ON j.id_annee = a.id_annee
              JOIN Filiere f ON a.id_filiere = f.id_filiere
              WHERE j.date = ?";
    $params = [$date];
    
    // If specific year is provided, filter by it
    if ($annee_id) {
        $query .= " AND j.id_annee = ?";
        $params[] = $annee_id;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($results)) {
        // If multiple results (same date in different academic years), return all
        $blockedInfo = [];
        foreach ($results as $result) {
            $blockedInfo[] = [
                'type' => $result['type_jour'],
                'annee_info' => $result['annee_info'],
                'message' => $result['type_jour'] === 'ferie' ? 'jour férié' : 'jour de grève'
            ];
        }
        
        echo json_encode([
            'blocked' => true,
            'info' => $blockedInfo,
            'count' => count($blockedInfo)
        ]);
    } else {
        echo json_encode(['blocked' => false]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error checking date: ' . $e->getMessage()]);
}
?>