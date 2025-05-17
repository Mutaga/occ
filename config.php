<?php
function getDBConnection() {
    $host = 'localhost';
    $dbname = 'occ_db';
    $username = 'root';
    $password = '@Mysql-x45#'; // Default XAMPP password; change to '@Mysql-x45#' if correct

    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        error_log("DB Connection Error: " . $e->getMessage());
        die("Erreur de connexion : " . htmlspecialchars($e->getMessage()));
    }
}

function logAction($user_id, $action, $ip_address, $browser, $os) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO logs (user_id, action, ip_address, browser, os) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $ip_address, $browser, $os]);
    } catch (Exception $e) {
        error_log("Log Action Error: " . $e->getMessage());
    }
}

function generateMemberId() {
    try {
        $db = getDBConnection();
        $stmt = $db->query("SELECT id FROM members ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
        $last_id = $stmt->fetchColumn();
        $next_id = $last_id ? (int)$last_id + 1 : 1;
        return str_pad($next_id, 5, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Generate Member ID Error: " . $e->getMessage());
        return str_pad(1, 5, '0', STR_PAD_LEFT); // Fallback
    }
}
?>