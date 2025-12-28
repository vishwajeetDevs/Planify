<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../helpers/IdEncrypt.php';

// Require login before anything else
requireLogin();

// Validate user exists
$user = getCurrentUser($conn, $_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get workspace ID (supports both encrypted 'ref' and plain 'id' for backward compatibility)
$workspaceId = getDecryptedId('ref');
if ($workspaceId === false) {
    showInvalidAccessError('Invalid or unauthorized access to workspace.');
}

$pageTitle = 'Workspace - Planify';
require_once '../includes/header.php';
require_once '../includes/skeleton.php';

// Get workspace details
$stmt = $conn->prepare("
    SELECT w.*, u.name as owner_name
    FROM workspaces w
    INNER JOIN users u ON w.owner_id = u.id
    WHERE w.id = ?
");
$stmt->bind_param("i", $workspaceId);
$stmt->execute();
$workspace = $stmt->get_result()->fetch_assoc();

if (!$workspace) {
    header('Location: dashboard.php');
    exit;
}

// Check if user has access to this workspace
// User has access if they own the workspace OR have access to at least one board in it
if (!hasAccessToWorkspace($conn, $_SESSION['user_id'], $workspaceId)) {
    header('Location: dashboard.php');
    exit;
}

// Get only boards the user has access to in this workspace
$boards = getUserAccessibleBoardsInWorkspace($conn, $_SESSION['user_id'], $workspaceId);
?>

<div class="min-h-screen bg-slate-50/80 dark:bg-gray-900/95">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-6 animate-fade-in-up" x-data="{ modalOpen: false }">
        
        <!-- Header -->
        <div class="mb-6 border-b-2 border-dashed border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/70 backdrop-blur-xl transition-all duration-300 rounded-lg shadow-sm hover:shadow-md animate-fade-in-down" style="animation-delay: 0.1s;">
            <div class="p-5 sm:p-6">
                <!-- Breadcrumb -->
                <div class="flex items-center text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-3">
                    <a href="dashboard.php" class="font-medium text-primary hover:underline transition-colors duration-200 hover:text-primary-dark">
                        <i class="fas fa-home mr-1"></i>Dashboard
                    </a>
                    <span class="mx-2 text-gray-400">/</span>
                    <span class="text-gray-700 dark:text-gray-300 font-medium"><?php echo e($workspace['name']); ?></span>
                </div>
                
                <!-- Title + Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="animate-fade-in-up" style="animation-delay: 0.15s;">
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                            <?php echo e($workspace['name']); ?>
                        </h1>
                        <?php if ($workspace['description']): ?>
                        <p class="text-gray-600 dark:text-gray-400 mt-1"><?php echo e($workspace['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <button 
                        onclick="showCreateBoardModal()"
                        class="group inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary-dark shadow-lg shadow-primary/30 hover:shadow-xl hover:shadow-primary/40 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200"
                    >
                        <i class="fas fa-plus mr-2 text-xs transition-transform duration-200 group-hover:rotate-90"></i>Create Board
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Boards Grid -->
        <?php if (empty($boards)): ?>
        <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-lg p-12 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700 text-center transition-all duration-300 hover:border-primary dark:hover:border-primary animate-fade-in-up" style="animation-delay: 0.2s;">
            <i class="fas fa-clipboard text-5xl text-gray-400 mb-4 animate-bounce-subtle"></i>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No boards yet</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Create your first board to start organizing your tasks</p>
            <button 
                onclick="showCreateBoardModal()"
                class="group inline-flex items-center px-5 py-3 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary-dark shadow-lg shadow-primary/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200"
            >
                <i class="fas fa-plus mr-2 transition-transform duration-200 group-hover:rotate-90"></i>Create Board
            </button>
        </div>
        <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php 
            $index = 0;
            foreach ($boards as $board): 
                $delay = 0.2 + ($index * 0.05);
                $index++;
            ?>
            <div class="group relative animate-fade-in-up" style="animation-delay: <?php echo $delay; ?>s;"
                 x-data="{ showMenu: false, boardId: <?php echo $board['id']; ?>, hovered: false }">
                <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-lg rounded-lg border border-dashed transition-all duration-300 p-5 h-40 flex flex-col hover:shadow-xl hover:-translate-y-1 overflow-hidden"
                     @mouseenter="hovered = true"
                     @mouseleave="hovered = false"
                     :style="'border-top: 4px solid <?php echo $board['background_color'] ?? '#4F46E5'; ?>; border-top-style: solid; border-color: ' + (hovered ? '<?php echo $board['background_color'] ?? '#4F46E5'; ?>' : '') + '; --board-color: <?php echo $board['background_color'] ?? '#4F46E5'; ?>;'"
                     :class="hovered ? '' : 'border-gray-200 dark:border-gray-700'">
                    <!-- Three-dot menu button -->
                    <div class="absolute top-3 right-3 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <button 
                            @click.stop="showMenu = !showMenu"
                            class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:scale-110"
                            @click.away="showMenu = false"
                            title="Board options">
                            <i class="fas fa-ellipsis-h w-4 h-4"></i>
                        </button>
                        <!-- Dropdown menu -->
                        <div 
                            x-show="showMenu"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-xl py-1 z-50 border border-gray-200 dark:border-gray-700 overflow-hidden"
                        >
                            <button 
                                class="w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-gray-700 transition-all duration-150 hover:pl-5"
                                @click="showMenu = false; $dispatch('open-edit-board', { 
                                    id: <?php echo $board['id']; ?>, 
                                    name: '<?php echo addslashes($board['name']); ?>', 
                                    description: '<?php echo isset($board['description']) ? addslashes($board['description']) : ''; ?>',
                                    background_color: '<?php echo $board['background_color'] ?? '#4F46E5'; ?>'
                                })"
                            >
                                <i class="far fa-edit mr-2 w-4"></i> Edit
                            </button>
                            <button 
                                class="w-full text-left flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-all duration-150 hover:pl-5"
                                @click="showMenu = false; $dispatch('delete-board', { id: <?php echo $board['id']; ?> })"
                            >
                                <i class="far fa-trash-alt mr-2 w-4"></i> Delete
                            </button>
                        </div>
                    </div>
                    
                    <a href="<?php echo encryptedUrl('board.php', $board['id']); ?>" class="flex-1 flex flex-col">
                        <div class="flex-1">
                            <h3 class="font-medium text-sm sm:text-base text-gray-900 dark:text-gray-100 leading-tight group-hover:text-primary line-clamp-2 pr-6 transition-colors duration-200">
                                <?php echo e($board['name']); ?>
                            </h3>
                            <?php if ($board['description']): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 line-clamp-2">
                                <?php echo e($board['description']); ?>
                            </p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center">
                                <i class="fas fa-list-ul mr-1.5 text-gray-400"></i>
                                <?php echo $board['list_count']; ?> list<?php echo $board['list_count'] != 1 ? 's' : ''; ?>
                            </p>
                        </div>
                        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-[11px] text-gray-400 flex items-center">
                                <i class="far fa-clock mr-1.5"></i>
                                Created <?php echo timeAgo($board['created_at']); ?>
                            </span>
                        </div>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Create Board Modal -->
    <div id="createBoardModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-opacity duration-300" onclick="if(event.target === this) hideCreateBoardModal()">
        <div id="createBoardModalContent" class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-lg rounded-xl border border-gray-200 dark:border-gray-700 p-6 w-full max-w-md mx-4 shadow-2xl transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Create Board</h3>
                <button type="button" onclick="hideCreateBoardModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:rotate-90">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="createBoardForm" onsubmit="return createBoard(event)">
                <input type="hidden" name="workspace_id" value="<?php echo $workspaceId; ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Board Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        required 
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:shadow-lg"
                        placeholder="e.g., Product Roadmap"
                    >
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description (Optional)</label>
                    <textarea 
                        name="description" 
                        rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:shadow-lg resize-none"
                        placeholder="What is this board for?"
                    ></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Color</label>
                    <div class="grid grid-cols-5 gap-2">
                        <?php
                        $colors = [
                            ['#4F46E5', 'Indigo'], ['#3B82F6', 'Blue'], ['#10B981', 'Green'], ['#8B5CF6', 'Purple'], ['#EC4899', 'Pink'],
                            ['#EF4444', 'Red'], ['#F97316', 'Orange'], ['#F59E0B', 'Amber'], ['#14B8A6', 'Teal'], ['#06B6D4', 'Cyan'],
                            ['#0EA5E9', 'Sky'], ['#6366F1', 'Violet'], ['#D946EF', 'Fuchsia'], ['#F43F5E', 'Rose'], ['#6B7280', 'Gray']
                        ];
                        foreach ($colors as $i => $color):
                        ?>
                        <label class="block cursor-pointer" title="<?php echo $color[1]; ?>">
                            <input type="radio" name="background_color" value="<?php echo $color[0]; ?>" class="sr-only peer" <?php echo $i === 0 ? 'checked' : ''; ?>>
                            <div class="w-full h-10 rounded-lg transition-all duration-200 peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-indigo-500 hover:scale-105 hover:shadow-md" style="background-color: <?php echo $color[0]; ?>;"></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button 
                        type="button" 
                        onclick="hideCreateBoardModal()" 
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 transition-all duration-200 hover:shadow-md"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        id="createBoardSubmitBtn"
                        class="px-4 py-2.5 text-sm font-medium text-white bg-primary hover:bg-primary-dark rounded-lg shadow-md shadow-primary/30 hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0"
                    >
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Board Modal -->
    <div id="editBoardModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-opacity duration-300" onclick="if(event.target === this) hideEditBoardModal()">
        <div id="editBoardModalContent" class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-lg rounded-xl border border-gray-200 dark:border-gray-700 p-6 w-full max-w-md mx-4 shadow-2xl transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Edit Board</h3>
                <button type="button" onclick="hideEditBoardModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:rotate-90">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="editBoardForm" onsubmit="return updateBoard(event)">
                <input type="hidden" name="board_id" id="editBoardId">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Board Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        id="editBoardName"
                        required 
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:shadow-lg"
                        placeholder="e.g., Product Roadmap"
                    >
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description (Optional)</label>
                    <textarea 
                        name="description" 
                        id="editBoardDescription"
                        rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:shadow-lg resize-none"
                        placeholder="What is this board for?"
                    ></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Color</label>
                    <div class="grid grid-cols-5 gap-2" id="editColorOptions">
                        <!-- Color options will be populated by JavaScript -->
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button 
                        type="button" 
                        onclick="hideEditBoardModal()" 
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 transition-all duration-200 hover:shadow-md"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        id="editBoardSubmitBtn"
                        class="px-4 py-2.5 text-sm font-medium text-white bg-primary hover:bg-primary-dark rounded-lg shadow-md shadow-primary/30 hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteBoardModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-opacity duration-300" onclick="if(event.target === this) hideDeleteBoardModal()">
        <div id="deleteBoardModalContent" class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-lg rounded-xl border border-gray-200 dark:border-gray-700 p-6 w-full max-w-md mx-4 shadow-2xl transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    Delete Board
                </h3>
                <button type="button" onclick="hideDeleteBoardModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:rotate-90">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <p class="text-gray-600 dark:text-gray-300 mb-6">
                Are you sure you want to delete this board? This action cannot be undone and all data will be permanently removed.
            </p>
            
            <div class="flex justify-end space-x-3">
                <button 
                    type="button" 
                    onclick="hideDeleteBoardModal()" 
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 transition-all duration-200 hover:shadow-md"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    onclick="deleteBoard()"
                    id="deleteBoardBtn"
                    class="px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 rounded-lg shadow-md shadow-red-500/30 hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0"
                >
                    <i class="fas fa-trash-alt mr-2"></i>Delete Board
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Animated modal functions
function showModal(modalId, contentId) {
    const modal = document.getElementById(modalId);
    const content = document.getElementById(contentId);
    if (modal && content) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            modal.style.opacity = '1';
            content.style.transform = 'scale(1)';
            content.style.opacity = '1';
        });
    }
}

