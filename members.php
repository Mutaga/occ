<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Récupérer les données pour les dropdowns
try {
    $db = getDBConnection();
    $formations = $db->query("SELECT id, nom, promotion FROM formations")->fetchAll(PDO::FETCH_ASSOC);
    $oikos = $db->query("SELECT id, nom FROM oikos")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . $e->getMessage();
    $formations = [];
    $oikos = [];
}

// Initialiser les messages
$error = null;
$success = null;

// Liste des valeurs valides
$valid_departements = ['Media', 'Comptabilité', 'Sécurité', 'Chorale', 'SundaySchool', 'Protocole', 'Pastorat', 'Diaconat'];
$valid_sexes = ['Masculin', 'Féminin'];

// Convertir DD/MM/YYYY en YYYY-MM-DD
function convertToMySQLDate($date) {
    if (empty($date)) return null;
    $parts = explode('/', $date);
    if (count($parts) !== 3 || !checkdate($parts[1], $parts[0], $parts[2])) {
        throw new Exception("Format de date invalide : $date. Attendu : JJ/MM/AAAA");
    }
    return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
}

// Ajout d'un membre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre') && !isset($_POST['action'])) {
    try {
        $id = generateMemberId();
        $nom = trim($_POST['nom'] ?? '') ?: null;
        $prenom = trim($_POST['prenom'] ?? '') ?: null;
        $sexe = trim($_POST['sexe'] ?? '') ?: null;
        $date_naissance = convertToMySQLDate($_POST['date_naissance'] ?? '');
        $province_naissance = trim($_POST['province_naissance'] ?? '') ?: null;
        $pays_naissance = trim($_POST['pays_naissance'] ?? '') ?: null;
        $telephone = trim($_POST['telephone'] ?? '') ?: null;
        $email = trim($_POST['email'] ?? '') ?: null;
        $residence = trim($_POST['residence'] ?? '') ?: null;
        $profession = trim($_POST['profession'] ?? '') ?: null;
        $etat_civil = trim($_POST['etat_civil'] ?? '') ?: null;
        $conjoint_nom_prenom = trim($_POST['conjoint_nom_prenom'] ?? '') ?: null;
        $date_nouvelle_naissance = convertToMySQLDate($_POST['date_nouvelle_naissance'] ?? '');
        $eglise_nouvelle_naissance = trim($_POST['eglise_nouvelle_naissance'] ?? '') ?: null;
        $lieu_nouvelle_naissance = trim($_POST['lieu_nouvelle_naissance'] ?? '') ?: null;
        $formation_id = !empty($_POST['formation_id']) ? (int)$_POST['formation_id'] : null;
        $oikos_id = !empty($_POST['oikos_id']) ? (int)$_POST['oikos_id'] : null;
        $departement = trim($_POST['departement'] ?? '') ?: null;
        $event_type = trim($_POST['event_type'] ?? '') ?: null;
        $event_date = convertToMySQLDate($_POST['event_date'] ?? '');

        // Validation
        if (empty($nom) || empty($prenom) || empty($sexe) || empty($date_naissance) || empty($etat_civil)) {
            throw new Exception("Les champs Nom, Prénom, Sexe, Date de Naissance et État Civil sont requis.");
        }
        if ($sexe !== null && !in_array($sexe, $valid_sexes)) {
            throw new Exception("Valeur invalide pour Sexe.");
        }
        if ($departement !== null && !in_array($departement, $valid_departements)) {
            throw new Exception("Valeur invalide pour Département.");
        }
        if ($event_type && empty($event_date)) {
            throw new Exception("La date de l'événement est requise.");
        }

        // Gestion du PDF
        $fiche_membre = null;
        if (isset($_FILES['fiche_membre']) && $_FILES['fiche_membre']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (!is_writable($upload_dir)) throw new Exception("Dossier uploads/ non accessible.");
            $fiche_membre = $upload_dir . "{$id}_{$nom}.pdf";
            if (!move_uploaded_file($_FILES['fiche_membre']['tmp_name'], $fiche_membre)) {
                throw new Exception("Échec du téléchargement du PDF.");
            }
        }

        // Insertion
        $stmt = $db->prepare("
            INSERT INTO members (
                id, nom, prenom, sexe, date_naissance, province_naissance, pays_naissance, 
                telephone, email, residence, profession, etat_civil, conjoint_nom_prenom, 
                date_nouvelle_naissance, eglise_nouvelle_naissance, lieu_nouvelle_naissance, 
                formation_id, oikos_id, departement, fiche_membre
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id, $nom, $prenom, $sexe, $date_naissance, $province_naissance, $pays_naissance,
            $telephone, $email, $residence, $profession, $etat_civil, $conjoint_nom_prenom,
            $date_nouvelle_naissance, $eglise_nouvelle_naissance, $lieu_nouvelle_naissance,
            $formation_id, $oikos_id, $departement, $fiche_membre
        ]);

        // Événements
        if ($event_type && $event_date) {
            $stmt = $db->prepare("INSERT INTO events (member_id, type, date_evenement) VALUES (?, ?, ?)");
            $stmt->execute([$id, $event_type, $event_date]);
        }

        logAction($_SESSION['user_id'], "Ajout membre: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        $success = "Membre ajouté avec succès (ID: $id).";
    } catch (Exception $e) {
        $error = "Erreur lors de l'ajout : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur ajout membre: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Mise à jour d'un membre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre') && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = trim($_POST['id'] ?? '');
        $nom = trim($_POST['nom'] ?? '') ?: null;
        $prenom = trim($_POST['prenom'] ?? '') ?: null;
        $sexe = trim($_POST['sexe'] ?? '') ?: null;
        $date_naissance = convertToMySQLDate($_POST['date_naissance'] ?? '');
        $province_naissance = trim($_POST['province_naissance'] ?? '') ?: null;
        $pays_naissance = trim($_POST['pays_naissance'] ?? '') ?: null;
        $telephone = trim($_POST['telephone'] ?? '') ?: null;
        $email = trim($_POST['email'] ?? '') ?: null;
        $residence = trim($_POST['residence'] ?? '') ?: null;
        $profession = trim($_POST['profession'] ?? '') ?: null;
        $etat_civil = trim($_POST['etat_civil'] ?? '') ?: null;
        $conjoint_nom_prenom = trim($_POST['conjoint_nom_prenom'] ?? '') ?: null;
        $date_nouvelle_naissance = convertToMySQLDate($_POST['date_nouvelle_naissance'] ?? '');
        $eglise_nouvelle_naissance = trim($_POST['eglise_nouvelle_naissance'] ?? '') ?: null;
        $lieu_nouvelle_naissance = trim($_POST['lieu_nouvelle_naissance'] ?? '') ?: null;
        $formation_id = !empty($_POST['formation_id']) ? (int)$_POST['formation_id'] : null;
        $oikos_id = !empty($_POST['oikos_id']) ? (int)$_POST['oikos_id'] : null;
        $departement = trim($_POST['departement'] ?? '') ?: null;
        $event_type = trim($_POST['event_type'] ?? '') ?: null;
        $event_date = convertToMySQLDate($_POST['event_date'] ?? '');

        // Validation
        if (empty($id) || empty($nom) || empty($prenom) || empty($sexe) || empty($date_naissance) || empty($etat_civil)) {
            throw new Exception("Champs requis manquants.");
        }
        if ($sexe !== null && !in_array($sexe, $valid_sexes)) {
            throw new Exception("Valeur invalide pour Sexe.");
        }
        if ($departement !== null && !in_array($departement, $valid_departements)) {
            throw new Exception("Valeur invalide pour Département.");
        }
        if ($event_type && empty($event_date)) {
            throw new Exception("Date de l'événement requise.");
        }

        // Gestion du PDF
        $existing_fiche = $db->prepare("SELECT fiche_membre FROM members WHERE id = ?");
        $existing_fiche->execute([$id]);
        $current_fiche = $existing_fiche->fetchColumn();
        $fiche_membre = $current_fiche;

        if (isset($_FILES['fiche_membre']) && $_FILES['fiche_membre']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (!is_writable($upload_dir)) throw new Exception("Dossier uploads/ non accessible.");
            $fiche_membre = $upload_dir . "{$id}_{$nom}.pdf";
            if (!move_uploaded_file($_FILES['fiche_membre']['tmp_name'], $fiche_membre)) {
                throw new Exception("Échec du téléchargement du PDF.");
            }
            if ($current_fiche && file_exists($current_fiche)) unlink($current_fiche);
        }

        // Mise à jour
        $stmt = $db->prepare("
            UPDATE members SET
                nom = ?, prenom = ?, sexe = ?, date_naissance = ?, province_naissance = ?, pays_naissance = ?, 
                telephone = ?, email = ?, residence = ?, profession = ?, etat_civil = ?, conjoint_nom_prenom = ?, 
                date_nouvelle_naissance = ?, eglise_nouvelle_naissance = ?, lieu_nouvelle_naissance = ?, 
                formation_id = ?, oikos_id = ?, departement = ?, fiche_membre = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nom, $prenom, $sexe, $date_naissance, $province_naissance, $pays_naissance,
            $telephone, $email, $residence, $profession, $etat_civil, $conjoint_nom_prenom,
            $date_nouvelle_naissance, $eglise_nouvelle_naissance, $lieu_nouvelle_naissance,
            $formation_id, $oikos_id, $departement, $fiche_membre, $id
        ]);

        // Événements
        $stmt = $db->prepare("DELETE FROM events WHERE member_id = ?");
        $stmt->execute([$id]);
        if ($event_type && $event_date) {
            $stmt = $db->prepare("INSERT INTO events (member_id, type, date_evenement) VALUES (?, ?, ?)");
            $stmt->execute([$id, $event_type, $event_date]);
        }

        logAction($_SESSION['user_id'], "Mise à jour membre: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        $success = "Membre mis à jour avec succès (ID: $id).";
    } catch (Exception $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur mise à jour membre: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SESSION['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        $logFile = 'error_log.txt';
        file_put_contents($logFile, "Début suppression membre: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        $id = $_POST['member_id'] ?? '';
        if (empty($id)) throw new Exception("ID du membre manquant.");
        file_put_contents($logFile, "ID membre: $id\n", FILE_APPEND);

        $db->beginTransaction();

        $stmt = $db->prepare("DELETE FROM events WHERE member_id = ?");
        $stmt->execute([$id]);
        file_put_contents($logFile, "Événements supprimés\n", FILE_APPEND);

        $stmt = $db->prepare("SELECT fiche_membre FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $fiche_membre = $stmt->fetchColumn();
        if ($fiche_membre && file_exists($fiche_membre)) {
            if (is_writable($fiche_membre)) {
                if (!unlink($fiche_membre)) throw new Exception("Impossible de supprimer le PDF.");
                file_put_contents($logFile, "PDF supprimé: $fiche_membre\n", FILE_APPEND);
            } else {
                throw new Exception("PDF non accessible.");
            }
        }

        $stmt = $db->prepare("DELETE FROM members WHERE id = ?");
        $stmt->execute([$id]);
        file_put_contents($logFile, "Membre supprimé\n", FILE_APPEND);

        $db->commit();
        logAction($_SESSION['user_id'], "Suppression membre: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Membre supprimé (ID: $id)."]);
    } catch (Exception $e) {
        $db->rollBack();
        file_put_contents($logFile, "Erreur: " . $e->getMessage() . "\n", FILE_APPEND);
        logAction($_SESSION['user_id'], "Erreur suppression: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Recherche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    try {
        $search_term = trim($_POST['search_term'] ?? '');
        $query = "
            SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom 
            FROM members m 
            LEFT JOIN formations f ON m.formation_id = f.id 
            LEFT JOIN oikos o ON m.oikos_id = o.id
            WHERE m.id LIKE :term 
               OR m.nom LIKE :term 
               OR m.prenom LIKE :term 
               OR m.departement LIKE :term
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([':term' => "%$search_term%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'results' => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur recherche : " . $e->getMessage()]);
    }
    exit;
}

// Récupération pour modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_member' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre')) {
    try {
        $id = $_POST['member_id'] ?? '';
        if (empty($id)) throw new Exception("ID manquant.");
        $stmt = $db->prepare("
            SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom, 
                   e.type AS event_type, e.date_evenement 
            FROM members m 
            LEFT JOIN formations f ON m.formation_id = f.id 
            LEFT JOIN oikos o ON m.oikos_id = o.id 
            LEFT JOIN events e ON m.id = e.member_id 
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($member) {
            if ($member['date_naissance']) $member['date_naissance'] = date('d/m/Y', strtotime($member['date_naissance']));
            if ($member['date_nouvelle_naissance']) $member['date_nouvelle_naissance'] = date('d/m/Y', strtotime($member['date_nouvelle_naissance']));
            if ($member['date_evenement']) $member['date_evenement'] = date('d/m/Y', strtotime($member['date_evenement']));
            echo json_encode(['success' => true, 'member' => $member]);
        } else {
            throw new Exception("Membre non trouvé : $id");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Export CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre')) {
    try {
        $search_term = trim($_POST['search_term'] ?? '');
        $query = "
            SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom 
            FROM members m 
            LEFT JOIN formations f ON m.formation_id = f.id 
            LEFT JOIN oikos o ON m.oikos_id = o.id
        ";
        if ($search_term) {
            $query .= " WHERE m.id LIKE ? OR m.nom LIKE ? OR m.prenom LIKE ? OR m.departement LIKE ?";
            $stmt = $db->prepare($query);
            $stmt->execute(["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"]);
        } else {
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="members_list.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Nom', 'Prénom', 'Sexe', 'Date de Naissance', 'Département', 'Téléphone', 'Email', 'Province de Naissance', 'Pays de Naissance', 'Résidence', 'Profession', 'État Civil', 'Conjoint']);
        foreach ($members as $member) {
            fputcsv($output, [
                $member['id'], $member['nom'], $member['prenom'], $member['sexe'] ?? '-', $member['date_naissance'] ?? '-',
                $member['departement'] ?? '-', $member['telephone'] ?? '-', $member['email'] ?? '-', $member['province_naissance'] ?? '-',
                $member['pays_naissance'] ?? '-', $member['residence'] ?? '-', $member['profession'] ?? '-', $member['etat_civil'] ?? '-',
                $member['conjoint_nom_prenom'] ?? '-'
            ]);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = "Erreur export CSV : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur export CSV: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Liste des membres
try {
    $members = $db->query("
        SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom 
        FROM members m 
        LEFT JOIN formations f ON m.formation_id = f.id 
        LEFT JOIN oikos o ON m.oikos_id = o.id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération membres : " . $e->getMessage();
    $members = [];
}

// Détails d'un membre
$view_member = null;
if (isset($_GET['view'])) {
    try {
        $stmt = $db->prepare("
            SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom, 
                   e.type AS event_type, e.date_evenement 
            FROM members m 
            LEFT JOIN formations f ON m.formation_id = f.id 
            LEFT JOIN oikos o ON m.oikos_id = o.id 
            LEFT JOIN events e ON m.id = e.member_id 
            WHERE m.id = ?
        ");
        $stmt->execute([$_GET['view']]);
        $view_member = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$view_member) $error = "Membre non trouvé : " . htmlspecialchars($_GET['view']);
    } catch (Exception $e) {
        $error = "Erreur récupération membre : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Membres</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Arial', sans-serif;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: #fff;
            height: 100vh;
            padding: 20px;
            position: fixed;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #495057;
            color: #fff;
        }
        .content {
            margin-left: 250px;
            padding: 30px;
        }
        .table {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #117a8b;
            border-color: #117a8b;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #c82333;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        #updateMemberModal .modal-body, #addMemberModal .modal-body {
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
        }
        #viewMemberModal .modal-content, #deleteConfirmModal .modal-content, #searchModal .modal-content {
            z-index: 1050;
        }
        #addMemberModal .modal-content, #updateMemberModal .modal-content {
            z-index: 1055;
        }
        #searchModal .modal-body {
            background-color: #fff;
            padding: 20px;
        }
        .search-bar {
            position: relative;
        }
        .search-bar input {
            padding-right: 40px;
        }
        .search-bar .clear-search {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 18px;
        }
        #search-results-table .btn-sm, #members-table .btn-sm {
            font-size: 12px;
            margin-right: 5px;
            padding: 4px 8px;
        }
        #search-results-table th:last-child, #search-results-table td:last-child,
        #members-table th:last-child, #members-table td:last-child {
            width: 200px;
            white-space: nowrap;
        }
        .datepicker {
            font-family: 'Arial', sans-serif;
            border-radius: 8px;
            border: 2px solid #007bff;
            padding: 10px;
            background-color: #fff;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar">
            <div class="text-center mb-4">
                <img src="assets/images/logo.jpeg" alt="OCC Logo" class="img-fluid" style="width: 100px;">
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="index.php" class="nav-link">Tableau de Bord</a></li>
                <li class="nav-item"><a href="members.php" class="nav-link active">Membres</a></li>
                <li class="nav-item"><a href="children.php" class="nav-link">Enfants</a></li>
                <li class="nav-item"><a href="formations.php" class="nav-link">Formations</a></li>
                <li class="nav-item"><a href="anniversaires.php" class="nav-link">Anniversaires</a></li>
                <li class="nav-item"><a href="oikos.php" class="nav-link">Oikos</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a href="logs.php" class="nav-link">Logs</a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="auth.php?logout" class="nav-link">Déconnexion</a></li>
            </ul>
        </div>
        <div class="content flex-grow-1">
            <h1 class="mb-4">Gestion des Membres</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addMemberModal">Ajouter un Membre</button>
                <?php endif; ?>
                <button class="btn btn-info" data-toggle="modal" data-target="#searchModal">Rechercher</button>
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                    <form action="members.php" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_csv">
                        <input type="hidden" name="search_term" id="export-csv-search-term" value="">
                        <button type="submit" class="btn btn-success">Exporter en CSV</button>
                    </form>
                <?php endif; ?>
            </div>

            <table id="members-table" class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nom et Prénom</th>
                        <th>Téléphone</th>
                        <th>Résidence</th>
                        <th>Département</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr id="member-<?php echo htmlspecialchars($member['id']); ?>">
                            <td><?php echo htmlspecialchars($member['id']); ?></td>
                            <td><?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($member['telephone'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($member['residence'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($member['departement'] ?: '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info view-member" data-member-id="<?php echo htmlspecialchars($member['id']); ?>">Voir</button>
                                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                                    <button class="btn btn-sm btn-warning update-member" data-member-id="<?php echo htmlspecialchars($member['id']); ?>">Modifier</button>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <button class="btn btn-sm btn-danger delete-member" data-member-id="<?php echo htmlspecialchars($member['id']); ?>" data-member-name="<?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?>">Supprimer</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Member Modal -->
            <div class="modal fade" id="addMemberModal" tabindex="-1" role="dialog" aria-labelledby="addMemberModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="addMemberModalLabel">Ajouter un Membre</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form id="add-member-form" action="members.php" method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nom">Nom *</label>
                                            <input type="text" class="form-control" id="nom" name="nom" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="prenom">Prénom *</label>
                                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="sexe">Sexe *</label>
                                            <select class="form-control" id="sexe" name="sexe" required>
                                                <option value="">Sélectionner...</option>
                                                <option value="Masculin">Masculin</option>
                                                <option value="Féminin">Féminin</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="date_naissance">Date de Naissance *</label>
                                            <input type="text" class="form-control datepicker" id="date_naissance" name="date_naissance" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="province_naissance">Province de Naissance</label>
                                            <input type="text" class="form-control" id="province_naissance" name="province_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="pays_naissance">Pays de Naissance</label>
                                            <input type="text" class="form-control" id="pays_naissance" name="pays_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="telephone">Téléphone</label>
                                            <input type="text" class="form-control" id="telephone" name="telephone">
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control" id="email" name="email">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="residence">Résidence</label>
                                            <input type="text" class="form-control" id="residence" name="residence">
                                        </div>
                                        <div class="form-group">
                                            <label for="profession">Profession</label>
                                            <input type="text" class="form-control" id="profession" name="profession">
                                        </div>
                                        <div class="form-group">
                                            <label for="etat_civil">État Civil *</label>
                                            <select class="form-control" id="etat_civil" name="etat_civil" required>
                                                <option value="">Sélectionner...</option>
                                                <option value="Célibataire">Célibataire</option>
                                                <option value="Marié(e)">Marié(e)</option>
                                                <option value="Divorcé(e)">Divorcé(e)</option>
                                                <option value="Veuf(ve)">Veuf(ve)</option>
                                            </select>
                                        </div>
                                        <div class="form-group" id="conjoint_field" style="display: none;">
                                            <label for="conjoint_nom_prenom">Nom et Prénom du Conjoint</label>
                                            <input type="text" class="form-control" id="conjoint_nom_prenom" name="conjoint_nom_prenom">
                                        </div>
                                        <div class="form-group">
                                            <label for="date_nouvelle_naissance">Date de Nouvelle Naissance</label>
                                            <input type="text" class="form-control datepicker" id="date_nouvelle_naissance" name="date_nouvelle_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="eglise_nouvelle_naissance">Église de Nouvelle Naissance</label>
                                            <input type="text" class="form-control" id="eglise_nouvelle_naissance" name="eglise_nouvelle_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="lieu_nouvelle_naissance">Lieu de Nouvelle Naissance</label>
                                            <input type="text" class="form-control" id="lieu_nouvelle_naissance" name="lieu_nouvelle_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="formation_id">Formation</label>
                                            <select class="form-control" id="formation_id" name="formation_id">
                                                <option value="">Aucune</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="oikos_id">Oikos</label>
                                            <select class="form-control" id="oikos_id" name="oikos_id">
                                                <option value="">Aucun</option>
                                                <?php foreach ($oikos as $oiko): ?>
                                                    <option value="<?php echo htmlspecialchars($oiko['id']); ?>">
                                                        <?php echo htmlspecialchars($oiko['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="departement">Département</label>
                                            <select class="form-control" id="departement" name="departement">
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($valid_departements as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                                        <?php echo htmlspecialchars($dept); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="event_type">Type d'Événement</label>
                                            <select class="form-control" id="event_type" name="event_type">
                                                <option value="">Aucun</option>
                                                <option value="Baptême">Baptême</option>
                                                <option value="Mariage">Mariage</option>
                                                <option value="Décès">Décès</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="event_date">Date de l'Événement</label>
                                            <input type="text" class="form-control datepicker" id="event_date" name="event_date">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="fiche_membre">Fiche Membre (PDF)</label>
                                            <input type="file" class="form-control-file" id="fiche_membre" name="fiche_membre" accept=".pdf">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary">Ajouter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Update Member Modal -->
            <div class="modal fade" id="updateMemberModal" tabindex="-1" role="dialog" aria-labelledby="updateMemberModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="updateMemberModalLabel">Modifier un Membre</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form id="update-member-form" action="members.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" id="update_id" name="id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_nom">Nom *</label>
                                            <input type="text" class="form-control" id="update_nom" name="nom" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_prenom">Prénom *</label>
                                            <input type="text" class="form-control" id="update_prenom" name="prenom" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_sexe">Sexe *</label>
                                            <select class="form-control" id="update_sexe" name="sexe" required>
                                                <option value="">Sélectionner...</option>
                                                <option value="Masculin">Masculin</option>
                                                <option value="Féminin">Féminin</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_date_naissance">Date de Naissance *</label>
                                            <input type="text" class="form-control datepicker" id="update_date_naissance" name="date_naissance" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_province_naissance">Province de Naissance</label>
                                            <input type="text" class="form-control" id="update_province_naissance" name="province_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_pays_naissance">Pays de Naissance</label>
                                            <input type="text" class="form-control" id="update_pays_naissance" name="pays_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_telephone">Téléphone</label>
                                            <input type="text" class="form-control" id="update_telephone" name="telephone">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_email">Email</label>
                                            <input type="email" class="form-control" id="update_email" name="email">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_residence">Résidence</label>
                                            <input type="text" class="form-control" id="update_residence" name="residence">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_profession">Profession</label>
                                            <input type="text" class="form-control" id="update_profession" name="profession">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_etat_civil">État Civil *</label>
                                            <select class="form-control" id="update_etat_civil" name="etat_civil" required>
                                                <option value="">Sélectionner...</option>
                                                <option value="Célibataire">Célibataire</option>
                                                <option value="Marié(e)">Marié(e)</option>
                                                <option value="Divorcé(e)">Divorcé(e)</option>
                                                <option value="Veuf(ve)">Veuf(ve)</option>
                                            </select>
                                        </div>
                                        <div class="form-group" id="update_conjoint_field" style="display: none;">
                                            <label for="update_conjoint_nom_prenom">Nom et Prénom du Conjoint</label>
                                            <input type="text" class="form-control" id="update_conjoint_nom_prenom" name="conjoint_nom_prenom">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_date_nouvelle_naissance">Date de Nouvelle Naissance</label>
                                            <input type="text" class="form-control datepicker" id="update_date_nouvelle_naissance" name="date_nouvelle_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_eglise_nouvelle_naissance">Église de Nouvelle Naissance</label>
                                            <input type="text" class="form-control" id="update_eglise_nouvelle_naissance" name="eglise_nouvelle_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_lieu_nouvelle_naissance">Lieu de Nouvelle Naissance</label>
                                            <input type="text" class="form-control" id="update_lieu_nouvelle_naissance" name="lieu_nouvelle_naissance">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_formation_id">Formation</label>
                                            <select class="form-control" id="update_formation_id" name="formation_id">
                                                <option value="">Aucune</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_oikos_id">Oikos</label>
                                            <select class="form-control" id="update_oikos_id" name="oikos_id">
                                                <option value="">Aucun</option>
                                                <?php foreach ($oikos as $oiko): ?>
                                                    <option value="<?php echo htmlspecialchars($oiko['id']); ?>">
                                                        <?php echo htmlspecialchars($oiko['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_departement">Département</label>
                                            <select class="form-control" id="update_departement" name="departement">
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($valid_departements as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                                        <?php echo htmlspecialchars($dept); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_event_type">Type d'Événement</label>
                                            <select class="form-control" id="update_event_type" name="event_type">
                                                <option value="">Aucun</option>
                                                <option value="Baptême">Baptême</option>
                                                <option value="Mariage">Mariage</option>
                                                <option value="Décès">Décès</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_event_date">Date de l'Événement</label>
                                            <input type="text" class="form-control datepicker" id="update_event_date" name="event_date">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_fiche_membre">Fiche Membre (PDF)</label>
                                            <input type="file" class="form-control-file" id="update_fiche_membre" name="fiche_membre" accept=".pdf">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- View Member Modal -->
            <div class="modal fade" id="viewMemberModal" tabindex="-1" role="dialog" aria-labelledby="viewMemberModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewMemberModalLabel">Détails du Membre</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if ($view_member): ?>
                                <h6>Informations Personnelles</h6>
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($view_member['id']); ?></p>
                                <p><strong>Nom:</strong> <?php echo htmlspecialchars($view_member['nom']); ?></p>
                                <p><strong>Prénom:</strong> <?php echo htmlspecialchars($view_member['prenom']); ?></p>
                                <p><strong>Sexe:</strong> <?php echo htmlspecialchars($view_member['sexe'] ?: '-'); ?></p>
                                <p><strong>Date de Naissance:</strong> <?php echo htmlspecialchars($view_member['date_naissance'] ? date('d/m/Y', strtotime($view_member['date_naissance'])) : '-'); ?></p>
                                <p><strong>Province de Naissance:</strong> <?php echo htmlspecialchars($view_member['province_naissance'] ?: '-'); ?></p>
                                <p><strong>Pays de Naissance:</strong> <?php echo htmlspecialchars($view_member['pays_naissance'] ?: '-'); ?></p>
                                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($view_member['telephone'] ?: '-'); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($view_member['email'] ?: '-'); ?></p>
                                <p><strong>Résidence:</strong> <?php echo htmlspecialchars($view_member['residence'] ?: '-'); ?></p>
                                <p><strong>Profession:</strong> <?php echo htmlspecialchars($view_member['profession'] ?: '-'); ?></p>
                                <p><strong>État Civil:</strong> <?php echo htmlspecialchars($view_member['etat_civil'] ?: '-'); ?></p>
                                <p><strong>Conjoint:</strong> <?php echo htmlspecialchars($view_member['conjoint_nom_prenom'] ?: '-'); ?></p>
                                <h6>Informations Spirituelles</h6>
                                <p><strong>Date de Nouvelle Naissance:</strong> <?php echo htmlspecialchars($view_member['date_nouvelle_naissance'] ? date('d/m/Y', strtotime($view_member['date_nouvelle_naissance'])) : '-'); ?></p>
                                <p><strong>Église de Nouvelle Naissance:</strong> <?php echo htmlspecialchars($view_member['eglise_nouvelle_naissance'] ?: '-'); ?></p>
                                <p><strong>Lieu de Nouvelle Naissance:</strong> <?php echo htmlspecialchars($view_member['lieu_nouvelle_naissance'] ?: '-'); ?></p>
                                <p><strong>Formation:</strong> <?php echo htmlspecialchars($view_member['formation_nom'] . ($view_member['promotion'] ? ' - ' . $view_member['promotion'] : '') ?: '-'); ?></p>
                                <p><strong>Oikos:</strong> <?php echo htmlspecialchars($view_member['oikos_nom'] ?: '-'); ?></p>
                                <p><strong>Département:</strong> <?php echo htmlspecialchars($view_member['departement'] ?: '-'); ?></p>
                                <h6>Événements</h6>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars($view_member['event_type'] ?: '-'); ?></p>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($view_member['date_evenement'] ? date('d/m/Y', strtotime($view_member['date_evenement'])) : '-'); ?></p>
                                <h6>Fiche Membre</h6>
                                <p>
                                    <?php if ($view_member['fiche_membre'] && file_exists($view_member['fiche_membre'])): ?>
                                        <a href="<?php echo htmlspecialchars($view_member['fiche_membre']); ?>" target="_blank">Télécharger le PDF</a>
                                    <?php else: ?>
                                        Aucun fichier PDF
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p>Aucun membre sélectionné.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <?php if ($view_member && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre')): ?>
                                <button class="btn btn-warning update-member" data-member-id="<?php echo htmlspecialchars($view_member['id']); ?>">Modifier</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteConfirmModalLabel">Confirmer la Suppression</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            Êtes-vous sûr de vouloir supprimer le membre <strong id="delete-member-name"></strong> ? Cette action est irréversible.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-danger" id="confirm-delete">Supprimer</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Modal -->
            <div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-labelledby="searchModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="searchModalLabel">Rechercher un Membre</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="search-bar mb-3">
                                <input type="text" class="form-control" id="search-input" placeholder="Rechercher par ID, Nom, Prénom ou Département...">
                                <span class="clear-search" id="clear-search" style="display: none;">&times;</span>
                            </div>
                            <div class="spinner-container" id="search-spinner" style="display: none; text-align: center;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Chargement...</span>
                                </div>
                            </div>
                            <div class="results-table">
                                <table id="search-results-table" class="table table-bordered table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom et Prénom</th>
                                            <th>Téléphone</th>
                                            <th>Résidence</th>
                                            <th>Département</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="search-results-body">
                                        <tr><td colspan="6">Entrez un terme de recherche pour commencer.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="assets/bootstrap/js/bootstrap.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.fr.min.js"></script>
            <script>
                $(document).ready(function() {
                    // Initialiser les datepickers
                    $('.datepicker').datepicker({
                        format: 'dd/mm/yyyy',
                        language: 'fr',
                        autoclose: true,
                        todayHighlight: true,
                        changeMonth: true,
                        changeYear: true,
                        yearRange: '1900:2100'
                    });

                    // Gestion du champ conjoint
                    $('#etat_civil, #update_etat_civil').on('change', function() {
                        var isMarried = this.value === 'Marié(e)';
                        $(this).closest('.modal-body').find('[id$="conjoint_field"]').toggle(isMarried);
                    });

                    // Bouton Voir
                    $(document).on('click', '.view-member', function(e) {
                        e.preventDefault();
                        var memberId = $(this).data('member-id');
                        window.location.href = 'members.php?view=' + memberId;
                    });

                    // Bouton Modifier
                    $(document).on('click', '.update-member', function() {
                        var memberId = $(this).data('member-id');
                        $('.modal').modal('hide');
                        $.ajax({
                            url: 'members.php',
                            type: 'POST',
                            data: { action: 'get_member', member_id: memberId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var member = response.member;
                                    $('#update_id').val(member.id);
                                    $('#update_nom').val(member.nom);
                                    $('#update_prenom').val(member.prenom);
                                    $('#update_sexe').val(member.sexe);
                                    $('#update_date_naissance').val(member.date_naissance);
                                    $('#update_province_naissance').val(member.province_naissance);
                                    $('#update_pays_naissance').val(member.pays_naissance);
                                    $('#update_telephone').val(member.telephone);
                                    $('#update_email').val(member.email);
                                    $('#update_residence').val(member.residence);
                                    $('#update_profession').val(member.profession);
                                    $('#update_etat_civil').val(member.etat_civil);
                                    $('#update_conjoint_field').toggle(member.etat_civil === 'Marié(e)');
                                    $('#update_conjoint_nom_prenom').val(member.conjoint_nom_prenom);
                                    $('#update_date_nouvelle_naissance').val(member.date_nouvelle_naissance);
                                    $('#update_eglise_nouvelle_naissance').val(member.eglise_nouvelle_naissance);
                                    $('#update_lieu_nouvelle_naissance').val(member.lieu_nouvelle_naissance);
                                    $('#update_formation_id').val(member.formation_id);
                                    $('#update_oikos_id').val(member.oikos_id);
                                    $('#update_departement').val(member.departement);
                                    $('#update_event_type').val(member.event_type);
                                    $('#update_event_date').val(member.date_evenement);
                                    $('#update_fiche_membre').val('');
                                    $('#updateMemberModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr) {
                                alert('Erreur lors de la récupération des données.');
                                console.error('Erreur AJAX (get_member) : ', xhr.responseText);
                            }
                        });
                    });

                    // Bouton Supprimer
                    $(document).on('click', '.delete-member', function() {
                        var memberId = $(this).data('member-id');
                        var memberName = $(this).data('member-name');
                        $('#delete-member-name').text(memberName);
                        $('#deleteConfirmModal').data('member-id', memberId);
                        $('#deleteConfirmModal').modal('show');
                    });

                    // Confirmation suppression
                    $('#confirm-delete').on('click', function() {
                        var memberId = $('#deleteConfirmModal').data('member-id');
                        $.ajax({
                            url: 'members.php',
                            type: 'POST',
                            data: { action: 'delete', member_id: memberId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $('#member-' + memberId).remove();
                                    $('#deleteConfirmModal').modal('hide');
                                    alert(response.message);
                                    window.location.reload();
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr) {
                                alert('Erreur lors de la suppression.');
                                console.error('Erreur AJAX (delete) : ', xhr.responseText);
                            }
                        });
                    });

                    // Recherche
                    let searchTimeout;
                    $('#search-input').on('input', function() {
                        var searchTerm = $(this).val().trim();
                        console.log('Recherche : ', searchTerm);
                        $('#clear-search').toggle(searchTerm.length > 0);
                        $('#export-csv-search-term').val(searchTerm);

                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            $('#search-spinner').show();
                            $('#search-results-body').html('<tr><td colspan="6">Recherche en cours...</td></tr>');

                            if (searchTerm.length >= 1) {
                                $.ajax({
                                    url: 'members.php',
                                    type: 'POST',
                                    data: { action: 'search', search_term: searchTerm },
                                    dataType: 'json',
                                    timeout: 5000,
                                    success: function(response) {
                                        $('#search-spinner').hide();
                                        console.log('Réponse recherche : ', response);
                                        if (response.success && Array.isArray(response.results)) {
                                            if (response.results.length > 0) {
                                                $('#search-results-body').empty();
                                                response.results.forEach(function(member) {
                                                    var row = `
                                                        <tr id="search-member-${member.id}">
                                                            <td>${member.id || '-'}</td>
                                                            <td>${(member.nom || '') + ' ' + (member.prenom || '')}</td>
                                                            <td>${member.telephone || '-'}</td>
                                                            <td>${member.residence || '-'}</td>
                                                            <td>${member.departement || '-'}</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info view-member" data-member-id="${member.id}">Voir</button>
                                                                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                                                                    <button class="btn btn-sm btn-warning update-member" data-member-id="${member.id}">Modifier</button>
                                                                <?php endif; ?>
                                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                                    <button class="btn btn-sm btn-danger delete-member" data-member-id="${member.id}" data-member-name="${(member.nom || '') + ' ' + (member.prenom || '')}">Supprimer</button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    `;
                                                    $('#search-results-body').append(row);
                                                });
                                                console.log('Résultats affichés : ', response.results.length);
                                            } else {
                                                $('#search-results-body').html('<tr><td colspan="6">Aucun résultat pour "' + searchTerm + '".</td></tr>');
                                                console.log('Aucun résultat');
                                            }
                                        } else {
                                            $('#search-results-body').html('<tr><td colspan="6">Erreur : ' + (response.message || 'Réponse invalide.') + '</td></tr>');
                                            console.error('Erreur recherche : ', response.message);
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        $('#search-spinner').hide();
                                        $('#search-results-body').html('<tr><td colspan="6">Erreur recherche : ' + (error || 'Serveur injoignable.') + '</td></tr>');
                                        console.error('Erreur AJAX (search) : ', status, error, xhr.responseText);
                                    }
                                });
                            } else {
                                $('#search-spinner').hide();
                                $('#search-results-body').html('<tr><td colspan="6">Entrez un terme de recherche.</td></tr>');
                            }
                        }, 500);
                    });

                    // Réinitialiser recherche
                    $('#clear-search').on('click', function() {
                        $('#search-input').val('');
                        $('#clear-search').hide();
                        $('#search-results-body').html('<tr><td colspan="6">Entrez un terme de recherche.</td></tr>');
                        $('#export-csv-search-term').val('');
                        console.log('Recherche réinitialisée');
                    });

                    // Afficher modal détails
                    <?php if ($view_member): ?>
                        $(window).on('load', function() {
                            $('#viewMemberModal').modal('show');
                        });
                    <?php endif; ?>

                    // Gestion modals
                    $('.modal').on('show.bs.modal', function() {
                        $('.modal-backdrop').css('z-index', parseInt($(this).css('z-index')) - 10);
                    }).on('hidden.bs.modal', function() {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>