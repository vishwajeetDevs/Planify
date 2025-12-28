// Global variable to store current card ID
window.currentCardId = null;

/* =====================================================
   PERFORMANCE UTILITIES
   ===================================================== */

// Debounce function to prevent rapid repeated calls
window.debounce = function(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Throttle function to limit call frequency
window.throttle = function(func, limit = 100) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

// Request deduplication - prevents duplicate API calls
window.pendingRequests = new Map();
window.deduplicatedFetch = async function(url, options = {}) {
    const key = `${options.method || 'GET'}:${url}`;
    
    // If there's already a pending request for this URL, return that promise
    if (window.pendingRequests.has(key)) {
        return window.pendingRequests.get(key);
    }
    
    // Create the request promise
    const requestPromise = fetch(url, options)
        .then(response => {
            window.pendingRequests.delete(key);
            return response;
        })
        .catch(error => {
            window.pendingRequests.delete(key);
            throw error;
        });
    
    // Store the promise
    window.pendingRequests.set(key, requestPromise);
    
    return requestPromise;
};

/* =====================================================
   CSRF TOKEN UTILITY
   ===================================================== */

// Get CSRF token from meta tag
window.getCSRFToken = function() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
};

// Enhanced fetch with CSRF token automatically included
window.secureFetch = function(url, options = {}) {
    const csrfToken = window.getCSRFToken();
    
    // Default options
    options.credentials = options.credentials || 'same-origin';
    
    // Add CSRF token to headers for non-GET requests
    if (options.method && options.method.toUpperCase() !== 'GET') {
        options.headers = options.headers || {};
        options.headers['X-CSRF-TOKEN'] = csrfToken;
        
        // If sending FormData, add CSRF token to it
        if (options.body instanceof FormData) {
            options.body.append('_token', csrfToken);
        }
        // If sending JSON, add CSRF token to the body
        else if (options.headers['Content-Type'] === 'application/json' && options.body) {
            try {
                const data = JSON.parse(options.body);
                data._token = csrfToken;
                options.body = JSON.stringify(data);
            } catch (e) {
                // Not JSON, skip
            }
        }
    }
    
    return fetch(url, options);
};

/* =====================================================
   BUTTON LOADING STATE UTILITY
   ===================================================== */

/**
 * Button Loading Manager
 * Adds loading state to buttons during async operations
 */
