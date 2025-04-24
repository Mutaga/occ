<?php
function getDBConnection() {
    $host = 'localhost';
    $dbname = 'occ_db';
    $username = 'root';
    $password = '@Mysql-x45#'; // À modifier selon votre configuration

    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}

function logAction($user_id, $action, $ip_address, $browser, $os) {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO logs (user_id, action, ip_address, browser, os) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip_address, $browser, $os]);
}

function generateMemberId() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT id FROM members ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
    $last_id = $stmt->fetchColumn();
    if ($last_id) {
        $next_id = (int)$last_id + 1;
    } else {
        $next_id = 1;
    }
    return str_pad($next_id, 5, '0', STR_PAD_LEFT);
}
?>