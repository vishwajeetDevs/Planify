<?php
session_start();
require_once '../config/db.php';

// Check if user needs verification
$userId = $_SESSION['pending_verification_user_id'] ?? null;
$email = $_SESSION['pending_verification_email'] ?? null;

if (!$userId || !$email) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/public/login.php');
    exit;
}

// Mask email for display
$emailParts = explode('@', $email);
$maskedEmail = substr($emailParts[0], 0, 2) . '***@' . $emailParts[1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Planify</title>
    
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
                        'pulse-slow': 'pulse 3s ease-in-out infinite',
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
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
            transition: all 0.3s ease;
        }
        
        .otp-input:focus {
            border-color: #4F46E5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
            transform: translateY(-2px);
        }
        
        .otp-input.filled {
            border-color: #4F46E5;
            background: white;
        }
        
        .otp-input.error {
            border-color: #ef4444;
            background: #fef2f2;
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
        
        <!-- Verification Card -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-white/50 animate-fade-in-up">
            
            <!-- Icon -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-indigo-100 rounded-full mb-4 animate-pulse-slow">
                    <i class="fas fa-envelope-open-text text-3xl text-primary"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Verify Your Email</h2>
                <p class="text-gray-600">
                    We've sent a 6-digit code to<br>
                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($maskedEmail); ?></span>
                </p>
            </div>
            
            <!-- OTP Input -->
            <form id="verifyForm" class="space-y-6">
                <div class="flex justify-center gap-3">
                    <input type="text" maxlength="1" class="otp-input" data-index="0" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                    <input type="text" maxlength="1" class="otp-input" data-index="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" class="otp-input" data-index="2" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" class="otp-input" data-index="3" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" class="otp-input" data-index="4" inputmode="numeric" pattern="[0-9]">
                    <input type="text" maxlength="1" class="otp-input" data-index="5" inputmode="numeric" pattern="[0-9]">
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    id="verifyBtn"
                    class="w-full bg-gradient-to-r from-primary to-indigo-600 text-white py-3.5 rounded-xl font-semibold hover:from-indigo-600 hover:to-primary transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    disabled
                >
                    <span class="flex items-center justify-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Verify Email
                    </span>
                </button>
            </form>
            
            <!-- Resend Code -->
            <div class="mt-6 text-center">
                <p class="text-gray-600 mb-2">Didn't receive the code?</p>
                <button 
                    id="resendBtn"
                    class="text-primary font-semibold hover:text-indigo-700 hover:underline transition-colors disabled:text-gray-400 disabled:no-underline"
                >
                    Resend Code
                </button>
                <span id="resendTimer" class="text-gray-500 text-sm hidden ml-2"></span>
            </div>
        </div>
        
        <!-- Back to Login -->
        <div class="text-center mt-6 animate-fade-in">
            <a href="login.php" class="inline-flex items-center text-gray-600 hover:text-primary transition-all duration-200 group">
                <i class="fas fa-arrow-left mr-2 transition-transform duration-200 group-hover:-translate-x-1"></i>
                Back to Login
            </a>
        </div>
    </div>
    
    <script>
        const inputs = document.querySelectorAll('.otp-input');
        const verifyForm = document.getElementById('verifyForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const resendBtn = document.getElementById('resendBtn');
        const resendTimer = document.getElementById('resendTimer');
        const toastContainer = document.getElementById('toastContainer');
        
        let resendCooldown = 0;
        
        // Toast notification system
        function showToast(message, type = 'info') {
            // Remove existing toasts
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
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideUp 0.3s ease-out forwards';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4000);
        }
        
        // Handle OTP input
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
                
                if (value) {
                    input.classList.add('filled');
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                } else {
                    input.classList.remove('filled');
                }
                
                checkComplete();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                pasteData.split('').forEach((char, i) => {
                    if (inputs[i]) {
                        inputs[i].value = char;
                        inputs[i].classList.add('filled');
                    }
                });
                checkComplete();
                if (pasteData.length === 6) {
                    inputs[5].focus();
                }
            });
        });
        
        function checkComplete() {
            const otp = getOTP();
            verifyBtn.disabled = otp.length !== 6;
        }
        
        function getOTP() {
            return Array.from(inputs).map(input => input.value).join('');
        }
        
        function setInputsError(error) {
            inputs.forEach(input => {
                if (error) {
                    input.classList.add('error');
                } else {
                    input.classList.remove('error');
                }
            });
        }
        
        // Verify form submission
        verifyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            setInputsError(false);
            
            const otp = getOTP();
            if (otp.length !== 6) return;
            
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Verifying...';
            
            try {
                const response = await fetch(window.BASE_PATH + '/actions/auth/verify-email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp, email: '<?php echo $email; ?>' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || 'login.php';
                    }, 1500);
                } else {
                    showToast(data.message, 'error');
                    setInputsError(true);
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Verify Email';
                }
            } catch (error) {
                showToast('An error occurred. Please try again.', 'error');
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Verify Email';
            }
        });
        
        // Resend OTP
        resendBtn.addEventListener('click', async () => {
            if (resendCooldown > 0) return;
            
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';
            
            try {
                const response = await fetch(window.BASE_PATH + '/actions/auth/send-verification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        user_id: <?php echo $userId; ?>, 
                        email: '<?php echo $email; ?>' 
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('New verification code sent!', 'success');
                    startResendCooldown(60);
                    // Clear inputs for new OTP
                    inputs.forEach(input => {
                        input.value = '';
                        input.classList.remove('filled', 'error');
                    });
                    inputs[0].focus();
                    verifyBtn.disabled = true;
                } else {
                    showToast(data.message, 'error');
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend Code';
                }
            } catch (error) {
                showToast('Failed to resend code. Please try again.', 'error');
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Code';
            }
        });
        
        function startResendCooldown(seconds) {
            resendCooldown = seconds;
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend Code';
            resendTimer.classList.remove('hidden');
            
            const interval = setInterval(() => {
                resendCooldown--;
                resendTimer.textContent = `(${resendCooldown}s)`;
                
                if (resendCooldown <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                    resendTimer.classList.add('hidden');
                }
            }, 1000);
            
            resendTimer.textContent = `(${resendCooldown}s)`;
        }
        
        // Auto-focus first input
        inputs[0].focus();
        
        // Start with cooldown (prevent immediate resend)
        startResendCooldown(30);
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

