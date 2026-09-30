<!-- Card Details Modal - Trello-style Layout -->
<div id="cardModal" class="fixed inset-0 bg-black/60 dark:bg-black/80 z-[250] flex items-center justify-center p-2 sm:p-3 overflow-y-auto hidden transition-all duration-300 opacity-0">
    <div class="card-modal-shell bg-gray-100 dark:bg-gray-900 rounded-xl w-full relative shadow-2xl transform transition-all duration-300 ease-out opacity-0 translate-y-4 scale-95 my-auto overflow-hidden" 
         id="cardModalContent">
        
        <!-- Header - Clean minimal design -->
        <div class="bg-primary/10 dark:bg-primary/20 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <!-- Left side - List name indicator (subtle) -->
            <div class="flex items-center gap-2">
                <span id="cardListName" class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="text-gray-400 dark:text-gray-500">in list</span>
                    <span class="font-medium text-gray-600 dark:text-gray-300 ml-1" id="cardListNameValue">Loading...</span>
                </span>
            </div>
            
            <!-- Right side - Action buttons -->
            <div class="flex items-center gap-1">
                <!-- Viewers Button with Dropdown -->
                <div class="relative" id="viewersDropdownContainer">
                    <button 
                        id="viewersBtn"
                        onclick="toggleViewersDropdown(event)"
                        class="p-2 text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-primary hover:bg-white/50 dark:hover:bg-gray-800 rounded transition-all cursor-pointer flex items-center gap-1.5" 
                        title="View who has seen this task">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span id="viewersCount" class="text-xs font-medium hidden">0</span>
                </button>
                    
                    <!-- Viewers Dropdown -->
                    <div id="viewersDropdown" class="viewers-dropdown hidden absolute right-0 top-full mt-1 w-64 rounded-lg shadow-xl z-50 overflow-hidden">
                        <div class="viewers-dropdown-header" style="padding: 10px 12px; border-bottom: 1px solid; display: flex; align-items: center; gap: 8px;">
                            <svg style="width: 14px; height: 14px; opacity: 0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span style="font-size: 12px; font-weight: 600;">Viewed by</span>
                        </div>
                        <div id="viewersList" style="max-height: 256px; overflow-y: auto;">
                            <!-- Viewers will be loaded here -->
                            <div style="padding: 16px; text-align: center;">
                                <div class="viewers-spinner animate-spin"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($canEdit)): ?>
                <button
                    type="button"
                    onclick="event.stopPropagation(); window.deleteCard(window.currentCardId)"
                    class="p-2 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 rounded transition-all"
                    title="Delete task"
                    aria-label="Delete task">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
                <?php endif; ?>

                <!-- Close Button -->
                <button onclick="closeCardModal()" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-white/50 dark:hover:bg-gray-800 rounded transition-all" title="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
            </div>
        </div>

        <!-- Main Content Area - Two Column Layout -->
        <div id="modalPanelsContainer" class="flex flex-col lg:flex-row relative flex-1 min-h-0">
            
            <!-- Left Column - Card Details -->
            <div id="leftPanel" class="flex-1 p-5 lg:p-6 lg:border-r-0 border-gray-200 dark:border-gray-800 overflow-y-auto overflow-x-hidden card-modal-scroll" style="min-width: 340px;">
                
                <!-- Card Title with Completion Checkbox -->
                <div class="flex items-start gap-3 mb-5">
                    <button 
                        id="cardCompleteBtn"
                        onclick="toggleCardCompleteFromModal()"
                        class="mt-1 w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all duration-200 hover:scale-110 border-gray-300 dark:border-gray-500 hover:border-green-400 dark:hover:border-green-400" 
                        title="Mark as complete"
                    ></button>
                    <h2 id="cardModalTitle" class="text-xl font-semibold text-gray-900 dark:text-white leading-tight"></h2>
                </div>

                <!-- Quick Actions Bar -->
                <div class="flex flex-wrap gap-2 mb-5 pb-5 border-b border-gray-200 dark:border-gray-800">
                    <!-- Labels Button -->
                    <div class="relative">
                        <button onclick="toggleLabelsPopup()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                        Labels
                    </button>
                        <div id="labelsPopup" class="action-popup hidden absolute left-0 top-full mt-1 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50">
                            <div class="p-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-800 dark:text-white">Labels</span>
                                <button onclick="toggleLabelsPopup()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                            <div id="labelsList" class="p-2 max-h-64 overflow-y-auto">
                                <div class="text-center py-4"><div class="animate-spin w-5 h-5 border-2 border-primary border-t-transparent rounded-full mx-auto"></div></div>
                            </div>
                            <div class="p-2 border-t border-gray-100 dark:border-gray-700">
                                <button onclick="showCreateLabel()" class="w-full text-left px-3 py-2 text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors">+ Create new label</button>
                            </div>
                            <div id="createLabelForm" class="hidden p-3 border-t border-gray-100 dark:border-gray-700">
                                <input type="text" id="newLabelName" placeholder="Label name" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white mb-2">
                                <div class="flex gap-1 mb-2" id="labelColorPicker">
                                    <button type="button" data-color="#ef4444" class="w-6 h-6 rounded bg-red-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#f97316" class="w-6 h-6 rounded bg-orange-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#eab308" class="w-6 h-6 rounded bg-yellow-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#22c55e" class="w-6 h-6 rounded bg-green-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#3b82f6" class="w-6 h-6 rounded bg-blue-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#8b5cf6" class="w-6 h-6 rounded bg-violet-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#ec4899" class="w-6 h-6 rounded bg-pink-500 hover:ring-2 ring-offset-1"></button>
                                    <button type="button" data-color="#6b7280" class="w-6 h-6 rounded bg-gray-500 hover:ring-2 ring-offset-1"></button>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="createLabel()" class="flex-1 px-3 py-1.5 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark">Create</button>
                                    <button onclick="hideCreateLabel()" class="px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dates Button -->
                    <div class="relative">
                        <button onclick="toggleDatesPopup()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Dates
                    </button>
                        <div id="datesPopup" class="action-popup hidden absolute left-0 top-full mt-1 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50">
                            <div class="p-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-800 dark:text-white">Dates</span>
                                <button onclick="toggleDatesPopup()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                            <div class="p-3 space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Start Date</label>
                                    <input type="date" id="cardStartDate" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Due Date</label>
                                    <input type="date" id="cardDueDate" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Due Time</label>
                                    <input type="time" id="cardDueTime" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
                                </div>
                                <div class="flex gap-2 pt-2">
                                    <button id="saveDatesBtn" onclick="saveDates()" class="flex-1 px-3 py-2 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition-all">Save</button>
                                    <button id="removeDatesBtn" onclick="removeDates()" class="px-3 py-2 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-all">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Members Button -->
                    <div class="relative">
                        <button onclick="toggleMembersPopup()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Members
                    </button>
                        <div id="membersPopup" class="action-popup hidden absolute left-0 top-full mt-1 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50">
                            <div class="p-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-800 dark:text-white">Members</span>
                                <button onclick="toggleMembersPopup()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                            <div id="memberAssignBar" class="hidden h-1 bg-primary/15 overflow-hidden">
                                <div class="member-assign-bar h-full bg-primary"></div>
                            </div>
                            <div id="membersList" class="p-2 max-h-64 overflow-y-auto">
                                <div class="text-center py-4"><div class="animate-spin w-5 h-5 border-2 border-primary border-t-transparent rounded-full mx-auto"></div></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attachment Button -->
                    <div class="relative">
                        <button onclick="toggleAttachmentPopup()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            Attachment
                        </button>
                        <div id="attachmentPopup" class="action-popup hidden absolute left-0 top-full mt-1 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50">
                            <div class="p-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-800 dark:text-white">Attach</span>
                                <button onclick="toggleAttachmentPopup()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                            <div class="p-3 space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Upload file</label>
                                    <input type="file" id="attachmentFileInput" class="w-full text-xs text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-primary file:text-white hover:file:bg-primary-dark file:cursor-pointer cursor-pointer mb-2">
                                    <button id="uploadFileBtn" onclick="uploadAttachment()" class="w-full px-3 py-2 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition-all">Upload File</button>
                                </div>
                                <div class="border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Attach a link</label>
                                    <input type="url" id="attachmentLinkUrl" placeholder="Paste link URL" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white mb-2">
                                    <input type="text" id="attachmentLinkName" placeholder="Link name (optional)" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white mb-2">
                                    <button id="addLinkBtn" onclick="addLinkAttachment()" class="w-full px-3 py-2 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition-all">Add Link</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Checklist feature toggle (right of attachments) -->
                    <div class="ml-auto flex items-center shrink-0">
                        <div class="inline-flex items-center gap-2 pl-2 border-l border-gray-200 dark:border-gray-700">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400 hidden sm:inline">Checklist</span>
                            <button type="button" id="checklistFeatureToggle" onclick="toggleChecklistFeature()"
                                    class="checklist-feature-toggle" role="switch" aria-checked="false" aria-label="Show checklist on this task"
                                    title="Show or hide checklist">
                                <span class="checklist-feature-toggle-knob" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Card Labels Display -->
                <div id="cardLabelsSection" class="hidden mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Labels</span>
                    </div>
                    <div id="cardLabelsDisplay" class="flex flex-wrap gap-1.5"></div>
                </div>
                
                <!-- Card Dates Display -->
                <div id="cardDatesSection" class="hidden mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Dates</span>
                    </div>
                    <div id="cardDatesDisplay" class="flex flex-wrap gap-2"></div>
                </div>
                
                <!-- Card Assignees Display -->
                <div id="cardAssigneesSection" class="hidden mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Members</span>
                    </div>
                    <div id="cardAssigneesDisplay" class="flex flex-wrap gap-1.5"></div>
                </div>

                <!-- Description Section -->
                <div class="mb-5">
                <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Description</span>
                        </div>
                        <button id="editDescriptionBtn" onclick="editDescription()" class="text-xs text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-primary transition-colors">
                            Edit
                        </button>
                    </div>
                    <div id="cardDescription" class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed pl-6 prose prose-sm dark:prose-invert max-w-none p-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50/50 dark:bg-gray-800/50">
                        <!-- Description content -->
                    </div>
                    <div id="descriptionEditor" class="hidden pl-6">
                        <textarea id="descriptionInput" class="w-full p-3 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary resize-y min-h-[120px]" rows="8" placeholder="Add a more detailed description..."></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" id="saveDescBtn" onclick="saveDescription()" class="px-3 py-1.5 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition-all">
                                Save
                            </button>
                            <button type="button" onclick="cancelEditDescription()" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-md transition-all">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Checklist (collapsible; visible when feature toggle is on; width matches description) -->
                <div id="checklistsSection" class="mb-5 hidden">
                    <div id="checklistsContainer" class="checklist-blocks w-full"></div>
                </div>

                <!-- Attachments Section -->
                <div id="attachmentsSection" class="hidden mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Attachments</span>
                    </div>
                    <div id="attachmentsList" class="space-y-2"></div>
                </div>

                    </div>

            <!-- Resizable Divider -->
            <div id="panelDivider" class="panel-divider hidden lg:flex">
                <div class="divider-handle">
                    <div class="divider-line"></div>
                </div>
                </div>

            <!-- Right Column - Comments and Activity -->
            <div id="rightPanel" class="p-5 lg:p-6 bg-white dark:bg-gray-800 lg:rounded-br-lg overflow-hidden flex flex-col min-h-0 card-modal-comments-panel">

                <!-- Comment Input -->
                <div class="mb-4">
                    <!-- Mini Toolbar -->
                    <div class="flex items-center gap-0.5 mb-2 text-gray-400 text-xs">
                        <!-- Text Format Dropdown -->
                        <div class="relative" id="textFormatDropdownContainer">
                            <button type="button" onclick="toggleTextFormatDropdown()" class="flex items-center gap-1 px-2 py-1.5 text-gray-500 dark:text-gray-400 font-medium hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors" title="Text Format">
                                <span>Aa</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div id="textFormatDropdown" class="hidden absolute left-0 top-full mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 py-1">
                                <button type="button" onclick="formatText('commentInput', 'heading'); toggleTextFormatDropdown();" class="w-full text-left px-3 py-1.5 text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Heading</button>
                                <button type="button" onclick="formatText('commentInput', 'normal'); toggleTextFormatDropdown();" class="w-full text-left px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Normal text</button>
                                <button type="button" onclick="formatText('commentInput', 'code'); toggleTextFormatDropdown();" class="w-full text-left px-3 py-1.5 text-sm font-mono text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Code</button>
                                <button type="button" onclick="formatText('commentInput', 'quote'); toggleTextFormatDropdown();" class="w-full text-left px-3 py-1.5 text-sm text-gray-500 dark:text-gray-400 italic hover:bg-gray-100 dark:hover:bg-gray-700 border-l-2 border-gray-300 dark:border-gray-600 ml-2">"Quote"</button>
                            </div>
                        </div>
                        <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                        <button type="button" onclick="formatText('commentInput', 'bold')" class="p-1.5 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors" title="Bold (Ctrl+B)">
                            <span class="font-bold">B</span>
                        </button>
                        <button type="button" onclick="formatText('commentInput', 'italic')" class="p-1.5 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors" title="Italic (Ctrl+I)">
                            <span class="italic">I</span>
                        </button>
                        <button type="button" onclick="formatText('commentInput', 'ul')" class="p-1.5 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors" title="Bullet List">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </button>
                        <div class="flex-1"></div>
                        <button type="button" onclick="document.getElementById('commentFileInput').click()" class="p-1.5 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors" title="Attach File">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </button>
                        <button type="button" onclick="formatText('commentInput', 'mention')" class="p-1.5 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors" title="Mention @">
                            <span class="font-medium">@</span>
                        </button>
                    </div>
                    
                    <!-- Hidden file input for attachments (images and files) -->
                    <input type="file" id="commentFileInput" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" class="hidden" onchange="handleCommentFileSelect(this)">
                    
                    <!-- Attachment Preview Area -->
                    <div id="commentAttachmentPreview" class="hidden mb-2">
                        <div class="relative inline-block bg-gray-100 dark:bg-gray-700 rounded-lg p-2 pr-8">
                            <!-- Image preview -->
                            <img id="commentImagePreviewImg" src="" alt="Preview" class="hidden max-h-32 rounded-lg border border-gray-200 dark:border-gray-600">
                            <!-- File preview -->
                            <div id="commentFilePreviewInfo" class="hidden flex items-center gap-2">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <div>
                                    <p id="commentFileName" class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate max-w-[150px]"></p>
                                    <p id="commentFileSize" class="text-xs text-gray-500 dark:text-gray-400"></p>
                                </div>
                            </div>
                            <button type="button" onclick="removeCommentAttachment()" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs shadow-md transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Input Area - Rich Text Editor -->
                    <div id="commentInput" 
                        contenteditable="true"
                        class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-primary focus:border-primary focus:bg-white dark:focus:bg-gray-800 transition-all min-h-[32px] max-h-[150px] overflow-y-auto outline-none empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400"
                        data-placeholder="Write a comment..."
                        onfocus="expandCommentInput(true)"
                        oninput="handleRichTextInput(this)"></div>
                    <input type="hidden" id="commentInputRaw" value="">
                    
                    <!-- Save Button Row -->
                    <div id="commentActions" class="hidden mt-2 flex items-center gap-2">
                        <button id="addCommentBtn" onclick="addComment()" class="px-3 py-1.5 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition-all">
                            Save
                        </button>
                        <span id="commentAttachmentIndicator" class="hidden text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <span id="attachmentIndicatorText">File attached</span>
                        </span>
                </div>
            </div>
            
                <!-- Activity/Comments List -->
                <div id="commentsContainer" class="space-y-3 flex-1 min-h-[200px] overflow-y-auto overflow-x-hidden custom-scrollbar pr-1 card-modal-comments-list">
                    <!-- Comments will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add checklist modal -->
