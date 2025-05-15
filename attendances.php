<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$date_presence = date('Y-m-d');
$success_message = '';
$error_message = '';

try {
    $db = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
        $db->beginTransaction();
        try {
            foreach ($_POST['attendance'] as $member_id => $present) {
                $present = intval($present);
                $stmt = $db->prepare("INSERT INTO formation_attendance (member_id, formation_id, session_id, date_presence, present) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE present = ?");
                $stmt->execute([$member_id, $formation_id, $session_id, $date_presence, $present, $present]);
            }
            $db->commit();
            $success_message = "Attendance recorded successfully!";
            
            // Log action
            $user_id = $_SESSION['user_id'];
            $action = "Recorded attendance for formation ID: $formation_id, session ID: $session_id";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $browser = $_SERVER['HTTP_USER_AGENT'];
            $os = php_uname('s');
            logAction($user_id, $action, $ip_address, $browser, $os);
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Error recording attendance: " . $e->getMessage();
        }
    }

    // Fetch formations
    $stmt = $db->query("SELECT id, nom, promotion FROM formations WHERE status IN ('active', 'pending')");
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch sessions for the selected formation
    $sessions = [];
    if ($formation_id) {
        $stmt = $db->prepare("SELECT id, nom, date_session FROM sessions WHERE formation_id = ? AND status = 'planned'");
        $stmt->execute([$formation_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch enrolled members for the selected formation
    $members = [];
    if ($formation_id) {
        $stmt = $db->prepare("SELECT m.id, m.nom, m.prenom 
                              FROM members m 
                              JOIN member_formations mf ON m.id = mf.member_id 
                              WHERE mf.formation_id = ?");
        $stmt->execute([$formation_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch existing attendance for the selected session and date
    $attendance = [];
    if ($formation_id && $session_id) {
        $stmt = $db->prepare("SELECT member_id, present 
                              FROM formation_attendance 
                              WHERE formation_id = ? AND session_id = ? AND date_presence = ?");
        $stmt->execute([$formation_id, $session_id, $date_presence]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $attendance[$row['member_id']] = $row['present'];
        }
    }

    // Fetch attendance history for the selected formation
    $attendance_history = [];
    if ($formation_id) {
        $stmt = $db->prepare("SELECT fa.member_id, m.nom, m.prenom, fa.session_id, s.nom AS session_name, fa.date_presence, fa.present 
                              FROM formation_attendance fa 
                              JOIN members m ON fa.member_id = m.id 
                              JOIN sessions s ON fa.session_id = s.id 
                              WHERE fa.formation_id = ? 
                              ORDER BY fa.date_presence DESC");
        $stmt->execute([$formation_id]);
        $attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
        }
        .table-row:nth-child(even) {
            background-color: #f9fafb;
        }
        .table-row:hover {
            background-color: #e5e7eb;
            transition: background-color 0.2s;
        }
        .btn-primary {
            transition: background-color 0.2s, transform 0.1s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
        }
        .card {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
    </style>
    <script>
        function toggleAllCheckboxes(source) {
            document.querySelectorAll('input[name^="attendance"]').forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar fixed top-0 left-0 w-64 h-full bg-white shadow-lg z-40 md:static md:translate-x-0">
            <div class="p-4 border-b">
                <h2 class="text-xl font-bold text-indigo-600">OCC Dashboard</h2>
            </div>
            <nav class="mt-4">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-50 text-indigo-600' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
                <a href="promotions.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'promotions.php' ? 'bg-indigo-50 text-indigo-600' : ''; ?>">
                    <i class="fas fa-graduation-cap mr-3"></i> Promotions
                </a>
                <a href="attendances.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'attendances.php' ? 'bg-indigo-50 text-indigo-600' : ''; ?>">
                    <i class="fas fa-check-square mr-3"></i> Attendances
                </a>
                <a href="sessions.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'bg-indigo-50 text-indigo-600' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i> Sessions
                </a>
                <a href="logout.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors duration-200">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 ml-0 md:ml-64 transition-all duration-300">
            <!-- Mobile Menu Button -->
            <div class="md:hidden p-4 bg-white shadow">
                <button onclick="toggleSidebar()" class="text-indigo-600 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="container mx-auto p-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Gestion des Présences</h1>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 card">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 card">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Formation Selection -->
                <div class="bg-white p-6 rounded-lg shadow-lg mb-6 card">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Select Formation</h2>
                    <form method="GET">
                        <div class="mb-4">
                            <label for="formation_id" class="block text-sm font-medium text-gray-600">Formation</label>
                            <select name="formation_id" id="formation_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                                <option value="">-- Select Formation --</option>
                                <?php foreach ($formations as $formation): ?>
                                    <option value="<?php echo $formation['id']; ?>" <?php echo $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($formation['nom'] . ' - ' . $formation['promotion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Session Selection -->
                <?php if ($formation_id): ?>
                    <div class="bg-white p-6 rounded-lg shadow-lg mb-6 card">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">Select Session</h2>
                        <form method="GET">
                            <input type="hidden" name="formation_id" value="<?php echo $formation_id; ?>">
                            <div class="mb-4">
                                <label for="session_id" class="block text-sm font-medium text-gray-600">Session</label>
                                <select name="session_id" id="session_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                                    <option value="">-- Select Session --</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['nom'] . ' (' . $session['date_session'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Attendance Form -->
                <?php if ($formation_id && $session_id && $members): ?>
                    <div class="bg-white p-6 rounded-lg shadow-lg mb-6 card">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">Record Attendance</h2>
                        <form method="POST">
                            <input type="hidden" name="save_attendance" value="1">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Present <input type="checkbox" onclick="toggleAllCheckboxes(this)" class="focus:ring-indigo-500">
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($members as $member): ?>
                                            <tr class="table-row">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($member['nom'] . ' ' . $member['prenom']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <input type="checkbox" name="attendance[<?php echo $member['id']; ?>]" value="1" 
                                                           <?php echo isset($attendance[$member['id']]) && $attendance[$member['id']] ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-6">
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 btn-primary">
                                    Save Attendance
                                </button>
                            </div>
                        </form>
                    </div>
                <?php elseif ($formation_id && $session_id): ?>
                    <div class="bg-white p-6 rounded-lg shadow-lg mb-6 card">
                        <p class="text-gray-600">No members enrolled in this formation.</p>
                    </div>
                <?php endif; ?>

                <!-- Attendance History -->
                <?php if ($formation_id && $attendance_history): ?>
                    <div class="bg-white p-6 rounded-lg shadow-lg card">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">Attendance History</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($attendance_history as $record): ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($record['nom'] . ' ' . $record['prenom']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($record['session_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($record['date_presence']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $record['present'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $record['present'] ? 'Yes' : 'No'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>