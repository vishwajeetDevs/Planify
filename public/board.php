<?php
// Start output buffering at the very first line with no whitespace before
if (ob_get_level() == 0) {
    ob_start();
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define constants
define('ROOT_PATH', dirname(__DIR__));

// Include required files
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/skeleton.php';
require_once ROOT_PATH . '/helpers/IdEncrypt.php';

// Check if user is logged in
requireLogin();

// Set default page title
$pageTitle = 'Board - Planify';
$includeDragDrop = true;

// Get board ID (supports both encrypted 'ref' and plain 'id' for backward compatibility)
$boardId = getDecryptedId('ref');

if (!$boardId) {
    showInvalidAccessError('Invalid or unauthorized access to board.');
}

// Get board details BEFORE including header
$stmt = $conn->prepare("
    SELECT b.*, w.name as workspace_name, w.id as workspace_id
    FROM boards b
    INNER JOIN workspaces w ON b.workspace_id = w.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $boardId);
$stmt->execute();
$board = $stmt->get_result()->fetch_assoc();

// Check if current user is board owner (for export feature)
$isBoardOwner = false;
if ($board) {
    // Check if user created the board OR has owner role in board_members
    if ($board['created_by'] == $_SESSION['user_id']) {
        $isBoardOwner = true;
    } else {
        $ownerStmt = $conn->prepare("SELECT role FROM board_members WHERE board_id = ? AND user_id = ? AND role = 'owner'");
        $ownerStmt->bind_param("ii", $boardId, $_SESSION['user_id']);
        $ownerStmt->execute();
        $ownerResult = $ownerStmt->get_result();
        if ($ownerResult->num_rows > 0) {
            $isBoardOwner = true;
        }
        $ownerStmt->close();
    }
}

if (!$board) {
    header('Location: dashboard.php');
    exit;
}

// Check access BEFORE including header
$userAccess = hasAccessToBoard($conn, $_SESSION['user_id'], $boardId);
if (!$userAccess) {
    // Store error message in session for display on dashboard
    $_SESSION['error_message'] = 'You are not a member of this board. Access denied.';
    header('Location: dashboard.php');
    exit;
}

// Include header after all redirects are done
$showSearch = true; // Show search bar on board page
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
/* Completed card styles */
.card-completed {
    opacity: 0.75;
}
.card-completed:hover {
    opacity: 0.9;
}
.card-completed .block.rounded-lg {
    background-color: rgb(243 244 246) !important; /* gray-100 */
}
.dark .card-completed .block.rounded-lg {
    background-color: rgba(55, 65, 81, 0.5) !important; /* gray-700/50 */
}
</style>
<?php
$canEdit = in_array($userAccess['role'], ['owner', 'member']);

// Get lists with cards count and created_at
$stmt = $conn->prepare("
    SELECT l.id, l.title, l.position, l.created_at, l.updated_at,
           (SELECT COUNT(*) FROM cards WHERE list_id = l.id) as card_count
    FROM lists l
    WHERE l.board_id = ? 
    ORDER BY l.position ASC
");
$stmt->bind_param("i", $boardId);
$stmt->execute();
$lists = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all cards for the board
$stmt = $conn->prepare("
    SELECT c.*, u.name as created_by_name,
           (SELECT COUNT(*) FROM checklist_items ci 
            INNER JOIN checklists ch ON ci.checklist_id = ch.id 
            WHERE ch.card_id = c.id) as checklist_total,
           (SELECT COUNT(*) FROM checklist_items ci 
            INNER JOIN checklists ch ON ci.checklist_id = ch.id 
            WHERE ch.card_id = c.id AND ci.is_completed = 1) as checklist_completed,
           (SELECT COUNT(*) FROM comments WHERE card_id = c.id) as comment_count,
           (SELECT COUNT(*) FROM attachments WHERE card_id = c.id) as attachment_count
    FROM cards c
    INNER JOIN users u ON c.created_by = u.id
    INNER JOIN lists l ON c.list_id = l.id
    WHERE l.board_id = ?
    ORDER BY c.position ASC
");
$stmt->bind_param("i", $boardId);
$stmt->execute();
$allCards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group cards by list
$cardsByList = [];
foreach ($allCards as $card) {
    $cardsByList[$card['list_id']][] = $card;
}

// Get mentioned users for all cards (for displaying on task cards)
$cardMentions = [];
$mentionStmt = $conn->prepare("
    SELECT 
        cm.card_id,
        u.id as user_id,
        u.name,
        u.avatar
    FROM comment_mentions cm
    INNER JOIN users u ON cm.mentioned_user_id = u.id
    INNER JOIN cards c ON cm.card_id = c.id
    INNER JOIN lists l ON c.list_id = l.id
    WHERE l.board_id = ?
    GROUP BY cm.card_id, u.id
    ORDER BY cm.card_id, cm.created_at DESC
");
if ($mentionStmt) {
    $mentionStmt->bind_param("i", $boardId);
    $mentionStmt->execute();
    $mentionResult = $mentionStmt->get_result();
    while ($row = $mentionResult->fetch_assoc()) {
        $cardId = (int)$row['card_id'];
        if (!isset($cardMentions[$cardId])) {
            $cardMentions[$cardId] = [];
        }
        // Avoid duplicates
        $exists = false;
        foreach ($cardMentions[$cardId] as $m) {
            if ($m['user_id'] === (int)$row['user_id']) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $cardMentions[$cardId][] = [
                'user_id' => (int)$row['user_id'],
                'name' => $row['name'],
                'avatar' => $row['avatar']
            ];
        }
    }
}

// Get board labels
$stmt = $conn->prepare("SELECT * FROM labels WHERE board_id = ? ORDER BY name");
$stmt->bind_param("i", $boardId);
$stmt->execute();
$labels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get card labels (which labels are assigned to which cards)
$cardLabels = [];
$cardLabelsStmt = $conn->prepare("
    SELECT cl.card_id, cl.label_id, l.name, l.color
    FROM card_labels cl
    INNER JOIN labels l ON cl.label_id = l.id
    INNER JOIN cards c ON cl.card_id = c.id
    INNER JOIN lists li ON c.list_id = li.id
    WHERE li.board_id = ?
");
if ($cardLabelsStmt) {
    $cardLabelsStmt->bind_param("i", $boardId);
    $cardLabelsStmt->execute();
    $result = $cardLabelsStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cardId = (int)$row['card_id'];
        if (!isset($cardLabels[$cardId])) {
            $cardLabels[$cardId] = [];
        }
        $cardLabels[$cardId][] = [
            'id' => (int)$row['label_id'],
            'name' => $row['name'],
            'color' => $row['color']
        ];
    }
}

// Get card assignees (which members are assigned to which cards)
$cardAssignees = [];
$cardAssigneesStmt = $conn->prepare("
    SELECT ca.card_id, u.id as user_id, u.name, u.avatar
    FROM card_assignees ca
    INNER JOIN users u ON ca.user_id = u.id
    INNER JOIN cards c ON ca.card_id = c.id
    INNER JOIN lists li ON c.list_id = li.id
    WHERE li.board_id = ?
");
if ($cardAssigneesStmt) {
    $cardAssigneesStmt->bind_param("i", $boardId);
    $cardAssigneesStmt->execute();
    $result = $cardAssigneesStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cardId = (int)$row['card_id'];
        if (!isset($cardAssignees[$cardId])) {
            $cardAssignees[$cardId] = [];
        }
        $cardAssignees[$cardId][] = [
            'user_id' => (int)$row['user_id'],
            'name' => $row['name'],
            'avatar' => $row['avatar']
        ];
    }
}

// Get activities
$stmt = $conn->prepare("
    SELECT a.*, u.name as user_name
    FROM activities a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.board_id = ?
    ORDER BY a.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $boardId);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get pending join requests count (only for board owners)
$pendingRequestsCount = 0;
$isOwner = ($userAccess['role'] === 'owner');
if ($isOwner) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM join_requests jr
        WHERE jr.board_id = ? AND jr.status = 'pending'
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $pendingRequestsCount = $result['count'] ?? 0;
}

// Get board members with their details
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.avatar, bm.role, bm.created_at as joined_at
    FROM board_members bm
    INNER JOIN users u ON bm.user_id = u.id
    WHERE bm.board_id = ?
    ORDER BY 
        CASE bm.role 
            WHEN 'owner' THEN 1 
            WHEN 'member' THEN 2 
            WHEN 'commenter' THEN 3
            WHEN 'viewer' THEN 4 
        END,
        u.name ASC
");
$stmt->bind_param("i", $boardId);
$stmt->execute();
$boardMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Separate owner from other members
$boardOwner = null;
$otherMembers = [];
foreach ($boardMembers as $member) {
    if ($member['role'] === 'owner') {
        $boardOwner = $member;
    } else {
        $otherMembers[] = $member;
    }
}
?>

<div class="bg-slate-50/80 dark:bg-gray-900/95">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
        <!-- Board Header -->
        <div class="mb-6 border-b-2 border-dashed border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 backdrop-blur-xl transition-all duration-300">
            <div class="p-5 sm:p-6">
                <!-- Breadcrumb -->
                <div class="flex items-center text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-3">
                    <a href="dashboard.php" class="font-medium text-primary hover:underline">Dashboard</a>
                    <span class="mx-2 text-gray-400">/</span>
                    <a href="<?php echo encryptedUrl('workspace.php', $board['workspace_id']); ?>" class="font-medium text-primary hover:underline">
                        <?php echo e($board['workspace_name']); ?>
                    </a>
                    <span class="mx-2 text-gray-400">/</span>
                    <span class="text-gray-700 dark:text-gray-300 font-medium"><?php echo e($board['name']); ?></span>
                </div>

                <!-- Title + Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                            <?php echo e($board['name']); ?>
                        </h1>
                        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary-light">
                            <?php echo ($userAccess['role'] === 'owner') ? 'Admin' : ucfirst($userAccess['role']); ?>
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php if ($canEdit): ?>
                        <!-- Import Plans Button -->
                        <button 
                            onclick="showImportModal()"
                            class="inline-flex items-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-gray-200/80 dark:border-gray-700 bg-white/70 dark:bg-gray-900/70 text-gray-700 dark:text-gray-200 hover:bg-gray-100/90 dark:hover:bg-gray-800 hover:shadow-sm transition-all duration-200"
                            title="Import tasks from file"
                        >
                            <i class="fas fa-file-import mr-2 text-xs text-primary"></i>
                            Import
                        </button>
                        <?php endif; ?>

                        <?php if ($isBoardOwner): ?>
                        <!-- Export Button (Board Owner Only) -->
                        <button 
                            onclick="showExportModal()"
                            class="inline-flex items-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-gray-200/80 dark:border-gray-700 bg-white/70 dark:bg-gray-900/70 text-gray-700 dark:text-gray-200 hover:bg-gray-100/90 dark:hover:bg-gray-800 hover:shadow-sm transition-all duration-200"
                            title="Export board tasks"
                        >
                            <i class="fas fa-file-export mr-2 text-xs text-primary"></i>
                            Export
                        </button>
                        <?php endif; ?>

                        <!-- Activity Button -->
                        <button 
                            onclick="showActivityModal()"
                            class="inline-flex items-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-gray-200/80 dark:border-gray-700 bg-white/70 dark:bg-gray-900/70 text-gray-700 dark:text-gray-200 hover:bg-gray-100/90 dark:hover:bg-gray-800 hover:shadow-sm transition-all duration-200"
                        >
                            <i class="fas fa-history mr-2 text-xs"></i>
                            Activity
                        </button>

                        <?php if ($canEdit): ?>
                        <!-- Share Button -->
                        <button 
                            id="shareButton"
                            type="button"
                            class="relative inline-flex items-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-gray-200/80 dark:border-gray-700 bg-white/70 dark:bg-gray-900/70 text-gray-700 dark:text-gray-200 hover:bg-gray-100/90 dark:hover:bg-gray-800 hover:shadow-sm transition-all duration-200"
                        >
                            <i class="fas fa-share-alt mr-2 text-xs"></i>
                            Share
                            <?php if ($pendingRequestsCount > 0): ?>
                            <span id="pendingRequestsBadge" class="absolute -top-2 -right-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[20px] h-5 animate-pulse">
                                <?php echo $pendingRequestsCount; ?>
                            </span>
                            <?php endif; ?>
                        </button>

                        <!-- Add List Button -->
                        <button 
                            onclick="showAddListModal()"
                            class="inline-flex items-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary-dark shadow-md shadow-primary/30 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <i class="fas fa-plus mr-2 text-xs"></i>
                            Add List
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Board Members Preview (Avatars) -->
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Board Members</span>
                            <div class="flex -space-x-2">
                                <?php 
                                $displayLimit = 8;
                                $displayedMembers = array_slice($boardMembers, 0, $displayLimit);
                                foreach ($displayedMembers as $member): 
                                    $initials = strtoupper(substr($member['name'], 0, 1));
                                    $isOwnerMember = ($member['role'] === 'owner');
                                    // Check if avatar exists and is not a default placeholder
                                    $hasValidAvatar = !empty($member['avatar']) 
                                        && $member['avatar'] !== 'default-avatar.png' 
                                        && file_exists(ROOT_PATH . '/assets/uploads/avatars/' . $member['avatar']);
                                ?>
                                <div class="relative group/avatar">
                                    <?php if ($hasValidAvatar): ?>
                                    <img 
                                        src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/<?php echo e($member['avatar']); ?>" 
                                        alt="<?php echo e($member['name']); ?>"
                                        class="w-8 h-8 rounded-full border-2 <?php echo $isOwnerMember ? 'border-amber-400' : 'border-white dark:border-gray-800'; ?> object-cover hover:z-10 hover:scale-110 transition-transform cursor-pointer"
                                        onclick="showMembersModal()"
                                    >
                                    <?php else: ?>
                                    <div 
                                        class="w-8 h-8 rounded-full border-2 <?php echo $isOwnerMember ? 'border-amber-400 bg-gradient-to-br from-amber-400 to-orange-500 text-white' : 'border-white dark:border-gray-800 bg-primary text-white'; ?> flex items-center justify-center text-xs font-semibold hover:z-10 hover:scale-110 transition-transform cursor-pointer shadow-sm"
                                        onclick="showMembersModal()"
                                    >
                                        <?php echo $initials; ?>
                                    </div>
                                    <?php endif; ?>
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover/avatar:opacity-100 transition-opacity pointer-events-none z-20">
                                        <?php echo e($member['name']); ?>
                                        <?php if ($isOwnerMember): ?>
                                        <span class="text-amber-400">(Admin)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($boardMembers) > $displayLimit): ?>
                                <div 
                                    class="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xs font-semibold text-gray-600 dark:text-gray-300 hover:z-10 hover:scale-110 transition-transform cursor-pointer"
                                    onclick="showMembersModal()"
                                >
                                    +<?php echo count($boardMembers) - $displayLimit; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button 
                            onclick="showMembersModal()"
                            class="text-xs text-primary hover:text-primary-dark font-medium transition-colors"
                        >
                            View all
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lists + Cards -->
        <div class="flex items-start gap-4 pb-6 px-1 w-full overflow-x-auto custom-scrollbar mb-20 sm:mb-24 md:mb-28 lg:mb-28">
            <?php foreach ($lists as $list): ?>
                <div class="min-w-[280px] max-w-[320px] flex-shrink-0 group/list" data-list-id="<?php echo $list['id']; ?>">
                    <div class="flex flex-col bg-white/90 dark:bg-gray-900/80 backdrop-blur-lg rounded-md border-2 border-dashed border-gray-200 dark:border-gray-700 group-hover/list:border-primary dark:group-hover/list:border-primary transition-all duration-200">
                        <!-- List Header -->
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between group">
                            <h3 class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">
                                <?php echo e($list['title']); ?>
                            </h3>
                            <?php if ($canEdit): ?>
                            <div class="relative">
                                <button 
                                    type="button" 
                                    class="p-1 -mr-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    onclick="event.stopPropagation(); toggleListMenu('list-menu-<?php echo $list['id']; ?>')"
                                >
                                    <i class="fas fa-ellipsis-h w-4 h-4"></i>
                                </button>
                                
                                <!-- Dropdown menu -->
                                <div 
                                    id="list-menu-<?php echo $list['id']; ?>" 
                                    class="hidden absolute right-0 mt-1 w-44 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-50 border border-gray-200 dark:border-gray-700"
                                >
                                    <a 
                                        href="#" 
                                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        onclick="event.preventDefault(); showListDetailsModal(<?php echo htmlspecialchars(json_encode([
                                            'id' => $list['id'],
                                            'title' => $list['title'],
                                            'card_count' => $list['card_count'],
                                            'created_at' => $list['created_at'],
                                            'updated_at' => $list['updated_at'],
                                            'position' => $list['position']
                                        ]), ENT_QUOTES, 'UTF-8'); ?>)"
                                    >
                                        <i class="fas fa-info-circle mr-2"></i> Details
                                    </a>
                                    <a 
                                        href="#" 
                                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        onclick="event.preventDefault(); showEditListModal(<?php echo $list['id']; ?>, '<?php echo addslashes($list['title']); ?>')"
                                    >
                                        <i class="far fa-edit mr-2"></i> Edit List
                                    </a>
                                    <a 
                                        href="#" 
                                        class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-gray-700"
                                        onclick="event.preventDefault(); deleteList(<?php echo $list['id']; ?>)"
                                    >
                                        <i class="far fa-trash-alt mr-2"></i> Delete List
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Cards Container -->
                        <div class="p-3 space-y-3 overflow-y-auto max-h-[calc(100vh-200px)] custom-scrollbar" style="min-height: 40px;" 
                             data-list-id="<?php echo $list['id']; ?>" 
                             id="list-<?php echo $list['id']; ?>">
                            <?php if (isset($cardsByList[$list['id']])): ?>
                                <?php foreach ($cardsByList[$list['id']] as $card): ?>
                                    <?php $isCompleted = !empty($card['is_completed']); ?>
                                    <?php 
                                    // Calculate priority based on due date
                                    $calculatedPriority = '';
                                    $priorityBarColor = '';
                                    
                                    if (!empty($card['due_date'])) {
                                        $dueDate = new DateTime($card['due_date']);
                                        $today = new DateTime();
                                        $today->setTime(0, 0, 0);
                                        $dueDate->setTime(0, 0, 0);
                                        
                                        $diff = $today->diff($dueDate);
                                        $daysUntilDue = (int)$diff->format('%r%a'); // Negative if overdue
                                        
                                        if ($daysUntilDue < 0) {
                                            // Overdue - highest priority
                                            $calculatedPriority = 'overdue';
                                            $priorityBarColor = 'bg-red-600';
                                        } elseif ($daysUntilDue <= 2) {
                                            // Due within 2 days - High priority
                                            $calculatedPriority = 'high';
                                            $priorityBarColor = 'bg-red-500';
                                        } elseif ($daysUntilDue <= 7) {
                                            // Due within a week - Medium priority
                                            $calculatedPriority = 'medium';
                                            $priorityBarColor = 'bg-yellow-500';
                                        } else {
                                            // Due more than a week away - Low priority
                                            $calculatedPriority = 'low';
                                            $priorityBarColor = 'bg-green-500';
                                        }
                                    }
                                    ?>
                                    <div class="group relative card-draggable cursor-grab active:cursor-grabbing <?php echo $isCompleted ? 'card-completed' : ''; ?>" 
                                         data-card-id="<?php echo $card['id']; ?>"
                                         id="card-<?php echo $card['id']; ?>"
                                         onclick="if (!window.isDragging) window.showCardDetails(<?php echo $card['id']; ?>);">
                                        <div class="block w-full rounded-lg <?php echo $isCompleted ? 'bg-gray-100 dark:bg-gray-700/50 border-gray-300 dark:border-gray-600' : 'bg-white dark:bg-gray-800 border-gray-200/80 dark:border-gray-700 hover:border-primary/40 dark:hover:border-primary/50'; ?> border hover:shadow-md hover:shadow-primary/10 transition-all duration-150 overflow-hidden">
                                            <?php if ($priorityBarColor): ?>
                                            <!-- Priority Bar (thin top border) -->
                                            <div class="h-[2px] w-full <?php echo $priorityBarColor; ?>"></div>
                                            <?php endif; ?>
                                            <div class="p-2.5 sm:p-3">
                                                <!-- Title with Priority Badge -->
                                                <div class="flex justify-between items-start gap-2 mb-1.5">
                                                    <h3 class="font-medium text-sm sm:text-base leading-tight group-hover:text-primary <?php echo $isCompleted ? 'text-gray-500 dark:text-gray-400 line-through' : 'text-gray-900 dark:text-gray-100'; ?>">
                                                        <?php echo e($card['title']); ?>
                                                    </h3>
                                                    <?php if (!empty($calculatedPriority)): ?>
                                                    <span class="card-priority-badge flex-shrink-0 px-1.5 py-0.5 text-[10px] font-semibold rounded transition-opacity duration-200 <?php 
                                                        echo match($calculatedPriority) {
                                                            'overdue' => 'bg-red-200 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                                                            'high' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                                            'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
                                                            'low' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                                            default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
                                                        };
                                                    ?>" title="<?php 
                                                        if (!empty($card['due_date'])) {
                                                            $dueDate = new DateTime($card['due_date']);
                                                            $today = new DateTime();
                                                            $today->setTime(0, 0, 0);
                                                            $dueDate->setTime(0, 0, 0);
                                                            $diff = $today->diff($dueDate);
                                                            $days = (int)$diff->format('%r%a');
                                                            if ($days < 0) {
                                                                echo 'Overdue by ' . abs($days) . ' day(s)';
                                                            } elseif ($days == 0) {
                                                                echo 'Due today';
                                                            } elseif ($days == 1) {
                                                                echo 'Due tomorrow';
                                                            } else {
                                                                echo 'Due in ' . $days . ' days';
                                                            }
                                                        }
                                                    ?>"><?php echo $calculatedPriority === 'overdue' ? 'Overdue' : ucfirst($calculatedPriority); ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Description Preview (truncated) -->
                                                <?php if ($card['description']): ?>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1 line-clamp-1 leading-snug">
                                                        <?php 
                                                        $desc = strip_tags($card['description']);
                                                        echo e(strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc); 
                                                        ?>
                                                    </p>
                                                <?php endif; ?>

                                                <!-- Created By -->
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                                    Created by <?php echo e($card['created_by_name']); ?>
                                                </div>

                                                <!-- Meta row -->
                                                <div class="flex items-center justify-between pt-1.5 mt-1.5 border-t border-gray-100 dark:border-gray-700">
                                                    <!-- Left side: Assigned Members + Mentioned Users + Due date + Checklist -->
                                                    <div class="flex items-center gap-2">
                                                        <?php 
                                                        // Display assigned members (BLUE - on LEFT)
                                                        $assignees = $cardAssignees[$card['id']] ?? [];
                                                        if (!empty($assignees)): 
                                                            $displayAssignees = array_slice($assignees, 0, 3);
                                                            $extraAssigneeCount = count($assignees) - 3;
                                                        ?>
                                                        <div class="flex items-center" id="card-assignees-<?php echo $card['id']; ?>">
                                                            <div class="flex -space-x-1.5" title="Assigned members">
                                                                <?php foreach ($displayAssignees as $assignee): ?>
                                                                    <?php 
                                                                    $hasAvatar = !empty($assignee['avatar']) && $assignee['avatar'] !== 'default-avatar.png';
                                                                    // Get initials (first 2 letters of name)
                                                                    $nameParts = explode(' ', $assignee['name']);
                                                                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                                                                    if (isset($nameParts[1])) {
                                                                        $initials .= strtoupper(substr($nameParts[1], 0, 1));
                                                                    } else {
                                                                        $initials .= strtoupper(substr($nameParts[0], 1, 1));
                                                                    }
                                                                    ?>
                                                                    <?php if ($hasAvatar): ?>
                                                                        <img 
                                                                            class="h-5 w-5 rounded-full border border-white dark:border-gray-800 shadow-sm object-cover"
                                                                            src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/<?php echo e($assignee['avatar']); ?>" 
                                                                            alt="<?php echo e($assignee['name']); ?>"
                                                                            title="<?php echo e($assignee['name']); ?>"
                                                                        >
                                                                    <?php else: ?>
                                                                        <div 
                                                                            class="h-5 w-5 rounded-full border border-white dark:border-gray-800 bg-primary flex items-center justify-center text-white text-[8px] font-bold shadow-sm"
                                                                            title="<?php echo e($assignee['name']); ?>"
                                                                        ><?php echo $initials; ?></div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <?php if ($extraAssigneeCount > 0): ?>
                                                                    <span class="flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-gray-600 text-[8px] font-bold text-gray-600 dark:text-gray-200 border border-white dark:border-gray-800" title="<?php echo $extraAssigneeCount; ?> more">
                                                                        +<?php echo $extraAssigneeCount; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php 
                                                        // Display mentioned users (ORANGE - on RIGHT of assignees)
                                                        $mentions = $cardMentions[$card['id']] ?? [];
                                                        if (!empty($mentions)): 
                                                            $displayMentions = array_slice($mentions, 0, 3);
                                                            $extraCount = count($mentions) - 3;
                                                        ?>
                                                        <div class="flex items-center" id="card-mentions-<?php echo $card['id']; ?>">
                                                            <div class="flex -space-x-1.5" title="Mentioned in comments">
                                                                <?php foreach ($displayMentions as $mention): ?>
                                                                    <?php 
                                                                    $hasAvatar = !empty($mention['avatar']) && $mention['avatar'] !== 'default-avatar.png';
                                                                    // Get initials (first 2 letters of name)
                                                                    $nameParts = explode(' ', $mention['name']);
                                                                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                                                                    if (isset($nameParts[1])) {
                                                                        $initials .= strtoupper(substr($nameParts[1], 0, 1));
                                                                    } else {
                                                                        $initials .= strtoupper(substr($nameParts[0], 1, 1));
                                                                    }
                                                                    ?>
                                                                    <?php if ($hasAvatar): ?>
                                                                        <img 
                                                                            class="h-5 w-5 rounded-full border border-white dark:border-gray-800 shadow-sm object-cover"
                                                                            src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/<?php echo e($mention['avatar']); ?>" 
                                                                            alt="<?php echo e($mention['name']); ?>"
                                                                            title="<?php echo e($mention['name']); ?>"
                                                                        >
                                                                    <?php else: ?>
                                                                        <div 
                                                                            class="h-5 w-5 rounded-full border border-white dark:border-gray-800 bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-[8px] font-bold shadow-sm"
                                                                            title="<?php echo e($mention['name']); ?>"
                                                                        ><?php echo $initials; ?></div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <?php if ($extraCount > 0): ?>
                                                                    <span class="flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-gray-600 text-[8px] font-bold text-gray-600 dark:text-gray-200 border border-white dark:border-gray-800" title="<?php echo $extraCount; ?> more">
                                                                        +<?php echo $extraCount; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if ($card['due_date']): ?>
                                                            <?php 
                                                                $dueDate = new DateTime($card['due_date']);
                                                                $now = new DateTime();
                                                                $now->setTime(0, 0, 0);
                                                                $dueDate->setTime(0, 0, 0);
                                                                $isOverdue = $dueDate < $now;
                                                                $isToday = $dueDate == $now;
                                                            ?>
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium
                                                                <?php 
                                                                    echo $isOverdue 
                                                                        ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/50 dark:text-rose-200' 
                                                                        : ($isToday 
                                                                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/50 dark:text-amber-200'
                                                                            : 'bg-sky-50 text-sky-700 dark:bg-sky-900/50 dark:text-sky-200');
                                                                ?>">
                                                                <i class="far fa-clock mr-0.5 text-[9px]"></i>
                                                                <?php echo $isOverdue ? 'Overdue' : ($isToday ? 'Today' : $dueDate->format('M j')); ?>
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ($card['checklist_total'] > 0): ?>
                                                            <span class="inline-flex items-center text-[10px] text-gray-500 dark:text-gray-300">
                                                                <i class="far fa-check-square mr-0.5 text-[9px]"></i>
                                                                <?php echo $card['checklist_completed']; ?>/<?php echo $card['checklist_total']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Right side: Comment & Attachment counts -->
                                                    <div class="flex items-center gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                                                        <?php if ($card['attachment_count'] > 0): ?>
                                                            <span class="inline-flex items-center gap-0.5">
                                                                <i class="fas fa-paperclip text-[10px]"></i>
                                                                <span><?php echo $card['attachment_count']; ?></span>
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ($card['comment_count'] > 0): ?>
                                                            <span class="inline-flex items-center gap-0.5">
                                                                <i class="far fa-comment text-[10px]"></i>
                                                                <span><?php echo $card['comment_count']; ?></span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Assignees + Labels -->
                                                <?php 
                                                $hasAssignees = !empty($card['assigned_to']);
                                                $thisCardLabels = $cardLabels[$card['id']] ?? [];
                                                if ($hasAssignees || !empty($thisCardLabels)): 
                                                ?>
                                                    <div class="mt-2 flex items-center justify-between gap-2">
                                                        <?php if ($hasAssignees): ?>
                                                            <?php 
                                                                $assignedUsers = json_decode($card['assigned_to'], true);
                                                                if (is_array($assignedUsers) && !empty($assignedUsers)):
                                                            ?>
                                                                <div class="flex -space-x-1">
                                                                    <?php foreach (array_slice($assignedUsers, 0, 3) as $userId): ?>
                                                                        <?php 
                                                                            $userStmt = $conn->prepare("SELECT name, avatar FROM users WHERE id = ?");
                                                                            $userStmt->bind_param("i", $userId);
                                                                            $userStmt->execute();
                                                                            $user = $userStmt->get_result()->fetch_assoc();
                                                                        ?>
                                                                        <?php if ($user): ?>
                                                                            <img 
                                                                                class="h-5 w-5 rounded-full border border-white dark:border-gray-800 shadow-sm"
                                                                                src="<?php echo !empty($user['avatar']) ? '../' . $user['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=4F46E5&color=fff'; ?>" 
                                                                                alt="<?php echo e($user['name']); ?>"
                                                                                title="<?php echo e($user['name']); ?>"
                                                                            >
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                    <?php if (count($assignedUsers) > 3): ?>
                                                                        <span class="flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-gray-700 text-[8px] font-medium text-gray-700 dark:text-gray-200 border border-white dark:border-gray-800">
                                                                            +<?php echo count($assignedUsers) - 3; ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <?php if (!empty($thisCardLabels)): ?>
                                                            <div class="flex flex-wrap gap-1">
                                                                <?php foreach ($thisCardLabels as $label): ?>
                                                                    <span class="inline-block h-2 w-6 rounded-full shadow-sm" style="background-color: <?php echo e($label['color']); ?>" title="<?php echo e($label['name']); ?>"></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($canEdit): ?>
                                            <div class="absolute top-1.5 right-1.5 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200 card-action-btn">
                                                <button 
                                                    type="button"
                                                    class="card-action-btn p-1 bg-white/95 dark:bg-gray-800/95 hover:bg-white dark:hover:bg-gray-700 shadow-sm hover:shadow-md rounded text-gray-600 hover:text-primary dark:text-gray-300 dark:hover:text-primary transition-all duration-200"
                                                    onclick="event.stopPropagation(); showEditCardModal(<?php echo $card['id']; ?>);"
                                                    title="Edit task">
                                                    <i class="fas fa-pen text-[10px]"></i>
                                                </button>
                                                <button 
                                                    type="button"
                                                    class="card-action-btn p-1 bg-white/95 dark:bg-gray-800/95 hover:bg-white dark:hover:bg-gray-700 shadow-sm hover:shadow-md rounded text-gray-600 hover:text-red-500 dark:text-gray-300 hover:dark:text-red-400 transition-all duration-200"
                                                    onclick="event.stopPropagation(); deleteCard(<?php echo $card['id']; ?>);"
                                                    title="Delete task">
                                                    <i class="fas fa-trash text-[10px]"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Add Card Button -->
                        <?php if ($canEdit): ?>
                        <div class="px-3 pb-3 pt-1">
                            <button 
                                onclick="showAddCardModal(<?php echo $list['id']; ?>)"
                                class="w-full py-2 text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300 bg-slate-50/80 dark:bg-gray-800/80 hover:bg-slate-100 dark:hover:bg-gray-700 rounded-md border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center gap-2 transition-all duration-200"
                            >
                                <i class="fas fa-plus text-xs"></i>
                                Add task
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Activity Sidebar -->
        <div id="activitySidebar" class="hidden w-80 bg-white/95 dark:bg-gray-900/95 border-l border-gray-200 dark:border-gray-800 backdrop-blur-xl">
            <div class="p-4 sm:p-5">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity</h2>
                    <button onclick="window.editDescription && window.editDescription()" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <?php foreach ($activities as $activity): ?>
                        <div class="flex space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-semibold shadow-md">
                                    <?php echo strtoupper(substr($activity['user_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    <span class="font-semibold"><?php echo e($activity['user_name']); ?></span>
                                    <?php echo e($activity['description']); ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <?php echo timeAgo($activity['created_at']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Members Modal -->
<div id="membersModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-lg rounded-xl w-full max-w-lg shadow-2xl border border-dashed border-gray-200 dark:border-gray-700">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-users text-primary"></i> Board Members
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(<?php echo count($boardMembers); ?>)</span>
            </h3>
            <button onclick="hideMembersModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="p-6 max-h-[60vh] overflow-y-auto">
            <?php if ($boardOwner): ?>
            <!-- Owner Section -->
            <div class="mb-6">
                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-crown text-amber-500"></i> Board Admin
                </h4>
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-lg p-4 border border-amber-200/50 dark:border-amber-700/30">
                    <div class="flex items-center gap-4">
                        <?php 
                        $ownerInitials = strtoupper(substr($boardOwner['name'], 0, 1));
                        $ownerHasValidAvatar = !empty($boardOwner['avatar']) 
                            && $boardOwner['avatar'] !== 'default-avatar.png' 
                            && file_exists(ROOT_PATH . '/assets/uploads/avatars/' . $boardOwner['avatar']);
                        if ($ownerHasValidAvatar): 
                        ?>
                        <img 
                            src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/<?php echo e($boardOwner['avatar']); ?>" 
                            alt="<?php echo e($boardOwner['name']); ?>"
                            class="w-12 h-12 rounded-full border-2 border-amber-400 object-cover"
                        >
                        <?php else: ?>
                        <div class="w-12 h-12 rounded-full border-2 border-amber-400 bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white text-lg font-semibold shadow-md">
                            <?php echo $ownerInitials; ?>
                        </div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h5 class="font-semibold text-gray-900 dark:text-white truncate"><?php echo e($boardOwner['name']); ?></h5>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">
                                    <i class="fas fa-crown mr-1 text-[10px]"></i> Admin
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate"><?php echo e($boardOwner['email']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Other Members Section -->
            <?php if (!empty($otherMembers)): ?>
            <div>
                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-user-friends text-gray-400"></i> Members (<?php echo count($otherMembers); ?>)
                </h4>
                <div class="space-y-2">
                    <?php foreach ($otherMembers as $member): 
                        $memberInitials = strtoupper(substr($member['name'], 0, 1));
                        $memberHasValidAvatar = !empty($member['avatar']) 
                            && $member['avatar'] !== 'default-avatar.png' 
                            && file_exists(ROOT_PATH . '/assets/uploads/avatars/' . $member['avatar']);
                        $roleColors = [
                            'member' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200',
                            'commenter' => 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200',
                            'viewer' => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
                        ];
                        $roleColor = $roleColors[$member['role']] ?? $roleColors['viewer'];
                        $roleIcons = [
                            'member' => 'fa-user-edit',
                            'commenter' => 'fa-comment',
                            'viewer' => 'fa-eye'
                        ];
                        $roleIcon = $roleIcons[$member['role']] ?? 'fa-user';
                        $isCurrentUser = ($member['id'] == $_SESSION['user_id']);
                    ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group">
                        <?php if ($memberHasValidAvatar): ?>
                        <img 
                            src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/<?php echo e($member['avatar']); ?>" 
                            alt="<?php echo e($member['name']); ?>"
                            class="w-10 h-10 rounded-full border-2 border-white dark:border-gray-600 object-cover"
                        >
                        <?php else: ?>
                        <div class="w-10 h-10 rounded-full border-2 border-white dark:border-gray-600 bg-primary flex items-center justify-center text-white text-sm font-semibold shadow-sm">
                            <?php echo $memberInitials; ?>
                        </div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <h5 class="font-medium text-gray-900 dark:text-white truncate">
                                <?php echo e($member['name']); ?>
                                <?php if ($isCurrentUser): ?>
                                <span class="text-xs text-gray-400 dark:text-gray-500">(You)</span>
                                <?php endif; ?>
                            </h5>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?php echo e($member['email']); ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo $roleColor; ?>">
                                <i class="fas <?php echo $roleIcon; ?> mr-1 text-[10px]"></i>
                                <?php echo ($member['role'] === 'owner') ? 'Admin' : ucfirst($member['role']); ?>
                            </span>
                            <?php if ($isOwner && !$isCurrentUser): ?>
                            <!-- Admin actions: Remove member or Transfer admin role -->
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button 
                                    onclick="showTransferOwnershipConfirm(<?php echo $member['id']; ?>, '<?php echo e(addslashes($member['name'])); ?>')"
                                    class="p-1.5 text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded transition-colors"
                                    title="Transfer admin role to this member"
                                >
                                    <i class="fas fa-crown text-xs"></i>
                                </button>
                                <button 
                                    onclick="showRemoveMemberConfirm(<?php echo $member['id']; ?>, '<?php echo e(addslashes($member['name'])); ?>')"
                                    class="p-1.5 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                                    title="Remove from board"
                                >
                                    <i class="fas fa-user-minus text-xs"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <i class="fas fa-user-friends text-3xl mb-2 opacity-50"></i>
                <p>No other members yet</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <div class="flex items-center justify-between gap-4">
                <?php if (!$isOwner): ?>
                <!-- Leave Board button for non-owners -->
                <button 
                    onclick="showLeaveBoardConfirm()"
                    class="px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                >
                    <i class="fas fa-sign-out-alt mr-2"></i>Leave Board
                </button>
                <?php else: ?>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    As admin, transfer admin role before leaving.
                </p>
                <?php endif; ?>
                <button 
                    onclick="hideMembersModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Board Confirmation Modal -->
<div id="leaveBoardModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-[60] flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30">
                <i class="fas fa-sign-out-alt text-red-600 dark:text-red-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-center text-gray-900 dark:text-white mb-2">Leave Board?</h3>
            <p class="text-sm text-center text-gray-500 dark:text-gray-400 mb-6">
                Are you sure you want to leave this board? You will lose access to all cards and content. 
                You'll need to be re-invited to rejoin.
            </p>
            <div class="flex gap-3">
                <button 
                    onclick="hideLeaveBoardModal()"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    onclick="leaveBoard()"
                    id="leaveBoardBtn"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                >
                    <i class="fas fa-sign-out-alt mr-2"></i>Leave Board
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Remove Member Confirmation Modal -->
<div id="removeMemberModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-[60] flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30">
                <i class="fas fa-user-minus text-red-600 dark:text-red-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-center text-gray-900 dark:text-white mb-2">Remove Member?</h3>
            <p class="text-sm text-center text-gray-500 dark:text-gray-400 mb-6">
                Are you sure you want to remove <strong id="removeMemberName" class="text-gray-900 dark:text-white"></strong> from this board? 
                They will lose access immediately.
            </p>
            <input type="hidden" id="removeMemberUserId" value="">
            <div class="flex gap-3">
                <button 
                    onclick="hideRemoveMemberModal()"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    onclick="removeMember()"
                    id="removeMemberBtn"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                >
                    <i class="fas fa-user-minus mr-2"></i>Remove
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Ownership Confirmation Modal -->
<div id="transferOwnershipModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-[60] flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30">
                <i class="fas fa-crown text-amber-600 dark:text-amber-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-center text-gray-900 dark:text-white mb-2">Transfer Admin Role?</h3>
            <p class="text-sm text-center text-gray-500 dark:text-gray-400 mb-6">
                Are you sure you want to transfer admin role to <strong id="transferOwnerName" class="text-gray-900 dark:text-white"></strong>? 
                You will become a regular member and lose admin privileges.
            </p>
            <input type="hidden" id="transferOwnerUserId" value="">
            <div class="flex gap-3">
                <button 
                    onclick="hideTransferOwnershipModal()"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    onclick="transferOwnership()"
                    id="transferOwnershipBtn"
                    class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-colors"
                >
                    <i class="fas fa-crown mr-2"></i>Transfer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Activity Modal -->
<div id="activityModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <i class="fas fa-history text-primary"></i>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Board Activity</h3>
            </div>
            <button onclick="closeActivityModal()" class="text-gray-500 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 space-y-3 max-h-[70vh] overflow-y-auto bg-gray-50/60 dark:bg-gray-900/60">
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="flex gap-3 items-start bg-white dark:bg-gray-800/80 rounded-lg px-3 py-2 border border-gray-100 dark:border-gray-800 shadow-sm">
                        <div class="h-8 w-8 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center text-primary font-semibold text-sm">
                            <?php echo strtoupper(substr($activity['user_name'] ?? 'S', 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 dark:text-gray-100">
                                <span class="font-semibold"><?php echo e($activity['user_name'] ?? 'System'); ?></span>
                                <?php echo e($activity['description'] ?? $activity['action'] ?? 'updated'); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo timeAgo($activity['created_at']); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-sm text-gray-500 py-6">
                    No activity yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Close on overlay click
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('activityModal');
            if (modal && !modal.classList.contains('hidden')) {
                const dialog = modal.querySelector('.bg-white, .dark\\:bg-gray-900');
                if (e.target === modal) {
                    closeActivityModal();
                }
            }
        });
    </script>
</div>

<!-- Include the card modal component -->
<?php include '../components/card_modal.php'; ?>

<!-- List Details Modal -->
<div id="listDetailsModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-xl overflow-hidden">
        <!-- Header with list name -->
        <div class="px-6 pt-6 pb-4">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">List Details</span>
                <button onclick="hideListDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors -mr-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <h3 id="listDetailsTitle" class="text-xl font-bold text-gray-900 dark:text-white">List Name</h3>
        </div>
        
        <!-- Stats Cards -->
        <div class="px-6 pb-4">
            <div class="grid grid-cols-1 gap-3">
                <!-- Cards Count -->
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Tasks</span>
                        <span id="listDetailCardCount" class="text-lg font-bold text-gray-900 dark:text-white">0</span>
                    </div>
                </div>
                
                <!-- Created -->
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Created</span>
                        <span id="listDetailCreatedAt" class="text-sm font-medium text-gray-900 dark:text-white">-</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
            <button 
                onclick="hideListDetailsModal()"
                class="w-full px-4 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors"
            >
                Close
            </button>
        </div>
    </div>
</div>

<!-- Share Board Modal -->
<div id="shareModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden" x-data="shareModalData()">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-xl overflow-hidden" @click.away="closeShareModal()">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-share-alt text-primary"></i>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Share Board</h3>
                </div>
                <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <!-- View Tabs -->
            <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
                <button 
                    @click="activeTab = 'create'"
                    :class="activeTab === 'create' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    Create Link
                </button>
                <button 
                    @click="activeTab = 'manage'; loadExistingLinks()"
                    :class="activeTab === 'manage' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    Manage Links
                </button>
                <button 
                    @click="activeTab = 'requests'; loadJoinRequests()"
                    :class="activeTab === 'requests' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    Requests
                    <span x-show="pendingRequests > 0" x-text="pendingRequests" class="ml-1 px-1.5 py-0.5 text-xs bg-red-500 text-white rounded-full"></span>
                </button>
            </div>
            
            <!-- Create Link Tab -->
            <div x-show="activeTab === 'create'" x-cloak>
                <!-- Generated Link Display -->
                <div x-show="generatedLink" class="mb-6">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span class="text-sm font-medium text-green-800 dark:text-green-200">Link created successfully!</span>
                        </div>
                        <p class="text-xs text-green-600 dark:text-green-400 mb-3">This link will only be shown once. Copy it now!</p>
                        <div class="flex gap-2">
                            <input type="text" :value="generatedLink" readonly 
                                   class="flex-1 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg">
                            <button @click="copyLink()" 
                                    class="px-3 py-2 text-sm font-medium text-white rounded-lg transition"
                                    style="background-color: #4F46E5;"
                                    onmouseover="this.style.backgroundColor='#4338CA'"
                                    onmouseout="this.style.backgroundColor='#4F46E5'">
                                <i class="fas mr-1" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                    </div>
                    <button @click="generatedLink = ''; resetForm()" class="text-sm text-primary hover:underline">
                        <i class="fas fa-plus mr-1"></i>Create another link
                    </button>
                </div>
                
                <!-- Create Form -->
                <div x-show="!generatedLink">
                    <!-- Access Type -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Access Type</label>
                        <div class="space-y-2">
                            <label class="flex items-start p-3 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                                   :class="accessType === 'join_on_click' && 'border-primary bg-primary/5'">
                                <input type="radio" x-model="accessType" value="join_on_click" class="mt-0.5 text-primary focus:ring-primary">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Join on click</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Users can join instantly after signing in</p>
                                </div>
                            </label>
                            <label class="flex items-start p-3 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                                   :class="accessType === 'view_only' && 'border-primary bg-primary/5'">
                                <input type="radio" x-model="accessType" value="view_only" class="mt-0.5 text-primary focus:ring-primary">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">View only</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Users can view but not modify the board</p>
                                </div>
                            </label>
                            <label class="flex items-start p-3 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                                   :class="accessType === 'invite_only' && 'border-primary bg-primary/5'">
                                <input type="radio" x-model="accessType" value="invite_only" class="mt-0.5 text-primary focus:ring-primary">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Invite only</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">You must approve each join request</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Role on Join -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role when joining</label>
                        <select x-model="roleOnJoin" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="viewer">Viewer — Can view only</option>
                            <option value="commenter">Commenter — Can view and comment</option>
                            <option value="member">Member — Can edit tasks and lists</option>
                        </select>
                    </div>
                    
                    <!-- Expiration -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link expires</label>
                        <select x-model="expiresIn" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="never">Never</option>
                            <option value="1day">In 1 day</option>
                            <option value="7days">In 7 days</option>
                            <option value="30days">In 30 days</option>
                        </select>
                    </div>
                    
                    <!-- Advanced Options Toggle -->
                    <button @click="showAdvanced = !showAdvanced" class="flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white mb-4">
                        <i class="fas fa-cog mr-2"></i>
                        <span>Advanced options</span>
                        <i class="fas fa-chevron-down ml-2 transition-transform" :class="showAdvanced && 'rotate-180'"></i>
                    </button>
                    
                    <!-- Advanced Options -->
                    <div x-show="showAdvanced" x-transition class="space-y-4 mb-5 pl-4 border-l-2 border-gray-200 dark:border-gray-700">
                        <!-- Max Uses -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Maximum uses (optional)</label>
                            <input type="number" x-model="maxUses" min="1" placeholder="Unlimited"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <!-- Domain Restriction -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Restrict to domain (optional)</label>
                            <input type="text" x-model="restrictDomain" placeholder="@company.com"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                            <p class="text-xs text-gray-500 mt-1">Only users with this email domain can join</p>
                        </div>
                        
                        <!-- Single Use -->
                        <label class="flex items-center">
                            <input type="checkbox" x-model="singleUse" class="rounded text-primary focus:ring-primary">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Single use (link becomes invalid after first use)</span>
                        </label>
                    </div>
                    
                    <!-- Info Notice -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-5">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                            <p class="text-xs text-blue-800 dark:text-blue-200">
                                Anyone with this link must sign in to Planify before they can access the board.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Error Message -->
                    <div x-show="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-5">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-2"></i>
                            <p class="text-xs text-red-800 dark:text-red-200" x-text="error"></p>
                        </div>
                    </div>
                    
                    <!-- Generate Button -->
                    <button @click="generateLink()" :disabled="loading"
                            class="w-full px-4 py-2.5 text-sm font-medium text-white rounded-lg shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                            style="background-color: #4F46E5;"
                            onmouseover="if(!this.disabled) this.style.backgroundColor='#4338CA'"
                            onmouseout="this.style.backgroundColor='#4F46E5'">
                        <span x-show="!loading"><i class="fas fa-link mr-2"></i>Generate Link</span>
                        <span x-show="loading" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generating...
                        </span>
                    </button>
                </div>
            </div>
            
            <!-- Manage Links Tab -->
            <div x-show="activeTab === 'manage'" x-cloak>
                <div x-show="loadingLinks" class="text-center py-8">
                    <svg class="animate-spin h-8 w-8 text-primary mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-sm text-gray-500 mt-2">Loading share links...</p>
                </div>
                
                <div x-show="!loadingLinks && existingLinks.length === 0" class="text-center py-8">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                        <i class="fas fa-link text-gray-400"></i>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No share links created yet</p>
                    <button @click="activeTab = 'create'" class="text-sm text-primary hover:underline mt-2">
                        Create your first link
                    </button>
                </div>
                
                <div x-show="!loadingLinks && existingLinks.length > 0" class="space-y-3 max-h-80 overflow-y-auto">
                    <template x-for="link in existingLinks" :key="link.id">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                                          :class="{
                                              'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': link.status === 'active',
                                              'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': link.status === 'revoked',
                                              'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': link.status === 'expired',
                                              'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400': link.status === 'exhausted' || link.status === 'used'
                                          }"
                                          x-text="link.status.charAt(0).toUpperCase() + link.status.slice(1)">
                                    </span>
                                </div>
                                <button x-show="link.status === 'active'" @click="revokeLink(link.id)" 
                                        class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                    <i class="fas fa-ban mr-1"></i>Revoke
                                </button>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <p><span class="font-medium">Type:</span> <span x-text="formatAccessType(link.access_type)"></span></p>
                                <p><span class="font-medium">Role:</span> <span x-text="link.role_on_join.charAt(0).toUpperCase() + link.role_on_join.slice(1)"></span></p>
                                <p><span class="font-medium">Uses:</span> <span x-text="link.uses + (link.max_uses ? '/' + link.max_uses : '')"></span></p>
                                <p x-show="link.expires_at"><span class="font-medium">Expires:</span> <span x-text="formatDate(link.expires_at)"></span></p>
                                <p class="text-xs text-gray-400">Created <span x-text="formatDate(link.created_at)"></span></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- Join Requests Tab -->
            <div x-show="activeTab === 'requests'" x-cloak>
                <div x-show="loadingRequests" class="text-center py-8">
                    <svg class="animate-spin h-8 w-8 text-primary mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-sm text-gray-500 mt-2">Loading requests...</p>
                </div>
                
                <div x-show="!loadingRequests && joinRequests.length === 0" class="text-center py-8">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                        <i class="fas fa-user-clock text-gray-400"></i>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No pending join requests</p>
                </div>
                
                <div x-show="!loadingRequests && joinRequests.length > 0" class="space-y-3 max-h-80 overflow-y-auto">
                    <template x-for="request in joinRequests" :key="request.id">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold">
                                    <span x-text="request.user_name.charAt(0).toUpperCase()"></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="request.user_name"></p>
                                    <p class="text-xs text-gray-500" x-text="request.user_email"></p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mb-3">Requested <span x-text="formatDate(request.created_at)"></span></p>
                            <div class="flex gap-2">
                                <button @click="handleRequest(request.id, 'approve')" 
                                        class="flex-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                                <button @click="handleRequest(request.id, 'reject')" 
                                        class="flex-1 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    <i class="fas fa-times mr-1"></i>Decline
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const boardId = <?php echo $boardId; ?>;
const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;

// Share Modal Data and Functions
function shareModalData() {
    return {
        activeTab: 'create',
        accessType: 'join_on_click',
        roleOnJoin: 'viewer',
        expiresIn: 'never',
        maxUses: '',
        restrictDomain: '',
        singleUse: false,
        showAdvanced: false,
        loading: false,
        error: '',
        generatedLink: '',
        copied: false,
        existingLinks: [],
        loadingLinks: false,
        joinRequests: [],
        loadingRequests: false,
        pendingRequests: 0,
        
        resetForm() {
            this.accessType = 'join_on_click';
            this.roleOnJoin = 'viewer';
            this.expiresIn = 'never';
            this.maxUses = '';
            this.restrictDomain = '';
            this.singleUse = false;
            this.showAdvanced = false;
            this.error = '';
        },
        
        async generateLink() {
            this.loading = true;
            this.error = '';
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            try {
                const response = await fetch(window.BASE_PATH + '/actions/share/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        board_id: boardId,
                        access_type: this.accessType,
                        role_on_join: this.roleOnJoin,
                        expires_in: this.expiresIn,
                        max_uses: this.maxUses ? parseInt(this.maxUses) : null,
                        restrict_domain: this.restrictDomain,
                        single_use: this.singleUse,
                        _token: csrfToken
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.generatedLink = data.share_link.url;
                    showToast('Share link created successfully!', 'success');
                } else {
                    this.error = data.message || 'Failed to create share link';
                }
            } catch (err) {
                console.error('Error creating share link:', err);
                this.error = 'An error occurred. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        
        async copyLink() {
            try {
                await navigator.clipboard.writeText(this.generatedLink);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        },
        
        async loadExistingLinks() {
            this.loadingLinks = true;
            
            try {
                const response = await fetch(`${window.BASE_PATH}/actions/share/get.php?board_id=${boardId}`);
                const data = await response.json();
                
                if (data.success) {
                    this.existingLinks = data.share_links;
                }
            } catch (err) {
                console.error('Error loading share links:', err);
            } finally {
                this.loadingLinks = false;
            }
        },
        
        async revokeLink(linkId) {
            if (!confirm('Are you sure you want to revoke this link? Anyone with this link will no longer be able to join.')) {
                return;
            }
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            try {
                const response = await fetch(window.BASE_PATH + '/actions/share/revoke.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ share_link_id: linkId, _token: csrfToken })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Link revoked successfully', 'success');
                    this.loadExistingLinks();
                } else {
                    showToast(data.message || 'Failed to revoke link', 'error');
                }
            } catch (err) {
                console.error('Error revoking link:', err);
                showToast('An error occurred', 'error');
            }
        },
        
        async loadJoinRequests() {
            this.loadingRequests = true;
            
            try {
                const response = await fetch(`${window.BASE_PATH}/actions/share/requests.php?board_id=${boardId}`);
                const data = await response.json();
                
                if (data.success) {
                    this.joinRequests = data.requests;
                    this.pendingRequests = data.count;
                    // Update the badge on the Share button
                    updatePendingRequestsBadge(data.count);
                }
            } catch (err) {
                console.error('Error loading join requests:', err);
            } finally {
                this.loadingRequests = false;
            }
        },
        
        async handleRequest(requestId, action) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            try {
                const response = await fetch(window.BASE_PATH + '/actions/share/request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ request_id: requestId, action: action, _token: csrfToken })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    this.loadJoinRequests();
                    // Update the badge on the Share button
                    updatePendingRequestsBadge(this.pendingRequests - 1);
                } else {
                    showToast(data.message || 'Failed to process request', 'error');
                }
            } catch (err) {
                console.error('Error handling request:', err);
                showToast('An error occurred', 'error');
            }
        },
        
        formatAccessType(type) {
            const types = {
                'view_only': 'View only',
                'join_on_click': 'Join on click',
                'invite_only': 'Invite only'
            };
            return types[type] || type;
        },
        
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }
    };
}

// Update pending requests badge on Share button
function updatePendingRequestsBadge(count) {
    const badge = document.getElementById('pendingRequestsBadge');
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            // Create badge if it doesn't exist
            const shareButton = document.getElementById('shareButton');
            if (shareButton) {
                const newBadge = document.createElement('span');
                newBadge.id = 'pendingRequestsBadge';
                newBadge.className = 'absolute -top-2 -right-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[20px] h-5 animate-pulse';
                newBadge.textContent = count;
                shareButton.appendChild(newBadge);
            }
        }
    } else {
        if (badge) {
            badge.classList.add('hidden');
        }
    }
}

// Show Share Modal
function showShareModal() {
    if (window.DEBUG_MODE) console.log('showShareModal called');
    document.getElementById('shareModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load pending requests count when modal opens
    const shareModalEl = document.getElementById('shareModal');
    if (shareModalEl && shareModalEl.__x) {
        shareModalEl.__x.$data.loadJoinRequests();
    }
}

// Close Share Modal
function closeShareModal() {
    document.getElementById('shareModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Attach Share button click handler
document.addEventListener('DOMContentLoaded', function() {
    const shareButton = document.getElementById('shareButton');
    if (shareButton) {
        shareButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showShareModal();
        });
        if (window.DEBUG_MODE) console.log('Share button event listener attached');
    }
});

// Close share modal on backdrop click
document.getElementById('shareModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeShareModal();
    }
});