<div id="checklistCreateModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-[260] flex items-center justify-center p-4 hidden" onclick="if (event.target === this) hideChecklistCreateModal()">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md border border-gray-200 dark:border-gray-700" role="dialog" aria-labelledby="checklistCreateModalTitle" aria-modal="true">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 id="checklistCreateModalTitle" class="text-base font-semibold text-gray-900 dark:text-white">Add checklist</h3>
            <button type="button" onclick="hideChecklistCreateModal()" class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5">
            <label for="newChecklistTitle" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Title</label>
            <input type="text" id="newChecklistTitle" placeholder="Checklist" value="Checklist" autocomplete="off" data-no-ai-assist
                   onkeydown="if(event.key==='Enter'){event.preventDefault();createChecklist();}"
                   class="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Each task can have one checklist.</p>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
            <button type="button" onclick="hideChecklistCreateModal(true)" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">Cancel</button>
            <button type="button" onclick="createChecklist()" class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary-dark rounded-lg transition-colors">Create checklist</button>
        </div>
    </div>
</div>

<!-- Styles -->
<style>
/* Task modal — large viewport footprint */
.card-modal-shell {
    width: min(1440px, 96vw);
    max-width: none;
    min-height: min(860px, 90vh);
    max-height: 96vh;
    display: flex;
    flex-direction: column;
}
#modalPanelsContainer {
    flex: 1 1 auto;
    min-height: 0;
}
.card-modal-scroll {
    max-height: calc(96vh - 2.75rem);
}
.card-modal-comments-panel {
    width: min(420px, 38vw);
    min-width: 300px;
    max-height: calc(96vh - 2.75rem);
}
@media (min-width: 1024px) {
    .card-modal-comments-panel {
        min-width: 360px;
    }
}
.card-modal-comments-list {
    max-height: none;
    flex: 1 1 auto;
}

/* Modal scrollbar */
#cardModalContent::-webkit-scrollbar { width: 6px; }
#cardModalContent::-webkit-scrollbar-track { background: transparent; }
#cardModalContent::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.dark #cardModalContent::-webkit-scrollbar-thumb { background: #4b5563; }

/* Comment input */
#commentInput:focus { min-height: 60px; }
#commentInput:empty:before { content: attr(data-placeholder); color: #9ca3af; pointer-events: none; }
#commentInput strong { font-weight: 700; }
#commentInput em { font-style: italic; }
#commentInput code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 12px; font-family: monospace; }
.dark #commentInput code { background: #374151; color: #e5e7eb; }

/* Activity/Comment items */
.activity-item { display: flex; gap: 0.625rem; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; overflow: hidden; }
.activity-item:last-child { border-bottom: none; }
.dark .activity-item { border-bottom-color: #374151; }
.activity-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0; }
.activity-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.activity-content { flex: 1; min-width: 0; overflow: hidden; }
.activity-header { display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap; }

/* Author name - theme aware */
.activity-author { font-size: 13px; font-weight: 600; color: #1f2937; }
.dark .activity-author { color: #f9fafb; }

/* Timestamp - theme aware */
.activity-time { font-size: 12px; color: #6b7280; }
.dark .activity-time { color: #9ca3af; }

/* Comment text - theme aware */
.activity-text { font-size: 13px; color: #374151; margin-top: 0.25rem; line-height: 1.5; }
.dark .activity-text { color: #e5e7eb; }
.activity-text strong { font-weight: 600; }
.activity-text em { font-style: italic; }
.activity-text code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 12px; color: #1f2937; }
.dark .activity-text code { background: #4b5563; color: #f3f4f6; }

/* Mention links - theme aware */
.mention-link { color: var(--color-primary-text); font-weight: 500; background: rgba(var(--color-primary-rgb), 0.12); padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
.dark .mention-link { color: #a5b4fc; background: rgba(129, 140, 248, 0.2); }

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; }

.member-assign-bar {
    width: 40%;
    animation: memberAssignSlide 0.8s ease-in-out infinite;
}
@keyframes memberAssignSlide {
    0% { transform: translateX(-120%); }
    100% { transform: translateX(280%); }
}

/* Viewers dropdown styles */
.viewers-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid var(--color-primary);
    border-top-color: transparent;
    border-radius: 50%;
    margin: 0 auto;
}
.viewers-dropdown {
    animation: dropdownFadeIn 0.15s ease-out;
    background: var(--surface-1) !important;
    border: 1px solid var(--surface-border) !important;
}
.viewers-dropdown-header {
    background: var(--surface-2) !important;
    border-color: var(--surface-border) !important;
    color: var(--color-primary-text) !important;
}
.viewer-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid var(--surface-border);
    transition: background-color 0.15s ease;
}
.viewer-item:last-child {
    border-bottom: none;
}
.viewer-item:hover {
    background: var(--surface-2);
}
.viewer-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--color-primary-light) 0%, var(--color-primary) 100%);
    color: var(--color-on-primary);
    font-size: 12px;
    font-weight: 600;
}
.viewer-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.viewer-info {
    flex: 1;
    min-width: 0;
}
.viewer-name {
    font-size: 13px;
    font-weight: 500;
    color: #171717;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.viewer-time {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 1px;
}
.viewer-count {
    font-size: 10px;
    color: var(--text-muted);
    background: var(--surface-2);
    padding: 2px 6px;
    border-radius: 9999px;
}
.viewers-empty {
    color: var(--text-muted);
    font-size: 12px;
}

.dark .viewer-name {
    color: #f5f5f5;
}

@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
#viewersList::-webkit-scrollbar { width: 4px; }
#viewersList::-webkit-scrollbar-track { background: transparent; }
#viewersList::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
.dark #viewersList::-webkit-scrollbar-thumb { background: #525252; }

/* Viewers button hover effect */
#viewersBtn:hover svg { stroke: var(--color-primary-text); }

/* Action popup styles */
.action-popup {
    animation: popupFadeIn 0.15s ease-out;
}
@keyframes popupFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Checklist block */
.checklist-blocks:empty {
    display: none;
}
.checklist-card {
    border-radius: 0.5rem;
    border: 2px solid var(--surface-border, #e5e7eb);
    background: rgba(255, 255, 255, 0.85);
    padding: 1rem 1.125rem;
}
.checklist-blocks {
    width: 100%;
}
.checklist-card.checklist-dropdown {
    width: 100%;
    padding: 0;
    overflow: hidden;
    border-width: 2px;
    border-radius: 0.5rem;
    background: rgba(249, 250, 251, 0.5);
}
.dark .checklist-card.checklist-dropdown {
    background: rgba(31, 41, 55, 0.5);
}
.checklist-item {
    cursor: pointer;
    user-select: none;
}
.checklist-item-checkbox {
    pointer-events: none;
}
.dark .checklist-card {
    background: rgba(31, 41, 55, 0.65);
    border-color: var(--surface-border, #4b5563);
}
.checklist-dropdown-trigger {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    text-align: left;
    border-radius: 0.375rem;
    transition: background-color 0.15s ease;
}
.checklist-dropdown-trigger:hover {
    background: rgba(0, 0, 0, 0.03);
}
.dark .checklist-dropdown-trigger:hover {
    background: rgba(255, 255, 255, 0.04);
}
.checklist-dropdown-chevron {
    transition: transform 0.2s ease;
}
.checklist-dropdown.is-open .checklist-dropdown-chevron {
    transform: rotate(90deg);
}
.checklist-dropdown-panel {
    padding: 0 0.875rem 0.75rem;
    border-top: 1px solid var(--surface-border, #e5e7eb);
}
.dark .checklist-dropdown-panel {
    border-top-color: #4b5563;
}
.checklist-dropdown-panel.is-collapsed {
    display: none;
}
.checklist-feature-toggle {
    position: relative;
    width: 2.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    background: #d1d5db;
    transition: background-color 0.2s ease;
    flex-shrink: 0;
}
.dark .checklist-feature-toggle {
    background: #4b5563;
}
.checklist-feature-toggle[aria-checked="true"] {
    background: var(--color-primary, #171717);
}
.checklist-feature-toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s ease;
}
.checklist-feature-toggle[aria-checked="true"] .checklist-feature-toggle-knob {
    transform: translateX(1rem);
}
#checklistsContainer .checklist-progress-track {
    height: 4px;
}

/* Checklist item check control */
.checklist-item-check {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
    cursor: pointer;
}
.checklist-item-checkbox {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    margin: 0;
    width: 100%;
    height: 100%;
}
.checklist-item-check-box {
    width: 100%;
    height: 100%;
    border-radius: 9999px;
    border: 2px solid #9ca3af;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s ease, background-color 0.15s ease, transform 0.1s ease;
    pointer-events: none;
}
.dark .checklist-item-check-box {
    border-color: #6b7280;
}
.checklist-item-check:hover .checklist-item-check-box {
    border-color: var(--color-primary, #171717);
}
.checklist-item-checkbox:focus-visible + .checklist-item-check-box {
    outline: 2px solid rgba(var(--color-primary-rgb, 23, 23, 23), 0.35);
    outline-offset: 2px;
}
.checklist-item-checkbox:checked + .checklist-item-check-box {
    background: #22c55e;
    border-color: #16a34a;
}
.checklist-item-checkbox:checked + .checklist-item-check-box svg {
    opacity: 1;
    transform: scale(1);
}
.checklist-item-check-box svg {
    width: 0.65rem;
    height: 0.65rem;
    color: #fff;
    opacity: 0;
    transform: scale(0.5);
    transition: opacity 0.12s ease, transform 0.12s ease;
}

.checklist-add-trigger-row {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.375rem;
    margin-left: -0.375rem;
    font-size: 0.8125rem;
    line-height: 1.25rem;
    color: #6b7280;
    border-radius: 0.375rem;
    transition: color 0.15s ease, background-color 0.15s ease;
}
.checklist-add-trigger-row:hover {
    color: var(--color-primary-text, #374151);
    background: rgba(0, 0, 0, 0.04);
}
.dark .checklist-add-trigger-row {
    color: #9ca3af;
}
.dark .checklist-add-trigger-row:hover {
    background: rgba(255, 255, 255, 0.06);
}
.checklist-add-form {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    margin-top: 0.125rem;
}
.checklist-add-form.is-hidden {
    display: none;
}
.checklist-add-input {
    flex: 1;
    min-width: 0;
    padding: 0.25rem 0.5rem;
    font-size: 0.8125rem;
    line-height: 1.25rem;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    border-radius: 0;
    background: transparent;
    color: inherit;
}
.checklist-add-input:focus {
    outline: none;
    border-bottom-color: var(--color-primary, #171717);
}
.dark .checklist-add-input {
    border-bottom-color: #4b5563;
}
.checklist-add-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.625rem;
    height: 1.625rem;
    flex-shrink: 0;
    border-radius: 0.375rem;
    color: #6b7280;
    transition: color 0.15s ease, background-color 0.15s ease;
}
.checklist-add-icon-btn:hover:not(:disabled) {
    color: var(--color-primary-text, #111);
    background: rgba(0, 0, 0, 0.05);
}
.checklist-add-icon-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.checklist-add-cancel-btn {
    font-size: 0.75rem;
    color: #9ca3af;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
}
.checklist-add-cancel-btn:hover {
    color: #6b7280;
    background: rgba(0, 0, 0, 0.04);
}

/* Image Lightbox */
.image-lightbox {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
}
.image-lightbox.active {
    opacity: 1;
    visibility: visible;
}
.image-lightbox img {
    max-width: 90%;
    max-height: 90%;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    transform: scale(0.9);
    transition: transform 0.2s ease;
}
.image-lightbox.active img {
    transform: scale(1);
}
.lightbox-controls {
    position: absolute;
    top: 20px;
    right: 20px;
    display: flex;
    gap: 10px;
}
.lightbox-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}
.lightbox-btn:hover {
    background: rgba(255, 255, 255, 0.25);
}
.lightbox-btn svg {
    width: 24px;
    height: 24px;
    color: white;
}

/* Panel Divider - Resizable */
.panel-divider {
    width: 12px;
    cursor: col-resize;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
    user-select: none;
    -webkit-user-select: none;
}
.divider-handle {
    width: 12px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
    border-radius: 4px;
}
.divider-line {
    width: 4px;
    height: 48px;
    background: #d1d5db;
    border-radius: 4px;
    transition: all 0.2s ease;
}
.dark .divider-line {
    background: #525252;
}
.panel-divider:hover .divider-handle {
    background: rgba(var(--color-primary-rgb), 0.12);
}
.panel-divider:hover .divider-line {
    background: var(--color-primary);
    height: 64px;
    width: 5px;
}
.panel-divider.dragging .divider-handle {
    background: rgba(var(--color-primary-rgb), 0.18);
}
.panel-divider.dragging .divider-line {
    background: var(--color-primary-dark);
    height: 80px;
    width: 6px;
}
/* Prevent text selection during drag */
body.resizing-panels {
    cursor: col-resize !important;
    user-select: none !important;
    -webkit-user-select: none !important;
}
body.resizing-panels * {
    cursor: col-resize !important;
}
/* Panel overflow handling */
#leftPanel, #rightPanel {
    max-height: calc(92vh - 60px);
}
/* Responsive - disable on smaller screens */
@media (max-width: 1023px) {
    .panel-divider {
        display: none !important;
    }
    #leftPanel, #rightPanel {
        width: 100% !important;
        min-width: 100% !important;
        flex: none !important;
    }
}
</style>

<!-- Image Lightbox Modal -->
<div id="imageLightbox" class="image-lightbox" onclick="closeLightbox(event)">
    <div class="lightbox-controls">
        <a id="lightboxDownload" href="#" download class="lightbox-btn" title="Download" onclick="event.stopPropagation()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        </a>
        <button class="lightbox-btn" onclick="closeLightbox(event)" title="Close">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <img id="lightboxImage" src="" alt="Preview">
</div>

<!-- JavaScript -->
<script>
if (typeof window.currentCardId === 'undefined') window.currentCardId = null;

// ========================================
// Panel Resizer - Draggable Divider
// ========================================
(function() {
    const STORAGE_KEY = 'planify_panel_divider_width';
    const MIN_LEFT_WIDTH = 300;
    const MIN_RIGHT_WIDTH = 280;
    const DEFAULT_RIGHT_WIDTH = 320;
    
    let isDragging = false;
    let startX = 0;
    let startRightWidth = 0;
    let divider = null;
    let leftPanel = null;
    let rightPanel = null;
    let container = null;
    
    // Get saved width from localStorage
    function getSavedWidth() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const width = parseInt(saved, 10);
                if (!isNaN(width) && width >= MIN_RIGHT_WIDTH) {
                    return width;
                }
            }
        } catch (e) {
            console.warn('Could not read panel width from localStorage:', e);
        }
        return DEFAULT_RIGHT_WIDTH;
    }
    
    // Save width to localStorage
    function saveWidth(width) {
        try {
            localStorage.setItem(STORAGE_KEY, width.toString());
        } catch (e) {
            console.warn('Could not save panel width to localStorage:', e);
        }
    }
    
    // Apply panel widths
    function applyWidths(rightWidth) {
        if (!leftPanel || !rightPanel || !container) return;
        
        const containerWidth = container.offsetWidth;
        const dividerWidth = divider ? divider.offsetWidth : 12;
        const maxRightWidth = containerWidth - MIN_LEFT_WIDTH - dividerWidth;
        
        // Enforce constraints
        rightWidth = Math.max(MIN_RIGHT_WIDTH, Math.min(rightWidth, maxRightWidth));
        
        rightPanel.style.width = rightWidth + 'px';
        rightPanel.style.flex = 'none';
        leftPanel.style.flex = '1';
        leftPanel.style.minWidth = MIN_LEFT_WIDTH + 'px';
        
        return rightWidth;
    }
    
    // Initialize the divider
    function initDivider() {
        divider = document.getElementById('panelDivider');
        leftPanel = document.getElementById('leftPanel');
        rightPanel = document.getElementById('rightPanel');
        container = document.getElementById('modalPanelsContainer');
        
        if (!divider || !leftPanel || !rightPanel || !container) {
            return;
        }
        
        // Apply saved width
        const savedWidth = getSavedWidth();
        applyWidths(savedWidth);
        
        // Mouse events for dragging
        divider.addEventListener('mousedown', startDrag);
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
        
        // Touch events for mobile/tablet
        divider.addEventListener('touchstart', startDragTouch, { passive: false });
        document.addEventListener('touchmove', onDragTouch, { passive: false });
        document.addEventListener('touchend', stopDrag);
    }
    
    function startDrag(e) {
        if (window.innerWidth < 1024) return; // Disabled on mobile
        
        e.preventDefault();
        isDragging = true;
        startX = e.clientX;
        startRightWidth = rightPanel.offsetWidth;
        
        divider.classList.add('dragging');
        document.body.classList.add('resizing-panels');
    }
    
    function startDragTouch(e) {
        if (window.innerWidth < 1024) return;
        
        e.preventDefault();
        isDragging = true;
        startX = e.touches[0].clientX;
        startRightWidth = rightPanel.offsetWidth;
        
        divider.classList.add('dragging');
        document.body.classList.add('resizing-panels');
    }
    
    function onDrag(e) {
        if (!isDragging) return;
        
        e.preventDefault();
        const deltaX = startX - e.clientX;
        const newRightWidth = startRightWidth + deltaX;
        applyWidths(newRightWidth);
    }
    
    function onDragTouch(e) {
        if (!isDragging) return;
        
        e.preventDefault();
        const deltaX = startX - e.touches[0].clientX;
        const newRightWidth = startRightWidth + deltaX;
        applyWidths(newRightWidth);
    }
    
    function stopDrag() {
        if (!isDragging) return;
        
        isDragging = false;
        divider.classList.remove('dragging');
        document.body.classList.remove('resizing-panels');
        
        // Save the current width
        if (rightPanel) {
            saveWidth(rightPanel.offsetWidth);
        }
    }
    
    // Re-apply widths when modal is shown (to restore saved position)
    window.restorePanelWidths = function() {
        setTimeout(() => {
            if (!divider) initDivider();
            const savedWidth = getSavedWidth();
            applyWidths(savedWidth);
        }, 50);
    };
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (rightPanel && leftPanel && container) {
            const currentWidth = rightPanel.offsetWidth;
            applyWidths(currentWidth);
        }
    });
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDivider);
    } else {
        initDivider();
    }
})();

