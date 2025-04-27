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

// Initialize messages
$error = null;
$success = null;

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . $e->getMessage();
}

// Check if sessions table has responsable_id column
try {
    $db->query("SELECT responsable_id FROM sessions LIMIT 1");
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Unknown column \'responsable_id\'') !== false) {
        $error = "Erreur : La colonne 'responsable_id' est manquante dans la table 'sessions'. Exécutez : ALTER TABLE sessions ADD COLUMN responsable_id INT UNSIGNED NOT NULL, ADD CONSTRAINT sessions_ibfk_2 FOREIGN KEY (responsable_id) REFERENCES members(id) ON DELETE RESTRICT;";
    } else {
        $error = "Erreur vérification table sessions : " . $e->getMessage();
    }
}

// Create Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $promotion_id = trim($_POST['promotion_id'] ?? '');
        $date_session = trim($_POST['date_session'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $responsable_id = trim($_POST['responsable_id'] ?? '');

        if (empty($promotion_id) || empty($date_session) || empty($responsable_id)) {
            throw new Exception("Les champs Promotion, Date et Responsable sont requis.");
        }
        // Verify promotion_id exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$promotion_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Promotion invalide.");
        }
        // Verify responsable_id is valid (temporary: accept any member)
        // TODO: Replace with correct diacre check, e.g., WHERE role = 'diacre' or equivalent
        $stmt = $db->prepare("SELECT id FROM members WHERE id = ?");
        $stmt->execute([$responsable_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Responsable invalide.");
        }

        $stmt = $db->prepare("
            INSERT INTO sessions (promotion_id, date_session, description, responsable_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$promotion_id, $date_session, $description, $responsable_id]);

        $session_id = $db->lastInsertId();

        logAction($_SESSION['user_id'], "Création session: $session_id (Promotion ID: $promotion_id, Date: $date_session)", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Session créée avec succès (ID: $session_id)."]);
    } catch (Exception $e) {
        error_log("Create session error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur création session: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur création : " . $e->getMessage()]);
    }
    exit;
}

// Update Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $promotion_id = trim($_POST['promotion_id'] ?? '');
        $date_session = trim($_POST['date_session'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $responsable_id = trim($_POST['responsable_id'] ?? '');

        error_log("Update session inputs: id=$id, promotion_id=$promotion_id, date_session=$date_session, description=$description, responsable_id=$responsable_id");

        if (empty($id) || empty($promotion_id) || empty($date_session) || empty($responsable_id)) {
            throw new Exception("Les champs ID, Promotion, Date et Responsable sont requis.");
        }
        // Verify promotion_id exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$promotion_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Promotion invalide.");
        }
        // Verify responsable_id is valid (temporary: accept any member)
        // TODO: Replace with correct diacre check, e.g., WHERE role = 'diacre' or equivalent
        $stmt = $db->prepare("SELECT id FROM members WHERE id = ?");
        $stmt->execute([$responsable_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Responsable invalide.");
        }

        $stmt = $db->prepare("
            UPDATE sessions SET promotion_id = ?, date_session = ?, description = ?, responsable_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$promotion_id, $date_session, $description, $responsable_id, $id]);

        logAction($_SESSION['user_id'], "Mise à jour session: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Session mise à jour avec succès (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Update session error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur mise à jour session: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur mise à jour : " . $e->getMessage()]);
    }
    exit;
}

