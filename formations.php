<?php
// Enable error reporting for debugging (log only, no display)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start application
define('APP_START', true);
require_once 'session_manager.php';
require_once 'config.php';

// Verify user is logged in
requireLogin();

// Log access
logAction(getCurrentUserId(), "Accès à formations.php", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));

// Determine section (default to dashboard)
$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING) ?? 'dashboard';

// Initialize messages
$error = null;
$success = null;

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
    error_log("DB Connection Error: " . $e->getMessage());
}

// Fetch formations (used in multiple sections)
try {
    $stmt = $db->prepare("SELECT id, nom, promotion, status FROM formations ORDER BY promotion DESC, nom");
    $stmt->execute();
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération formations : " . htmlspecialchars($e->getMessage());
    error_log("Fetch Formations Error: " . $e->getMessage());
}

// Fetch promotions for filters
try {
    $stmt = $db->prepare("SELECT DISTINCT promotion FROM formations ORDER BY promotion DESC");
    $stmt->execute();
    $promotions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $error = "Erreur récupération promotions : " . htmlspecialchars($e->getMessage());
    error_log("Fetch Promotions Error: " . $e->getMessage());
}

// Fetch active formations for Examens section
try {
    $stmt = $db->prepare("SELECT id, nom, promotion FROM formations WHERE status = 'active' ORDER BY promotion DESC, nom");
    $stmt->execute();
    $active_formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération formations actives : " . htmlspecialchars($e->getMessage());
    error_log("Fetch Active Formations Error: " . $e->getMessage());
}

