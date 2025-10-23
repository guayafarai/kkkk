<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

setSecurityHeaders();
startSecureSession();
requireLogin();

// Solo admin puede ver logs
if (!hasPermission('admin')) {
    header('Location: dashboard.php');
    exit();
}

$user = getCurrentUser();
$db = getDB();

// Filtros
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$action_filter = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Construir query
$where = ["DATE(created_at) = ?"];
$params = [$date_filter];

if ($user_filter > 0) {
    $where[] = "user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where[] = "action = ?";
    $params[] = $action_filter;
}

$where_clause = implode(" AND ", $where);

// Obtener logs
$query = "
    SELECT l.*, u.nombre as usuario_nombre, u.username 
    FROM activity_logs l 
    LEFT JOIN usuarios u ON l.user_id = u.id 
    WHERE $where_clause 
    ORDER BY l.created_at DESC 
    LIMIT 100
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener usuarios para filtro
$users_stmt = $db->query("SELECT id, nombre, username FROM usuarios ORDER BY nombre");
$users = $users_stmt->fetchAll();

// Obtener acciones únicas
$actions_stmt = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $actions_stmt->fetchAll();

require_once '../includes/navbar_unified.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Actividad - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php renderNavbar('activity_logs'); ?>
    
    <main class="page-content">
        <div class="p-6">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Logs de Actividad</h2>
            
            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" 
                           class="px-3 py-2 border rounded-lg">
                    
                    <select name="user_id" class="px-3 py-2 border rounded-lg">
                        <option value="0">Todos los usuarios</option>
                        <?php foreach($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['nombre']); ?> (@<?php echo htmlspecialchars($u['username']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="action" class="px-3 py-2 border rounded-lg">
                        <option value="">Todas las acciones</option>
                        <?php foreach($actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $action_filter == $a['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['action']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg">Filtrar</button>
                    <a href="activity_logs.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg">Limpiar</a>
                </form>
            </div>
            
            <!-- Tabla de logs -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha/Hora</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($logs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                            <td class="px-4 py-3 text-sm">
                                <?php echo htmlspecialchars($log['usuario_nombre']); ?>
                                <span class="text-gray-500">(@<?php echo htmlspecialchars($log['username']); ?>)</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($log['description']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>