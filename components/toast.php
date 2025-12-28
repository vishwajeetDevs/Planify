<?php
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    $type = $toast['type'] ?? 'info';
    $message = $toast['message'] ?? '';
    
    // Define colors and icons based on type
    $styles = [
        'success' => [
            'bg' => 'bg-green-500',
            'icon' => 'fa-check-circle',
        ],
        'error' => [
            'bg' => 'bg-red-500',
            'icon' => 'fa-exclamation-circle',
        ],
        'warning' => [
            'bg' => 'bg-amber-500',
            'icon' => 'fa-exclamation-triangle',
        ],
        'info' => [
            'bg' => 'bg-blue-500',
            'icon' => 'fa-info-circle',
        ]
    ];
    
    $style = $styles[$type] ?? $styles['info'];
    ?>
    <div id="sessionToast" style="position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 99999; max-width: 400px; width: calc(100% - 2rem); animation: slideDown 0.3s ease-out;">
        <div class="<?php echo $style['bg']; ?> text-white rounded-xl px-5 py-3.5 shadow-2xl flex items-center gap-3" role="alert">
            <i class="fas <?php echo $style['icon']; ?> text-lg flex-shrink-0"></i>
            <p class="text-sm font-medium flex-1"><?php echo htmlspecialchars($message); ?></p>
            <button onclick="document.getElementById('sessionToast').remove()" class="text-white/80 hover:text-white transition-colors flex-shrink-0">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <style>
        @keyframes slideDown {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 1; transform: translateX(-50%) translateY(0); }
            to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }
    </style>
    <script>
        // Auto-remove toast after 4 seconds with slide up animation
        setTimeout(() => {
            const toast = document.getElementById('sessionToast');
            if (toast) {
                toast.style.animation = 'slideUp 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 4000);
    </script>
    <?php
    // Clear the toast after displaying
    unset($_SESSION['toast']);
}
?>