function hideModal(modalId, contentId) {
    const modal = document.getElementById(modalId);
    const content = document.getElementById(contentId);
    if (modal && content) {
        content.style.transform = 'scale(0.95)';
        content.style.opacity = '0';
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.style.opacity = '';
            content.style.transform = '';
            content.style.opacity = '';
            document.body.style.overflow = '';
        }, 200);
    }
}

// Show create board modal
function showCreateBoardModal() {
    showModal('createBoardModal', 'createBoardModalContent');
    setTimeout(() => {
        const input = document.querySelector('#createBoardForm input[name="name"]');
        if (input) input.focus();
    }, 200);
}

// Hide create board modal
function hideCreateBoardModal() {
    hideModal('createBoardModal', 'createBoardModalContent');
}

// Helper function to escape HTML
function escapeHtmlLocal(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add board to grid without page reload
function addBoardToGrid(board) {
    let grid = document.querySelector('.grid.md\\:grid-cols-2');
    const pageContainer = document.querySelector('.w-full.px-4');
    
    // If no grid exists (empty state), create it and replace empty state
    if (!grid) {
        const emptyState = document.querySelector('.bg-white\\/90.dark\\:bg-gray-900\\/80.backdrop-blur-lg.p-12');
        if (emptyState && pageContainer) {
            // Create the grid container
            grid = document.createElement('div');
            grid.className = 'grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4';
            
            // Replace empty state with grid
            emptyState.replaceWith(grid);
        }
    }
    
    if (!grid) {
        // Fallback: reload the page
        setTimeout(() => window.location.reload(), 500);
        return;
    }
    
    const safeName = escapeHtmlLocal(board.name);
    const safeDesc = escapeHtmlLocal(board.description || '');
    const boardColor = board.background_color || '#4F46E5';
    const boardRef = board.ref || board.id;
    
    const boardCard = document.createElement('div');
    boardCard.className = 'group relative animate-fade-in-up';
    boardCard.setAttribute('x-data', `{ showMenu: false, boardId: ${board.id}, hovered: false }`);
    
    boardCard.innerHTML = `
        <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-lg rounded-lg border border-dashed border-gray-200 dark:border-gray-700 transition-all duration-300 p-5 h-40 flex flex-col hover:shadow-xl hover:-translate-y-1 overflow-hidden"
             @mouseenter="hovered = true"
             @mouseleave="hovered = false"
             style="border-top: 4px solid ${boardColor}; border-top-style: solid;">
            <!-- Three-dot menu button -->
            <div class="absolute top-3 right-3 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <button 
                    @click.stop="showMenu = !showMenu"
                    class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:scale-110"
                    @click.away="showMenu = false"
                    title="Board options">
                    <i class="fas fa-ellipsis-h w-4 h-4"></i>
                </button>
                <!-- Dropdown menu -->
                <div 
                    x-show="showMenu"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-xl py-1 z-50 border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <button 
                        class="w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-gray-700 transition-all duration-150 hover:pl-5"
                        @click="showMenu = false; $dispatch('open-edit-board', { 
                            id: ${board.id}, 
                            name: '${safeName.replace(/'/g, "\\'")}', 
                            description: '${safeDesc.replace(/'/g, "\\'")}',
                            background_color: '${boardColor}'
                        })"
                    >
                        <i class="far fa-edit mr-2 w-4"></i> Edit
                    </button>
                    <button 
                        class="w-full text-left flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-all duration-150 hover:pl-5"
                        @click="showMenu = false; $dispatch('delete-board', { id: ${board.id} })"
                    >
                        <i class="far fa-trash-alt mr-2 w-4"></i> Delete
                    </button>
                </div>
            </div>
            
            <a href="${window.BASE_PATH}/public/board.php?ref=${boardRef}" class="flex-1 flex flex-col">
                <div class="flex-1">
                    <h3 class="font-medium text-sm sm:text-base text-gray-900 dark:text-gray-100 leading-tight group-hover:text-primary line-clamp-2 pr-6 transition-colors duration-200">
                        ${safeName}
                    </h3>
                    ${safeDesc ? `<p class="text-sm text-gray-500 dark:text-gray-400 mt-2 line-clamp-2">${safeDesc}</p>` : ''}
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center">
                        <i class="fas fa-list-ul mr-1.5 text-gray-400"></i>
                        ${board.list_count || 0} list${(board.list_count || 0) !== 1 ? 's' : ''}
                    </p>
                </div>
                <div class="mt-auto pt-2 border-t border-gray-100 dark:border-gray-700">
                    <span class="text-[11px] text-gray-400 flex items-center">
                        <i class="far fa-clock mr-1.5"></i>
                        Created 0 min ago
                    </span>
            </div>
        </a>
        </div>
    `;
    
    grid.appendChild(boardCard);
    
    // Re-initialize Alpine.js for the new element
    if (window.Alpine) {
        Alpine.initTree(boardCard);
    }
    
    // Animate in
    boardCard.style.opacity = '0';
    boardCard.style.transform = 'translateY(10px)';
    requestAnimationFrame(() => {
        boardCard.style.transition = 'all 0.3s ease-out';
        boardCard.style.opacity = '1';
        boardCard.style.transform = 'translateY(0)';
    });
}

// Update existing board in grid
function updateBoardInGrid(board) {
    const boardCard = document.querySelector(`[x-data*="boardId: ${board.id}"]`);
    if (!boardCard) return;
    
    const titleEl = boardCard.querySelector('h3');
    if (titleEl) titleEl.textContent = board.name;
    
    const descEl = boardCard.querySelector('p');
    if (descEl) descEl.textContent = board.description || 'No description';
    
    const colorDiv = boardCard.querySelector('.h-20');
    if (colorDiv && board.background_color) {
        colorDiv.style.background = board.background_color;
    }
}

// Show empty state when no boards
function showEmptyBoardsState() {
    const container = document.querySelector('.grid')?.parentElement;
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-12 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            <i class="fas fa-th-large text-4xl text-gray-400 mb-3"></i>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No boards yet</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">Create your first board to get started</p>
            <button onclick="showCreateBoardModal()" 
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary-dark transition-all">
                <i class="fas fa-plus mr-2"></i> Create Board
            </button>
        </div>
    `;
}

// Handle board creation
function createBoard(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitButton = document.getElementById('createBoardSubmitBtn');
    const originalButtonText = submitButton.innerHTML;
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Creating...
    `;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    fetch('../actions/board/create.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin'
    })
    .then(async response => {
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || 'Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (!data || data.success === false) {
            throw new Error(data?.message || 'Failed to create board');
        }
        
        showToast('Board created successfully!', 'success');
        hideCreateBoardModal();
        form.reset();
        
        // Add board to grid without page reload
        if (data.board) {
            addBoardToGrid(data.board);
        } else {
            // Fallback: reload if no board data returned
            setTimeout(() => window.location.reload(), 500);
        }
    })
    .catch(err => {
        console.error('Error creating board:', err);
        showToast(err.message || 'An error occurred while creating the board', 'error');
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Create';
    });
    
    return false;
}