// Close share modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('shareModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeShareModal();
        }
    }
});

// The showCardDetails function is now in card_modal.php to avoid duplication

// Show List Details Modal
function showListDetailsModal(listData) {
    // Close any open list menus
    document.querySelectorAll('[id^="list-menu-"]').forEach(menu => menu.classList.add('hidden'));
    
    // Populate modal with list data
    document.getElementById('listDetailsTitle').textContent = listData.title;
    document.getElementById('listDetailCardCount').textContent = listData.card_count;
    
    // Format created date
    if (listData.created_at) {
        const createdDate = new Date(listData.created_at);
        document.getElementById('listDetailCreatedAt').textContent = createdDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    } else {
        document.getElementById('listDetailCreatedAt').textContent = '-';
    }
    
    // Show modal
    document.getElementById('listDetailsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Hide List Details Modal
function hideListDetailsModal() {
    document.getElementById('listDetailsModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Close list details modal on backdrop click
document.getElementById('listDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideListDetailsModal();
    }
});

// Close list details modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('listDetailsModal');
        if (modal && !modal.classList.contains('hidden')) {
            hideListDetailsModal();
        }
    }
});

// Function to close the modal
function closeCardModal() {
    const modal = document.getElementById('cardModal');
    const modalContent = document.getElementById('cardModalContent');
    
    // Animate out
    modal.style.opacity = '0';
    modalContent.style.opacity = '0';
    modalContent.style.transform = 'scale(0.95)';
    
    // Remove modal after animation
    setTimeout(() => {
        modal.classList.add('hidden');
        document.removeEventListener('keydown', handleEscapeKey);
    }, 300);
}

