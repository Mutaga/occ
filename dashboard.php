<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch basic stats
$formation_count = $conn->query("SELECT COUNT(*) as total FROM formations")->fetch_assoc()['total'];
$member_count = $conn->query("SELECT COUNT(*) as total FROM members")->fetch_assoc()['total'];
$active_formations = $conn->query("SELECT COUNT(*) as total FROM formations WHERE status = 'active'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <!-- Navigation -->
        <nav class="bg-white shadow mb-4 p-4 rounded">
            <a href="promotions.php" class="text-indigo-600 hover:text-indigo-900 mr-4">Promotions</a>
            <a href="attendances.php" class="text-indigo-600 hover:text-indigo-900 mr-4">Attendances</a>
            <a href="sessions.php" class="text-indigo-600 hover:text-indigo-900 mr-4">Sessions</a>
            <a href="logout.php" class="text-red-600 hover:text-red-900">Logout</a>
        </nav>

        <h1 class="text-2xl font-bold mb-4">Dashboard</h1>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white shadow rounded-lg p-4">
                <h2 class="text-lg font-semibold">Total Formations</h2>
                <p class="text-2xl"><?php echo $formation_count; ?></p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <h2 class="text-lg font-semibold">Total Members</h2>
                <p class="text-2xl"><?php echo $member_count; ?></p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <h2 class="text-lg font-semibold">Active Formations</h2>
                <p class="text-2xl"><?php echo $active_formations; ?></p>
            </div>
        </div>

        <!-- Attendance Summary -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-4">
            <h2 class="text-xl font-bold p-4">Attendance Summary</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Formation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promotion</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Attended</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Score (/50)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $result = $conn->query("SELECT a.*, m.nom, m.prenom 
                                            FROM attendance_summary a 
                                            JOIN members m ON a.member_id = m.id");
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['formation_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['promotion']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['days_attended']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['total_days']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($row['attendance_score'], 2); ?></td>
                            </tr>
                        <?php endwhile; 
                    } else { ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No attendance records found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>