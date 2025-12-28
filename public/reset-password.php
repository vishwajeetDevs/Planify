<?php
session_start();
require_once '../config/db.php';

$basePath = defined('BASE_PATH') ? BASE_PATH : '';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $basePath . '/public/dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!$token || !$email) {
    header('Location: ' . $basePath . '/public/forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Planify</title>
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
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
                        float: {
                            '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(5deg)' },
                        },
                    }
                }
            }
        }
    </script>
    
    <!-- Base Path for API calls -->
    <script>
        window.BASE_PATH = '<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>';
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
        
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center font-sans antialiased relative overflow-hidden">
    
    <!-- Toast Container -->
    <div id="toastContainer" style="position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 99999; width: calc(100% - 2rem); max-width: 400px;"></div>
    
    <!-- Floating background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-32 h-32 bg-indigo-200/30 rounded-full blur-xl animate-float" style="animation-delay: 0s;"></div>
        <div class="absolute top-40 right-20 w-24 h-24 bg-blue-200/30 rounded-full blur-xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-20 left-1/4 w-40 h-40 bg-purple-200/30 rounded-full blur-xl animate-float" style="animation-delay: 4s;"></div>
    </div>
    
    <div class="max-w-md w-full mx-4 relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8 animate-fade-in-down">
            <a href="index.php" class="inline-block group">
                <h1 class="text-4xl font-bold text-primary mb-2 transition-transform duration-300 group-hover:scale-105">PLANIFY</h1>
            </a>
        </div>
        
        <!-- Reset Password Card -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-white/50 animate-fade-in-up">
            
            <!-- Icon -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-4">
                    <i class="fas fa-lock-open text-2xl text-primary"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Create New Password</h2>
                <p class="text-gray-600">Your new password must be different from previously used passwords.</p>
            </div>
            
            <!-- Form -->
            <div id="formContainer">
                <form id="resetForm" class="space-y-5">
                    <!-- New Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            New Password
                        </label>
                        <div class="relative group">
                            <input 
                                type="password" 
                                id="password"
                                name="password" 
                                required 
                                minlength="6"
                                autocomplete="new-password"
                                class="input-focus w-full px-4 py-3.5 pl-11 pr-11 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50/50 hover:bg-white"
                                placeholder="Enter new password"
                            >
                            <i class="fas fa-lock absolute left-4 top-4 text-gray-400 transition-colors duration-200 group-focus-within:text-primary"></i>
                            <button type="button" onclick="togglePassword('password')" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                        <!-- Password Strength -->
                        <div class="mt-2">
                            <div class="flex gap-1">
                                <div class="strength-bar flex-1 bg-gray-200" id="strength1"></div>
                                <div class="strength-bar flex-1 bg-gray-200" id="strength2"></div>
                                <div class="strength-bar flex-1 bg-gray-200" id="strength3"></div>
                                <div class="strength-bar flex-1 bg-gray-200" id="strength4"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1" id="strengthText">Enter password</p>
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <div class="relative group">
                            <input 
                                type="password" 
                                id="confirmPassword"
                                name="confirm_password" 
                                required 
                                minlength="6"
                                autocomplete="new-password"
                                class="input-focus w-full px-4 py-3.5 pl-11 pr-11 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50/50 hover:bg-white"
                                placeholder="Confirm new password"
                            >
                            <i class="fas fa-lock absolute left-4 top-4 text-gray-400 transition-colors duration-200 group-focus-within:text-primary"></i>
                            <button type="button" onclick="togglePassword('confirmPassword')" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                            </button>
                        </div>
                        <p class="text-xs text-red-500 mt-1 hidden" id="matchError">Passwords do not match</p>
                    </div>
                    
                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="w-full bg-gradient-to-r from-primary to-indigo-600 text-white py-3.5 rounded-xl font-semibold hover:from-indigo-600 hover:to-primary transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span class="flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i>
                            Reset Password
                        </span>
                    </button>
                </form>
            </div>
            
            <!-- Success State -->
            <div id="successContainer" class="hidden text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-check text-3xl text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Password Reset Successful!</h3>
                <p class="text-gray-600 mb-6">
                    Your password has been reset successfully. You can now login with your new password.
                </p>
                <a href="login.php" class="inline-flex items-center justify-center w-full bg-gradient-to-r from-primary to-indigo-600 text-white py-3 rounded-xl font-semibold hover:from-indigo-600 hover:to-primary transition-all">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Go to Login
                </a>
            </div>
            
            <!-- Back to Login Link -->
            <div id="backLink" class="mt-6 text-center">
                <a href="login.php" class="text-primary font-semibold hover:text-indigo-700 hover:underline transition-colors">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script>
        const resetForm = document.getElementById('resetForm');
        const submitBtn = document.getElementById('submitBtn');
        const formContainer = document.getElementById('formContainer');
        const successContainer = document.getElementById('successContainer');
        const backLink = document.getElementById('backLink');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const matchError = document.getElementById('matchError');
        const toastContainer = document.getElementById('toastContainer');
        
        // Toast notification system
        function showToast(message, type = 'info') {
            toastContainer.innerHTML = '';
            
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-amber-500',
                info: 'bg-blue-500'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const toast = document.createElement('div');
            toast.className = `${colors[type] || colors.info} text-white rounded-xl px-5 py-3.5 shadow-2xl flex items-center gap-3`;
            toast.style.animation = 'slideDown 0.3s ease-out';
            toast.innerHTML = `
                <i class="fas ${icons[type] || icons.info} text-lg flex-shrink-0"></i>
                <p class="text-sm font-medium flex-1">${message}</p>
                <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white transition-colors flex-shrink-0">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideUp 0.3s ease-out forwards';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4000);
        }
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + 'ToggleIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return Math.min(strength, 4);
        }
        
        function updateStrengthIndicator(strength) {
            const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
            const texts = ['Weak', 'Fair', 'Good', 'Strong'];
            
            for (let i = 1; i <= 4; i++) {
                const bar = document.getElementById('strength' + i);
                bar.className = 'strength-bar flex-1 ' + (i <= strength ? colors[strength - 1] : 'bg-gray-200');
            }
            
            document.getElementById('strengthText').textContent = strength > 0 ? texts[strength - 1] : 'Enter password';
        }
        
        passwordInput.addEventListener('input', () => {
            const strength = checkPasswordStrength(passwordInput.value);
            updateStrengthIndicator(strength);
            checkMatch();
        });
        
        confirmPasswordInput.addEventListener('input', checkMatch);
        
        function checkMatch() {
            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                matchError.classList.remove('hidden');
                confirmPasswordInput.classList.add('border-red-300');
            } else {
                matchError.classList.add('hidden');
                confirmPasswordInput.classList.remove('border-red-300');
            }
        }
        
        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password.length < 6) {
                showToast('Password must be at least 6 characters', 'error');
                return;
            }
            
            if (password !== confirmPassword) {
                showToast('Passwords do not match', 'error');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting...';
            
            try {
                const response = await fetch(window.BASE_PATH + '/actions/auth/reset-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: '<?php echo htmlspecialchars($token); ?>',
                        email: '<?php echo htmlspecialchars($email); ?>',
                        password: password,
                        confirm_password: confirmPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Password reset successful!', 'success');
                    // Show success state
                    formContainer.classList.add('hidden');
                    backLink.classList.add('hidden');
                    successContainer.classList.remove('hidden');
                } else {
                    showToast(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Reset Password';
                }
            } catch (error) {
                showToast('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Reset Password';
            }
        });
    </script>
    
    <style>
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
    </style>
</body>
</html>

