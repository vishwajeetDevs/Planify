/**
 * Planify live sync.
 *
 * Keeps an EventSource open to the change stream and applies each event to
 * the page that is currently open. The board is reconciled against the server;
 * an open task that another user deleted is closed and cannot be edited.
 */
(function () {
    if (!window.currentUserId) {
        return;
    }

    const goneCards = new Set();
    let syncingBoard = false;
    let boardSyncQueued = false;
    let regionTimer = null;
    let modalTimer = null;
    const modalCards = new Set();
    const modalRefreshEvents = new Map();

    function base() {
        return window.BASE_PATH || '';
    }

    function sameId(a, b) {
        return a != null && b != null && String(a) === String(b);
    }

    function isOwn(event) {
        return sameId(event.actor_id, window.currentUserId);
    }

    function toast(message, type) {
        if (!isOwn({ actor_id: window.__planifyToastActor }) && window.showToast) {
            window.showToast(message, type || 'info');
        }
    }

    function notify(event, message, type) {
        if (isOwn(event) || !window.showToast) {
            return;
        }
        window.showToast(message, type || 'info');
    }

    window.PlanifyRealtime = {
        cardIsGone(cardId) {
            return goneCards.has(String(cardId));
        },

        invalidateCard(cardId, message) {
            if (!cardId) {
                return;
            }
            const id = String(cardId);
            const alreadyGone = goneCards.has(id);
            goneCards.add(id);

            const card = document.getElementById('card-' + id);
            if (card) {
                card.remove();
            }

            if (sameId(window.currentCardId, id)) {
                window.currentCardId = null;
                if (typeof window.closeCardModal === 'function') {
                    window.closeCardModal();
                }
                if (!alreadyGone && message && window.showToast) {
                    window.showToast(message, 'error');
                }
            }
        }
    };

    function escapeText(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function listColumn(listId) {
        return document.querySelector('div.group\\/list[data-list-id="' + listId + '"]');
    }

    function listCardsEl(listId) {
        return document.getElementById('list-' + listId);
    }

    function applyCardFace(el, card) {
        const title = el.querySelector('h3');
        if (title) {
            title.textContent = card.title || 'Untitled';
            title.classList.toggle('line-through', !!card.is_completed);
            title.classList.toggle('text-gray-500', !!card.is_completed);
        }
        el.classList.toggle('card-completed', !!card.is_completed);
    }

    function buildCard(card) {
        const root = document.createElement('div');
        root.id = 'card-' + card.id;
        root.dataset.cardId = String(card.id);
        root.className = 'group relative card-draggable cursor-grab active:cursor-grabbing' + (card.is_completed ? ' card-completed' : '');
        root.innerHTML =
            '<div class="block w-full rounded-lg border bg-white dark:bg-gray-800 border-gray-200/80 dark:border-gray-700 hover:shadow-md transition-all duration-150 overflow-hidden">' +
                '<div class="p-2.5 sm:p-3">' +
                    '<h3 class="font-medium text-sm sm:text-base leading-tight text-gray-900 dark:text-gray-100"></h3>' +
                '</div>' +
            '</div>';
        root.addEventListener('click', function () {
            if (!window.isDragging && window.showCardDetails) {
                window.showCardDetails(card.id);
            }
        });
        applyCardFace(root, card);
        return root;
    }

    function buildList(list) {
        const column = document.createElement('div');
        column.className = 'board-list-column w-[300px] min-w-[300px] max-w-[300px] shrink-0 group/list';
        column.dataset.listId = String(list.id);
        const cardCount = (list.cards && list.cards.length) || 0;
        const addTaskHidden = cardCount > 0 ? ' hidden' : '';
        const addTask = window.boardCanEdit
            ? '<div id="list-add-task-wrap-' + list.id + '" class="px-2.5 pt-2 pb-1 shrink-0' + addTaskHidden + '"><button type="button" class="w-full py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-slate-50/80 dark:bg-gray-800/80 hover:bg-slate-100 dark:hover:bg-gray-700 rounded-md border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center gap-1.5"><i class="fas fa-plus text-xs"></i> Add task</button></div>'
            : '';
        column.innerHTML =
            '<div class="board-list-panel flex flex-col bg-white/90 dark:bg-gray-900/80 backdrop-blur-lg rounded-md border-2 border-dashed border-gray-200 dark:border-gray-700">' +
                '<div class="px-3 py-2 border-b border-gray-100 dark:border-gray-800"><h3 class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate"></h3></div>' +
                addTask +
                '<div class="board-list-cards px-2.5 pb-2.5 pt-0 space-y-3 custom-scrollbar" style="min-height:40px;" id="list-' + list.id + '" data-list-id="' + list.id + '"></div>' +
            '</div>';
        column.querySelector('h3').textContent = list.title || 'List';
        const button = column.querySelector('button');
        if (button) {
            button.addEventListener('click', function () {
                if (window.showAddCardModal) {
                    window.showAddCardModal(list.id);
                }
            });
        }
        return column;
    }

    async function syncBoard() {
        const boardId = window.currentBoardId;
        const boardLists = document.getElementById('boardLists');
        if (!boardId || !boardLists) {
            return;
        }
        if (window.isDragging) {
            boardSyncQueued = true;
            return;
        }
        if (syncingBoard) {
            boardSyncQueued = true;
            return;
        }
        syncingBoard = true;
        try {
            const response = await window.PlanifyRealtime._fetch(base() + '/actions/board/get-content.php?board_id=' + encodeURIComponent(boardId));
            const data = await response.json();
            if (!data.success) {
                if (/access denied|unauthorized/i.test(data.message || '')) {
                    window.location.href = base() + '/public/dashboard.php';
                }
                return;
            }

            const lists = data.lists || [];
            const seenLists = new Set();
            lists.forEach(function (list) {
                seenLists.add(String(list.id));
                let column = listColumn(list.id);
                if (!column) {
                    column = buildList(list);
                    boardLists.appendChild(column);
                }
                const heading = column.querySelector('h3');
                if (heading) {
                    heading.textContent = list.title || 'List';
                }

                const container = listCardsEl(list.id);
                if (!container) {
                    return;
                }
                const cards = list.cards || [];
                const seenCards = new Set(cards.map(function (card) { return String(card.id); }));
                container.querySelectorAll('[data-card-id]').forEach(function (el) {
                    if (!seenCards.has(el.dataset.cardId)) {
                        if (sameId(window.currentCardId, el.dataset.cardId)) {
                            window.PlanifyRealtime.invalidateCard(el.dataset.cardId, 'This task was deleted.');
                        } else {
                            el.remove();
                        }
                    }
                });
                cards.forEach(function (card) {
                    if (window.isDragging) {
                        boardSyncQueued = true;
                        return;
                    }
                    goneCards.delete(String(card.id));
                    let el = document.getElementById('card-' + card.id);
                    if (el && (el.classList.contains('sortable-chosen') || el.classList.contains('sortable-drag') || el.classList.contains('sortable-fallback'))) {
                        return;
                    }
                    if (!el) {
                        el = buildCard(card);
                    } else {
                        applyCardFace(el, card);
                    }
                    container.appendChild(el);
                });
                if (typeof window.syncListAddTaskPrompt === 'function') {
                    window.syncListAddTaskPrompt(list.id);
                }
            });

            document.querySelectorAll('div.group\\/list[data-list-id]').forEach(function (column) {
                if (!seenLists.has(column.dataset.listId)) {
                    column.remove();
                }
            });

            if (window.currentCardId && !document.getElementById('card-' + window.currentCardId)) {
                window.PlanifyRealtime.invalidateCard(window.currentCardId, 'This task is no longer on the board.');
            }

            if (typeof window.initSortable === 'function') {
                window.initSortable();
            }
        } catch (error) {
            console.error('Live board sync failed', error);
        } finally {
            syncingBoard = false;
            if (boardSyncQueued) {
                boardSyncQueued = false;
                syncBoard();
            }
        }
    }

    window.refreshBoardContent = function () {
        return syncBoard();
    };

    function refreshOpenCard(cardId, event) {
        if (!cardId || !sameId(window.currentCardId, cardId)) {
            return;
        }
        if (goneCards.has(String(cardId))) {
            return;
        }

        const entityType = event && event.entity_type;
        const recentLocalChecklistEdit = Date.now() < (window._planifyChecklistLocalEditUntil || 0);
        if (entityType === 'checklist_item' && (isOwn(event) || recentLocalChecklistEdit)) {
            return;
        }
        if (entityType === 'checklist_item' || entityType === 'checklist') {
            if (typeof window.loadChecklists === 'function') window.loadChecklists();
            if (typeof window.loadActivity === 'function') window.loadActivity(cardId);
            return;
        }

        const editor = document.getElementById('descriptionEditor');
        const editing = editor && !editor.classList.contains('hidden');
        if (!editing && typeof window.loadCardDetails === 'function') {
            window.loadCardDetails(cardId);
            return;
        }
        if (typeof window.loadComments === 'function') window.loadComments(cardId);
        if (typeof window.loadChecklists === 'function') window.loadChecklists();
        if (typeof window.loadAttachments === 'function') window.loadAttachments();
        if (typeof window.loadCardLabelsForDisplay === 'function') window.loadCardLabelsForDisplay();
        if (typeof window.loadCardAssigneesForDisplay === 'function') window.loadCardAssigneesForDisplay();
        if (typeof window.loadCardDatesForDisplay === 'function') window.loadCardDatesForDisplay();
        if (typeof window.loadActivity === 'function') window.loadActivity(cardId);
    }

    function refreshSharePanel() {
        const modal = document.getElementById('shareModal');
        if (!modal || modal.classList.contains('hidden') || !window.Alpine || typeof Alpine.$data !== 'function') {
            return;
        }
        const data = Alpine.$data(modal);
        if (!data) {
            return;
        }
        if (typeof data.loadExistingLinks === 'function') data.loadExistingLinks();
        if (typeof data.loadJoinRequests === 'function') data.loadJoinRequests();
    }

    async function refreshRegion(selector) {
        const current = document.querySelector(selector);
        if (!current) {
            return;
        }
        try {
            const response = await window.PlanifyRealtime._fetch(window.location.href, { cache: 'no-store' });
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.querySelector(selector);
            if (!next) {
                return;
            }
            current.innerHTML = next.innerHTML;
            if (window.Alpine && typeof Alpine.initTree === 'function') {
                Alpine.initTree(current);
            }
        } catch (error) {
            console.error('Live region sync failed', error);
        }
    }

    function scheduleRegionRefresh() {
        clearTimeout(regionTimer);
        regionTimer = setTimeout(function () {
            if (document.getElementById('workspaceGrid')) {
                refreshRegion('#workspaceGrid');
            }
            if (document.getElementById('workspaceBoards')) {
                refreshRegion('#workspaceBoards');
            }
        }, 250);
    }

    function onEvent(event) {
        if (!event || !event.entity_type) {
            return;
        }

        if (event.entity_type === 'notification' && sameId(event.target_user_id, window.currentUserId)) {
            window.dispatchEvent(new CustomEvent('planify:notification'));
        }

        const onThisBoard = window.currentBoardId && sameId(event.board_id, window.currentBoardId);

        if (onThisBoard && event.entity_type === 'board' && event.action === 'deleted') {
            notify(event, 'This board was deleted.', 'error');
            window.location.href = base() + '/public/dashboard.php';
            return;
        }

        if (onThisBoard && event.entity_type === 'board' && event.action === 'updated' && event.summary) {
            const title = document.getElementById('boardTitle');
            if (title) {
                title.textContent = event.summary;
            }
        }

        if (onThisBoard && event.entity_type === 'board_member' && event.action === 'deleted' && sameId(event.entity_id, window.currentUserId)) {
            notify(event, 'You were removed from this board.', 'error');
            window.location.href = base() + '/public/dashboard.php';
            return;
        }

        if (event.entity_type === 'card' && event.action === 'deleted') {
            const message = isOwn(event) ? '' : 'This task was deleted.';
            window.PlanifyRealtime.invalidateCard(event.entity_id, message);
        }

        if (onThisBoard && event.entity_type === 'list' && event.action === 'deleted') {
            const column = listColumn(event.entity_id);
            if (column) {
                const open = column.querySelector('#card-' + window.currentCardId);
                column.remove();
                if (open) {
                    window.PlanifyRealtime.invalidateCard(window.currentCardId, isOwn(event) ? '' : 'This list was deleted.');
                }
            }
        }

        const structure = ['card', 'list', 'label', 'card_label', 'assignee', 'board_member'];
        if (onThisBoard && structure.indexOf(event.entity_type) !== -1) {
            clearTimeout(window.__planifyBoardTimer);
            window.__planifyBoardTimer = setTimeout(syncBoard, 200);
        }

        const cardId = event.entity_type === 'card' ? event.entity_id : event.card_id;
        const cardDetail = ['card', 'comment', 'checklist', 'checklist_item', 'attachment', 'assignee', 'card_label', 'label'];
        if (onThisBoard && cardId && cardDetail.indexOf(event.entity_type) !== -1 && event.action !== 'deleted') {
            const cardKey = String(cardId);
            modalCards.add(cardKey);
            modalRefreshEvents.set(cardKey, event);
            clearTimeout(modalTimer);
            modalTimer = setTimeout(function () {
                modalCards.forEach(function (id) {
                    refreshOpenCard(id, modalRefreshEvents.get(id));
                    modalRefreshEvents.delete(id);
                });
                modalCards.clear();
            }, 200);
        }

        if (onThisBoard && (event.entity_type === 'share_link' || event.entity_type === 'join_request')) {
            refreshSharePanel();
            if (event.entity_type === 'join_request' && typeof window.refreshJoinRequests === 'function') {
                window.refreshJoinRequests();
            }
        }

        if (onThisBoard && event.entity_type === 'board_member' && typeof window.updateBoardMembersDisplay === 'function') {
            window.updateBoardMembersDisplay();
        }

        const workspaceEvent = event.entity_type === 'workspace' || event.entity_type === 'workspace_member';
        const boardListingEvent = event.entity_type === 'board' || event.entity_type === 'board_member';
        const sameWorkspace = !event.workspace_id || !window.currentWorkspaceId || sameId(event.workspace_id, window.currentWorkspaceId);
        if (workspaceEvent || (boardListingEvent && sameWorkspace && !onThisBoard)) {
            scheduleRegionRefresh();
        }
    }

    const originalFetch = window.fetch.bind(window);
    window.PlanifyRealtime._fetch = originalFetch;
    window.fetch = function (input, init) {
        return originalFetch(input, init).then(function (response) {
            const url = typeof input === 'string' ? input : (input && input.url) || '';
            if (!/\/actions\/(card|comment|checklist|label|attachment)\//.test(url)) {
                return response;
            }
            const copy = response.clone();
            copy.json().then(function (data) {
                if (data && data.success === false && /not found|deleted|no longer/i.test(data.message || '') && window.currentCardId) {
                    window.PlanifyRealtime.invalidateCard(window.currentCardId, 'This task is no longer available.');
                }
            }).catch(function () {});
            return response;
        });
    };

    function connect() {
        const source = new EventSource(base() + '/actions/realtime/stream.php');
        source.onmessage = function (message) {
            try {
                onEvent(JSON.parse(message.data));
            } catch (error) {
                console.error('Live sync event ignored', error);
            }
        };
        source.onerror = function () {
            // The browser reconnects on its own using the stream's retry interval.
        };
    }

    document.addEventListener('planify:dragend', function () {
        if (boardSyncQueued && !window.isDragging) {
            boardSyncQueued = false;
            syncBoard();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', connect);
    } else {
        connect();
    }

    window.escapeText = escapeText;
})();
