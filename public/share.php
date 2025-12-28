<?php
/**
 * Share Link Landing Page
 * Handles share link validation and join flow
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../helpers/IdEncrypt.php';

$token = trim($_GET['token'] ?? '');
$error = null;
$errorCode = null;
$shareLink = null;
$boardInfo = null;
$isLoggedIn = isLoggedIn();
$alreadyMember = false;
$existingRole = null;

if (empty($token)) {
    $error = 'Invalid share link';
    $errorCode = 'INVALID_TOKEN';
} else {
    // Validate the token
    $tokenHash = hash('sha256', $token);
    
    $stmt = $conn->prepare("
        SELECT sl.*, b.name as board_name, b.description as board_description, 
               b.id as board_id, u.name as owner_name, u.avatar as owner_avatar
        FROM share_links sl
        INNER JOIN boards b ON sl.board_id = b.id
        INNER JOIN users u ON sl.owner_id = u.id
        WHERE sl.token_hash = ?
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $shareLink = $stmt->get_result()->fetch_assoc();
    
    if (!$shareLink) {
        $error = 'This link is not valid';
        $errorCode = 'INVALID_TOKEN';
    } elseif ($shareLink['is_revoked']) {
        $error = 'This link has been revoked by the owner';
        $errorCode = 'REVOKED';
        $boardInfo = [
            'name' => $shareLink['board_name'],
            'owner' => $shareLink['owner_name']
        ];
    } elseif ($shareLink['expires_at'] && strtotime($shareLink['expires_at']) < time()) {
        $error = 'This link has expired';
        $errorCode = 'EXPIRED';
        $boardInfo = [
            'name' => $shareLink['board_name'],
            'owner' => $shareLink['owner_name']
        ];
    } elseif ($shareLink['max_uses'] && $shareLink['uses'] >= $shareLink['max_uses']) {
        $error = 'This link has reached its maximum number of uses';
        $errorCode = 'MAX_USES_REACHED';
        $boardInfo = [
            'name' => $shareLink['board_name'],
            'owner' => $shareLink['owner_name']
        ];
    } elseif ($shareLink['single_use'] && $shareLink['uses'] > 0) {
        $error = 'This link has already been used';
        $errorCode = 'ALREADY_USED';
        $boardInfo = [
            'name' => $shareLink['board_name'],
            'owner' => $shareLink['owner_name']
        ];
    } else {
        $boardInfo = [
            'id' => $shareLink['board_id'],
            'name' => $shareLink['board_name'],
            'description' => $shareLink['board_description'],
            'owner' => $shareLink['owner_name'],
            'owner_avatar' => $shareLink['owner_avatar'],
            'access_type' => $shareLink['access_type'],
            'role_on_join' => $shareLink['role_on_join'],
            'restrict_domain' => $shareLink['restrict_domain']
        ];
        
        // If logged in, check membership and domain
        if ($isLoggedIn) {
            $userId = $_SESSION['user_id'];
            
            // Check if already a member
            $stmt = $conn->prepare("
                SELECT role FROM board_members 
                WHERE board_id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $shareLink['board_id'], $userId);
            $stmt->execute();
            $membership = $stmt->get_result()->fetch_assoc();
            
            if ($membership) {
                $alreadyMember = true;
                $existingRole = $membership['role'];
            }
            
            // Check domain restriction
            if ($shareLink['restrict_domain'] && !$alreadyMember) {
                $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                
                $userDomain = '@' . substr(strrchr($user['email'], '@'), 1);
                if (strtolower($userDomain) !== strtolower($shareLink['restrict_domain'])) {
                    $error = 'This link is restricted to ' . e($shareLink['restrict_domain']) . ' email addresses';
                    $errorCode = 'DOMAIN_RESTRICTED';
                }
            }
        }
    }
}

$pageTitle = $boardInfo ? 'Join ' . e($boardInfo['name']) . ' - Planify' : 'Share Link - Planify';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo ($_SESSION['theme'] ?? 'light') === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN - Suppress production warning -->
    <script>
        (function() {
            const originalWarn = console.warn;
            console.warn = function(...args) {
                if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com')) return;
                originalWarn.apply(console, args);
            };
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                fontFamily: {
                    sans: ['Poppins', 'sans-serif'],
                },
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#3B82F6'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 dark:bg-gray-900 flex items-center justify-center p-4 font-sans antialiased">
    <div class="w-full max-w-md">
        <?php if ($error): ?>
        <!-- Error State -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <?php if ($errorCode === 'EXPIRED'): ?>
                    <i class="fas fa-clock text-2xl text-red-500"></i>
                    <?php elseif ($errorCode === 'REVOKED'): ?>
                    <i class="fas fa-ban text-2xl text-red-500"></i>
                    <?php elseif ($errorCode === 'DOMAIN_RESTRICTED'): ?>
                    <i class="fas fa-shield-alt text-2xl text-red-500"></i>
                    <?php else: ?>
                    <i class="fas fa-link-slash text-2xl text-red-500"></i>
                    <?php endif; ?>
                </div>
                
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    <?php 
                    switch ($errorCode) {
                        case 'EXPIRED':
                            echo 'Link Expired';
                            break;
                        case 'REVOKED':
                            echo 'Link Revoked';
                            break;
                        case 'DOMAIN_RESTRICTED':
                            echo 'Access Restricted';
                            break;
                        case 'MAX_USES_REACHED':
                        case 'ALREADY_USED':
                            echo 'Link No Longer Available';
                            break;
                        default:
                            echo 'Invalid Link';
                    }
                    ?>
                </h1>
                
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    <?php echo e($error); ?>
                </p>
                
                <?php if ($boardInfo): ?>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6 text-left">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Board</p>
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo e($boardInfo['name']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Shared by</p>
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo e($boardInfo['owner']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="space-y-3">
                    <a href="dashboard.php" class="block w-full px-4 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition">
                        Go to Dashboard
                    </a>
                    <a href="index.php" class="block w-full px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif (!$isLoggedIn): ?>
        <!-- Not Logged In - Show Login Prompt -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <!-- Header -->
            <div class="gradient-bg p-6 text-center text-white">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-white/20 flex items-center justify-center">
                    <i class="fas fa-clipboard-list text-xl"></i>
                </div>
                <h1 class="text-xl font-bold">You've been invited!</h1>
            </div>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                        <?php echo e($boardInfo['name']); ?>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Shared by <?php echo e($boardInfo['owner']); ?>
                    </p>
                    <?php if ($boardInfo['description']): ?>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        <?php echo e($boardInfo['description']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-amber-500 mt-0.5 mr-3"></i>
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            Sign in to your Planify account to join this board.
                        </p>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <a href="login.php?redirect=<?php echo urlencode('share.php?token=' . $token); ?>" 
                       class="block w-full px-4 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition text-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In to Join
                    </a>
                    <a href="register.php?redirect=<?php echo urlencode('share.php?token=' . $token); ?>" 
                       class="block w-full px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition text-center">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif ($alreadyMember): ?>
        <!-- Already a Member -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <i class="fas fa-check text-2xl text-green-500"></i>
                </div>
                
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    You're already a member!
                </h1>
                
                <p class="text-gray-600 dark:text-gray-400 mb-2">
                    You have access to this board as <span class="font-medium"><?php echo ucfirst($existingRole); ?></span>.
                </p>
                
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6">
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo e($boardInfo['name']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">by <?php echo e($boardInfo['owner']); ?></p>
                </div>
                
                <a href="<?php echo encryptedUrl('board.php', $boardInfo['id']); ?>" 
                   class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-arrow-right mr-2"></i>Go to Board
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Join Board Modal -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden" 
             x-data="{ joining: false, requestSent: false, joined: false, error: null }">
            <!-- Header -->
            <div class="gradient-bg p-6 text-center text-white">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-white/20 flex items-center justify-center">
                    <i class="fas fa-clipboard-list text-xl"></i>
                </div>
                <h1 class="text-xl font-bold">Join Board</h1>
            </div>
            
            <div class="p-6">
                <!-- Success State -->
                <div x-show="joined" x-cloak class="text-center py-4">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <i class="fas fa-check text-2xl text-green-500"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">You've joined the board!</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">You can now view and collaborate on this board.</p>
                    <a href="<?php echo encryptedUrl('board.php', $boardInfo['id']); ?>" 
                       class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-arrow-right mr-2"></i>Go to Board
                    </a>
                </div>
                
                <!-- Request Sent State -->
                <div x-show="requestSent" x-cloak class="text-center py-4">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <i class="fas fa-paper-plane text-2xl text-blue-500"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Request Sent!</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">The board owner will review your request. You'll be notified when they respond.</p>
                    <a href="dashboard.php" 
                       class="inline-flex items-center px-6 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-home mr-2"></i>Go to Dashboard
                    </a>
                </div>
                
                <!-- Join Form -->
                <div x-show="!joined && !requestSent">
                    <div class="text-center mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                            <?php echo e($boardInfo['name']); ?>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Shared by <?php echo e($boardInfo['owner']); ?>
                        </p>
                        <?php if ($boardInfo['description']): ?>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            <?php echo e($boardInfo['description']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">You'll join as</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                <?php echo ucfirst($boardInfo['role_on_join']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Error Message -->
                    <div x-show="error" x-cloak class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                            <p class="text-sm text-red-800 dark:text-red-200" x-text="error"></p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <?php if ($boardInfo['access_type'] === 'invite_only'): ?>
                        <button 
                            @click="requestAccess()"
                            :disabled="joining"
                            class="w-full px-4 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center">
                            <span x-show="!joining"><i class="fas fa-paper-plane mr-2"></i>Request Access</span>
                            <span x-show="joining" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Sending Request...
                            </span>
                        </button>
                        <p class="text-xs text-center text-gray-500 dark:text-gray-400">
                            The board owner will need to approve your request.
                        </p>
                        <?php else: ?>
                        <button 
                            @click="joinBoard()"
                            :disabled="joining"
                            class="w-full px-4 py-2.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center">
                            <span x-show="!joining"><i class="fas fa-user-plus mr-2"></i>Join Board</span>
                            <span x-show="joining" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Joining...
                            </span>
                        </button>
                        <?php endif; ?>
                        
                        <a href="dashboard.php" 
                           class="block w-full px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition text-center">
                            Not now
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <p class="text-center text-xs text-gray-500 dark:text-gray-400 mt-6">
            <a href="index.php" class="text-primary hover:underline">Planify</a> — Project Management Made Simple
        </p>
    </div>
    
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        const shareToken = '<?php echo addslashes($token); ?>';
        
        function joinBoard() {
            const component = Alpine.$data(document.querySelector('[x-data]'));
            component.joining = true;
            component.error = null;
            
            fetch('../actions/share/join.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ token: shareToken })
            })
            .then(response => response.json())
            .then(data => {
                component.joining = false;
                if (data.success) {
                    if (data.already_member) {
                        window.location.href = 'board.php?ref=' + data.board_ref;
                    } else if (data.joined) {
                        component.joined = true;
                    } else if (data.request_sent) {
                        component.requestSent = true;
                    }
                } else {
                    component.error = data.message || 'Failed to join board';
                }
            })
            .catch(err => {
                component.joining = false;
                component.error = 'An error occurred. Please try again.';
                console.error('Error:', err);
            });
        }
        
        function requestAccess() {
            // Same as joinBoard - the server will handle it as a request for invite-only links
            joinBoard();
        }
    </script>
</body>
</html>

