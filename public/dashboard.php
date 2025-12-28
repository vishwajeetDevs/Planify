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

// Get current user details - check BEFORE including header
$user = getCurrentUser($conn, $_SESSION['user_id']);

// If user not found (deleted account or invalid session), logout
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pageTitle = 'Dashboard - Planify';
require_once '../includes/header.php';
require_once '../includes/skeleton.php';

// Get user's accessible workspaces (only those with visible boards or owned by user)
$workspaces = getUserAccessibleWorkspaces($conn, $_SESSION['user_id']);

// Get all accessible boards for the current user
$boards = getUserAccessibleBoards($conn, $_SESSION['user_id']);

// Check for error message in session
$errorMessage = null;
if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Main Content -->
    <div class="w-full pt-20 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="px-8 py-6 max-w-[100rem] mx-auto border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 shadow-sm transition-all duration-300 hover:shadow-md">
            
            <?php if ($errorMessage): ?>
            <!-- Error Message -->
            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg animate-fade-in-up" id="errorAlert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700 dark:text-red-300"><?php echo e($errorMessage); ?></p>
                    <button onclick="document.getElementById('errorAlert').remove()" class="ml-auto text-red-400 hover:text-red-600 dark:hover:text-red-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="mb-8 animate-fade-in-up" style="animation-delay: 0.15s;">
                <div class="flex items-center justify-between">
                    <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">Welcome back, <?php echo e($user['name']); ?>!</h1>
                <p class="text-gray-600 dark:text-gray-400">Manage your boards and stay organized</p>
                    </div>
                </div>
            </div>

            <!-- Workspaces Section -->
            <div class="mb-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-layer-group text-gray-500 dark:text-gray-400 mr-2"></i>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Your Workspaces</h2>
                    </div>
                    <button 
                        type="button"
                        onclick="showCreateWorkspaceModal()"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-primary hover:bg-primary-dark dark:bg-primary dark:hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:focus:ring-offset-gray-900 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
                    >
                        <i class="fas fa-plus mr-2 transition-transform duration-200 group-hover:rotate-90"></i> New Workspace
                    </button>
                </div>

                <?php if (empty($workspaces)): ?>
                    <div class="text-center py-12 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg transition-all duration-300 hover:border-primary dark:hover:border-primary animate-fade-in">
                        <i class="fas fa-layer-group text-4xl text-gray-400 mb-3 animate-bounce-subtle"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No workspaces yet</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Create your first workspace to get started</p>
                        <button 
                            type="button"
                            onclick="showCreateWorkspaceModal()" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-primary hover:bg-primary-dark dark:bg-primary dark:hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:focus:ring-offset-gray-900 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
                        >
                            <i class="fas fa-plus mr-2"></i> Create Workspace
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Workspaces Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- New Workspace Card -->
                        <div 
                            class="group flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-primary dark:hover:border-primary p-5 h-40 cursor-pointer transition-all duration-300 hover:shadow-lg hover:-translate-y-1 animate-fade-in-up"
                            style="animation-delay: 0.25s;"
                            onclick="showCreateWorkspaceModal()">
                            <div class="flex flex-col items-center justify-center text-center p-4">
                                <div class="w-12 h-12 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center mb-3 group-hover:bg-primary/20 dark:group-hover:bg-primary/30 transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg">
                                    <i class="fas fa-plus text-primary text-lg transition-transform duration-300 group-hover:rotate-90"></i>
                                </div>
                                <h3 class="font-medium text-gray-900 dark:text-white mb-1 transition-colors duration-200 group-hover:text-primary">New Workspace</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Click to create a new workspace</p>
                            </div>
                        </div>
                        
                        <?php 
                        // Sort workspaces by created_at in ascending order (oldest first)
                        usort($workspaces, function($a, $b) {
                            return strtotime($a['created_at']) - strtotime($b['created_at']);
                        });
                        
                        $index = 0;
                        foreach ($workspaces as $workspace): 
                            $isWorkspaceOwner = ($workspace['owner_id'] == $_SESSION['user_id']);
                            $delay = 0.3 + ($index * 0.05);
                            $index++;
                        ?>
                            <div class="group block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-xl p-5 h-40 flex flex-col border border-gray-100 dark:border-gray-700 hover:border-primary/50 dark:hover:border-primary/50 relative transition-all duration-300 hover:-translate-y-1 animate-fade-in-up"
                                 style="animation-delay: <?php echo $delay; ?>s;"
                                 x-data="{ showMenu: false, workspaceId: <?php echo $workspace['id']; ?> }">
                                <?php if ($isWorkspaceOwner): ?>
                                <!-- Three-dot menu button (only for owners) -->
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <button 
                                        @click.stop="showMenu = !showMenu"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:scale-110"
                                        @click.away="showMenu = false"
                                    >
                                        <i class="fas fa-ellipsis-h"></i>
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
                                        <a 
                                            href="#" 
                                            class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-gray-700 transition-all duration-150 hover:pl-5"
                                            @click="showMenu = false; $dispatch('open-edit-workspace', { id: workspaceId, name: '<?php echo addslashes($workspace['name']); ?>', description: '<?php echo isset($workspace['description']) ? addslashes($workspace['description']) : ''; ?>' })"
                                        >
                                            <i class="far fa-edit mr-2 w-4"></i> Edit
                                        </a>
                                        <a 
                                            href="#" 
                                            class="flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-150 hover:pl-5"
                                            @click="showMenu = false; { deleteWorkspace(workspaceId); }"
                                        >
                                            <i class="far fa-trash-alt mr-2 w-4"></i> Delete
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <a href="<?php echo encryptedUrl('workspace.php', $workspace['id']); ?>" class="flex-1 flex flex-col">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <h3 class="font-medium text-gray-900 dark:text-white line-clamp-2 pr-4 transition-colors duration-200 group-hover:text-primary">
                                                <?php echo e($workspace['name']); ?>
                                            </h3>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center">
                                            <i class="fas fa-clipboard mr-1.5 text-gray-400"></i>
                                            <?php echo $workspace['visible_board_count']; ?> board<?php echo $workspace['visible_board_count'] != 1 ? 's' : ''; ?>
                                        </p>
                                    </div>
                                    <div class="mt-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                                        <span class="text-xs text-gray-400 flex items-center">
                                            <i class="far fa-clock mr-1.5"></i>
                                            Created <?php echo timeAgo($workspace['created_at']); ?>
                                        </span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit Workspace Modal -->
    <div id="workspaceModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden transition-opacity duration-300" onclick="if(event.target === this) hideWorkspaceModal()">
        <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-2xl transform transition-all duration-300 scale-95 opacity-0" id="workspaceModalContent">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="workspaceModalTitle">Create New Workspace</h3>
                    <button type="button" onclick="hideWorkspaceModal()" 
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:rotate-90">
                        <i class="fas fa-times w-5 h-5"></i>
                        <span class="sr-only">Close</span>
                    </button>
                </div>
                
                <form id="workspaceForm" onsubmit="return handleWorkspaceSubmit(event)">
                    <input type="hidden" id="workspaceId" name="id" value="">
                    <div class="mt-4">
                        <label for="workspace-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workspace Name <span class="text-red-500">*</span></label>
                        <input type="text" id="workspace-name" name="name" required
                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:shadow-lg"
                               placeholder="e.g., Marketing Team">
                    </div>
                    <div class="mt-4">
                        <label for="workspace-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (Optional)</label>
                        <textarea
                            id="workspace-description"
                            name="description"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:shadow-lg resize-none"
                            rows="3"
                            placeholder="What's this workspace about?"></textarea>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button"
                                onclick="hideWorkspaceModal()"
                                class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-all duration-200 hover:shadow-md">
                            Cancel
                        </button>
                        <button type="submit" id="workspaceSubmitBtn"
                                class="px-4 py-2.5 text-sm font-medium text-white bg-primary hover:bg-primary-dark dark:bg-primary dark:hover:bg-primary-light border border-transparent rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:focus:ring-offset-gray-900 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0">
                            Create Workspace
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Workspace Modal and Functions -->
    <script>
        // Make sure the DOM is fully loaded
        document.addEventListener('alpine:init', () => {
            // Initialize Alpine.js data
            Alpine.data('workspace', () => ({
                showMenu: false,
                toggleMenu() {
                    this.showMenu = !this.showMenu;
                },
                closeMenu() {
                    this.showMenu = false;
                }
            }));
        });

        // Show create workspace modal with animation
        function showCreateWorkspaceModal() {
            const modal = document.getElementById('workspaceModal');
            const modalContent = document.getElementById('workspaceModalContent');
            const form = document.getElementById('workspaceForm');
            
            if (modal && form && modalContent) {
                // Reset form
                form.reset();
                form.querySelector('input[name="id"]').value = '';
                
                // Update UI
                document.getElementById('workspaceModalTitle').textContent = 'Create New Workspace';
                document.getElementById('workspaceSubmitBtn').textContent = 'Create Workspace';
                
                // Show modal with animation
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                // Trigger animation
                requestAnimationFrame(() => {
                    modal.style.opacity = '1';
                    modalContent.style.transform = 'scale(1)';
                    modalContent.style.opacity = '1';
                });
                
                // Focus on name input after animation
                setTimeout(() => {
                    const nameInput = form.querySelector('input[name="name"]');
                    if (nameInput) nameInput.focus();
                }, 200);
            }
        }
        
        // Show edit workspace modal with animation
        function showEditWorkspaceModal(workspace) {
            const modal = document.getElementById('workspaceModal');
            const modalContent = document.getElementById('workspaceModalContent');
            const form = document.getElementById('workspaceForm');
            
            if (modal && form && modalContent) {
                // Set form values
                form.querySelector('input[name="id"]').value = workspace.id;
                form.querySelector('input[name="name"]').value = workspace.name;
                form.querySelector('textarea[name="description"]').value = workspace.description || '';
                
                // Update UI
                document.getElementById('workspaceModalTitle').textContent = 'Edit Workspace';
                document.getElementById('workspaceSubmitBtn').textContent = 'Update Workspace';
                
                // Show modal with animation
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                // Trigger animation
                requestAnimationFrame(() => {
                    modal.style.opacity = '1';
                    modalContent.style.transform = 'scale(1)';
                    modalContent.style.opacity = '1';
                });
                
                // Focus on name input after animation
                setTimeout(() => {
                    const nameInput = form.querySelector('input[name="name"]');
                    if (nameInput) nameInput.focus();
                }, 200);
            }
        }
        
        // Hide workspace modal with animation
        function hideWorkspaceModal() {
            const modal = document.getElementById('workspaceModal');
            const modalContent = document.getElementById('workspaceModalContent');
            
            if (modal && modalContent) {
                // Animate out
                modalContent.style.transform = 'scale(0.95)';
                modalContent.style.opacity = '0';
                
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.style.opacity = '';
                    modalContent.style.transform = '';
                    modalContent.style.opacity = '';
                    document.body.style.overflow = '';
                }, 200);
            }
        }
        
        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('workspaceModal');
                if (modal && !modal.classList.contains('hidden')) {
                    hideWorkspaceModal();
                }
            }
        });
        
        // Handle workspace form submission (create/update)
        async function handleWorkspaceSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const workspaceId = formData.get('id');
            const isEdit = !!workspaceId;
            
            // Validate form
            const workspaceName = formData.get('name').trim();
            if (!workspaceName) {
                showToast('Workspace name is required', 'error');
                const nameInput = form.querySelector('[name="name"]');
                nameInput.classList.add('border-red-500', 'animate-wiggle');
                nameInput.focus();
                setTimeout(() => nameInput.classList.remove('animate-wiggle'), 500);
                return false;
            }
            
            const submitButton = document.getElementById('workspaceSubmitBtn');
            const cancelButton = form.querySelector('button[type="button"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Update button state with animation
            submitButton.disabled = true;
            if (cancelButton) cancelButton.disabled = true;
            submitButton.innerHTML = `
                <span class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ${isEdit ? 'Updating...' : 'Creating...'}
                </span>
            `;
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                };
                
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }
                
                const endpoint = isEdit 
                    ? `${window.BASE_PATH}/actions/workspace/update.php` 
                    : `${window.BASE_PATH}/actions/workspace/create.php`;
                
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });
                
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: JSON.stringify(data),
                    headers: headers
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    form.reset();
                    hideWorkspaceModal();
                    
                    showToast(
                        isEdit ? 'Workspace updated successfully!' : 'Workspace created successfully!', 
                        'success'
                    );
                    
                    // Add new workspace to DOM without page reload
                    if (!isEdit && result.workspace) {
                        addWorkspaceToGrid(result.workspace);
                    } else if (isEdit && result.workspace) {
                        updateWorkspaceInGrid(result.workspace);
                    } else {
                        // Fallback: reload only if we don't have workspace data
                        setTimeout(() => window.location.reload(), 500);
                    }
                } else {
                    throw new Error(result.message || `Failed to ${isEdit ? 'update' : 'create'} workspace`);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast(
                    error.message || `An error occurred while ${isEdit ? 'updating' : 'creating'} the workspace`, 
                    'error'
                );
            } finally {
                submitButton.disabled = false;
                if (cancelButton) cancelButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
            
            return false;
        }
        
        // Helper function to escape HTML
        function escapeHtmlLocal(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Add new workspace to grid without page reload
        function addWorkspaceToGrid(workspace) {
            let grid = document.querySelector('.grid.grid-cols-1');
            const workspacesSection = document.querySelector('.mb-8.animate-fade-in-up[style*="0.2s"]');
            
            // If no grid exists (empty state), create it and replace empty state
            if (!grid) {
                const emptyState = document.querySelector('.text-center.py-12.border-2');
                if (emptyState && workspacesSection) {
                    // Create the grid container
                    grid = document.createElement('div');
                    grid.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4';
                    
                    // Add "New Workspace" card first
                    grid.innerHTML = `
                        <div 
                            class="group flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-primary dark:hover:border-primary p-5 h-40 cursor-pointer transition-all duration-300 hover:shadow-lg hover:-translate-y-1 animate-fade-in-up"
                            onclick="showCreateWorkspaceModal()">
                            <div class="flex flex-col items-center justify-center text-center p-4">
                                <div class="w-12 h-12 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center mb-3 group-hover:bg-primary/20 dark:group-hover:bg-primary/30 transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg">
                                    <i class="fas fa-plus text-primary text-lg transition-transform duration-300 group-hover:rotate-90"></i>
                                </div>
                                <h3 class="font-medium text-gray-900 dark:text-white mb-1 transition-colors duration-200 group-hover:text-primary">New Workspace</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Click to create a new workspace</p>
                            </div>
                        </div>
                    `;
                    
                    // Replace empty state with grid
                    emptyState.replaceWith(grid);
                }
            }
            
            if (!grid) {
                // Fallback: reload the page
                setTimeout(() => window.location.reload(), 500);
                return;
            }
            
            const safeName = escapeHtmlLocal(workspace.name);
            const safeDesc = escapeHtmlLocal(workspace.description || '');
            
            const card = document.createElement('div');
            card.className = 'group block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-xl p-5 h-40 flex flex-col border border-gray-100 dark:border-gray-700 hover:border-primary/50 dark:hover:border-primary/50 relative transition-all duration-300 hover:-translate-y-1 animate-fade-in-up';
            card.dataset.workspaceId = workspace.id;
            card.setAttribute('x-data', `{ showMenu: false, workspaceId: ${workspace.id} }`);
            
            card.innerHTML = `
                <!-- Three-dot menu button -->
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    <button 
                        @click.stop="showMenu = !showMenu"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 hover:scale-110"
                        @click.away="showMenu = false"
                    >
                        <i class="fas fa-ellipsis-h"></i>
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
                        <a 
                            href="#" 
                            class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-gray-700 transition-all duration-150 hover:pl-5"
                            @click="showMenu = false; $dispatch('open-edit-workspace', { id: workspaceId, name: '${safeName.replace(/'/g, "\\'")}', description: '${safeDesc.replace(/'/g, "\\'")}' })"
                        >
                            <i class="far fa-edit mr-2 w-4"></i> Edit
                        </a>
                        <a 
                            href="#" 
                            class="flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-150 hover:pl-5"
                            @click="showMenu = false; { deleteWorkspace(workspaceId); }"
                        >
                            <i class="far fa-trash-alt mr-2 w-4"></i> Delete
                        </a>
                    </div>
                </div>
                
                <a href="${window.BASE_PATH}/public/workspace.php?id=${workspace.id}" class="flex-1 flex flex-col">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <h3 class="font-medium text-gray-900 dark:text-white line-clamp-2 pr-4 transition-colors duration-200 group-hover:text-primary">
                                ${safeName}
                            </h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center">
                            <i class="fas fa-clipboard mr-1.5 text-gray-400"></i>
                            0 boards
                        </p>
                    </div>
                    <div class="mt-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <span class="text-xs text-gray-400 flex items-center">
                            <i class="far fa-clock mr-1.5"></i>
                            Created 0 min ago
                        </span>
                    </div>
                </a>
            `;
            
            // Insert after the "New Workspace" card
            const newWorkspaceCard = grid.querySelector('[onclick*="showCreateWorkspaceModal"]');
            if (newWorkspaceCard) {
                newWorkspaceCard.after(card);
            } else {
                grid.appendChild(card);
            }
            
            // Re-initialize Alpine.js for the new element
            if (window.Alpine) {
                Alpine.initTree(card);
            }
        }
        
        // Update existing workspace in grid
        function updateWorkspaceInGrid(workspace) {
            const card = document.querySelector(`[data-workspace-id="${workspace.id}"]`);
            if (!card) return;
            
            const titleEl = card.querySelector('h3');
            if (titleEl) titleEl.textContent = workspace.name;
        }
        
        // Delete workspace with animation
        async function deleteWorkspace(workspaceId) {
            if (!workspaceId) return;
            
            const result = await Swal.fire({
                title: 'Delete Workspace',
                text: 'Are you sure you want to delete this workspace? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash-alt mr-2"></i>Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                showClass: {
                    popup: 'animate-scale-in'
                },
                hideClass: {
                    popup: 'animate-scale-out'
                }
            });
            
            if (!result.isConfirmed) return;
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                };
                
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }
                
                const response = await fetch(`${window.BASE_PATH}/actions/workspace/delete.php`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ id: workspaceId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Animate removal
                    const workspaceCard = document.querySelector(`[x-data*="workspaceId: ${workspaceId}"]`);
                    if (workspaceCard) {
                        workspaceCard.style.transition = 'all 0.3s ease-out';
                        workspaceCard.style.transform = 'scale(0.9)';
                        workspaceCard.style.opacity = '0';
                        setTimeout(() => workspaceCard.remove(), 300);
                    }
                    
                    showToast('Workspace deleted successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to delete workspace', 'error');
                }
            } catch (error) {
                console.error('Error deleting workspace:', error);
                showToast(error.message || 'An error occurred while deleting the workspace', 'error');
            }
        }
        
        // Listen for edit workspace event
        document.addEventListener('open-edit-workspace', (event) => {
            showEditWorkspaceModal(event.detail);
        });
    </script>

    <?php require_once '../includes/footer.php'; ?>
