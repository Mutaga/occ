<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Log access to confirm file usage
logAction($_SESSION['user_id'], "Accès à promotions.php", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));

// Initialize messages and filter
$error = null;
$success = null;
$filter_status = $_GET['filter_status'] ?? '';

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . $e->getMessage();
}

// Fetch members for responsible and enrollment dropdowns
try {
    // Responsible members (filtered by department)
    $stmt = $db->prepare("
        SELECT m.id, m.nom, m.prenom
        FROM members m
        WHERE m.departement IN ('Pastorat', 'Diaconat', 'Protocole') OR m.departement IS NULL
        ORDER BY m.nom, m.prenom
    ");
    $stmt->execute();
    $responsible_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // All members for enrollment
    $stmt = $db->prepare("SELECT id, nom, prenom FROM members ORDER BY nom, prenom");
    $stmt->execute();
    $all_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération membres : " . $e->getMessage();
}

// Status translations
$status_labels = [
    'active' => 'Actif',
    'pending' => 'En attente',
    'completed' => 'Terminé'
];

// Create Formation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $nom = trim($_POST['nom'] ?? '');
        $promotion = trim($_POST['promotion'] ?? '');
        $date_debut = trim($_POST['date_debut'] ?? '');
        $date_fin = trim($_POST['date_fin'] ?? '');
        $status = trim($_POST['status'] ?? 'pending');
        $responsible_id = trim($_POST['responsible_id'] ?? '');

        if (empty($nom) || empty($promotion) || empty($responsible_id)) {
            throw new Exception("Les champs Nom, Promotion et Responsable sont requis.");
        }
        if (!in_array($status, ['active', 'pending', 'completed'])) {
            throw new Exception("Statut invalide.");
        }
        if (!empty($date_debut) && !empty($date_fin) && $date_fin < $date_debut) {
            throw new Exception("La date de fin doit être postérieure à la date de début.");
        }
        // Check for duplicate formation
        $stmt = $db->prepare("SELECT id FROM formations WHERE nom = ? AND promotion = ?");
        $stmt->execute([$nom, $promotion]);
        if ($stmt->fetch()) {
            throw new Exception("Une formation avec ce nom et cette promotion existe déjà.");
        }

        $stmt = $db->prepare("
            INSERT INTO formations (nom, promotion, date_debut, date_fin, status, responsible_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nom,
            $promotion,
            $date_debut ?: null,
            $date_fin ?: null,
            $status,
            $responsible_id
        ]);

        $formation_id = $db->lastInsertId();

        logAction($_SESSION['user_id'], "Création formation: $formation_id (Nom: $nom, Promotion: $promotion, Statut: $status, Responsable: $responsible_id)", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Formation créée avec succès (ID: $formation_id)."]);
    } catch (Exception $e) {
        error_log("Create formation error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur création formation: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Update Formation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $promotion = trim($_POST['promotion'] ?? '');
        $date_debut = trim($_POST['date_debut'] ?? '');
        $date_fin = trim($_POST['date_fin'] ?? '');
        $status = trim($_POST['status'] ?? 'pending');
        $responsible_id = trim($_POST['responsible_id'] ?? '');

        if (empty($id) || empty($nom) || empty($promotion) || empty($responsible_id)) {
            throw new Exception("Les champs ID, Nom, Promotion et Responsable sont requis.");
        }
        if (!in_array($status, ['active', 'pending', 'completed'])) {
            throw new Exception("Statut invalide.");
        }
        if (!empty($date_debut) && !empty($date_fin) && $date_fin < $date_debut) {
            throw new Exception("La date de fin doit être postérieure à la date de début.");
        }
        // Check for duplicate formation (excluding current record)
        $stmt = $db->prepare("SELECT id FROM formations WHERE nom = ? AND promotion = ? AND id != ?");
        $stmt->execute([$nom, $promotion, $id]);
        if ($stmt->fetch()) {
            throw new Exception("Une formation avec ce nom et cette promotion existe déjà.");
        }

        $stmt = $db->prepare("
            UPDATE formations SET nom = ?, promotion = ?, date_debut = ?, date_fin = ?, status = ?, responsible_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nom,
            $promotion,
            $date_debut ?: null,
            $date_fin ?: null,
            $status,
            $responsible_id,
            $id
        ]);

        logAction($_SESSION['user_id'], "Mise à jour formation: $id (Statut: $status, Responsable: $responsible_id)", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Formation mise à jour avec succès (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Update formation error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur mise à jour formation: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Delete Formation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        $id = $_POST['formation_id'] ?? '';
        if (empty($id)) throw new Exception("ID manquant.");

        // Check if formation is used in formation_attendance
        $stmt = $db->prepare("SELECT COUNT(*) AS count FROM formation_attendance WHERE formation_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("Impossible de supprimer : cette formation est utilisée dans les présences.");
        }

        // Check if formation is used in member_formations
        $stmt = $db->prepare("SELECT COUNT(*) AS count FROM member_formations WHERE formation_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("Impossible de supprimer : cette formation a des membres inscrits.");
        }

        // Check if formation is used in sessions
        $stmt = $db->prepare("SELECT COUNT(*) AS count FROM sessions WHERE formation_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("Impossible de supprimer : cette formation a des sessions associées.");
        }

        $stmt = $db->prepare("DELETE FROM formations WHERE id = ?");
        $stmt->execute([$id]);

        logAction($_SESSION['user_id'], "Suppression formation: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Formation supprimée (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Delete formation error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur suppression formation: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get Formation Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_formation') {
    try {
        $formation_id = $_POST['formation_id'] ?? '';
        if (empty($formation_id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("
            SELECT f.*, m.nom AS responsible_nom, m.prenom AS responsible_prenom
            FROM formations f
            LEFT JOIN members m ON f.responsible_id = m.id
            WHERE f.id = ?
        ");
        $stmt->execute([$formation_id]);
        $formation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$formation) throw new Exception("Formation introuvable.");

        // Ensure valid status
        if (!isset($status_labels[$formation['status']])) {
            $formation['status'] = 'pending';
        }

        echo json_encode(['success' => true, 'formation' => $formation]);
    } catch (Exception $e) {
        error_log("Get formation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get Enrolled Members
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_enrolled_members') {
    try {
        $formation_id = $_POST['formation_id'] ?? '';
        if (empty($formation_id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("
            SELECT m.id, m.nom, m.prenom
            FROM member_formations mf
            JOIN members m ON mf.member_id = m.id
            WHERE mf.formation_id = ?
            ORDER BY m.nom, m.prenom
        ");
        $stmt->execute([$formation_id]);
        $enrolled_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'enrolled_members' => $enrolled_members]);
    } catch (Exception $e) {
        error_log("Get enrolled members error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Enroll Members
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'enroll_members' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $formation_id = trim($_POST['formation_id'] ?? '');
        $member_ids = $_POST['member_ids'] ?? [];

        if (empty($formation_id)) throw new Exception("ID de formation manquant.");
        if (empty($member_ids)) throw new Exception("Aucun membre sélectionné.");

        // Verify formation exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Formation invalide.");
        }

        $inserted = 0;
        foreach ($member_ids as $member_id) {
            // Verify member exists
            $stmt = $db->prepare("SELECT id FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            if (!$stmt->fetch()) {
                continue; // Skip invalid member
            }
            // Check for existing enrollment
            $stmt = $db->prepare("SELECT member_id FROM member_formations WHERE member_id = ? AND formation_id = ?");
            $stmt->execute([$member_id, $formation_id]);
            if ($stmt->fetch()) {
                continue; // Skip already enrolled
            }
            // Enroll member
            $stmt = $db->prepare("INSERT INTO member_formations (member_id, formation_id) VALUES (?, ?)");
            $stmt->execute([$member_id, $formation_id]);
            $inserted++;
        }

        logAction($_SESSION['user_id'], "Inscription de $inserted membre(s) à la formation: $formation_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "$inserted membre(s) inscrit(s) avec succès."]);
    } catch (Exception $e) {
        error_log("Enroll members error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur inscription membres: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Unenroll Members
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'unenroll_members' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $formation_id = trim($_POST['formation_id'] ?? '');
        $member_ids = $_POST['member_ids'] ?? [];

        if (empty($formation_id)) throw new Exception("ID de formation manquant.");
        if (empty($member_ids)) throw new Exception("Aucun membre sélectionné.");

        // Verify formation exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Formation invalide.");
        }

        $deleted = 0;
        foreach ($member_ids as $member_id) {
            // Check if member has attendance records
            $stmt = $db->prepare("SELECT COUNT(*) AS count FROM formation_attendance WHERE member_id = ? AND formation_id = ?");
            $stmt->execute([$member_id, $formation_id]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                continue; // Skip members with attendance
            }
            // Unenroll member
            $stmt = $db->prepare("DELETE FROM member_formations WHERE member_id = ? AND formation_id = ?");
            $stmt->execute([$member_id, $formation_id]);
            if ($stmt->rowCount() > 0) {
                $deleted++;
            }
        }

        logAction($_SESSION['user_id'], "Désinscription de $deleted membre(s) de la formation: $formation_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "$deleted membre(s) désinscrit(s) avec succès."]);
    } catch (Exception $e) {
        error_log("Unenroll members error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur désinscription membres: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX DataTable Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    $where = [];
    $params = [];
    $filter_status = $_GET['filter_status'] ?? '';

    if (!empty($filter_status)) {
        $where[] = "f.status = ?";
        $params[] = $filter_status;
    }

    $sql = "
        SELECT f.*, 
               m.nom AS responsible_nom, 
               m.prenom AS responsible_prenom,
               (SELECT COUNT(*) FROM sessions s WHERE s.formation_id = f.id) AS session_count,
               (SELECT COUNT(*) FROM sessions s WHERE s.formation_id = f.id AND s.teacher_id IS NOT NULL) AS teacher_assigned_count,
               (SELECT COUNT(*) FROM member_formations mf WHERE mf.formation_id = f.id) AS enrolled_member_count
        FROM formations f
        LEFT JOIN members m ON f.responsible_id = m.id
    ";
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY f.promotion DESC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['formations' => $formations]);
    } catch (Exception $e) {
        error_log("AJAX fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => "Erreur récupération formations : " . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Promotions</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
        .sidebar .sub-menu {
            padding-left: 20px;
        }
        .sidebar .sub-menu .nav-link {
            font-size: 14px;
            padding: 8px 15px;
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
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .modal-body {
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
        }
        .btn-sm {
            font-size: 11px;
            padding: 3px 6px;
            line-height: 1.4;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            white-space: nowrap;
            justify-content: center;
        }
        .filter-section {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .toast-container {
            z-index: 1055;
        }
        .toast {
            border-radius: 8px;
            min-width: 300px;
        }
        .toast-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        #enrolled-members-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        .enrolled-member {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
        }
        .enrolled-member .btn {
            font-size: 12px;
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
                <li class="nav-item"><a href="members.php" class="nav-link">Membres</a></li>
                <li class="nav-item"><a href="children.php" class="nav-link">Enfants</a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link">Formations</a>
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a href="promotions.php" class="nav-link active">Promotions</a></li>
                        <li class="nav-item"><a href="sessions.php" class="nav-link">Sessions</a></li>
                        <li class="nav-item"><a href="formations.php?section=attendances" class="nav-link">Présences</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a href="anniversaires.php" class="nav-link">Anniversaires</a></li>
                <li class="nav-item"><a href="oikos.php" class="nav-link">Oikos</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a href="logs.php" class="nav-link">Logs</a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="auth.php?logout" class="nav-link">Déconnexion</a></li>
            </ul>
        </div>
        <div class="content flex-grow-1">
            <h1 class="mb-4">Gestion des Promotions</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Toast Notification Container -->
            <div class="toast-container position-fixed top-0 end-0 p-3">
                <div id="notification-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-autohide="true" data-delay="5000">
                    <div class="toast-header">
                        <span id="toast-icon" class="me-2"></span>
                        <strong id="toast-title" class="me-auto"></strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div id="toast-body" class="toast-body"></div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" id="filter-form">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_status">Statut</label>
                                <select class="form-control" id="filter_status" name="filter_status">
                                    <option value="">Tous</option>
                                    <?php foreach ($status_labels as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $filter_status === $value ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                            <a href="promotions.php" class="btn btn-secondary ml-2">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mb-3">
                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#createFormationModal">Créer une Formation</button>
                <?php endif; ?>
            </div>

            <table id="formation-table" class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Promotion</th>
                        <th>Date Début</th>
                        <th>Date Fin</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                        <th>Sessions (Enseignants)</th>
                        <th>Membres Inscrits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- Create Formation Modal -->
            <div class="modal fade" id="createFormationModal" tabindex="-1" role="dialog" aria-labelledby="createFormationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createFormationModalLabel">Créer une Formation</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="create-formation-form">
                            <input type="hidden" name="action" value="create">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_nom" class="form-label">Nom *</label>
                                            <input type="text" class="form-control" id="create_nom" name="nom" required placeholder="ex: Leadership Spirituel">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_promotion" class="form-label">Promotion *</label>
                                            <input type="text" class="form-control" id="create_promotion" name="promotion" required placeholder="ex: 2023">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_date_debut" class="form-label">Date Début</label>
                                            <input type="date" class="form-control" id="create_date_debut" name="date_debut">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_date_fin" class="form-label">Date Fin</label>
                                            <input type="date" class="form-control" id="create_date_fin" name="date_fin">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_responsible_id" class="form-label">Responsable *</label>
                                            <select class="form-control" id="create_responsible_id" name="responsible_id" required>
                                                <option value="">Sélectionnez un responsable</option>
                                                <?php foreach ($responsible_members as $member): ?>
                                                    <option value="<?php echo htmlspecialchars($member['id']); ?>">
                                                        <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_status" class="form-label">Statut *</label>
                                            <select class="form-control" id="create_status" name="status" required>
                                                <?php foreach ($status_labels as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'pending' ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary">Créer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Formation Modal -->
            <div class="modal fade" id="editFormationModal" tabindex="-1" role="dialog" aria-labelledby="editFormationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="editFormationModalLabel">Modifier la Formation</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="edit-formation-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_nom" class="form-label">Nom *</label>
                                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_promotion" class="form-label">Promotion *</label>
                                            <input type="text" class="form-control" id="edit_promotion" name="promotion" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_date_debut" class="form-label">Date Début</label>
                                            <input type="date" class="form-control" id="edit_date_debut" name="date_debut">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_date_fin" class="form-label">Date Fin</label>
                                            <input type="date" class="form-control" id="edit_date_fin" name="date_fin">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_responsible_id" class="form-label">Responsable *</label>
                                            <select class="form-control" id="edit_responsible_id" name="responsible_id" required>
                                                <option value="">Sélectionnez un responsable</option>
                                                <?php foreach ($responsible_members as $member): ?>
                                                    <option value="<?php echo htmlspecialchars($member['id']); ?>">
                                                        <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_status" class="form-label">Statut *</label>
                                            <select class="form-control" id="edit_status" name="status" required>
                                                <?php foreach ($status_labels as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>">
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-warning">Mettre à jour</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- View Formation Modal -->
            <div class="modal fade" id="viewFormationModal" tabindex="-1" role="dialog" aria-labelledby="viewFormationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewFormationModalLabel">Détails de la Formation</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>ID:</strong> <span id="view_id"></span></p>
                            <p><strong>Nom:</strong> <span id="view_nom"></span></p>
                            <p><strong>Promotion:</strong> <span id="view_promotion"></span></p>
                            <p><strong>Date Début:</strong> <span id="view_date_debut"></span></p>
                            <p><strong>Date Fin:</strong> <span id="view_date_fin"></span></p>
                            <p><strong>Responsable:</strong> <span id="view_responsible"></span></p>
                            <p><strong>Statut:</strong> <span id="view_status"></span></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Members Modal -->
            <div class="modal fade" id="manageMembersModal" tabindex="-1" role="dialog" aria-labelledby="manageMembersModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="manageMembersModalLabel">Gérer les Membres Inscrits</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="manage_formation_id">
                            <h6>Inscrire des Membres</h6>
                            <div class="form-group">
                                <label for="enroll_members">Sélectionner les Membres</label>
                                <select class="form-control" id="enroll_members" name="enroll_members" multiple style="height: 150px;">
                                    <?php foreach ($all_members as $member): ?>
                                        <option value="<?php echo htmlspecialchars($member['id']); ?>">
                                            <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" id="enroll-members-btn" class="btn btn-success mb-3">Inscrire</button>
                            <h6>Membres Inscrits</h6>
                            <div id="enrolled-members-list"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
            <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
            <script>
                $(document).ready(function() {
                    // Notification function
                    function showNotification(message, type = 'success') {
                        const toast = $('#notification-toast');
                        const icon = $('#toast-icon');
                        const title = $('#toast-title');
                        const body = $('#toast-body');

                        toast.removeClass('bg-success bg-danger bg-warning text-white');
                        icon.removeClass('bi-check-circle-fill bi-exclamation-triangle-fill bi-info-circle-fill');

                        if (type === 'success') {
                            toast.addClass('bg-success text-white');
                            icon.addClass('bi-check-circle-fill');
                            title.text('Succès');
                        } else if (type === 'error') {
                            toast.addClass('bg-danger text-white');
                            icon.addClass('bi-exclamation-triangle-fill');
                            title.text('Erreur');
                        } else if (type === 'warning') {
                            toast.addClass('bg-warning');
                            icon.addClass('bi-info-circle-fill');
                            title.text('Avertissement');
                        }

                        body.text(message);
                        toast.toast('show');
                    }

                    // Initialize DataTable
                    const table = $('#formation-table').DataTable({
                        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                        order: [[2, 'desc']],
                        pageLength: 10,
                        responsive: true,
                        columnDefs: [
                            { width: '150px', targets: 9, className: 'text-center' },
                            { width: '10%', targets: 0 },
                            { width: '15%', targets: 1 },
                            { width: '10%', targets: 2 },
                            { width: '10%', targets: 3 },
                            { width: '10%', targets: 4 },
                            { width: '15%', targets: 5 },
                            { width: '10%', targets: 6 },
                            { width: '10%', targets: 7 },
                            { width: '10%', targets: 8 }
                        ],
                        ajax: {
                            url: 'promotions.php?ajax=1',
                            type: 'GET',
                            data: function(d) {
                                d.filter_status = $('#filter_status').val();
                            },
                            dataSrc: 'formations'
                        },
                        columns: [
                            { data: 'id' },
                            { data: 'nom' },
                            { data: 'promotion' },
                            { data: 'date_debut', render: function(data) { return data || '-'; } },
                            { data: 'date_fin', render: function(data) { return data || '-'; } },
                            {
                                data: null,
                                render: function(data) {
                                    return data.responsible_nom && data.responsible_prenom
                                        ? `${data.responsible_nom} ${data.responsible_prenom}`
                                        : '-';
                                }
                            },
                            {
                                data: 'status',
                                render: function(data) {
                                    const labels = <?php echo json_encode($status_labels); ?>;
                                    return labels[data] || 'Inconnu';
                                }
                            },
                            {
                                data: null,
                                render: function(data) {
                                    return `${data.session_count} (${data.teacher_assigned_count} avec enseignant)`;
                                }
                            },
                            { data: 'enrolled_member_count', render: function(data) { return data || '0'; } },
                            {
                                data: null,
                                render: function(data, type, row) {
                                    let actions = `
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-info view-formation" data-formation-id="${row.id}">Voir</button>
                                    `;
                                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                        actions += `
                                            <button class="btn btn-sm btn-warning edit-formation" data-formation-id="${row.id}">Modifier</button>
                                            <button class="btn btn-sm btn-success manage-members" data-formation-id="${row.id}">Membres</button>
                                        `;
                                    <?php endif; ?>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        actions += `<button class="btn btn-sm btn-danger delete-formation" data-formation-id="${row.id}">Supprimer</button>`;
                                    <?php endif; ?>
                                    actions += `</div>`;
                                    return actions;
                                }
                            }
                        ]
                    });

                    // Form validation and submission
                    function handleFormSubmission(formId, url, successMessage, modalId) {
                        $(formId).on('submit', function(e) {
                            e.preventDefault();
                            const formData = $(this).serialize();
                            if (!$(`${formId} [name="nom"]`).val().trim() || !$(`${formId} [name="promotion"]`).val().trim() || !$(`${formId} [name="responsible_id"]`).val()) {
                                showNotification('Les champs Nom, Promotion et Responsable sont requis.', 'warning');
                                return;
                            }
                            $.ajax({
                                url: url,
                                type: 'POST',
                                data: formData,
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        showNotification(response.message || successMessage, 'success');
                                        $(modalId).modal('hide');
                                        $(formId)[0].reset();
                                        table.ajax.reload();
                                    } else {
                                        showNotification(response.message, 'error');
                                    }
                                },
                                error: function(xhr) {
                                    showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                                }
                            });
                        });
                    }

                    handleFormSubmission('#create-formation-form', 'promotions.php', 'Formation créée avec succès.', '#createFormationModal');
                    handleFormSubmission('#edit-formation-form', 'promotions.php', 'Formation mise à jour avec succès.', '#editFormationModal');

                    // View formation
                    $(document).on('click', '.view-formation', function() {
                        const formationId = $(this).data('formation-id');
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: { action: 'get_formation', formation_id: formationId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const formation = response.formation;
                                    $('#view_id').text(formation.id);
                                    $('#view_nom').text(formation.nom);
                                    $('#view_promotion').text(formation.promotion);
                                    $('#view_date_debut').text(formation.date_debut || '-');
                                    $('#view_date_fin').text(formation.date_fin || '-');
                                    $('#view_responsible').text(formation.responsible_nom && formation.responsible_prenom ? `${formation.responsible_nom} ${formation.responsible_prenom}` : '-');
                                    $('#view_status').text(<?php echo json_encode($status_labels); ?>[formation.status] || 'Inconnu');
                                    $('#viewFormationModal').modal('show');
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // Edit formation
                    $(document).on('click', '.edit-formation', function() {
                        const formationId = $(this).data('formation-id');
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: { action: 'get_formation', formation_id: formationId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const formation = response.formation;
                                    $('#edit_id').val(formation.id);
                                    $('#edit_nom').val(formation.nom);
                                    $('#edit_promotion').val(formation.promotion);
                                    $('#edit_date_debut').val(formation.date_debut || '');
                                    $('#edit_date_fin').val(formation.date_fin || '');
                                    $('#edit_responsible_id').val(formation.responsible_id);
                                    $('#edit_status').val(formation.status);
                                    $('#editFormationModal').modal('show');
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // Delete formation
                    $(document).on('click', '.delete-formation', function() {
                        const formationId = $(this).data('formation-id');
                        if (confirm('Êtes-vous sûr de vouloir supprimer cette formation ?')) {
                            $.ajax({
                                url: 'promotions.php',
                                type: 'POST',
                                data: { action: 'delete', formation_id: formationId },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        showNotification(response.message, 'success');
                                        table.ajax.reload();
                                    } else {
                                        showNotification(response.message, 'error');
                                    }
                                },
                                error: function(xhr) {
                                    showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                                }
                            });
                        }
                    });

                    // Manage members
                    $(document).on('click', '.manage-members', function() {
                        const formationId = $(this).data('formation-id');
                        $('#manage_formation_id').val(formationId);
                        loadEnrolledMembers(formationId);
                        $('#manageMembersModal').modal('show');
                    });

                    // Load enrolled members
                    function loadEnrolledMembers(formationId) {
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: { action: 'get_enrolled_members', formation_id: formationId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const members = response.enrolled_members;
                                    const list = $('#enrolled-members-list');
                                    list.empty();
                                    if (members.length === 0) {
                                        list.append('<p>Aucun membre inscrit.</p>');
                                    } else {
                                        members.forEach(member => {
                                            list.append(`
                                                <div class="enrolled-member">
                                                    <span>${member.nom} ${member.prenom}</span>
                                                    <button class="btn btn-sm btn-danger unenroll-member" data-member-id="${member.id}">Désinscrire</button>
                                                </div>
                                            `);
                                        });
                                    }
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    }

                    // Enroll members
                    $('#enroll-members-btn').on('click', function() {
                        const formationId = $('#manage_formation_id').val();
                        const memberIds = $('#enroll_members').val();
                        if (!memberIds || memberIds.length === 0) {
                            showNotification('Veuillez sélectionner au moins un membre.', 'warning');
                            return;
                        }
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: {
                                action: 'enroll_members',
                                formation_id: formationId,
                                member_ids: memberIds
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification(response.message, 'success');
                                    loadEnrolledMembers(formationId);
                                    $('#enroll_members').val([]);
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // Unenroll member
                    $(document).on('click', '.unenroll-member', function() {
                        const formationId = $('#manage_formation_id').val();
                        const memberId = $(this).data('member-id');
                        if (confirm('Êtes-vous sûr de vouloir désinscrire ce membre ?')) {
                            $.ajax({
                                url: 'promotions.php',
                                type: 'POST',
                                data: {
                                    action: 'unenroll_members',
                                    formation_id: formationId,
                                    member_ids: [memberId]
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        showNotification(response.message, 'success');
                                        loadEnrolledMembers(formationId);
                                        table.ajax.reload();
                                    } else {
                                        showNotification(response.message, 'error');
                                    }
                                },
                                error: function(xhr) {
                                    showNotification('Erreur serveur : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                                }
                            });
                        }
                    });

                    // Modal z-index fix
                    $('.modal').on('show.bs.modal', function() {
                        const zIndex = 1050 + (10 * $('.modal:visible').length);
                        $(this).css('z-index', zIndex);
                        setTimeout(() => {
                            $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
                        }, 0);
                    }).on('hidden.bs.modal', function() {
                        if ($('.modal:visible').length > 0) {
                            setTimeout(() => {
                                $(document.body).addClass('modal-open');
                            }, 0);
                        }
                        $('.modal-backdrop').remove();
                    });

                    // Refresh table on filter change
                    $('#filter-form').on('submit', function(e) {
                        e.preventDefault();
                        table.ajax.reload();
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>