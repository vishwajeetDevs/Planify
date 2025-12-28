// Theme toggle functionality
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Update class
    if (newTheme === 'dark') {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
    
    // Save to session - use absolute path for consistency
    fetch((window.BASE_PATH || '') + '/actions/theme/toggle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ theme: newTheme })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to save theme preference');
        }
    })
    .catch(err => console.error('Theme toggle error:', err));
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    // Theme is already set by PHP in the header
    // This just ensures consistency
    const savedTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    
    // Apply theme-specific styles if needed
    if (savedTheme === 'dark') {
        document.body.style.colorScheme = 'dark';
    } else {
        document.body.style.colorScheme = 'light';
    }
});