// Fetch formations with exam results for Totaux section
try {
    $stmt = $db->prepare("
        SELECT DISTINCT f.id, f.nom, f.promotion
        FROM formations f
        JOIN formation_results fr ON f.id = fr.formation_id
        ORDER BY f.promotion DESC, f.nom
    ");
    $stmt->execute();
    $formations_with_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération formations avec totaux : " . htmlspecialchars($e->getMessage());
    error_log("Fetch Formations with Results Error: " . $e->getMessage());
}

// Handle Dashboard Summary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_dashboard_summary') {
    header('Content-Type: application/json');
    try {
        error_log("get_dashboard_summary request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
        $promotion = filter_input(INPUT_POST, 'promotion', FILTER_SANITIZE_STRING) ?? null;
        $status = isset($_POST['status']) && is_array($_POST['status']) ? array_map('trim', $_POST['status']) : ['active', 'inactive'];
        $date_start = filter_input(INPUT_POST, 'date_start', FILTER_SANITIZE_STRING) ?? null;
        $date_end = filter_input(INPUT_POST, 'date_end', FILTER_SANITIZE_STRING) ?? null;

        // Build conditions
        $conditions = [];
        $params = [];
        if ($formation_id) {
            $conditions[] = "f.id = ?";
            $params[] = $formation_id;
        }
        if ($promotion) {
            $conditions[] = "f.promotion = ?";
            $params[] = $promotion;
        }
        if (!empty($status)) {
            $conditions[] = "f.status IN (" . implode(',', array_fill(0, count($status), '?')) . ")";
            $params = array_merge($params, $status);
        }

        $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

        // Total formations
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM formations f $where");
        $stmt->execute($params);
        $total_formations = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Total enrolled members
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT mf.member_id) as total
            FROM member_formations mf
            JOIN formations f ON mf.formation_id = f.id
            $where
        ");
        $stmt->execute($params);
        $total_members = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Average total score
        $stmt = $db->prepare("
            SELECT AVG(COALESCE(fr.total_score, 0)) as avg_score
            FROM formation_results fr
            JOIN formations f ON fr.formation_id = f.id
            $where
        ");
        $stmt->execute($params);
        $avg_score = $stmt->fetch(PDO::FETCH_ASSOC)['avg_score'];
        $avg_score = $avg_score !== null ? round($avg_score, 1) : 0;

        echo json_encode([
            'success' => true,
            'summary' => [
                'total_formations' => $total_formations,
                'total_members' => $total_members,
                'avg_score' => $avg_score
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get dashboard summary error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Handle Dashboard Table Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_dashboard_tables') {
    header('Content-Type: application/json');
    try {
        error_log("get_dashboard_tables request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
        $promotion = filter_input(INPUT_POST, 'promotion', FILTER_SANITIZE_STRING) ?? null;
        $status = isset($_POST['status']) && is_array($_POST['status']) ? array_map('trim', $_POST['status']) : ['active', 'inactive'];
        $date_start = filter_input(INPUT_POST, 'date_start', FILTER_SANITIZE_STRING) ?? null;
        $date_end = filter_input(INPUT_POST, 'date_end', FILTER_SANITIZE_STRING) ?? null;

        // Build conditions
        $conditions = [];
        $params = [];
        if ($formation_id) {
            $conditions[] = "f.id = ?";
            $params[] = $formation_id;
        }
        if ($promotion) {
            $conditions[] = "f.promotion = ?";
            $params[] = $promotion;
        }
        if (!empty($status)) {
            $conditions[] = "f.status IN (" . implode(',', array_fill(0, count($status), '?')) . ")";
            $params = array_merge($params, $status);
        } else {
            $conditions[] = "f.status IN ('active', 'inactive')";
        }

        $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

        // Initialize datasets
        $avg_scores = [];
        $status_distribution = [];
        $attendance_trends = [];
        $exam_distribution = [];
        $member_scores = [];

        // Average total scores by formation
        try {
            $stmt = $db->prepare("
                SELECT f.nom, f.promotion, AVG(COALESCE(fr.total_score, 0)) as avg_score
                FROM formations f
                LEFT JOIN formation_results fr ON f.id = fr.formation_id
                $where
                GROUP BY f.id, f.nom, f.promotion
                ORDER BY f.nom
            ");
            $stmt->execute($params);
            $avg_scores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Avg scores query error: " . $e->getMessage());
        }

        // Member distribution by status
        try {
            $stmt = $db->prepare("
                SELECT f.status, COUNT(DISTINCT mf.member_id) as member_count
                FROM formations f
                LEFT JOIN member_formations mf ON f.id = mf.formation_id
                $where
                GROUP BY f.status
            ");
            $stmt->execute($params);
            $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Status distribution query error: " . $e->getMessage());
        }

        // Attendance trends
        try {
            $attendance_conditions = $conditions;
            $attendance_params = $params;
            if ($date_start && $date_end) {
                $attendance_conditions[] = "s.date_session BETWEEN ? AND ?";
                $attendance_params[] = $date_start;
                $attendance_params[] = $date_end;
            }
            $attendance_where = $attendance_conditions ? "WHERE " . implode(" AND ", $attendance_conditions) : "";
            $stmt = $db->prepare("
                SELECT s.date_session, AVG(COALESCE(fa.present, 0)) * 100 as attendance_rate
                FROM sessions s
                LEFT JOIN formations f ON s.formation_id = f.id
                LEFT JOIN formation_attendance fa ON s.id = fa.session_id AND fa.formation_id = f.id
                $attendance_where
                GROUP BY s.date_session
                ORDER BY s.date_session
            ");
            $stmt->execute($attendance_params);
            $attendance_trends = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Attendance trends query error: " . $e->getMessage());
        }

        // Exam score distribution
        try {
            $stmt = $db->prepare("
                SELECT 
                    CASE 
                        WHEN fr.exam_score <= 10 THEN '0-10'
                        WHEN fr.exam_score <= 20 THEN '11-20'
                        WHEN fr.exam_score <= 30 THEN '21-30'
                        WHEN fr.exam_score <= 40 THEN '31-40'
                        ELSE '41-50'
                    END as score_range,
                    COUNT(*) as count
                FROM formation_results fr
                LEFT JOIN formations f ON fr.formation_id = f.id
                $where
                GROUP BY score_range
                ORDER BY score_range
            ");
            $stmt->execute($params);
            $exam_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Exam distribution query error: " . $e->getMessage());
        }

        // Member scores
        try {
            $stmt = $db->prepare("
                SELECT m.nom, m.prenom, COALESCE(fr.attendance_score, 0) as attendance_score, COALESCE(fr.exam_score, 0) as exam_score
                FROM members m
                LEFT JOIN member_formations mf ON m.id = mf.member_id
                LEFT JOIN formations f ON mf.formation_id = f.id
                LEFT JOIN formation_results fr ON m.id = fr.member_id AND fr.formation_id = f.id
                $where
                ORDER BY m.nom, m.prenom
                LIMIT 10
            ");
            $stmt->execute($params);
            $member_scores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Member scores query error: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'tables' => [
                'avg_scores' => $avg_scores,
                'status_distribution' => $status_distribution,
                'attendance_trends' => $attendance_trends,
                'exam_distribution' => $exam_distribution,
                'member_scores' => $member_scores
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get dashboard tables error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Handle Exam Points Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_exam_points' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    header('Content-Type: application/json');
    try {
        error_log("save_exam_points request: " . json_encode($_POST));
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        $exam_points = $_POST['exam_points'] ?? [];

        if (empty($formation_id)) {
            throw new Exception("ID de formation manquant.");
        }

        // Verify formation exists
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Formation invalide.");
        }

        $updated = 0;
        foreach ($exam_points as $member_id => $points) {
            $points = trim($points);
            if ($points === '') {
                continue;
            }
            if (!is_numeric($points) || $points < 0 || $points > 50) {
                throw new Exception("Les points pour le membre ID $member_id doivent être entre 0 et 50.");
            }
            // Verify member exists and is enrolled
            $stmt = $db->prepare("
                SELECT m.id
                FROM members m
                JOIN member_formations mf ON m.id = mf.member_id
                WHERE m.id = ? AND mf.formation_id = ?
            ");
            $stmt->execute([$member_id, $formation_id]);
            if (!$stmt->fetch()) {
                continue;
            }
            // Check if exam points exist
            $stmt = $db->prepare("
                SELECT id FROM formation_exams WHERE member_id = ? AND formation_id = ?
            ");
            $stmt->execute([$member_id, $formation_id]);
            if ($stmt->fetch()) {
                throw new Exception("Les points pour le membre ID $member_id ont déjà été enregistrés.");
            }
            // Insert exam points
            $stmt = $db->prepare("
                INSERT INTO formation_exams (formation_id, member_id, exam_score)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$formation_id, $member_id, $points]);

            // Update formation_results
            $stmt = $db->prepare("
                SELECT attendance_score
                FROM attendance_summary
                WHERE member_id = ? AND formation_id = ?
            ");
            $stmt->execute([$member_id, $formation_id]);
            $attendance_score = $stmt->fetch(PDO::FETCH_ASSOC)['attendance_score'] ?? 0;

            $total_score = $attendance_score + $points;
            $stmt = $db->prepare("
                INSERT INTO formation_results (member_id, formation_id, attendance_score, exam_score, total_score)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    attendance_score = ?,
                    exam_score = ?,
                    total_score = ?,
                    created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $member_id, $formation_id, $attendance_score, $points, $total_score,
                $attendance_score, $points, $total_score
            ]);
            $updated++;
        }

        logAction(getCurrentUserId(), "Enregistrement de $updated points d'examen pour la formation: $formation_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "$updated points d'examen enregistrés avec succès.", 'updated' => $updated]);
    } catch (Exception $e) {
        error_log("Save exam points error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur enregistrement points d'examen: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Fetch Enrolled Members for Examens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_enrolled_members_exam') {
    header('Content-Type: application/json');
    try {
        error_log("get_enrolled_members_exam request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        if (empty($formation_id)) throw new Exception("ID de formation manquant.");

        $stmt = $db->prepare("
            SELECT m.id, m.nom, m.prenom, fe.exam_score
            FROM members m
            JOIN member_formations mf ON m.id = mf.member_id
            LEFT JOIN formation_exams fe ON m.id = fe.member_id AND fe.formation_id = ?
            WHERE mf.formation_id = ?
            ORDER BY m.nom ASC, m.prenom ASC
        ");
        $stmt->execute([$formation_id, $formation_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Exception $e) {
        error_log("Get enrolled members error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Fetch Totaux Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_totaux_data') {
    header('Content-Type: application/json');
    try {
        error_log("get_totaux_data request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        if (empty($formation_id)) throw new Exception("ID de formation manquant.");

        $stmt = $db->prepare("
            SELECT m.id, m.nom, m.prenom, fr.attendance_score, fr.exam_score, fr.total_score
            FROM members m
            JOIN formation_results fr ON m.id = fr.member_id
            WHERE fr.formation_id = ?
            ORDER BY m.nom ASC, m.prenom ASC
        ");
        $stmt->execute([$formation_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Exception $e) {
        error_log("Get totaux data error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Handle Totaux CSV Download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_totaux_csv') {
    try {
        error_log("download_totaux_csv request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        if (empty($formation_id)) throw new Exception("ID de formation manquant.");

        // Fetch formation details
        $stmt = $db->prepare("SELECT nom, promotion FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        $formation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$formation) throw new Exception("Formation invalide.");

        // Fetch totaux data
        $stmt = $db->prepare("
            SELECT m.nom, m.prenom, fr.attendance_score, fr.exam_score, fr.total_score
            FROM members m
            JOIN formation_results fr ON m.id = fr.member_id
            WHERE fr.formation_id = ?
            ORDER BY m.nom ASC, m.prenom ASC
        ");
        $stmt->execute([$formation_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sanitize filename
        $nom = preg_replace('/[^a-zA-Z0-9_-]/', '_', $formation['nom']);
        $promotion = preg_replace('/[^a-zA-Z0-9_-]/', '_', $formation['promotion']);
        $filename = "Totaux_{$nom}_{$promotion}.csv";

        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Write UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write CSV headers
        fputcsv($output, [
            'Nom',
            'Prénom',
            'Points de Présence (/50)',
            'Points d\'Examen (/50)',
            'Total (/100)'
        ]);

        // Write data
        foreach ($members as $member) {
            fputcsv($output, [
                $member['nom'],
                $member['prenom'],
                $member['attendance_score'],
                $member['exam_score'],
                $member['total_score']
            ]);
        }

        fclose($output);
        logAction(getCurrentUserId(), "Téléchargement CSV des totaux pour la formation: $formation_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        exit;
    } catch (Exception $e) {
        error_log("Download totaux CSV error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur téléchargement CSV totaux: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Handle Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    header('Content-Type: application/json');
    try {
        error_log("save_attendance request: " . json_encode($_POST));
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT) ?? '';
        $attendance = $_POST['attendance'] ?? [];
        $date_presence = date('Y-m-d');

        if (empty($formation_id) || empty($session_id)) {
            throw new Exception("Formation ou session manquante.");
        }

        // Verify formation and session
        $stmt = $db->prepare("SELECT id FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Formation invalide.");
        }
        $stmt = $db->prepare("SELECT id, date_session FROM sessions WHERE id = ? AND formation_id = ?");
        $stmt->execute([$session_id, $formation_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new Exception("Session invalide.");
        }
        $date_presence = $session['date_session'] ?? $date_presence;

        $updated = 0;
        foreach ($attendance as $member_id => $status) {
            $present = $status === 'present' ? 1 : 0;
            $stmt = $db->prepare("
                INSERT INTO formation_attendance (member_id, formation_id, session_id, date_presence, present)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE present = ?
            ");
            $stmt->execute([$member_id, $formation_id, $session_id, $date_presence, $present, $present]);
            $updated++;
        }

        logAction(getCurrentUserId(), "Mise à jour de $updated présences pour la formation: $formation_id, session: $session_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "$updated présences enregistrées avec succès."]);
    } catch (Exception $e) {
        error_log("Save attendance error: " . $e->getMessage());
        logAction(getCurrentUserId(), "Erreur enregistrement présences: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Fetch Attendance Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_attendance') {
    header('Content-Type: application/json');
    try {
        error_log("get_attendance request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT) ?? '';
        if (empty($formation_id) || empty($session_id)) {
            throw new Exception("Formation ou session manquante.");
        }

        $stmt = $db->prepare("
            SELECT m.id, m.nom, m.prenom, fa.present
            FROM members m
            JOIN member_formations mf ON m.id = mf.member_id
            LEFT JOIN formation_attendance fa ON m.id = fa.member_id AND fa.session_id = ? AND fa.formation_id = ?
            WHERE mf.formation_id = ?
            ORDER BY m.nom ASC, m.prenom ASC
        ");
        $stmt->execute([$session_id, $formation_id, $formation_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $members = array_map(function($member) {
            $member['status'] = $member['present'] === '1' ? 'present' : 'absent';
            return $member;
        }, $members);

        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Exception $e) {
        error_log("Get attendance error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// Fetch Sessions for Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_sessions') {
    header('Content-Type: application/json');
    try {
        error_log("get_sessions request: " . json_encode($_POST));
        $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT) ?? '';
        if (empty($formation_id)) throw new Exception("ID de formation manquant.");

        $stmt = $db->prepare("SELECT id, nom, date_session FROM sessions WHERE formation_id = ? ORDER BY date_session DESC");
        $stmt->execute([$formation_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'sessions' => $sessions]);
    } catch (Exception $e) {
        error_log("Get sessions error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Formations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Arial', sans-serif;
            color: #000000; /* Noir */
        }
        .sidebar {
            width: 250px;
            background-color: #000000; /* Noir */
            color: #ffffff; /* Blanc */
            height: 100vh;
            padding: 20px;
            position: fixed;
        }
        .sidebar .nav-link {
            color: #ffffff; /* Blanc */
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #007bff; /* Bleu */
            color: #ffffff; /* Blanc */
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
            background-color: #ffffff; /* Blanc */
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .table-dark {
            background-color: #007bff; /* Bleu */
            color: #ffffff; /* Blanc */
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }
        .table-striped tbody tr:hover {
            background-color: #e9ecef;
        }
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: #ffffff; /* Blanc */
        }
        .modal-body {
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
            color: #000000; /* Noir */
        }
        .btn-primary {
            background-color: #007bff; /* Bleu */
            border-color: #007bff;
            color: #ffffff; /* Blanc */
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-secondary {
            background-color: #ffffff; /* Blanc */
            border-color: #007bff; /* Bleu */
            color: #000000; /* Noir */
        }
        .btn-secondary:hover {
            background-color: #e9ecef;
            border-color: #007bff;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            white-space: nowrap;
            justify-content: center;
        }
        .filter-section {
            background-color: #ffffff; /* Blanc */
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
        .toast.bg-success {
            background-color: #007bff; /* Bleu */
            color: #ffffff; /* Blanc */
        }
        .toast.bg-danger {
            background-color: #000000; /* Noir */
            color: #ffffff; /* Blanc */
        }
        .exam-points-input {
            width: 80px;
            color: #000000; /* Noir */
        }
        .data-table-card {
            background-color: #ffffff; /* Blanc */
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .data-table-card table {
            width: 100%;
        }
        .summary-card {
            background-color: #ffffff; /* Blanc */
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            color: #000000; /* Noir */
        }
        .summary-card h3 {
            color: #007bff; /* Bleu */
        }
        .form-select, .form-control {
            color: #000000; /* Noir */
            border-color: #000000;
        }
        .form-check-label {
            color: #000000; /* Noir */
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
                    <a href="formations.php?section=dashboard" class="nav-link">Formations</a>
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a href="promotions.php" class="nav-link">Promotions</a></li>
                        <li class="nav-item"><a href="sessions.php" class="nav-link">Sessions</a></li>
                        <li class="nav-item"><a href="formations.php?section=dashboard" class="nav-link <?php echo $section === 'dashboard' ? 'active' : ''; ?>">Dashboard</a></li>
                        <li class="nav-item"><a href="formations.php?section=attendances" class="nav-link <?php echo $section === 'attendances' ? 'active' : ''; ?>">Présences</a></li>
                        <li class="nav-item"><a href="formations.php?section=examens" class="nav-link <?php echo $section === 'examens' ? 'active' : ''; ?>">Examens</a></li>
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
            <h1 class="mb-4">Gestion des Formations</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
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

            <?php if ($section === 'dashboard'): ?>
                <h2 class="mb-4">Dashboard des Formations</h2>
                <div class="filter-section">
                    <form id="dashboard-filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="dashboard_formation_id">Formation</label>
                                    <select class="form-select" id="dashboard_formation_id" name="formation_id">
                                        <option value="">Toutes les formations</option>
                                        <?php foreach ($formations as $formation): ?>
                                            <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="dashboard_promotion">Promotion</label>
                                    <select class="form-select" id="dashboard_promotion" name="promotion">
                                        <option value="">Toutes les promotions</option>
                                        <?php foreach ($promotions as $promotion): ?>
                                            <option value="<?php echo htmlspecialchars($promotion); ?>">
                                                <?php echo htmlspecialchars($promotion); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Statut</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="status_active" name="status[]" value="active" checked>
                                            <label class="form-check-label" for="status_active">Actif</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="status_inactive" name="status[]" value="inactive" checked>
                                            <label class="form-check-label" for="status_inactive">Inactif</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="dashboard_date_range">Plage de dates</label>
                                    <input type="text" class="form-control" id="dashboard_date_range" name="date_range" placeholder="Sélectionnez une plage de dates">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                            <button type="button" id="reset-filters-btn" class="btn btn-secondary">Réinitialiser</button>
                        </div>
                    </form>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h5>Total Formations</h5>
                            <h3 id="total-formations">0</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h5>Membres Inscrits</h5>
                            <h3 id="total-members">0</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h5>Score Moyen (/100)</h5>
                            <h3 id="avg-score">0</h3>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="data-table-card">
                            <h5>Scores Moyens par Formation</h5>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Formation</th>
                                        <th>Promotion</th>
                                        <th>Score Moyen (/100)</th>
                                    </tr>
                                </thead>
                                <tbody id="avg-scores-table"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-table-card">
                            <h5>Distribution par Statut</h5>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Statut</th>
                                        <th>Nombre de Membres</th>
                                    </tr>
                                </thead>
                                <tbody id="status-distribution-table"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-table-card">
                            <h5>Tendances de Présence</h5>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date de Session</th>
                                        <th>Taux de Présence (%)</th>
                                    </tr>
                                </thead>
                                <tbody id="attendance-trends-table"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-table-card">
                            <h5>Distribution des Scores d'Examen</h5>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Plage de Scores</th>
                                        <th>Nombre de Membres</th>
                                    </tr>
                                </thead>
                                <tbody id="exam-distribution-table"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="data-table-card">
                            <h5>Scores par Membre (Top 10)</h5>
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Points de Présence</th>
                                        <th>Points d'Examen</th>
                                    </tr>
                                </thead>
                                <tbody id="member-scores-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($section === 'attendances'): ?>
                <div class="filter-section">
                    <form id="attendance-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="attendance_formation_id">Formation</label>
                                    <select class="form-select" id="attendance_formation_id" name="formation_id" required>
                                        <option value="">Sélectionnez une formation</option>
                                        <?php foreach ($formations as $formation): ?>
                                            <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="attendance_session_id">Session</label>
                                    <select class="form-select" id="attendance_session_id" name="session_id" required>
                                        <option value="">Sélectionnez une session</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <table id="attendance-table" class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Présence</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                    <div class="mt-3">
                        <button id="save-attendance-btn" class="btn btn-primary" disabled>Enregistrer les Présences</button>
                    </div>
                <?php endif; ?>

            <?php elseif ($section === 'examens'): ?>
                <h2 class="mb-4">Examens</h2>
                <div class="filter-section">
                    <form id="exam-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="exam_formation_id">Formation</label>
                                    <select class="form-select" id="exam_formation_id" name="formation_id" required>
                                        <option value="">Sélectionnez une formation active</option>
                                        <?php foreach ($active_formations as $formation): ?>
                                            <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <form id="exam-points-form">
                    <input type="hidden" name="action" value="save_exam_points">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="formation_id" id="exam_points_formation_id">
                    <table id="exam-table" class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Points d'Examen (/50)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                        <div class="mt-3">
                            <button type="submit" id="save-exam-points-btn" class="btn btn-primary" disabled>Enregistrer les Points</button>
                        </div>
                    <?php endif; ?>
                </form>

                <h2 class="mt-5 mb-4">Totaux</h2>
                <div class="filter-section">
                    <form id="totaux-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="totaux_formation_id">Formation</label>
                                    <select class="form-select" id="totaux_formation_id" name="formation_id" required>
                                        <option value="">Sélectionnez une formation</option>
                                        <?php foreach ($formations_with_results as $formation): ?>
                                            <option value="<?php echo htmlspecialchars($formation['id']); ?>">
                                                <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <table id="totaux-table" class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Points de Présence (/50)</th>
                            <th>Points d'Examen (/50)</th>
                            <th>Total (/100)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="mt-3">
                    <button id="download-totaux-csv-btn" class="btn btn-secondary" disabled>Télécharger en CSV</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
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

        <?php if ($section === 'dashboard'): ?>
            // Initialize Flatpickr
            flatpickr('#dashboard_date_range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                locale: { rangeSeparator: ' à ' }
            });

            // Load Dashboard Data
            function loadDashboardData() {
                const formData = $('#dashboard-filter-form').serializeArray();
                const data = { action: 'get_dashboard_summary' };
                formData.forEach(item => {
                    if (item.name === 'date_range') {
                        const dates = item.value.split(' à ');
                        data['date_start'] = dates[0] || '';
                        data['date_end'] = dates[1] || '';
                    } else {
                        data[item.name] = item.value;
                    }
                });

                console.log('Summary data:', data);

                // Load summary
                $.ajax({
                    url: 'formations.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Summary response:', response);
                        if (response.success) {
                            $('#total-formations').text(response.summary.total_formations);
                            $('#total-members').text(response.summary.total_members);
                            $('#avg-score').text(response.summary.avg_score);
                        } else {
                            showNotification(response.message || 'Erreur lors du chargement du résumé', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Summary error:', xhr.responseText, status, error);
                        showNotification('Erreur serveur lors du chargement du résumé: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                    }
                });

                // Load table data
                data.action = 'get_dashboard_tables';
                console.log('Tables data:', data);
                $.ajax({
                    url: 'formations.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Tables response:', response);
                        if (response.success) {
                            // Update Average Scores Table
                            const avgScoresBody = $('#avg-scores-table');
                            avgScoresBody.empty();
                            if (response.tables.avg_scores.length) {
                                response.tables.avg_scores.forEach(item => {
                                    avgScoresBody.append(`
                                        <tr>
                                            <td>${item.nom}</td>
                                            <td>${item.promotion}</td>
                                            <td>${parseFloat(item.avg_score).toFixed(1)}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                avgScoresBody.append('<tr><td colspan="3">Aucune donnée disponible</td></tr>');
                            }

                            // Update Status Distribution Table
                            const statusBody = $('#status-distribution-table');
                            statusBody.empty();
                            if (response.tables.status_distribution.length) {
                                response.tables.status_distribution.forEach(item => {
                                    statusBody.append(`
                                        <tr>
                                            <td>${item.status === 'active' ? 'Actif' : 'Inactif'}</td>
                                            <td>${item.member_count}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                statusBody.append('<tr><td colspan="2">Aucune donnée disponible</td></tr>');
                            }

                            // Update Attendance Trends Table
                            const attendanceBody = $('#attendance-trends-table');
                            attendanceBody.empty();
                            if (response.tables.attendance_trends.length) {
                                response.tables.attendance_trends.forEach(item => {
                                    attendanceBody.append(`
                                        <tr>
                                            <td>${item.date_session || 'N/A'}</td>
                                            <td>${parseFloat(item.attendance_rate).toFixed(1)}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                attendanceBody.append('<tr><td colspan="2">Aucune donnée disponible</td></tr>');
                            }

                            // Update Exam Distribution Table
                            const examBody = $('#exam-distribution-table');
                            examBody.empty();
                            if (response.tables.exam_distribution.length) {
                                response.tables.exam_distribution.forEach(item => {
                                    examBody.append(`
                                        <tr>
                                            <td>${item.score_range}</td>
                                            <td>${item.count}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                examBody.append('<tr><td colspan="2">Aucune donnée disponible</td></tr>');
                            }

                            // Update Member Scores Table
                            const memberBody = $('#member-scores-table');
                            memberBody.empty();
                            if (response.tables.member_scores.length) {
                                response.tables.member_scores.forEach(item => {
                                    memberBody.append(`
                                        <tr>
                                            <td>${item.nom}</td>
                                            <td>${item.prenom}</td>
                                            <td>${parseFloat(item.attendance_score).toFixed(1)}</td>
                                            <td>${parseFloat(item.exam_score).toFixed(1)}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                memberBody.append('<tr><td colspan="4">Aucune donnée disponible</td></tr>');
                            }
                        } else {
                            showNotification(response.message || 'Erreur lors du chargement des tableaux', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Tables error:', xhr.responseText, status, error);
                        showNotification('Erreur serveur lors du chargement des tableaux: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                    }
                });
            }

            // Initial load
            loadDashboardData();

            // Filter form submission
            $('#dashboard-filter-form').on('submit', function(e) {
                e.preventDefault();
                loadDashboardData();
            });

            // Reset filters
            $('#reset-filters-btn').on('click', function() {
                $('#dashboard_formation_id').val('');
                $('#dashboard_promotion').val('');
                $('#status_active').prop('checked', true);
                $('#status_inactive').prop('checked', true);
                $('#dashboard_date_range').val('');
                loadDashboardData();
            });

        <?php elseif ($section === 'attendances'): ?>
            // Initialize Attendance Table
            const attendanceTable = $('#attendance-table').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                pageLength: 10,
                responsive: true,
                columns: [
                    { data: 'nom' },
                    { data: 'prenom' },
                    {
                        data: 'status',
                        render: function(data, type, row) {
                            <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                return `
                                    <select class="form-select attendance-status" data-member-id="${row.id}">
                                        <option value="present" ${data === 'present' ? 'selected' : ''}>Présent</option>
                                        <option value="absent" ${data === 'absent' ? 'selected' : ''}>Absent</option>
                                    </select>
                                `;
                            <?php else: ?>
                                return data === 'present' ? 'Présent' : data === 'absent' ? 'Absent' : '-';
                            <?php endif; ?>
                        }
                    }
                ]
            });

            // Load Sessions
            $('#attendance_formation_id').on('change', function() {
                const formationId = $(this).val();
                $('#attendance_session_id').empty().append('<option value="">Sélectionnez une session</option>');
                attendanceTable.clear().draw();
                $('#save-attendance-btn').prop('disabled', true);

                if (formationId) {
                    $.ajax({
                        url: 'formations.php',
                        type: 'POST',
                        data: { action: 'get_sessions', formation_id: formationId },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Sessions response:', response);
                            if (response.success) {
                                response.sessions.forEach(session => {
                                    $('#attendance_session_id').append(
                                        `<option value="${session.id}">${session.nom} ${session.date_session ? '(' + session.date_session + ')' : ''}</option>`
                                    );
                                });
                            } else {
                                showNotification(response.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            console.error('Sessions error:', xhr.responseText);
                            showNotification('Erreur serveur: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                        }
                    });
                }
            });

            // Load Attendance
            $('#attendance_session_id').on('change', function() {
                const formationId = $('#attendance_formation_id').val();
                const sessionId = $(this).val();
                attendanceTable.clear().draw();
                $('#save-attendance-btn').prop('disabled', true);

                if (formationId && sessionId) {
                    $.ajax({
                        url: 'formations.php',
                        type: 'POST',
                        data: { action: 'get_attendance', formation_id: formationId, session_id: sessionId },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Attendance response:', response);
                            if (response.success) {
                                attendanceTable.rows.add(response.members).draw();
                                $('#save-attendance-btn').prop('disabled', false);
                            } else {
                                showNotification(response.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            console.error('Attendance error:', xhr.responseText);
                            showNotification('Erreur serveur: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                        }
                    });
                }
            });

            // Save Attendance
            $('#save-attendance-btn').on('click', function() {
                const formationId = $('#attendance_formation_id').val();
                const sessionId = $('#attendance_session_id').val();
                const attendance = {};
                $('.attendance-status').each(function() {
                    const memberId = $(this).data('member-id');
                    attendance[memberId] = $(this).val();
                });

                $.ajax({
                    url: 'formations.php',
                    type: 'POST',
                    data: {
                        action: 'save_attendance',
                        formation_id: formationId,
                        session_id: sessionId,
                        attendance: attendance,
                        csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Save attendance response:', response);
                        if (response.success) {
                            showNotification(response.message, 'success');
                        } else {
                            showNotification(response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        console.error('Save attendance error:', xhr.responseText);
                        showNotification('Erreur serveur: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                    }
                });
            });

        <?php elseif ($section === 'examens'): ?>
            // Initialize Exam Table
            const examTable = $('#exam-table').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                pageLength: 10,
                responsive: true,
                columns: [
                    { data: 'nom' },
                    { data: 'prenom' },
                    {
                        data: 'exam_score',
                        render: function(data, type, row) {
                            <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                return data ? `<span>${data}</span>` : `<input type="number" class="form-control exam-points-input" name="exam_points[${row.id}]" value="${data || ''}" min="0" max="50" step="0.1">`;
                            <?php else: ?>
                                return data || '-';
                            <?php endif; ?>
                        }
                    }
                ]
            });

            // Initialize Totaux Table
            const totauxTable = $('#totaux-table').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                pageLength: 10,
                responsive: true,
                columns: [
                    { data: 'nom' },
                    { data: 'prenom' },
                    { data: 'attendance_score' },
                    { data: 'exam_score' },
                    { data: 'total_score' }
                ]
            });

            // Load Enrolled Members
            $('#exam_formation_id').on('change', function() {
                const formationId = $(this).val();
                examTable.clear().draw();
                $('#exam_points_formation_id').val(formationId);
                $('#save-exam-points-btn').prop('disabled', true);

                if (formationId) {
                    $.ajax({
                        url: 'formations.php',
                        type: 'POST',
                        data: { action: 'get_enrolled_members_exam', formation_id: formationId },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Enrolled members response:', response);
                            if (response.success) {
                                examTable.rows.add(response.members).draw();
                                $('#save-exam-points-btn').prop('disabled', response.members.length === 0);
                            } else {
                                showNotification(response.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            console.error('Enrolled members error:', xhr.responseText);
                            showNotification('Erreur serveur: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                        }
                    });
                }
            });

            // Save Exam Points
            $('#exam-points-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const formationId = $('#exam_formation_id').val();

                if (!formationId) {
                    showNotification('Veuillez sélectionner une formation.', 'warning');
                    return;
                }

                $.ajax({
                    url: 'formations.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Save exam points response:', response);
                        if (response.success) {
                            showNotification(response.message, 'success');
                            $.ajax({
                                url: 'formations.php',
                                type: 'POST',
                                data: { action: 'get_enrolled_members_exam', formation_id: formationId },
                                dataType: 'json',
                                success: function(refreshResponse) {
                                    if (refreshResponse.success) {
                                        examTable.clear().rows.add(refreshResponse.members).draw();
                                        $('#save-exam-points-btn').prop('disabled', refreshResponse.members.length === 0 || refreshResponse.updated === 0);
                                    } else {
                                        showNotification(refreshResponse.message, 'error');
                                    }
                                },
                                error: function(xhr) {
                                    console.error('Refresh exam error:', xhr.responseText);
                                    showNotification('Erreur lors du rafraîchissement: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                                }
                            });
                        } else {
                            showNotification(response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        console.error('Save exam points error:', xhr.responseText);
                        showNotification('Erreur serveur: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                    }
                });
            });

            // Load Totaux Data
            $('#totaux_formation_id').on('change', function() {
                const formationId = $(this).val();
                totauxTable.clear().draw();
                $('#download-totaux-csv-btn').prop('disabled', true);

                if (formationId) {
                    $.ajax({
                        url: 'formations.php',
                        type: 'POST',
                        data: { action: 'get_totaux_data', formation_id: formationId },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Totaux response:', response);
                            if (response.success) {
                                totauxTable.rows.add(response.members).draw();
                                $('#download-totaux-csv-btn').prop('disabled', response.members.length === 0);
                            } else {
                                showNotification(response.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            console.error('Totaux error:', xhr.responseText);
                            showNotification('Erreur serveur: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                        }
                    });
                }
            });

            // Download Totaux CSV
            $('#download-totaux-csv-btn').on('click', function() {
                const formationId = $('#totaux_formation_id').val();
                if (!formationId) {
                    showNotification('Veuillez sélectionner une formation.', 'warning');
                    return;
                }

                $.ajax({
                    url: 'formations.php',
                    type: 'POST',
                    data: { action: 'download_totaux_csv', formation_id: formationId },
                    xhrFields: { responseType: 'blob' },
                    success: function(data, status, xhr) {
                        const disposition = xhr.getResponseHeader('Content-Disposition');
                        let filename = 'Totaux.csv';
                        if (disposition && disposition.indexOf('attachment') !== -1) {
                            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                            if (matches != null && matches[1]) {
                                filename = matches[1].replace(/['"]/g, '');
                            }
                        }

                        const url = window.URL.createObjectURL(data);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        showNotification('Téléchargement du CSV démarré.', 'success');
                    },
                    error: function(xhr) {
                        console.error('Download CSV error:', xhr.responseText);
                        showNotification('Erreur lors du téléchargement du CSV: ' + (xhr.responseJSON?.message || 'Erreur inconnue'), 'error');
                    }
                });
            });
        <?php endif; ?>
    });
</script>
</body>
</html>