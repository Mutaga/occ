<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Récupérer des statistiques simples
$db = getDBConnection();
$total_members = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$total_oikos = $db->query("SELECT COUNT(*) FROM oikos")->fetchColumn();
$total_formations = $db->query("SELECT COUNT(*) FROM formations")->fetchColumn();

// Récupérer les anniversaires à venir (prochains 30 jours)
$today = date('m-d');
$upcoming_birthdays = $db->query("SELECT id, nom, prenom, date_naissance 
    FROM members 
    WHERE DATE_FORMAT(date_naissance, '%m-%d') >= '$today' 
    AND DATE_FORMAT(date_naissance, '%m-%d') <= DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '%m-%d')
    ORDER BY DATE_FORMAT(date_naissance, '%m-%d') 
    LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oasis Christian Center - Tableau de Bord</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="text-center mb-4">
                <img src="assets/images/logo.jpeg" alt="OCC Logo" class="img-fluid" style="width: 100px; height: auto;">
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="index.php" class="nav-link active">Tableau de Bord</a></li>
                <li class="nav-item"><a href="members.php" class="nav-link">Membres</a></li>
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
            <h2 class="mb-4">Tableau de Bord</h2>
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h5>
                            <p class="card-text">Voici un aperçu de l'état actuel de l'Oasis Christian Center.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Membres</h5>
                            <p class="card-text"><?php echo $total_members; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Oikos</h5>
                            <p class="card-text"><?php echo $total_oikos; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Formations</h5>
                            <p class="card-text"><?php echo $total_formations; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Anniversaires à Venir (30 jours)</h5>
                            <?php if (empty($upcoming_birthdays)): ?>
                                <p class="card-text">Aucun anniversaire dans les 30 prochains jours.</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($upcoming_birthdays as $birthday): ?>
                                        <li class="list-group-item">
                                            <?php echo htmlspecialchars($birthday['nom'] . ' ' . $birthday['prenom']); ?> - 
                                            <?php echo date('d M', strtotime($birthday['date_naissance'])); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/jquery/jquery.min.js"></script>
    <script src="assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>