window.ButtonLoader = {
    // Store original button states
    buttonStates: new Map(),
    
    /**
     * Set button to loading state
     * @param {string|Element} button - Button selector or element
     * @param {string} loadingText - Optional loading text (default: 'Loading...')
     * @returns {string} - Button ID for later reference
     */
    start: function(button, loadingText = null) {
        const btn = typeof button === 'string' ? document.querySelector(button) : button;
        if (!btn) return null;
        
        const id = btn.id || `btn-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        if (!btn.id) btn.id = id;
        
        // Store original state
        this.buttonStates.set(id, {
            innerHTML: btn.innerHTML,
            disabled: btn.disabled,
            width: btn.offsetWidth
        });
        
        // Set minimum width to prevent button from shrinking
        btn.style.minWidth = btn.offsetWidth + 'px';
        
        // Disable button
        btn.disabled = true;
        btn.classList.add('btn-loading');
        
        // Set loading content
        const spinner = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
        
        if (loadingText) {
            btn.innerHTML = spinner + loadingText;
        } else {
            // Try to keep icon if present, otherwise show spinner
            const hasIcon = btn.querySelector('i, svg');
            if (hasIcon) {
                btn.innerHTML = spinner + (btn.textContent.trim() || '');
            } else {
                btn.innerHTML = spinner + 'Loading...';
            }
        }
        
        return id;
    },
    
    /**
     * Reset button to original state
     * @param {string|Element} button - Button selector, element, or ID from start()
     */
    stop: function(button) {
        let btn, id;
        
        if (typeof button === 'string') {
            // Check if it's an ID we stored
            if (this.buttonStates.has(button)) {
                id = button;
                btn = document.getElementById(id);
            } else {
                btn = document.querySelector(button);
                id = btn?.id;
            }
        } else {
            btn = button;
            id = btn?.id;
        }
        
        if (!btn || !id) return;
        
        const state = this.buttonStates.get(id);
        if (state) {
            btn.innerHTML = state.innerHTML;
            btn.disabled = state.disabled;
            btn.style.minWidth = '';
            btn.classList.remove('btn-loading');
            this.buttonStates.delete(id);
        }
    },
    
    /**
     * Wrap an async function with button loading state
     * @param {string|Element} button - Button to show loading on
     * @param {Function} asyncFn - Async function to execute
     * @param {string} loadingText - Optional loading text
     */
    wrap: async function(button, asyncFn, loadingText = null) {
        const id = this.start(button, loadingText);
        try {
            return await asyncFn();
        } finally {
            this.stop(id);
        }
    }
};

// Shorthand functions for convenience
window.btnLoading = (btn, text) => window.ButtonLoader.start(btn, text);
window.btnReset = (btn) => window.ButtonLoader.stop(btn);
window.btnWrap = (btn, fn, text) => window.ButtonLoader.wrap(btn, fn, text);

/* =====================================================
   SKELETON LOADING SYSTEM - JavaScript Orchestration
   ===================================================== */

/**
 * Skeleton Loading Manager
 * Handles showing/hiding skeletons during async operations
 */
window.SkeletonManager = {
    // Track active skeletons
    activeSkeletons: new Map(),
    
    /**
     * Show skeleton for a container
     * @param {string|Element} container - Container selector or element
     * @param {string} type - Skeleton type (card, list, workspace, etc.)
     * @param {number} count - Number of skeleton items
     * @param {object} options - Additional options
     */
    show: function(container, type = 'card', count = 3, options = {}) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return null;
        
        const id = options.id || `skeleton-${Date.now()}`;
        
        // Create skeleton wrapper
        const skeletonWrapper = document.createElement('div');
        skeletonWrapper.className = `skeleton-wrapper skeleton-transition is-loading ${options.class || ''}`;
        skeletonWrapper.id = id;
        skeletonWrapper.setAttribute('aria-busy', 'true');
        skeletonWrapper.setAttribute('aria-label', 'Loading content');
        skeletonWrapper.setAttribute('role', 'status');
        
        // Generate skeleton HTML based on type
        skeletonWrapper.innerHTML = this.generateSkeletonHTML(type, count, options);
        
        // Store original content if replacing
        if (options.replace) {
            this.activeSkeletons.set(id, {
                container: el,
                originalContent: el.innerHTML,
                wrapper: skeletonWrapper
            });
            el.innerHTML = '';
        }
        
        // Insert skeleton
        if (options.prepend) {
            el.insertBefore(skeletonWrapper, el.firstChild);
        } else if (options.replace) {
            el.appendChild(skeletonWrapper);
        } else {
            el.appendChild(skeletonWrapper);
        }
        
        return id;
    },
    
    /**
     * Hide skeleton and optionally restore/show content
     * @param {string} id - Skeleton ID
     * @param {object} options - Options for hiding
     */
    hide: function(id, options = {}) {
        const skeletonEl = document.getElementById(id);
        if (!skeletonEl) return;
        
        // Add exit animation
        skeletonEl.classList.remove('is-loading');
        skeletonEl.classList.add('is-loaded');
        
        // Remove after animation
        setTimeout(() => {
            skeletonEl.remove();
            
            // Restore original content if needed
            const stored = this.activeSkeletons.get(id);
            if (stored && options.restore) {
                stored.container.innerHTML = stored.originalContent;
            }
            
            this.activeSkeletons.delete(id);
        }, options.delay || 200);
    },
    
    /**
     * Hide all skeletons in a container
     * @param {string|Element} container - Container selector or element
     */
    hideAll: function(container) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return;
        
        const skeletons = el.querySelectorAll('.skeleton-wrapper');
        skeletons.forEach(skeleton => {
            skeleton.classList.remove('is-loading');
            skeleton.classList.add('is-loaded');
            setTimeout(() => skeleton.remove(), 200);
        });
    },
    
    /**
     * Generate skeleton HTML for different types
     * @param {string} type - Skeleton type
     * @param {number} count - Number of items
     * @param {object} options - Additional options
     */
    generateSkeletonHTML: function(type, count, options = {}) {
        const shimmer = 'skeleton-shimmer';
        let html = '';
        
        switch (type) {
            case 'card':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="skeleton-card p-4 mb-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm" style="animation-delay: ${i * 100}ms;">
                            <div class="space-y-3">
                                <div class="${shimmer} h-5 w-4/5 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-3 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="flex items-center justify-between pt-2">
                                    <div class="flex items-center gap-2">
                                        <div class="${shimmer} h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                                        <div class="${shimmer} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="${shimmer} h-5 w-8 rounded bg-gray-200 dark:bg-gray-700"></div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                }
                break;
                
            case 'card-mini':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="p-3 mb-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700" style="animation-delay: ${i * 80}ms;">
                            <div class="${shimmer} h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                            <div class="${shimmer} h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
                        </div>`;
                }
                break;
                
            case 'workspace':
            case 'board':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="skeleton-workspace bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 h-40 flex flex-col border border-gray-100 dark:border-gray-700" style="animation-delay: ${i * 100}ms;">
                            <div class="flex-1">
                                <div class="${shimmer} h-5 w-3/4 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
                                <div class="flex items-center gap-2">
                                    <div class="${shimmer} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                                    <div class="${shimmer} h-3 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <div class="${shimmer} h-3 w-3 rounded bg-gray-200 dark:bg-gray-700"></div>
                                    <div class="${shimmer} h-3 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
                                </div>
                            </div>
                        </div>`;
                }
                break;
                
            case 'list':
                for (let i = 0; i < count; i++) {
                    const cardCount = Math.floor(Math.random() * 3) + 2;
                    let cardsHtml = '';
                    for (let j = 0; j < cardCount; j++) {
                        cardsHtml += `
                            <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700" style="animation-delay: ${(i * 150) + (j * 80)}ms;">
                                <div class="${shimmer} h-4 w-4/5 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                                <div class="${shimmer} h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>`;
                    }
                    html += `
                        <div class="skeleton-list flex-shrink-0 w-72 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-3" style="animation-delay: ${i * 150}ms;">
                            <div class="flex items-center justify-between mb-3 px-1">
                                <div class="${shimmer} h-5 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-6 w-6 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                            <div class="space-y-2 min-h-[100px]">${cardsHtml}</div>
                            <div class="mt-3 pt-2">
                                <div class="${shimmer} h-9 w-full rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        </div>`;
                }
                break;
                
            case 'comment':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="flex gap-3 p-3" style="animation-delay: ${i * 100}ms;">
                            <div class="${shimmer} h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex-shrink-0"></div>
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="${shimmer} h-4 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
                                    <div class="${shimmer} h-3 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
                                </div>
                                <div class="${shimmer} h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-3 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        </div>`;
                }
                break;
                
            case 'activity':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="flex items-start gap-3 py-3 border-b border-gray-100 dark:border-gray-700" style="animation-delay: ${i * 80}ms;">
                            <div class="${shimmer} h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex-shrink-0"></div>
                            <div class="flex-1 space-y-1">
                                <div class="${shimmer} h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        </div>`;
                }
                break;
                
            case 'member':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50" style="animation-delay: ${i * 80}ms;">
                            <div class="${shimmer} h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-1">
                                <div class="${shimmer} h-4 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-3 w-40 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                            <div class="${shimmer} h-6 w-16 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        </div>`;
                }
                break;
                
            case 'search':
                for (let i = 0; i < count; i++) {
                    html += `
                        <div class="flex items-center gap-3 p-3 rounded-lg" style="animation-delay: ${i * 60}ms;">
                            <div class="${shimmer} h-8 w-8 rounded bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-1">
                                <div class="${shimmer} h-4 w-48 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="${shimmer} h-3 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        </div>`;
                }
                break;
                
            case 'text':
                for (let i = 0; i < count; i++) {
                    const widths = ['w-full', 'w-3/4', 'w-1/2', 'w-2/3', 'w-4/5'];
                    html += `<div class="${shimmer} h-4 ${widths[i % widths.length]} rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>`;
                }
                break;
                
            default:
                html = `<div class="${shimmer} h-20 w-full rounded bg-gray-200 dark:bg-gray-700"></div>`;
        }
        
        return html;
    },
    
    /**
     * Wrap a fetch call with skeleton loading
     * @param {string|Element} container - Container for skeleton
     * @param {string} type - Skeleton type
     * @param {Promise} fetchPromise - The fetch promise
     * @param {object} options - Additional options
     */
    wrapFetch: async function(container, type, fetchPromise, options = {}) {
        const skeletonId = this.show(container, type, options.count || 3, options);
        
        try {
            const result = await fetchPromise;
            this.hide(skeletonId, { delay: options.hideDelay || 200 });
            return result;
        } catch (error) {
            this.hide(skeletonId, { delay: 100 });
            throw error;
        }
    }
};

