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
logAction($_SESSION['user_id'], "Accès à sessions.php", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));

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

// Fetch formations for dropdown
try {
    $stmt = $db->prepare("
        SELECT id, nom, promotion
        FROM formations
        ORDER BY promotion DESC, nom
    ");
    $stmt->execute();
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération formations : " . $e->getMessage();
}

// Fetch teachers for dropdown
try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name
        FROM teachers
        ORDER BY last_name, first_name
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération enseignants : " . $e->getMessage();
}

// Status translations
$status_labels = [
    'planned' => 'Planifié',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé'
];

// Create Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $formation_id = trim($_POST['formation_id'] ?? '');
        $teacher_id = trim($_POST['teacher_id'] ?? '') ?: null;
        $nom = trim($_POST['nom'] ?? '');
        $date_session = trim($_POST['date_session'] ?? '') ?: null;
        $status = trim($_POST['status'] ?? 'planned');

        if (empty($formation_id) || empty($nom)) {
            throw new Exception("Les champs Formation et Nom de la session sont requis.");
        }
        if (!in_array($status, ['planned', 'completed', 'cancelled'])) {
            throw new Exception("Statut invalide.");
        }

        // Check if formation exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Formation invalide.");
        }

        // Check if teacher exists (if provided)
        if ($teacher_id) {
            $stmt = $db->prepare("SELECT id FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Enseignant invalide.");
            }
        }

        $stmt = $db->prepare("
            INSERT INTO sessions (formation_id, teacher_id, nom, date_session, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $formation_id,
            $teacher_id,
            $nom,
            $date_session,
            $status
        ]);

        $session_id = $db->lastInsertId();

        logAction($_SESSION['user_id'], "Création session: $session_id (Formation: $formation_id, Nom: $nom, Statut: $status, Enseignant: " . ($teacher_id ?: 'Aucun') . ")", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Session créée avec succès (ID: $session_id)."]);
    } catch (Exception $e) {
        error_log("Create session error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur création session: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Update Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $formation_id = trim($_POST['formation_id'] ?? '');
        $teacher_id = trim($_POST['teacher_id'] ?? '') ?: null;
        $nom = trim($_POST['nom'] ?? '');
        $date_session = trim($_POST['date_session'] ?? '') ?: null;
        $status = trim($_POST['status'] ?? 'planned');

        if (empty($id) || empty($formation_id) || empty($nom)) {
            throw new Exception("Les champs ID, Formation et Nom de la session sont requis.");
        }
        if (!in_array($status, ['planned', 'completed', 'cancelled'])) {
            throw new Exception("Statut invalide.");
        }

        // Check if session exists
        $stmt = $db->prepare("SELECT id FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new Exception("Session introuvable.");
        }

        // Check if formation exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Formation invalide.");
        }

        // Check if teacher exists (if provided)
        if ($teacher_id) {
            $stmt = $db->prepare("SELECT id FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Enseignant invalide.");
            }
        }

        $stmt = $db->prepare("
            UPDATE sessions 
            SET formation_id = ?, teacher_id = ?, nom = ?, date_session = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $formation_id,
            $teacher_id,
            $nom,
            $date_session,
            $status,
            $id
        ]);

        logAction($_SESSION['user_id'], "Mise à jour session: $id (Formation: $formation_id, Statut: $status, Enseignant: " . ($teacher_id ?: 'Aucun') . ")", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Session mise à jour avec succès (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Update session error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur mise à jour session: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Delete Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        $id = $_POST['session_id'] ?? '';
        if (empty($id)) throw new Exception("ID manquant.");

        // Check if session is used in formation_attendance
        $stmt = $db->prepare("SELECT COUNT(*) AS count FROM formation_attendance WHERE formation_id = (SELECT formation_id FROM sessions WHERE id = ?)");
        $stmt->execute([$id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("Impossible de supprimer : cette session est liée à des présences.");
        }

        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$id]);

        logAction($_SESSION['user_id'], "Suppression session: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Session supprimée (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Delete session error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur suppression session: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get Session Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_session') {
    try {
        $session_id = $_POST['session_id'] ?? '';
        if (empty($session_id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("
            SELECT s.*, 
                   f.nom AS formation_nom, f.promotion AS formation_promotion,
                   t.first_name AS teacher_first_name, t.last_name AS teacher_last_name
            FROM sessions s
            LEFT JOIN formations f ON s.formation_id = f.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            WHERE s.id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new Exception("Session introuvable.");

        // Ensure valid status
        if (!isset($status_labels[$session['status']])) {
            $session['status'] = 'planned';
        }

        echo json_encode(['success' => true, 'session' => $session]);
    } catch (Exception $e) {
        error_log("Get session error: " . $e->getMessage());
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
        $where[] = "s.status = ?";
        $params[] = $filter_status;
    }

    $sql = "
        SELECT s.*, 
               f.nom AS formation_nom, f.promotion AS formation_promotion,
               t.first_name AS teacher_first_name, t.last_name AS teacher_last_name
        FROM sessions s
        LEFT JOIN formations f ON s.formation_id = f.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
    ";
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY s.date_session DESC, s.nom";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['sessions' => $sessions]);
    } catch (Exception $e) {
        error_log("AJAX fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => "Erreur récupération sessions : " . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Sessions</title>
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
                        <li class="nav-item"><a href="sessions.php" class="nav-link active">Sessions</a></li>
                        <li class="nav-item"><a href="formations.php" class="nav-link">Présences</a></li>
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
            <h1 class="mb-4">Gestion des Sessions</h1>

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
                            <a href="sessions.php" class="btn btn-secondary ml-2">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mb-3">
                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#createSessionModal">Créer une Session</button>
                <?php endif; ?>
            </div>

            <table id="session-table" class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Formation</th>
                        <th>Nom</th>
                        <th>Enseignant</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- Create Session Modal -->
            <div class="modal fade" id="createSessionModal" tabindex="-1" role="dialog" aria-labelledby="createSessionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createSessionModalLabel">Créer une Session</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="create-session-form">
                            <input type="hidden" name="action" value="create">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_formation_id">Formation *</label>
                                            <select class="form-control" id="create_formation_id" name="formation_id" required>
                                                <option value="">Sélectionnez une formation</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_nom">Nom *</label>
                                            <input type="text" class="form-control" id="create_nom" name="nom" required placeholder="ex: Chapitre 1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_teacher_id">Enseignant</label>
                                            <select class="form-control" id="create_teacher_id" name="teacher_id">
                                                <option value="">Aucun</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_date_session">Date</label>
                                            <input type="date" class="form-control" id="create_date_session" name="date_session">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_status">Statut *</label>
                                            <select class="form-control" id="create_status" name="status" required>
                                                <?php foreach ($status_labels as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'planned' ? 'selected' : ''; ?>>
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

            <!-- Edit Session Modal -->
            <div class="modal fade" id="editSessionModal" tabindex="-1" role="dialog" aria-labelledby="editSessionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="editSessionModalLabel">Modifier la Session</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="edit-session-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_formation_id">Formation *</label>
                                            <select class="form-control" id="edit_formation_id" name="formation_id" required>
                                                <option value="">Sélectionnez une formation</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_nom">Nom *</label>
                                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_teacher_id">Enseignant</label>
                                            <select class="form-control" id="edit_teacher_id" name="teacher_id">
                                                <option value="">Aucun</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_date_session">Date</label>
                                            <input type="date" class="form-control" id="edit_date_session" name="date_session">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_status">Statut *</label>
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

            <!-- View Session Modal -->
            <div class="modal fade" id="viewSessionModal" tabindex="-1" role="dialog" aria-labelledby="viewSessionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewSessionModalLabel">Détails de la Session</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>ID:</strong> <span id="view_id"></span></p>
                            <p><strong>Formation:</strong> <span id="view_formation"></span></p>
                            <p><strong>Nom:</strong> <span id="view_nom"></span></p>
                            <p><strong>Enseignant:</strong> <span id="view_teacher"></span></p>
                            <p><strong>Date:</strong> <span id="view_date_session"></span></p>
                            <p><strong>Statut:</strong> <span id="view_status"></span></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="assets/bootstrap/js/bootstrap.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
            <script>
                $(document).ready(function() {
                    // Initialize DataTable with AJAX
                    const table = $('#session-table').DataTable({
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                        },
                        order: [[4, 'desc']],
                        autoWidth: false,
                        columnDefs: [
                            { width: '150px', targets: 6, className: 'text-center' },
                            { width: '10%', targets: 0 },
                            { width: '20%', targets: 1 },
                            { width: '20%', targets: 2 },
                            { width: '20%', targets: 3 },
                            { width: '15%', targets: 4 },
                            { width: '15%', targets: 5 }
                        ],
                        ajax: {
                            url: 'sessions.php?ajax=1',
                            type: 'GET',
                            data: function(d) {
                                d.filter_status = $('#filter_status').val();
                            },
                            dataSrc: 'sessions'
                        },
                        columns: [
                            { data: 'id' },
                            { 
                                data: null, 
                                render: function(data) { 
                                    return data.formation_nom && data.formation_promotion 
                                        ? `${data.formation_nom} - ${data.formation_promotion}` 
                                        : '-'; 
                                } 
                            },
                            { data: 'nom' },
                            { 
                                data: null, 
                                render: function(data) { 
                                    return data.teacher_first_name && data.teacher_last_name 
                                        ? `${data.teacher_first_name} ${data.teacher_last_name}` 
                                        : 'Aucun'; 
                                } 
                            },
                            { data: 'date_session', render: function(data) { return data || '-'; } },
                            { 
                                data: 'status', 
                                render: function(data) { 
                                    const labels = <?php echo json_encode($status_labels); ?>;
                                    return labels[data] || 'Inconnu'; 
                                } 
                            },
                            {
                                data: null,
                                render: function(data, type, row) {
                                    let actions = `
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-info view-session" data-session-id="${row.id}">Voir</button>
                                    `;
                                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                        actions += `<button class="btn btn-sm btn-warning edit-session" data-session-id="${row.id}">Modifier</button>`;
                                    <?php endif; ?>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        actions += `<button class="btn btn-sm btn-danger delete-session" data-session-id="${row.id}">Supprimer</button>`;
                                    <?php endif; ?>
                                    actions += `</div>`;
                                    return actions;
                                }
                            }
                        ]
                    });

                    // Show notification
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

                    // Create session form submission
                    $('#create-session-form').on('submit', function(e) {
                        e.preventDefault();
                        const formData = $(this).serialize();
                        if (!$('#create_formation_id').val().trim() || !$('#create_nom').val().trim()) {
                            showNotification('Les champs Formation et Nom sont requis.', 'warning');
                            return;
                        }
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: formData,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification(response.message || 'Session créée avec succès.', 'success');
                                    $('#createSessionModal').modal('hide');
                                    $('#create-session-form')[0].reset();
                                    table.ajax.reload();
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showNotification('Erreur serveur lors de la création : ' + (xhr.responseJSON?.message || error), 'error');
                            }
                        });
                    });

                    // View session
                    $(document).on('click', '.view-session', function() {
                        const sessionId = $(this).data('session-id');
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: { action: 'get_session', session_id: sessionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const session = response.session;
                                    $('#view_id').text(session.id);
                                    $('#view_formation').text(session.formation_nom && session.formation_promotion ? `${session.formation_nom} - ${session.formation_promotion}` : '-');
                                    $('#view_nom').text(session.nom);
                                    $('#view_teacher').text(session.teacher_first_name && session.teacher_last_name ? `${session.teacher_first_name} ${session.teacher_last_name}` : 'Aucun');
                                    $('#view_date_session').text(session.date_session || '-');
                                    $('#view_status').text(<?php echo json_encode($status_labels); ?>[session.status] || 'Inconnu');
                                    $('#viewSessionModal').modal('show');
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showNotification('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || error), 'error');
                            }
                        });
                    });

                    // Edit session
                    $(document).on('click', '.edit-session', function() {
                        const sessionId = $(this).data('session-id');
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: { action: 'get_session', session_id: sessionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    const session = response.session;
                                    $('#edit_id').val(session.id);
                                    $('#edit_formation_id').val(session.formation_id);
                                    $('#edit_nom').val(session.nom);
                                    $('#edit_teacher_id').val(session.teacher_id || '');
                                    $('#edit_date_session').val(session.date_session || '');
                                    $('#edit_status').val(session.status);
                                    $('#editSessionModal').modal('show');
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showNotification('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || error), 'error');
                            }
                        });
                    });

                    // Update session
                    $('#edit-session-form').on('submit', function(e) {
                        e.preventDefault();
                        const formData = $(this).serialize();
                        if (!$('#edit_formation_id').val().trim() || !$('#edit_nom').val().trim()) {
                            showNotification('Les champs Formation et Nom sont requis.', 'warning');
                            return;
                        }
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: formData,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification(response.message || 'Session mise à jour avec succès.', 'success');
                                    $('#editSessionModal').modal('hide');
                                    table.ajax.reload();
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showNotification('Erreur serveur lors de la mise à jour : ' + (xhr.responseJSON?.message || error), 'error');
                            }
                        });
                    });

                    // Delete session
                    $(document).on('click', '.delete-session', function() {
                        const sessionId = $(this).data('session-id');
                        if (confirm('Êtes-vous sûr de vouloir supprimer cette session ?')) {
                            $.ajax({
                                url: 'sessions.php',
                                type: 'POST',
                                data: { action: 'delete', session_id: sessionId },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        showNotification(response.message, 'success');
                                        table.ajax.reload();
                                    } else {
                                        showNotification(response.message, 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    showNotification('Erreur serveur lors de la suppression : ' + (xhr.responseJSON?.message || error), 'error');
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