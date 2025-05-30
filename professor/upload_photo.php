<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if (!isset($_FILES['photo_profil'])) {
    echo json_encode(['success' => false, 'message' => 'Aucune photo n\'a été envoyée']);
    exit();
}

try {
    $file = $_FILES['photo_profil'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Vérifier les erreurs de téléchargement
    if ($fileError !== 0) {
        throw new Exception('Erreur lors du téléchargement du fichier');
    }
    
    // Vérifier la taille du fichier (max 5MB)
    if ($fileSize > 5000000) {
        throw new Exception('Le fichier est trop volumineux (max 5MB)');
    }
    
    // Vérifier le type de fichier
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = mime_content_type($fileTmpName);
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé (JPG, PNG et GIF uniquement)');
    }
    
    // Créer le dossier de destination s'il n'existe pas
    $uploadDir = '../uploads/profile_photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $newFileName;
    
    // Déplacer le fichier
    if (!move_uploaded_file($fileTmpName, $destination)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }
    
    // Mettre à jour la base de données
    $relativePath = 'uploads/profile_photos/' . $newFileName;
    $stmt = $pdo->prepare("
        UPDATE professeur p 
        INNER JOIN utilisateur u ON p.id_user = u.id_user 
        SET p.photo_profil = ? 
        WHERE u.id_user = ?
    ");
    
    if (!$stmt->execute([$relativePath, $_SESSION['user_id']])) {
        // Supprimer le fichier si la mise à jour de la base de données échoue
        unlink($destination);
        throw new Exception('Erreur lors de la mise à jour de la base de données');
    }
    
    // Supprimer l'ancienne photo si elle existe
    $stmt = $pdo->prepare("SELECT photo_profil FROM professeur p INNER JOIN utilisateur u ON p.id_user = u.id_user WHERE u.id_user = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $oldPhoto = $stmt->fetchColumn();
    
    if ($oldPhoto && $oldPhoto !== $relativePath && file_exists('../' . $oldPhoto)) {
        unlink('../' . $oldPhoto);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Photo de profil mise à jour avec succès',
        'photo_path' => $relativePath
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 