// Handle ESC key to close modal
function handleEscapeKey(event) {
    if (event.key === 'Escape') {
        closeCardModal();
    }
}

// Close the modal when clicking the close button
const closeButton = document.getElementById('closeModal');
if (closeButton) {
    closeButton.addEventListener('click', closeCardModal);
}

// Close modal when clicking outside the modal content
const cardModal = document.getElementById('cardModal');
if (cardModal) {
    cardModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeCardModal();
        }
    });
}

// Initialize when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Re-initialize event listeners in case of dynamic content
    const closeButton = document.getElementById('closeModal');
    if (closeButton) {
        closeButton.addEventListener('click', closeCardModal);
    }
});

// loadComments is defined in card_modal.php - do not duplicate here

// Load activity for a card
function loadActivity(cardId) {
    const container = document.getElementById('cardActivity');
    if (!container) {
        // Activity container is optional, silently skip if not present
        return;
    }
    
    // Show skeleton loading
    container.innerHTML = window.SkeletonManager ? window.SkeletonManager.generateSkeletonHTML('activity', 4) : '<div class="animate-pulse space-y-3"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div></div>';
    
    fetch(`${window.BASE_PATH}/actions/activity/get.php?card_id=${cardId}`)
        .then(response => response.json())
        .then(data => {
            if (!container) return; // Double check container still exists
            
            if (data.success && data.activities && data.activities.length > 0) {
                container.innerHTML = data.activities.map(activity => {
                    const activityType = activity.action || activity.type || 'update';
                    const userName = activity.user_name || 'System';
                    const description = activity.description || '';
                    const createdAt = activity.created_at || '';
                    
                    return `
                    <div class="flex items-start gap-2">
                        <div class="mt-0.5">
                            <div class="h-5 w-5 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-500">
                                <i class="fas ${getActivityIcon(activityType)}"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-gray-700 dark:text-gray-300">
                                <span class="font-medium">${escapeHtml(userName)}</span> ${escapeHtml(description)}
                            </p>
                            <p class="text-xs text-gray-500">${formatDate(createdAt)}</p>
                        </div>
                    </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = '<p class="text-sm text-gray-500">No activity yet</p>';
            }
        })
        .catch(error => {
            console.error('Error loading activity:', error);
            if (container) {
                container.innerHTML = '<p class="text-sm text-red-500">Error loading activity</p>';
            }
        });
}

// Helper function to format date
function formatDate(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    } catch (e) {
        return '';
    }
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Helper function to get activity icon
function getActivityIcon(type) {
    const icons = {
        'create': 'fa-plus',
        'update': 'fa-edit',
        'comment': 'fa-comment',
        'delete': 'fa-trash',
        'move': 'fa-arrows-alt',
        'complete': 'fa-check-circle',
        'reopen': 'fa-redo'
    };
    return icons[type] || 'fa-circle';
}

// Toggle list menu
function toggleListMenu(menuId) {
    // Close all other open menus
    document.querySelectorAll('.list-menu').forEach(menu => {
        if (menu.id !== menuId) {
            menu.classList.add('hidden');
        }
    });
    
    // Toggle the clicked menu
    const menu = document.getElementById(menuId);
    if (menu) {
        menu.classList.toggle('hidden');
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.relative') || e.target.closest('.list-menu-close')) {
            if (menu) menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    });
}

// Show edit list modal
function showEditListModal(listId, currentTitle) {
    // Close any open menus
    document.querySelectorAll('.list-menu').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Set up the modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit List</h3>
                    <button type="button" onclick="this.closest('.fixed').remove()" 
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-times w-5 h-5"></i>
                        <span class="sr-only">Close</span>
                    </button>
                </div>
                <form onsubmit="updateList(event, ${listId})">
                    <div class="mt-4">
                        <label for="list-title-${listId}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            List Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="list-title-${listId}" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white"
                               value="${currentTitle.replace(/"/g, '&quot;')}">
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button"
                                onclick="this.closest('.fixed').remove()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary border border-transparent rounded-lg hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.getElementById(`list-title-${listId}`).focus();
}

// Update list title
async function updateList(event, listId) {
    event.preventDefault();
    const newTitle = document.getElementById(`list-title-${listId}`).value.trim();
    
    if (!newTitle) return;
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(window.BASE_PATH + '/actions/list/update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                id: listId,
                title: newTitle,
                board_id: boardId,
                _token: csrfToken
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to update list');
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Update the list title in the UI
            const listHeader = document.querySelector(`[data-list-id="${listId}"] h3`);
            if (listHeader) {
                listHeader.textContent = newTitle;
            }
            
            // Close the modal
            const modal = event.target.closest('.fixed');
            if (modal) modal.remove();
            
            // Show success message
            showToast('List updated successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to update list');
        }
    } catch (error) {
        console.error('Error updating list:', error);
        showToast(error.message || 'An error occurred while updating the list', 'error');
    }
}

// Delete list
async function deleteList(listId) {
    const listTitle = document.querySelector(`[data-list-id="${listId}"] h3`)?.textContent || 'this list';
    
    const result = await Swal.fire({
        title: 'Delete List?',
        html: `Are you sure you want to delete <strong>${listTitle}</strong>?<br>This action cannot be undone and all tasks in this list will be permanently deleted.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            confirmButton: 'px-4 py-2 text-sm font-medium rounded-lg',
            cancelButton: 'px-4 py-2 text-sm font-medium rounded-lg mr-2',
            popup: 'dark:bg-gray-800 dark:text-white',
            title: 'dark:text-white',
            htmlContainer: 'dark:text-gray-300'
        }
    });

    if (!result.isConfirmed) {
        return;
    }
    
    try {
        const response = await fetch(window.BASE_PATH + '/actions/list/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                list_id: listId,
                board_id: boardId
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to delete list');
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Remove the list from the UI
            const listElement = document.querySelector(`[data-list-id="${listId}"]`);
            if (listElement) {
                listElement.style.opacity = '0';
                setTimeout(() => listElement.remove(), 300);
            }
            
            // Show success message
            showToast('List deleted successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to delete list');
        }
    } catch (error) {
        console.error('Error deleting list:', error);
        showToast(error.message || 'An error occurred while deleting the list', 'error');
    }
}

// Close all list menus when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.relative')) {
            document.querySelectorAll('.list-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
});

// Members modal controls
function showMembersModal() {
    const modal = document.getElementById('membersModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideMembersModal() {
    const modal = document.getElementById('membersModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Close members modal on backdrop click
document.getElementById('membersModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideMembersModal();
    }
});

// Close members modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('membersModal');
        if (modal && !modal.classList.contains('hidden')) {
            hideMembersModal();
        }
        // Also close sub-modals
        hideLeaveBoardModal();
        hideRemoveMemberModal();
        hideTransferOwnershipModal();
    }
});