/**
 * Helper function to show loading state on a button
 * @param {Element} button - Button element
 * @param {string} loadingText - Text to show while loading
 */
window.setButtonLoading = function(button, loadingText = 'Loading...') {
    if (!button) return;
    
    button.dataset.originalContent = button.innerHTML;
    button.disabled = true;
    button.classList.add('btn-loading');
    button.innerHTML = `<span class="opacity-0">${loadingText}</span>`;
};

/**
 * Reset button from loading state
 * @param {Element} button - Button element
 */
window.resetButtonLoading = function(button) {
    if (!button) return;
    
    button.disabled = false;
    button.classList.remove('btn-loading');
    if (button.dataset.originalContent) {
        button.innerHTML = button.dataset.originalContent;
        delete button.dataset.originalContent;
    }
};

/**
 * Initialize skeleton loading for AJAX navigation
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add loading indicator for page transitions
    const pageLoader = document.createElement('div');
    pageLoader.id = 'pageLoader';
    pageLoader.className = 'fixed top-0 left-0 right-0 h-1 bg-primary transform -translate-x-full transition-transform duration-300 z-[9999]';
    pageLoader.style.display = 'none';
    document.body.appendChild(pageLoader);
    
    // Show page loader on navigation
    window.showPageLoader = function() {
        pageLoader.style.display = 'block';
        setTimeout(() => {
            pageLoader.style.transform = 'translateX(-20%)';
        }, 10);
    };
    
    window.hidePageLoader = function() {
        pageLoader.style.transform = 'translateX(0)';
        setTimeout(() => {
            pageLoader.style.display = 'none';
            pageLoader.style.transform = 'translateX(-100%)';
        }, 300);
    };
    
    // Intercept form submissions to show loading
    document.querySelectorAll('form[data-skeleton]').forEach(form => {
        form.addEventListener('submit', function() {
            const container = form.dataset.skeletonContainer || form;
            const type = form.dataset.skeletonType || 'text';
            window.SkeletonManager.show(container, type, 3, { replace: true });
        });
    });
});

// Function to show card details
window.showCardDetails = function(cardId) {
    if (!cardId) {
        console.error('No card ID provided');
        return;
    }
    
    window.currentCardId = cardId;
    const modal = document.getElementById('cardModal');
    const modalContent = document.getElementById('cardModalContent');
    
    // Reset form and show loading state
    modalContent.classList.add('opacity-0');
    modalContent.style.transform = 'scale(0.95)';
    modal.classList.remove('hidden');
    
    // Show loading state
    document.getElementById('cardModalTitle').textContent = 'Loading...';
    document.getElementById('cardDescription').innerHTML = '<div class="flex justify-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div></div>';
    
    // Load card details
    loadCardDetails(cardId);
    
    // Animate in
    setTimeout(() => {
        modalContent.classList.remove('opacity-0', 'scale-95');
        modalContent.classList.add('opacity-100', 'scale-100');
    }, 10);
};

// Function to close card modal
window.closeCardModal = function() {
    const modal = document.getElementById('cardModal');
    const modalContent = document.getElementById('cardModalContent');
    
    modalContent.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        // Reset modal state
        document.getElementById('cardModalTitle').textContent = '';
        document.getElementById('cardDescription').innerHTML = '';
        document.getElementById('commentsContainer').innerHTML = '';
        document.getElementById('commentInput').value = '';
        
        // Reset description editor state
        const descriptionEl = document.getElementById('cardDescription');
        const editorEl = document.getElementById('descriptionEditor');
        const descriptionInput = document.getElementById('descriptionInput');
        
        if (descriptionEl) {
            descriptionEl.classList.remove('hidden');
        }
        if (editorEl) {
            editorEl.classList.add('hidden');
        }
        if (descriptionInput) {
            descriptionInput.value = '';
        }
        
        window.currentCardId = null;
    }, 300);
};

// NOTE: loadCardDetails is now defined in card_modal.php with viewer tracking functionality
// Do not duplicate here - the card_modal.php version includes:
// - Viewer tracking (trackCardView)
// - Viewers list loading (loadCardViewers)  
// - Comments loading
// - Mention system initialization

// Handle escape key for closing modal
function handleEscapeKey(event) {
    if (event.key === 'Escape' && window.closeCardModal) {
        window.closeCardModal();
    }
}

// Close modal when clicking on the overlay
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('cardModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal && window.closeCardModal) {
                window.closeCardModal();
            }
        });
    }
});

// NOTE: addComment function is defined in card_modal.php with file attachment support
// Do not define it here to avoid overwriting

// Utility function to escape HTML to prevent XSS
window.escapeHtml = function(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

// Utility function to render markdown text (bold, italic, code)
window.renderMarkdownText = function(text) {
    if (!text) return '';
    let html = window.escapeHtml(text);
    // Bold: **text**
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // Italic: *text* (single asterisks only)
    html = html.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
    // Code: `text`
    html = html.replace(/`([^`\n]+)`/g, '<code class="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono">$1</code>');
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    return html;
};

// Render comment with markdown and mention highlighting
function renderCommentWithMarkdown(content, mentionedUsers) {
    if (!content) return '';
    
    // First render markdown (bold, italic, code, line breaks)
    let html = window.renderMarkdownText(content);
    
    // Highlight mentions
    if (mentionedUsers && mentionedUsers.length > 0) {
        mentionedUsers.forEach(user => {
            if (user && user.name) {
                const escapedName = window.escapeHtml(user.name);
                const pattern = new RegExp(`@${escapedName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`, 'gi');
                html = html.replace(pattern, `<span class="mention-link text-primary font-medium bg-primary/10 px-1 rounded">@${escapedName}</span>`);
            }
        });
    }
    // Also highlight @mentions that weren't matched to known users
    html = html.replace(/@(\w+)/g, (match, name) => {
        if (html.indexOf(`mention-link">@${name}`) === -1 && html.indexOf(`mention-link">@${name.toLowerCase()}`) === -1) {
            return `<span class="mention-link text-primary font-medium bg-primary/10 px-1 rounded">${match}</span>`;
        }
        return match;
    });
    
    return html;
}

// Prepend a new comment to the comments container
window.prependComment = function(comment) {
    if (window.DEBUG_MODE) console.log('Prepending comment:', comment, 'currentUserId:', window.currentUserId);
    
    let container = document.getElementById('commentsContainer');
    if (!container) return;
    
    // The current user ID should already be set from header.php
    // But as a fallback, if this is the user's own comment, set it
    if (!window.currentUserId && comment.user_id) {
        window.currentUserId = parseInt(comment.user_id, 10);
    }
    
    // Check if current user owns this comment (convert both to integers)
    const currentUserId = window.currentUserId ? parseInt(window.currentUserId, 10) : null;
    const commentUserId = parseInt(comment.user_id, 10);
    const isOwner = currentUserId && (commentUserId === currentUserId);
    
    if (window.DEBUG_MODE) console.log('isOwner check:', isOwner, 'comment.user_id:', commentUserId, 'currentUserId:', currentUserId);
    
    // Create comment element
    const commentEl = document.createElement('div');
    commentEl.className = 'comment-item flex gap-2 relative comment-enter';
    commentEl.id = `comment-${comment.id}`;
    
    // Format the date
    const commentDate = new Date(comment.created_at);
    const formattedDate = commentDate.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Set the inner HTML
    commentEl.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-7 w-7 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center text-primary dark:text-primary-300 font-medium text-xs">
                ${comment.user_name ? comment.user_name.charAt(0).toUpperCase() : 'U'}
            </div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="bg-gray-50 dark:bg-gray-800 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-750 transition-colors">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2">
                            <span class="text-xs font-semibold text-gray-900 dark:text-white">${window.escapeHtml(comment.user_name || 'User')}</span>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400">${formattedDate}</span>
                        </div>
                    </div>
                    ${isOwner ? `
                        <div class="comment-actions flex gap-1.5">
                            <button onclick="editComment(${comment.id}, '${window.escapeHtml(comment.content).replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" 
                                    class="p-1 text-xs text-gray-400 hover:text-primary dark:text-gray-500 dark:hover:text-indigo-400 hover:bg-white dark:hover:bg-gray-700 rounded transition-all"
                                    title="Edit comment">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteComment(${comment.id})" 
                                    class="p-1 text-xs text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400 hover:bg-white dark:hover:bg-gray-700 rounded transition-all"
                                    title="Delete comment">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    ` : ''}
                </div>
                <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed" id="comment-content-${comment.id}">
                    ${renderCommentWithMarkdown(comment.content, comment.mentioned_users || [])}
                </p>
            </div>
        </div>
    `;
    
    // Prepend to container
    if (container.firstChild) {
        container.insertBefore(commentEl, container.firstChild);
    } else {
        container.appendChild(commentEl);
    }
};

// Modern Dynamic Island-style toast notification - compact and centered at top
window.showToast = function(message, type = 'info') {
    // Create or get the top-center toast container
    let toastContainer = document.getElementById('dynamicIslandContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'dynamicIslandContainer';
        toastContainer.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[9999] flex flex-col items-center gap-2 pointer-events-none';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    
    // Color schemes for Dynamic Island style (dark background with colored accents)
    const styles = {
        success: {
            bg: 'bg-gray-900/95 dark:bg-gray-950/95',
            accent: 'text-emerald-400',
            icon: 'fa-check',
            glow: 'shadow-emerald-500/20'
        },
        error: {
            bg: 'bg-gray-900/95 dark:bg-gray-950/95',
            accent: 'text-red-400',
            icon: 'fa-xmark',
            glow: 'shadow-red-500/20'
        },
        info: {
            bg: 'bg-gray-900/95 dark:bg-gray-950/95',
            accent: 'text-blue-400',
            icon: 'fa-info',
            glow: 'shadow-blue-500/20'
        },
        warning: {
            bg: 'bg-gray-900/95 dark:bg-gray-950/95',
            accent: 'text-amber-400',
            icon: 'fa-exclamation',
            glow: 'shadow-amber-500/20'
        }
    };
    
    const style = styles[type] || styles.info;
    
    // Dynamic Island inspired design - compact pill shape
    toast.className = `
        ${style.bg} backdrop-blur-xl
        text-white text-sm font-medium
        px-4 py-2.5 rounded-full
        shadow-lg ${style.glow}
        flex items-center gap-2.5
        pointer-events-auto
        transition-all duration-300 ease-out
        border border-white/10
    `.replace(/\s+/g, ' ').trim();
    
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    
    toast.innerHTML = `
        <span class="flex items-center justify-center w-5 h-5 rounded-full ${style.accent} bg-white/10">
            <i class="fas ${style.icon} text-xs"></i>
        </span>
        <span class="text-white/90 whitespace-nowrap">${message}</span>
    `;
    
    // Set initial state for animation
    toast.style.transform = 'scale(0.5)';
    toast.style.opacity = '0';
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Trigger entrance animation after a microtask (Dynamic Island expand effect)
    setTimeout(() => {
        toast.style.transform = 'scale(1)';
        toast.style.opacity = '1';
    }, 10);
    
    // Auto-dismiss with smooth exit (longer duration for better visibility)
    const dismissTime = type === 'error' ? 5000 : 3000;
    let dismissTimer = setTimeout(() => dismissToast(), dismissTime);
    
    function dismissToast() {
        // Dynamic Island shrink effect
        toast.style.transform = 'scale(0.8)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }
    
    // Allow click to dismiss
    toast.addEventListener('click', () => {
        clearTimeout(dismissTimer);
        dismissToast();
    });
    
    // Pause on hover
    toast.addEventListener('mouseenter', () => clearTimeout(dismissTimer));
    toast.addEventListener('mouseleave', () => {
        dismissTimer = setTimeout(() => dismissToast(), 1000);
    });
    
    return toast;
}

// Global search functionality
const searchInput = document.getElementById('globalSearch');
const searchResults = document.getElementById('searchResults');

// Only initialize search if both elements exist (fixes console errors on pages without search)
if (searchInput && searchResults) {
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }
        
        // Get optional filters from data attributes
        const workspaceId = searchInput.dataset.workspaceId || '';
        const boardId = searchInput.dataset.boardId || '';
        
        // Build search URL with filters
        let searchUrl = `${window.BASE_PATH || ''}/actions/search.php?q=${encodeURIComponent(query)}`;
        if (workspaceId) searchUrl += `&workspace_id=${workspaceId}`;
        if (boardId) searchUrl += `&board_id=${boardId}`;
        
        searchTimeout = setTimeout(() => {
            // Show loading state
            searchResults.innerHTML = `
                <div class="px-4 py-3 text-gray-500 dark:text-gray-400 flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </div>`;
            searchResults.classList.remove('hidden');
            
            fetch(searchUrl)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.results.length > 0) {
                        searchResults.innerHTML = `
                            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600 text-xs font-medium text-gray-500 dark:text-gray-400">
                                ${data.count} result${data.count !== 1 ? 's' : ''} found
                            </div>
                            ${data.results.map(card => `
                                <a href="${window.BASE_PATH || ''}/public/board.php?ref=${card.board_ref}&card=${card.id}" 
                                   class="block px-4 py-3 hover:bg-primary/5 dark:hover:bg-primary/10 border-b border-gray-100 dark:border-gray-700 last:border-b-0 transition-colors duration-150">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900 dark:text-white truncate">${escapeHtml(card.title)}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                <span class="text-primary">${escapeHtml(card.workspace_name)}</span>
                                                <span class="mx-1">›</span>
                                                <span>${escapeHtml(card.board_name)}</span>
                                                <span class="mx-1">›</span>
                                                <span>${escapeHtml(card.list_name)}</span>
                                            </div>
                                            ${card.assignees ? `<div class="text-xs text-gray-400 dark:text-gray-500 mt-1"><i class="fas fa-user text-[10px] mr-1"></i>${escapeHtml(card.assignees)}</div>` : ''}
                                        </div>
                                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                            ${card.priority_label ? `<span class="text-[10px] px-1.5 py-0.5 rounded ${getPriorityClass(card.priority)}">${card.priority_label}</span>` : ''}
                                            ${card.due_date_formatted ? `<span class="text-[10px] ${card.is_overdue ? 'text-red-500' : 'text-gray-400'}">${card.due_date_formatted}</span>` : ''}
                                        </div>
                                    </div>
                                </a>
                            `).join('')}
                        `;
                        searchResults.classList.remove('hidden');
                    } else {
                        searchResults.innerHTML = `
                            <div class="px-4 py-6 text-center">
                                <i class="fas fa-search text-2xl text-gray-300 dark:text-gray-600 mb-2"></i>
                                <div class="text-gray-500 dark:text-gray-400">No tasks found</div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Try a different search term</div>
                            </div>`;
                        searchResults.classList.remove('hidden');
                    }
                })
                .catch(err => {
                    console.error('Search error:', err);
                    searchResults.innerHTML = '<div class="px-4 py-3 text-red-500">Search failed. Please try again.</div>';
                    searchResults.classList.remove('hidden');
                });
        }, 300);
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });
    
    // Close on Escape key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.classList.add('hidden');
            searchInput.blur();
        }
    });
}

// Helper function for priority badge colors (based on deadline proximity)
function getPriorityClass(priority) {
    switch (priority) {
        case 'overdue': return 'bg-red-200 text-red-800 dark:bg-red-900/50 dark:text-red-200';
        case 'high': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'medium': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'low': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        default: return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
    }
}

// Escape HTML helper (if not already defined)
if (typeof escapeHtml !== 'function') {
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Board page functions
function toggleActivity() {
    const sidebar = document.getElementById('activitySidebar');
    sidebar.classList.toggle('hidden');
}

// Show add list modal
function showAddListModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Add List</h3>
            <form onsubmit="createList(event)">
                <input type="hidden" name="board_id" value="${boardId}">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">List Name</label>
                    <input 
                        type="text" 
                        name="title" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                        placeholder="e.g., Backlog"
                    >
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-indigo-700">
                        Create
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Create list
function createList(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    fetch((window.BASE_PATH || '') + '/actions/list/create.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken || ''
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('List created successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to create list', 'error');
        }
    })
    .catch(err => {
        showToast('An error occurred', 'error');
    });
}

// Delete list
function deleteList(listId) {
    if (!confirm('Are you sure you want to delete this list? All cards in this list will be deleted.')) {
        return;
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    fetch((window.BASE_PATH || '') + '/actions/list/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || ''
        },
        body: JSON.stringify({ list_id: listId, _token: csrfToken })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('List deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to delete list', 'error');
        }
    })
    .catch(err => {
        showToast('An error occurred', 'error');
    });
}

// Show add card modal
function showAddCardModal(listId) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Add Task</h3>
            <form onsubmit="createCard(event, ${listId})">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Task Title</label>
                    <input 
                        type="text" 
                        name="title" 
                        required 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                        placeholder="Enter task title"
                    >
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description (Optional)</label>
                    <textarea 
                        name="description" 
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                        placeholder="Add more details..."
                    ></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
                        Create
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Create card/task
function createCard(e, listId) {
    e.preventDefault();
    
    const form = e.target;
    const modal = form.closest('.fixed'); // Get the modal container
    const submitBtn = form.querySelector('button[type="submit"]');
    const titleInput = form.querySelector('input[name="title"]');
    const title = titleInput?.value?.trim() || '';
    
    if (!title) {
        showToast('Please enter a task title', 'error');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('list_id', listId);
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Generate temporary ID for optimistic UI
    const tempId = 'temp-' + Date.now();
    
    // Find the cards container inside the list - use the inner container with id="list-{listId}"
    const listContainer = document.getElementById(`list-${listId}`) || 
                          document.querySelector(`.p-3.space-y-3[data-list-id="${listId}"]`);
    
    // Close modal immediately for snappy UX
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => modal.remove(), 150);
    }
    
    // OPTIMISTIC UI: Add loading card to DOM immediately
    if (listContainer) {
        const tempCard = createOptimisticCard(tempId, title, listId);
        listContainer.appendChild(tempCard);
        
        // Smooth scroll to new card
        tempCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    fetch((window.BASE_PATH || '') + '/actions/card/create.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken || ''
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.card) {
            // Remove temp card and replace with real card from backend response
            const tempCard = document.getElementById(`card-${tempId}`);
            if (tempCard && listContainer) {
                // Generate proper card HTML using backend data
                const realCardHtml = generateCardHtmlFromData(data.card);
                
                // Create new element from HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = realCardHtml.trim();
                const realCard = tempDiv.firstChild;
                
                // Replace temp card with real card
                tempCard.replaceWith(realCard);
            }
            showToast('Task created successfully', 'success');
        } else {
            // Remove temp card on failure
            const tempCard = document.getElementById(`card-${tempId}`);
            if (tempCard) {
                tempCard.style.opacity = '0';
                tempCard.style.transform = 'scale(0.9)';
                setTimeout(() => tempCard.remove(), 200);
            }
            showToast(data.message || 'Error creating task', 'error');
        }
    })
    .catch(err => {
        // Remove temp card on error
        const tempCard = document.getElementById(`card-${tempId}`);
        if (tempCard) {
            tempCard.style.opacity = '0';
            tempCard.style.transform = 'scale(0.9)';
            setTimeout(() => tempCard.remove(), 200);
        }
        console.error('Error:', err);
        showToast('An error occurred while creating the task', 'error');
    });
}

// Generate card HTML from backend data (matches board.php card structure exactly)
function generateCardHtmlFromData(card) {
    // Helper to escape HTML
    const safeEscape = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const isCompleted = card.is_completed;
    const completedClass = isCompleted ? 'card-completed' : '';
    const cardBgClass = isCompleted 
        ? 'bg-gray-100 dark:bg-gray-700/50 border-gray-300 dark:border-gray-600' 
        : 'bg-white dark:bg-gray-800 border-gray-200/80 dark:border-gray-700 hover:border-primary/40 dark:hover:border-primary/50';
    const titleClass = isCompleted 
        ? 'text-gray-500 dark:text-gray-400 line-through' 
        : 'text-gray-900 dark:text-gray-100';
    const escapedTitle = safeEscape(card.title);
    const creatorName = safeEscape(card.created_by_name || 'You');
    
    // Description preview (if exists)
    let descriptionHtml = '';
    if (card.description && card.description.trim()) {
        const desc = card.description.length > 120 ? card.description.substring(0, 120) + '...' : card.description;
        descriptionHtml = `<p class="text-xs text-gray-600 dark:text-gray-400 mb-1 line-clamp-1 leading-snug">${safeEscape(desc)}</p>`;
    }
    
    return `
        <div class="group relative card-draggable cursor-grab active:cursor-grabbing ${completedClass}" 
             data-card-id="${card.id}"
             id="card-${card.id}"
             onclick="if (!window.isDragging) window.showCardDetails(${card.id});">
            <div class="block w-full rounded-lg ${cardBgClass} border hover:shadow-md hover:shadow-primary/10 transition-all duration-150 overflow-hidden">
                <div class="p-2.5 sm:p-3">
                    <!-- Title -->
                    <div class="flex justify-between items-start gap-2 mb-1.5">
                        <h3 class="font-medium text-sm sm:text-base leading-tight group-hover:text-primary ${titleClass}">
                            ${escapedTitle}
                        </h3>
                    </div>
                    
                    ${descriptionHtml}
                    
                    <!-- Created By -->
                    <div class="text-[10px] text-gray-500 dark:text-gray-400">
                        Created by ${creatorName}
                    </div>
                    
                    <!-- Meta row (empty but maintains structure) -->
                    <div class="flex items-center justify-between pt-1.5 mt-1.5 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-2"></div>
                        <div class="flex items-center gap-1.5"></div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Helper function to create an optimistic card element
function createOptimisticCard(tempId, title, listId) {
    // Safe HTML escape
    const safeEscape = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const card = document.createElement('div');
    card.id = `card-${tempId}`;
    card.dataset.cardId = tempId;
    card.className = 'group relative card-item opacity-70 transition-opacity duration-200';
    card.draggable = true;
    
    const escapedTitle = safeEscape(title);
    
    card.innerHTML = `
        <div class="block w-full rounded-xl p-3 text-left border bg-white dark:bg-gray-800 border-gray-200/80 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer">
            <div class="flex items-start gap-2">
                <button type="button" class="flex-shrink-0 w-4 h-4 mt-0.5 rounded-full border-2 border-gray-300 dark:border-gray-500 transition-all duration-200" title="Mark as complete" disabled>
                </button>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">${escapedTitle}</h3>
                    <div class="flex items-center gap-2 mt-2 text-xs text-gray-400">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Creating...</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    return card;
}

// Show edit card modal
function showEditCardModal(cardId) {
    if (window.DEBUG_MODE) console.log('showEditCardModal called with cardId:', cardId);
    
    // First, fetch the card details
    const url = `${window.BASE_PATH || ''}/actions/card/get.php?id=${cardId}`;
    if (window.DEBUG_MODE) console.log('Fetching from URL:', url);
    
    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        
        // Handle non-JSON responses (like redirects)
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Server returned an invalid response');
        }
        
        const data = await response.json().catch(e => {
            console.error('JSON parse error:', e);
            throw new Error('Error parsing server response');
        });
        
        // Handle authentication redirect
        if (response.status === 401 || data.redirect) {
            const redirectUrl = data.redirect || (window.BASE_PATH || '') + '/public/login.php';
            if (window.DEBUG_MODE) console.log('Authentication required, redirecting to:', redirectUrl);
            window.location.href = redirectUrl;
            return Promise.reject('Redirecting to login');
        }
        
        // Handle other error statuses
        if (!response.ok) {
            console.error('Server error:', response.status, data.message || 'Unknown error');
            throw new Error(data.message || `Error loading card (${response.status})`);
        }
        
        // Check if we got valid card data
        if (!data || typeof data !== 'object' || !data.success) {
            console.error('Invalid response format:', data);
            throw new Error(data.message || 'Invalid card data received');
        }
        
        return data;
    })
    .then(data => {
        if (data.success) {
            const card = data.card;
            
            // Create and show the edit modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Edit Task</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form onsubmit="updateCard(event, ${cardId})">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                                <input type="text" name="title" value="${window.escapeHtml(card.title)}" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                       required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea name="description" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">${window.escapeHtml(card.description || '')}</textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" onclick="this.closest('.fixed').remove()" 
                                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-indigo-600">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.querySelector('input').focus();
        } else {
            showToast(data.message || 'Error loading task details', 'error');
        }
    })
    .catch(err => {
        console.error('Error in showEditCardModal:', err);
        
        // Don't show error if we're redirecting to login
        if (err.toString().includes('Redirecting to login')) {
            if (window.DEBUG_MODE) console.log('Redirecting to login, not showing error');
            return;
        }
        
        // Show user-friendly error message
        const errorMessage = err.message || 'An error occurred while loading the task. Please try again.';
        console.error('Showing error to user:', errorMessage);
        showToast(errorMessage, 'error');
        
        // If it's a 404, the card might have been deleted
        if (err.message.includes('404')) {
            // Optionally remove the card from the UI
            const cardElement = document.querySelector(`[data-card-id="${cardId}"]`);
            if (cardElement) {
                cardElement.remove();
            }
        }
    });
}

// Update card
function updateCard(e, cardId) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    // Add card ID to form data
    formData.append('id', cardId);
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        formData.append('_token', csrfToken);
    }
    
    // Show loading state
    if (submitBtn && window.btnLoading) btnLoading(submitBtn, 'Updating...');
    
    fetch(`${window.BASE_PATH || ''}/actions/card/update.php`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken || ''
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Task updated successfully', 'success');
            setTimeout(() => window.location.reload(), 500);
        } else {
            if (submitBtn && window.btnReset) btnReset(submitBtn);
            showToast(data.message || 'Error updating task', 'error');
        }
    })
    .catch(err => {
        if (submitBtn && window.btnReset) btnReset(submitBtn);
        console.error('Error:', err);
        showToast('An error occurred while updating the task', 'error');
    });
}

// Delete card
function deleteCard(cardId) {
    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        return;
    }
    
    // Show loading state
    const deleteButton = document.querySelector(`[onclick*="deleteCard(${cardId})"]`);
    const originalContent = deleteButton ? deleteButton.innerHTML : '';
    if (deleteButton) {
        deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
        deleteButton.disabled = true;
    }
    
    // Use absolute path for the delete endpoint
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_PATH || '') + '/actions/card/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ 
            id: cardId,
            _token: csrfToken
        })
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        
        // Handle authentication redirect
        if (response.status === 401 || data.redirect) {
            window.location.href = data.redirect || (window.BASE_PATH || '') + '/public/login.php';
            return;
        }
        
        if (!response.ok) {
            throw new Error(data.message || `HTTP error! status: ${response.status}`);
        }
        
        if (data.success) {
            showToast('Task deleted successfully', 'success');
            // Remove the task element from the DOM with animation
            const cardElement = document.querySelector(`[data-card-id="${cardId}"]`);
            if (cardElement) {
                cardElement.style.opacity = '0';
                setTimeout(() => cardElement.remove(), 300);
            }
        } else {
            throw new Error(data.message || 'Failed to delete task');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        // Don't show error if we're already redirecting
        if (!window.location.href.includes('login.php')) {
            showToast(`Error: ${error.message || 'Failed to delete task. Please try again.'}`, 'error');
        }
    })
    .finally(() => {
        if (deleteButton) {
            deleteButton.innerHTML = originalContent;
            deleteButton.disabled = false;
        }
    });
}

// Use the global escapeHtml function defined earlier in this file (window.escapeHtml)

// Open card modal (will be detailed in card modal component)
function openCardModal(cardId) {
    // This will be implemented with the card modal
    window.location.href = `#card-${cardId}`;
    loadCardDetails(cardId);
}