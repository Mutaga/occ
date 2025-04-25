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

// Récupérer les données pour le dropdown des parents
try {
    $db = getDBConnection();
    $parents = $db->query("SELECT id, nom, prenom FROM members ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données : " . $e->getMessage();
    $parents = [];
}

// Initialiser les messages
$error = null;
$success = null;

// Liste des valeurs valides
$valid_sexes = ['Masculin', 'Féminin'];

// Ajout d'un enfant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre') && !isset($_POST['action'])) {
    try {
        $nom = trim($_POST['nom'] ?? '') ?: null;
        $prenom = trim($_POST['prenom'] ?? '') ?: null;
        $sexe = trim($_POST['sexe'] ?? '') ?: null;
        $parent_nom = trim($_POST['parent_nom'] ?? '') ?: null;
        $parent_prenom = trim($_POST['parent_prenom'] ?? '') ?: null;
        $parent_telephone1 = trim($_POST['parent_telephone1'] ?? '') ?: null;
        $parent_telephone2 = trim($_POST['parent_telephone2'] ?? '') ?: null;
        $parent_email = trim($_POST['parent_email'] ?? '') ?: null;
        $parent_id = !empty($_POST['parent_id']) ? trim($_POST['parent_id']) : null;

        // Validation
        if (empty($nom) || empty($prenom) || empty($sexe) || empty($parent_id)) {
            throw new Exception("Les champs Nom, Prénom, Sexe et ID du Parent sont requis.");
        }
        if ($sexe !== null && !in_array($sexe, $valid_sexes)) {
            throw new Exception("Valeur invalide pour Sexe.");
        }
        // Vérifier si parent_id existe
        $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE id = ?");
        $stmt->execute([$parent_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("ID du parent invalide.");
        }

        // Insertion (exclude id, let AUTO_INCREMENT handle it)
        $stmt = $db->prepare("
            INSERT INTO children (
                nom, prenom, sexe, parent_nom, parent_prenom, parent_telephone1, 
                parent_telephone2, parent_email, parent_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nom, $prenom, $sexe, $parent_nom, $parent_prenom, $parent_telephone1,
            $parent_telephone2, $parent_email, $parent_id
        ]);

        // Récupérer l'ID généré
        $id = $db->lastInsertId();

        logAction($_SESSION['user_id'], "Ajout enfant: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        $success = "Enfant ajouté avec succès (ID: $id).";
    } catch (Exception $e) {
        $error = "Erreur lors de l'ajout : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur ajout enfant: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Mise à jour d'un enfant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre') && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = trim($_POST['id'] ?? '');
        $nom = trim($_POST['nom'] ?? '') ?: null;
        $prenom = trim($_POST['prenom'] ?? '') ?: null;
        $sexe = trim($_POST['sexe'] ?? '') ?: null;
        $parent_nom = trim($_POST['parent_nom'] ?? '') ?: null;
        $parent_prenom = trim($_POST['parent_prenom'] ?? '') ?: null;
        $parent_telephone1 = trim($_POST['parent_telephone1'] ?? '') ?: null;
        $parent_telephone2 = trim($_POST['parent_telephone2'] ?? '') ?: null;
        $parent_email = trim($_POST['parent_email'] ?? '') ?: null;
        $parent_id = !empty($_POST['parent_id']) ? trim($_POST['parent_id']) : null;

        // Validation
        if (empty($id) || empty($nom) || empty($prenom) || empty($sexe) || empty($parent_id)) {
            throw new Exception("Champs requis manquants.");
        }
        if ($sexe !== null && !in_array($sexe, $valid_sexes)) {
            throw new Exception("Valeur invalide pour Sexe.");
        }
        // Vérifier si parent_id existe
        $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE id = ?");
        $stmt->execute([$parent_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("ID du parent invalide.");
        }

        // Mise à jour
        $stmt = $db->prepare("
            UPDATE children SET
                nom = ?, prenom = ?, sexe = ?, parent_nom = ?, parent_prenom = ?, 
                parent_telephone1 = ?, parent_telephone2 = ?, parent_email = ?, parent_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nom, $prenom, $sexe, $parent_nom, $parent_prenom, $parent_telephone1,
            $parent_telephone2, $parent_email, $parent_id, $id
        ]);

        logAction($_SESSION['user_id'], "Mise à jour enfant: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        $success = "Enfant mis à jour avec succès (ID: $id).";
    } catch (Exception $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur mise à jour enfant: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SESSION['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        $logFile = 'error_log.txt';
        file_put_contents($logFile, "Début suppression enfant: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        $id = $_POST['child_id'] ?? '';
        if (empty($id)) throw new Exception("ID de l'enfant manquant.");
        file_put_contents($logFile, "ID enfant: $id\n", FILE_APPEND);

        $db->beginTransaction();

        $stmt = $db->prepare("DELETE FROM children WHERE id = ?");
        $stmt->execute([$id]);
        file_put_contents($logFile, "Enfant supprimé\n", FILE_APPEND);

        $db->commit();
        logAction($_SESSION['user_id'], "Suppression enfant: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Enfant supprimé (ID: $id)."]);
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
            SELECT c.*, m.nom AS parent_nom_membre, m.prenom AS parent_prenom_membre
            FROM children c
            LEFT JOIN members m ON c.parent_id = m.id
            WHERE c.id LIKE :term 
               OR c.nom LIKE :term 
               OR c.prenom LIKE :term 
               OR c.sexe LIKE :term 
               OR c.parent_id LIKE :term
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_child' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre')) {
    try {
        $id = $_POST['child_id'] ?? '';
        if (empty($id)) throw new Exception("ID manquant.");
        $stmt = $db->prepare("
            SELECT c.*, m.nom AS parent_nom_membre, m.prenom AS parent_prenom_membre
            FROM children c
            LEFT JOIN members m ON c.parent_id = m.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($child) {
            echo json_encode(['success' => true, 'child' => $child]);
        } else {
            throw new Exception("Enfant non trouvé : $id");
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
            SELECT c.*, m.nom AS parent_nom_membre, m.prenom AS parent_prenom_membre
            FROM children c
            LEFT JOIN members m ON c.parent_id = m.id
        ";
        if ($search_term) {
            $query .= " WHERE c.id LIKE ? OR c.nom LIKE ? OR c.prenom LIKE ? OR c.sexe LIKE ? OR c.parent_id LIKE ?";
            $stmt = $db->prepare($query);
            $stmt->execute(["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"]);
        } else {
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="children_list.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Nom', 'Prénom', 'Sexe', 'Parent Nom', 'Parent Prénom', 'Téléphone 1', 'Téléphone 2', 'Email', 'Parent ID']);
        foreach ($children as $child) {
            fputcsv($output, [
                $child['id'], $child['nom'], $child['prenom'], $child['sexe'] ?? '-',
                $child['parent_nom'] ?? '-', $child['parent_prenom'] ?? '-',
                $child['parent_telephone1'] ?? '-', $child['parent_telephone2'] ?? '-',
                $child['parent_email'] ?? '-', $child['parent_id'] ?? '-'
            ]);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = "Erreur export CSV : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur export CSV: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Liste des enfants
try {
    $childrenthechildren = $db->query("
        SELECT c.*, m.nom AS parent_nom_membre, m.prenom AS parent_prenom_membre
        FROM children c
        LEFT JOIN members m ON c.parent_id = m.id
        ORDER BY c.nom, c.prenom
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur récupération enfants : " . $e->getMessage();
    $children = [];
}

// Détails d'un enfant
$view_child = null;
if (isset($_GET['view'])) {
    try {
        $stmt = $db->prepare("
            SELECT c.*, m.nom AS parent_nom_membre, m.prenom AS parent_prenom_membre
            FROM children c
            LEFT JOIN members m ON c.parent_id = m.id
            WHERE c.id = ?
        ");
        $stmt->execute([$_GET['view']]);
        $view_child = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$view_child) $error = "Enfant non trouvé : " . htmlspecialchars($_GET['view']);
    } catch (Exception $e) {
        $error = "Erreur récupération enfant : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Enfants (École du Dimanche)</title>
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
        #updateChildModal .modal-body, #addChildModal .modal-body {
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
        }
        #viewChildModal .modal-content, #deleteConfirmModal .modal-content, #searchModal .modal-content {
            z-index: 1050;
        }
        #addChildModal .modal-content, #updateChildModal .modal-content {
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
        #search-results-table .btn-sm, #children-table .btn-sm {
            font-size: 12px;
            margin-right: 5px;
            padding: 4px 8px;
        }
        #search-results-table th:last-child, #search-results-table td:last-child,
        #children-table th:last-child, #children-table td:last-child {
            width: 200px;
            white-space: nowrap;
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
уп
                <li class="nav-item"><a href="members.php" class="nav-link">Membres</a></li>
                <li class="nav-item"><a href="children.php" class="nav-link active">Enfants</a></li>
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
            <h1 class="mb-4">Gestion des Enfants (École du Dimanche)</h1>

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
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addChildModal">Ajouter un Enfant</button>
                <?php endif; ?>
                <button class="btn btn-info" data-toggle="modal" data-target="#searchModal">Rechercher</button>
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                    <form action="children.php" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_csv">
                        <input type="hidden" name="search_term" id="export-csv-search-term" value="">
                        <button type="submit" class="btn btn-success">Exporter en CSV</button>
                    </form>
                <?php endif; ?>
            </div>

            <table id="children-table" class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nom et Prénom</th>
                        <th>Sexe</th>
                        <th>Parent/Tuteur</th>
                        <th>Téléphone 1</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($children as $child): ?>
                        <tr id="child-<?php echo htmlspecialchars($child['id']); ?>">
                            <td><?php echo htmlspecialchars($child['id']); ?></td>
                            <td><?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($child['sexe'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($child['parent_nom'] . ' ' . $child['parent_prenom']); ?></td>
                            <td><?php echo htmlspecialchars($child['parent_telephone1'] ?: '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info view-child" data-child-id="<?php echo htmlspecialchars($child['id']); ?>">Voir</button>
                                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                                    <button class="btn btn-sm btn-warning update-child" data-child-id="<?php echo htmlspecialchars($child['id']); ?>">Modifier</button>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <button class="btn btn-sm btn-danger delete-child" data-child-id="<?php echo htmlspecialchars($child['id']); ?>" data-child-name="<?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?>">Supprimer</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Child Modal -->
            <div class="modal fade" id="addChildModal" tabindex="-1" role="dialog" aria-labelledby="addChildModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="addChildModalLabel">Ajouter un Enfant</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="add-child-form" action="children.php" method="POST">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nom">Nom de l'Enfant *</label>
                                            <input type="text" class="form-control" id="nom" name="nom" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="prenom">Prénom(s) de l'Enfant *</label>
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
                                            <label for="parent_nom">Nom du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="parent_nom" name="parent_nom">
                                        </div>
                                        <div class="form-group">
                                            <label for="parent_prenom">Prénom du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="parent_prenom" name="parent_prenom">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="parent_telephone1">Téléphone 1 du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="parent_telephone1" name="parent_telephone1">
                                        </div>
                                        <div class="form-group">
                                            <label for="parent_telephone2">Téléphone 2 du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="parent_telephone2" name="parent_telephone2">
                                        </div>
                                        <div class="form-group">
                                            <label for="parent_email">E-mail du Parent/Tuteur</label>
                                            <input type="email" class="form-control" id="parent_email" name="parent_email">
                                        </div>
                                        <div class="form-group">
                                            <label for="parent_id">ID du Parent/Tuteur *</label>
                                            <select class="form-control" id="parent_id" name="parent_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($parents as $parent): ?>
                                                    <option value="<?php echo htmlspecialchars($parent['id']); ?>">
                                                        <?php echo htmlspecialchars($parent['id'] . ' - ' . $parent['nom'] . ' ' . $parent['prenom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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

            <!-- Update Child Modal -->
            <div class="modal fade" id="updateChildModal" tabindex="-1" role="dialog" aria-labelledby="updateChildModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="updateChildModalLabel">Modifier un Enfant</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="update-child-form" action="children.php" method="POST">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" id="update_id" name="id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_nom">Nom de l'Enfant *</label>
                                            <input type="text" class="form-control" id="update_nom" name="nom" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="update_prenom">Prénom(s) de l'Enfant *</label>
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
                                            <label for="update_parent_nom">Nom du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="update_parent_nom" name="parent_nom">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_parent_prenom">Prénom du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="update_parent_prenom" name="parent_prenom">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_parent_telephone1">Téléphone 1 du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="update_parent_telephone1" name="parent_telephone1">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_parent_telephone2">Téléphone 2 du Parent/Tuteur</label>
                                            <input type="text" class="form-control" id="update_parent_telephone2" name="parent_telephone2">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_parent_email">E-mail du Parent/Tuteur</label>
                                            <input type="email" class="form-control" id="update_parent_email" name="parent_email">
                                        </div>
                                        <div class="form-group">
                                            <label for="update_parent_id">ID du Parent/Tuteur *</label>
                                            <select class="form-control" id="update_parent_id" name="parent_id" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($parents as $parent): ?>
                                                    <option value="<?php echo htmlspecialchars($parent['id']); ?>">
                                                        <?php echo htmlspecialchars($parent['id'] . ' - ' . $parent['nom'] . ' ' . $parent['prenom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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

            <!-- View Child Modal -->
            <div class="modal fade" id="viewChildModal" tabindex="-1" role="dialog" aria-labelledby="viewChildModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewChildModalLabel">Détails de l'Enfant</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if ($view_child): ?>
                                <h6>Informations de l'Enfant</h6>
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($view_child['id']); ?></p>
                                <p><strong>Nom:</strong> <?php echo htmlspecialchars($view_child['nom']); ?></p>
                                <p><strong>Prénom:</strong> <?php echo htmlspecialchars($view_child['prenom']); ?></p>
                                <p><strong>Sexe:</strong> <?php echo htmlspecialchars($view_child['sexe'] ?: '-'); ?></p>
                                <h6>Informations du Parent/Tuteur</h6>
                                <p><strong>Nom:</strong> <?php echo htmlspecialchars($view_child['parent_nom'] ?: '-'); ?></p>
                                <p><strong>Prénom:</strong> <?php echo htmlspecialchars($view_child['parent_prenom'] ?: '-'); ?></p>
                                <p><strong>Téléphone 1:</strong> <?php echo htmlspecialchars($view_child['parent_telephone1'] ?: '-'); ?></p>
                                <p><strong>Téléphone 2:</strong> <?php echo htmlspecialchars($view_child['parent_telephone2'] ?: '-'); ?></p>
                                <p><strong>E-mail:</strong> <?php echo htmlspecialchars($view_child['parent_email'] ?: '-'); ?></p>
                                <p><strong>ID du Parent:</strong> <?php echo htmlspecialchars($view_child['parent_id'] ?: '-'); ?></p>
                                <p><strong>Nom du Parent (Membre):</strong> <?php echo htmlspecialchars($view_child['parent_nom_membre'] . ' ' . $view_child['parent_prenom_membre'] ?: '-'); ?></p>
                            <?php else: ?>
                                <p>Aucun enfant sélectionné.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <?php if ($view_child && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre')): ?>
                                <button class="btn btn-warning update-child" data-child-id="<?php echo htmlspecialchars($view_child['id']); ?>">Modifier</button>
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
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            Êtes-vous sûr de vouloir supprimer l'enfant <strong id="delete-child-name"></strong> ? Cette action est irréversible.
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
                            <h5 class="modal-title" id="searchModalLabel">Rechercher un Enfant</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="search-bar mb-3">
                                <input type="text" class="form-control" id="search-input" placeholder="Rechercher par ID, Nom, Prénom, Sexe ou ID Parent...">
                                <span class="clear-search" id="clear-search" style="display: none;">×</span>
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
                                            <th>Sexe</th>
                                            <th>Parent/Tuteur</th>
                                            <th>Téléphone 1</th>
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
            <script>
                $(document).ready(function() {
                    // Bouton Voir
                    $(document).on('click', '.view-child', function(e) {
                        e.preventDefault();
                        var childId = $(this).data('child-id');
                        window.location.href = 'children.php?view=' + childId;
                    });

                    // Bouton Modifier
                    $(document).on('click', '.update-child', function() {
                        var childId = $(this).data('child-id');
                        $('.modal').modal('hide');
                        $.ajax({
                            url: 'children.php',
                            type: 'POST',
                            data: { action: 'get_child', child_id: childId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var child = response.child;
                                    $('#update_id').val(child.id);
                                    $('#update_nom').val(child.nom);
                                    $('#update_prenom').val(child.prenom);
                                    $('#update_sexe').val(child.sexe);
                                    $('#update_parent_nom').val(child.parent_nom);
                                    $('#update_parent_prenom').val(child.parent_prenom);
                                    $('#update_parent_telephone1').val(child.parent_telephone1);
                                    $('#update_parent_telephone2').val(child.parent_telephone2);
                                    $('#update_parent_email').val(child.parent_email);
                                    $('#update_parent_id').val(child.parent_id);
                                    $('#updateChildModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr) {
                                alert('Erreur lors de la récupération des données.');
                                console.error('Erreur AJAX (get_child) : ', xhr.responseText);
                            }
                        });
                    });

                    // Bouton Supprimer
                    $(document).on('click', '.delete-child', function() {
                        var childId = $(this).data('child-id');
                        var childName = $(this).data('child-name');
                        $('#delete-child-name').text(childName);
                        $('#deleteConfirmModal').data('child-id', childId);
                        $('#deleteConfirmModal').modal('show');
                    });

                    // Confirmation suppression
                    $('#confirm-delete').on('click', function() {
                        var childId = $('#deleteConfirmModal').data('child-id');
                        $.ajax({
                            url: 'children.php',
                            type: 'POST',
                            data: { action: 'delete', child_id: childId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $('#child-' + childId).remove();
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
                                    url: 'children.php',
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
                                                response.results.forEach(function(child) {
                                                    var row = `
                                                        <tr id="search-child-${child.id}">
                                                            <td>${child.id || '-'}</td>
                                                            <td>${(child.nom || '') + ' ' + (child.prenom || '')}</td>
                                                            <td>${child.sexe || '-'}</td>
                                                            <td>${(child.parent_nom || '') + ' ' + (child.parent_prenom || '')}</td>
                                                            <td>${child.parent_telephone1 || '-'}</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info view-child" data-child-id="${child.id}">Voir</button>
                                                                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                                                                    <button class="btn btn-sm btn-warning update-child" data-child-id="${child.id}">Modifier</button>
                                                                <?php endif; ?>
                                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                                    <button class="btn btn-sm btn-danger delete-child" data-child-id="${child.id}" data-child-name="${(child.nom || '') + ' ' + (child.prenom || '')}">Supprimer</button>
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
                    <?php if ($view_child): ?>
                        $(window).on('load', function() {
                            $('#viewChildModal').modal('show');
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