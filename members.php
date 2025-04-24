<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Récupérer les données pour les dropdowns
$db = getDBConnection();
$formations = $db->query("SELECT id, nom, promotion FROM formations")->fetchAll(PDO::FETCH_ASSOC);
$oikos = $db->query("SELECT id, nom FROM oikos")->fetchAll(PDO::FETCH_ASSOC);

// Initialiser les messages d'erreur/succès
$error = null;
$success = null;

// Liste des valeurs valides pour departement
$valid_departements = ['Media', 'Comptabilité', 'Sécurité', 'Chorale', 'SundaySchool', 'Protocole', 'Pastorat', 'Diaconat'];

// Fonction pour convertir DD/MM/YYYY en YYYY-MM-DD pour MySQL
function convertToMySQLDate($date) {
    if (empty($date)) return null;
    $parts = explode('/', $date);
    if (count($parts) !== 3 || !checkdate($parts[1], $parts[0], $parts[2])) {
        throw new Exception("Format de date invalide : $date. Attendu : JJ/MM/AAAA");
    }
    return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
}

// Gestion de l'ajout d'un membre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre')) {
    try {
        $id = generateMemberId();
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = convertToMySQLDate($_POST['date_naissance'] ?? '');
        $province_naissance = trim($_POST['province_naissance'] ?? '') ?: null;
        $pays_naissance = trim($_POST['pays_naissance'] ?? '') ?: null;
        $telephone = trim($_POST['telephone'] ?? '') ?: null;
        $email = trim($_POST['email'] ?? '') ?: null;
        $residence = trim($_POST['residence'] ?? '') ?: null;
        $profession = trim($_POST['profession'] ?? '') ?: null;
        $etat_civil = $_POST['etat_civil'] ?? null;
        $conjoint_nom_prenom = trim($_POST['conjoint_nom_prenom'] ?? '') ?: null;
        $date_nouvelle_naissance = convertToMySQLDate($_POST['date_nouvelle_naissance'] ?? '');
        $eglise_nouvelle_naissance = trim($_POST['eglise_nouvelle_naissance'] ?? '') ?: null;
        $lieu_nouvelle_naissance = trim($_POST['lieu_nouvelle_naissance'] ?? '') ?: null;
        $formation_id = !empty($_POST['formation_id']) ? (int)$_POST['formation_id'] : null;
        $oikos_id = !empty($_POST['oikos_id']) ? (int)$_POST['oikos_id'] : null;
        $departement = trim($_POST['departement'] ?? '') ?: null;
        $event_date = convertToMySQLDate($_POST['event_date'] ?? '');

        // Validation des champs requis
        if (empty($nom) || empty($prenom) || empty($date_naissance) || empty($etat_civil)) {
            throw new Exception("Les champs Nom, Prénom, Date de Naissance et État Civil sont requis.");
        }

        // Validation de departement
        if ($departement !== null && !in_array($departement, $valid_departements)) {
            throw new Exception("Valeur invalide pour Département : '$departement'. Valeurs autorisées : " . implode(', ', $valid_departements));
        }

        // Gestion du fichier PDF
        $fiche_membre = null;
        if (isset($_FILES['fiche_membre']) && $_FILES['fiche_membre']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_writable($upload_dir)) {
                throw new Exception("Le dossier uploads/ n'est pas accessible en écriture.");
            }
            $fiche_membre = $upload_dir . "{$id}_{$nom}.pdf";
            if (!move_uploaded_file($_FILES['fiche_membre']['tmp_name'], $fiche_membre)) {
                throw new Exception("Échec du téléchargement du fichier PDF.");
            }
        }

        // Insertion dans la table members
        $stmt = $db->prepare("
            INSERT INTO members (
                id, nom, prenom, date_naissance, province_naissance, pays_naissance, 
                telephone, email, residence, profession, etat_civil, conjoint_nom_prenom, 
                date_nouvelle_naissance, eglise_nouvelle_naissance, lieu_nouvelle_naissance, 
                formation_id, oikos_id, departement, fiche_membre
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id, $nom, $prenom, $date_naissance, $province_naissance, $pays_naissance,
            $telephone, $email, $residence, $profession, $etat_civil, $conjoint_nom_prenom,
            $date_nouvelle_naissance, $eglise_nouvelle_naissance, $lieu_nouvelle_naissance,
            $formation_id, $oikos_id, $departement, $fiche_membre
        ]);

        // Gestion des événements
        if (!empty($_POST['event_type']) && !empty($event_date)) {
            $stmt = $db->prepare("INSERT INTO events (member_id, type, date_evenement) VALUES (?, ?, ?)");
            $stmt->execute([$id, $_POST['event_type'], $event_date]);
        }

        // Journalisation
        logAction($_SESSION['user_id'], "Ajout membre: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        $success = "Membre ajouté avec succès (ID: $id).";
    } catch (Exception $e) {
        $error = "Erreur lors de l'ajout du membre : " . $e->getMessage();
        logAction($_SESSION['user_id'], "Erreur ajout membre: " . $e->getMessage(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
    }
}

// Gestion de la suppression (admin seulement)
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    try {
        $id = $_GET['delete'];
        $stmt = $db->prepare("DELETE FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $db->prepare("DELETE FROM events WHERE member_id = ?");
        $stmt->execute([$id]);
        logAction($_SESSION['user_id'], "Suppression membre: $id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], php_uname('s'));
        $success = "Membre supprimé avec succès (ID: $id).";
    } catch (Exception $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
    header('Location: members.php');
    exit;
}

// Récupérer les membres
$members = $db->query("
    SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom 
    FROM members m 
    LEFT JOIN formations f ON m.formation_id = f.id 
    LEFT JOIN oikos o ON m.oikos_id = o.id
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails d'un membre pour la vue
$view_member = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("
        SELECT m.*, f.nom AS formation_nom, f.promotion, o.nom AS oikos_nom, e.type AS event_type, e.date_evenement 
        FROM members m 
        LEFT JOIN formations f ON m.formation_id = f.id 
        LEFT JOIN oikos o ON m.oikos_id = o.id 
        LEFT JOIN events e ON m.id = e.member_id 
        WHERE m.id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $view_member = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Gestion des Membres</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <style>
        /* Custom styles for Bootstrap Datepicker */
        .datepicker {
            font-family: 'Arial', sans-serif;
            border-radius: 8px;
            border: 2px solid #007bff;
            padding: 10px;
            background-color: #f8f9fa;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .datepicker:focus {
            border-color: #0056b3;
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
            outline: none;
        }
        .bootstrap-datepicker .datepicker-days table {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .bootstrap-datepicker .datepicker-days th,
        .bootstrap-datepicker .datepicker-days td {
            padding: 10px;
            text-align: center;
            transition: background-color 0.2s ease;
        }
        .bootstrap-datepicker .day {
            color: #333;
            font-weight: 500;
        }
        .bootstrap-datepicker .day:hover {
            background-color: #007bff;
            color: #fff;
            border-radius: 50%;
        }
        .bootstrap-datepicker .today {
            background-color: #e9ecef;
            border-radius: 50%;
        }
        .bootstrap-datepicker .selected {
            background-color: #007bff !important;
            color: #fff !important;
            border-radius: 50%;
        }
        .bootstrap-datepicker .month,
        .bootstrap-datepicker .year {
            color: #333;
            padding: 8px;
        }
        .bootstrap-datepicker .month:hover,
        .bootstrap-datepicker .year:hover {
            background-color: #007bff;
            color: #fff;
            border-radius: 4px;
        }
        .bootstrap-datepicker .prev,
        .bootstrap-datepicker .next {
            background-color: #007bff;
            color: #fff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            transition: background-color 0.2s ease;
        }
        .bootstrap-datepicker .prev:hover,
        .bootstrap-datepicker .next:hover {
            background-color: #0056b3;
        }
        .bootstrap-datepicker .dow {
            color: #007bff;
            font-weight: bold;
            text-transform: uppercase;
        }
        .bootstrap-datepicker .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="text-center mb-4">
                <img src="assets/images/logo.jpeg" alt="OCC Logo" class="img-fluid" style="width: 100px; height: auto;">
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
                <li class="nav-item"><a href="auth.php?logout=1" class="nav-link">Déconnexion</a></li>
            </ul>
        </div>

        <!-- Contenu principal -->
        <div class="content p-4">
            <h2 class="mb-4">Gestion des Membres</h2>

            <!-- Afficher les messages d'erreur ou de succès -->
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'diacre'): ?>
                <button class="btn btn-primary mb-4" data-toggle="modal" data-target="#addMemberModal">Ajouter un Membre</button>
            <?php endif; ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Date de Naissance</th>
                        <th>Département</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['id']); ?></td>
                            <td><?php echo htmlspecialchars($member['nom']); ?></td>
                            <td><?php echo htmlspecialchars($member['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($member['date_naissance']); ?></td>
                            <td><?php echo htmlspecialchars($member['departement'] ?? '-'); ?></td>
                            <td>
                                <a href="members.php?view=<?php echo $member['id']; ?>" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewMemberModal">Voir</a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="members.php?delete=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce membre ?');">Supprimer</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal pour ajouter un membre -->
            <div class="modal fade" id="addMemberModal" tabindex="-1" role="dialog" aria-labelledby="addMemberModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addMemberModalLabel">Ajouter un Membre</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data">
                                <h6>Section 1: Informations Personnelles</h6>
                                <div class="row">
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="nom">Nom</label>
                                        <input type="text" class="form-control" id="nom" name="nom" required>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="prenom">Prénom</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="date_naissance">Date de Naissance</label>
                                        <input type="text" class="form-control datepicker" id="date_naissance" name="date_naissance" required placeholder="JJ/MM/AAAA">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="province_naissance">Province de Naissance</label>
                                        <input type="text" class="form-control" id="province_naissance" name="province_naissance">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="pays_naissance">Pays de Naissance</label>
                                        <input type="text" class="form-control" id="pays_naissance" name="pays_naissance">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="telephone">Téléphone</label>
                                        <input type="text" class="form-control" id="telephone" name="telephone">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="email">E-mail</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="residence">Résidence</label>
                                        <input type="text" class="form-control" id="residence" name="residence">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="profession">Profession</label>
                                        <input type="text" class="form-control" id="profession" name="profession">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="etat_civil">État Civil</label>
                                        <select class="form-control" id="etat_civil" name="etat_civil" required>
                                            <option value="">Sélectionner</option>
                                            <option value="Célibataire">Célibataire</option>
                                            <option value="Marié(e)">Marié(e)</option>
                                            <option value="Veuf(ve)">Veuf(ve)</option>
                                            <option value="Divorcé(e)">Divorcé(e)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group mb-3" id="conjoint_field" style="display: none;">
                                        <label for="conjoint_nom_prenom">Nom et Prénom du Conjoint</label>
                                        <input type="text" class="form-control" id="conjoint_nom_prenom" name="conjoint_nom_prenom">
                                    </div>
                                </div>

                                <h6>Section 2: Nouvelle Naissance</h6>
                                <div class="row">
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="date_nouvelle_naissance">Date de Nouvelle Naissance</label>
                                        <input type="text" class="form-control datepicker" id="date_nouvelle_naissance" name="date_nouvelle_naissance" placeholder="JJ/MM/AAAA">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="eglise_nouvelle_naissance">Église de Nouvelle Naissance</label>
                                        <input type="text" class="form-control" id="eglise_nouvelle_naissance" name="eglise_nouvelle_naissance">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="lieu_nouvelle_naissance">Lieu de Nouvelle Naissance</label>
                                        <input type="text" class="form-control" id="lieu_nouvelle_naissance" name="lieu_nouvelle_naissance">
                                    </div>
                                </div>

                                <h6>Section 3: Informations Complémentaires</h6>
                                <div class="row">
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="formation_id">Formation</label>
                                        <select class="form-control" id="formation_id" name="formation_id">
                                            <option value="">Sélectionner</option>
                                            <?php foreach ($formations as $formation): ?>
                                                <option value="<?php echo $formation['id']; ?>">
                                                    <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="oikos_id">Oikos</label>
                                        <select class="form-control" id="oikos_id" name="oikos_id">
                                            <option value="">Sélectionner</option>
                                            <?php foreach ($oikos as $oiko): ?>
                                                <option value="<?php echo $oiko['id']; ?>">
                                                    <?php echo htmlspecialchars($oiko['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="departement">Département</label>
                                        <select class="form-control" id="departement" name="departement">
                                            <option value="">Sélectionner</option>
                                            <option value="Media">Media</option>
                                            <option value="Comptabilité">Comptabilité</option>
                                            <option value="Sécurité">Sécurité</option>
                                            <option value="Chorale">Chorale</option>
                                            <option value="SundaySchool">SundaySchool</option>
                                            <option value="Protocole">Protocole</option>
                                            <option value="Pastorat">Pastorat</option>
                                            <option value="Diaconat">Diaconat</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="fiche_membre">Fiche de Membre (PDF)</label>
                                        <input type="file" class="form-control" id="fiche_membre" name="fiche_membre" accept=".pdf">
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="event_type">Événement</label>
                                        <select class="form-control" id="event_type" name="event_type">
                                            <option value="">Aucun</option>
                                            <option value="Baptême">Baptême</option>
                                            <option value="Mariage">Mariage</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group mb-3">
                                        <label for="event_date">Date de l'Événement</label>
                                        <input type="text" class="form-control datepicker" id="event_date" name="event_date" placeholder="JJ/MM/AAAA">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Ajouter</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal pour voir un membre -->
            <?php if ($view_member): ?>
                <div class="modal fade" id="viewMemberModal" tabindex="-1" role="dialog" aria-labelledby="viewMemberModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="viewMemberModalLabel">Détails du Membre</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <h6>Section 1: Informations Personnelles</h6>
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($view_member['id']); ?></p>
                                <p><strong>Nom:</strong> <?php echo htmlspecialchars($view_member['nom']); ?></p>
                                <p><strong>Prénom:</strong> <?php echo htmlspecialchars($view_member['prenom']); ?></p>
                                <p><strong>Date de Naissance:</strong> <?php echo htmlspecialchars($view_member['date_naissance']); ?></p>
                                <p><strong>Province de Naissance:</strong> <?php echo htmlspecialchars($view_member['province_naissance'] ?? '-'); ?></p>
                                <p><strong>Pays de Naissance:</strong> <?php echo htmlspecialchars($view_member['pays_naissance'] ?? '-'); ?></p>
                                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($view_member['telephone'] ?? '-'); ?></p>
                                <p><strong>E-mail:</strong> <?php echo htmlspecialchars($view_member['email'] ?? '-'); ?></p>
                                <p><strong>Résidence:</strong> <?php echo htmlspecialchars($view_member['residence'] ?? '-'); ?></p>
                                <p><strong>Profession:</strong> <?php echo htmlspecialchars($view_member['profession'] ?? '-'); ?></p>
                                <p><strong>État Civil:</strong> <?php echo htmlspecialchars($view_member['etat_civil']); ?></p>
                                <p><strong>Conjoint:</strong> <?php echo htmlspecialchars($view_member['conjoint_nom_prenom'] ?? '-'); ?></p>

                                <h6>Section 2: Nouvelle Naissance</h6>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($view_member['date_nouvelle_naissance'] ?? '-'); ?></p>
                                <p><strong>Église:</strong> <?php echo htmlspecialchars($view_member['eglise_nouvelle_naissance'] ?? '-'); ?></p>
                                <p><strong>Lieu:</strong> <?php echo htmlspecialchars($view_member['lieu_nouvelle_naissance'] ?? '-'); ?></p>

                                <h6>Section 3: Informations Complémentaires</h6>
                                <p><strong>Formation:</strong> <?php echo htmlspecialchars($view_member['formation_nom'] . ' - ' . $view_member['promotion'] ?? '-'); ?></p>
                                <p><strong>Oikos:</strong> <?php echo htmlspecialchars($view_member['oikos_nom'] ?? '-'); ?></p>
                                <p><strong>Département:</strong> <?php echo htmlspecialchars($view_member['departement'] ?? '-'); ?></p>
                                <p><strong>Fiche Membre:</strong> <?php echo $view_member['fiche_membre'] ? '<a href="' . htmlspecialchars($view_member['fiche_membre']) . '" target="_blank">Voir PDF</a>' : '-'; ?></p>
                                <p><strong>Événement:</strong> <?php echo htmlspecialchars($view_member['event_type'] . ' (' . $view_member['date_evenement'] . ')' ?? '-'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).ready(function() {
                            jQuery('#viewMemberModal').modal('show');
                        });
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- jQuery CDN avec fallback local -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script>
        // Vérifier si jQuery a chargé depuis le CDN, sinon charger local
        if (typeof jQuery == 'undefined') {
            console.error('jQuery CDN failed to load, attempting local fallback');
            document.write('<script src="assets/jquery/jquery.min.js"><\/script>');
        } else {
            console.log('jQuery loaded successfully from CDN');
        }
    </script>
    <script src="assets/bootstrap/js/bootstrap.min.js"></script>
    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.fr.min.js"></script>
    <script>
        // Vérifier que Bootstrap est chargé
        if (typeof $.fn.modal === 'undefined') {
            console.error('Bootstrap JS not loaded or jQuery not initialized properly');
        } else {
            console.log('Bootstrap JS loaded successfully');
        }

        // Vérifier que Bootstrap Datepicker est chargé
        if (typeof $.fn.datepicker === 'undefined') {
            console.error('Bootstrap Datepicker JS not loaded');
        } else {
            console.log('Bootstrap Datepicker JS loaded successfully');
        }

        // Gestion JavaScript
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
            }).on('changeDate', function(e) {
                console.log('Date sélectionnée : ', e.format());
            });

            // Gestion de l'affichage du champ conjoint
            $('#etat_civil').on('change', function() {
                console.log('État civil changé : ', this.value);
                $('#conjoint_field').toggle(this.value === 'Marié(e)');
            });

            // Gestion du changement de département
            $('#departement').on('change', function() {
                console.log('Département sélectionné : ', this.value);
            });

            // Débogage du bouton Ajouter un Membre
            $('button[data-target="#addMemberModal"]').on('click', function() {
                console.log('Bouton Ajouter un Membre cliqué');
                $('#addMemberModal').modal('show');
            });

            // Débogage de la soumission du formulaire
            $('#addMemberModal form').on('submit', function() {
                console.log('Formulaire soumis');
                // Validation client-side des dates
                $('.datepicker').each(function() {
                    var date = $(this).val();
                    if (date && !/^\d{2}\/\d{2}\/\d{4}$/.test(date)) {
                        alert('Veuillez entrer une date valide au format JJ/MM/AAAA pour ' + $(this).attr('id'));
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>