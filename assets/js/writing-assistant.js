/**
 * AI Writing Assistant for Planify
 * 
 * Provides inline text improvement suggestions for text inputs.
 * Features:
 * - Detects text input fields and shows "Improve with Assistant" badge
 * - Sends text to AI for professional rephrasing
 * - Shows preview with Replace/Cancel options
 * - Non-intrusive, works with any text input
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        minTextLength: 5,           // Minimum characters before showing badge
        debounceDelay: 800,         // Delay before showing badge after typing stops
        badgeOffset: 8,             // Offset from input edge
        excludedSelectors: [        // Inputs to exclude
            '[data-no-ai-assist]',
            '[type="password"]',
            '[type="email"]',
            '[type="number"]',
            '[type="date"]',
            '[type="time"]',
            '[type="url"]',
            '[type="search"]',
            '.ai-no-assist'
        ],
        supportedInputs: [          // Inputs to target
            'input[type="text"]',
            'textarea',
            '[contenteditable="true"]'
        ]
    };

    // State
    let currentBadge = null;
    let currentInput = null;
    let debounceTimer = null;
    let isProcessing = false;

    // SVG Icons
    const ICONS = {
        sparkle: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3L14.5 8.5L20 11L14.5 13.5L12 19L9.5 13.5L4 11L9.5 8.5L12 3Z"/>
            <path d="M19 3L20 5L22 6L20 7L19 9L18 7L16 6L18 5L19 3Z" opacity="0.6"/>
        </svg>`,
        spinner: `<svg class="ai-badge-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
        </svg>`,
        magic: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 4V2"/>
            <path d="M15 16v-2"/>
            <path d="M8 9h2"/>
            <path d="M20 9h2"/>
            <path d="M17.8 11.8L19 13"/>
            <path d="M15 9h0"/>
            <path d="M17.8 6.2L19 5"/>
            <path d="M3 21l9-9"/>
            <path d="M12.2 6.2L11 5"/>
        </svg>`
    };

    /**
     * Initialize the Writing Assistant
     */
    function init() {
        // Add event listeners for input detection
        document.addEventListener('focusin', handleFocusIn);
        document.addEventListener('focusout', handleFocusOut);
        document.addEventListener('input', handleInput);
        
        // Clean up badge on scroll/resize
        window.addEventListener('scroll', hideBadge, true);
        window.addEventListener('resize', debounce(repositionBadge, 100));
        
        console.log('[AI Writing Assistant] Initialized');
    }

    /**
     * Handle focus in on supported inputs
     */
    function handleFocusIn(e) {
        const input = e.target;
        if (!isValidInput(input)) return;
        
        currentInput = input;
        
        // Check if there's enough text to show badge
        const text = getInputText(input);
        if (text.length >= CONFIG.minTextLength) {
            showBadge(input);
        }
    }

    /**
     * Handle focus out - hide badge
     */
    function handleFocusOut(e) {
        const relatedTarget = e.relatedTarget;
        
        // Don't hide if clicking on the badge
        if (currentBadge && currentBadge.contains(relatedTarget)) {
            return;
        }
        
        // Delay hiding to allow badge click
        setTimeout(() => {
            if (!currentBadge?.matches(':hover')) {
                hideBadge();
            }
        }, 150);
    }

    /**
     * Handle input - debounced badge visibility
     */
    function handleInput(e) {
        const input = e.target;
        if (!isValidInput(input)) return;
        
        currentInput = input;
        
        // Clear existing timer
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        
        // Hide badge while typing
        hideBadge();
        
        // Show badge after user stops typing
        debounceTimer = setTimeout(() => {
            const text = getInputText(input);
            if (text.length >= CONFIG.minTextLength && document.activeElement === input) {
                showBadge(input);
            }
        }, CONFIG.debounceDelay);
    }

    /**
     * Check if input is valid for AI assistance
     */
    function isValidInput(element) {
        if (!element) return false;
        
        // Check if it matches any supported input type
        const isSupported = CONFIG.supportedInputs.some(selector => 
            element.matches(selector)
        );
        if (!isSupported) return false;
        
        // Check if it's excluded
        const isExcluded = CONFIG.excludedSelectors.some(selector => 
            element.matches(selector)
        );
        if (isExcluded) return false;
        
        // Check if it's read-only or disabled
        if (element.readOnly || element.disabled) return false;
        
        return true;
    }

    /**
     * Get text from input (handles contenteditable)
     */
    function getInputText(input) {
        if (input.getAttribute('contenteditable') === 'true') {
            return input.innerText || input.textContent || '';
        }
        return input.value || '';
    }

    /**
     * Set text in input (handles contenteditable)
     */
    function setInputText(input, text) {
        if (input.getAttribute('contenteditable') === 'true') {
            input.innerText = text;
            // Trigger input event for any listeners
            input.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            input.value = text;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /**
     * Detect context from input
     */
    function detectContext(input) {
        const id = (input.id || '').toLowerCase();
        const name = (input.name || '').toLowerCase();
        const placeholder = (input.placeholder || '').toLowerCase();
        const className = (input.className || '').toLowerCase();
        
        // Check for common patterns
        if (id.includes('title') || name.includes('title') || placeholder.includes('title')) {
            return 'task_title';
        }
        if (id.includes('description') || name.includes('description') || placeholder.includes('description')) {
            return 'task_description';
        }
        if (id.includes('comment') || name.includes('comment') || placeholder.includes('comment')) {
            return 'comment';
        }
        if (id.includes('note') || name.includes('note') || placeholder.includes('note')) {
            return 'note';
        }
        
        return 'general';
    }

    /**
     * Show the AI badge near input
     */
    function showBadge(input) {
        // Remove existing badge
        hideBadge();
        
        // Create badge container
        const container = document.createElement('div');
        container.className = 'ai-writing-badge-container';
        
        // Create badge button
        const badge = document.createElement('button');
        badge.className = 'ai-writing-badge';
        badge.type = 'button';
        badge.innerHTML = `${ICONS.sparkle}<span>Improve with Assistant</span>`;
        
        // Handle click
        badge.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            handleImproveClick(input);
        });
        
        container.appendChild(badge);
        document.body.appendChild(container);
        
        currentBadge = container;
        
        // Position badge
        positionBadge(input, container);
    }

    /**
     * Position badge relative to input
     */
    function positionBadge(input, container) {
        const rect = input.getBoundingClientRect();
        const badgeHeight = 32;
        
        // Position at bottom-right of input
        let top = rect.bottom - badgeHeight - CONFIG.badgeOffset;
        let left = rect.right - 160 - CONFIG.badgeOffset; // Approximate badge width
        
        // Ensure it's visible
        if (left < rect.left + 10) {
            left = rect.left + 10;
        }
        
        // For textareas with multiple lines, position inside
        if (input.tagName === 'TEXTAREA' || input.getAttribute('contenteditable') === 'true') {
            top = rect.bottom - badgeHeight - CONFIG.badgeOffset;
        }
        
        container.style.position = 'fixed';
        container.style.top = `${top}px`;
        container.style.left = `${left}px`;
    }

    /**
     * Reposition badge on resize
     */
    function repositionBadge() {
        if (currentBadge && currentInput) {
            positionBadge(currentInput, currentBadge);
        }
    }

    /**
     * Hide the AI badge
     */
    function hideBadge() {
        if (currentBadge) {
            currentBadge.classList.add('hiding');
            const badge = currentBadge;
            setTimeout(() => badge.remove(), 200);
            currentBadge = null;
        }
    }

    /**
     * Handle improve button click
     */
    async function handleImproveClick(input) {
        if (isProcessing) return;
        
        const text = getInputText(input).trim();
        if (text.length < CONFIG.minTextLength) {
            showError('Please enter more text to improve.');
            return;
        }
        
        isProcessing = true;
        
        // Update badge to loading state
        const badgeBtn = currentBadge?.querySelector('.ai-writing-badge');
        if (badgeBtn) {
            badgeBtn.classList.add('loading');
            badgeBtn.innerHTML = `${ICONS.spinner}<span>Improving...</span>`;
        }
        
        try {
            const context = detectContext(input);
            const result = await improveText(text, context);
            
            if (result.success) {
                showPreview(input, result.original, result.improved);
            } else {
                showError(result.message || 'Failed to improve text.');
            }
        } catch (error) {
            console.error('[AI Writing Assistant] Error:', error);
            showError('Something went wrong. Please try again.');
        } finally {
            isProcessing = false;
            hideBadge();
        }
    }

    /**
     * Call API to improve text
     */
    async function improveText(text, context) {
        const basePath = window.BASE_PATH || '';
        const response = await fetch(`${basePath}/actions/ai/improve-text.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ text, context })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            return {
                success: false,
                message: errorData.message || `Error: ${response.status}`
            };
        }
        
        return await response.json();
    }

    /**
     * Show preview modal with improved text
     */
    function showPreview(input, original, improved) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'ai-preview-overlay';
        
        // Create modal
        overlay.innerHTML = `
            <div class="ai-preview-modal">
                <div class="ai-preview-header">
                    <div class="ai-preview-icon">${ICONS.magic}</div>
                    <div>
                        <div class="ai-preview-title">Improved Text</div>
                        <div class="ai-preview-subtitle">Review the AI-improved version</div>
                    </div>
                </div>
                <div class="ai-preview-content">
                    <div class="ai-preview-section">
                        <div class="ai-preview-label">Original</div>
                        <div class="ai-preview-text original">${escapeHtml(original)}</div>
                    </div>
                    <div class="ai-preview-section">
                        <div class="ai-preview-label">Improved</div>
                        <div class="ai-preview-text improved">${escapeHtml(improved)}</div>
                    </div>
                </div>
                <div class="ai-preview-footer">
                    <button class="ai-preview-btn cancel" data-action="cancel">Keep Original</button>
                    <button class="ai-preview-btn replace" data-action="replace">Replace Text</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // Handle actions
        overlay.addEventListener('click', (e) => {
            const action = e.target.dataset.action;
            
            if (action === 'replace') {
                setInputText(input, improved);
                input.focus();
                closePreview(overlay);
                
                // Show success toast
                if (window.showToast) {
                    window.showToast('Text improved successfully!', 'success');
                }
            } else if (action === 'cancel' || e.target === overlay) {
                input.focus();
                closePreview(overlay);
            }
        });
        
        // Handle escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                input.focus();
                closePreview(overlay);
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
        
        // Focus replace button
        setTimeout(() => {
            overlay.querySelector('.ai-preview-btn.replace')?.focus();
        }, 100);
    }

    /**
     * Close preview modal
     */
    function closePreview(overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 200);
    }

    /**
     * Show error toast
     */
    function showError(message) {
        // Use Planify's toast if available
        if (window.showToast) {
            window.showToast(message, 'error');
            return;
        }
        
        // Fallback toast
        const toast = document.createElement('div');
        toast.className = 'ai-error-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 200);
        }, 4000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debounce helper
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose API for manual control
    window.AIWritingAssistant = {
        init,
        showBadge,
        hideBadge,
        improveText
    };

})();

