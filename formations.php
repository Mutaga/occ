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
logAction(getCurrentUserId(), "Accès à formations.php", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));

// Initialize messages
$error = null;
$success = null;

// Determine active section
$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING) ?? 'dashboard';

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
}

// Dashboard Section Logic
if ($section === 'dashboard') {
    try {
        // Fetch basic stats
        $stmt = $db->query("SELECT COUNT(*) as total FROM formations");
        $formation_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM members");
        $member_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM formations WHERE status = 'active'");
        $active_formations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Fetch attendance summary
        $stmt = $db->query("SELECT a.*, m.nom, m.prenom 
                            FROM attendance_summary a 
                            JOIN members m ON a.member_id = m.id");
        $attendance_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Erreur récupération données tableau de bord : " . htmlspecialchars($e->getMessage());
        $attendance_summary = [];
    }
}

// Gestion des Présences Section Logic
if ($section === 'attendances') {
    $formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;
    $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
    $date_presence = date('Y-m-d');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance']) && in_array($_SESSION['role'], ['admin', 'diacre'])) {
        try {
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $db->beginTransaction();
            foreach ($_POST['attendance'] as $member_id => $present) {
                $present = intval($present);
                $stmt = $db->prepare("INSERT INTO formation_attendance (member_id, formation_id, session_id, date_presence, present) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE present = ?");
                $stmt->execute([$member_id, $formation_id, $session_id, $date_presence, $present, $present]);
            }
            $db->commit();
            $success = "Présences enregistrées avec succès !";
            
            // Log action
            $action = "Enregistrement présences pour formation ID: $formation_id, session ID: $session_id";
            logAction(getCurrentUserId(), $action, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur enregistrement présences : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch formations
    try {
        $stmt = $db->query("SELECT id, nom, promotion FROM formations WHERE status IN ('active', 'pending')");
        $attendances_formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Erreur récupération formations : " . htmlspecialchars($e->getMessage());
        $attendances_formations = [];
    }

    // Fetch sessions for the selected formation
    $attendances_sessions = [];
    if ($formation_id) {
        try {
            $stmt = $db->prepare("SELECT id, nom, date_session FROM sessions WHERE formation_id = ? AND status = 'planned'");
            $stmt->execute([$formation_id]);
            $attendances_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur récupération sessions : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch enrolled members for the selected formation
    $attendances_members = [];
    if ($formation_id) {
        try {
            $stmt = $db->prepare("SELECT m.id, m.nom, m.prenom 
                                  FROM members m 
                                  JOIN member_formations mf ON m.id = mf.member_id 
                                  WHERE mf.formation_id = ?");
            $stmt->execute([$formation_id]);
            $attendances_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur récupération membres : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch existing attendance for the selected session and date
    $attendances_records = [];
    if ($formation_id && $session_id) {
        try {
            $stmt = $db->prepare("SELECT member_id, present 
                                  FROM formation_attendance 
                                  WHERE formation_id = ? AND session_id = ? AND date_presence = ?");
            $stmt->execute([$formation_id, $session_id, $date_presence]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $attendances_records[$row['member_id']] = $row['present'];
            }
        } catch (PDOException $e) {
            $error = "Erreur récupération présences : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch attendance history for the selected formation
    $attendances_history = [];
    if ($formation_id) {
        try {
            $stmt = $db->prepare("SELECT fa.member_id, m.nom, m.prenom, fa.session_id, s.nom AS session_name, fa.date_presence, fa.present 
                                  FROM formation_attendance fa 
                                  JOIN members m ON fa.member_id = m.id 
                                  JOIN sessions s ON fa.session_id = s.id 
                                  WHERE fa.formation_id = ? 
                                  ORDER BY fa.date_presence DESC");
            $stmt->execute([$formation_id]);
            $attendances_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur récupération historique présences : " . htmlspecialchars($e->getMessage());
        }
    }
}

// Présences (Détaillé) Section Logic
if ($section === 'presences_detailed') {
    $formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;
    $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
    $date_presence = date('Y-m-d');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance']) && in_array($_SESSION['role'], ['admin', 'diacre'])) {
        try {
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $db->beginTransaction();
            foreach ($_POST['attendance'] as $member_id => $present) {
                $present = intval($present);
                $stmt = $db->prepare("INSERT INTO formation_attendance (member_id, formation_id, session_id, date_presence, present) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE present = ?");
                $stmt->execute([$member_id, $formation_id, $session_id, $date_presence, $present, $present]);
            }
            $db->commit();
            $success = "Présences enregistrées avec succès !";
            
            // Log action
            $action = "Enregistrement présences détaillées pour formation ID: $formation_id, session ID: $session_id";
            logAction(getCurrentUserId(), $action, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur enregistrement présences : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch formations
    try {
        $stmt = $db->query("SELECT id, nom, promotion FROM formations WHERE status IN ('active', 'pending')");
        $detailed_formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Erreur récupération formations : " . htmlspecialchars($e->getMessage());
        $detailed_formations = [];
    }

    // Fetch sessions for the selected formation
    $detailed_sessions = [];
    if ($formation_id) {
        try {
            $stmt = $db->prepare("SELECT id, nom, date_session FROM sessions WHERE formation_id = ? AND status = 'planned'");
            $stmt->execute([$formation_id]);
            $detailed_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur récupération sessions : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch enrolled members for the selected formation
    $detailed_members = [];
    if ($formation_id) {
        try {
            $stmt = $db->prepare("SELECT m.id, m.nom, m.prenom 
                                  FROM members m 
                                  JOIN member_formations mf ON m.id = mf.member_id 
                                  WHERE mf.formation_id = ?");
            $stmt->execute([$formation_id]);
            $detailed_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur récupération membres : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch existing attendance for the selected session and date
    $detailed_records = [];
    if ($formation_id && $session_id) {
        try {
            $stmt = $db->prepare("SELECT member_id, present 
                                  FROM formation_attendance 
                                  WHERE formation_id = ? AND session_id = ? AND date_presence = ?");
            $stmt->execute([$formation_id, $session_id, $date_presence]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $detailed_records[$row['member_id']] = $row['present'];
            }
        } catch (PDOException $e) {
            $error = "Erreur récupération présences : " . htmlspecialchars($e->getMessage());
        }
    }

    // Fetch attendance history for the selected formation
    $detailed_history = [];
    if ($formation_id) {
        try {
            $stmt = $db->prepare("SELECT fa.member_id, m.nom, m.prenom, fa.session_id, s.nom AS session_name, fa.date_presence, fa.present 
                                  FROM formation_attendance fa 
                                  JOIN members m ON fa.member_id = m.id 
                                  JOIN sessions s ON fa.session_id = s.id 
                                  WHERE fa.formation_id = ? 
                                  ORDER BY fa.date_presence DESC");
            $stmt->execute([$formation_id]);
            $detailed_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur récupération historique présences : " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Présences</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
        .table th, .table td {
            vertical-align: middle;
        }
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #e9ecef;
            border-bottom: 1px solid #dee2e6;
        }
        .toast-container {
            z-index: 1055;
        }
        .toast {
            border-radius: 8px;
            min-width: 300px;
        }
        .badge-present {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
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
                <li class="nav-item">
                    <a href="?section=dashboard" class="nav-link <?php echo $section === 'dashboard' ? 'active' : ''; ?>">Tableau de Bord</a>
                </li>
                <li class="nav-item">
                    <a href="members.php" class="nav-link">Membres</a>
                </li>
                <li class="nav-item">
                    <a href="children.php" class="nav-link">Enfants</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">Formations</a>
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a href="promotions.php" class="nav-link">Promotions</a></li>
                        <li class="nav-item"><a href="?section=attendances" class="nav-link <?php echo $section === 'attendances' ? 'active' : ''; ?>">Gestion des Présences</a></li>
                        <li class="nav-item"><a href="sessions.php" class="nav-link">Sessions</a></li>
                        <li class="nav-item"><a href="?section=presences_detailed" class="nav-link <?php echo $section === 'presences_detailed' ? 'active' : ''; ?>">Présences (Détaillé)</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="anniversaires.php" class="nav-link">Anniversaires</a>
                </li>
                <li class="nav-item">
                    <a href="oikos.php" class="nav-link">Oikos</a>
                </li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a href="logs.php" class="nav-link">Logs</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="auth.php?logout" class="nav-link">Déconnexion</a>
                </li>
            </ul>
        </div>
        <div class="content flex-grow-1">
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

            <!-- Dashboard Section -->
            <?php if ($section === 'dashboard'): ?>
                <h1 class="mb-4">Tableau de Bord</h1>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="h5 mb-0">Total Formations</h2>
                            </div>
                            <div class="card-body">
                                <p class="display-6"><?php echo isset($formation_count) ? $formation_count : 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="h5 mb-0">Total Membres</h2>
                            </div>
                            <div class="card-body">
                                <p class="display-6"><?php echo isset($member_count) ? $member_count : 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="h5 mb-0">Formations Actives</h2>
                            </div>
                            <div class="card-body">
                                <p class="display-6"><?php echo isset($active_formations) ? $active_formations : 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Résumé des Présences</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Membre</th>
                                        <th>Formation</th>
                                        <th>Promotion</th>
                                        <th>Jours Présents</th>
                                        <th>Total Jours</th>
                                        <th>Score Présence (/50)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($attendance_summary): ?>
                                        <?php foreach ($attendance_summary as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></td>
                                                <td><?php echo htmlspecialchars($row['formation_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['promotion']); ?></td>
                                                <td><?php echo htmlspecialchars($row['days_attended']); ?></td>
                                                <td><?php echo htmlspecialchars($row['total_days']); ?></td>
                                                <td><?php echo number_format($row['attendance_score'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Aucun enregistrement de présence trouvé.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Gestion des Présences Section -->
            <?php if ($section === 'attendances'): ?>
                <h1 class="mb-4">Gestion des Présences</h1>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Sélectionner une Formation</h2>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="section" value="attendances">
                            <div class="mb-3">
                                <label for="formation_id_attendances" class="form-label">Formation</label>
                                <select name="formation_id" id="formation_id_attendances" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Sélectionner une Formation --</option>
                                    <?php foreach ($attendances_formations as $formation): ?>
                                        <option value="<?php echo $formation['id']; ?>" <?php echo $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($formation_id): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Sélectionner une Session</h2>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <input type="hidden" name="section" value="attendances">
                                <input type="hidden" name="formation_id" value="<?php echo $formation_id; ?>">
                                <div class="mb-3">
                                    <label for="session_id_attendances" class="form-label">Session</label>
                                    <select name="session_id" id="session_id_attendances" class="form-select" onchange="this.form.submit()">
                                        <option value="">-- Sélectionner une Session --</option>
                                        <?php foreach ($attendances_sessions as $session): ?>
                                            <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($session['nom'] . ' (' . $session['date_session'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($formation_id && $session_id && $attendances_members): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Enregistrer les Présences</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="save_attendance" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Membre</th>
                                                <th>Présent <input type="checkbox" onclick="toggleAllCheckboxes(this)" class="form-check-input"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendances_members as $member): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?></td>
                                                    <td>
                                                        <input type="checkbox" name="attendance[<?php echo $member['id']; ?>]" value="1" 
                                                               <?php echo isset($attendances_records[$member['id']]) && $attendances_records[$member['id']] ? 'checked' : ''; ?>
                                                               class="form-check-input">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary" <?php echo !in_array($_SESSION['role'], ['admin', 'diacre']) ? 'disabled' : ''; ?>>Enregistrer les Présences</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($formation_id && $session_id): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <p class="text-muted">Aucun membre inscrit à cette formation.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($formation_id && $attendances_history): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="h5 mb-0>Historique des Présences</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Membre</th>
                                            <th>Session</th>
                                            <th>Date</th>
                                            <th>Présent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendances_history as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['nom'] . ' ' . $record['prenom']); ?></td>
                                                <td><?php echo htmlspecialchars($record['session_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['date_presence']); ?></td>
                                                <td>
                                                    <span class="badge badge-present <?php echo $record['present'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $record['present'] ? 'Oui' : 'Non'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Présences (Détaillé) Section -->
            <?php if ($section === 'presences_detailed'): ?>
                <h1 class="mb-4">Présences (Détaillé)</h1>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Sélectionner une Formation</h2>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="section" value="presences_detailed">
                            <div class="mb-3">
                                <label for="formation_id_detailed" class="form-label">Formation</label>
                                <select name="formation_id" id="formation_id_detailed" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Sélectionner une Formation --</option>
                                    <?php foreach ($detailed_formations as $formation): ?>
                                        <option value="<?php echo $formation['id']; ?>" <?php echo $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($formation_id): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Sélectionner une Session</h2>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <input type="hidden" name="section" value="presences_detailed">
                                <input type="hidden" name="formation_id" value="<?php echo $formation_id; ?>">
                                <div class="mb-3">
                                    <label for="session_id_detailed" class="form-label">Session</label>
                                    <select name="session_id" id="session_id_detailed" class="form-select" onchange="this.form.submit()">
                                        <option value="">-- Sélectionner une Session --</option>
                                        <?php foreach ($detailed_sessions as $session): ?>
                                            <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($session['nom'] . ' (' . $session['date_session'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($formation_id && $session_id && $detailed_members): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Enregistrer les Présences</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="save_attendance" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Membre</th>
                                                <th>Présent <input type="checkbox" onclick="toggleAllCheckboxes(this)" class="form-check-input"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailed_members as $member): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?></td>
                                                    <td>
                                                        <input type="checkbox" name="attendance[<?php echo $member['id']; ?>]" value="1" 
                                                               <?php echo isset($detailed_records[$member['id']]) && $detailed_records[$member['id']] ? 'checked' : ''; ?>
                                                               class="form-check-input">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary" <?php echo !in_array($_SESSION['role'], ['admin', 'diacre']) ? 'disabled' : ''; ?>>Enregistrer les Présences</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($formation_id && $session_id): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <p class="text-muted">Aucun membre inscrit à cette formation.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($formation_id && $detailed_history): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Historique des Présences</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Membre</th>
                                            <th>Session</th>
                                            <th>Date</th>
                                            <th>Présent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detailed_history as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['nom'] . ' ' . $record['prenom']); ?></td>
                                                <td><?php echo htmlspecialchars($record['session_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['date_presence']); ?></td>
                                                <td>
                                                    <span class="badge badge-present <?php echo $record['present'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $record['present'] ? 'Oui' : 'Non'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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

            // Toggle all checkboxes for attendance
            function toggleAllCheckboxes(source) {
                $('input[name^="attendance"]').prop('checked', source.checked);
            }

            // Show any success/error messages as toasts
            <?php if ($success): ?>
                showNotification('<?php echo addslashes($success); ?>', 'success');
            <?php endif; ?>
            <?php if ($error): ?>
                showNotification('<?php echo addslashes($error); ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>
</html>