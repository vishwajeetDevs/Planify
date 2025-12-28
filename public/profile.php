<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';

// Require login before anything else
requireLogin();

// Validate user exists
$user = getCurrentUser($conn, $_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pageTitle = 'My Profile - Planify';
require_once '../includes/header.php';
require_once '../includes/skeleton.php';

$userId = $_SESSION['user_id'];

// Get detailed user information
$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM workspaces WHERE owner_id = u.id) as workspace_count,
           (SELECT COUNT(*) FROM boards b 
            INNER JOIN board_members bm ON b.id = bm.board_id 
            WHERE bm.user_id = u.id) as board_count,
           (SELECT COUNT(*) FROM cards WHERE created_by = u.id) as card_count,
           (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count
    FROM users u 
    WHERE u.id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: dashboard.php');
    exit;
}

// Get recent activity
$stmt = $conn->prepare("
    SELECT a.*, b.name as board_name
    FROM activities a
    LEFT JOIN boards b ON a.board_id = b.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's workspaces
$stmt = $conn->prepare("
    SELECT w.*, 
           (SELECT COUNT(*) FROM boards WHERE workspace_id = w.id) as board_count
    FROM workspaces w
    WHERE w.owner_id = ?
    ORDER BY w.updated_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userWorkspaces = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Format member since date
$memberSince = new DateTime($user['created_at']);
$now = new DateTime();
$interval = $memberSince->diff($now);
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Profile Header -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden mb-6">
            <div class="p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    <!-- Avatar -->
                    <div class="relative flex-shrink-0">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-gray-100 dark:bg-gray-700 shadow-sm flex items-center justify-center overflow-hidden">
                            <?php if (!empty($user['avatar']) && $user['avatar'] !== 'default-avatar.png'): ?>
                                <img src="<?php echo e($user['avatar']); ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-4xl sm:text-5xl font-bold text-primary">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <button 
                            onclick="showAvatarModal()"
                            class="absolute -bottom-2 -right-2 w-8 h-8 bg-white dark:bg-gray-600 hover:bg-gray-50 dark:hover:bg-gray-500 text-gray-600 dark:text-gray-200 rounded-lg flex items-center justify-center shadow-md transition-colors border border-gray-200 dark:border-gray-500"
                            title="Change avatar"
                        >
                            <i class="fas fa-camera text-sm"></i>
                        </button>
                    </div>
                    
                    <!-- User Details -->
                    <div class="flex-1 text-center sm:text-left">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                            <?php echo e($user['name']); ?>
                        </h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">
                            <?php echo e($user['email']); ?>
                        </p>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">
                            Member since <?php echo $memberSince->format('F Y'); ?>
                            <?php if ($interval->y > 0): ?>
                                · <?php echo $interval->y; ?> year<?php echo $interval->y > 1 ? 's' : ''; ?>
                            <?php elseif ($interval->m > 0): ?>
                                · <?php echo $interval->m; ?> month<?php echo $interval->m > 1 ? 's' : ''; ?>
                            <?php else: ?>
                                · <?php echo max(1, $interval->d); ?> day<?php echo $interval->d > 1 ? 's' : ''; ?>
                            <?php endif; ?>
                        </p>
                        
                        <!-- Action Button -->
                        <div class="mt-4">
                            <button 
                                onclick="showEditProfileModal()"
                                class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium rounded-lg transition-colors text-sm"
                            >
                                <i class="fas fa-pen mr-2 text-xs"></i>
                                Edit Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="max-w-2xl mx-auto space-y-6">
            
            <!-- Profile Settings -->
            <div class="space-y-6">
                
                <!-- Account Settings -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                            <i class="fas fa-cog mr-2 text-gray-400"></i>
                            Account Settings
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Light/Dark Mode -->
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Appearance</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Choose light or dark mode</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button 
                                    onclick="setTheme('light')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $user['theme'] === 'light' ? 'bg-primary text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>"
                                >
                                    <i class="fas fa-sun mr-1"></i> Light
                                </button>
                                <button 
                                    onclick="setTheme('dark')"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $user['theme'] === 'dark' ? 'bg-primary text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>"
                                >
                                    <i class="fas fa-moon mr-1"></i> Dark
                                </button>
                            </div>
                        </div>
                        
                        <hr class="border-gray-200 dark:border-gray-700">
                        
                        <!-- Theme Color -->
                        <div>
                            <div class="mb-4">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Theme Color</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Select your preferred accent color</p>
                            </div>
                            <div class="grid grid-cols-6 sm:grid-cols-13 gap-3" id="themeColorPicker">
                                <?php
                                $themeColors = [
                                    'indigo' => ['bg' => '#4F46E5', 'name' => 'Indigo'],
                                    'blue' => ['bg' => '#2563EB', 'name' => 'Blue'],
                                    'purple' => ['bg' => '#7C3AED', 'name' => 'Purple'],
                                    'pink' => ['bg' => '#DB2777', 'name' => 'Pink'],
                                    'rose' => ['bg' => '#E11D48', 'name' => 'Rose'],
                                    'red' => ['bg' => '#DC2626', 'name' => 'Red'],
                                    'orange' => ['bg' => '#EA580C', 'name' => 'Orange'],
                                    'amber' => ['bg' => '#D97706', 'name' => 'Amber'],
                                    'green' => ['bg' => '#16A34A', 'name' => 'Green'],
                                    'emerald' => ['bg' => '#059669', 'name' => 'Emerald'],
                                    'teal' => ['bg' => '#0D9488', 'name' => 'Teal'],
                                    'cyan' => ['bg' => '#0891B2', 'name' => 'Cyan'],
                                    'slate' => ['bg' => '#475569', 'name' => 'Slate'],
                                ];
                                $currentThemeColor = $user['theme_color'] ?? 'indigo';
                                foreach ($themeColors as $colorKey => $colorData):
                                    $isSelected = $currentThemeColor === $colorKey;
                                ?>
                                <button 
                                    type="button"
                                    onclick="setThemeColor('<?php echo $colorKey; ?>')"
                                    class="group relative w-10 h-10 rounded-full transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 <?php echo $isSelected ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-white scale-110' : ''; ?>"
                                    style="background-color: <?php echo $colorData['bg']; ?>;"
                                    title="<?php echo $colorData['name']; ?>"
                                    data-color="<?php echo $colorKey; ?>"
                                >
                                    <?php if ($isSelected): ?>
                                    <span class="absolute inset-0 flex items-center justify-center">
                                        <i class="fas fa-check text-white text-sm"></i>
                                    </span>
                                    <?php endif; ?>
                                    <span class="sr-only"><?php echo $colorData['name']; ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                                Currently selected: <span id="currentColorName" class="font-medium text-gray-600 dark:text-gray-300"><?php echo $themeColors[$currentThemeColor]['name'] ?? 'Indigo'; ?></span>
                            </p>
                        </div>
                        
                        <hr class="border-gray-200 dark:border-gray-700">
                        
                        <!-- Change Password -->
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Password</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Update your account password</p>
                            </div>
                            <button 
                                onclick="showChangePasswordModal()"
                                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            >
                                <i class="fas fa-key mr-1"></i> Change Password
                            </button>
                        </div>
                        
                        <hr class="border-gray-200 dark:border-gray-700">
                        
                        <!-- Delete Account -->
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-red-600 dark:text-red-400">Delete Account</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Permanently delete your account and all data</p>
                            </div>
                            <button 
                                onclick="confirmDeleteAccount()"
                                class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                            >
                                <i class="fas fa-trash-alt mr-1"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Button -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <button 
                        onclick="showActivityModal()"
                        class="w-full flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-primary/10 dark:bg-primary/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-history text-primary"></i>
                            </div>
                            <div class="ml-3 text-left">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent Activity</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo count($recentActivities); ?> recent actions</p>
                            </div>
                        </div>
                        <i class="fas fa-external-link-alt text-gray-400"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Modal -->
<div id="activityModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-lg shadow-xl max-h-[80vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-history mr-2 text-gray-400"></i>
                Recent Activity
            </h3>
            <button onclick="hideActivityModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <?php if (empty($recentActivities)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-clock text-5xl text-gray-300 dark:text-gray-600 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">No recent activity</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Your actions will appear here</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="flex items-start space-x-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
                                <?php
                                $icon = 'fa-circle';
                                $action = $activity['action'] ?? '';
                                if (strpos($action, 'create') !== false) $icon = 'fa-plus';
                                elseif (strpos($action, 'update') !== false) $icon = 'fa-edit';
                                elseif (strpos($action, 'delete') !== false) $icon = 'fa-trash';
                                elseif (strpos($action, 'move') !== false) $icon = 'fa-arrows-alt';
                                elseif (strpos($action, 'comment') !== false) $icon = 'fa-comment';
                                ?>
                                <i class="fas <?php echo $icon; ?> text-sm text-primary"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    <?php echo e($activity['description']); ?>
                                </p>
                                <div class="flex items-center mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <?php if (!empty($activity['board_name'])): ?>
                                        <span class="truncate max-w-[150px]"><?php echo e($activity['board_name']); ?></span>
                                        <span class="mx-1">•</span>
                                    <?php endif; ?>
                                    <span><?php echo timeAgo($activity['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
            <button 
                onclick="hideActivityModal()"
                class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            >
                Close
            </button>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="editProfileModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-xl">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Profile</h3>
            <button onclick="hideEditProfileModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editProfileForm" onsubmit="updateProfile(event)" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                <input 
                    type="text" 
                    name="name" 
                    id="editName"
                    value="<?php echo e($user['name']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                <input 
                    type="email" 
                    name="email" 
                    id="editEmail"
                    value="<?php echo e($user['email']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div class="flex justify-end space-x-3 pt-4">
                <button 
                    type="button" 
                    onclick="hideEditProfileModal()"
                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-indigo-600 transition-colors"
                >
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-xl">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h3>
            <button onclick="hideChangePasswordModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="changePasswordForm" onsubmit="changePassword(event)" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                <input 
                    type="password" 
                    name="current_password" 
                    id="currentPassword"
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                <input 
                    type="password" 
                    name="new_password" 
                    id="newPassword"
                    required
                    minlength="6"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                <input 
                    type="password" 
                    name="confirm_password" 
                    id="confirmPassword"
                    required
                    minlength="6"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div class="flex justify-end space-x-3 pt-4">
                <button 
                    type="button" 
                    onclick="hideChangePasswordModal()"
                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-indigo-600 transition-colors"
                >
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Show toast notification
function showToast(message, type = 'success') {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({ icon: type, title: message });
    } else {
        alert(message);
    }
}

// Activity Modal
function showActivityModal() {
    document.getElementById('activityModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideActivityModal() {
    document.getElementById('activityModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Edit Profile Modal
function showEditProfileModal() {
    document.getElementById('editProfileModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideEditProfileModal() {
    document.getElementById('editProfileModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Change Password Modal
function showChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('changePasswordForm').reset();
}

// Update Profile
async function updateProfile(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const response = await fetch(window.BASE_PATH + '/actions/profile/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('editName').value,
                email: document.getElementById('editEmail').value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Profile updated successfully!', 'success');
            hideEditProfileModal();
            
            // Update UI without page reload
            if (data.user) {
                document.querySelectorAll('.user-name').forEach(el => el.textContent = data.user.name);
                document.querySelectorAll('.user-email').forEach(el => el.textContent = data.user.email);
                if (data.user.avatar) {
                    document.querySelectorAll('.user-avatar').forEach(el => el.src = data.user.avatar);
                }
            }
        } else {
            throw new Error(data.message || 'Failed to update profile');
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Change Password
async function changePassword(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    
    try {
        const response = await fetch(window.BASE_PATH + '/actions/profile/change-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: document.getElementById('currentPassword').value,
                new_password: newPassword
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Password updated successfully!', 'success');
            hideChangePasswordModal();
        } else {
            throw new Error(data.message || 'Failed to update password');
        }
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Set Theme (Light/Dark)
async function setTheme(theme) {
    try {
        const response = await fetch(window.BASE_PATH + '/actions/theme/toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme: theme })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            // Theme applied instantly, no reload needed
            showToast('Theme updated!', 'success');
        }
    } catch (error) {
        showToast('Failed to update theme', 'error');
    }
}

// Theme color names for display
const themeColorNames = {
    'indigo': 'Indigo',
    'blue': 'Blue',
    'purple': 'Purple',
    'pink': 'Pink',
    'rose': 'Rose',
    'red': 'Red',
    'orange': 'Orange',
    'amber': 'Amber',
    'green': 'Green',
    'emerald': 'Emerald',
    'teal': 'Teal',
    'cyan': 'Cyan',
    'slate': 'Slate'
};

// Set Theme Color
async function setThemeColor(color) {
    try {
        const response = await fetch(window.BASE_PATH + '/actions/theme/color.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ theme_color: color })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the HTML attribute immediately for instant feedback
            document.documentElement.setAttribute('data-theme-color', color);
            
            // Update the UI to show the selected color
            const buttons = document.querySelectorAll('#themeColorPicker button');
            buttons.forEach(btn => {
                const btnColor = btn.getAttribute('data-color');
                if (btnColor === color) {
                    btn.classList.add('ring-2', 'ring-offset-2', 'ring-gray-900', 'dark:ring-white', 'scale-110');
                    btn.innerHTML = '<span class="absolute inset-0 flex items-center justify-center"><i class="fas fa-check text-white text-sm"></i></span><span class="sr-only">' + themeColorNames[color] + '</span>';
                } else {
                    btn.classList.remove('ring-2', 'ring-offset-2', 'ring-gray-900', 'dark:ring-white', 'scale-110');
                    btn.innerHTML = '<span class="sr-only">' + themeColorNames[btnColor] + '</span>';
                }
            });
            
            // Update the current color name display
            document.getElementById('currentColorName').textContent = themeColorNames[color];
            
            showToast('Theme color updated to ' + themeColorNames[color], 'success');
        } else {
            throw new Error(data.message || 'Failed to update theme color');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Delete Account Confirmation
async function confirmDeleteAccount() {
    const result = await Swal.fire({
        title: 'Delete Account?',
        html: `
            <p class="text-gray-600 dark:text-gray-300 mb-4">This action cannot be undone. All your data will be permanently deleted.</p>
            <p class="text-sm text-red-600">Type <strong>DELETE</strong> to confirm:</p>
        `,
        input: 'text',
        inputPlaceholder: 'Type DELETE',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Delete My Account',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if (value !== 'DELETE') {
                return 'Please type DELETE to confirm';
            }
        }
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch(window.BASE_PATH + '/actions/profile/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Account deleted successfully', 'success');
                setTimeout(() => {
                    window.location.href = (window.BASE_PATH || '') + '/public/index.php';
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to delete account');
            }
        } catch (error) {
            showToast(error.message, 'error');
        }
    }
}

// Avatar Modal (placeholder)
function showAvatarModal() {
    showToast('Avatar upload coming soon!', 'info');
}

// Close modals on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        hideEditProfileModal();
        hideChangePasswordModal();
        hideActivityModal();
    }
});

// Close modals on backdrop click
document.querySelectorAll('#editProfileModal, #changePasswordModal, #activityModal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>

