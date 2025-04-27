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

// Create Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create' && $_SESSION['role'] === 'admin') {
    try {
        $nom = trim($_POST['nom'] ?? '');
        $promotion = trim($_POST['promotion'] ?? '');
        $date_debut = trim($_POST['date_debut'] ?? '');
        $date_fin = trim($_POST['date_fin'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($nom) || empty($promotion) || empty($date_debut) || empty($date_fin)) {
            throw new Exception("Tous les champs sont requis.");
        }
        // Fetch valid nom values from the database
        $valid_noms = $db->query("SELECT DISTINCT nom FROM formations")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($nom, $valid_noms)) {
            throw new Exception("Formation invalide.");
        }
        if (strtotime($date_debut) > strtotime($date_fin)) {
            throw new Exception("La date de début doit être avant la date de fin.");
        }

        if ($active) {
            $db->exec("UPDATE formations SET active = 0");
        }
        $stmt = $db->prepare("
            INSERT INTO formations (nom, promotion, date_debut, date_fin, active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $promotion, $date_debut, $date_fin, $active]);

        $promotion_id = $db->lastInsertId();

        logAction($_SESSION['user_id'], "Création formation: $promotion_id ($nom - $promotion)", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Promotion créée avec succès (ID: $promotion_id)."]);
    } catch (Exception $e) {
        error_log("Create error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur création formation: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur création : " . $e->getMessage()]);
    }
    exit;
}

// Update Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update' && in_array($_SESSION['role'], ['admin', 'diacre'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $promotion = trim($_POST['promotion'] ?? '');
        $date_debut = trim($_POST['date_debut'] ?? '');
        $date_fin = trim($_POST['date_fin'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        error_log("Update inputs: id=$id, nom=$nom, promotion=$promotion, date_debut=$date_debut, date_fin=$date_fin, active=$active");

        if (empty($id) || empty($nom) || empty($promotion) || empty($date_debut) || empty($date_fin)) {
            throw new Exception("Tous les champs sont requis.");
        }
        $valid_noms = $db->query("SELECT DISTINCT nom FROM formations")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($nom, $valid_noms)) {
            throw new Exception("Formation invalide.");
        }
        if (strtotime($date_debut) > strtotime($date_fin)) {
            throw new Exception("La date de début doit être avant la date de fin.");
        }

        if ($active) {
            error_log("Resetting active status for all formations");
            $db->exec("UPDATE formations SET active = 0");
        }
        $stmt = $db->prepare("
            UPDATE formations SET nom = ?, promotion = ?, date_debut = ?, date_fin = ?, active = ?
            WHERE id = ?
        ");
        error_log("Executing update for id=$id");
        $stmt->execute([$nom, $promotion, $date_debut, $date_fin, $active, $id]);

        logAction($_SESSION['user_id'], "Mise à jour formation: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Promotion mise à jour avec succès (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Update error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur mise à jour formation: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur mise à jour : " . $e->getMessage()]);
    }
    exit;
}

