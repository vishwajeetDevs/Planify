/**
 * Planify @Mention System
 * Provides autocomplete for mentioning board members in comments
 * Supports both textarea and contenteditable elements
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        triggerChar: '@',
        minChars: 0, // Start showing suggestions immediately after @
        maxSuggestions: 10,
        debounceMs: 150,
        dropdownZIndex: 10000
    };

    // State
    let boardMembers = [];
    let boardMembersLoaded = false;
    let currentBoardId = null;
    let activeDropdown = null;
    let selectedIndex = 0;
    let mentionStartPos = -1;
    let isComposing = false;
    let activeElement = null;

    /**
     * Initialize the mention system for an input element
     * @param {HTMLElement} element - The comment input (textarea or contenteditable)
     * @param {number} boardId - The current board ID
     */
    window.initMentionSystem = function(element, boardId) {
        if (!element || !boardId) {
            console.log('[Mention] Init failed - missing element or boardId', { element: !!element, boardId });
            return;
        }

        console.log('[Mention] Initializing for board:', boardId);

        // Prevent re-initialization
        if (element._mentionInitialized) {
            // Just ensure board members are loaded
            currentBoardId = boardId;
            loadBoardMembers(boardId);
            console.log('[Mention] Already initialized, reloading members');
            return;
        }

        currentBoardId = boardId;
        activeElement = element;
        
        // Load board members
        loadBoardMembers(boardId);

        // Detect if this is a contenteditable or textarea
        const isContentEditable = element.hasAttribute('contenteditable');

        // Create wrapper for positioning (only if not already wrapped)
        if (!element._mentionWrapper) {
            const wrapper = document.createElement('div');
            wrapper.className = 'mention-wrapper relative';
            element.parentNode.insertBefore(wrapper, element);
            wrapper.appendChild(element);
            element._mentionWrapper = wrapper;
        }

        // Event listeners
        element.addEventListener('input', handleInput);
        element.addEventListener('keydown', handleKeydown);
        element.addEventListener('blur', handleBlur);
        element.addEventListener('compositionstart', () => isComposing = true);
        element.addEventListener('compositionend', () => isComposing = false);

        // Store reference
        element._boardId = boardId;
        element._mentionInitialized = true;
        element._isContentEditable = isContentEditable;

        console.log('[Mention] Initialized successfully. ContentEditable:', isContentEditable);
    };

    /**
     * Load board members from API
     */
    async function loadBoardMembers(boardId) {
        if (boardMembersLoaded && currentBoardId === boardId && boardMembers.length > 0) {
            console.log('[Mention] Using cached members:', boardMembers.length);
            return;
        }

        try {
            console.log('[Mention] Loading board members for board:', boardId);
            const response = await fetch(`${window.BASE_PATH || ''}/actions/board/members.php?board_id=${boardId}`);
            const data = await response.json();
            
            if (data.success && data.members) {
                boardMembers = data.members.map(m => ({
                    id: parseInt(m.id),
                    name: m.name,
                    email: m.email,
                    avatar: m.avatar,
                    role: m.role
                }));
                boardMembersLoaded = true;
                console.log('[Mention] Loaded members:', boardMembers.length, boardMembers.map(m => m.name));
            } else {
                console.error('[Mention] Failed to load members:', data);
            }
        } catch (error) {
            console.error('[Mention] Failed to load board members:', error);
        }
    }

    /**
     * Get text and cursor position from element
     */
    function getTextAndCursor(element) {
        if (element._isContentEditable) {
            // For contenteditable
            const selection = window.getSelection();
            if (!selection.rangeCount) return { text: '', cursorPos: 0 };
            
            const range = selection.getRangeAt(0);
            const text = element.innerText || element.textContent || '';
            
            // Get cursor position by creating a range from start to cursor
            const preCaretRange = range.cloneRange();
            preCaretRange.selectNodeContents(element);
            preCaretRange.setEnd(range.endContainer, range.endOffset);
            const cursorPos = preCaretRange.toString().length;
            
            return { text, cursorPos };
        } else {
            // For textarea
            return {
                text: element.value || '',
                cursorPos: element.selectionStart || 0
            };
        }
    }

    /**
     * Handle input event
     */
    function handleInput(e) {
        if (isComposing) return;

        const element = e.target;
        activeElement = element;
        const { text, cursorPos } = getTextAndCursor(element);

        console.log('[Mention] Input event - text:', text.substring(Math.max(0, cursorPos - 10), cursorPos), 'cursor:', cursorPos);

        // Find if we're in a mention context
        const mentionContext = getMentionContext(text, cursorPos);

        if (mentionContext) {
            console.log('[Mention] Found mention context:', mentionContext);
            mentionStartPos = mentionContext.start;
            showSuggestions(element, mentionContext.query);
        } else {
            hideSuggestions();
            mentionStartPos = -1;
        }
    }

    /**
     * Get the mention context (text after @ up to cursor)
     */
    function getMentionContext(text, cursorPos) {
        // Look backwards from cursor to find @
        let start = cursorPos - 1;
        
        while (start >= 0) {
            const char = text[start];
            
            // Found trigger
            if (char === CONFIG.triggerChar) {
                // Make sure it's at start or after whitespace/newline
                if (start === 0 || /[\s\n]/.test(text[start - 1])) {
                    const query = text.substring(start + 1, cursorPos);
                    // Don't trigger if there's a space in the query (mention already completed)
                    if (!/\s/.test(query)) {
                        return { start, query };
                    }
                }
                break;
            }
            
            // Stop if we hit whitespace
            if (/[\s\n]/.test(char)) break;
            
            start--;
        }
        
        return null;
    }

    /**
     * Show suggestions dropdown
     */
    function showSuggestions(element, query) {
        const filtered = filterMembers(query);
        
        console.log('[Mention] Showing suggestions for query:', query, 'Found:', filtered.length);
        
        if (filtered.length === 0) {
            hideSuggestions();
            return;
        }

        selectedIndex = 0;

        // Create or get dropdown
        let dropdown = activeDropdown;
        if (!dropdown) {
            dropdown = createDropdown();
            activeDropdown = dropdown;
        }

        // Update content
        dropdown.innerHTML = filtered.slice(0, CONFIG.maxSuggestions).map((member, index) => `
            <div class="mention-item ${index === selectedIndex ? 'selected' : ''}" 
                 data-user-id="${member.id}"
                 data-user-name="${escapeHtml(member.name)}"
                 role="option"
                 aria-selected="${index === selectedIndex}">
                <div class="mention-avatar">
                    ${member.avatar && member.avatar !== 'default-avatar.png' 
                        ? `<img src="${window.BASE_PATH || ''}/assets/uploads/avatars/${escapeHtml(member.avatar)}" alt="" class="w-7 h-7 rounded-full object-cover">`
                        : `<div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary to-indigo-600 flex items-center justify-center text-white text-xs font-semibold">${member.name.charAt(0).toUpperCase()}</div>`
                    }
                </div>
                <div class="mention-info flex-1 min-w-0">
                    <div class="mention-name text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(member.name)}</div>
                    <div class="mention-email text-xs text-gray-500 dark:text-gray-400 truncate">${escapeHtml(member.email)}</div>
                </div>
                <span class="mention-role text-xs px-2 py-0.5 rounded-full ${getRoleBadgeClass(member.role)}">${member.role}</span>
            </div>
        `).join('');

        // Position dropdown
        positionDropdown(element, dropdown);

        // Add click handlers
        dropdown.querySelectorAll('.mention-item').forEach((item, index) => {
            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                selectMention(element, index);
            });
            item.addEventListener('mouseenter', () => {
                selectedIndex = index;
                updateSelection(dropdown);
            });
        });

        dropdown.classList.remove('hidden');
    }

    /**
     * Create the dropdown element
     */
    function createDropdown() {
        const dropdown = document.createElement('div');
        dropdown.className = 'mention-dropdown hidden absolute bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden';
        dropdown.style.zIndex = CONFIG.dropdownZIndex;
        dropdown.setAttribute('role', 'listbox');
        dropdown.setAttribute('aria-label', 'Member suggestions');
        document.body.appendChild(dropdown);
        return dropdown;
    }

    /**
     * Position dropdown near the element cursor
     */
    function positionDropdown(element, dropdown) {
        const rect = element.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        // Position below the element
        let top = rect.bottom + scrollTop + 4;
        let left = rect.left + scrollLeft;

        // Ensure dropdown fits in viewport
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        // If dropdown would go below viewport, show above element
        if (top + 200 > viewportHeight + scrollTop) {
            top = rect.top + scrollTop - 200 - 4;
        }

        // Ensure left edge is visible
        if (left + 280 > viewportWidth + scrollLeft) {
            left = viewportWidth + scrollLeft - 280 - 8;
        }

        dropdown.style.top = `${top}px`;
        dropdown.style.left = `${left}px`;
        dropdown.style.width = `${Math.min(rect.width, 320)}px`;
        dropdown.style.maxHeight = '200px';
        dropdown.style.overflowY = 'auto';
    }

    /**
     * Filter members by query
     */
    function filterMembers(query) {
        if (!query) return boardMembers;
        
        const lowerQuery = query.toLowerCase();
        return boardMembers.filter(member => 
            member.name.toLowerCase().includes(lowerQuery) ||
            member.email.toLowerCase().includes(lowerQuery)
        );
    }

    /**
     * Handle keydown for navigation
     */
    function handleKeydown(e) {
        if (!activeDropdown || activeDropdown.classList.contains('hidden')) return;

        const items = activeDropdown.querySelectorAll('.mention-item');
        if (items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateSelection(activeDropdown);
                break;

            case 'ArrowUp':
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                updateSelection(activeDropdown);
                break;

            case 'Tab':
            case 'Enter':
                if (activeDropdown && !activeDropdown.classList.contains('hidden')) {
                    e.preventDefault();
                    selectMention(e.target, selectedIndex);
                }
                break;

            case 'Escape':
                e.preventDefault();
                hideSuggestions();
                break;
        }
    }

    /**
     * Update visual selection
     */
    function updateSelection(dropdown) {
        const items = dropdown.querySelectorAll('.mention-item');
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.classList.add('selected');
                item.setAttribute('aria-selected', 'true');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
                item.setAttribute('aria-selected', 'false');
            }
        });
    }

    /**
     * Select a mention and insert it
     */
    function selectMention(element, index) {
        const items = activeDropdown.querySelectorAll('.mention-item');
        if (index >= items.length) return;

        const item = items[index];
        const userId = item.dataset.userId;
        const userName = item.dataset.userName;

        console.log('[Mention] Selecting:', userName, 'ID:', userId);

        if (element._isContentEditable) {
            insertMentionContentEditable(element, userName, userId);
        } else {
            insertMentionTextarea(element, userName, userId);
        }

        // Store mentioned user IDs in data attribute
        let mentionedIds = JSON.parse(element.dataset.mentionedUserIds || '[]');
        if (!mentionedIds.includes(parseInt(userId))) {
            mentionedIds.push(parseInt(userId));
        }
        element.dataset.mentionedUserIds = JSON.stringify(mentionedIds);

        hideSuggestions();
        element.focus();
    }

    /**
     * Insert mention into contenteditable element
     */
    function insertMentionContentEditable(element, userName, userId) {
        const { text, cursorPos } = getTextAndCursor(element);
        
        // Calculate where to replace
        const beforeMention = text.substring(0, mentionStartPos);
        const afterMention = text.substring(cursorPos);
        
        // Create the mention text
        const mentionText = `@${userName} `;
        
        // Update the content
        element.innerText = beforeMention + mentionText + afterMention;
        
        // Trigger input event for any listeners
        element.dispatchEvent(new Event('input', { bubbles: true }));
        
        // Set cursor position after the mention
        const newPos = mentionStartPos + mentionText.length;
        setCursorPosition(element, newPos);
    }

    /**
     * Insert mention into textarea element
     */
    function insertMentionTextarea(element, userName, userId) {
        const text = element.value;
        const beforeMention = text.substring(0, mentionStartPos);
        const afterMention = text.substring(element.selectionStart);
        
        // Format: @Username
        const mentionText = `@${userName} `;
        
        element.value = beforeMention + mentionText + afterMention;
        
        // Set cursor position after the mention
        const newPos = mentionStartPos + mentionText.length;
        element.setSelectionRange(newPos, newPos);
    }

    /**
     * Set cursor position in contenteditable
     */
    function setCursorPosition(element, position) {
        const range = document.createRange();
        const selection = window.getSelection();
        
        // Get text node
        let node = element.firstChild;
        if (!node) {
            // If empty, create a text node
            node = document.createTextNode('');
            element.appendChild(node);
        }
        
        // Find the right text node and offset
        let currentPos = 0;
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
        
        while (walker.nextNode()) {
            const nodeLength = walker.currentNode.length;
            if (currentPos + nodeLength >= position) {
                range.setStart(walker.currentNode, position - currentPos);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
                return;
            }
            currentPos += nodeLength;
        }
        
        // If position is beyond text, put cursor at end
        if (node.nodeType === Node.TEXT_NODE) {
            range.setStart(node, node.length);
        } else {
            range.selectNodeContents(element);
            range.collapse(false);
        }
        selection.removeAllRanges();
        selection.addRange(range);
    }

    /**
     * Handle blur event
     */
    function handleBlur(e) {
        // Delay hiding to allow click on dropdown
        setTimeout(() => {
            if (!activeDropdown?.matches(':hover')) {
                hideSuggestions();
            }
        }, 200);
    }

    /**
     * Hide suggestions dropdown
     */
    function hideSuggestions() {
        if (activeDropdown) {
            activeDropdown.classList.add('hidden');
        }
        selectedIndex = 0;
        mentionStartPos = -1;
    }

    /**
     * Get role badge CSS class
     */
    function getRoleBadgeClass(role) {
        const classes = {
            'owner': 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'admin': 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
            'member': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
            'commenter': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
            'viewer': 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
        };
        return classes[role] || classes['viewer'];
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get mentioned user IDs from element
     */
    window.getMentionedUserIds = function(element) {
        if (!element) return [];
        return JSON.parse(element.dataset.mentionedUserIds || '[]');
    };

    /**
     * Clear mentioned user IDs from element
     */
    window.clearMentionedUserIds = function(element) {
        if (element) {
            element.dataset.mentionedUserIds = '[]';
        }
    };

    /**
     * Parse mentions from comment text (fallback for plain text)
     * Returns array of user IDs that were mentioned
     */
    window.parseMentionsFromText = function(text, members) {
        const mentionedIds = [];
        const mentionRegex = /@(\w+(?:\s+\w+)*)/g;
        let match;
        
        while ((match = mentionRegex.exec(text)) !== null) {
            const mentionName = match[1].toLowerCase();
            const member = members.find(m => 
                m.name.toLowerCase() === mentionName ||
                m.name.toLowerCase().startsWith(mentionName)
            );
            if (member && !mentionedIds.includes(member.id)) {
                mentionedIds.push(member.id);
            }
        }
        
        return mentionedIds;
    };

    /**
     * Render comment text with highlighted mentions
     */
    window.renderCommentWithMentions = function(text, mentionedUsers = []) {
        if (!text) return '';
        
        let rendered = escapeHtml(text);
        
        // Highlight @mentions
        rendered = rendered.replace(/@(\w+(?:\s+\w+)*)/g, (match, name) => {
            const user = mentionedUsers.find(u => 
                u.name.toLowerCase() === name.toLowerCase()
            );
            if (user) {
                return `<span class="mention-tag bg-primary/10 text-primary dark:bg-primary/20 dark:text-indigo-300 px-1 py-0.5 rounded font-medium" data-user-id="${user.id}">@${escapeHtml(user.name)}</span>`;
            }
            return `<span class="mention-tag bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-1 py-0.5 rounded">@${escapeHtml(name)}</span>`;
        });
        
        return rendered;
    };

    /**
     * Force reload board members (useful after board member changes)
     */
    window.reloadBoardMembers = function(boardId) {
        boardMembersLoaded = false;
        boardMembers = [];
        if (boardId) {
            loadBoardMembers(boardId);
        }
    };

    // Add CSS styles
    const style = document.createElement('style');
    style.textContent = `
        .mention-dropdown {
            animation: mentionDropdownIn 0.15s ease-out;
        }
        
        @keyframes mentionDropdownIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .mention-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            cursor: pointer;
            transition: background-color 0.1s ease;
        }
        
        .mention-item:hover,
        .mention-item.selected {
            background-color: rgba(79, 70, 229, 0.08);
        }
        
        .dark .mention-item:hover,
        .dark .mention-item.selected {
            background-color: rgba(79, 70, 229, 0.15);
        }
        
        .mention-item.selected {
            background-color: rgba(79, 70, 229, 0.12);
        }
        
        .mention-tag {
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .mention-tag:hover {
            opacity: 0.8;
        }

        @media (prefers-reduced-motion: reduce) {
            .mention-dropdown {
                animation: none;
            }
        }
    `;
    document.head.appendChild(style);

})();