// =====================================================
// LEAVE BOARD FUNCTIONALITY
// =====================================================

function showLeaveBoardConfirm() {
    document.getElementById('leaveBoardModal').classList.remove('hidden');
}

function hideLeaveBoardModal() {
    document.getElementById('leaveBoardModal')?.classList.add('hidden');
}

async function leaveBoard() {
    const btn = document.getElementById('leaveBoardBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Leaving...';
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(window.BASE_PATH + '/actions/board/leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ board_id: boardId, _token: csrfToken })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('You have left the board successfully', 'success');
            // Redirect to dashboard after a short delay
            setTimeout(() => {
                window.location.href = window.BASE_PATH + '/public/dashboard.php';
            }, 1500);
        } else {
            throw new Error(data.message || 'Failed to leave board');
        }
    } catch (error) {
        console.error('Error leaving board:', error);
        showToast(error.message || 'Failed to leave board', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Close leave board modal on backdrop click
document.getElementById('leaveBoardModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideLeaveBoardModal();
    }
});

// =====================================================
// REMOVE MEMBER FUNCTIONALITY
// =====================================================

function showRemoveMemberConfirm(userId, userName) {
    document.getElementById('removeMemberUserId').value = userId;
    document.getElementById('removeMemberName').textContent = userName;
    document.getElementById('removeMemberModal').classList.remove('hidden');
}

function hideRemoveMemberModal() {
    document.getElementById('removeMemberModal')?.classList.add('hidden');
}

// Update board members display without page reload
async function updateBoardMembersDisplay() {
    try {
        const response = await fetch(window.BASE_PATH + '/actions/board/members.php?board_id=<?php echo $boardId; ?>');
        const data = await response.json();
        if (data.success && data.members) {
            // Update member avatars in header
            const memberContainer = document.querySelector('.board-members-container');
            if (memberContainer) {
                memberContainer.innerHTML = data.members.slice(0, 5).map(member => {
                    const initials = member.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                    return member.avatar 
                        ? `<img src="${window.BASE_PATH}/uploads/avatars/${member.avatar}" alt="${member.name}" class="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 -ml-2 first:ml-0" title="${member.name}">`
                        : `<div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-medium border-2 border-white dark:border-gray-800 -ml-2 first:ml-0" title="${member.name}">${initials}</div>`;
                }).join('');
                
                if (data.members.length > 5) {
                    memberContainer.innerHTML += `<div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-medium text-gray-600 dark:text-gray-300 border-2 border-white dark:border-gray-800 -ml-2">+${data.members.length - 5}</div>`;
                }
            }
        }
    } catch (error) {
        console.error('Failed to update members display:', error);
    }
}

async function removeMember() {
    const userId = document.getElementById('removeMemberUserId').value;
    const btn = document.getElementById('removeMemberBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Removing...';
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(window.BASE_PATH + '/actions/board/remove-member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ 
                board_id: boardId,
                user_id: parseInt(userId),
                _token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'Member removed successfully', 'success');
            hideRemoveMemberModal();
            // Optimistically remove member from UI without page reload
            const memberElement = document.querySelector(`[data-member-id="${memberToRemove}"]`);
            if (memberElement) {
                memberElement.style.opacity = '0';
                memberElement.style.transform = 'scale(0.8)';
                setTimeout(() => memberElement.remove(), 200);
            }
            // Also update the board members display in header
            updateBoardMembersDisplay();
        } else {
            throw new Error(data.message || 'Failed to remove member');
        }
    } catch (error) {
        console.error('Error removing member:', error);
        showToast(error.message || 'Failed to remove member', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Close remove member modal on backdrop click
document.getElementById('removeMemberModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideRemoveMemberModal();
    }
});

// =====================================================
// TRANSFER OWNERSHIP FUNCTIONALITY
// =====================================================

function showTransferOwnershipConfirm(userId, userName) {
    document.getElementById('transferOwnerUserId').value = userId;
    document.getElementById('transferOwnerName').textContent = userName;
    document.getElementById('transferOwnershipModal').classList.remove('hidden');
}

function hideTransferOwnershipModal() {
    document.getElementById('transferOwnershipModal')?.classList.add('hidden');
}

async function transferOwnership() {
    const userId = document.getElementById('transferOwnerUserId').value;
    const btn = document.getElementById('transferOwnershipBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Transferring...';
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(window.BASE_PATH + '/actions/board/transfer-ownership.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ 
                board_id: boardId,
                new_owner_id: parseInt(userId),
                _token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'Admin role transferred successfully', 'success');
            hideTransferOwnershipModal();
            // Update UI without full page reload - refresh critical sections
            updateBoardMembersDisplay();
            // Update the board role badge if visible
            const roleBadge = document.querySelector('.board-role-badge');
            if (roleBadge) {
                roleBadge.textContent = 'Admin';
                roleBadge.className = 'board-role-badge px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
            }
        } else {
            throw new Error(data.message || 'Failed to transfer admin role');
        }
    } catch (error) {
        console.error('Error transferring ownership:', error);
        showToast(error.message || 'Failed to transfer admin role', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Close transfer ownership modal on backdrop click
document.getElementById('transferOwnershipModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideTransferOwnershipModal();
    }
});

// Activity modal controls
function showActivityModal() {
    const modal = document.getElementById('activityModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeActivityModal() {
    const modal = document.getElementById('activityModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// =====================================================
// BOARD GLOBALS
// =====================================================

// Current board ID for mention system and other features
window.currentBoardId = <?php echo json_encode($boardId); ?>;

// Update card mentions display after posting a comment with mentions
window.updateCardMentions = async function(cardId) {
    try {
        const response = await fetch(`${window.BASE_PATH}/actions/card/mentions.php?card_ids=${cardId}`);
        const data = await response.json();
        
        if (data.success && data.mentions && data.mentions[cardId]) {
            const mentions = data.mentions[cardId];
            const container = document.getElementById(`card-mentions-${cardId}`);
            
            if (mentions.length > 0) {
                const displayMentions = mentions.slice(0, 3);
                const extraCount = mentions.length - 3;
                
                let html = `<div class="flex -space-x-1.5" title="Mentioned in comments">`;
                
                displayMentions.forEach(mention => {
                    const hasAvatar = mention.avatar && mention.avatar !== 'default-avatar.png';
                    // Get initials (first 2 letters)
                    const nameParts = mention.name.split(' ');
                    let initials = nameParts[0].charAt(0).toUpperCase();
                    if (nameParts[1]) {
                        initials += nameParts[1].charAt(0).toUpperCase();
                    } else {
                        initials += nameParts[0].charAt(1).toUpperCase();
                    }
                    
                    if (hasAvatar) {
                        html += `<img class="h-6 w-6 rounded-full border-2 border-white dark:border-gray-800 shadow-sm object-cover" 
                                     src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/${escapeHtml(mention.avatar)}" 
                                     alt="${escapeHtml(mention.name)}" 
                                     title="${escapeHtml(mention.name)}">`;
                    } else {
                        html += `<div class="h-6 w-6 rounded-full border-2 border-white dark:border-gray-800 bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-[9px] font-bold shadow-sm" 
                                     title="${escapeHtml(mention.name)}">${initials}</div>`;
                    }
                });
                
                if (extraCount > 0) {
                    html += `<span class="flex items-center justify-center h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600 text-[9px] font-bold text-gray-600 dark:text-gray-200 border-2 border-white dark:border-gray-800" title="${extraCount} more">+${extraCount}</span>`;
                }
                
                html += '</div>';
                
                if (container) {
                    container.innerHTML = html;
                } else {
                    // Create the container if it doesn't exist - insert on the LEFT side
                    const cardElement = document.querySelector(`[data-card-id="${cardId}"]`);
                    if (cardElement) {
                        const metaRow = cardElement.querySelector('.flex.items-center.justify-between.pt-2');
                        if (metaRow) {
                            // Find the left side container (first child with flex items-center gap-2)
                            const leftSide = metaRow.querySelector('.flex.items-center.gap-2');
                            if (leftSide) {
                                const mentionsDiv = document.createElement('div');
                                mentionsDiv.id = `card-mentions-${cardId}`;
                                mentionsDiv.className = 'flex items-center';
                                mentionsDiv.innerHTML = html;
                                // Insert at the beginning of the left side
                                leftSide.insertBefore(mentionsDiv, leftSide.firstChild);
                            }
                        }
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error updating card mentions:', error);
    }
};

// Toggle card completion status
window.toggleCardComplete = async function(cardId) {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(window.BASE_PATH + '/actions/card/toggle_complete.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ card_id: cardId, _token: csrfToken })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const cardElement = document.getElementById(`card-${cardId}`);
            if (cardElement) {
                const isCompleted = data.is_completed;
                const cardInner = cardElement.querySelector('.block.w-full.rounded-xl');
                const titleElement = cardElement.querySelector('h3');
                const checkboxBtn = cardElement.querySelector('button[onclick*="toggleCardComplete"]');
                
                if (isCompleted) {
                    // Mark as completed
                    cardElement.classList.add('card-completed');
                    if (cardInner) {
                        cardInner.classList.remove('bg-white', 'dark:bg-gray-800', 'border-gray-200/80', 'dark:border-gray-700', 'hover:border-primary/40', 'dark:hover:border-primary/50');
                        cardInner.classList.add('bg-gray-100', 'dark:bg-gray-700/50', 'border-gray-300', 'dark:border-gray-600');
                    }
                    if (titleElement) {
                        titleElement.classList.remove('text-gray-900', 'dark:text-gray-100');
                        titleElement.classList.add('text-gray-500', 'dark:text-gray-400', 'line-through');
                    }
                    if (checkboxBtn) {
                        checkboxBtn.classList.remove('border-gray-300', 'dark:border-gray-500', 'hover:border-green-400', 'dark:hover:border-green-400');
                        checkboxBtn.classList.add('bg-green-500', 'border-green-500', 'text-white');
                        checkboxBtn.innerHTML = '<i class="fas fa-check text-[10px]"></i>';
                        checkboxBtn.title = 'Mark as incomplete';
                    }
                    showToast('Task marked as completed', 'success');
                } else {
                    // Mark as incomplete
                    cardElement.classList.remove('card-completed');
                    if (cardInner) {
                        cardInner.classList.add('bg-white', 'dark:bg-gray-800', 'border-gray-200/80', 'dark:border-gray-700', 'hover:border-primary/40', 'dark:hover:border-primary/50');
                        cardInner.classList.remove('bg-gray-100', 'dark:bg-gray-700/50', 'border-gray-300', 'dark:border-gray-600');
                    }
                    if (titleElement) {
                        titleElement.classList.add('text-gray-900', 'dark:text-gray-100');
                        titleElement.classList.remove('text-gray-500', 'dark:text-gray-400', 'line-through');
                    }
                    if (checkboxBtn) {
                        checkboxBtn.classList.add('border-gray-300', 'dark:border-gray-500', 'hover:border-green-400', 'dark:hover:border-green-400');
                        checkboxBtn.classList.remove('bg-green-500', 'border-green-500', 'text-white');
                        checkboxBtn.innerHTML = '';
                        checkboxBtn.title = 'Mark as complete';
                    }
                    showToast('Task marked as incomplete', 'info');
                }
            }
        } else {
            showToast(data.message || 'Failed to update task', 'error');
        }
    } catch (error) {
        console.error('Error toggling task completion:', error);
        showToast('Failed to update task', 'error');
    }
};

// Update card assignees display after assigning/removing members
window.updateCardAssignees = async function(cardId) {
    try {
        const response = await fetch(`${window.BASE_PATH}/actions/card/assignees.php?card_id=${cardId}`);
        const data = await response.json();
        
        if (data.success && data.assignees) {
            const assignees = data.assignees;
            let container = document.getElementById(`card-assignees-${cardId}`);
            
            if (assignees.length > 0) {
                const displayAssignees = assignees.slice(0, 3);
                const extraCount = assignees.length - 3;
                
                let html = `<div class="flex -space-x-1.5" title="Assigned members">`;
                
                displayAssignees.forEach(assignee => {
                    const hasAvatar = assignee.avatar && assignee.avatar !== 'default-avatar.png';
                    // Get initials (first 2 letters)
                    const nameParts = assignee.name.split(' ');
                    let initials = nameParts[0].charAt(0).toUpperCase();
                    if (nameParts[1]) {
                        initials += nameParts[1].charAt(0).toUpperCase();
                    } else {
                        initials += nameParts[0].charAt(1).toUpperCase();
                    }
                    
                    if (hasAvatar) {
                        html += `<img class="h-6 w-6 rounded-full border-2 border-white dark:border-gray-800 shadow-sm object-cover"
                                     src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/uploads/avatars/${escapeHtml(assignee.avatar)}"
                                     alt="${escapeHtml(assignee.name)}"
                                     title="${escapeHtml(assignee.name)}">`;
                    } else {
                        html += `<div class="h-6 w-6 rounded-full border-2 border-white dark:border-gray-800 bg-primary flex items-center justify-center text-white text-[9px] font-bold shadow-sm"
                                     title="${escapeHtml(assignee.name)}">${initials}</div>`;
                    }
                });
                
                if (extraCount > 0) {
                    html += `<span class="flex items-center justify-center h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600 text-[9px] font-bold text-gray-600 dark:text-gray-200 border-2 border-white dark:border-gray-800" title="${extraCount} more">+${extraCount}</span>`;
                }
                html += `</div>`;
                
                if (container) {
                    container.innerHTML = html;
                } else {
                    // Create the container if it doesn't exist
                    const cardElement = document.querySelector(`[data-card-id="${cardId}"]`);
                    if (cardElement) {
                        const metaRow = cardElement.querySelector('.flex.items-center.justify-between.pt-2');
                        if (metaRow) {
                            const leftSide = metaRow.querySelector('.flex.items-center.gap-2');
                            if (leftSide) {
                                const assigneesDiv = document.createElement('div');
                                assigneesDiv.id = `card-assignees-${cardId}`;
                                assigneesDiv.className = 'flex items-center';
                                assigneesDiv.innerHTML = html;
                                // Insert at beginning (blue assignees on LEFT)
                                leftSide.insertBefore(assigneesDiv, leftSide.firstChild);
                            }
                        }
                    }
                }
            } else if (container) {
                // Remove the container if no assignees
                container.remove();
            }
        }
    } catch (error) {
        console.error('Error updating card assignees:', error);
    }
};

// Helper function for escaping HTML in JS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =====================================================
// DRAG AND DROP FUNCTIONALITY (SortableJS)
// =====================================================

// Flag to prevent click events during drag
window.isDragging = false;

// User permission - set from PHP
window.userCanEdit = <?php echo json_encode($canEdit); ?>;

// Initialize drag and drop for cards
function initDragAndDrop() {
    // Get all list containers (only the card containers, not menus)
    const listContainers = document.querySelectorAll('[id^="list-"]:not([id*="menu"])');
    
    if (listContainers.length === 0) {
        console.warn('No list containers found for drag and drop');
        return;
    }
    
    console.log('Initializing drag and drop for', listContainers.length, 'lists');
    
    listContainers.forEach(container => {
        const listId = container.getAttribute('data-list-id');
        
        if (!listId) {
            console.warn('List container missing data-list-id:', container.id);
            return;
        }
        
        // Initialize Sortable on each list container
        new Sortable(container, {
            group: 'cards', // Allow cards to be dragged between lists
            animation: 200,
            easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            draggable: '[data-card-id]', // Only card elements are draggable
            filter: '.card-action-btn', // Only filter action buttons
            preventOnFilter: true, // Prevent drag on filtered elements but allow their click
            forceFallback: true, // Use fallback for card to follow cursor smoothly
            fallbackClass: 'sortable-fallback',
            fallbackOnBody: true, // Append dragged element to body for smooth movement
            fallbackTolerance: 0, // Start drag immediately
            swapThreshold: 0.65,
            delay: 50, // Minimal delay for quick response
            delayOnTouchOnly: true,
            touchStartThreshold: 3, // Pixels to move before drag starts on touch
            
            // When drag starts
            onStart: function(evt) {
                console.log('Drag started:', evt.item.dataset.cardId);
                window.isDragging = true;
                document.body.classList.add('dragging');
                // Store the original list ID on the item for permission check
                evt.item.dataset.originalListId = evt.from.dataset.listId;
                // Add visual feedback to original card position
                evt.item.style.opacity = '0.5';
            },
            
            // When drag ends (item dropped)
            onEnd: function(evt) {
                console.log('Drag ended');
                // Delay resetting isDragging to prevent click from firing
                setTimeout(() => { window.isDragging = false; }, 100);
                document.body.classList.remove('dragging');
                // Reset card opacity
                evt.item.style.opacity = '';
                evt.item.style.transform = '';
                // Clean up the stored original list ID
                delete evt.item.dataset.originalListId;
                
                const cardId = evt.item.dataset.cardId;
                const newListId = evt.to.dataset.listId;
                const oldListId = evt.from.dataset.listId;
                const newPosition = evt.newIndex;
                const oldPosition = evt.oldIndex;
                
                // Check if card was actually moved to a different list
                const movedToNewList = (oldListId !== newListId);
                
                // Only update if position or list changed
                if (movedToNewList || newPosition !== oldPosition) {
                    console.log('Task moved:', {
                        cardId: cardId,
                        oldListId: oldListId,
                        newListId: newListId,
                        oldPosition: oldPosition,
                        newPosition: newPosition,
                        movedToNewList: movedToNewList
                    });
                    
                    // Send update to server (only show toast if moved to new list)
                    updateCardPosition(cardId, newListId, oldListId, newPosition, movedToNewList);
                } else {
                    console.log('Task dropped in same position, no update needed');
                }
            },
            
            // When dragging over a list - BLOCK cross-list moves for viewers
            onMove: function(evt) {
                const fromListId = evt.from.dataset.listId;
                const toListId = evt.to.dataset.listId;
                
                // If user is a viewer and trying to move to a different list, block it
                if (!window.userCanEdit && fromListId !== toListId) {
                    // Show toast only once (not on every move event)
                    if (!window._viewerMoveWarningShown) {
                        window._viewerMoveWarningShown = true;
                        if (window.showToast) {
                            window.showToast('You cannot move tasks', 'error');
                        }
                        // Reset the flag after a delay
                        setTimeout(() => { window._viewerMoveWarningShown = false; }, 2000);
                    }
                    return false; // Block the move
                }
                
                return true; // Allow the move
            }
        });
        
        console.log('Sortable initialized for list:', listId);
    });
}

// Update card position on the server
function updateCardPosition(cardId, newListId, oldListId, newPosition, movedToNewList) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('card_id', cardId);
    formData.append('list_id', newListId);
    formData.append('old_list_id', oldListId);
    formData.append('position', newPosition);
    formData.append('_token', csrfToken);
    
    fetch(window.BASE_PATH + '/actions/card/reorder.php', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData,
        credentials: 'same-origin'
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Failed to update task position');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            // Show success message ONLY when task is moved to a different list
            if (movedToNewList) {
                if (window.showToast) {
                    window.showToast('Task moved successfully!', 'success');
                }
            }
            console.log('Task position updated successfully');
        } else {
            throw new Error(data.message || 'Failed to update task position');
        }
    })
    .catch(error => {
        console.error('Error updating task position:', error);
        if (window.showToast) {
            window.showToast('Failed to move task', 'error');
            // Revert the card to its original position (the DOM already has the old state since we haven't modified it on failure)
        }
    });
}

// Initialize drag and drop when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for Sortable to be available
    if (typeof Sortable !== 'undefined') {
        initDragAndDrop();
    } else {
        console.error('SortableJS not loaded');
        // Try again after a short delay
        setTimeout(() => {
            if (typeof Sortable !== 'undefined') {
                initDragAndDrop();
            } else {
                console.error('SortableJS still not available');
            }
        }, 500);
    }
});

<?php if ($isBoardOwner): ?>
// ========================================
// Export Board Tasks Functions (Board Owner Only)
// ========================================

function showExportModal() {
    document.getElementById('exportModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Set default dates (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('exportFromDate').value = formatDateForInput(thirtyDaysAgo);
    document.getElementById('exportToDate').value = formatDateForInput(today);
    
    // Load export preview
    loadExportPreview();
}

function hideExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

async function loadExportPreview() {
    try {
        const response = await fetch(`${window.BASE_PATH}/actions/export/tasks.php?board_id=<?php echo $boardId; ?>`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('exportPreviewLists').textContent = data.data.lists || 0;
            document.getElementById('exportPreviewTasks').textContent = data.data.tasks || 0;
        }
    } catch (error) {
        console.error('Error loading export preview:', error);
    }
}

async function exportBoardTasks(format = 'csv') {
    const fromDate = document.getElementById('exportFromDate').value;
    const toDate = document.getElementById('exportToDate').value;
    
    if (!fromDate || !toDate) {
        showToast('Please select both dates', 'error');
        return;
    }
    
    if (new Date(fromDate) > new Date(toDate)) {
        showToast('From date must be before To date', 'error');
        return;
    }
    
    // Show loading state
    const exportBtn = document.getElementById('exportBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Exporting...';
    exportBtn.disabled = true;
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(window.BASE_PATH + '/actions/export/tasks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                board_id: <?php echo $boardId; ?>,
                from_date: fromDate,
                to_date: toDate,
                format: format,
                _token: csrfToken
            })
        });
        
        if (response.ok) {
            const contentType = response.headers.get('Content-Type');
            if (contentType && contentType.includes('text/csv')) {
                // Download the file
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $board['name']); ?>_export_${fromDate}_to_${toDate}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                showToast('Export completed successfully!', 'success');
                hideExportModal();
            } else {
                const errorData = await response.json();
                showToast(errorData.message || 'Export failed', 'error');
            }
        } else {
            const errorData = await response.json();
            showToast(errorData.message || 'Export failed', 'error');
        }
    } catch (error) {
        console.error('Export error:', error);
        showToast('Export failed. Please try again.', 'error');
    } finally {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }
}