// Delete Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete' && $_SESSION['role'] === 'admin') {
    try {
        $id = $_POST['promotion_id'] ?? '';
        if (empty($id)) throw new Exception("ID manquant.");

        $stmt = $db->prepare("DELETE FROM formations WHERE id = ?");
        $stmt->execute([$id]);

        logAction($_SESSION['user_id'], "Suppression formation: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        echo json_encode(['success' => true, 'message' => "Promotion supprimée (ID: $id)."]);
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        logAction($_SESSION['user_id'], "Erreur suppression formation: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
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

        $stmt = $db->prepare("SELECT * FROM formations WHERE id = ?");
        $stmt->execute([$promotion_id]);
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$promotion) throw new Exception("Promotion introuvable.");

        echo json_encode(['success' => true, 'promotion' => $promotion]);
    } catch (Exception $e) {
        error_log("Get promotion error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
    }
    exit;
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

// Fetch valid nom values for forms
try {
    $valid_noms = $db->query("SELECT DISTINCT nom FROM formations")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $valid_noms = ['Isoko Classe 1', 'Isoko Classe 2', 'Isoko Classe 3'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Promotions</title>
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
                        <li class="nav-item"><a href="promotions.php" class="nav-link active">Promotions</a></li>
                        <li class="nav-item"><a href="sessions.php" class="nav-link">Sessions</a></li>
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
            <h1 class="mb-4">Gestion des Promotions</h1>

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
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#createPromotionModal">Créer une Promotion</button>
                <?php endif; ?>
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
                        <tr id="promotion-<?php echo htmlspecialchars($promotion['id']); ?>">
                            <td><?php echo htmlspecialchars($promotion['id']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['nom']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['promotion']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['date_debut']); ?></td>
                            <td><?php echo htmlspecialchars($promotion['date_fin']); ?></td>
                            <td><?php echo $promotion['active'] ? 'Oui' : 'Non'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-info view-promotion" data-promotion-id="<?php echo htmlspecialchars($promotion['id']); ?>">Voir</button>
                                <?php if (in_array($_SESSION['role'], ['admin', 'diacre'])): ?>
                                    <button class="btn btn-sm btn-warning edit-promotion" data-promotion-id="<?php echo htmlspecialchars($promotion['id']); ?>">Modifier</button>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <button class="btn btn-sm btn-danger delete-promotion" data-promotion-id="<?php echo htmlspecialchars($promotion['id']); ?>">Supprimer</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Create Promotion Modal -->
            <div class="modal fade" id="createPromotionModal" tabindex="-1" role="dialog" aria-labelledby="createPromotionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createPromotionModalLabel">Créer une Promotion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="create-promotion-form">
                            <input type="hidden" name="action" value="create">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_nom">Formation *</label>
                                            <select class="form-control" id="create_nom" name="nom" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($valid_noms as $nom): ?>
                                                    <option value="<?php echo htmlspecialchars($nom); ?>"><?php echo htmlspecialchars($nom); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_promotion">Promotion *</label>
                                            <input type="text" class="form-control" id="create_promotion" name="promotion" placeholder="ex: 2023-01" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_date_debut">Date Début *</label>
                                            <input type="date" class="form-control" id="create_date_debut" name="date_debut" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="create_date_fin">Date Fin *</label>
                                            <input type="date" class="form-control" id="create_date_fin" name="date_fin" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group form-check">
                                            <input type="checkbox" class="form-check-input" id="create_active" name="active">
                                            <label class="form-check-label" for="create_active">Marquer comme Active</label>
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

            <!-- Edit Promotion Modal -->
            <div class="modal fade" id="editPromotionModal" tabindex="-1" role="dialog" aria-labelledby="editPromotionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="editPromotionModalLabel">Modifier la Promotion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <form id="edit-promotion-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_nom">Formation *</label>
                                            <select class="form-control" id="edit_nom" name="nom" required>
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($valid_noms as $nom): ?>
                                                    <option value="<?php echo htmlspecialchars($nom); ?>"><?php echo htmlspecialchars($nom); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_promotion">Promotion *</label>
                                            <input type="text" class="form-control" id="edit_promotion" name="promotion" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_date_debut">Date Début *</label>
                                            <input type="date" class="form-control" id="edit_date_debut" name="date_debut" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_date_fin">Date Fin *</label>
                                            <input type="date" class="form-control" id="edit_date_fin" name="date_fin" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_active" name="active">
                                            <label class="form-check-label" for="edit_active">Marquer comme Active</label>
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

            <!-- View Promotion Modal -->
            <div class="modal fade" id="viewPromotionModal" tabindex="-1" role="dialog" aria-labelledby="viewPromotionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewPromotionModalLabel">Détails de la Promotion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>ID:</strong> <span id="view_id"></span></p>
                            <p><strong>Formation:</strong> <span id="view_nom"></span></p>
                            <p><strong>Promotion:</strong> <span id="view_promotion"></span></p>
                            <p><strong>Date Début:</strong> <span id="view_date_debut"></span></p>
                            <p><strong>Date Fin:</strong> <span id="view_date_fin"></span></p>
                            <p><strong>Active:</strong> <span id="view_active"></span></p>
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
                    // Create promotion
                    $('#create-promotion-form').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: $(this).serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message || 'Promotion créée avec succès.');
                                    $('#createPromotionModal').modal('hide');
                                    $('#create-promotion-form')[0].reset();
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

                    // View promotion
                    $(document).on('click', '.view-promotion', function() {
                        var promotionId = $(this).data('promotion-id');
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: { action: 'get_promotion', promotion_id: promotionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var promotion = response.promotion;
                                    $('#view_id').text(promotion.id);
                                    $('#view_nom').text(promotion.nom);
                                    $('#view_promotion').text(promotion.promotion);
                                    $('#view_date_debut').text(promotion.date_debut);
                                    $('#view_date_fin').text(promotion.date_fin);
                                    $('#view_active').text(promotion.active ? 'Oui' : 'Non');
                                    $('#viewPromotionModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || error));
                            }
                        });
                    });

                    // Edit promotion
                    $(document).on('click', '.edit-promotion', function() {
                        var promotionId = $(this).data('promotion-id');
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: { action: 'get_promotion', promotion_id: promotionId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    var promotion = response.promotion;
                                    $('#edit_id').val(promotion.id);
                                    $('#edit_nom').val(promotion.nom);
                                    $('#edit_promotion').val(promotion.promotion);
                                    $('#edit_date_debut').val(promotion.date_debut);
                                    $('#edit_date_fin').val(promotion.date_fin);
                                    $('#edit_active').prop('checked', promotion.active == 1);
                                    $('#editPromotionModal').modal('show');
                                } else {
                                    alert('Erreur : ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Erreur serveur lors de la récupération : ' + (xhr.responseJSON?.message || error));
                            }
                        });
                    });

                    // Update promotion
                    $('#edit-promotion-form').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: 'promotions.php',
                            type: 'POST',
                            data: $(this).serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message || 'Promotion mise à jour avec succès.');
                                    $('#editPromotionModal').modal('hide');
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

                    // Delete promotion
                    $(document).on('click', '.delete-promotion', function() {
                        var promotionId = $(this).data('promotion-id');
                        if (confirm('Êtes-vous sûr de vouloir supprimer cette promotion ?')) {
                            $.ajax({
                                url: 'promotions.php',
                                type: 'POST',
                                data: { action: 'delete', promotion_id: promotionId },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        alert(response.message);
                                        $('#promotion-' + promotionId).remove();
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