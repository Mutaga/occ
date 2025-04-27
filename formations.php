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

// Fetch members for enrollment dropdown
try {
    $members = $db->query("SELECT id, nom, prenom FROM members ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération membres : " . $e->getMessage();
    $members = [];
}

// Function to check prerequisites
function checkPrerequisites($db, $member_id, $formation_nom) {
    if ($formation_nom === 'Isoko Classe 2') {
        $completed_classe1 = $db->prepare("
            SELECT COUNT(*) FROM promotion_members pm
            JOIN formations f ON pm.promotion_id = f.id
            WHERE pm.member_id = ? AND f.nom = 'Isoko Classe 1' AND pm.points >= 0
        ");
        $completed_classe1->execute([$member_id]);
        if ($completed_classe1->fetchColumn() == 0) {
            throw new Exception("Doit compléter Isoko Classe 1 d'abord.");
        }
    } elseif ($formation_nom === 'Isoko Classe 3') {
        $completed_classe2 = $db->prepare("
            SELECT COUNT(*) FROM promotion_members pm
            JOIN formations f ON pm.promotion_id = f.id
            WHERE pm.member_id = ? AND f.nom = 'Isoko Classe 2' AND pm.points >= 0
        ");
        $completed_classe2->execute([$member_id]);
        if ($completed_classe2->fetchColumn() == 0) {
            throw new Exception("Doit compléter Isoko Classe 2 d'abord.");
        }
    }
}

// Function to calculate points
function calculatePoints($db, $promotion_id, $member_id) {
    $total_sessions = $db->prepare("SELECT COUNT(*) FROM sessions WHERE promotion_id = ?");
    $total_sessions->execute([$promotion_id]);
    $total = $total_sessions->fetchColumn();

    $presences = $db->prepare("
        SELECT COUNT(*) FROM attendance a
        JOIN sessions s ON a.session_id = s.id
        WHERE s.promotion_id = ? AND a.member_id = ? AND a.present = 'Oui'
    ");
    $presences->execute([$promotion_id, $member_id]);
    $presences_count = $presences->fetchColumn();

    $scaled_points = ($total > 0) ? round(($presences_count / $total) * 50) : 0;

    $stmt = $db->prepare("
        UPDATE promotion_members SET points = ?
        WHERE promotion_id = ? AND member_id = ?
    ");
    $stmt->execute([$scaled_points, $promotion_id, $member_id]);

    return ['presences' => $presences_count, 'total' => $total, 'points' => $scaled_points];
}

// Enroll Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'enroll' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $promotion_id = $_POST['promotion_id'] ?? '';
        $member_id = $_POST['member_id'] ?? '';

        if (empty($promotion_id) || empty($member_id)) throw new Exception("Promotion ou membre manquant.");

        // Get formation nom
        $stmt = $db->prepare("SELECT nom FROM formations WHERE id = ?");
        $stmt->execute([$promotion_id]);
        $formation_nom = $stmt->fetchColumn();
        if (!$formation_nom) throw new Exception("Promotion introuvable.");

        // Check prerequisites
        checkPrerequisites($db, $member_id, $formation_nom);

        // Check if already enrolled
        $stmt = $db->prepare("SELECT COUNT(*) FROM promotion_members WHERE promotion_id = ? AND member_id = ?");
        $stmt->execute([$promotion_id, $member_id]);
        if ($stmt->fetchColumn() > 0) throw new Exception("Membre déjà inscrit.");

        // Enroll
        $stmt = $db->prepare("INSERT INTO promotion_members (promotion_id, member_id) VALUES (?, ?)");
        $stmt->execute([$promotion_id, $member_id]);

        logAction($_SESSION['user_id'], "Inscription membre $member_id dans promotion $promotion_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Membre inscrit avec succès."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Remove Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'remove_member' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $promotion_id = $_POST['promotion_id'] ?? '';
        $member_id = $_POST['member_id'] ?? '';

        if (empty($promotion_id) || empty($member_id)) throw new Exception("Promotion ou membre manquant.");

        $stmt = $db->prepare("DELETE FROM promotion_members WHERE promotion_id = ? AND member_id = ?");
        $stmt->execute([$promotion_id, $member_id]);

        logAction($_SESSION['user_id'], "Retrait membre $member_id de promotion $promotion_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Membre retiré avec succès."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Mark Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'mark_attendance' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $session_id = $_POST['session_id'] ?? '';
        $attendances = $_POST['attendance'] ?? [];

        if (empty($session_id)) throw new Exception("Session manquante.");

        $db->beginTransaction();
        foreach ($attendances as $member_id => $present) {
            $present = ($present === 'Oui') ? 'Oui' : 'Non';
            $stmt = $db->prepare("
                INSERT INTO attendance (session_id, member_id, present)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE present = ?
            ");
            $stmt->execute([$session_id, $member_id, $present, $present]);
        }
        $db->commit();

        logAction($_SESSION['user_id'], "Marquage présence pour session $session_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Présences enregistrées."]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Get Promotion Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_promotion') {
    try {
        $promotion_id = $_POST['promotion_id'] ?? '';
        if (empty($promotion_id)) throw new Exception("ID manquant.");

        // Fetch promotion
        $stmt = $db->prepare("SELECT * FROM formations WHERE id = ?");
        $stmt->execute([$promotion_id]);
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$promotion) throw new Exception("Promotion introuvable.");

        // Fetch enrolled members
        $stmt = $db->prepare("
            SELECT pm.member_id, m.nom, m.prenom, pm.points
            FROM promotion_members pm
            JOIN members m ON pm.member_id = m.id
            WHERE pm.promotion_id = ?
        ");
        $stmt->execute([$promotion_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch sessions
        $stmt = $db->prepare("SELECT id, date_session, description FROM sessions WHERE promotion_id = ? ORDER BY date_session");
        $stmt->execute([$promotion_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch points data
        $points_data = [];
        foreach ($members as $member) {
            $points = calculatePoints($db, $promotion_id, $member['member_id']);
            $points_data[$member['member_id']] = $points;
        }

        echo json_encode([
            'success' => true,
            'promotion' => $promotion,
            'members' => $members,
            'sessions' => $sessions,
            'points' => $points_data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Get Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_attendance') {
    try {
        $session_id = $_POST['session_id'] ?? '';
        $promotion_id = $_POST['promotion_id'] ?? '';

        if (empty($session_id) || empty($promotion_id)) throw new Exception("Session ou promotion manquante.");

        $stmt = $db->prepare("
            SELECT pm.member_id, m.nom, m.prenom, a.present
            FROM promotion_members pm
            JOIN members m ON pm.member_id = m.id
            LEFT JOIN attendance a ON a.session_id = ? AND a.member_id = pm.member_id
            WHERE pm.promotion_id = ?
        ");
        $stmt->execute([$session_id, $promotion_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
}

// Search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'search') {
    try {
        $search_term = trim($_POST['search_term'] ?? '');
        $query = "
            SELECT f.*, m.nom AS member_nom, m.prenom AS member_prenom
            FROM formations f
            LEFT JOIN promotion_members pm ON f.id = pm.promotion_id
            LEFT JOIN members m ON pm.member_id = m.id
            WHERE f.id LIKE :term
               OR f.nom LIKE :term
               OR f.promotion LIKE :term
               OR m.nom LIKE :term
               OR m.id LIKE :term
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

// Export CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'export_csv' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $filter = trim($_POST['filter'] ?? 'all');
        $search_term = trim($_POST['search_term'] ?? '');

        $query = "
            SELECT f.id, f.nom, f.promotion, f.date_debut, f.date_fin, f.active,
                   pm.member_id, m.nom AS member_nom, m.prenom AS member_prenom,
                   COUNT(CASE WHEN a.present = 'Oui' THEN 1 END) AS presences,
                   COUNT(s.id) AS total_sessions,
                   pm.points
            FROM formations f
            LEFT JOIN promotion_members pm ON f.id = pm.promotion_id
            LEFT JOIN members m ON pm.member_id = m.id
            LEFT JOIN sessions s ON f.id = s.promotion_id
            LEFT JOIN attendance a ON s.id = a.session_id AND a.member_id = pm.member_id
            WHERE 1=1
        ";
        if ($filter !== 'all') {
            $query .= " AND f.nom = ?";
        }
        if ($search_term) {
            $query .= " AND (f.id LIKE ? OR f.nom LIKE ? OR f.promotion LIKE ? OR m.nom LIKE ? OR m.id LIKE ?)";
        }
        $query .= " GROUP BY f.id, pm.member_id";

        $stmt = $db->prepare($query);
        $params = [];
        if ($filter !== 'all') {
            $params[] = $filter;
        }
        if ($search_term) {
            $params = array_merge($params, ["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"]);
        }
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="formations_export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'ID', 'Formation', 'Promotion', 'Date Début', 'Date Fin', 'Active',
            'Membre ID', 'Nom', 'Prénom', 'Présences', 'Total Sessions', 'Points'
        ]);
        foreach ($results as $row) {
            fputcsv($output, [
                $row['id'], $row['nom'], $row['promotion'], $row['date_debut'], $row['date_fin'],
                $row['active'] ? 'Oui' : 'Non',
                $row['member_id'] ?? '-', $row['member_nom'] ?? '-', $row['member_prenom'] ?? '-',
                $row['presences'] ?? '0', $row['total_sessions'] ?? '0', $row['points'] ?? '0'
            ]);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = "Erreur export CSV : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur export CSV: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Fetch all promotions
try {
    $promotions = $db->query("
        SELECT f.*
        FROM formations f
        ORDER BY date_debut DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération formations : " . $e->getMessage();
    $promotions = [];
}

// View promotion details
$view_promotion = null;
if (isset($_GET['view'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM formations WHERE id = ?");
        $stmt->execute([$_GET['view']]);
        $view_promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$view_promotion) $error = "Promotion non trouvée : " . htmlspecialchars($_GET['view']);
    } catch (Exception $e) {
        $error = "Erreur récupération promotion : " . $e->getMessage();
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
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #117a8b;
            border-color: #117a8b;
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
        .modal-body {
            max-height: 80vh;
            overflow-y: auto;
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
        .btn-sm {
            font-size: 12px;
            margin-right: 5px;
            padding: 4px 8px;
        }
        .dropdown-menu {
            min-width: 120px;
        }
        .nav-tabs .nav-link {
            color: #007bff;
        }
        .nav-tabs .nav-link.active {
            color: #212529;
            background-color: #f4f6f9;
            border-color: #dee2e6 #dee2e6 #f4f6f9;
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
                        <li class="nav-item"><a href="formations.php" class="nav-link active">Membres</a></li>
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
            <h1 class="mb-4">Gestion des Membres</h1>

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

            <div class="mb-3 d-flex justify-content-between">
                <div>
                    <button class="btn btn-info" data-toggle="modal" data-target="#searchModal">Rechercher</button>
                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                        <form action="formations.php" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="filter" id="export-csv-filter" value="all">
                            <input type="hidden" name="search_term" id="export-csv-search-term" value="">
                            <button type="submit" class="btn btn-success">Exporter en CSV</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="filterButton" aria-haspopup="true" aria-expanded="false">
                        Filtrer par Formation
                    </button>
                    <div class="dropdown-menu" aria-labelledby="filterButton">
                        <a class="dropdown-item filter-promotion" href="#" data-filter="all">Tous</a>
                        <a class="dropdown-item filter-promotion" href="#" data-filter="Isoko Classe 1">Isoko Classe 1</a>
                        <a class="dropdown-item filter-promotion" href="#" data-filter="Isoko Classe 2">Isoko Classe 2</a>
                        <a class="dropdown-item filter-promotion" href="#" data-filter="Isoko Classe 3">Isoko Classe 3</a>
                    </div>
                </div>
            </div>

            <table id="promotion-table" class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Formation</th>
                        <th>Promotion</th>
                        <th>Date Début</th>
                        <th>Date Fin</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promotions as $promotion): ?>
                        <tr id="promotion-<?php echo htmlspecialchars($promotion['id']); ?>" data-formation-id="<?php echo htmlspecialchars($promotion['nom']); ?>">
                            <td><?php echo htmlspecialchars($promotion['id']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['nom']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['promotion']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['date_debut']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['date_fin']); ?></td>
                            <td><?php echo $promotion['active'] ? 'Oui' : 'Non'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-info view-promotion" data-promotion-id="<?php echo htmlspecialchars($promotion['id']); ?>">Voir</button>
                                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                    <button class="btn btn-sm btn-primary manage-attendance" data-promotion-id="<?php echo htmlspecialchars($promotion['id']); ?>">Gérer Présences</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- View Promotion Modal -->
            <div class="modal fade" id="viewPromotionModal" tabindex="-1" role="dialog" aria-labelledby="viewPromotionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewPromotionModalLabel">Détails de la Promotion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs" id="promotionTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab">Détails</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="members-tab" data-toggle="tab" href="#members" role="tab">Membres</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="sessions-tab" data-toggle="tab" href="#sessions" role="tab">Sessions</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="points-tab" data-toggle="tab" href="#points" role="tab">Points</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="promotionTabContent">
                                <div class="tab-pane fade show active" id="details" role="tabpanel">
                                    <p><strong>ID:</strong> <span id="view_id"></span></p>
                                    <p><strong>Formation:</strong> <span id="view_nom"></span></p>
                                    <p><strong>Promotion:</strong> <span id="view_promotion"></span></p>
                                    <p><strong>Date Début:</strong> <span id="view_date_debut"></span></p>
                                    <p><strong>Date Fin:</strong> <span id="view_date_fin"></span></p>
                                    <p><strong>Active:</strong> <span id="view_active"></span></p>
                                </div>
                                <div class="tab-pane fade" id="members" role="tabpanel">
                                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                        <div class="form-group">
                                            <label for="enroll_member">Inscrire un Membre</label>
                                            <select class="form-control" id="enroll_member">
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?php echo htmlspecialchars($member['id']); ?>">
                                                        <?php echo htmlspecialchars($member['id'] . ' - ' . $member['nom'] . ' ' . $member['prenom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-primary mt-2" id="enroll_button">Inscrire</button>
                                        </div>
                                    <?php endif; ?>
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nom</th>
                                                <th>Prénom</th>
                                                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                                    <th>Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody id="members_table"></tbody>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="sessions" role="tabpanel">
                                    <p>Pour gérer les sessions, visitez la <a href="sessions.php">section Sessions</a>.</p>
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sessions_table"></tbody>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="points" role="tabpanel">
                                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                        <button class="btn btn-primary mb-3" id="recalculate_points">Recalculer Points</button>
                                    <?php endif; ?>
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nom</th>
                                                <th>Prénom</th>
                                                <th>Présences</th>
                                                <th>Points</th>
                                            </tr>
                                        </thead>
                                        <tbody id="points_table"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Attendance Modal -->
            <div class="modal fade" id="attendanceModal" tabindex="-1" role="dialog" aria-labelledby="attendanceModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="attendanceModalLabel">Gérer les Présences</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="session_select">Sélectionner une Session</label>
                                <select class="form-control" id="session_select">
                                    <option value="">Sélectionner...</option>
                                </select>
                            </div>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Présent</th>
                                    </tr>
                                </thead>
                                <tbody id="attendance_table"></tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                            <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                <button type="button" class="btn btn-primary" id="save_attendance">Enregistrer</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Modal -->
            <div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-labelledby="searchModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="searchModalLabel">Rechercher une Promotion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="search-bar mb-3">
                                <input type="text" class="form-control" id="search-input" placeholder="Rechercher par ID, Formation, Promotion, Membre...">
                                <span class="clear-search" id="clear-search" style="display: none;">×</span>
                            </div>
                            <div class="spinner-container" id="search-spinner" style="display: none; text-align: center;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Chargement...</span>
                                </div>
                            </div>
                            <table id="search-results-table" class="table table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Formation</th>
                                        <th>Promotion</th>
                                        <th>Date Début</th>
                                        <th>Date Fin</th>
                                        <th>Active</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="search-results-body">
                                    <tr><td colspan="7">Entrez un terme de recherche pour commencer.</td></tr>
                                </tbody>
                            </table>
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
                    var currentPromotionId = null;

                    // Toggle dropdown manually
                    $('#filterButton').on('click', function() {
                        $(this).parent().toggleClass('show');
                        $(this).siblings('.dropdown-menu').toggleClass('show');
                    });

                    // Close dropdown when clicking outside
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('.dropdown').length) {
                            $('.dropdown').removeClass('show');
                            $('.dropdown-menu').removeClass('show');
                        }
                    });

                    // Filter promotions
                    $('.filter-promotion').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var filter = $(this).data('filter');
                        $('#filterButton').text('Filtrer: ' + (filter === 'all' ? 'Tous' : filter));
                        $('#export-csv-filter').val(filter);

                        $('#promotion-table tbody tr').each(function() {
                            var formationId = $(this).data('formation-id');
                            $(this).toggle(filter === 'all' || formationId === filter);
                        });

                        $(this).closest('.dropdown').removeClass('show');
                        $(this).closest('.dropdown-menu').removeClass('show');
                    });

                    // View promotion
                    $(document).on('click', '.view-promotion', function(e) {
                        e.preventDefault();
                        var promotionId = $(this).data('promotion-id');
                        currentPromotionId = promotionId;
                        loadPromotionDetails(promotionId);
                    });

                    // Manage attendance
                    $(document).on('click', '.manage-attendance', function() {
                        currentPromotionId = $(this).data('promotion-id');
                        $.ajax({
                            url: 'formations.php',
                            type: 'POST',
                            data: { action: 'get_promotion', promotion_id: currentPromotionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var sessions = response.sessions;
                                    $('#session_select').empty().append('<option value="">Sélectionner...</option>');
                                    sessions.forEach(function(session) {
                                        $('#session_select').append(
                                            `<option value="${session.id}">${session.date_session} - ${session.description || 'Session'}</option>`
                                        );
                                    });
                                    $('#attendance_table').empty();
                                    $('#attendanceModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            }
                        });
                    });

                    // Load attendance
                    $('#session_select').on('change', function() {
                        var sessionId = $(this).val();
                        if (sessionId) {
                            $.ajax({
                                url: 'formations.php',
                                type: 'POST',
                                data: { action: 'get_attendance', session_id: sessionId, promotion_id: currentPromotionId },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        var html = '';
                                        response.members.forEach(function(member) {
                                            var checked = member.present === 'Oui' ? 'checked' : '';
                                            html += `
                                                <tr>
                                                    <td>${member.member_id}</td>
                                                    <td>${member.nom}</td>
                                                    <td>${member.prenom}</td>
                                                    <td>
                                                        <input type="checkbox" class="attendance-checkbox"
                                                               data-member-id="${member.member_id}" ${checked}>
                                                    </td>
                                                </tr>
                                            `;
                                        });
                                        $('#attendance_table').html(html);
                                    } else {
                                        alert('Erreur : ' + response.message);
                                    }
                                }
                            });
                        } else {
                            $('#attendance_table').empty();
                        }
                    });

                    // Save attendance
                    $('#save_attendance').on('click', function() {
                        var sessionId = $('#session_select').val();
                        if (!sessionId) {
                            alert('Veuillez sélectionner une session.');
                            return;
                        }
                        var attendance = {};
                        $('.attendance-checkbox').each(function() {
                            var memberId = $(this).data('member-id');
                            attendance[memberId] = $(this).is(':checked') ? 'Oui' : 'Non';
                        });
                        $.ajax({
                            url: 'formations.php',
                            type: 'POST',
                            data: { action: 'mark_attendance', session_id: sessionId, attendance: attendance },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message);
                                    $('#attendanceModal').modal('hide');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            }
                        });
                    });

                    // Enroll member
                    $('#enroll_button').on('click', function() {
                        var memberId = $('#enroll_member').val();
                        if (!memberId) {
                            alert('Veuillez sélectionner un membre.');
                            return;
                        }
                        $.ajax({
                            url: 'formations.php',
                            type: 'POST',
                            data: { action: 'enroll', promotion_id: currentPromotionId, member_id: memberId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message);
                                    loadPromotionDetails(currentPromotionId);
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            }
                        });
                    });

                    // Remove member
                    $(document).on('click', '.remove-member', function() {
                        var memberId = $(this).data('member-id');
                        if (confirm('Retirer ce membre de la promotion ?')) {
                            $.ajax({
                                url: 'formations.php',
                                type: 'POST',
                                data: { action: 'remove_member', promotion_id: currentPromotionId, member_id: memberId },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        alert(response.message);
                                        loadPromotionDetails(currentPromotionId);
                                    } else {
                                        alert('Erreur : ' + response.message);
                                    }
                                }
                            });
                        }
                    });

                    // Recalculate points
                    $('#recalculate_points').on('click', function() {
                        loadPromotionDetails(currentPromotionId, true);
                    });

                    // Search
                    let searchTimeout;
                    $('#search-input').on('input', function() {
                        var searchTerm = $(this).val().trim();
                        $('#clear-search').toggle(searchTerm.length > 0);
                        $('#export-csv-search-term').val(searchTerm);

                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            $('#search-spinner').show();
                            $('#search-results-body').html('<tr><td colspan="7">Recherche en cours...</td></tr>');

                            if (searchTerm.length >= 1) {
                                $.ajax({
                                    url: 'formations.php',
                                    type: 'POST',
                                    data: { action: 'search', search_term: searchTerm },
                                    dataType: 'json',
                                    success: function(response) {
                                        $('#search-spinner').hide();
                                        if (response.success && Array.isArray(response.results)) {
                                            if (response.results.length > 0) {
                                                $('#search-results-body').empty();
                                                response.results.forEach(function(result) {
                                                    var row = `
                                                        <tr>
                                                            <td>${result.id}</td>
                                                            <td>${result.nom}</td>
                                                            <td>${result.promotion}</td>
                                                            <td>${result.date_debut}</td>
                                                            <td>${result.date_fin}</td>
                                                            <td>${result.active ? 'Oui' : 'Non'}</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info view-promotion" data-promotion-id="${result.id}">Voir</button>
                                                            </td>
                                                        </tr>
                                                    `;
                                                    $('#search-results-body').append(row);
                                                });
                                            } else {
                                                $('#search-results-body').html('<tr><td colspan="7">Aucun résultat.</td></tr>');
                                            }
                                        } else {
                                            $('#search-results-body').html('<tr><td colspan="7">Erreur recherche.</td></tr>');
                                        }
                                    }
                                });
                            } else {
                                $('#search-spinner').hide();
                                $('#search-results-body').html('<tr><td colspan="7">Entrez un terme de recherche.</td></tr>');
                            }
                        }, 500);
                    });

                    $('#clear-search').on('click', function() {
                        $('#search-input').val('');
                        $('#clear-search').hide();
                        $('#search-results-body').html('<tr><td colspan="7">Entrez un terme de recherche.</td></tr>');
                        $('#export-csv-search-term').val('');
                    });

                    // Load promotion details
                    function loadPromotionDetails(promotionId, recalculatePoints = false) {
                        $.ajax({
                            url: 'formations.php',
                            type: 'POST',
                            data: { action: 'get_promotion', promotion_id: promotionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var promotion = response.promotion;
                                    var members = response.members;
                                    var sessions = response.sessions;
                                    var points = response.points;

                                    // Details tab
                                    $('#view_id').text(promotion.id);
                                    $('#view_nom').text(promotion.nom);
                                    $('#view_promotion').text(promotion.promotion);
                                    $('#view_date_debut').text(promotion.date_debut);
                                    $('#view_date_fin').text(promotion.date_fin);
                                    $('#view_active').text(promotion.active ? 'Oui' : 'Non');

                                    // Members tab
                                    var membersHtml = '';
                                    members.forEach(function(member) {
                                        membersHtml += `
                                            <tr>
                                                <td>${member.member_id}</td>
                                                <td>${member.nom}</td>
                                                <td>${member.prenom}</td>
                                                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                                    <td>
                                                        <button class="btn btn-sm btn-danger remove-member" data-member-id="${member.member_id}">Retirer</button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        `;
                                    });
                                    $('#members_table').html(membersHtml || '<tr><td colspan="4">Aucun membre inscrit.</td></tr>');

                                    // Sessions tab
                                    var sessionsHtml = '';
                                    sessions.forEach(function(session) {
                                        sessionsHtml += `
                                            <tr>
                                                <td>${session.date_session}</td>
                                                <td>${session.description || '-'}</td>
                                            </tr>
                                        `;
                                    });
                                    $('#sessions_table').html(sessionsHtml || '<tr><td colspan="2">Aucune session.</td></tr>');

                                    // Points tab
                                    var pointsHtml = '';
                                    members.forEach(function(member) {
                                        var pointData = points[member.member_id] || { presences: 0, total: 0, points: 0 };
                                        pointsHtml += `
                                            <tr>
                                                <td>${member.member_id}</td>
                                                <td>${member.nom}</td>
                                                <td>${member.prenom}</td>
                                                <td>${pointData.presences}/${pointData.total}</td>
                                                <td>${pointData.points}/50</td>
                                            </tr>
                                        `;
                                    });
                                    $('#points_table').html(pointsHtml || '<tr><td colspan="5">Aucun point calculé.</td></tr>');

                                    $('#viewPromotionModal').modal('show');
                                    $('#promotionTabs a:first').tab('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            }
                        });
                    }

                    // Show view modal if view parameter
                    <?php if ($view_promotion): ?>
                        $(window).on('load', function() {
                            currentPromotionId = '<?php echo $view_promotion['id']; ?>';
                            loadPromotionDetails(currentPromotionId);
                        });
                    <?php endif; ?>

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