// Close export modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const exportModal = document.getElementById('exportModal');
        if (exportModal && !exportModal.classList.contains('hidden')) {
            hideExportModal();
        }
    }
});
<?php endif; ?>

<?php if ($canEdit): ?>
// ========================================
// Import Plans Functions
// ========================================

let importSelectedFile = null;

function showImportModal() {
    document.getElementById('importModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    clearImportFile();
}

function hideImportModal() {
    document.getElementById('importModal').classList.add('hidden');
    document.body.style.overflow = '';
    clearImportFile();
}

function showImportResultsModal() {
    document.getElementById('importResultsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideImportResultsModal() {
    document.getElementById('importResultsModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Refresh board content without full page reload
async function refreshBoardContent() {
    try {
        // Fetch updated lists and cards
        const response = await fetch(window.BASE_PATH + '/actions/board/get-content.php?board_id=<?php echo $boardId; ?>');
        const data = await response.json();
        
        if (data.success && data.lists) {
            // Update each list's cards
            data.lists.forEach(list => {
                const listContainer = document.querySelector(`[data-list-id="${list.id}"]`);
                if (listContainer && list.cards) {
                    // Add only new cards that don't exist
                    list.cards.forEach(card => {
                        if (!document.getElementById(`card-${card.id}`)) {
                            const cardHtml = createCardHTML(card);
                            listContainer.insertAdjacentHTML('beforeend', cardHtml);
                        }
                    });
                }
            });
            
            // Re-initialize sortable for new cards
            if (typeof initSortable === 'function') {
                initSortable();
            }
        } else {
            // Fallback to page reload if refresh fails
            window.location.reload();
        }
    } catch (error) {
        console.error('Failed to refresh board:', error);
        // Fallback to page reload
        window.location.reload();
    }
}

// Helper to create card HTML
function createCardHTML(card) {
    const isCompleted = card.is_completed ? 'card-completed' : '';
    const completedClass = card.is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 dark:border-gray-500';
    const titleClass = card.is_completed ? 'text-gray-500 dark:text-gray-400 line-through' : 'text-gray-900 dark:text-gray-100';
    
    return `
        <div id="card-${card.id}" data-card-id="${card.id}" class="group relative card-item ${isCompleted}" draggable="true">
            <div onclick="window.openCardModal(${card.id})" class="block w-full rounded-xl p-3 text-left border bg-white dark:bg-gray-800 border-gray-200/80 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer">
                <div class="flex items-start gap-2">
                    <button type="button" onclick="event.stopPropagation(); window.toggleCardComplete(${card.id})" 
                            class="flex-shrink-0 w-4 h-4 mt-0.5 rounded-full border-2 ${completedClass} transition-all duration-200" 
                            title="${card.is_completed ? 'Mark as incomplete' : 'Mark as complete'}">
                        ${card.is_completed ? '<i class="fas fa-check text-[10px]"></i>' : ''}
                    </button>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium ${titleClass} line-clamp-2">${escapeHtml(card.title)}</h3>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function clearImportFile() {
    importSelectedFile = null;
    document.getElementById('importFileInput').value = '';
    document.getElementById('importDropDefault').classList.remove('hidden');
    document.getElementById('importFileSelected').classList.add('hidden');
    document.getElementById('importErrors').classList.add('hidden');
    document.getElementById('importBtn').disabled = true;
    
    // Reset drop zone styling
    const dropZone = document.getElementById('importDropZone');
    dropZone.classList.remove('border-green-400', 'dark:border-green-500', 'bg-green-50', 'dark:bg-green-900/10');
}

function handleImportFileSelect(input) {
    const file = input.files[0];
    if (file) {
        validateAndSetFile(file);
    }
}

function validateAndSetFile(file) {
    const errorsDiv = document.getElementById('importErrors');
    const errorText = document.getElementById('importErrorText');
    
    // Hide previous errors
    errorsDiv.classList.add('hidden');
    
    // Validate file type
    const allowedExtensions = ['csv', 'xlsx'];
    const fileExt = file.name.split('.').pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExt)) {
        errorText.textContent = 'Invalid file type. Only CSV and XLSX files are allowed.';
        errorsDiv.classList.remove('hidden');
        return;
    }
    
    // Validate file size (5MB)
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        errorText.textContent = 'File size exceeds maximum limit of 5MB.';
        errorsDiv.classList.remove('hidden');
        return;
    }
    
    // File is valid
    importSelectedFile = file;
    
    // Update UI
    document.getElementById('importDropDefault').classList.add('hidden');
    document.getElementById('importFileSelected').classList.remove('hidden');
    document.getElementById('importFileName').textContent = file.name;
    document.getElementById('importFileSize').textContent = formatFileSize(file.size);
    document.getElementById('importBtn').disabled = false;
    
    // Update drop zone styling
    const dropZone = document.getElementById('importDropZone');
    dropZone.classList.add('border-green-400', 'dark:border-green-500', 'bg-green-50', 'dark:bg-green-900/10');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Drag and drop handlers
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('importDropZone');
    if (!dropZone) return;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        dropZone.classList.add('border-green-400', 'dark:border-green-500', 'bg-green-50', 'dark:bg-green-900/10');
    }
    
    function unhighlight(e) {
        if (!importSelectedFile) {
            dropZone.classList.remove('border-green-400', 'dark:border-green-500', 'bg-green-50', 'dark:bg-green-900/10');
        }
    }
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            validateAndSetFile(files[0]);
        }
    }
});

async function importTasks() {
    if (!importSelectedFile) {
        showToast('Please select a file first', 'error');
        return;
    }
    
    const importBtn = document.getElementById('importBtn');
    const originalText = importBtn.innerHTML;
    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Importing...';
    importBtn.disabled = true;
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData();
        formData.append('import_file', importSelectedFile);
        formData.append('board_id', <?php echo $boardId; ?>);
        formData.append('_token', csrfToken);
        
        const response = await fetch(window.BASE_PATH + '/actions/card/import.php', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Hide import modal
            hideImportModal();
            
            // Show results modal
            showImportResults(data);
        } else {
            // Show error in import modal
            const errorsDiv = document.getElementById('importErrors');
            const errorText = document.getElementById('importErrorText');
            errorText.textContent = data.message;
            errorsDiv.classList.remove('hidden');
            
            importBtn.innerHTML = originalText;
            importBtn.disabled = false;
        }
    } catch (error) {
        console.error('Import error:', error);
        showToast('Failed to import tasks. Please try again.', 'error');
        
        importBtn.innerHTML = originalText;
        importBtn.disabled = false;
    }
}

function showImportResults(data) {
    const results = data.data;
    
    // Set icon based on results
    const iconDiv = document.getElementById('importResultIcon');
    const titleEl = document.getElementById('importResultTitle');
    
    if (results.failed === 0) {
        iconDiv.className = 'flex items-center justify-center w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/30 mb-3';
        iconDiv.innerHTML = '<i class="fas fa-check-circle text-2xl text-green-600 dark:text-green-400"></i>';
        titleEl.textContent = 'Import Successful!';
    } else if (results.success > 0) {
        iconDiv.className = 'flex items-center justify-center w-12 h-12 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 mb-3';
        iconDiv.innerHTML = '<i class="fas fa-exclamation-circle text-2xl text-yellow-600 dark:text-yellow-400"></i>';
        titleEl.textContent = 'Import Completed with Warnings';
    } else {
        iconDiv.className = 'flex items-center justify-center w-12 h-12 rounded-lg bg-red-100 dark:bg-red-900/30 mb-3';
        iconDiv.innerHTML = '<i class="fas fa-times-circle text-2xl text-red-600 dark:text-red-400"></i>';
        titleEl.textContent = 'Import Failed';
    }
    
    // Set counts
    document.getElementById('importSuccessCount').textContent = results.success;
    document.getElementById('importFailedCount').textContent = results.failed;
    
    // Show created lists
    const createdListsDiv = document.getElementById('importCreatedLists');
    const createdListsContent = document.getElementById('importCreatedListsContent');
    if (results.created_lists && results.created_lists.length > 0) {
        createdListsContent.textContent = results.created_lists.join(', ');
        createdListsDiv.classList.remove('hidden');
    } else {
        createdListsDiv.classList.add('hidden');
    }
    
    // Show created labels
    const createdLabelsDiv = document.getElementById('importCreatedLabels');
    const createdLabelsContent = document.getElementById('importCreatedLabelsContent');
    if (results.created_labels && results.created_labels.length > 0) {
        createdLabelsContent.textContent = results.created_labels.join(', ');
        createdLabelsDiv.classList.remove('hidden');
    } else {
        createdLabelsDiv.classList.add('hidden');
    }
    
    // Show warnings
    const warningsDiv = document.getElementById('importWarnings');
    const warningsContent = document.getElementById('importWarningsContent');
    if (results.warnings && results.warnings.length > 0) {
        warningsContent.innerHTML = results.warnings.map(w => `<li>${escapeHtml(w)}</li>`).join('');
        warningsDiv.classList.remove('hidden');
    } else {
        warningsDiv.classList.add('hidden');
    }
    
    // Show errors
    const errorsDiv = document.getElementById('importErrorsList');
    const errorsContent = document.getElementById('importErrorsContent');
    if (results.errors && results.errors.length > 0) {
        errorsContent.innerHTML = results.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('');
        errorsDiv.classList.remove('hidden');
    } else {
        errorsDiv.classList.add('hidden');
    }
    
    // Show the results modal
    showImportResultsModal();
}

// Close import modals on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const importModal = document.getElementById('importModal');
        const importResultsModal = document.getElementById('importResultsModal');
        
        if (importResultsModal && !importResultsModal.classList.contains('hidden')) {
            hideImportResultsModal();
            refreshBoardContent();
        } else if (importModal && !importModal.classList.contains('hidden')) {
            hideImportModal();
        }
    }
});
<?php endif; ?>
</script>

<?php if ($isBoardOwner): ?>
<!-- Export Board Tasks Modal (Board Owner Only) -->
<div id="exportModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-80" onclick="hideExportModal()"></div>
        
        <!-- Modal -->
        <div class="relative z-10 w-full max-w-lg p-6 mx-auto bg-white dark:bg-gray-800 rounded-xl shadow-2xl">
            <!-- Header -->
            <div class="relative mb-6">
                <button onclick="hideExportModal()" class="absolute right-0 top-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div class="flex flex-col items-center text-center">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 dark:bg-primary/20 mb-3">
                        <i class="fas fa-file-export text-xl text-primary"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Export Board Tasks</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($board['name']); ?></p>
                </div>
            </div>
            
            <!-- Export Preview Stats -->
            <div class="grid grid-cols-2 gap-4 mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary" id="exportPreviewLists">-</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Lists</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400" id="exportPreviewTasks">-</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Tasks</div>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    <i class="fas fa-calendar-alt mr-2 text-gray-400"></i>Date Range (by Created Date)
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">From</label>
                        <input 
                            type="date" 
                            id="exportFromDate"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent transition-colors"
                        >
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">To</label>
                        <input 
                            type="date" 
                            id="exportToDate"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent transition-colors"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Export Info -->
            <div class="mb-6 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium mb-1">Export includes:</p>
                        <ul class="text-xs space-y-0.5 list-disc list-inside">
                            <li>All tasks from this board</li>
                            <li>Task details: title, description, status, members, labels, dates</li>
                            <li>Attachment names and comment counts</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end gap-3">
                <button 
                    type="button"
                    onclick="hideExportModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="button"
                    id="exportBtn"
                    onclick="exportBoardTasks('csv')"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary-dark rounded-lg shadow-sm hover:shadow-md transition-all"
                >
                    <i class="fas fa-download mr-2"></i> Export CSV
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<!-- Import Plans Modal -->
<div id="importModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="hideImportModal()"></div>
        
        <!-- Modal -->
        <div class="relative z-10 w-full max-w-lg p-6 mx-auto bg-white dark:bg-gray-800 rounded-xl shadow-2xl">
            <!-- Header -->
            <div class="relative mb-6">
                <button onclick="hideImportModal()" class="absolute right-0 top-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div class="flex flex-col items-center text-center">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 dark:bg-primary/20 mb-3">
                        <i class="fas fa-file-import text-xl text-primary"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Plans</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($board['name']); ?></p>
                </div>
            </div>
            
            <!-- Download Sample Link -->
            <div class="flex justify-end mb-4">
                <a 
                    href="<?php echo BASE_PATH; ?>/actions/card/import.php?action=sample&format=csv" 
                    class="inline-flex items-center text-sm text-primary hover:text-primary-dark transition-colors"
                    download
                >
                    <i class="fas fa-download mr-2 text-xs"></i>
                    Download Sample File
                </a>
            </div>
            
            <!-- Upload Area -->
            <div 
                id="importDropZone"
                class="relative border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center transition-all duration-200 hover:border-primary dark:hover:border-primary cursor-pointer mb-4"
                onclick="document.getElementById('importFileInput').click()"
            >
                <input 
                    type="file" 
                    id="importFileInput" 
                    accept=".csv,.xlsx" 
                    class="hidden"
                    onchange="handleImportFileSelect(this)"
                >
                
                <!-- Default State -->
                <div id="importDropDefault" class="space-y-3">
                    <div class="flex justify-center">
                        <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 dark:text-gray-500"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Drag and drop your file here
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            or click to browse
                        </p>
                    </div>
                    <div class="flex justify-center gap-2">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                            <i class="fas fa-file-csv mr-1"></i> CSV
                        </span>
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                            <i class="fas fa-file-excel mr-1"></i> XLSX
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Max file size: 5MB
                    </p>
                </div>
                
                <!-- File Selected State -->
                <div id="importFileSelected" class="hidden space-y-3">
                    <div class="flex justify-center">
                        <div class="w-16 h-16 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
                            <i class="fas fa-file-alt text-2xl text-primary"></i>
                        </div>
                    </div>
                    <div>
                        <p id="importFileName" class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate max-w-xs mx-auto"></p>
                        <p id="importFileSize" class="text-xs text-gray-500 dark:text-gray-400 mt-1"></p>
                    </div>
                    <button 
                        type="button"
                        onclick="event.stopPropagation(); clearImportFile()"
                        class="inline-flex items-center text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                    >
                        <i class="fas fa-times mr-1"></i> Remove
                    </button>
                </div>
            </div>
            
            <!-- Validation Errors -->
            <div id="importErrors" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-2"></i>
                    <div class="text-sm text-red-700 dark:text-red-300">
                        <p class="font-medium mb-1">Validation Error</p>
                        <p id="importErrorText" class="text-xs"></p>
                    </div>
                </div>
            </div>
            
            <!-- Import Info -->
            <div class="mb-6 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium mb-1">Import Guidelines:</p>
                        <ul class="text-xs space-y-0.5 list-disc list-inside">
                            <li>Do not modify column headers</li>
                            <li>Lists will be created automatically if they don't exist</li>
                            <li>Assignees must be board members (by email)</li>
                            <li>Labels can be comma-separated</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end gap-3">
                <button 
                    type="button"
                    onclick="hideImportModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="button"
                    id="importBtn"
                    onclick="importTasks()"
                    disabled
                    class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary-dark rounded-lg shadow-sm hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-sm"
                >
                    <i class="fas fa-file-import mr-2"></i> Import Plans
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import Results Modal -->
<div id="importResultsModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="hideImportResultsModal(); refreshBoardContent();"></div>
        
        <!-- Modal -->
        <div class="relative z-10 w-full max-w-lg p-6 mx-auto bg-white dark:bg-gray-800 rounded-xl shadow-2xl">
            <!-- Header -->
            <div class="relative mb-6">
                <button onclick="hideImportResultsModal(); refreshBoardContent();" class="absolute right-0 top-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div class="flex flex-col items-center text-center">
                    <div id="importResultIcon" class="flex items-center justify-center w-12 h-12 rounded-lg mb-3">
                        <!-- Icon will be set dynamically -->
                    </div>
                    <h3 id="importResultTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Import Complete</h3>
                </div>
            </div>
            
            <!-- Results Summary -->
            <div id="importResultsSummary" class="mb-4 grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="text-center">
                    <div id="importSuccessCount" class="text-2xl font-bold text-green-600 dark:text-green-400">0</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Tasks Imported</div>
                </div>
                <div class="text-center">
                    <div id="importFailedCount" class="text-2xl font-bold text-red-600 dark:text-red-400">0</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
                </div>
            </div>
            
            <!-- Created Lists -->
            <div id="importCreatedLists" class="hidden mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <p class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-2">
                    <i class="fas fa-list mr-1"></i> New Lists Created:
                </p>
                <div id="importCreatedListsContent" class="text-xs text-blue-600 dark:text-blue-400"></div>
            </div>
            
            <!-- Created Labels -->
            <div id="importCreatedLabels" class="hidden mb-4 p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                <p class="text-sm font-medium text-purple-700 dark:text-purple-300 mb-2">
                    <i class="fas fa-tags mr-1"></i> New Labels Created:
                </p>
                <div id="importCreatedLabelsContent" class="text-xs text-purple-600 dark:text-purple-400"></div>
            </div>
            
            <!-- Warnings -->
            <div id="importWarnings" class="hidden mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg max-h-32 overflow-y-auto">
                <p class="text-sm font-medium text-yellow-700 dark:text-yellow-300 mb-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Warnings:
                </p>
                <ul id="importWarningsContent" class="text-xs text-yellow-600 dark:text-yellow-400 list-disc list-inside space-y-1"></ul>
            </div>
            
            <!-- Errors -->
            <div id="importErrorsList" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg max-h-32 overflow-y-auto">
                <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-2">
                    <i class="fas fa-times-circle mr-1"></i> Errors:
                </p>
                <ul id="importErrorsContent" class="text-xs text-red-600 dark:text-red-400 list-disc list-inside space-y-1"></ul>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end">
                <button 
                    type="button"
                    onclick="hideImportResultsModal(); refreshBoardContent();"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary-dark rounded-lg shadow-sm hover:shadow-md transition-all"
                >
                    <i class="fas fa-check mr-2"></i> Done
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- AI Chatbot -->
<div id="chatbotContainer" class="fixed bottom-8 right-8 z-[9999]">
    <!-- Chatbot Toggle Button -->
    <button 
        id="chatbotToggle"
        onclick="toggleChatbot()"
        class="chatbot-theme-btn w-14 h-14 text-white rounded-full shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 flex items-center justify-center"
        title="Ask Planify AI"
    >
        <i class="fas fa-robot text-xl"></i>
        <span class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white dark:border-gray-900 animate-pulse"></span>
    </button>
</div>

<!-- Chatbot Panel -->
<div id="chatbotPanel" class="fixed top-0 right-0 bottom-0 bg-white dark:bg-gray-800 shadow-2xl transform translate-x-full transition-all duration-300 ease-in-out z-[10000] flex flex-col" style="width: 750px; max-width: 100%;">
    <!-- Resize Handle -->
    <div id="chatbotResizeHandle" class="chatbot-resize-handle">
        <div class="chatbot-resize-grip">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <!-- Header -->
    <div class="chatbot-theme-header p-4 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                <i class="fas fa-robot text-white text-lg"></i>
            </div>
            <div>
                <h3 class="text-white font-semibold">Planify Assistant</h3>
                <p class="text-white/70 text-xs">AI-powered help for your board</p>
            </div>
        </div>
        <div class="flex items-center gap-1">
            <button onclick="toggleChatbot()" class="text-white/80 hover:text-white transition-colors p-2" title="Close">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
    </div>
    
    <!-- Suggested Questions -->
    <div id="suggestedQuestions" class="p-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Quick questions:</p>
        <div class="flex flex-wrap gap-2">
            <button onclick="askQuestion('What tasks are pending?')" class="chatbot-quick-btn text-xs px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full transition-colors">
                Pending tasks
            </button>
            <button onclick="askQuestion('Give me a board summary')" class="chatbot-quick-btn text-xs px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full transition-colors">
                Board summary
            </button>
            <button onclick="askQuestion('What tasks are overdue?')" class="chatbot-quick-btn text-xs px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full transition-colors">
                Overdue tasks
            </button>
            <button onclick="askQuestion('Who is assigned to tasks?')" class="chatbot-quick-btn text-xs px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full transition-colors">
                Assignees
            </button>
        </div>
    </div>
    
    <!-- Chat Messages -->
    <div id="chatMessages" 
         class="flex-1 overflow-y-auto p-4 space-y-4"
         ondragover="handleChatDragOver(event)"
         ondragleave="handleChatDragLeave(event)"
         ondrop="handleChatDrop(event)"
    >
        <!-- Welcome Message -->
        <div class="flex gap-3">
            <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-white text-sm"></i>
            </div>
            <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-md px-4 py-3 max-w-[85%]">
                <p class="text-sm text-gray-700 dark:text-gray-200">
                    Hi! 👋 I'm your Planify Assistant. Ask me anything about this board, or upload an image for me to analyze!
                </p>
            </div>
        </div>
    </div>
    
    <!-- Drag & Drop Overlay -->
    <div id="chatDropOverlay" class="hidden absolute inset-0 bg-primary/20 backdrop-blur-sm flex items-center justify-center z-10 pointer-events-none">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl text-center">
            <i class="fas fa-cloud-upload-alt text-4xl text-primary mb-3"></i>
            <p class="text-gray-700 dark:text-gray-200 font-medium">Drop file here</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Images, PDF, CSV, TXT & more</p>
        </div>
    </div>
    
    <!-- Multiple Files Preview Area -->
    <div id="chatFilesPreview" class="hidden p-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 max-h-40 overflow-y-auto">
        <div class="flex items-center justify-between mb-2">
            <span id="chatFilesCount" class="text-xs font-medium text-gray-600 dark:text-gray-400">0 files selected</span>
            <button onclick="clearAllChatFiles()" class="text-xs text-red-500 hover:text-red-600 transition-colors">
                <i class="fas fa-times mr-1"></i>Clear all
            </button>
        </div>
        <div id="chatFilesContainer" class="flex flex-wrap gap-2">
            <!-- Files will be added here dynamically -->
        </div>
    </div>
    
    <!-- Input Area -->
    <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex-shrink-0 mt-auto">
        <div class="flex gap-2 items-end">
            <!-- Image Upload Button -->
            <input type="file" id="chatImageInput" accept="image/*" class="hidden" onchange="handleChatImageSelect(event)" multiple>
            <button 
                onclick="document.getElementById('chatImageInput').click()"
                class="p-2.5 text-gray-500 hover:text-blue-500 dark:text-gray-400 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-all duration-200 flex-shrink-0"
                title="Upload images (up to 5)"
            >
                <i class="fas fa-image text-lg"></i>
            </button>
            <!-- File Upload Button (documents) - Multiple files -->
            <input type="file" id="chatFileInput" accept=".pdf,.csv,.txt,.json,.xml,.md,.html,.css,.js,.py,.php,.sql,.log,.doc,.docx,.xls,.xlsx" class="hidden" onchange="handleChatFileSelect(event)" multiple>
            <button 
                onclick="document.getElementById('chatFileInput').click()"
                class="p-2.5 text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-primary hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200 flex-shrink-0"
                title="Upload files (PDF, CSV, TXT, code files)"
            >
                <i class="fas fa-paperclip text-lg"></i>
            </button>
            <textarea 
                id="chatInput"
                placeholder="Ask about your tasks..."
                rows="1"
                class="chatbot-input flex-1 px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent text-sm text-gray-900 dark:text-white placeholder-gray-400 resize-none overflow-hidden"
                onkeydown="handleChatKeydown(event)"
                oninput="autoResizeChatInput(this)"
                onpaste="handleChatPaste(event)"
            ></textarea>
            <button 
                id="chatSendBtn"
                onclick="sendChatMessage()"
                class="chatbot-theme-btn px-4 py-2.5 text-white rounded-lg transition-all duration-200 flex items-center justify-center flex-shrink-0"
            >
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="flex items-center justify-between mt-2">
            <span class="text-xs text-gray-400">
                <i class="fas fa-image text-blue-400 mr-1"></i> Images
                <span class="mx-1">|</span>
                <i class="fas fa-paperclip text-gray-400 mr-1"></i> Files
                <span class="mx-1">·</span>
                <span>Max 5</span>
            </span>
            <button onclick="clearChat()" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <i class="fas fa-trash-alt mr-1"></i> Clear chat
            </button>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div id="chatImageModal" class="fixed inset-0 z-[20000] hidden" onclick="closeChatImageModal(event)">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative max-w-[90vw] max-h-[90vh]">
            <img id="chatImageModalImg" src="" alt="Full size image" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl">
            <button 
                onclick="closeChatImageModal(event)" 
                class="absolute -top-3 -right-3 w-10 h-10 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-full flex items-center justify-center shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
                <i class="fas fa-times text-lg"></i>
            </button>
            <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2">
                <a id="chatImageDownloadBtn" href="" download="image" class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-lg shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-sm flex items-center gap-2">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Prevent unnecessary scrollbar */
    html, body {
        overflow-x: hidden;
    }
    
    /* Chatbot Panel - ensure full height */
    #chatbotPanel {
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        height: 100vh !important;
        height: 100dvh !important;
        min-height: 100vh !important;
        max-height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
    }
    
    #chatbotPanel #chatMessages {
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        min-height: 0 !important;
    }
    
    /* Chatbot Theme Colors - Uses CSS Custom Properties */
    .chatbot-theme-btn {
        background-color: var(--color-primary);
    }
    .chatbot-theme-btn:hover {
        background-color: var(--color-primary-dark);
    }
    .chatbot-theme-header {
        background-color: var(--color-primary);
    }
    .chatbot-avatar {
        background-color: var(--color-primary);
    }
    .chatbot-user-bubble {
        background-color: var(--color-primary);
    }
    .chatbot-quick-btn:hover {
        border-color: var(--color-primary);
        color: var(--color-primary);
    }
    .chatbot-input:focus {
        --tw-ring-color: var(--color-primary);
        box-shadow: 0 0 0 2px rgba(var(--color-primary-rgb), 0.3);
    }
    
    /* AI Chatbot Styles */
    .ai-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 12px 0;
        font-size: 13px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid var(--color-primary);
        box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.15);
    }
    .ai-table th, .ai-table td {
        border-bottom: 1px solid rgba(var(--color-primary-rgb), 0.2);
        border-right: 1px solid rgba(var(--color-primary-rgb), 0.2);
        padding: 10px 14px;
        text-align: left;
    }
    .ai-table th:last-child, .ai-table td:last-child {
        border-right: none;
    }
    .ai-table tr:last-child td {
        border-bottom: none;
    }
    .ai-table th {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
        font-weight: 600;
        color: #ffffff;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--color-primary-dark);
    }
    .ai-table td {
        color: #374151;
        background: #ffffff;
    }
    .ai-table tr:nth-child(even) td {
        background: rgba(var(--color-primary-rgb), 0.05);
    }
    .ai-table tr:hover td {
        background: rgba(var(--color-primary-rgb), 0.1);
    }
    /* Dark mode table styles */
    .dark .ai-table {
        border-color: var(--color-primary);
        box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.25);
    }
    .dark .ai-table th, .dark .ai-table td {
        border-color: var(--color-primary-dark);
    }
    .dark .ai-table th {
        background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
        border-bottom-color: var(--color-primary-dark);
    }
    .dark .ai-table td {
        color: #e5e7eb;
        background: rgba(var(--color-primary-rgb), 0.1);
    }
    .dark .ai-table tr:nth-child(even) td {
        background: #312e81;
    }
    .dark .ai-table tr:hover td {
        background: #3730a3;
    }
    
    /* AI Message Content Styling */
    .ai-message-content strong {
        font-weight: 600;
        color: var(--color-primary);
    }
    .dark .ai-message-content strong {
        color: var(--color-primary-light);
    }
    .ai-message-content em {
        font-style: italic;
        opacity: 0.9;
    }
    .ai-message-content ul, .ai-message-content ol {
        margin: 0.5rem 0;
        padding-left: 0;
    }
    .ai-message-content li {
        margin-bottom: 0.25rem;
        list-style-position: inside;
    }
    .ai-message-content h2, .ai-message-content h3, .ai-message-content h4 {
        color: inherit;
        margin-top: 0.75rem;
        margin-bottom: 0.25rem;
    }
    .ai-message-content code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.875em;
    }
    .ai-message-content pre {
        margin: 0.5rem 0;
    }
    .ai-message-content pre code {
        display: block;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .ai-message-content hr {
        border: none;
        border-top: 1px solid rgba(var(--color-primary-rgb), 0.2);
        margin: 0.75rem 0;
    }
    
    /* Chat bubble animation */
    @keyframes chatBubble {
        0% { opacity: 0; transform: translateY(10px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .chat-bubble {
        animation: chatBubble 0.3s ease-out;
    }
    
    /* Typing indicator */
    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 12px 16px;
    }
    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: #9ca3af;
        border-radius: 50%;
        animation: typingBounce 1.4s infinite ease-in-out;
    }
    .typing-indicator span:nth-child(1) { animation-delay: 0s; }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingBounce {
        0%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(-6px); }
    }
    
    /* Chatbot Resize Handle */
    .chatbot-resize-handle {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        cursor: ew-resize;
        z-index: 10;
        transition: background-color 0.2s ease;
    }
    
    .chatbot-resize-handle:hover,
    .chatbot-resize-handle.active {
        background: linear-gradient(90deg, rgba(var(--color-primary-rgb), 0.3) 0%, transparent 100%);
    }
    
    .chatbot-resize-grip {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 8px 4px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    
    .chatbot-resize-handle:hover .chatbot-resize-grip,
    .chatbot-resize-handle.active .chatbot-resize-grip {
        opacity: 1;
    }
    
    .chatbot-resize-grip span {
        width: 3px;
        height: 3px;
        background: var(--color-primary);
        border-radius: 50%;
    }
    
    /* Prevent text selection during resize */
    body.chatbot-resizing {
        cursor: ew-resize !important;
        user-select: none !important;
        -webkit-user-select: none !important;
    }
    
    body.chatbot-resizing * {
        cursor: ew-resize !important;
    }
    
    /* Smooth width transition when not dragging */
    #chatbotPanel:not(.resizing) {
        transition: transform 0.3s ease-in-out, width 0.15s ease-out;
    }
    
    /* No transition during resize for instant feedback */
    #chatbotPanel.resizing {
        transition: transform 0.3s ease-in-out !important;
    }
    
    /* Custom width state - override expanded class */
    #chatbotPanel.chatbot-custom-width {
        width: var(--chatbot-width) !important;
        max-width: 95vw !important;
    }
    
    /* Fullscreen mode */
    #chatbotPanel.chatbot-fullscreen {
        width: 100vw !important;
        max-width: 100vw !important;
        border-radius: 0 !important;
    }
    
    #chatbotPanel.chatbot-fullscreen .chatbot-resize-handle {
        width: 8px;
        background: linear-gradient(90deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    }
    
    #chatbotPanel.chatbot-fullscreen .chatbot-resize-handle:hover,
    #chatbotPanel.chatbot-fullscreen .chatbot-resize-handle.active {
        background: linear-gradient(90deg, rgba(var(--color-primary-rgb), 0.4) 0%, transparent 100%);
    }
    
    /* Hide resize handle on mobile */
    @media (max-width: 768px) {
        .chatbot-resize-handle {
            display: none;
        }
        #chatbotPanel.chatbot-custom-width {
            width: 100vw !important;
            max-width: 100vw !important;
        }
    }
</style>

<script>
// Chatbot functionality
const currentBoardIdForChat = <?php echo $boardId; ?>;
let isChatbotOpen = false;
let chatHistoryLoaded = false;

// ========================================
// Chatbot Resize Functionality
// ========================================
const CHATBOT_MIN_WIDTH = 320;  // Minimum width in pixels
const CHATBOT_MAX_WIDTH = window.innerWidth * 0.85;  // Max 85% of viewport before fullscreen
const CHATBOT_FULLSCREEN_THRESHOLD = 0.85;  // Trigger fullscreen at 85% of viewport width
const CHATBOT_DEFAULT_WIDTH = 750;  // Default width in pixels

let chatbotCustomWidth = parseInt(localStorage.getItem('chatbotCustomWidth')) || null;
let isChatbotFullscreen = localStorage.getItem('chatbotFullscreen') === 'true';
let isResizing = false;
let resizeStartX = 0;
let resizeStartWidth = 0;

// Initialize chatbot resize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initChatbotResize();
});

function initChatbotResize() {
    const panel = document.getElementById('chatbotPanel');
    const handle = document.getElementById('chatbotResizeHandle');
    
    if (!panel || !handle) return;
    
    // Apply saved custom width if exists
    if (chatbotCustomWidth) {
        applyChatbotWidth(chatbotCustomWidth);
    }
    
    // Mouse events for resize handle
    handle.addEventListener('mousedown', startChatbotResize);
    
    // Touch events for mobile (though handle is hidden on mobile)
    handle.addEventListener('touchstart', startChatbotResizeTouch, { passive: false });
}

function startChatbotResize(e) {
    e.preventDefault();
    
    const panel = document.getElementById('chatbotPanel');
    const handle = document.getElementById('chatbotResizeHandle');
    
    isResizing = true;
    resizeStartX = e.clientX;
    resizeStartWidth = panel.offsetWidth;
    
    // Add active states
    document.body.classList.add('chatbot-resizing');
    panel.classList.add('resizing');
    handle.classList.add('active');
    
    // Add document-level listeners for smooth tracking
    document.addEventListener('mousemove', doChatbotResize);
    document.addEventListener('mouseup', stopChatbotResize);
}

function startChatbotResizeTouch(e) {
    if (e.touches.length !== 1) return;
    e.preventDefault();
    
    const touch = e.touches[0];
    const panel = document.getElementById('chatbotPanel');
    const handle = document.getElementById('chatbotResizeHandle');
    
    isResizing = true;
    resizeStartX = touch.clientX;
    resizeStartWidth = panel.offsetWidth;
    
    document.body.classList.add('chatbot-resizing');
    panel.classList.add('resizing');
    handle.classList.add('active');
    
    document.addEventListener('touchmove', doChatbotResizeTouch, { passive: false });
    document.addEventListener('touchend', stopChatbotResizeTouch);
    document.addEventListener('touchcancel', stopChatbotResizeTouch);
}

function doChatbotResize(e) {
    if (!isResizing) return;
    
    // Calculate new width (dragging left increases width since panel is on right)
    const deltaX = resizeStartX - e.clientX;
    let newWidth = resizeStartWidth + deltaX;
    
    // Check if we should trigger fullscreen mode
    const fullscreenThreshold = window.innerWidth * CHATBOT_FULLSCREEN_THRESHOLD;
    if (newWidth >= fullscreenThreshold) {
        enterChatbotFullscreen();
        return;
    }
    
    // Exit fullscreen if dragging back below threshold
    if (isChatbotFullscreen && newWidth < fullscreenThreshold) {
        exitChatbotFullscreen();
    }
    
    // Clamp to min/max bounds
    newWidth = Math.max(CHATBOT_MIN_WIDTH, Math.min(window.innerWidth * 0.95, newWidth));
    
    // Apply width immediately for smooth feel
    applyChatbotWidth(newWidth);
}

function doChatbotResizeTouch(e) {
    if (!isResizing || e.touches.length !== 1) return;
    e.preventDefault();
    
    const touch = e.touches[0];
    const deltaX = resizeStartX - touch.clientX;
    let newWidth = resizeStartWidth + deltaX;
    
    // Check if we should trigger fullscreen mode
    const fullscreenThreshold = window.innerWidth * CHATBOT_FULLSCREEN_THRESHOLD;
    if (newWidth >= fullscreenThreshold) {
        enterChatbotFullscreen();
        return;
    }
    
    // Exit fullscreen if dragging back below threshold
    if (isChatbotFullscreen && newWidth < fullscreenThreshold) {
        exitChatbotFullscreen();
    }
    
    newWidth = Math.max(CHATBOT_MIN_WIDTH, Math.min(window.innerWidth * 0.95, newWidth));
    applyChatbotWidth(newWidth);
}

function stopChatbotResize(e) {
    if (!isResizing) return;
    
    const panel = document.getElementById('chatbotPanel');
    const handle = document.getElementById('chatbotResizeHandle');
    
    isResizing = false;
    
    // Remove active states
    document.body.classList.remove('chatbot-resizing');
    panel.classList.remove('resizing');
    handle.classList.remove('active');
    
    // Remove document listeners
    document.removeEventListener('mousemove', doChatbotResize);
    document.removeEventListener('mouseup', stopChatbotResize);
    
    // Save the custom width
    saveChatbotWidth();
}

function stopChatbotResizeTouch(e) {
    if (!isResizing) return;
    
    const panel = document.getElementById('chatbotPanel');
    const handle = document.getElementById('chatbotResizeHandle');
    
    isResizing = false;
    
    document.body.classList.remove('chatbot-resizing');
    panel.classList.remove('resizing');
    handle.classList.remove('active');
    
    document.removeEventListener('touchmove', doChatbotResizeTouch);
    document.removeEventListener('touchend', stopChatbotResizeTouch);
    document.removeEventListener('touchcancel', stopChatbotResizeTouch);
    
    saveChatbotWidth();
}

function applyChatbotWidth(width) {
    const panel = document.getElementById('chatbotPanel');
    if (!panel) return;
    
    chatbotCustomWidth = width;
    
    // Set CSS custom property and add custom width class
    panel.style.setProperty('--chatbot-width', width + 'px');
    panel.classList.add('chatbot-custom-width');
    
    // Remove fullscreen class if active
    panel.classList.remove('chatbot-fullscreen');
    isChatbotFullscreen = false;
}

function saveChatbotWidth() {
    if (chatbotCustomWidth) {
        localStorage.setItem('chatbotCustomWidth', chatbotCustomWidth);
    }
}

function resetChatbotWidth() {
    const panel = document.getElementById('chatbotPanel');
    if (!panel) return;
    
    chatbotCustomWidth = null;
    localStorage.removeItem('chatbotCustomWidth');
    panel.classList.remove('chatbot-custom-width');
    panel.style.removeProperty('--chatbot-width');
}

function enterChatbotFullscreen() {
    const panel = document.getElementById('chatbotPanel');
    if (!panel || isChatbotFullscreen) return;
    
    isChatbotFullscreen = true;
    chatbotCustomWidth = null;
    
    // Remove custom width class and add fullscreen
    panel.classList.remove('chatbot-custom-width');
    panel.style.removeProperty('--chatbot-width');
    panel.classList.add('chatbot-fullscreen');
    
    // Save state
    localStorage.setItem('chatbotFullscreen', 'true');
    localStorage.removeItem('chatbotCustomWidth');
}

function exitChatbotFullscreen() {
    const panel = document.getElementById('chatbotPanel');
    if (!panel || !isChatbotFullscreen) return;
    
    isChatbotFullscreen = false;
    panel.classList.remove('chatbot-fullscreen');
    
    // Set to 80% of viewport as starting point after exiting fullscreen
    const newWidth = window.innerWidth * 0.8;
    applyChatbotWidth(newWidth);
    
    localStorage.removeItem('chatbotFullscreen');
}

// Update max width on window resize
window.addEventListener('resize', function() {
    const newMax = Math.min(900, window.innerWidth * 0.9);
    if (chatbotCustomWidth && chatbotCustomWidth > newMax) {
        applyChatbotWidth(newMax);
        saveChatbotWidth();
    }
});

function toggleChatbot() {
    const panel = document.getElementById('chatbotPanel');
    const toggleBtn = document.getElementById('chatbotContainer');
    
    isChatbotOpen = !isChatbotOpen;
    
    if (isChatbotOpen) {
        panel.classList.remove('translate-x-full');
        toggleBtn.classList.add('hidden');
        
        // Restore fullscreen, custom width, or apply default width
        if (isChatbotFullscreen) {
            panel.classList.add('chatbot-fullscreen');
        } else if (chatbotCustomWidth) {
            applyChatbotWidth(chatbotCustomWidth);
        } else {
            // Apply default width of 750px on first open
            applyChatbotWidth(CHATBOT_DEFAULT_WIDTH);
        }
        
        document.getElementById('chatInput').focus();
        
        // Load chat history on first open
        if (!chatHistoryLoaded) {
            loadChatHistory();
        }
    } else {
        panel.classList.add('translate-x-full');
        toggleBtn.classList.remove('hidden');
    }
}

// Load chat history from database
async function loadChatHistory() {
    try {
        const response = await fetch(`${window.BASE_PATH}/actions/ai/chat.php?board_id=${currentBoardIdForChat}&action=history`);
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            const chatMessages = document.getElementById('chatMessages');
            // Keep the welcome message
            const welcomeMsg = chatMessages.querySelector('.flex.gap-3');
            chatMessages.innerHTML = '';
            if (welcomeMsg) {
                chatMessages.appendChild(welcomeMsg);
            }
            
            // Add history messages
            data.messages.forEach(msg => {
                addMessageToChat(msg.message, msg.role === 'user' ? 'user' : 'ai', null, false, true);
            });
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        chatHistoryLoaded = true;
    } catch (error) {
        console.error('Error loading chat history:', error);
        chatHistoryLoaded = true; // Don't retry on error
    }
}

function askQuestion(question) {
    document.getElementById('chatInput').value = question;
    sendChatMessage();
}

// Handle keyboard events in chat input
function handleChatKeydown(event) {
    if (event.key === 'Enter') {
        if (event.shiftKey) {
            // Shift+Enter: Allow new line (default behavior)
            return true;
        } else {
            // Enter alone: Send message
            event.preventDefault();
            sendChatMessage();
            return false;
        }
    }
}

// Auto-resize textarea based on content
function autoResizeChatInput(textarea) {
    // Reset height to auto to get the correct scrollHeight
    textarea.style.height = 'auto';
    // Set new height based on content (max 120px = ~5 lines)
    const newHeight = Math.min(textarea.scrollHeight, 120);
    textarea.style.height = newHeight + 'px';
}

// ========================================
// Image Upload Functions for Chatbot
// ========================================

// File data variables (legacy kept for compatibility)
let chatImageFile = null;
let chatImageBase64 = null;

// Supported file types
const SUPPORTED_FILE_TYPES = {
    // Images
    'image/jpeg': { icon: 'fa-file-image', color: 'text-blue-500', type: 'image' },
    'image/png': { icon: 'fa-file-image', color: 'text-blue-500', type: 'image' },
    'image/gif': { icon: 'fa-file-image', color: 'text-blue-500', type: 'image' },
    'image/webp': { icon: 'fa-file-image', color: 'text-blue-500', type: 'image' },
    // Documents
    'application/pdf': { icon: 'fa-file-pdf', color: 'text-red-500', type: 'document' },
    'text/csv': { icon: 'fa-file-csv', color: 'text-green-500', type: 'text' },
    'text/plain': { icon: 'fa-file-alt', color: 'text-gray-500', type: 'text' },
    'application/json': { icon: 'fa-file-code', color: 'text-yellow-500', type: 'text' },
    'text/html': { icon: 'fa-file-code', color: 'text-orange-500', type: 'text' },
    'text/css': { icon: 'fa-file-code', color: 'text-blue-400', type: 'text' },
    'text/javascript': { icon: 'fa-file-code', color: 'text-yellow-400', type: 'text' },
    'application/javascript': { icon: 'fa-file-code', color: 'text-yellow-400', type: 'text' },
    'text/xml': { icon: 'fa-file-code', color: 'text-purple-500', type: 'text' },
    'application/xml': { icon: 'fa-file-code', color: 'text-purple-500', type: 'text' },
    'text/markdown': { icon: 'fa-file-alt', color: 'text-gray-600', type: 'text' },
    'text/x-python': { icon: 'fa-file-code', color: 'text-blue-500', type: 'text' },
    'application/x-httpd-php': { icon: 'fa-file-code', color: 'text-indigo-500', type: 'text' },
    'application/sql': { icon: 'fa-database', color: 'text-blue-600', type: 'text' },
};

// File type by extension (fallback)
const FILE_EXTENSIONS = {
    'pdf': { icon: 'fa-file-pdf', color: 'text-red-500', type: 'document', mime: 'application/pdf' },
    'csv': { icon: 'fa-file-csv', color: 'text-green-500', type: 'text', mime: 'text/csv' },
    'txt': { icon: 'fa-file-alt', color: 'text-gray-500', type: 'text', mime: 'text/plain' },
    'json': { icon: 'fa-file-code', color: 'text-yellow-500', type: 'text', mime: 'application/json' },
    'xml': { icon: 'fa-file-code', color: 'text-purple-500', type: 'text', mime: 'application/xml' },
    'html': { icon: 'fa-file-code', color: 'text-orange-500', type: 'text', mime: 'text/html' },
    'css': { icon: 'fa-file-code', color: 'text-blue-400', type: 'text', mime: 'text/css' },
    'js': { icon: 'fa-file-code', color: 'text-yellow-400', type: 'text', mime: 'text/javascript' },
    'md': { icon: 'fa-file-alt', color: 'text-gray-600', type: 'text', mime: 'text/markdown' },
    'py': { icon: 'fa-file-code', color: 'text-blue-500', type: 'text', mime: 'text/x-python' },
    'php': { icon: 'fa-file-code', color: 'text-indigo-500', type: 'text', mime: 'application/x-httpd-php' },
    'sql': { icon: 'fa-database', color: 'text-blue-600', type: 'text', mime: 'application/sql' },
    'log': { icon: 'fa-file-alt', color: 'text-gray-500', type: 'text', mime: 'text/plain' },
};

let chatFileData = null; // { file, base64, type, mimeType }

// Maximum files allowed
const MAX_CHAT_FILES = 5;

// Array to store multiple files
let chatFilesArray = [];

// Handle image selection - Multiple images
function handleChatImageSelect(event) {
    const files = Array.from(event.target.files);
    if (files.length > 0) {
        // Filter to only images
        const imageFiles = files.filter(f => f.type.startsWith('image/'));
        if (imageFiles.length > 0) {
            addFilesToChat(imageFiles);
        }
        if (imageFiles.length < files.length) {
            showToast('Only image files were added', 'warning');
        }
    }
    // Reset input so same file can be selected again
    event.target.value = '';
}

// Handle file selection (documents) - Multiple files
function handleChatFileSelect(event) {
    const files = Array.from(event.target.files);
    if (files.length > 0) {
        addFilesToChat(files);
    }
    // Reset input so same file can be selected again
    event.target.value = '';
}

// Handle paste event for images
function handleChatPaste(event) {
    const items = event.clipboardData?.items;
    if (!items) return;
    
    const files = [];
    for (let item of items) {
        if (item.type.startsWith('image/')) {
            event.preventDefault();
            const file = item.getAsFile();
            if (file) {
                files.push(file);
            }
        }
    }
    
    if (files.length > 0) {
        addFilesToChat(files);
    }
}

// Get file info based on type or extension
function getFileInfo(file) {
    // Check by MIME type first
    if (SUPPORTED_FILE_TYPES[file.type]) {
        return { ...SUPPORTED_FILE_TYPES[file.type], mime: file.type };
    }
    
    // Fallback to extension
    const ext = file.name.split('.').pop().toLowerCase();
    if (FILE_EXTENSIONS[ext]) {
        return FILE_EXTENSIONS[ext];
    }
    
    // Default
    return { icon: 'fa-file', color: 'text-gray-500', type: 'unknown', mime: file.type || 'application/octet-stream' };
}

// Add multiple files to chat
function addFilesToChat(files) {
    // Check if adding these files would exceed the limit
    const remainingSlots = MAX_CHAT_FILES - chatFilesArray.length;
    if (remainingSlots <= 0) {
        showToast(`Maximum ${MAX_CHAT_FILES} files allowed. Remove some files first.`, 'error');
        return;
    }
    
    // Only process up to remaining slots
    const filesToProcess = files.slice(0, remainingSlots);
    if (files.length > remainingSlots) {
        showToast(`Only ${remainingSlots} more file(s) can be added. ${files.length - remainingSlots} file(s) were skipped.`, 'warning');
    }
    
    filesToProcess.forEach(file => processChatFile(file));
}

// Process and preview a single file
function processChatFile(file) {
    const fileInfo = getFileInfo(file);
    
    // Check if file type is supported
    if (fileInfo.type === 'unknown') {
        showToast(`Unsupported file type: ${file.name}`, 'error');
        return;
    }
    
    // Validate file size (max 10MB for documents, 4MB for images)
    const maxSize = fileInfo.type === 'image' ? 4 * 1024 * 1024 : 10 * 1024 * 1024;
    if (file.size > maxSize) {
        showToast(`${file.name} is too large. Max ${fileInfo.type === 'image' ? '4MB' : '10MB'}`, 'error');
        return;
    }
    
    // Check for duplicate
    if (chatFilesArray.some(f => f.file.name === file.name && f.file.size === file.size)) {
        showToast(`${file.name} is already added`, 'warning');
        return;
    }
    
    // Read file
    const reader = new FileReader();
    reader.onload = function(e) {
        const fileData = {
            id: 'file-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
            file: file,
            base64: e.target.result,
            type: fileInfo.type,
            mimeType: fileInfo.mime,
            icon: fileInfo.icon,
            color: fileInfo.color
        };
        
        chatFilesArray.push(fileData);
        updateFilesPreview();
    };
    
    reader.readAsDataURL(file);
}

// Update the files preview UI
function updateFilesPreview() {
    const preview = document.getElementById('chatFilesPreview');
    const container = document.getElementById('chatFilesContainer');
    const countEl = document.getElementById('chatFilesCount');
    
    if (chatFilesArray.length === 0) {
        preview.classList.add('hidden');
        document.getElementById('chatInput').placeholder = 'Ask about your tasks...';
        return;
    }
    
    // Update count
    countEl.textContent = `${chatFilesArray.length} file${chatFilesArray.length > 1 ? 's' : ''} selected (max ${MAX_CHAT_FILES})`;
    
    // Update placeholder
    if (chatFilesArray.length === 1) {
        const type = chatFilesArray[0].type;
        document.getElementById('chatInput').placeholder = type === 'image' ? 'Ask about this image...' : 'Ask about this file...';
    } else {
        document.getElementById('chatInput').placeholder = `Ask about these ${chatFilesArray.length} files...`;
    }
    
    // Build preview HTML
    container.innerHTML = chatFilesArray.map(fileData => {
        if (fileData.type === 'image') {
            return `
                <div class="relative group" id="preview-${fileData.id}">
                    <img src="${fileData.base64}" alt="${escapeHtml(fileData.file.name)}" 
                         class="w-14 h-14 object-cover rounded-lg border-2 border-gray-300 dark:border-gray-600 cursor-pointer hover:opacity-80 transition-opacity"
                         onclick="openChatImageModal('preview-img-${fileData.id}')"
                         id="preview-img-${fileData.id}"
                         title="${escapeHtml(fileData.file.name)}">
                    <button onclick="removeChatFileById('${fileData.id}')" 
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 transition-colors opacity-0 group-hover:opacity-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        } else {
            return `
                <div class="relative group" id="preview-${fileData.id}">
                    <div class="w-14 h-14 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 flex flex-col items-center justify-center p-1"
                         title="${escapeHtml(fileData.file.name)}">
                        <i class="fas ${fileData.icon} text-lg ${fileData.color}"></i>
                        <span class="text-[8px] text-gray-500 dark:text-gray-400 truncate w-full text-center mt-0.5">${escapeHtml(fileData.file.name.slice(0, 8))}${fileData.file.name.length > 8 ? '...' : ''}</span>
                    </div>
                    <button onclick="removeChatFileById('${fileData.id}')" 
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 transition-colors opacity-0 group-hover:opacity-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
    }).join('');
    
    preview.classList.remove('hidden');
}

// Remove a specific file by ID
function removeChatFileById(fileId) {
    chatFilesArray = chatFilesArray.filter(f => f.id !== fileId);
    updateFilesPreview();
}

// Clear all files
function clearAllChatFiles() {
    chatFilesArray = [];
    document.getElementById('chatFileInput').value = '';
    updateFilesPreview();
}

// Remove selected file (legacy - clears all)
function removeChatFile() {
    clearAllChatFiles();
}

// Legacy function for compatibility
function removeChatImage() {
    removeChatFile();
}

// Drag and drop handlers
function handleChatDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Check if dragging files
    if (event.dataTransfer.types.includes('Files')) {
        document.getElementById('chatDropOverlay').classList.remove('hidden');
    }
}

function handleChatDragLeave(event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Only hide if leaving the chat area entirely
    const chatPanel = document.getElementById('chatbotPanel');
    const rect = chatPanel.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || 
        event.clientY < rect.top || event.clientY > rect.bottom) {
        document.getElementById('chatDropOverlay').classList.add('hidden');
    }
}

function handleChatDrop(event) {
    event.preventDefault();
    event.stopPropagation();
    
    document.getElementById('chatDropOverlay').classList.add('hidden');
    
    const files = Array.from(event.dataTransfer.files);
    if (files.length > 0) {
        addFilesToChat(files);
    }
}

// Add image message to chat
// Add file (image or document) to chat
function addFileToChat(fileData, message) {
    const chatMessages = document.getElementById('chatMessages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex gap-3 chat-bubble flex-row-reverse';
    
    let filePreviewHtml = '';
    if (fileData.type === 'image') {
        // Make image clickable to open in modal
        const imageId = 'chat-img-' + Date.now();
        filePreviewHtml = `<img id="${imageId}" src="${fileData.base64}" alt="Uploaded image" class="max-w-full rounded-lg mb-2 max-h-48 object-contain cursor-pointer hover:opacity-90 transition-opacity chat-image-preview" onclick="openChatImageModal('${imageId}')" title="Click to view full size">`;
    } else {
        // Document/file preview
        filePreviewHtml = `
            <div class="flex items-center gap-3 bg-white/10 rounded-lg p-3 mb-2">
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <i class="fas ${fileData.icon} text-lg text-white"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">${escapeHtml(fileData.file.name)}</p>
                    <p class="text-xs text-white/70">${formatFileSize(fileData.file.size)}</p>
                </div>
            </div>
        `;
    }
    
    const defaultMessage = fileData.type === 'image' ? 'Analyze this image' : 'Analyze this file';
    
    messageDiv.innerHTML = `
        <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
            <i class="fas fa-user text-white text-sm"></i>
        </div>
        <div class="chatbot-user-bubble text-white rounded-2xl rounded-tr-md px-4 py-3 max-w-[85%]">
            ${filePreviewHtml}
            ${message ? `<p class="text-sm">${escapeHtml(message)}</p>` : `<p class="text-sm text-white/80 italic">${defaultMessage}</p>`}
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Open image in modal for full size view
function openChatImageModal(imageId) {
    const img = document.getElementById(imageId);
    if (!img) return;
    
    const modal = document.getElementById('chatImageModal');
    const modalImg = document.getElementById('chatImageModalImg');
    const downloadBtn = document.getElementById('chatImageDownloadBtn');
    
    modalImg.src = img.src;
    downloadBtn.href = img.src;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close image modal
function closeChatImageModal(event) {
    // Only close if clicking the backdrop or close button, not the image
    if (event && event.target.tagName === 'IMG') return;
    
    const modal = document.getElementById('chatImageModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('chatImageModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeChatImageModal();
        }
    }
});

// Legacy function for compatibility
function addImageToChat(imageBase64, message) {
    addFileToChat({
        base64: imageBase64,
        type: 'image',
        file: { name: 'image', size: 0 },
        icon: 'fa-file-image'
    }, message);
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    const hasFiles = chatFilesArray.length > 0;
    
    if (!message && !hasFiles) return;
    
    // Reset textarea height after sending
    input.style.height = 'auto';
    
    // Note: We no longer block free hosting here. The backend now has a fallback mode
    // that generates responses from board data when the AI API is unavailable.
    
    // Store files data before clearing
    const filesToSend = [...chatFilesArray];
    
    // Clear input and files
    input.value = '';
    
    // Add user message to chat (with files if present)
    if (hasFiles) {
        addMultipleFilesToChat(filesToSend, message);
        clearAllChatFiles();
    } else {
        addMessageToChat(message, 'user');
    }
    
    // Show typing indicator
    showTypingIndicator();
    
    try {
        // Determine default message based on files
        let defaultMessage = 'Analyze these files and provide a summary.';
        if (filesToSend.length === 1) {
            defaultMessage = filesToSend[0].type === 'image' 
                ? 'What do you see in this image? Describe it in detail.' 
                : 'Analyze this file and provide a summary.';
        }
        
        // Prepare request body
        const requestBody = {
            board_id: currentBoardIdForChat,
            message: message || defaultMessage
        };
        
        // Add files data if present
        if (filesToSend.length > 0) {
            requestBody.files = filesToSend.map(fileData => ({
                data: fileData.base64.split(',')[1],
                mime_type: fileData.mimeType,
                type: fileData.type,
                name: fileData.file.name
            }));
        }
        
        const response = await fetch(window.BASE_PATH + '/actions/ai/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });
        
        // Check if response is HTML (403 error page)
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            hideTypingIndicator();
            addMessageToChat('⚠️ AI Chatbot is not available on this hosting. The server is blocking API requests. Please use localhost or a different hosting provider.', 'ai', null, true);
            return;
        }
        
        const data = await response.json();
        
        // Hide typing indicator
        hideTypingIndicator();
        
        if (data.success) {
            // Add AI response (works for both AI API and fallback responses)
            if (data.has_table && data.table_html) {
                addMessageToChat(data.response, 'ai', data.table_html);
            } else {
                addMessageToChat(data.response, 'ai');
            }
            
            // Show a subtle indicator if running in fallback mode
            if (data.fallback) {
                console.log('AI Assistant: Running in offline/fallback mode');
            }
        } else {
            // Format error message nicely
            let errorMsg = data.message || 'Sorry, I encountered an error. Please try again.';
            addMessageToChat(errorMsg, 'ai', null, true);
        }
    } catch (error) {
        hideTypingIndicator();
        console.error('Chat error:', error);
        
        // Provide more helpful error message based on error type
        let errorMessage = '⚠️ Unable to connect to the AI service.';
        if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            errorMessage = '⚠️ Cannot reach the server. Make sure XAMPP/Apache is running.';
        } else if (error.name === 'SyntaxError') {
            errorMessage = '⚠️ Server returned an invalid response. Check PHP error logs.';
        } else if (error.message) {
            errorMessage = '⚠️ Error: ' + error.message;
        }
        
        addMessageToChat(errorMessage, 'ai', null, true);
    }
}

// Add multiple files to chat display
function addMultipleFilesToChat(filesArray, message) {
    const chatMessages = document.getElementById('chatMessages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex gap-3 chat-bubble flex-row-reverse';
    
    // Build files preview HTML
    let filesPreviewHtml = '';
    if (filesArray.length === 1) {
        // Single file - use existing style
        const fileData = filesArray[0];
        if (fileData.type === 'image') {
            const imageId = 'chat-img-' + Date.now();
            filesPreviewHtml = `<img id="${imageId}" src="${fileData.base64}" alt="Uploaded image" class="max-w-full rounded-lg mb-2 max-h-48 object-contain cursor-pointer hover:opacity-90 transition-opacity chat-image-preview" onclick="openChatImageModal('${imageId}')" title="Click to view full size">`;
        } else {
            filesPreviewHtml = `
                <div class="flex items-center gap-3 bg-white/10 rounded-lg p-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                        <i class="fas ${fileData.icon} text-lg text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">${escapeHtml(fileData.file.name)}</p>
                        <p class="text-xs text-white/70">${formatFileSize(fileData.file.size)}</p>
                    </div>
                </div>
            `;
        }
    } else {
        // Multiple files - grid layout
        filesPreviewHtml = `<div class="flex flex-wrap gap-2 mb-2">`;
        filesArray.forEach((fileData, index) => {
            if (fileData.type === 'image') {
                const imageId = 'chat-img-' + Date.now() + '-' + index;
                filesPreviewHtml += `
                    <img id="${imageId}" src="${fileData.base64}" alt="${escapeHtml(fileData.file.name)}" 
                         class="w-16 h-16 object-cover rounded-lg cursor-pointer hover:opacity-90 transition-opacity border border-white/30"
                         onclick="openChatImageModal('${imageId}')" title="${escapeHtml(fileData.file.name)}">
                `;
            } else {
                filesPreviewHtml += `
                    <div class="w-16 h-16 rounded-lg bg-white/10 flex flex-col items-center justify-center p-1 border border-white/30" title="${escapeHtml(fileData.file.name)}">
                        <i class="fas ${fileData.icon} text-lg text-white"></i>
                        <span class="text-[8px] text-white/70 truncate w-full text-center mt-0.5">${escapeHtml(fileData.file.name.slice(0, 6))}${fileData.file.name.length > 6 ? '..' : ''}</span>
                    </div>
                `;
            }
        });
        filesPreviewHtml += `</div>`;
    }
    
    const defaultMessage = filesArray.length === 1 
        ? (filesArray[0].type === 'image' ? 'Analyze this image' : 'Analyze this file')
        : `Analyze these ${filesArray.length} files`;
    
    messageDiv.innerHTML = `
        <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
            <i class="fas fa-user text-white text-sm"></i>
        </div>
        <div class="chatbot-user-bubble text-white rounded-2xl rounded-tr-md px-4 py-3 max-w-[85%]">
            ${filesPreviewHtml}
            ${message ? `<p class="text-sm">${escapeHtml(message)}</p>` : `<p class="text-sm text-white/80 italic">${defaultMessage}</p>`}
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function addMessageToChat(message, sender, tableHtml = null, isError = false, isFromHistory = false) {
    const chatMessages = document.getElementById('chatMessages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex gap-3 chat-bubble';
    
    if (sender === 'user') {
        messageDiv.classList.add('flex-row-reverse');
        messageDiv.innerHTML = `
            <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div class="chatbot-user-bubble text-white rounded-2xl rounded-tr-md px-4 py-3 max-w-[85%]">
                <p class="text-sm">${escapeHtml(message)}</p>
            </div>
        `;
    } else {
        const bgColor = isError ? 'bg-red-50 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700';
        const textColor = isError ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-200';
        
        // Format the message with markdown parsing
        let displayMessage = message;
        let displayTable = tableHtml;
        
        if (isFromHistory && !tableHtml) {
            // Check if history message contains a markdown table
            const formattedResult = formatHistoryMessage(message);
            displayMessage = formattedResult.text;
            displayTable = formattedResult.tableHtml;
        } else if (!isFromHistory) {
            // For live messages, also parse markdown (may already have some HTML like <br>)
            displayMessage = parseMarkdownToHtml(message);
        }
        
        let content = `<div class="text-sm ${textColor} ai-message-content">${displayMessage}</div>`;
        if (displayTable) {
            content += `<div class="mt-2 overflow-x-auto">${displayTable}</div>`;
        }
        
        messageDiv.innerHTML = `
            <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-white text-sm"></i>
            </div>
            <div class="${bgColor} rounded-2xl rounded-tl-md px-4 py-3 max-w-[85%]">
                ${content}
            </div>
        `;
    }
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Parse markdown to HTML for AI chat messages
function parseMarkdownToHtml(text) {
    if (!text) return text;
    
    let result = text;
    
    // First, unescape any HTML entities that were double-escaped
    result = result.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
    
    // Handle code blocks (```)
    result = result.replace(/```(\w*)\n?([\s\S]*?)```/g, '<pre class="bg-gray-800 text-gray-100 p-3 rounded-lg my-2 text-xs overflow-x-auto"><code>$2</code></pre>');
    
    // Handle inline code (`)
    result = result.replace(/`([^`]+)`/g, '<code class="bg-gray-200 dark:bg-gray-600 px-1 py-0.5 rounded text-sm">$1</code>');
    
    // Handle headers (## Header)
    result = result.replace(/^### (.*?)$/gm, '<h4 class="font-bold text-base mt-3 mb-1">$1</h4>');
    result = result.replace(/^## (.*?)$/gm, '<h3 class="font-bold text-lg mt-3 mb-1">$1</h3>');
    result = result.replace(/^# (.*?)$/gm, '<h2 class="font-bold text-xl mt-3 mb-1">$1</h2>');
    
    // Handle bold (**text** or __text__)
    result = result.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    result = result.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    
    // Handle italic (*text* or _text_) - but not when part of bold
    result = result.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>');
    result = result.replace(/(?<!_)_([^_]+)_(?!_)/g, '<em>$1</em>');
    
    // Handle bullet points (- item or * item)
    result = result.replace(/^[\-\*] (.*?)$/gm, '<li class="ml-4">$1</li>');
    // Wrap consecutive li elements in ul
    result = result.replace(/(<li[^>]*>.*?<\/li>\n?)+/g, '<ul class="list-disc list-inside my-2">$&</ul>');
    
    // Handle numbered lists (1. item)
    result = result.replace(/^\d+\. (.*?)$/gm, '<li class="ml-4">$1</li>');
    
    // Handle horizontal rules (---)
    result = result.replace(/^---$/gm, '<hr class="my-3 border-gray-300 dark:border-gray-600">');
    
    // Handle line breaks - but preserve existing <br> tags
    result = result.replace(/(?<!>)\n(?!<)/g, '<br>');
    
    return result;
}

// Format history message (convert markdown to HTML)
function formatHistoryMessage(text) {
    let tableHtml = null;
    let summaryText = text;
    
    // Check for markdown table
    if (text.includes('|') && text.includes('\n|')) {
        const lines = text.split('\n');
        let inTable = false;
        let tableLines = [];
        let textLines = [];
        
        for (const line of lines) {
            const trimmed = line.trim();
            if (/^\|.*\|$/.test(trimmed)) {
                // Skip separator row
                if (/^\|[-:| ]+\|$/.test(trimmed)) continue;
                inTable = true;
                tableLines.push(trimmed);
            } else {
                if (inTable && trimmed === '') continue; // Skip empty lines after table
                inTable = false;
                if (trimmed) textLines.push(trimmed);
            }
        }
        
        if (tableLines.length > 0) {
            tableHtml = '<table class="ai-table">';
            tableLines.forEach((line, index) => {
                const cells = line.split('|').filter(c => c.trim()).map(c => c.trim());
                if (index === 0) {
                    tableHtml += '<thead><tr>' + cells.map(c => `<th>${parseMarkdownToHtml(c)}</th>`).join('') + '</tr></thead><tbody>';
                } else {
                    tableHtml += '<tr>' + cells.map(c => `<td>${parseMarkdownToHtml(c)}</td>`).join('') + '</tr>';
                }
            });
            tableHtml += '</tbody></table>';
        }
        
        summaryText = textLines.join('\n');
    }
    
    // Apply markdown parsing
    summaryText = parseMarkdownToHtml(summaryText);
    
    return { text: summaryText, tableHtml };
}

function showTypingIndicator() {
    const chatMessages = document.getElementById('chatMessages');
    
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typingIndicator';
    typingDiv.className = 'flex gap-3 chat-bubble';
    typingDiv.innerHTML = `
        <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
            <i class="fas fa-robot text-white text-sm"></i>
        </div>
        <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-md">
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    `;
    
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

async function clearChat() {
    const chatMessages = document.getElementById('chatMessages');
    
    // Clear from database
    try {
        await fetch(`${window.BASE_PATH}/actions/ai/chat.php?board_id=${currentBoardIdForChat}&action=clear`);
    } catch (error) {
        console.error('Error clearing chat history:', error);
    }
    
    // Reset UI
    chatMessages.innerHTML = `
        <div class="flex gap-3">
            <div class="chatbot-avatar w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-robot text-white text-sm"></i>
            </div>
            <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-md px-4 py-3 max-w-[85%]">
                <p class="text-sm text-gray-700 dark:text-gray-200">
                    Hi! 👋 I'm your Planify Assistant. Ask me anything about this board - tasks, assignees, due dates, or get a quick summary!
                </p>
            </div>
        </div>
    `;
    
    // Mark history as needing reload next time
    chatHistoryLoaded = true; // Keep true since we just cleared
}

// Close chatbot with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isChatbotOpen) {
        toggleChatbot();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
