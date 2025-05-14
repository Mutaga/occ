<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('APP_START', true);
require_once 'session_manager.php';
require_once 'config.php';

// Verify user is logged in
requireLogin();

// Log access
logAction(getCurrentUserId(), "Accès à attendances.php", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));

// Initialize messages
$error = null;
$success = null;

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
}

// Fetch Sessions for a Formation (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_sessions') {
    try {
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $formation_id = trim($_POST['formation_id'] ?? '');
        if (empty($formation_id)) throw new Exception("ID de formation manquant.");

        $stmt = $db->prepare("SELECT id, nom, date_session FROM sessions WHERE formation_id = ? ORDER BY date_session ASC");
        $stmt->execute([$formation_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'sessions' => $sessions]);
    } catch (Exception $e) {
        error_log("Get sessions error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur récupération sessions: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Create Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $member_id = trim($_POST['member_id'] ?? '');
        $formation_id = trim($_POST['formation_id'] ?? '');
        $session_id = trim($_POST['session_id'] ?? '');
        $date_presence = trim($_POST['date_presence'] ?? '');
        $present = isset($_POST['present']) ? 1 : 0;

        if (empty($member_id) || empty($formation_id) || empty($session_id) || empty($date_presence)) {
            throw new Exception("Les champs Membre, Formation, Session et Date sont requis.");
        }
        // Verify member_id exists
        $stmt = $db->prepare("SELECT id FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Membre invalide.");
        }
        // Verify member is enrolled in formation
        $stmt = $db->prepare("SELECT member_id FROM member_formations WHERE member_id = ? AND formation_id = ?");
        $stmt->execute([$member_id, $formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Ce membre n'est pas inscrit à cette formation.");
        }
        // Verify session_id exists and belongs to formation
        $stmt = $db->prepare("SELECT id, date_session FROM sessions WHERE id = ? AND formation_id = ?");
        $stmt->execute([$session_id, $formation_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new Exception("Session invalide ou non associée à la formation.");
        }
        // Validate date_presence matches session date
        if ($date_presence !== $session['date_session']) {
            throw new Exception("La date de présence doit correspondre à la date de la session ({$session['date_session']}).");
        }
        // Check for duplicate attendance
        $stmt = $db->prepare("SELECT id FROM formation_attendance WHERE member_id = ? AND session_id = ? AND date_presence = ?");
        $stmt->execute([$member_id, $session_id, $date_presence]);
        if ($stmt->fetch()) {
            throw new Exception("Une présence pour ce membre, cette session et cette date existe déjà.");
        }

        $stmt = $db->prepare("
            INSERT INTO formation_attendance (member_id, formation_id, session_id, date_presence, present)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$member_id, $formation_id, $session_id, $date_presence, $present]);

        $attendance_id = $db->lastInsertId();

        logAction(getCurrentUserId(), "Création présence: $attendance_id (Membre: $member_id, Formation: $formation_id, Session: $session_id)", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Présence enregistrée avec succès (ID: $attendance_id)."]);
    } catch (Exception $e) {
        error_log("Create attendance error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur création présence: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur création : " . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Update Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $id = trim($_POST['id'] ?? '');
        $member_id = trim($_POST['member_id'] ?? '');
        $formation_id = trim($_POST['formation_id'] ?? '');
        $session_id = trim($_POST['session_id'] ?? '');
        $date_presence = trim($_POST['date_presence'] ?? '');
        $present = isset($_POST['present']) ? 1 : 0;

        if (empty($id) || empty($member_id) || empty($formation_id) || empty($session_id) || empty($date_presence)) {
            throw new Exception("Les champs ID, Membre, Formation, Session et Date sont requis.");
        }
        // Verify member_id exists
        $stmt = $db->prepare("SELECT id FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Membre invalide.");
        }
        // Verify member is enrolled in formation
        $stmt = $db->prepare("SELECT member_id FROM member_formations WHERE member_id = ? AND formation_id = ?");
        $stmt->execute([$member_id, $formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Ce membre n'est pas inscrit à cette formation.");
        }
        // Verify session_id exists and belongs to formation
        $stmt = $db->prepare("SELECT id, date_session FROM sessions WHERE id = ? AND formation_id = ?");
        $stmt->execute([$session_id, $formation_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new Exception("Session invalide ou non associée à la formation.");
        }
        // Validate date_presence matches session date
        if ($date_presence !== $session['date_session']) {
            throw new Exception("La date de présence doit correspondre à la date de la session ({$session['date_session']}).");
        }
        // Check for duplicate attendance (excluding current record)
        $stmt = $db->prepare("SELECT id FROM formation_attendance WHERE member_id = ? AND session_id = ? AND date_presence = ? AND id != ?");
        $stmt->execute([$member_id, $session_id, $date_presence, $id]);
        if ($stmt->fetch()) {
            throw new Exception("Une présence pour ce membre, cette session et cette date existe déjà.");
        }

        $stmt = $db->prepare("
            UPDATE formation_attendance SET member_id = ?, formation_id = ?, session_id = ?, date_presence = ?, present = ?
            WHERE id = ?
        ");
        $stmt->execute([$member_id, $formation_id, $session_id, $date_presence, $present, $id]);

        logAction(getCurrentUserId(), "Mise à jour présence: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Présence mise à jour avec succès (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Update attendance error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur mise à jour présence: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur mise à jour : " . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Delete Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $id = trim($_POST['attendance_id'] ?? '');
        if (empty($id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("DELETE FROM formation_attendance WHERE id = ?");
        $stmt->execute([$id]);

        logAction(getCurrentUserId(), "Suppression présence: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Présence supprimée (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Delete attendance error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur suppression présence: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Get Attendance Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_attendance') {
    try {
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $attendance_id = trim($_POST['attendance_id'] ?? '');
        if (empty($attendance_id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("
            SELECT a.*, m.nom AS member_nom, m.prenom AS member_prenom, f.nom AS formation_nom, f.promotion, s.nom AS session_nom, s.date_session
            FROM formation_attendance a
            JOIN members m ON a.member_id = m.id
            JOIN formations f ON a.formation_id = f.id
            JOIN sessions s ON a.session_id = s.id
            WHERE a.id = ?
        ");
        $stmt->execute([$attendance_id]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attendance) throw new Exception("Présence introuvable.");

        echo json_encode(['success' => true, 'attendance' => $attendance]);
    } catch (Exception $e) {
        error_log("Get attendance error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur récupération présence: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Handle Filters
$where = [];
$params = [];
$filter_member = filter_input(INPUT_GET, 'filter_member', FILTER_SANITIZE_STRING) ?? '';
$filter_formation = filter_input(INPUT_GET, 'filter_formation', FILTER_SANITIZE_STRING) ?? '';
$filter_date_start = filter_input(INPUT_GET, 'filter_date_start', FILTER_SANITIZE_STRING) ?? '';
$filter_date_end = filter_input(INPUT_GET, 'filter_date_end', FILTER_SANITIZE_STRING) ?? '';

if (!empty($filter_member)) {
    $where[] = "a.member_id = ?";
    $params[] = $filter_member;
}
if (!empty($filter_formation)) {
    $where[] = "a.formation_id = ?";
    $params[] = $filter_formation;
}
if (!empty($filter_date_start)) {
    $where[] = "a.date_presence >= ?";
    $params[] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where[] = "a.date_presence <= ?";
    $params[] = $filter_date_end;
}

$sql = "
    SELECT a.*, m.nom AS member_nom, m.prenom AS member_prenom, f.nom AS formation_nom, f.promotion, s.nom AS session_nom, s.date_session
    FROM formation_attendance a
    JOIN members m ON a.member_id = m.id
    JOIN formations f ON a.formation_id = f.id
    JOIN sessions s ON a.session_id = s.id
";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY a.date_presence DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération présences : " . htmlspecialchars($e->getMessage());
    $attendances = [];
}

// Fetch members for dropdown
try {
    $stmt = $db->prepare("SELECT id, nom, prenom FROM members ORDER BY nom ASC");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($members)) {
        $error = "Aucun membre disponible. Ajoutez des membres à la table 'members'.";
    }
} catch (Exception $e) {
    $error = "Erreur récupération membres : " . htmlspecialchars($e->getMessage());
    $members = [];
}

// Fetch formations for dropdown
try {
    $stmt = $db->prepare("SELECT id, nom, promotion FROM formations ORDER BY promotion DESC");
    $stmt->execute();
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($formations)) {
        $error = "Aucune formation disponible. Ajoutez des formations à la table 'formations'.";
    }
} catch (Exception $e) {
    $error = "Erreur récupération formations : " . htmlspecialchars($e->getMessage());
    $formations = [];
}

// Fetch summary of points per member
try {
    $stmt = $db->prepare("
        SELECT m.id, m.nom, m.prenom, SUM(a.points) AS total_points
        FROM members m
        LEFT JOIN formation_attendance a ON m.id = a.member_id
        GROUP BY m.id
        ORDER BY total_points DESC
    ");
    $stmt->execute();
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération sommaire : " . htmlspecialchars($e->getMessage());
    $summary = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Présences</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
        .summary-section {
            margin-top: 20px;
        }
        .toast-container {
            z-index: 1055;
        }
        .toast {
            border-radius: 8px;
            min-width: 300px;
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
                        <li class="nav-item"><a href="promotions.php" class="nav-link">Promotions</a></li>
                        <li class="nav-item"><a href="sessions.php" class="nav-link">Sessions</a></li>
                        <li class="nav-item"><a href="attendances.php" class="nav-link active">Présences</a></li>
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
            <h1 class="mb-4">Gestion des Présences</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="toast-container position-fixed top-0 end-0 p-3">
                <div id="notification-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
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
                                <label for="filter_member">Membre</label>
                                <select class="form-select" id="filter_member" name="filter_member">
                                    <option value="">Tous</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo htmlspecialchars($member['id']); ?>" <?php echo $filter_member === $member['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_formation">Formation</label>
                                <select class="form-select" id="filter_formation" name="filter_formation">
                                    <option value="">Toutes</option>
                                    <?php foreach ($formations as $formation): ?>
                                        <option value="<?php echo htmlspecialchars($formation['id']); ?>" <?php echo $filter_formation === $formation['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formation['nom'] . ' (' . $formation['promotion'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="filter_date_start">Date Début</label>
                                <input type="date" class="form-control" id="filter_date_start" name="filter_date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="filter_date_end">Date Fin</label>
                                <input type="date" class="form-control" id="filter_date_end" name="filter_date_end" value="<?php echo htmlspecialchars($filter_date_end); ?>">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                            <a href="attendances.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mb-3">
                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAttendanceModal" <?php echo empty($members) || empty($formations) ? 'disabled' : ''; ?>>Enregistrer une Présence</button>
                    <?php if (empty($members)): ?>
                        <small class="text-danger">Aucun membre disponible. Ajoutez des membres.</small>
                    <?php elseif (empty($formations)): ?>
                        <small class="text-danger">Aucune formation disponible. Ajoutez des formations.</small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <table id="attendance-table" class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Membre</th>
                        <th>Formation</th>
                        <th>Promotion</th>
                        <th>Session</th>
                        <th>Date</th>
                        <th>Présent</th>
                        <th>Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendances as $attendance): ?>
                        <tr id="attendance-<?php echo htmlspecialchars($attendance['id']); ?>">
                            <td><?php echo htmlspecialchars($attendance['id']); ?></td>
                            <td><?php echo htmlspecialchars($attendance['member_nom'] . ' ' . $attendance['member_prenom']); ?></td>
                            <td><?php echo htmlspecialchars($attendance['formation_nom']); ?></td>
                            <td><?php echo htmlspecialchars($attendance['promotion']); ?></td>
                            <td><?php echo htmlspecialchars($attendance['session_nom']); ?></td>
                            <td><?php echo htmlspecialchars($attendance['date_presence']); ?></td>
                            <td><?php echo $attendance['present'] ? 'Oui' : 'Non'; ?></td>
                            <td><?php echo htmlspecialchars($attendance['points'] ?? '0'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info view-attendance" data-attendance-id="<?php echo htmlspecialchars($attendance['id']); ?>">Voir</button>
                                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                        <button class="btn btn-sm btn-warning edit-attendance" data-attendance-id="<?php echo htmlspecialchars($attendance['id']); ?>" <?php echo empty($members) || empty($formations) ? 'disabled' : ''; ?>>Modifier</button>
                                    <?php endif; ?>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <button class="btn btn-sm btn-danger delete-attendance" data-attendance-id="<?php echo htmlspecialchars($attendance['id']); ?>">Supprimer</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Summary Section -->
            <div class="summary-section">
                <h3 class="mb-3">Sommaire des Points par Membre</h3>
                <button class="btn btn-info mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#summaryCollapse" aria-expanded="false" aria-controls="summaryCollapse">
                    Afficher/Cacher le Sommaire
                </button>
                <div class="collapse" id="summaryCollapse">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Membre</th>
                                <th>Total Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_points'] ?? '0'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create Attendance Modal -->
            <div class="modal fade" id="createAttendanceModal" tabindex="-1" aria-labelledby="createAttendanceModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createAttendanceModalLabel">Enregistrer une Présence</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="create-attendance-form">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="create_member_id" class="form-label">Membre *</label>
                                            <select class="form-select" id="create_member_id" name="member_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?php echo htmlspecialchars($member['id']); ?>">
                                                        <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom'] . ' (' . $member['id'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="create_formation_id" class="form-label">Formation *</label>
                                            <select class="form-select" id="create_formation_id" name="formation_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' (' . $formation['promotion'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="create_session_id" class="form-label">Session *</label>
                                            <select class="form-select" id="create_session_id" name="session_id" required>
                                                <option value="">Sélectionner une formation d'abord...</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="create_date_presence" class="form-label">Date *</label>
                                            <input type="date" class="form-control" id="create_date_presence" name="date_presence" required readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="create_present" class="form-label">Présent</label>
                                            <input type="checkbox" id="create_present" name="present" checked>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Attendance Modal -->
            <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="editAttendanceModalLabel">Modifier la Présence</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="edit-attendance-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_member_id" class="form-label">Membre *</label>
                                            <select class="form-select" id="edit_member_id" name="member_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?php echo htmlspecialchars($member['id']); ?>">
                                                        <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom'] . ' (' . $member['id'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_formation_id" class="form-label">Formation *</label>
                                            <select class="form-select" id="edit_formation_id" name="formation_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' (' . $formation['promotion'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_session_id" class="form-label">Session *</label>
                                            <select class="form-select" id="edit_session_id" name="session_id" required>
                                                <option value="">Sélectionner une formation d'abord...</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_date_presence" class="form-label">Date *</label>
                                            <input type="date" class="form-control" id="edit_date_presence" name="date_presence" required readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_present" class="form-label">Présent</label>
                                            <input type="checkbox" id="edit_present" name="present">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-warning">Mettre à jour</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- View Attendance Modal -->
            <div class="modal fade" id="viewAttendanceModal" tabindex="-1" aria-labelledby="viewAttendanceModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewAttendanceModalLabel">Détails de la Présence</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>ID:</strong> <span id="view_id"></span></p>
                            <p><strong>Membre:</strong> <span id="view_member_nom"></span></p>
                            <p><strong>Formation:</strong> <span id="view_formation_nom"></span></p>
                            <p><strong>Promotion:</strong> <span id="view_promotion"></span></p>
                            <p><strong>Session:</strong> <span id="view_session_nom"></span></p>
                            <p><strong>Date:</strong> <span id="view_date_presence"></span></p>
                            <p><strong>Présent:</strong> <span id="view_present"></span></p>
                            <p><strong>Points:</strong> <span id="view_points"></span></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
            <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
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
                    $('#attendance-table').DataTable({
                        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                        order: [[5, 'desc']],
                        pageLength: 10,
                        responsive: true,
                        columnDefs: [
                            { width: '10%', targets: 0 },
                            { width: '20%', targets: 1 },
                            { width: '15%', targets: 2 },
                            { width: '10%', targets: 3 },
                            { width: '15%', targets: 4 },
                            { width: '10%', targets: 5 },
                            { width: '10%', targets: 6 },
                            { width: '10%', targets: 7 },
                            { width: '150px', targets: 8, className: 'text-center' }
                        ]
                    });

                    // Function to load sessions for a formation
                    function loadSessions(formationId, sessionSelectId, selectedSessionId = null, dateInputId = null) {
                        if (!formationId) {
                            $(sessionSelectId).html('<option value="">Sélectionner une formation d\'abord...</option>');
                            if (dateInputId) $(dateInputId).val('');
                            return;
                        }
                        $.ajax({
                            url: 'attendances.php',
                            type: 'POST',
                            data: { action: 'get_sessions', formation_id: formationId, csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>' },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    let options = '<option value="">Sélectionner...</option>';
                                    response.sessions.forEach(function(session) {
                                        options += `<option value="${session.id}" data-date="${session.date_session}">${session.nom} (${session.date_session})</option>`;
                                    });
                                    $(sessionSelectId).html(options);
                                    if (selectedSessionId) {
                                        $(sessionSelectId).val(selectedSessionId);
                                        if (dateInputId) {
                                            const selectedOption = $(sessionSelectId).find(`option[value="${selectedSessionId}"]`);
                                            $(dateInputId).val(selectedOption.data('date') || '');
                                        }
                                    }
                                } else {
                                    showNotification('Erreur : ' + response.message, 'error');
                                    $(sessionSelectId).html('<option value="">Erreur de chargement...</option>');
                                    if (dateInputId) $(dateInputId).val('');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur lors du chargement des sessions : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                                $(sessionSelectId).html('<option value="">Erreur de chargement...</option>');
                                if (dateInputId) $(dateInputId).val('');
                            }
                        });
                    }

                    // Create modal: Load sessions when formation changes
                    $('#create_formation_id').on('change', function() {
                        const formationId = $(this).val();
                        loadSessions(formationId, '#create_session_id', null, '#create_date_presence');
                    });

                    // Create modal: Update date when session changes
                    $('#create_session_id').on('change', function() {
                        const selectedOption = $(this).find('option:selected');
                        $('#create_date_presence').val(selectedOption.data('date') || '');
                    });

                    // Create attendance
                    $('#create-attendance-form').on('submit', function(e) {
                        e.preventDefault();
                        const formData = $(this).serialize();
                        if (!$(this).find('[name="member_id"]').val().trim() || !$(this).find('[name="formation_id"]').val().trim() || !$(this).find('[name="session_id"]').val().trim()) {
                            showNotification('Les champs Membre, Formation et Session sont requis.', 'warning');
                            return;
                        }
                        $.ajax({
                            url: 'attendances.php',
                            type: 'POST',
                            data: formData,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification(response.message || 'Présence enregistrée avec succès.', 'success');
                                    $('#createAttendanceModal').modal('hide');
                                    $('#create-attendance-form')[0].reset();
                                    $('#create_session_id').html('<option value="">Sélectionner une formation d\'abord...</option>');
                                    $('#create_date_presence').val('');
                                    location.reload();
                                } else {
                                    showNotification('Erreur : ' + response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur lors de la création : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // View attendance
                    $(document).on('click', '.view-attendance', function() {
                        const attendanceId = $(this).data('attendance-id');
                        $.ajax({
                            url: 'attendances.php',
                            type: 'POST',
                            data: { action: 'get_attendance', attendance_id: attendanceId, csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>' },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const attendance = response.attendance;
                                    $('#view_id').text(attendance.id);
                                    $('#view_member_nom').text(attendance.member_nom + ' ' + attendance.member_prenom);
                                    $('#view_formation_nom').text(attendance.formation_nom);
                                    $('#view_promotion').text(attendance.promotion);
                                    $('#view_session_nom').text(attendance.session_nom);
                                    $('#view_date_presence').text(attendance.date_presence);
                                    $('#view_present').text(attendance.present ? 'Oui' : 'Non');
                                    $('#view_points').text(attendance.points ?? '0');
                                    $('#viewAttendanceModal').modal('show');
                                } else {
                                    showNotification('Erreur : ' + response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // Edit attendance
                    $(document).on('click', '.edit-attendance', function() {
                        const attendanceId = $(this).data('attendance-id');
                        $.ajax({
                            url: 'attendances.php',
                            type: 'POST',
                            data: { action: 'get_attendance', attendance_id: attendanceId, csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>' },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const attendance = response.attendance;
                                    $('#edit_id').val(attendance.id);
                                    $('#edit_member_id').val(attendance.member_id);
                                    $('#edit_formation_id').val(attendance.formation_id);
                                    $('#edit_present').prop('checked', attendance.present == 1);
                                    loadSessions(attendance.formation_id, '#edit_session_id', attendance.session_id, '#edit_date_presence');
                                    $('#editAttendanceModal').modal('show');
                                } else {
                                    showNotification('Erreur : ' + response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // Edit modal: Load sessions when formation changes
                    $('#edit_formation_id').on('change', function() {
                        const formationId = $(this).val();
                        loadSessions(formationId, '#edit_session_id', null, '#edit_date_presence');
                    });

                    // Edit modal: Update date when session changes
                    $('#edit_session_id').on('change', function() {
                        const selectedOption = $(this).find('option:selected');
                        $('#edit_date_presence').val(selectedOption.data('date') || '');
                    });

                    // Update attendance
                    $('#edit-attendance-form').on('submit', function(e) {
                        e.preventDefault();
                        const formData = $(this).serialize();
                        if (!$(this).find('[name="member_id"]').val().trim() || !$(this).find('[name="formation_id"]').val().trim() || !$(this).find('[name="session_id"]').val().trim()) {
                            showNotification('Les champs Membre, Formation et Session sont requis.', 'warning');
                            return;
                        }
                        $.ajax({
                            url: 'attendances.php',
                            type: 'POST',
                            data: formData,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification(response.message || 'Présence mise à jour avec succès.', 'success');
                                    $('#editAttendanceModal').modal('hide');
                                    location.reload();
                                } else {
                                    showNotification('Erreur : ' + response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                showNotification('Erreur serveur lors de la mise à jour : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                            }
                        });
                    });

                    // Delete attendance
                    $(document).on('click', '.delete-attendance', function() {
                        const attendanceId = $(this).data('attendance-id');
                        if (confirm('Êtes-vous sûr de vouloir supprimer cette présence ?')) {
                            $.ajax({
                                url: 'attendances.php',
                                type: 'POST',
                                data: { action: 'delete', attendance_id: attendanceId, csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>' },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        showNotification(response.message, 'success');
                                        $('#attendance-' + attendanceId).remove();
                                        location.reload();
                                    } else {
                                        showNotification('Erreur : ' + response.message, 'error');
                                    }
                                },
                                error: function(xhr) {
                                    showNotification('Erreur serveur lors de la suppression : ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
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
                        location.href = 'attendances.php?' + $(this).serialize();
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>