// Show edit board modal
function showEditBoardModal(boardData) {
    document.getElementById('editBoardId').value = boardData.id;
    document.getElementById('editBoardName').value = boardData.name;
    document.getElementById('editBoardDescription').value = boardData.description || '';
    
    // Populate color options
    const colorOptions = document.getElementById('editColorOptions');
    const colors = [
        ['#4F46E5', 'Indigo'], ['#3B82F6', 'Blue'], ['#10B981', 'Green'], ['#8B5CF6', 'Purple'], ['#EC4899', 'Pink'],
        ['#EF4444', 'Red'], ['#F97316', 'Orange'], ['#F59E0B', 'Amber'], ['#14B8A6', 'Teal'], ['#06B6D4', 'Cyan'],
        ['#0EA5E9', 'Sky'], ['#6366F1', 'Violet'], ['#D946EF', 'Fuchsia'], ['#F43F5E', 'Rose'], ['#6B7280', 'Gray']
    ];
    
    colorOptions.innerHTML = colors.map(([color, name]) => `
        <label class="block cursor-pointer" title="${name}">
            <input type="radio" name="background_color" value="${color}" 
                   class="sr-only peer" ${boardData.background_color === color ? 'checked' : ''}>
            <div class="w-full h-10 rounded-lg transition-all duration-200 peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-indigo-500 hover:scale-105 hover:shadow-md" 
                 style="background-color: ${color};"></div>
        </label>
    `).join('');
    
    showModal('editBoardModal', 'editBoardModalContent');
    setTimeout(() => document.getElementById('editBoardName').focus(), 200);
}

