<?php
// Start output buffering at the very first line
if (ob_get_level() == 0) {
    ob_start();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Get current user and theme
$currentUser = isLoggedIn() ? getCurrentUser($conn) : null;
$theme = $_SESSION['theme'] ?? 'light';

// Get theme color from session or database
$themeColor = $_SESSION['theme_color'] ?? 'purple';
if ($currentUser && isset($currentUser['theme_color'])) {
    $themeColor = $currentUser['theme_color'];
    $_SESSION['theme_color'] = $themeColor; // Sync session with database
}

// Only send headers if they haven't been sent already
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Only add HSTS in production with HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' && !Env::isDevelopment()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Generate CSRF token if not exists
$csrfToken = ensureCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>" data-theme-color="<?php echo htmlspecialchars($themeColor); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title><?php echo $pageTitle ?? 'Planify - Kanban Project Management'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/images/planify_logo.png">
    <link rel="apple-touch-icon" href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/images/planify_logo.png">
    
    <!-- Google Fonts - Poppins (Load first for font availability) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN - Suppress production warning -->
    <script>
        // Suppress Tailwind CDN production warning by intercepting console.warn
        (function() {
            const originalWarn = console.warn;
            console.warn = function(...args) {
                // Filter out the Tailwind CDN warning
                if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com')) {
                    return; // Suppress this specific warning
                }
                originalWarn.apply(console, args);
            };
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind Configuration - Enhanced with comprehensive animations
        tailwind.config = {
            darkMode: 'class',
            theme: {
                fontFamily: {
                    sans: ['Poppins', 'sans-serif'],
                },
                extend: {
                    colors: {
                        primary: 'var(--color-primary)',
                        'primary-light': 'var(--color-primary-light)',
                        'primary-dark': 'var(--color-primary-dark)',
                        secondary: '#3B82F6'
                    },
                    borderColor: {
                        primary: 'var(--color-primary)',
                        'primary-light': 'var(--color-primary-light)',
                        'primary-dark': 'var(--color-primary-dark)',
                    },
                    animation: {
                        // Fade animations
                        'fade-in': 'fadeIn 0.3s ease-out forwards',
                        'fade-out': 'fadeOut 0.2s ease-in forwards',
                        'fade-in-up': 'fadeInUp 0.4s ease-out forwards',
                        'fade-in-down': 'fadeInDown 0.4s ease-out forwards',
                        
                        // Slide animations
                        'slide-in-right': 'slideInRight 0.3s ease-out forwards',
                        'slide-in-left': 'slideInLeft 0.3s ease-out forwards',
                        'slide-in-up': 'slideInUp 0.3s ease-out forwards',
                        'slide-in-down': 'slideInDown 0.3s ease-out forwards',
                        'slide-out-right': 'slideOutRight 0.2s ease-in forwards',
                        
                        // Scale animations
                        'scale-in': 'scaleIn 0.2s ease-out forwards',
                        'scale-out': 'scaleOut 0.15s ease-in forwards',
                        'pop': 'pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards',
                        
                        // Bounce animations
                        'bounce-in': 'bounceIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards',
                        'bounce-subtle': 'bounceSubtle 0.4s ease-out',
                        
                        // Pulse animations
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'pulse-fast': 'pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        
                        // Special animations
                        'shimmer': 'shimmer 2s linear infinite',
                        'wiggle': 'wiggle 0.5s ease-in-out',
                        'spin-slow': 'spin 2s linear infinite',
                        
                        // Card animations
                        'card-enter': 'cardEnter 0.3s ease-out forwards',
                        'card-exit': 'cardExit 0.2s ease-in forwards',
                        
                        // Modal animations
                        'modal-in': 'modalIn 0.3s ease-out forwards',
                        'modal-out': 'modalOut 0.2s ease-in forwards',
                        'backdrop-in': 'backdropIn 0.2s ease-out forwards',
                        'backdrop-out': 'backdropOut 0.15s ease-in forwards',
                    },
                    keyframes: {
                        // Fade keyframes
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        fadeOut: {
                            '0%': { opacity: '1' },
                            '100%': { opacity: '0' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeInDown: {
                            '0%': { opacity: '0', transform: 'translateY(-10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        
                        // Slide keyframes
                        slideInRight: {
                            '0%': { opacity: '0', transform: 'translateX(20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' },
                        },
                        slideInLeft: {
                            '0%': { opacity: '0', transform: 'translateX(-20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' },
                        },
                        slideInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        slideInDown: {
                            '0%': { opacity: '0', transform: 'translateY(-20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        slideOutRight: {
                            '0%': { opacity: '1', transform: 'translateX(0)' },
                            '100%': { opacity: '0', transform: 'translateX(20px)' },
                        },
                        
                        // Scale keyframes
                        scaleIn: {
                            '0%': { opacity: '0', transform: 'scale(0.95)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        scaleOut: {
                            '0%': { opacity: '1', transform: 'scale(1)' },
                            '100%': { opacity: '0', transform: 'scale(0.95)' },
                        },
                        pop: {
                            '0%': { opacity: '0', transform: 'scale(0.9)' },
                            '70%': { transform: 'scale(1.02)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        
                        // Bounce keyframes
                        bounceIn: {
                            '0%': { opacity: '0', transform: 'scale(0.3)' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        bounceSubtle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' },
                        },
                        
                        // Special keyframes
                        shimmer: {
                            '0%': { backgroundPosition: '-200% 0' },
                            '100%': { backgroundPosition: '200% 0' },
                        },
                        wiggle: {
                            '0%, 100%': { transform: 'rotate(0deg)' },
                            '25%': { transform: 'rotate(-3deg)' },
                            '75%': { transform: 'rotate(3deg)' },
                        },
                        
                        // Card keyframes
                        cardEnter: {
                            '0%': { opacity: '0', transform: 'translateY(-10px) scale(0.98)' },
                            '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                        },
                        cardExit: {
                            '0%': { opacity: '1', transform: 'scale(1)' },
                            '100%': { opacity: '0', transform: 'scale(0.95)' },
                        },
                        
                        // Modal keyframes
                        modalIn: {
                            '0%': { opacity: '0', transform: 'translateY(20px) scale(0.95)' },
                            '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                        },
                        modalOut: {
                            '0%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                            '100%': { opacity: '0', transform: 'translateY(20px) scale(0.95)' },
                        },
                        backdropIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        backdropOut: {
                            '0%': { opacity: '1' },
                            '100%': { opacity: '0' },
                        },
                    },
                    transitionTimingFunction: {
                        'bounce': 'cubic-bezier(0.34, 1.56, 0.64, 1)',
                        'smooth': 'cubic-bezier(0.4, 0, 0.2, 1)',
                        'out-expo': 'cubic-bezier(0.19, 1, 0.22, 1)',
                    },
                    transitionDuration: {
                        '0': '0ms',
                        '75': '75ms',
                        '100': '100ms',
                        '150': '150ms',
                        '200': '200ms',
                        '300': '300ms',
                        '400': '400ms',
                        '500': '500ms',
                    },
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- SortableJS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Alpine.js CDN - Load after Tailwind but before custom scripts -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom CSS - Load last to override any defaults -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    
    <script>
        // Debug mode - set to false for production
        window.DEBUG_MODE = false;
        
        // Base path for API calls - auto-detected from environment
        window.BASE_PATH = '<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>';
        
        // Helper function for debug logging
        window.debugLog = function(...args) {
            if (window.DEBUG_MODE) {
                console.log(...args);
            }
        };
        
        // Set current user ID globally for permission checks
        <?php if (isLoggedIn() && isset($_SESSION['user_id'])): ?>
        window.currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
        debugLog('Header: currentUserId set to', window.currentUserId);
        <?php else: ?>
        window.currentUserId = null;
        debugLog('Header: currentUserId is null (not logged in)');
        <?php endif; ?>
        
        // Global toast function with animations
        window.showToast = function(message, type = 'success') {
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    showClass: {
                        popup: 'animate-slide-in-right'
                    },
                    hideClass: {
                        popup: 'animate-slide-out-right'
                    },
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });
                
                Toast.fire({
                    icon: type,
                    title: message
                });
            } else {
                alert(`${type.toUpperCase()}: ${message}`);
            }
        };
    </script>
    
    <style>
        /* Critical inline styles for immediate rendering */
        [x-cloak] { display: none !important; }
        
        /* Smooth page transitions - using opacity only to avoid breaking fixed positioning */
        body {
            opacity: 0;
            animation: pageLoad 0.4s ease-out 0.1s forwards;
        }
        
        @keyframes pageLoad {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans antialiased transition-colors duration-300">
    
    <?php if (isLoggedIn()): ?>
    <!-- Navigation Bar -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 pl-[86px] transition-all duration-300 group-hover:pl-[276px] animate-fade-in-down relative z-[200]">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="flex items-center group/logo">
                        <img src="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/assets/images/planify_logo.png" alt="Planify" class="h-8 w-8 mr-2 transition-transform duration-300 group-hover/logo:scale-110">
                        <span class="text-2xl font-bold text-primary dark:text-white transition-transform duration-300 group-hover/logo:scale-105">PLANIFY</span>
                    </a>
                    
                    <!-- Global Search Bar (shown when logged in) -->
                    <?php if ($currentUser): ?>
                    <div class="ml-8 hidden md:block">
                        <div class="relative group/search">
                            <input 
                                type="text" 
                                id="globalSearch" 
                                placeholder="Search tasks..." 
                                data-workspace-id="<?php echo isset($workspaceId) ? intval($workspaceId) : ''; ?>"
                                data-board-id="<?php echo isset($boardId) ? intval($boardId) : ''; ?>"
                                class="w-64 px-4 py-2 pl-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-200 focus:w-80"
                            >
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400 transition-colors duration-200 group-focus-within/search:text-primary"></i>
                        </div>
                        <div id="searchResults" class="absolute mt-2 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 hidden z-50 max-h-96 overflow-y-auto animate-fade-in-down"></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Theme Toggle -->
                    <button 
                        onclick="toggleTheme()" 
                        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-all duration-200 hover:scale-110 hover:rotate-12"
                        title="Toggle theme"
                    >
                        <i class="fas fa-moon dark:hidden transition-transform duration-300"></i>
                        <i class="fas fa-sun hidden dark:inline transition-transform duration-300"></i>
                    </button>
                    
                    <!-- Notifications Bell -->
                    <div x-data="notificationsDropdown()" class="relative" @click.away="open = false">
                        <button 
                            @click="toggle()"
                            class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-all duration-200 hover:scale-110"
                            title="Notifications"
                        >
                            <i class="fas fa-bell text-lg"></i>
                            <span 
                                x-show="unreadCount > 0" 
                                x-text="unreadCount > 99 ? '99+' : unreadCount"
                                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center text-[10px] font-bold text-white bg-red-500 rounded-full px-1 animate-bounce-in"
                            ></span>
                        </button>
                        
                        <!-- Notifications Dropdown -->
                        <div 
                            x-show="open" 
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                            class="absolute right-0 mt-2 w-80 sm:w-96 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 z-[100] overflow-hidden"
                        >
                            <!-- Header -->
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-800/50">
                                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i class="fas fa-bell text-primary"></i>
                                    Notifications
                                </h3>
                                <button 
                                    x-show="unreadCount > 0"
                                    @click="markAllAsRead()"
                                    class="text-xs text-primary hover:text-primary-dark font-medium transition-colors"
                                >
                                    Mark all as read
                                </button>
                            </div>
                            
                            <!-- Notifications List -->
                            <div class="max-h-96 overflow-y-auto">
                                <template x-if="loading">
                                    <div class="p-8 text-center">
                                        <i class="fas fa-spinner fa-spin text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Loading...</p>
                                    </div>
                                </template>
                                
                                <template x-if="!loading && notifications.length === 0">
                                    <div class="p-8 text-center">
                                        <div class="w-16 h-16 mx-auto mb-3 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                            <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No notifications yet</p>
                                    </div>
                                </template>
                                
                                <template x-for="notification in notifications" :key="notification.id">
                                    <div 
                                        @click="handleNotificationClick(notification)"
                                        :class="{ 'bg-primary/5 dark:bg-primary/10': !notification.is_read }"
                                        class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div :class="getNotificationIconClass(notification.type)" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                                                <i :class="getNotificationIcon(notification.type)" class="text-sm"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="notification.title"></p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 line-clamp-2" x-text="notification.message"></p>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1" x-text="notification.time_ago"></p>
                                            </div>
                                            <div x-show="!notification.is_read" class="w-2 h-2 bg-primary rounded-full flex-shrink-0 mt-2"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- Footer -->
                            <div x-show="notifications.length > 0" class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <button 
                                    @click="loadMore()"
                                    x-show="hasMore"
                                    class="w-full text-center text-sm text-primary hover:text-primary-dark font-medium py-1 transition-colors"
                                >
                                    Load more
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div x-data="{ open: false }" class="relative">
                        <button 
                            @click="open = !open" 
                            class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-primary transition-all duration-200 group/user"
                        >
                            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-semibold transition-transform duration-200 group-hover/user:scale-110 group-hover/user:shadow-lg">
                                <?php echo $currentUser ? strtoupper(substr($currentUser['name'], 0, 1)) : 'U'; ?>
                            </div>
                            <span class="hidden md:inline"><?php echo $currentUser ? e($currentUser['name']) : 'User'; ?></span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>
                        
                        <div 
                            x-show="open" 
                            x-cloak
                            @click.away="open = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                            class="absolute right-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-[100] border border-gray-100 dark:border-gray-700"
                        >
                            <a href="profile.php" class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <i class="fas fa-user mr-2 w-4 text-gray-400"></i> Profile
                            </a>
                            <a href="../actions/auth/logout.php" class="flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2 w-4"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-20 right-4 z-50 space-y-2"></div>
    
    <!-- Notifications Alpine.js Component -->
    <script>
        function notificationsDropdown() {
            return {
                open: false,
                loading: false,
                notifications: [],
                unreadCount: 0,
                hasMore: false,
                offset: 0,
                limit: 10,
                pollInterval: null,
                
                init() {
                    this.fetchUnreadCount();
                    // Poll for new notifications every 30 seconds
                    this.pollInterval = setInterval(() => {
                        this.fetchUnreadCount();
                    }, 30000);
                },
                
                toggle() {
                    this.open = !this.open;
                    if (this.open && this.notifications.length === 0) {
                        this.loadNotifications();
                    }
                },
                
                async fetchUnreadCount() {
                    try {
                        const response = await fetch(`${window.BASE_PATH}/actions/notification/count.php`);
                        const data = await response.json();
                        if (data.success) {
                            this.unreadCount = data.unread_count;
                        }
                    } catch (error) {
                        console.error('Error fetching notification count:', error);
                    }
                },
                
                async loadNotifications() {
                    this.loading = true;
                    try {
                        const response = await fetch(`${window.BASE_PATH}/actions/notification/get.php?limit=${this.limit}&offset=0`);
                        const data = await response.json();
                        if (data.success) {
                            this.notifications = data.notifications;
                            this.unreadCount = data.unread_count;
                            this.hasMore = data.has_more;
                            this.offset = this.limit;
                        }
                    } catch (error) {
                        console.error('Error loading notifications:', error);
                    } finally {
                        this.loading = false;
                    }
                },
                
                async loadMore() {
                    try {
                        const response = await fetch(`${window.BASE_PATH}/actions/notification/get.php?limit=${this.limit}&offset=${this.offset}`);
                        const data = await response.json();
                        if (data.success) {
                            this.notifications = [...this.notifications, ...data.notifications];
                            this.hasMore = data.has_more;
                            this.offset += this.limit;
                        }
                    } catch (error) {
                        console.error('Error loading more notifications:', error);
                    }
                },
                
                async markAsRead(notification) {
                    if (notification.is_read) return;
                    
                    try {
                        const response = await fetch(`${window.BASE_PATH}/actions/notification/mark-read.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ notification_id: notification.id })
                        });
                        const data = await response.json();
                        if (data.success) {
                            notification.is_read = true;
                            this.unreadCount = data.unread_count;
                        }
                    } catch (error) {
                        console.error('Error marking notification as read:', error);
                    }
                },
                
                async markAllAsRead() {
                    try {
                        const response = await fetch(`${window.BASE_PATH}/actions/notification/mark-read.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ mark_all: true })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.notifications.forEach(n => n.is_read = true);
                            this.unreadCount = 0;
                        }
                    } catch (error) {
                        console.error('Error marking all as read:', error);
                    }
                },
                
                handleNotificationClick(notification) {
                    // Just mark as read - notifications are view-only
                    this.markAsRead(notification);
                },
                
                getNotificationIcon(type) {
                    const icons = {
                        'mention': 'fas fa-at',
                        'assignment': 'fas fa-user-plus',
                        'task_update': 'fas fa-edit',
                        'comment': 'fas fa-comment',
                        'due_date': 'fas fa-calendar-alt',
                        'checklist': 'fas fa-check-square',
                        'attachment': 'fas fa-paperclip',
                        'task_completed': 'fas fa-check-circle',
                        'task_moved': 'fas fa-arrows-alt'
                    };
                    return icons[type] || 'fas fa-bell';
                },
                
                getNotificationIconClass(type) {
                    const classes = {
                        'mention': 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                        'assignment': 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
                        'task_update': 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400',
                        'comment': 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
                        'due_date': 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                        'checklist': 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400',
                        'attachment': 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                        'task_completed': 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
                        'task_moved': 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400'
                    };
                    return classes[type] || 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
                },
                
                destroy() {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                    }
                }
            };
        }
    </script>
