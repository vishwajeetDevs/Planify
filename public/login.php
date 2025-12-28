<?php
session_start();
require_once '../config/db.php';

// Store redirect URL if provided
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

if (isset($_SESSION['user_id'])) {
    // If there's a redirect URL, go there instead of dashboard
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    if ($redirect) {
        header('Location: ' . $redirect);
    } else {
        header('Location: ' . $basePath . '/public/dashboard.php');
    }
    exit;
}

// Clear any previous error from session
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Planify</title>
    
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
            theme: {
                fontFamily: {
                    sans: ['Poppins', 'sans-serif'],
                },
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#3B82F6'
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'fade-in-down': 'fadeInDown 0.5s ease-out forwards',
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'slide-in-left': 'slideInLeft 0.5s ease-out forwards',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            'from': { opacity: '0', transform: 'translateY(20px)' },
                            'to': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeInDown: {
                            'from': { opacity: '0', transform: 'translateY(-20px)' },
                            'to': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeIn: {
                            'from': { opacity: '0' },
                            'to': { opacity: '1' },
                        },
                        slideInLeft: {
                            '0%': { opacity: '0', transform: 'translateX(-20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(5deg)' },
                        },
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .gradient-bg {
            background: linear-gradient(-45deg, #e0e7ff, #c7d2fe, #ddd6fe, #e0e7ff);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .input-focus {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.15);
        }
        
        .btn-primary {
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        /* Stagger animations */
        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }
        .stagger-5 { animation-delay: 0.5s; }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center font-sans antialiased relative overflow-hidden">
    
    <!-- Toast Notification -->
    <?php include '../components/toast.php'; ?>
    
    <!-- Floating background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-32 h-32 bg-indigo-200/30 rounded-full blur-xl animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute top-40 right-20 w-24 h-24 bg-blue-200/30 rounded-full blur-xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-20 left-1/4 w-40 h-40 bg-purple-200/30 rounded-full blur-xl animate-float" style="animation-delay: 4s;"></div>
        <div class="absolute bottom-40 right-10 w-28 h-28 bg-indigo-300/20 rounded-full blur-xl animate-float" style="animation-delay: 1s;"></div>
    </div>
    
    <div class="max-w-md w-full mx-4 relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8 animate-fade-in-down stagger-1">
            <a href="index.php" class="inline-block group">
                <h1 class="text-4xl font-bold text-primary mb-2 transition-transform duration-300 group-hover:scale-105">PLANIFY</h1>
            </a>
            <p class="text-gray-600">Sign in to your account</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-white/50 animate-fade-in-up stagger-2">
            
            <form action="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/actions/auth/login.php" method="POST" class="space-y-6">
                <?php if ($redirect): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <?php endif; ?>
                
                <!-- Email -->
                <div class="animate-fade-in-up stagger-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address
                    </label>
                    <div class="relative group">
                        <input 
                            type="email" 
                            name="email" 
                            required 
                            autocomplete="email"
                            class="input-focus w-full px-4 py-3.5 pl-11 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50/50 hover:bg-white"
                            placeholder="Enter your email"
                        >
                        <i class="fas fa-envelope absolute left-4 top-4 text-gray-400 transition-colors duration-200 group-focus-within:text-primary"></i>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="animate-fade-in-up stagger-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Password
                    </label>
                    <div class="relative group">
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            class="input-focus w-full px-4 py-3.5 pl-11 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50/50 hover:bg-white"
                            placeholder="Enter your password"
                        >
                        <i class="fas fa-lock absolute left-4 top-4 text-gray-400 transition-colors duration-200 group-focus-within:text-primary"></i>
                    </div>
                </div>
                
                <!-- Remember Me -->
                <div class="flex items-center justify-between animate-fade-in stagger-5">
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300 transition-transform duration-200 group-hover:scale-110">
                        <span class="ml-2 text-sm text-gray-600 group-hover:text-gray-900 transition-colors">Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="text-sm text-primary hover:text-indigo-700 hover:underline transition-colors">Forgot password?</a>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="btn-primary w-full bg-gradient-to-r from-primary to-indigo-600 text-white py-3.5 rounded-xl font-semibold hover:from-indigo-600 hover:to-primary transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl hover:shadow-primary/30 active:scale-[0.98] animate-fade-in-up"
                    style="animation-delay: 0.5s;"
                >
                    <span class="flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </span>
                </button>
            </form>
            
            <!-- Sign Up Link -->
            <div class="mt-6 text-center animate-fade-in" style="animation-delay: 0.6s;">
                <p class="text-gray-600">
                    Don't have an account? 
                    <a href="register.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>" class="text-primary font-semibold hover:text-indigo-700 hover:underline transition-colors">Sign up</a>
                </p>
            </div>
        </div>
        
        <!-- Back to Home -->
        <div class="text-center mt-6 animate-fade-in" style="animation-delay: 0.7s;">
            <a href="index.php" class="inline-flex items-center text-gray-600 hover:text-primary transition-all duration-200 group">
                <i class="fas fa-arrow-left mr-2 transition-transform duration-200 group-hover:-translate-x-1"></i>
                Back to Home
            </a>
        </div>
    </div>
    
</body>
</html>