// Image Lightbox Functions
window.openLightbox = function(imageSrc) {
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImg = document.getElementById('lightboxImage');
    const downloadBtn = document.getElementById('lightboxDownload');
    
    if (lightbox && lightboxImg) {
        lightboxImg.src = imageSrc;
        
        // Set download link
        if (downloadBtn) {
            downloadBtn.href = imageSrc;
            // Extract filename from path
            const filename = imageSrc.split('/').pop() || 'image.png';
            downloadBtn.download = filename;
        }
        
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
};

window.closeLightbox = function(event) {
    // Only close if clicking on backdrop or close button
    if (event && event.target.tagName === 'IMG') return;
    
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox) {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
};

// Close lightbox on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox && lightbox.classList.contains('active')) {
            closeLightbox();
        }
    }
});

function handleEscapeKey(e) { if (e.key === 'Escape') closeCardModal(); }
document.addEventListener('click', e => { if (e.target === document.getElementById('cardModal')) closeCardModal(); });
document.addEventListener('DOMContentLoaded', () => document.addEventListener('keydown', handleEscapeKey));

// Update completion checkbox UI
function updateCompletionCheckbox(isCompleted) {
    const btn = document.getElementById('cardCompleteBtn');
    const titleEl = document.getElementById('cardModalTitle');
    if (!btn) return;
    
    if (isCompleted) {
        btn.classList.remove('border-gray-300', 'dark:border-gray-500', 'hover:border-green-400', 'dark:hover:border-green-400');
        btn.classList.add('bg-green-500', 'border-green-500', 'text-white');
        btn.innerHTML = '<i class="fas fa-check text-xs"></i>';
        btn.title = 'Mark as incomplete';
        if (titleEl) titleEl.classList.add('line-through', 'text-gray-500');
    } else {
        btn.classList.add('border-gray-300', 'dark:border-gray-500', 'hover:border-green-400', 'dark:hover:border-green-400');
        btn.classList.remove('bg-green-500', 'border-green-500', 'text-white');
        btn.innerHTML = '';
        btn.title = 'Mark as complete';
        if (titleEl) titleEl.classList.remove('line-through', 'text-gray-500');
    }
}

// Toggle card completion from modal
window.toggleCardCompleteFromModal = async function() {
    if (!window.currentCardId) return;
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch((window.BASE_PATH || '') + '/actions/card/toggle_complete.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ card_id: window.currentCardId, _token: csrfToken })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update modal checkbox
            updateCompletionCheckbox(data.is_completed);
            
            // Update the card in the board view
            const cardElement = document.getElementById(`card-${window.currentCardId}`);
            if (cardElement) {
                const cardInner = cardElement.querySelector('.block.w-full.rounded-xl');
                const titleElement = cardElement.querySelector('h3');
                
                if (data.is_completed) {
                    cardElement.classList.add('card-completed');
                    if (cardInner) {
                        cardInner.classList.remove('bg-white', 'dark:bg-gray-800', 'border-gray-200/80', 'dark:border-gray-700', 'hover:border-primary/40', 'dark:hover:border-primary/50');
                        cardInner.classList.add('bg-gray-100', 'dark:bg-gray-700/50', 'border-gray-300', 'dark:border-gray-600');
                    }
                    if (titleElement) {
                        titleElement.classList.remove('text-gray-900', 'dark:text-gray-100');
                        titleElement.classList.add('text-gray-500', 'dark:text-gray-400', 'line-through');
                    }
                } else {
                    cardElement.classList.remove('card-completed');
                    if (cardInner) {
                        cardInner.classList.add('bg-white', 'dark:bg-gray-800', 'border-gray-200/80', 'dark:border-gray-700', 'hover:border-primary/40', 'dark:hover:border-primary/50');
                        cardInner.classList.remove('bg-gray-100', 'dark:bg-gray-700/50', 'border-gray-300', 'dark:border-gray-600');
                    }
                    if (titleElement) {
                        titleElement.classList.add('text-gray-900', 'dark:text-gray-100');
                        titleElement.classList.remove('text-gray-500', 'dark:text-gray-400', 'line-through');
                    }
                }
            }
            
            showToast(data.is_completed ? 'Task marked as completed' : 'Task marked as incomplete', 'success');
        } else {
            showToast(data.message || 'Failed to update task', 'error');
        }
    } catch (error) {
        console.error('Error toggling task completion:', error);
        showToast('Failed to update task', 'error');
    }
};

window.showCardModal = function() {
    const modal = document.getElementById('cardModal');
    const content = document.getElementById('cardModalContent');
    if (modal && content) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            modal.style.opacity = '1';
            content.style.opacity = '1';
            content.style.transform = 'translateY(0) scale(1)';
            // Restore saved panel divider position
            if (typeof window.restorePanelWidths === 'function') {
                window.restorePanelWidths();
            }
        });
    }
};

window.closeCardModal = function() {
    const modal = document.getElementById('cardModal');
    const content = document.getElementById('cardModalContent');
    if (modal && content) {
        content.style.opacity = '0';
        content.style.transform = 'translateY(10px) scale(0.98)';
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.style.opacity = '';
            content.style.opacity = '';
            content.style.transform = '';
            document.body.style.overflow = '';
            document.getElementById('cardModalTitle').textContent = '';
            document.getElementById('cardModalTitle').classList.remove('line-through', 'text-gray-500');
            document.getElementById('cardDescription').innerHTML = '';
            document.getElementById('commentsContainer').innerHTML = '';
            document.getElementById('commentInput').innerHTML = '';
            document.getElementById('commentInputRaw').value = '';
            // Reset list name
            const listNameValue = document.getElementById('cardListNameValue');
            if (listNameValue) listNameValue.textContent = 'Loading...';
            lastRawText = ''; // Reset raw text tracker
            // Reset completion checkbox
            updateCompletionCheckbox(false);
            // Clear any attached file
            if (window.removeCommentAttachment) window.removeCommentAttachment();
            collapseCommentInput();
            // Reset viewers dropdown
            const viewersDropdown = document.getElementById('viewersDropdown');
            const viewersCount = document.getElementById('viewersCount');
            if (viewersDropdown) viewersDropdown.classList.add('hidden');
            if (viewersCount) {
                viewersCount.textContent = '0';
                viewersCount.classList.add('hidden');
            }
            window.currentCardId = null;
        }, 200);
    }
};

function expandCommentInput(expand) {
    const actions = document.getElementById('commentActions');
    const input = document.getElementById('commentInput');
    if (expand) {
        actions.classList.remove('hidden');
        input.style.minHeight = '60px';
        if (input && !input._mentionInitialized && window.initMentionSystem && window.currentBoardId) {
            window.initMentionSystem(input, window.currentBoardId);
        }
        // Initialize paste handler for clipboard images
        if (window.initCommentPasteHandler) {
            window.initCommentPasteHandler();
        }
    }
}

function collapseCommentInput() {
    const actions = document.getElementById('commentActions');
    const input = document.getElementById('commentInput');
    if (input && !input.innerText.trim() && !window.commentAttachmentFile) {
        actions.classList.add('hidden');
        input.style.minHeight = '32px';
        input.innerHTML = '';
        // Also clear any attachment
        if (window.removeCommentAttachment) removeCommentAttachment();
    }
}

// Debounce timer for rich text rendering
let richTextTimer = null;
let lastRawText = ''; // Store the raw text before formatting
let isRendering = false; // Flag to prevent input handling during rendering

// Handle rich text input - just track the raw text, don't render formatting
// Formatting is only rendered when displaying saved comments, not while typing
function handleRichTextInput(el) {
    const rawInput = document.getElementById('commentInputRaw');
    if (!el || !rawInput) return;
    
    // Get the current raw text from contenteditable
    const currentText = el.innerText || '';
    
    // Always update raw text - this is what gets sent to the server
    // Remove non-breaking spaces that we add for cursor positioning
    const cleanText = currentText.replace(/[\u00A0]/g, ' ').replace(/[\u200B]/g, '');
    lastRawText = cleanText;
    rawInput.value = cleanText;
    
    // NOTE: We no longer auto-render formatting while typing
    // This prevents cursor issues with bold/italic text
    // The markdown syntax (**bold**, *italic*, `code`) will be visible while typing
    // and will be rendered properly when the comment is saved and displayed
}

// Note: We no longer render formatting inline while typing
// The markdown syntax is kept visible (**bold**, *italic*, `code`)
// Formatting is only rendered when comments are displayed after saving

// Get raw text from contenteditable (convert HTML back to markdown)
function getCommentText() {
    const input = document.getElementById('commentInput');
    if (!input) return '';
    
    // Get the inner text which preserves the structure
    let text = input.innerText || '';
    return text.trim();
}
    