// Hide edit board modal
function hideEditBoardModal() {
    hideModal('editBoardModal', 'editBoardModalContent');
}

// Show delete confirmation modal
function confirmDeleteBoard(boardId) {
    const modal = document.getElementById('deleteBoardModal');
    modal.setAttribute('data-board-id', boardId);
    showModal('deleteBoardModal', 'deleteBoardModalContent');
}

// Hide delete confirmation modal
function hideDeleteBoardModal() {
    hideModal('deleteBoardModal', 'deleteBoardModalContent');
}

// Handle board update
function updateBoard(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitButton = document.getElementById('editBoardSubmitBtn');
    const originalButtonText = submitButton.innerHTML;
    
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Saving...
    `;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    fetch('../actions/board/update.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin'
    })
    .then(async response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (!data || data.success === false) {
            throw new Error(data?.message || 'Failed to update board');
        }
        
        showToast('Board updated successfully!', 'success');
        hideEditBoardModal();
        
        // Update board in grid without page reload
        if (data.board) {
            updateBoardInGrid(data.board);
        } else {
            setTimeout(() => window.location.reload(), 500);
        }
    })
    .catch(err => {
        console.error('Error updating board:', err);
        showToast(err.message || 'An error occurred while updating the board', 'error');
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Save Changes';
    });
    
    return false;
}

// Handle board deletion
function deleteBoard() {
    const modal = document.getElementById('deleteBoardModal');
    const boardId = modal.getAttribute('data-board-id');
    if (!boardId) return;
    
    const deleteButton = document.getElementById('deleteBoardBtn');
    const originalButtonText = deleteButton.innerHTML;
    
    deleteButton.disabled = true;
    deleteButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Deleting...
    `;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('board_id', boardId);
    
    fetch('../actions/board/delete.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin'
    })
    .then(async response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (!data || data.success === false) {
            throw new Error(data?.message || 'Failed to delete board');
        }
        
        showToast('Board deleted successfully!', 'success');
        
        // Animate removal - find by data attribute instead of href
        const boardContainer = document.querySelector(`[x-data*="boardId: ${boardId}"]`);
        if (boardContainer) {
            boardContainer.style.transition = 'all 0.3s ease-out';
            boardContainer.style.transform = 'scale(0.9)';
            boardContainer.style.opacity = '0';
            setTimeout(() => {
                boardContainer.remove();
                // Check if grid is empty and show empty state
                const grid = document.querySelector('.grid');
                if (grid && grid.children.length === 0) {
                    showEmptyBoardsState();
                }
            }, 300);
        }
        
        hideDeleteBoardModal();
    })
    .catch(err => {
        console.error('Error deleting board:', err);
        showToast(err.message || 'An error occurred while deleting the board', 'error');
    })
    .finally(() => {
        deleteButton.disabled = false;
        deleteButton.innerHTML = '<i class="fas fa-trash-alt mr-2"></i>Delete Board';
    });
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('open-edit-board', (e) => showEditBoardModal(e.detail));
    document.addEventListener('delete-board', (e) => confirmDeleteBoard(e.detail.id));
    
    // Close modals with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideCreateBoardModal();
            hideEditBoardModal();
            hideDeleteBoardModal();
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