// Delete Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        $id = $_POST['session_id'] ?? '';
        if (empty($id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$id]);

        logAction($_SESSION['user_id'], "Suppression session: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Session supprimée (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Delete session error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur suppression session: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Get Session Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_session') {
    try {
        $session_id = $_POST['session_id'] ?? '';
        if (empty($session_id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("
            SELECT s.*, f.nom AS formation_nom, f.promotion, f.active AS formation_active, m.nom AS responsable_nom
            FROM sessions s
            JOIN formations f ON s.promotion_id = f.id
            JOIN members m ON s.responsable_id = m.id
            WHERE s.id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new Exception("Session introuvable.");

        echo json_encode(['success' => true, 'session' => $session]);
    } catch (Exception $e) {
        error_log("Get session error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Fetch all sessions
try {
    $sessions = $db->query("
        SELECT s.*, f.nom AS formation_nom, f.promotion, f.active AS formation_active, m.nom AS responsable_nom
        FROM sessions s
        JOIN formations f ON s.promotion_id = f.id
        JOIN members m ON s.responsable_id = m.id
        ORDER BY s.date_session DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération sessions : " . $e->getMessage();
    $sessions = [];
}

// Fetch formations for dropdown
try {
    $formations = $db->query("SELECT id, nom, promotion, active FROM formations ORDER BY promotion DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération formations : " . $e->getMessage();
    $formations = [];
}

// Fetch diacres for responsable dropdown (temporary: fetch all members)
try {
    // TODO: Replace with correct query to fetch diacres, e.g., WHERE role = 'diacre' or equivalent
    $diacres = $db->query("SELECT id, nom FROM members ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($diacres)) {
        $error = "Aucun diacre disponible pour le champ Responsable. Ajoutez des membres à la table 'members'.";
    }
} catch (Exception $e) {
    $error = "Erreur récupération diacres : " . $e->getMessage();
    $diacres = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Sessions</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
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
            font-size: 12px;
            margin-right: 5px;
            padding: 4px 8px;
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
                        <li class="nav-item"><a href="formations.php" class="nav-link">Membres</a></li>
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

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#createSessionModal" <?php echo empty($diacres) ? 'disabled' : ''; ?>>Créer une Session</button>
                    <?php if (empty($diacres)): ?>
                        <small class="text-danger">Aucun diacre disponible. Ajoutez des membres à la table 'members'.</small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <table id="session-table" class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Formation</th>
                        <th>Promotion</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Responsable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr id="session-<?php echo htmlspecialchars($session['id']); ?>">
                            <td><?php echo htmlspecialchars($session['id']); ?></td>
                            <td><?php echo htmlspecialchars($session['formation_nom']) . ($session['formation_active'] ? ' (Active)' : ''); ?></td>
                            <td><?php echo htmlspecialchars($session['promotion']); ?></td>
                            <td><?php echo htmlspecialchars($session['date_session']); ?></td>
                            <td><?php echo htmlspecialchars($session['description'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($session['responsable_nom']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info view-session" data-session-id="<?php echo htmlspecialchars($session['id']); ?>">Voir</button>
                                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                    <button class="btn btn-sm btn-warning edit-session" data-session-id="<?php echo htmlspecialchars($session['id']); ?>" <?php echo empty($diacres) ? 'disabled' : ''; ?>>Modifier</button>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <button class="btn btn-sm btn-danger delete-session" data-session-id="<?php echo htmlspecialchars($session['id']); ?>">Supprimer</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
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
                                            <label for="create_promotion_id">Promotion *</label>
                                            <select class="form-control" id="create_promotion_id" name="promotion_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' (' . $formation['promotion'] . ')' . ($formation['active'] ? ' (Active)' : '')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_date_session">Date *</label>
                                            <input type="date" class="form-control" id="create_date_session" name="date_session" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_responsable_id">Responsable *</label>
                                            <select class="form-control" id="create_responsable_id" name="responsable_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($diacres as $diacre): ?>
                                                    <option value="<?php echo htmlspecialchars($diacre['id']); ?>">
                                                        <?php echo htmlspecialchars($diacre['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_description">Description</label>
                                            <input type="text" class="form-control" id="create_description" name="description" placeholder="ex: Introduction à la session">
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
                                            <label for="edit_promotion_id">Promotion *</label>
                                            <select class="form-control" id="edit_promotion_id" name="promotion_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($formations as $formation): ?>
                                                    <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                        <?php echo htmlspecialchars($formation['nom'] . ' (' . $formation['promotion'] . ')' . ($formation['active'] ? ' (Active)' : '')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_date_session">Date *</label>
                                            <input type="date" class="form-control" id="edit_date_session" name="date_session" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_responsable_id">Responsable *</label>
                                            <select class="form-control" id="edit_responsable_id" name="responsable_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($diacres as $diacre): ?>
                                                    <option value="<?php echo htmlspecialchars($diacre['id']); ?>">
                                                        <?php echo htmlspecialchars($diacre['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_description">Description</label>
                                            <input type="text" class="form-control" id="edit_description" name="description">
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
                            <p><strong>Formation:</strong> <span id="view_formation_nom"></span></p>
                            <p><strong>Promotion:</strong> <span id="view_promotion"></span></p>
                            <p><strong>Date:</strong> <span id="view_date_session"></span></p>
                            <p><strong>Description:</strong> <span id="view_description"></span></p>
                            <p><strong>Responsable:</strong> <span id="view_responsable_nom"></span></p>
                            <p><strong>Créé le:</strong> <span id="view_created_at"></span></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="assets/bootstrap/js/bootstrap.min.js"></script>
            <script>
                $(document).ready(function() {
                    // Create session
                    $('#create-session-form').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: $(this).serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message || 'Session créée avec succès.');
                                    $('#createSessionModal').modal('hide');
                                    $('#create-session-form')[0].reset();
                                    location.reload();
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Erreur serveur lors de la création : ' + (xhr.responseJSON?.message || error));
                            }
                        });
                    });

                    // View session
                    $(document).on('click', '.view-session', function() {
                        var sessionId = $(this).data('session-id');
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: { action: 'get_session', session_id: sessionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var session = response.session;
                                    $('#view_id').text(session.id);
                                    $('#view_formation_nom').text(session.formation_nom + (session.formation_active ? ' (Active)' : ''));
                                    $('#view_promotion').text(session.promotion);
                                    $('#view_date_session').text(session.date_session);
                                    $('#view_description').text(session.description || '-');
                                    $('#view_responsable_nom').text(session.responsable_nom);
                                    $('#view_created_at').text(session.created_at);
                                    $('#viewSessionModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || error));
                            }
                        });
                    });

                    // Edit session
                    $(document).on('click', '.edit-session', function() {
                        var sessionId = $(this).data('session-id');
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: { action: 'get_session', session_id: sessionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var session = response.session;
                                    $('#edit_id').val(session.id);
                                    $('#edit_promotion_id').val(session.promotion_id);
                                    $('#edit_date_session').val(session.date_session);
                                    $('#edit_description').val(session.description);
                                    $('#edit_responsable_id').val(session.responsable_id);
                                    $('#editSessionModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || error));
                            }
                        });
                    });

                    // Update session
                    $('#edit-session-form').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: 'sessions.php',
                            type: 'POST',
                            data: $(this).serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message || 'Session mise à jour avec succès.');
                                    $('#editSessionModal').modal('hide');
                                    location.reload();
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Erreur serveur lors de la mise à jour : ' + (xhr.responseJSON?.message || error));
                            }
                        });
                    });

                    // Delete session
                    $(document).on('click', '.delete-session', function() {
                        var sessionId = $(this).data('session-id');
                        if (confirm('Êtes-vous sûr de vouloir supprimer cette session ?')) {
                            $.ajax({
                                url: 'sessions.php',
                                type: 'POST',
                                data: { action: 'delete', session_id: sessionId },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        alert(response.message);
                                        $('#session-' + sessionId).remove();
                                    } else {
                                        alert('Erreur : ' + response.message);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    alert('Erreur serveur lors de la suppression : ' + (xhr.responseJSON?.message || error));
                                }
                            });
                        }
                    });

                    // Modal z-index fix
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