// Toggle text format dropdown
function toggleTextFormatDropdown() {
    const dropdown = document.getElementById('textFormatDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('textFormatDropdownContainer');
    const dropdown = document.getElementById('textFormatDropdown');
    if (container && dropdown && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
    
function formatText(inputId, format) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.focus();
    
    // For contenteditable div
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        return;
    }
    
    const range = selection.getRangeAt(0);
    const selected = range.toString();
    
    // For mention, just insert @
    if (format === 'mention') {
        const atNode = document.createTextNode('@');
        range.deleteContents();
        range.insertNode(atNode);
        range.setStartAfter(atNode);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return;
    }
    
    // Handle block-level formats (heading, quote, normal)
    if (format === 'heading' || format === 'quote' || format === 'normal') {
        let prefix = '';
        switch(format) {
            case 'heading': prefix = '## '; break;
            case 'quote': prefix = '> '; break;
            case 'normal': prefix = ''; break;
        }
        
        if (selected) {
            // Wrap selected text with format
            const formattedText = document.createTextNode(prefix + selected);
            range.deleteContents();
            range.insertNode(formattedText);
            range.setStartAfter(formattedText);
            range.collapse(true);
        } else {
            // Insert prefix at cursor
            const prefixNode = document.createTextNode(prefix);
            range.insertNode(prefixNode);
            range.setStartAfter(prefixNode);
            range.collapse(true);
        }
        selection.removeAllRanges();
        selection.addRange(range);
        handleRichTextInput(input);
        return;
    }
    
    // If no text selected, insert placeholder markers and place cursor between them
    if (!selected) {
        let wrapper = '';
        switch(format) {
            case 'bold': wrapper = '**'; break;
            case 'italic': wrapper = '*'; break;
            case 'code': wrapper = '`'; break;
            case 'ul':
                const bullet = document.createTextNode('• ');
                range.insertNode(bullet);
                range.setStartAfter(bullet);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
                return;
        }
        // Insert markers with cursor in between
        const beforeMarker = document.createTextNode(wrapper);
        const afterMarker = document.createTextNode(wrapper);
        range.insertNode(afterMarker);
        range.insertNode(beforeMarker);
        // Place cursor between markers
        range.setStartAfter(beforeMarker);
        range.setEndBefore(afterMarker);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        return;
    }
    
    let wrapper = '';
    switch(format) {
        case 'bold':
            wrapper = '**';
            break;
        case 'italic':
            wrapper = '*';
            break;
        case 'code':
            wrapper = '`';
            break;
        case 'ul':
            // For list, just prepend bullet
            const bulletText = document.createTextNode('• ' + selected);
            range.deleteContents();
            range.insertNode(bulletText);
            handleRichTextInput(input);
            return;
    }
    
    // Wrap selected text with markdown markers
    const wrappedText = wrapper + selected + wrapper;
    const textNode = document.createTextNode(wrappedText);
    range.deleteContents();
    range.insertNode(textNode);
    
    // Add a space after for continued typing (as a separate text node)
    const spaceNode = document.createTextNode(' ');
    textNode.parentNode.insertBefore(spaceNode, textNode.nextSibling);
    
    // Update the raw text tracker
    lastRawText = input.innerText || '';
    const rawInput = document.getElementById('commentInputRaw');
    if (rawInput) rawInput.value = lastRawText.replace(/[\u00A0]/g, ' ');
    
    // Place cursor in the space node (after the formatted text)
    const newRange = document.createRange();
    newRange.setStart(spaceNode, 1);
    newRange.setEnd(spaceNode, 1);
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    // Keep focus on input
    input.focus();
}

window.editDescription = function() {
    const desc = document.getElementById('cardDescription');
    const editor = document.getElementById('descriptionEditor');
    const input = document.getElementById('descriptionInput');
    if (desc && editor && input) {
        // Check if it's the "No description" placeholder
        const textContent = desc.textContent?.trim();
        if (textContent === 'No description') {
            input.value = '';
        } else {
            // Convert HTML back to plain text with line breaks preserved
            // 1. Get innerHTML and replace <br> tags with newline markers
            let html = desc.innerHTML;
            // Replace <br>, <br/>, <br /> with newlines
            html = html.replace(/<br\s*\/?>/gi, '\n');
            
            // 2. Create a temp element to decode HTML entities
            const temp = document.createElement('textarea');
            temp.innerHTML = html;
            let text = temp.value;
            
            // 3. Strip any remaining HTML tags (shouldn't be any, but just in case)
            text = text.replace(/<[^>]*>/g, '');
            
            input.value = text;
        }
        
        desc.classList.add('hidden');
        editor.classList.remove('hidden');
        input.focus();
    }
};

window.cancelEditDescription = function() {
    document.getElementById('cardDescription')?.classList.remove('hidden');
    document.getElementById('descriptionEditor')?.classList.add('hidden');
};

window.saveDescription = function() {
    const cardId = window.currentCardId;
    const input = document.getElementById('descriptionInput');
    const newDesc = input?.value.trim() || '';
    const btn = document.getElementById('saveDescBtn');
    if (!cardId) return;
    
    // Show loading state
    if (window.btnLoading) btnLoading(btn, 'Saving...');
        
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_PATH || '') + '/actions/card/update_description.php', {
            method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ card_id: cardId, description: newDesc, _token: csrfToken })
    })
    .then(r => r.json())
        .then(data => {
        if (window.btnReset) btnReset(btn);
        if (data.success) {
            const desc = document.getElementById('cardDescription');
            desc.innerHTML = newDesc ? escapeHtml(newDesc).replace(/\n/g, '<br>') : '<span class="text-gray-400 italic">No description</span>';
            desc.classList.remove('hidden');
            document.getElementById('descriptionEditor').classList.add('hidden');
            if (window.showToast) window.showToast('Saved!', 'success');
        }
    })
    .catch(err => {
        if (window.btnReset) btnReset(btn);
        console.error('Save description error:', err);
        if (window.showToast) window.showToast('Failed to save', 'error');
    });
};

window.loadCardDetails = function(cardId, options) {
    if (!cardId) return;
    const opts = options && typeof options === 'object' ? options : {};
    window.currentCardId = cardId;
    
    // Reset list name while loading
    const listNameValue = document.getElementById('cardListNameValue');
    if (listNameValue) listNameValue.textContent = 'Loading...';
    
    // Track view first, then load viewers after tracking completes
    trackCardView(cardId).then(() => loadCardViewers(cardId));
    
    fetch(`${window.BASE_PATH || ''}/actions/card/get.php?id=${encodeURIComponent(cardId)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message);
            const card = data.card;
            
            document.getElementById('cardModalTitle').textContent = card.title || 'Untitled';
            // Update list name in the new structure
            const listNameEl = document.getElementById('cardListNameValue');
            if (listNameEl) listNameEl.textContent = card.list_name || 'List';
            
            // Update completion checkbox state
            updateCompletionCheckbox(card.is_completed);
            
            const desc = document.getElementById('cardDescription');
            desc.innerHTML = card.description?.trim() 
                ? escapeHtml(card.description).replace(/\n/g, '<br>') 
                : '<span class="text-gray-400 italic">No description</span>';
            document.getElementById('descriptionEditor').classList.add('hidden');
            desc.classList.remove('hidden');
            
            if (window.loadComments) window.loadComments(cardId);
            if (window.loadActivity) window.loadActivity(cardId);

            if (opts.syncUrl && data.link && data.link.o && typeof window.planifySyncBoardLocationWithOpen === 'function') {
                window.planifySyncBoardLocationWithOpen(data.link.o);
            }
            
            // Initialize mention system and paste handler
            requestAnimationFrame(() => {
                const input = document.getElementById('commentInput');
                if (input && window.initMentionSystem && window.currentBoardId) {
                    window.initMentionSystem(input, window.currentBoardId);
                }
                if (window.initCommentPasteHandler) {
                    window.initCommentPasteHandler();
                }
            });
            
            showCardModal();
        })
        .catch(err => {
            console.error(err);
            if (window.PlanifyRealtime && /not found|deleted|no longer/i.test(String(err && err.message || ''))) {
                window.PlanifyRealtime.invalidateCard(cardId, 'This task is no longer available.');
                return;
            }
            if (window.showToast) window.showToast('Failed to load', 'error');
        });
};

window.loadComments = function(cardId) {
    if (!cardId) return;
    const container = document.getElementById('commentsContainer');
    if (!container) return;
    
    container.innerHTML = '<div class="flex justify-center py-4"><div class="animate-spin rounded-full h-5 w-5 border-2 border-primary border-t-transparent"></div></div>';
    
    fetch(`${window.BASE_PATH || ''}/actions/comment/get.php?card_id=${encodeURIComponent(cardId)}`)
        .then(r => r.json())
        .then(data => {
            if (data.current_user_id !== undefined) window.currentUserId = Number(data.current_user_id);
            // Store server time for accurate relative time calculation (server_time is in database timezone)
            if (data.server_time) {
                // Parse server time (MySQL format: "YYYY-MM-DD HH:MM:SS")
                const serverDate = new Date(data.server_time.replace(' ', 'T'));
                if (!isNaN(serverDate.getTime())) {
                    window.serverTimeMs = serverDate.getTime();
                    window.clientTimeAtFetch = Date.now();
                }
            }
            const currentUserId = window.currentUserId ? Number(window.currentUserId) : null;
            
            if (data.success && data.comments?.length > 0) {
                container.innerHTML = data.comments.map(comment => {
                    const name = escapeHtml(comment.user_name || 'User');
                    
                    // Generate initials from name
                    const nameParts = (comment.user_name || 'User').trim().split(/\s+/);
                    let initials;
                    if (nameParts.length >= 2) {
                        // Multiple words: first letter of first and last word (e.g., "Vishwajeet Singh" -> "VS")
                        initials = (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase();
                    } else {
                        // Single word: first two letters (e.g., "Chetan" -> "CH")
                        initials = nameParts[0].substring(0, 2).toUpperCase();
                    }
                    
                    // Check if user has avatar
                    const hasUserAvatar = comment.user_avatar && comment.user_avatar.trim() !== '' && comment.user_avatar !== 'default-avatar.png';
                    
                    const content = renderCommentContent(comment.content, comment.mentioned_users || []);
                    const time = formatCommentTime(comment.created_at);
                    const isOwner = currentUserId && Number(comment.user_id) === currentUserId;
                    const hasAttachment = comment.image_path && comment.image_path.trim() !== '';
                    const isImage = comment.attachment_type === 'image' || (!comment.attachment_type && hasAttachment);
                    
                    // Build attachment HTML if exists
                    let attachmentHtml = '';
                    if (hasAttachment) {
                        const attachmentPath = comment.image_path.startsWith('/') ? comment.image_path : (window.BASE_PATH || '') + '/uploads/' + comment.image_path;
                        const attachmentName = comment.attachment_name || comment.image_path;
                        
                        if (isImage) {
                            attachmentHtml = `
                                <div class="mt-2">
                                    <img src="${escapeHtml(attachmentPath)}" alt="Comment image" 
                                         class="max-w-full max-h-48 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:opacity-90 transition-opacity"
                                         onclick="openLightbox('${escapeHtml(attachmentPath)}')">
                                </div>`;
                        } else {
                            // File attachment display
                            const fileExt = attachmentName.split('.').pop().toLowerCase();
                            let fileIcon = '';
                            if (['pdf'].includes(fileExt)) {
                                fileIcon = '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h8v2H8zm0 4h8v2H8z"/></svg>';
                            } else if (['doc', 'docx'].includes(fileExt)) {
                                fileIcon = '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h8v2H8zm0 4h5v2H8z"/></svg>';
                            } else if (['xls', 'xlsx'].includes(fileExt)) {
                                fileIcon = '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h3v2H8zm5 0h3v2h-3zm-5 4h3v2H8zm5 0h3v2h-3z"/></svg>';
                            } else if (['zip', 'rar', '7z'].includes(fileExt)) {
                                fileIcon = '<svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM6 20V4h5v7h7v9H6z"/><path d="M10 9h2v2h-2zm0 3h2v2h-2zm0 3h2v2h-2z"/></svg>';
                            } else {
                                fileIcon = '<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
                            }
                            
                            attachmentHtml = `
                                <div class="mt-2">
                                    <a href="${escapeHtml(attachmentPath)}" target="_blank" download="${escapeHtml(attachmentName)}"
                                       class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg border border-gray-200 dark:border-gray-600 transition-colors">
                                        ${fileIcon}
                                        <span class="text-sm text-gray-700 dark:text-gray-200 truncate max-w-[200px]">${escapeHtml(attachmentName)}</span>
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                </div>`;
                        }
                    }
                    
                    return `
                        <div class="activity-item group" id="comment-${comment.id}">
                            ${hasUserAvatar 
                                ? `<img src="${window.BASE_PATH || ''}/assets/uploads/avatars/${escapeHtml(comment.user_avatar)}" alt="" class="activity-avatar-img">`
                                : `<div class="activity-avatar bg-primary text-white">${initials}</div>`
                            }
                            <div class="activity-content">
                                <div class="activity-header">
                                    <span class="activity-author">${name}</span>
                                    <span class="activity-time">${time}</span>
                                                ${isOwner ? `
                                        <div class="opacity-0 group-hover:opacity-100 flex gap-1 ml-auto transition-opacity">
                                            <button onclick="editComment(${comment.id}, \`${escapeHtml(comment.content || '').replace(/`/g, '\\`')}\`)" class="p-1 text-gray-400 hover:text-primary text-xs rounded hover:bg-gray-100 dark:hover:bg-gray-700" title="Edit">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                        </button>
                                            <button onclick="deleteComment(${comment.id})" class="p-1 text-gray-400 hover:text-red-500 text-xs rounded hover:bg-gray-100 dark:hover:bg-gray-700" title="Delete">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                    </div>
                                                ` : ''}
                                </div>
                                ${content ? `<div class="activity-text">${content}</div>` : ''}
                                ${attachmentHtml}
                            </div>
                        </div>`;
                }).join('');
            } else {
                container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No comments yet</p>';
            }
        });
};

function renderCommentContent(content, mentionedUsers) {
    if (!content) return '';
    
    // First escape HTML special characters
    let html = escapeHtml(content);
    
    // Convert markdown-style formatting (order matters: bold before italic)
    // Bold: **text** - use non-greedy match
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // Italic: *text* - match single asterisks only (not double)
    html = html.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
    // Code: `text`
    html = html.replace(/`([^`\n]+)`/g, '<code class="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono">$1</code>');
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    
    // Highlight mentions
    if (mentionedUsers?.length) {
        mentionedUsers.forEach(user => {
            const escapedName = escapeHtml(user.name);
            const pattern = new RegExp(`@${escapedName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`, 'gi');
            html = html.replace(pattern, `<span class="mention-link">@${escapedName}</span>`);
        });
    }
    // Also highlight @mentions that weren't matched
    html = html.replace(/@(\w+)/g, (match, name) => {
        if (html.indexOf(`mention-link">@${name}`) === -1) {
            return `<span class="mention-link">${match}</span>`;
            }
        return match;
    });
    return html;
}

function formatCommentTime(dateString) {
    if (!dateString) return '';
    
    // Parse the date string (MySQL format: "YYYY-MM-DD HH:MM:SS")
    let date;
    if (dateString.includes('T')) {
        date = new Date(dateString);
    } else {
        // MySQL datetime format - parse as local time (same timezone as server)
        date = new Date(dateString.replace(' ', 'T'));
    }
    
    if (isNaN(date.getTime())) {
        // Fallback: try direct parsing
        date = new Date(dateString);
    }
    
    if (isNaN(date.getTime())) {
        return dateString; // Return original if parsing fails
    }
    
    // Calculate "now" based on server time if available
    let nowMs;
    if (window.serverTimeMs && window.clientTimeAtFetch) {
        // Adjust for time elapsed since we fetched server time
        const elapsed = Date.now() - window.clientTimeAtFetch;
        nowMs = window.serverTimeMs + elapsed;
    } else {
        nowMs = Date.now();
    }
    
    const diff = Math.floor((nowMs - date.getTime()) / 1000);
    
    if (diff < 0) return '0 min ago'; // Future dates (shouldn't happen, but handle gracefully)
    if (diff < 60) return '0 min ago';
    if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hr ago`;
    if (diff < 172800) return '1 day ago';
    if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
    
    // For older comments, show date
    const now = new Date(nowMs);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
}

window.escapeHtml = function(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
};

// Store selected attachment file
window.commentAttachmentFile = null;
window.commentAttachmentIsImage = false;

// Format file size for display
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Handle file selection for comment (images and other files)
window.handleCommentFileSelect = function(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file size (max 10MB)
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        if (window.showToast) window.showToast('File must be less than 10MB', 'error');
        return;
    }
    
    // Allowed file types
    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'
    ];
    
    const isImage = file.type.startsWith('image/');
    
    if (!allowedTypes.includes(file.type) && !isImage) {
        if (window.showToast) window.showToast('File type not allowed', 'error');
        return;
    }
    
    window.commentAttachmentFile = file;
    window.commentAttachmentIsImage = isImage;
    
    const preview = document.getElementById('commentAttachmentPreview');
    const previewImg = document.getElementById('commentImagePreviewImg');
    const fileInfo = document.getElementById('commentFilePreviewInfo');
    const fileName = document.getElementById('commentFileName');
    const fileSize = document.getElementById('commentFileSize');
    const indicator = document.getElementById('commentAttachmentIndicator');
    const indicatorText = document.getElementById('attachmentIndicatorText');
    
    if (isImage) {
        // Show image preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewImg.classList.remove('hidden');
            fileInfo.classList.add('hidden');
            preview.classList.remove('hidden');
            if (indicator) indicator.classList.remove('hidden');
            if (indicatorText) indicatorText.textContent = 'Image attached';
        };
        reader.readAsDataURL(file);
    } else {
        // Show file info preview
        previewImg.classList.add('hidden');
        fileInfo.classList.remove('hidden');
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        preview.classList.remove('hidden');
        if (indicator) indicator.classList.remove('hidden');
        if (indicatorText) indicatorText.textContent = 'File attached';
    }
    
    // Expand comment input area
    expandCommentInput(true);
};

// Remove selected attachment
window.removeCommentAttachment = function() {
    window.commentAttachmentFile = null;
    window.commentAttachmentIsImage = false;
    
    const preview = document.getElementById('commentAttachmentPreview');
    const previewImg = document.getElementById('commentImagePreviewImg');
    const fileInfo = document.getElementById('commentFilePreviewInfo');
    const indicator = document.getElementById('commentAttachmentIndicator');
    const fileInput = document.getElementById('commentFileInput');
    
    previewImg.src = '';
    previewImg.classList.add('hidden');
    fileInfo.classList.add('hidden');
    preview.classList.add('hidden');
    if (indicator) indicator.classList.add('hidden');
    if (fileInput) fileInput.value = '';
};

// Backward compatibility
window.removeCommentImage = window.removeCommentAttachment;

// Handle paste event for clipboard images (screenshots, copied images)
window.handleCommentPaste = function(event) {
    const clipboardData = event.clipboardData || window.clipboardData;
    if (!clipboardData) return;
    
    const items = clipboardData.items;
    if (!items) return;
    
    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        
        // Check if it's an image
        if (item.type.startsWith('image/')) {
            event.preventDefault(); // Prevent default paste behavior for images
            
            const file = item.getAsFile();
            if (!file) continue;
            
            // Create a proper filename for the pasted image
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const extension = item.type.split('/')[1] || 'png';
            const newFile = new File([file], `screenshot_${timestamp}.${extension}`, { type: item.type });
            
            // Validate file size (max 10MB)
            const maxSize = 10 * 1024 * 1024;
            if (newFile.size > maxSize) {
                if (window.showToast) window.showToast('Image must be less than 10MB', 'error');
                return;
            }
            
            // Store the file
            window.commentAttachmentFile = newFile;
            window.commentAttachmentIsImage = true;
            
            // Show preview
            const preview = document.getElementById('commentAttachmentPreview');
            const previewImg = document.getElementById('commentImagePreviewImg');
            const fileInfo = document.getElementById('commentFilePreviewInfo');
            const indicator = document.getElementById('commentAttachmentIndicator');
            const indicatorText = document.getElementById('attachmentIndicatorText');
            
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewImg.classList.remove('hidden');
                fileInfo.classList.add('hidden');
                preview.classList.remove('hidden');
                if (indicator) indicator.classList.remove('hidden');
                if (indicatorText) indicatorText.textContent = 'Screenshot attached';
            };
            reader.readAsDataURL(newFile);
            
            // Expand comment input area
            expandCommentInput(true);
            
            if (window.showToast) window.showToast('Screenshot pasted!', 'success');
            return; // Only handle first image
        }
    }
};

// Initialize paste handler on comment input
document.addEventListener('DOMContentLoaded', function() {
    const commentInput = document.getElementById('commentInput');
    if (commentInput) {
        commentInput.addEventListener('paste', window.handleCommentPaste);
    }
});

// Also set up paste handler when card modal opens (for dynamically loaded content)
window.initCommentPasteHandler = function() {
    const commentInput = document.getElementById('commentInput');
    if (commentInput && !commentInput._pasteHandlerAttached) {
        commentInput.addEventListener('paste', window.handleCommentPaste);
        commentInput._pasteHandlerAttached = true;
    }
};

window.addComment = function() {
    const input = document.getElementById('commentInput');
    const rawInput = document.getElementById('commentInputRaw');
    // Use the stored raw text if available (contains markdown), otherwise use innerText
    const text = (rawInput?.value || input?.innerText || '').trim();
    const mentionedIds = window.mentionSystem?.getMentionedUserIds() || [];
    
    // Check if we have text or attachment
    if (!text && !window.commentAttachmentFile) {
        if (window.showToast) window.showToast('Please enter a comment or add an attachment', 'error');
        return;
    }
    
    const btn = document.getElementById('addCommentBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
    
    // Always use FormData to handle both text-only and file uploads consistently
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('card_id', window.currentCardId);
    formData.append('content', text || '');
    formData.append('mentioned_user_ids', JSON.stringify(mentionedIds));
    formData.append('_token', csrfToken);
    
    if (window.commentAttachmentFile) {
        formData.append('attachment', window.commentAttachmentFile);
        formData.append('is_image', window.commentAttachmentIsImage ? '1' : '0');
    }
    
    fetch((window.BASE_PATH || '') + '/actions/comment/create.php', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(r => {
        if (!r.ok) {
            return r.text().then(text => {
                throw new Error('Server error: ' + r.status);
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            input.innerHTML = '';
            if (rawInput) rawInput.value = '';
            lastRawText = '';
            removeCommentAttachment();
            collapseCommentInput();
            if (window.loadComments) window.loadComments(window.currentCardId);
            if (mentionedIds.length && window.updateCardMentions) window.updateCardMentions(window.currentCardId);
            if (window.showToast) window.showToast('Comment added', 'success');
        } else {
            console.error('[Comment] Server returned error:', data);
            if (window.showToast) window.showToast(data.message || 'Failed to add comment', 'error');
        }
    })
    .catch(err => {
        console.error('Error adding comment:', err);
        if (window.showToast) window.showToast('Failed to add comment', 'error');
    })
    .finally(() => { 
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; } 
    });
};

window.editComment = function(id, content) {
    const el = document.getElementById(`comment-${id}`);
    if (!el) return;
    const textEl = el.querySelector('.activity-text');
    if (!textEl) return;
    
    textEl.innerHTML = `
        <textarea class="w-full p-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-primary resize-none" rows="2" id="edit-${id}">${escapeHtml(content)}</textarea>
        <div class="flex gap-2 mt-2">
            <button onclick="saveEditComment(${id})" class="px-2.5 py-1 text-xs font-medium bg-primary text-white rounded-md hover:bg-primary-dark">Save</button>
            <button onclick="loadComments(window.currentCardId)" class="px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">Cancel</button>
        </div>`;
    
    const textarea = document.getElementById(`edit-${id}`);
    if (textarea) { textarea.focus(); textarea.setSelectionRange(textarea.value.length, textarea.value.length); }
};

window.saveEditComment = function(id) {
    const textarea = document.getElementById(`edit-${id}`);
    if (!textarea?.value.trim()) return;
    
    fetch((window.BASE_PATH || '') + '/actions/comment/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, content: textarea.value.trim() })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (window.loadComments) window.loadComments(window.currentCardId);
            if (window.showToast) window.showToast('Updated', 'success');
        } else {
            if (window.showToast) window.showToast(data.message || 'Failed to update', 'error');
        }
    })
    .catch(err => {
        console.error('Update error:', err);
        if (window.showToast) window.showToast('Failed to update comment', 'error');
    });
};

window.deleteComment = async function(id) {
    const ok = await planifyConfirm({ title: 'Delete comment?', message: 'This comment will be permanently removed.', confirmLabel: 'Delete', danger: true });
    if (!ok) return;
    fetch((window.BASE_PATH || '') + '/actions/comment/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById(`comment-${id}`);
            if (el) { el.style.opacity = '0'; el.style.transform = 'translateX(-10px)'; setTimeout(() => el.remove(), 200); }
            if (window.showToast) window.showToast('Deleted', 'success');
        } else {
            if (window.showToast) window.showToast(data.message || 'Failed to delete', 'error');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        if (window.showToast) window.showToast('Failed to delete comment', 'error');
    });
};

document.addEventListener('click', e => {
    const wrapper = document.getElementById('commentInput')?.parentElement;
    if (wrapper && !wrapper.contains(e.target)) collapseCommentInput();
});

// =====================================================
// CARD VIEWERS FUNCTIONALITY
// =====================================================

// Track that current user viewed this card (returns Promise)
window.trackCardView = function(cardId) {
    if (!cardId) return Promise.resolve();
    
    return fetch((window.BASE_PATH || '') + '/actions/card/viewers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ card_id: cardId })
    })
    .then(r => r.json())
    .then(data => data)
    .catch(() => ({ success: false }));
};

// Load and display viewers list
window.loadCardViewers = function(cardId) {
    if (!cardId) return;
    
    const viewersList = document.getElementById('viewersList');
    const viewersCount = document.getElementById('viewersCount');
    
    if (!viewersList) return;
    
    // Show loading state
    viewersList.innerHTML = `
        <div style="padding: 20px; text-align: center;">
            <div class="viewers-spinner animate-spin" style="margin-bottom: 8px;"></div>
            <p class="viewers-empty">Loading...</p>
        </div>`;
    
    fetch(`${window.BASE_PATH || ''}/actions/card/viewers.php?card_id=${encodeURIComponent(cardId)}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.viewers) {
                const viewers = data.viewers;
                
                syncViewerClock(data.server_time_ms, data.server_time);
                
                // Update count badge
                if (viewersCount) {
                    viewersCount.textContent = viewers.length;
                    if (viewers.length > 0) {
                        viewersCount.classList.remove('hidden');
                    } else {
                        viewersCount.classList.add('hidden');
                    }
                }
                
                // Render viewers list
                if (viewers.length === 0) {
                    viewersList.innerHTML = `
                        <div style="padding: 16px; text-align: center;">
                            <p class="viewers-empty">No one has viewed this task yet</p>
                        </div>`;
                } else {
                    viewersList.innerHTML = viewers.map(viewer => {
                        // Generate initials from name
                        const nameParts = (viewer.name || 'User').trim().split(/\s+/);
                        let initials;
                        if (nameParts.length >= 2) {
                            // Multiple words: first letter of first and last word (e.g., "Vishwajeet Singh" -> "VS")
                            initials = (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase();
                        } else {
                            // Single word: first two letters (e.g., "Chetan" -> "CH")
                            initials = nameParts[0].substring(0, 2).toUpperCase();
                        }
                        
                        const viewedMs = viewer.last_viewed_at_ms || parseUtcToMs(viewer.last_viewed_at);
                        const timeAgo = formatViewerTimeMs(viewedMs);
                        const hasAvatar = viewer.avatar && viewer.avatar.trim() !== '' && viewer.avatar !== 'default-avatar.png';
                        
                        return `
                            <div class="viewer-item">
                                <div class="viewer-avatar">
                                    ${hasAvatar 
                                        ? `<img src="${window.BASE_PATH || ''}/assets/uploads/avatars/${escapeHtml(viewer.avatar)}" alt="">`
                                        : initials
                                    }
                                </div>
                                <div class="viewer-info">
                                    <div class="viewer-name">${escapeHtml(viewer.name)}</div>
                                    <div class="viewer-time" data-viewed-ms="${viewedMs || ''}">${timeAgo}</div>
                                </div>
                                ${viewer.view_count > 1 
                                    ? `<span class="viewer-count">${viewer.view_count}x</span>`
                                    : ''
                                }
                            </div>`;
                    }).join('');
                    startViewerTimeTicker();
                }
            } else {
                viewersList.innerHTML = `
                    <div style="padding: 16px; text-align: center;">
                        <p class="viewers-empty">Could not load viewers</p>
                    </div>`;
            }
        })
        .catch(err => {
            console.error('Error loading viewers:', err);
            viewersList.innerHTML = `
                <div class="px-3 py-4 text-center">
                    <p class="text-xs text-red-400">Error loading viewers</p>
                </div>`;
        });
};

function parseUtcToMs(value) {
    if (value == null || value === '') return NaN;
    if (typeof value === 'number') return value;
    let s = String(value).trim();
    if (s.includes(' ') && !s.includes('T')) {
        s = s.replace(' ', 'T') + 'Z';
    } else if (/^\d{4}-\d{2}-\d{2}T/.test(s) && !/[zZ]$/.test(s) && !/[+-]\d{2}:?\d{2}$/.test(s)) {
        s += 'Z';
    }
    return Date.parse(s);
}

function getViewerNowMs() {
    if (window.viewerServerTimeMs != null && window.viewerClientTimeAtFetch != null) {
        return window.viewerServerTimeMs + (Date.now() - window.viewerClientTimeAtFetch);
    }
    return Date.now();
}

function syncViewerClock(serverTimeMs, serverTimeUtc) {
    let ms = typeof serverTimeMs === 'number' ? serverTimeMs : parseUtcToMs(serverTimeUtc);
    if (!isNaN(ms)) {
        window.viewerServerTimeMs = ms;
        window.viewerClientTimeAtFetch = Date.now();
    }
}

function formatRelativeTimeMs(timestampMs, nowMs) {
    if (timestampMs == null || isNaN(timestampMs)) return '';
    const diff = Math.floor((nowMs - timestampMs) / 1000);
    if (diff < 0) return 'just now';
    if (diff < 60) return 'just now';
    if (diff < 3600) {
        const m = Math.floor(diff / 60);
        return m === 1 ? '1 min ago' : `${m} min ago`;
    }
    if (diff < 86400) {
        const h = Math.floor(diff / 3600);
        return h === 1 ? '1 hr ago' : `${h} hr ago`;
    }
    if (diff < 172800) return '1 day ago';
    if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
    const date = new Date(timestampMs);
    const now = new Date(nowMs);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
}

function formatViewerTimeMs(timestampMs) {
    return formatRelativeTimeMs(timestampMs, getViewerNowMs());
}

function refreshViewerTimeLabels() {
    document.querySelectorAll('.viewer-time[data-viewed-ms]').forEach(el => {
        const ms = parseInt(el.dataset.viewedMs, 10);
        if (!isNaN(ms)) {
            el.textContent = formatViewerTimeMs(ms);
        }
    });
}

let viewerTimeTicker = null;

function startViewerTimeTicker() {
    stopViewerTimeTicker();
    refreshViewerTimeLabels();
    viewerTimeTicker = setInterval(refreshViewerTimeLabels, 30000);
}

function stopViewerTimeTicker() {
    if (viewerTimeTicker) {
        clearInterval(viewerTimeTicker);
        viewerTimeTicker = null;
    }
}

// Toggle viewers dropdown
window.toggleViewersDropdown = function(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('viewersDropdown');
    if (dropdown) {
        const isOpening = dropdown.classList.contains('hidden');
        dropdown.classList.toggle('hidden');
        
        if (isOpening && window.currentCardId) {
            loadCardViewers(window.currentCardId);
        } else if (!isOpening) {
            stopViewerTimeTicker();
        }
    }
};

// Close viewers dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('viewersDropdownContainer');
    const dropdown = document.getElementById('viewersDropdown');
    
    if (container && dropdown && !container.contains(e.target)) {
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
            stopViewerTimeTicker();
        }
    }
});

// Close dropdown on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const dropdown = document.getElementById('viewersDropdown');
        if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
            stopViewerTimeTicker();
        }
        // Close all action popups
        document.querySelectorAll('.action-popup').forEach(p => p.classList.add('hidden'));
    }
});

// Close action popups when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-popup') && !e.target.closest('button')) {
        document.querySelectorAll('.action-popup').forEach(p => p.classList.add('hidden'));
    }
});

// =====================================================
// LABELS FUNCTIONALITY
// =====================================================
let selectedLabelColor = '#171717';

window.toggleLabelsPopup = function() {
    const popup = document.getElementById('labelsPopup');
    const wasHidden = popup.classList.contains('hidden');
    document.querySelectorAll('.action-popup').forEach(p => p.classList.add('hidden'));
    if (wasHidden) {
        popup.classList.remove('hidden');
        loadLabels();
    }
};

window.loadLabels = function() {
    if (!window.currentCardId || !window.currentBoardId) return;
    const container = document.getElementById('labelsList');
    container.innerHTML = '<div class="text-center py-4"><div class="animate-spin w-5 h-5 border-2 border-primary border-t-transparent rounded-full mx-auto"></div></div>';
    
    fetch(`${window.BASE_PATH || ''}/actions/label/get.php?board_id=${window.currentBoardId}&card_id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.labels.length === 0) {
                    container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No labels yet</p>';
                } else {
                    container.innerHTML = data.labels.map(label => `
                        <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer" onclick="toggleLabel(${label.id})">
                            <div class="w-full h-8 rounded flex items-center justify-between px-3" style="background-color: ${label.color}">
                                <span class="text-white text-sm font-medium">${escapeHtml(label.name || '')}</span>
                                ${label.selected ? '<svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : ''}
                            </div>
                        </div>
                    `).join('');
                }
                updateCardLabelsDisplay(data.labels.filter(l => l.selected));
            }
        })
        .catch(err => console.error('Load labels error:', err));
};

window.toggleLabel = function(labelId) {
    if (!window.currentCardId) return;
    fetch((window.BASE_PATH || '') + '/actions/label/toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ card_id: window.currentCardId, label_id: labelId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadLabels();
            updateCardLabelsDisplay(data.card_labels);
        } else {
            showToast(data.message || 'Failed', 'error');
        }
    });
};

window.showCreateLabel = function() {
    document.getElementById('createLabelForm').classList.remove('hidden');
};

window.hideCreateLabel = function() {
    document.getElementById('createLabelForm').classList.add('hidden');
    document.getElementById('newLabelName').value = '';
};

window.createLabel = function() {
    const name = document.getElementById('newLabelName').value.trim();
    fetch((window.BASE_PATH || '') + '/actions/label/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ board_id: window.currentBoardId, name, color: selectedLabelColor })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.label) {
            hideCreateLabel();
            // Automatically add the newly created label to the current card
            fetch((window.BASE_PATH || '') + '/actions/label/toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ card_id: window.currentCardId, label_id: data.label.id })
            })
            .then(r => r.json())
            .then(toggleData => {
                if (toggleData.success) {
                    loadLabels();
                    updateCardLabelsDisplay(toggleData.card_labels);
                    showToast('Label created and applied', 'success');
                }
            });
        } else {
            showToast(data.message || 'Failed', 'error');
        }
    });
};

// Label color picker
document.querySelectorAll('#labelColorPicker button').forEach(btn => {
    btn.addEventListener('click', function() {
        selectedLabelColor = this.dataset.color;
        document.querySelectorAll('#labelColorPicker button').forEach(b => b.classList.remove('ring-2', 'ring-primary'));
        this.classList.add('ring-2', 'ring-primary');
    });
});

window.updateCardLabelsDisplay = function(labels) {
    const section = document.getElementById('cardLabelsSection');
    const display = document.getElementById('cardLabelsDisplay');
    if (!labels || labels.length === 0) {
        section.classList.add('hidden');
        return;
    }
    section.classList.remove('hidden');
    display.innerHTML = labels.map(l => `
        <span class="px-2 py-1 text-xs font-medium text-white rounded" style="background-color: ${l.color}">${escapeHtml(l.name || '')}</span>
    `).join('');
};

// =====================================================
// DATES FUNCTIONALITY
// =====================================================
window.toggleDatesPopup = function() {
    const popup = document.getElementById('datesPopup');
    const wasHidden = popup.classList.contains('hidden');
    document.querySelectorAll('.action-popup').forEach(p => p.classList.add('hidden'));
    if (wasHidden) {
        popup.classList.remove('hidden');
    }
};

window.saveDates = function() {
    if (!window.currentCardId) return;
    
    const btn = document.getElementById('saveDatesBtn');
    const startDate = document.getElementById('cardStartDate').value;
    const dueDate = document.getElementById('cardDueDate').value;
    const dueTime = document.getElementById('cardDueTime').value;
    
    // Show loading state
    if (window.btnLoading) btnLoading(btn, 'Saving...');
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_PATH || '') + '/actions/card/dates.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ card_id: window.currentCardId, start_date: startDate, due_date: dueDate, due_time: dueTime, _token: csrfToken })
    })
    .then(r => {
        if (!r.ok) {
            return r.text().then(text => { throw new Error(text || 'Request failed'); });
        }
        return r.json();
    })
    .then(data => {
        if (window.btnReset) btnReset(btn);
        if (data.success) {
            updateCardDatesDisplay(data.dates);
            toggleDatesPopup();
            showToast('Dates updated', 'success');
        } else {
            showToast(data.message || 'Failed', 'error');
        }
    })
    .catch(err => {
        if (window.btnReset) btnReset(btn);
        console.error('Save dates error:', err);
        showToast('Failed to save dates', 'error');
    });
};

window.removeDates = function() {
    if (!window.currentCardId) return;
    
    const btn = document.getElementById('removeDatesBtn');
    if (window.btnLoading) btnLoading(btn, 'Removing...');
    
    const csrfToken2 = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_PATH || '') + '/actions/card/dates.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken2
        },
        body: JSON.stringify({ card_id: window.currentCardId, start_date: null, due_date: null, due_time: null, _token: csrfToken2 })
    })
    .then(r => r.text().then(text => {
        try { return JSON.parse(text); } 
        catch(e) { throw new Error('Invalid JSON: ' + text.substring(0, 200)); }
    }))
    .then(data => {
        if (window.btnReset) btnReset(btn);
        if (data.success) {
            document.getElementById('cardStartDate').value = '';
            document.getElementById('cardDueDate').value = '';
            document.getElementById('cardDueTime').value = '';
            updateCardDatesDisplay(null);
            toggleDatesPopup();
            showToast('Dates removed', 'success');
        }
    })
    .catch(err => {
        if (window.btnReset) btnReset(btn);
        console.error('Remove dates error:', err);
        showToast('Failed to remove dates', 'error');
    });
};

window.updateCardDatesDisplay = function(dates) {
    const section = document.getElementById('cardDatesSection');
    const display = document.getElementById('cardDatesDisplay');
    if (!dates || (!dates.start_date && !dates.due_date)) {
        section.classList.add('hidden');
        return;
    }
    section.classList.remove('hidden');
    let html = '';
    if (dates.start_date) {
        html += `<span class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded">Start: ${formatDate(dates.start_date)}</span>`;
    }
    if (dates.due_date) {
        const statusClass = dates.status === 'overdue' ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 
                           dates.status === 'due_soon' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400' : 
                           'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400';
        html += `<span class="px-2 py-1 text-xs ${statusClass} rounded">Due: ${formatDate(dates.due_date)}${dates.due_time ? ' ' + dates.due_time : ''}</span>`;
    }
    display.innerHTML = html;
};

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// =====================================================
// CHECKLIST FUNCTIONALITY
// =====================================================
window.cardChecklistFeatureEnabled = false;

function setChecklistFeatureEnabled(enabled, { skipModal = false } = {}) {
    window.cardChecklistFeatureEnabled = !!enabled;
    const toggle = document.getElementById('checklistFeatureToggle');
    if (toggle) {
        toggle.setAttribute('aria-checked', window.cardChecklistFeatureEnabled ? 'true' : 'false');
    }
    const section = document.getElementById('checklistsSection');
    if (section) {
        section.classList.toggle('hidden', !window.cardChecklistFeatureEnabled);
    }
    if (window.cardChecklistFeatureEnabled && !skipModal && window.cardChecklists.length === 0) {
        showChecklistCreateModal();
    }
}

function syncChecklistFeatureFromData() {
    const hasChecklist = window.cardChecklists.length > 0;
    setChecklistFeatureEnabled(hasChecklist, { skipModal: true });
}

window.toggleChecklistFeature = function() {
    const next = !window.cardChecklistFeatureEnabled;
    if (!next) {
        setChecklistFeatureEnabled(false, { skipModal: true });
        hideChecklistCreateModal();
        return;
    }
    if (window.cardChecklists.length > 0) {
        setChecklistFeatureEnabled(true, { skipModal: true });
        return;
    }
    setChecklistFeatureEnabled(true, { skipModal: false });
};

window.showChecklistCreateModal = function() {
    const modal = document.getElementById('checklistCreateModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    const input = document.getElementById('newChecklistTitle');
    if (input) {
        input.value = input.value.trim() || 'Checklist';
        setTimeout(() => input.focus(), 50);
    }
};

window.hideChecklistCreateModal = function(cancelled) {
    const modal = document.getElementById('checklistCreateModal');
    if (modal) modal.classList.add('hidden');
    if (cancelled && window.cardChecklists.length === 0) {
        setChecklistFeatureEnabled(false, { skipModal: true });
    }
};

// Local cache of checklists for the open card, so the UI can update
// in place instead of re-rendering (and dropping focus) on every action.
window.cardChecklists = [];

function checklistPost(url, payload) {
    return fetch((window.BASE_PATH || '') + url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json().catch(() => ({ success: false, message: 'Server returned an invalid response' })));
}

function findChecklist(checklistId) {
    return window.cardChecklists.find(cl => cl.id === Number(checklistId));
}

window.expandChecklistAdd = function(checklistId) {
    setChecklistDropdownOpen(checklistId, true);
    rememberChecklistUiState(checklistId, { addFormOpen: true });
    const block = document.getElementById(`checklist-${checklistId}`);
    if (!block) return;
    const trigger = block.querySelector('.checklist-add-trigger-row');
    const form = block.querySelector('.checklist-add-form');
    if (trigger) trigger.classList.add('hidden');
    if (form) {
        form.classList.remove('is-hidden');
        const input = form.querySelector('.checklist-add-input');
        if (input) input.focus();
    }
};

window.collapseChecklistAdd = function(checklistId) {
    rememberChecklistUiState(checklistId, { addFormOpen: false });
    const block = document.getElementById(`checklist-${checklistId}`);
    if (!block) return;
    const trigger = block.querySelector('.checklist-add-trigger-row');
    const form = block.querySelector('.checklist-add-form');
    if (form) {
        form.classList.add('is-hidden');
        const input = form.querySelector('.checklist-add-input');
        if (input) input.value = '';
    }
    if (trigger) trigger.classList.remove('hidden');
};

window.checklistUiState = window.checklistUiState || {};

function rememberChecklistUiState(checklistId, patch) {
    const id = String(checklistId);
    window.checklistUiState[id] = Object.assign({}, window.checklistUiState[id] || {}, patch);
}

function captureChecklistDropdownState() {
    const state = {};
    document.querySelectorAll('.checklist-dropdown[data-checklist-id]').forEach(function (block) {
        const id = block.dataset.checklistId;
        if (!id) return;
        const form = block.querySelector('.checklist-add-form');
        state[id] = {
            open: block.dataset.open === 'true',
            addFormOpen: !!(form && !form.classList.contains('is-hidden')),
        };
    });
    (window.cardChecklists || []).forEach(function (cl) {
        const id = String(cl.id);
        const remembered = window.checklistUiState[id];
        if (remembered && state[id] === undefined) {
            state[id] = { open: !!remembered.open, addFormOpen: !!remembered.addFormOpen };
        } else if (remembered && state[id]) {
            state[id].open = state[id].open || !!remembered.open;
        }
    });
    return state;
}

function restoreChecklistDropdownState(state) {
    if (!state) return;
    Object.keys(state).forEach(function (id) {
        const saved = state[id];
        if (!saved) return;
        setChecklistDropdownOpen(Number(id), !!saved.open);
        if (!saved.open) return;
        if (saved.addFormOpen) {
            expandChecklistAdd(Number(id));
        } else {
            collapseChecklistAdd(Number(id));
        }
    });
}

window.setChecklistDropdownOpen = function(checklistId, open) {
    const block = document.getElementById(`checklist-${checklistId}`);
    const shouldOpen = !!open;
    rememberChecklistUiState(checklistId, { open: shouldOpen });
    if (!block) return;
    const panel = block.querySelector('.checklist-dropdown-panel');
    const trigger = block.querySelector('.checklist-dropdown-trigger');
    block.classList.toggle('is-open', shouldOpen);
    block.dataset.open = shouldOpen ? 'true' : 'false';
    if (panel) panel.classList.toggle('is-collapsed', !shouldOpen);
    if (trigger) trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
};

window.toggleChecklistDropdown = function(checklistId) {
    const block = document.getElementById(`checklist-${checklistId}`);
    if (!block) return;
    const isOpen = block.dataset.open === 'true';
    setChecklistDropdownOpen(checklistId, !isOpen);
};

window.createChecklist = function() {
    if (!window.currentCardId) return;
    if (window.cardChecklists.length > 0) {
        showToast('This task already has a checklist.', 'error');
        hideChecklistCreateModal();
        return;
    }
    const titleInput = document.getElementById('newChecklistTitle');
    const title = titleInput.value.trim() || 'Checklist';

    checklistPost('/actions/checklist/create.php', { card_id: window.currentCardId, title })
    .then(data => {
        if (data.success && data.checklist) {
            window.cardChecklists.push(data.checklist);
            setChecklistFeatureEnabled(true, { skipModal: true });
            renderChecklists();
            hideChecklistCreateModal();
            if (titleInput) titleInput.value = 'Checklist';
            showToast('Checklist created', 'success');
            setChecklistDropdownOpen(data.checklist.id, true);
            expandChecklistAdd(data.checklist.id);
        } else {
            showToast(data.message || 'Could not create checklist', 'error');
        }
    })
    .catch(() => showToast('Could not create checklist', 'error'));
};

window.loadChecklists = function() {
    if (!window.currentCardId) return Promise.resolve();

    if (window._checklistUiStateCardId !== window.currentCardId) {
        window.checklistUiState = {};
        window._checklistUiStateCardId = window.currentCardId;
    }

    window.cardChecklists = [];
    if (typeof setChecklistFeatureEnabled === 'function') {
        setChecklistFeatureEnabled(false, { skipModal: true });
    }
    hideChecklistCreateModal();

    return fetch(`${window.BASE_PATH || ''}/actions/checklist/get.php?card_id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            window.cardChecklists = (data.success && Array.isArray(data.checklists)) ? data.checklists : [];
            renderChecklists();
            syncChecklistFeatureFromData();
        })
        .catch(err => {
            console.error('Checklist load failed:', err);
            window.cardChecklists = [];
            renderChecklists();
            syncChecklistFeatureFromData();
        });
};

function renderChecklistItem(cl, item) {
    return `
        <div class="checklist-item flex items-center gap-2 group py-1.5 rounded-md px-2 hover:bg-gray-100/80 dark:hover:bg-gray-900/50 transition-colors"
             data-item-id="${item.id}"
             role="button"
             tabindex="0"
             title="${item.is_completed ? 'Mark incomplete' : 'Mark complete'}"
             onclick="handleChecklistRowClick(event, ${item.id}, ${cl.id})"
             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();handleChecklistRowClick(event,${item.id},${cl.id});}"
             onmousedown="event.stopPropagation()">
            <span class="checklist-item-check shrink-0" aria-hidden="true">
                <input type="checkbox" ${item.is_completed ? 'checked' : ''}
                       class="checklist-item-checkbox"
                       tabindex="-1"
                       aria-hidden="true">
                <span class="checklist-item-check-box">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
            </span>
            <span class="checklist-item-title flex-1 min-w-0 text-[13px] leading-snug ${item.is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-100'}">${escapeHtml(item.title)}</span>
            <button type="button" onclick="event.stopPropagation(); deleteChecklistItem(${item.id}, ${cl.id})"
                    class="checklist-item-delete opacity-0 group-hover:opacity-100 focus:opacity-100 text-gray-400 hover:text-red-500 p-0.5 rounded transition-opacity shrink-0 cursor-pointer"
                    title="Remove item" aria-label="Remove item">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>`;
}

window.handleChecklistRowClick = function(event, itemId, checklistId) {
    if (event.target.closest('.checklist-item-delete')) return;
    const row = event.currentTarget;
    const checkbox = row.querySelector('.checklist-item-checkbox');
    if (!checkbox || checkbox.disabled) return;
    checkbox.checked = !checkbox.checked;
    toggleChecklistItem(itemId, checklistId, checkbox);
};

function renderChecklistBlock(cl) {
    const total = cl.items.length;
    const completed = cl.items.filter(i => i.is_completed).length;
    const progress = total > 0 ? Math.round((completed / total) * 100) : 0;
    return `
        <div class="checklist-card checklist-block checklist-dropdown" id="checklist-${cl.id}" data-checklist-id="${cl.id}" data-open="false">
            <button type="button" class="checklist-dropdown-trigger" onclick="toggleChecklistDropdown(${cl.id})" aria-expanded="false" aria-controls="checklist-panel-${cl.id}">
                <svg class="checklist-dropdown-chevron w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span class="flex-1 min-w-0 text-sm font-semibold text-gray-900 dark:text-white truncate">${escapeHtml(cl.title)}</span>
                <span class="checklist-progress-text text-xs font-semibold tabular-nums text-gray-500 dark:text-gray-400 shrink-0">${completed}/${total}</span>
            </button>
            <div id="checklist-panel-${cl.id}" class="checklist-dropdown-panel is-collapsed" onclick="event.stopPropagation()">
                <div class="checklist-progress-track bg-gray-200 dark:bg-gray-700 rounded-full mt-3 mb-3 overflow-hidden">
                    <div class="checklist-progress-bar h-full rounded-full transition-all duration-300 ${progress === 100 ? 'bg-green-500' : 'bg-primary'}" style="width: ${progress}%"></div>
                </div>
                <div class="checklist-items space-y-0.5 mb-1" id="checklist-items-${cl.id}">
                    ${cl.items.map(item => renderChecklistItem(cl, item)).join('')}
                </div>
                <div class="checklist-add-wrap" data-checklist-id="${cl.id}">
                    <button type="button" class="checklist-add-trigger-row" onclick="expandChecklistAdd(${cl.id})">
                        <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add an item
                    </button>
                    <form class="checklist-add-form is-hidden" onsubmit="event.preventDefault(); addChecklistItem(${cl.id}, this.querySelector('.checklist-add-input'));">
                        <button type="submit" class="checklist-add-icon-btn checklist-add-btn" title="Add item" aria-label="Add item">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <input type="text" placeholder="Add an item…" autocomplete="off" data-no-ai-assist
                               class="checklist-add-input text-gray-800 dark:text-gray-100 placeholder-gray-400">
                        <button type="button" class="checklist-add-cancel-btn" onclick="collapseChecklistAdd(${cl.id})">Cancel</button>
                    </form>
                </div>
                <div class="mt-3 pt-2 border-t border-gray-200/80 dark:border-gray-600/80 flex justify-end">
                    <button type="button" onclick="deleteChecklist(${cl.id})"
                            class="inline-flex items-center gap-1.5 px-2 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors"
                            title="Remove checklist">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </div>
            </div>
        </div>`;
}

function renderChecklists() {
    const container = document.getElementById('checklistsContainer');
    if (!container) return;
    if (!window.cardChecklists.length) {
        container.innerHTML = '';
        return;
    }
    const savedDropdownState = captureChecklistDropdownState();
    container.innerHTML = window.cardChecklists.map(renderChecklistBlock).join('');
    restoreChecklistDropdownState(savedDropdownState);
}

function refreshChecklistProgress(cl) {
    const block = document.getElementById(`checklist-${cl.id}`);
    if (!block) return;
    const total = cl.items.length;
    const completed = cl.items.filter(i => i.is_completed).length;
    const progress = total > 0 ? Math.round((completed / total) * 100) : 0;
    const text = block.querySelector('.checklist-progress-text');
    const bar = block.querySelector('.checklist-progress-bar');
    if (text) text.textContent = `${completed}/${total}`;
    if (bar) {
        bar.style.width = `${progress}%`;
        bar.classList.toggle('bg-green-500', progress === 100);
        bar.classList.toggle('bg-primary', progress !== 100);
    }
}

window.addChecklistItem = function(checklistId, input) {
    if (!input) return;
    markChecklistLocalEdit();
    const title = input.value.trim();
    if (!title) {
        input.focus();
        return;
    }
    const cl = findChecklist(checklistId);
    const form = input.closest('form');
    const submitBtn = form ? form.querySelector('.checklist-add-btn') : null;
    input.disabled = true;
    if (submitBtn) submitBtn.disabled = true;

    checklistPost('/actions/checklist/item.php', { action: 'create', checklist_id: Number(checklistId), title })
    .then(data => {
        if (data.success && data.item) {
            data.item.is_completed = !!data.item.is_completed;
            if (cl) {
                cl.items.push(data.item);
                const list = document.getElementById(`checklist-items-${cl.id}`);
                if (list) list.insertAdjacentHTML('beforeend', renderChecklistItem(cl, data.item));
                refreshChecklistProgress(cl);
            } else {
                loadChecklists();
            }
            input.value = '';
            if (submitBtn) submitBtn.disabled = false;
            input.disabled = false;
            input.focus();
        } else {
            showToast(data.message || 'Could not add item', 'error');
        }
    })
    .catch(() => showToast('Could not add item', 'error'))
    .finally(() => {
        input.disabled = false;
        if (submitBtn) submitBtn.disabled = false;
    });
};

window.markChecklistLocalEdit = function() {
    window._planifyChecklistLocalEditUntil = Date.now() + 2000;
};

window.toggleChecklistItem = function(itemId, checklistId, checkbox) {
    markChecklistLocalEdit();
    const cl = findChecklist(checklistId);
    const item = cl ? cl.items.find(i => i.id === Number(itemId)) : null;
    const row = checkbox ? checkbox.closest('.checklist-item') : null;
    const titleEl = row ? row.querySelector('.checklist-item-title') : null;

    const applyState = (completed) => {
        if (item) item.is_completed = completed;
        if (checkbox) checkbox.checked = completed;
        if (titleEl) {
            titleEl.classList.toggle('line-through', completed);
            titleEl.classList.toggle('text-gray-400', completed);
            titleEl.classList.toggle('dark:text-gray-500', completed);
            titleEl.classList.toggle('text-gray-800', !completed);
            titleEl.classList.toggle('dark:text-gray-100', !completed);
        }
        if (cl) refreshChecklistProgress(cl);
    };

    const previous = item ? !!item.is_completed : !(checkbox && checkbox.checked);
    applyState(!previous);
    if (checkbox) checkbox.disabled = true;

    checklistPost('/actions/checklist/item.php', { action: 'toggle', item_id: Number(itemId) })
    .then(data => {
        if (data.success) {
            applyState(!!data.is_completed);
        } else {
            applyState(previous);
            showToast(data.message || 'Could not update item', 'error');
        }
    })
    .catch(() => {
        applyState(previous);
        showToast('Could not update item', 'error');
    })
    .finally(() => { if (checkbox) checkbox.disabled = false; });
};

window.deleteChecklistItem = function(itemId, checklistId) {
    const cl = findChecklist(checklistId);
    const row = document.querySelector(`#checklist-${checklistId} .checklist-item[data-item-id="${itemId}"]`);
    if (row) row.style.opacity = '0.4';

    checklistPost('/actions/checklist/item.php', { action: 'delete', item_id: Number(itemId) })
    .then(data => {
        if (data.success) {
            if (cl) cl.items = cl.items.filter(i => i.id !== Number(itemId));
            if (row) row.remove();
            if (cl) refreshChecklistProgress(cl);
        } else {
            if (row) row.style.opacity = '';
            showToast(data.message || 'Could not remove item', 'error');
        }
    })
    .catch(() => {
        if (row) row.style.opacity = '';
        showToast('Could not remove item', 'error');
    });
};

window.deleteChecklist = function(checklistId) {
    const cl = findChecklist(checklistId);
    const itemCount = cl ? cl.items.length : 0;
    planifyConfirm({
        title: 'Delete checklist?',
        message: cl
            ? `"${cl.title}"${itemCount ? ` and its ${itemCount} item${itemCount === 1 ? '' : 's'}` : ''} will be removed from this task.`
            : 'This checklist will be removed from this task.',
        confirmLabel: 'Delete',
        danger: true
    }).then(ok => {
        if (!ok) return;
        checklistPost('/actions/checklist/delete.php', { checklist_id: Number(checklistId) })
        .then(data => {
            if (data.success) {
                window.cardChecklists = window.cardChecklists.filter(c => c.id !== Number(checklistId));
                renderChecklists();
                setChecklistFeatureEnabled(false, { skipModal: true });
                showToast('Checklist deleted', 'success');
            } else {
                showToast(data.message || 'Could not delete checklist', 'error');
            }
        })
        .catch(() => showToast('Could not delete checklist', 'error'));
    });
};

// =====================================================
// IN-APP CONFIRM DIALOG (replaces browser confirm())
// =====================================================
window.planifyConfirm = function({ title = 'Are you sure?', message = '', confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false } = {}) {
    return new Promise(resolve => {
        let overlay = document.getElementById('planifyConfirmModal');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'planifyConfirmModal';
            overlay.className = 'fixed inset-0 bg-black/50 dark:bg-black/70 z-[270] flex items-center justify-center p-4 hidden';
            overlay.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md shadow-2xl">
                    <div class="p-6">
                        <div id="planifyConfirmIcon" class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full"></div>
                        <h3 id="planifyConfirmTitle" class="text-lg font-semibold text-center text-gray-900 dark:text-white mb-2"></h3>
                        <p id="planifyConfirmMessage" class="text-sm text-center text-gray-500 dark:text-gray-400 mb-6"></p>
                        <div class="flex gap-3">
                            <button type="button" id="planifyConfirmCancel" class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"></button>
                            <button type="button" id="planifyConfirmOk" class="flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors"></button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
        }

        const iconWrap = overlay.querySelector('#planifyConfirmIcon');
        const okBtn = overlay.querySelector('#planifyConfirmOk');
        const cancelBtn = overlay.querySelector('#planifyConfirmCancel');

        overlay.querySelector('#planifyConfirmTitle').textContent = title;
        overlay.querySelector('#planifyConfirmMessage').textContent = message;
        okBtn.textContent = confirmLabel;
        cancelBtn.textContent = cancelLabel;
        iconWrap.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full ' +
            (danger ? 'bg-red-100 dark:bg-red-900/30' : 'bg-neutral-100 dark:bg-neutral-700');
        iconWrap.innerHTML = danger
            ? '<svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
            : '<svg class="w-6 h-6 text-neutral-800 dark:text-neutral-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        okBtn.className = 'flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors ' +
            (danger ? 'bg-red-600 hover:bg-red-700' : 'bg-neutral-900 hover:bg-neutral-800');

        const close = (result) => {
            overlay.classList.add('hidden');
            okBtn.onclick = null;
            cancelBtn.onclick = null;
            overlay.onclick = null;
            document.removeEventListener('keydown', onKey, true);
            resolve(result);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') { e.stopPropagation(); close(false); }
            if (e.key === 'Enter') { e.stopPropagation(); close(true); }
        };

        okBtn.onclick = () => close(true);
        cancelBtn.onclick = () => close(false);
        overlay.onclick = (e) => { if (e.target === overlay) close(false); };
        document.addEventListener('keydown', onKey, true);

        overlay.classList.remove('hidden');
        okBtn.focus();
    });
};

// =====================================================
// MEMBERS FUNCTIONALITY
// =====================================================
window.toggleMembersPopup = function() {
    const popup = document.getElementById('membersPopup');
    const wasHidden = popup.classList.contains('hidden');
    document.querySelectorAll('.action-popup').forEach(p => p.classList.add('hidden'));
    if (wasHidden) {
        popup.classList.remove('hidden');
        loadMembers();
    }
};

window.loadMembers = function() {
    if (!window.currentCardId) return;
    const container = document.getElementById('membersList');
    container.innerHTML = '<div class="text-center py-4"><div class="animate-spin w-5 h-5 border-2 border-primary border-t-transparent rounded-full mx-auto"></div></div>';
    
    fetch(`${window.BASE_PATH || ''}/actions/card/assignees.php?card_id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.members.length === 0) {
                    container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No board members</p>';
                } else {
                    container.innerHTML = data.members.map(member => {
                        const initials = getInitials(member.name);
                        const hasAvatar = member.avatar && member.avatar !== 'default-avatar.png';
                        return `
                        <div class="relative flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer" data-member-id="${member.id}" onclick="toggleMember(${member.id})">
                            ${hasAvatar 
                                ? `<img src="${window.BASE_PATH || ''}/assets/uploads/avatars/${member.avatar}" class="w-8 h-8 rounded-full object-cover">`
                                : `<div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-semibold">${initials}</div>`
                            }
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 dark:text-white truncate">${escapeHtml(member.name)}</div>
                                <div class="text-xs text-gray-500">${escapeHtml(member.role || '')}</div>
                            </div>
                            <svg class="member-check w-5 h-5 text-primary ${member.assigned ? '' : 'hidden'}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>`;
                    }).join('');
                }
                updateCardAssigneesDisplay(data.assignees);
            }
        });
};

window.toggleMember = function(userId) {
    if (!window.currentCardId || window.memberAssigning) return;
    const row = document.querySelector('#membersList [data-member-id="' + userId + '"]');
    const check = row ? row.querySelector('.member-check') : null;
    const wasAssigned = check ? !check.classList.contains('hidden') : false;
    window.memberAssigning = true;
    setMemberAssigning(true, row);
    if (check) check.classList.toggle('hidden', wasAssigned);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_PATH || '') + '/actions/card/assignees.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ card_id: window.currentCardId, user_id: userId, action: 'toggle', _token: csrfToken })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            if (check) check.classList.toggle('hidden', !wasAssigned);
            showToast(data.message || 'Could not update the assignment', 'error');
            return;
        }
        const assignedIds = new Set((data.assignees || []).map(person => Number(person.id)));
        document.querySelectorAll('#membersList [data-member-id]').forEach(item => {
            const mark = item.querySelector('.member-check');
            if (mark) mark.classList.toggle('hidden', !assignedIds.has(Number(item.dataset.memberId)));
        });
        updateCardAssigneesDisplay(data.assignees || []);
        if (typeof window.updateCardAssignees === 'function') {
            window.updateCardAssignees(window.currentCardId, data.assignees || []);
        }
    })
    .catch(() => {
        if (check) check.classList.toggle('hidden', !wasAssigned);
        showToast('Could not update the assignment', 'error');
    })
    .finally(() => {
        window.memberAssigning = false;
        setMemberAssigning(false, row);
    });
};

function setMemberAssigning(active, row) {
    const bar = document.getElementById('memberAssignBar');
    if (bar) bar.classList.toggle('hidden', !active);
    if (!row) return;
    row.classList.toggle('pointer-events-none', active);
    row.classList.toggle('opacity-70', active);
}

window.updateCardAssigneesDisplay = function(assignees) {
    const section = document.getElementById('cardAssigneesSection');
    const display = document.getElementById('cardAssigneesDisplay');
    if (!assignees || assignees.length === 0) {
        section.classList.add('hidden');
        return;
    }
    section.classList.remove('hidden');
    display.innerHTML = assignees.map(a => {
        const initials = getInitials(a.name);
        const hasAvatar = a.avatar && a.avatar !== 'default-avatar.png';
        return hasAvatar 
            ? `<img src="${window.BASE_PATH || ''}/assets/uploads/avatars/${a.avatar}" class="w-8 h-8 rounded-full object-cover" title="${escapeHtml(a.name)}">`
            : `<div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-semibold" title="${escapeHtml(a.name)}">${initials}</div>`;
    }).join('');
};

function getInitials(name) {
    const parts = (name || 'U').trim().split(/\s+/);
    return parts.length >= 2 
        ? (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
        : parts[0].substring(0, 2).toUpperCase();
}

// =====================================================
// ATTACHMENTS FUNCTIONALITY
// =====================================================
window.toggleAttachmentPopup = function() {
    const popup = document.getElementById('attachmentPopup');
    const wasHidden = popup.classList.contains('hidden');
    document.querySelectorAll('.action-popup').forEach(p => p.classList.add('hidden'));
    if (wasHidden) popup.classList.remove('hidden');
};

window.uploadAttachment = function() {
    const input = document.getElementById('attachmentFileInput');
    const btn = document.getElementById('uploadFileBtn');
    
    if (!input.files[0]) {
        showToast('Please select a file first', 'error');
        return;
    }
    if (!window.currentCardId) {
        showToast('No card selected', 'error');
        return;
    }
    
    // Show uploading state (button only, no toast)
    input.disabled = true;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Uploading...';
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('card_id', window.currentCardId);
    formData.append('file', input.files[0]);
    formData.append('_token', csrfToken);
    
    fetch((window.BASE_PATH || '') + '/actions/attachment/upload.php', { 
        method: 'POST', 
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData 
    })
        .then(r => r.json())
        .then(data => {
            input.disabled = false;
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Upload File';
            }
            if (data.success) {
                loadAttachments();
                toggleAttachmentPopup();
                input.value = '';
                showToast('File uploaded successfully', 'success');
            } else {
                showToast(data.message || 'Upload failed', 'error');
            }
        })
        .catch((err) => {
            input.disabled = false;
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Upload File';
            }
            console.error('Upload error:', err);
            showToast('Upload failed', 'error');
        });
};

window.addLinkAttachment = function() {
    const url = document.getElementById('attachmentLinkUrl').value.trim();
    const name = document.getElementById('attachmentLinkName').value.trim();
    const btn = document.getElementById('addLinkBtn');
    if (!url || !window.currentCardId) return;
    
    // Show loading state
    if (window.btnLoading) btnLoading(btn, 'Adding...');
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_PATH || '') + '/actions/attachment/link.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ card_id: window.currentCardId, url, name, _token: csrfToken })
    })
    .then(r => r.json())
    .then(data => {
        if (window.btnReset) btnReset(btn);
        if (data.success) {
            loadAttachments();
            toggleAttachmentPopup();
            document.getElementById('attachmentLinkUrl').value = '';
            document.getElementById('attachmentLinkName').value = '';
            showToast('Link added', 'success');
        } else {
            showToast(data.message || 'Failed', 'error');
        }
    })
    .catch(err => {
        if (window.btnReset) btnReset(btn);
        console.error('Add link error:', err);
        showToast('Failed to add link', 'error');
    });
};

window.loadAttachments = function() {
    if (!window.currentCardId) return Promise.resolve();
    const section = document.getElementById('attachmentsSection');
    const container = document.getElementById('attachmentsList');
    
    return fetch(`${window.BASE_PATH || ''}/actions/attachment/get.php?card_id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.attachments.length > 0) {
                section.classList.remove('hidden');
                container.innerHTML = data.attachments.map(att => {
                    const isLink = att.mime_type === 'link';
                    const isImage = att.is_image;
                    return `
                    <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg group">
                        ${isImage 
                            ? `<img src="${att.file_path}" class="w-12 h-12 rounded object-cover cursor-pointer" onclick="openLightbox('${att.file_path}')">`
                            : isLink 
                                ? `<div class="w-12 h-12 rounded bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg></div>`
                                : `<div class="w-12 h-12 rounded bg-gray-200 dark:bg-gray-600 flex items-center justify-center"><svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>`
                        }
                        <div class="flex-1 min-w-0">
                            <a href="${att.file_path}" target="_blank" class="text-sm font-medium text-gray-800 dark:text-white hover:text-primary truncate block">${escapeHtml(att.original_name)}</a>
                            <p class="text-xs text-gray-500">${isLink ? 'Link' : att.file_size_formatted}</p>
                        </div>
                        <button onclick="deleteAttachment(${att.id})" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>`;
                }).join('');
            } else {
                section.classList.add('hidden');
                container.innerHTML = '';
            }
        });
};

window.deleteAttachment = async function(attachmentId) {
    const ok = await planifyConfirm({ title: 'Delete attachment?', message: 'This file or link will be removed from the task.', confirmLabel: 'Delete', danger: true });
    if (!ok) return;
    fetch((window.BASE_PATH || '') + '/actions/attachment/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attachment_id: attachmentId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadAttachments();
            showToast('Attachment deleted', 'success');
        }
    });
};

// =====================================================
// LOAD ALL CARD DATA
// =====================================================
// Override loadCardDetails to also load new features
const originalLoadCardDetails = window.loadCardDetails;
window.loadCardDetails = function(cardId, options) {
    originalLoadCardDetails(cardId, options);
    
    // Load additional data in parallel (no delay needed)
    Promise.all([
        loadChecklists(),
        loadAttachments(),
        loadCardLabelsForDisplay(),
        loadCardDatesForDisplay(),
        loadCardAssigneesForDisplay()
    ]).catch(err => console.error('Error loading card data:', err));
};

window.loadCardLabelsForDisplay = function() {
    if (!window.currentCardId || !window.currentBoardId) return Promise.resolve();
    return fetch(`${window.BASE_PATH || ''}/actions/label/get.php?board_id=${window.currentBoardId}&card_id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCardLabelsDisplay(data.labels.filter(l => l.selected));
            }
        });
};

window.loadCardDatesForDisplay = function() {
    if (!window.currentCardId) return Promise.resolve();
    // Dates are loaded from card data, populate inputs
    return fetch(`${window.BASE_PATH || ''}/actions/card/get.php?id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.card) {
                document.getElementById('cardStartDate').value = data.card.start_date || '';
                document.getElementById('cardDueDate').value = data.card.due_date || '';
                document.getElementById('cardDueTime').value = data.card.due_time || '';
                
                if (data.card.due_date) {
                    const now = new Date();
                    const due = new Date(data.card.due_date);
                    let status = 'on_track';
                    if (data.card.is_completed) status = 'completed';
                    else if (due < now) status = 'overdue';
                    else if ((due - now) / (1000 * 60 * 60 * 24) <= 1) status = 'due_soon';
                    
                    updateCardDatesDisplay({
                        start_date: data.card.start_date,
                        due_date: data.card.due_date,
                        due_time: data.card.due_time,
                        status: status
                    });
                } else {
                    updateCardDatesDisplay(null);
                }
            }
        });
};

window.loadCardAssigneesForDisplay = function() {
    if (!window.currentCardId) return Promise.resolve();
    return fetch(`${window.BASE_PATH || ''}/actions/card/assignees.php?card_id=${window.currentCardId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCardAssigneesDisplay(data.assignees);
            }
        });
};
</script>
