<?php
/**
 * Planify Skeleton Loading System
 * 
 * A comprehensive, accessible skeleton loading component system
 * for improved perceived performance and UX.
 * 
 * Usage:
 *   <?php skeleton('card', 3); ?>
 *   <?php skeleton('workspace', 4, 'my-custom-class'); ?>
 *   <?php skeleton('board-list', 3); ?>
 * 
 * Available types:
 *   - card: Task/card placeholder
 *   - card-mini: Compact card placeholder
 *   - workspace: Workspace card placeholder
 *   - board: Board card placeholder
 *   - list: Kanban list/column placeholder
 *   - board-list: Full board with multiple lists
 *   - comment: Comment item placeholder
 *   - activity: Activity feed item placeholder
 *   - member: Member list item placeholder
 *   - profile: Profile page placeholder
 *   - modal-card: Card detail modal placeholder
 *   - modal-members: Members modal placeholder
 *   - search-result: Search result item placeholder
 * 
 * @param string $type   The skeleton type to render
 * @param int    $count  Number of skeleton items to render
 * @param string $class  Additional CSS classes
 * @param array  $options Additional options (e.g., ['animate' => false])
 */

function skeleton($type = 'card', $count = 1, $class = '', $options = []) {
    $animate = $options['animate'] ?? true;
    $baseClass = $animate ? 'skeleton-shimmer' : '';
    
    // Wrapper with ARIA attributes for accessibility
    $ariaAttrs = 'aria-busy="true" aria-hidden="true" role="status"';
    
    switch ($type) {
        case 'card':
            renderCardSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'card-mini':
            renderCardMiniSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'workspace':
            renderWorkspaceSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'board':
            renderBoardSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'list':
            renderListSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'board-list':
            renderBoardListSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'comment':
            renderCommentSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'activity':
            renderActivitySkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'member':
            renderMemberSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'profile':
            renderProfileSkeleton($class, $baseClass, $ariaAttrs);
            break;
        case 'modal-card':
            renderModalCardSkeleton($class, $baseClass, $ariaAttrs);
            break;
        case 'modal-members':
            renderModalMembersSkeleton($class, $baseClass, $ariaAttrs);
            break;
        case 'search-result':
            renderSearchResultSkeleton($count, $class, $baseClass, $ariaAttrs);
            break;
        case 'avatar':
            renderAvatarSkeleton($count, $class, $baseClass);
            break;
        case 'text-line':
            renderTextLineSkeleton($count, $class, $baseClass);
            break;
        case 'button':
            renderButtonSkeleton($count, $class, $baseClass);
            break;
        default:
            renderCardSkeleton($count, $class, $baseClass, $ariaAttrs);
    }
}

/**
 * Render a single skeleton line element
 */
function skeletonLine($width = 'w-full', $height = 'h-4', $class = '', $baseClass = 'skeleton-shimmer') {
    echo "<div class=\"skeleton-line {$baseClass} {$height} {$width} rounded bg-gray-200 dark:bg-gray-700 {$class}\"></div>";
}

/**
 * Card skeleton - for task cards
 */
function renderCardSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 100;
        echo <<<HTML
        <div class="skeleton-card p-4 mb-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <div class="space-y-3">
                <!-- Title -->
                <div class="skeleton-line {$baseClass} h-5 w-4/5 rounded bg-gray-200 dark:bg-gray-700"></div>
                <!-- Description -->
                <div class="skeleton-line {$baseClass} h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                <!-- Meta row -->
                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center gap-2">
                        <!-- Avatar -->
                        <div class="skeleton-avatar {$baseClass} h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        <!-- Name -->
                        <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                    <!-- Badges -->
                    <div class="flex items-center gap-2">
                        <div class="skeleton-badge {$baseClass} h-5 w-8 rounded bg-gray-200 dark:bg-gray-700"></div>
                        <div class="skeleton-badge {$baseClass} h-5 w-8 rounded bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
            </div>
            <span class="sr-only">Loading card...</span>
        </div>
HTML;
    }
}

/**
 * Mini card skeleton - compact version
 */
function renderCardMiniSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 80;
        echo <<<HTML
        <div class="skeleton-card-mini p-3 mb-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <div class="skeleton-line {$baseClass} h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
            <div class="skeleton-line {$baseClass} h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
            <span class="sr-only">Loading...</span>
        </div>
HTML;
    }
}

/**
 * Workspace skeleton
 */
function renderWorkspaceSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 100;
        echo <<<HTML
        <div class="skeleton-workspace group block bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 h-40 flex flex-col border border-gray-100 dark:border-gray-700 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <div class="flex-1">
                <!-- Workspace title -->
                <div class="skeleton-line {$baseClass} h-5 w-3/4 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
                <!-- Board count -->
                <div class="flex items-center gap-2">
                    <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="skeleton-line {$baseClass} h-3 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
            <!-- Footer -->
            <div class="mt-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <div class="skeleton-icon {$baseClass} h-3 w-3 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="skeleton-line {$baseClass} h-3 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
            <span class="sr-only">Loading workspace...</span>
        </div>
HTML;
    }
}

/**
 * Board skeleton
 */
function renderBoardSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 100;
        echo <<<HTML
        <div class="skeleton-board group block bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 h-40 flex flex-col border border-gray-100 dark:border-gray-700 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <div class="flex-1">
                <!-- Board title -->
                <div class="skeleton-line {$baseClass} h-5 w-2/3 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
                <!-- List count -->
                <div class="flex items-center gap-2">
                    <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="skeleton-line {$baseClass} h-3 w-12 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
            <!-- Footer with avatar -->
            <div class="mt-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="skeleton-icon {$baseClass} h-3 w-3 rounded bg-gray-200 dark:bg-gray-700"></div>
                        <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                    <div class="skeleton-avatar {$baseClass} h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
            <span class="sr-only">Loading board...</span>
        </div>
HTML;
    }
}

/**
 * List/Column skeleton
 */
function renderListSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 150;
        $cardCount = rand(2, 4);
        echo <<<HTML
        <div class="skeleton-list flex-shrink-0 w-72 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-3 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <!-- List header -->
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="skeleton-line {$baseClass} h-5 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-icon {$baseClass} h-6 w-6 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <!-- Cards container -->
            <div class="space-y-2 min-h-[100px]">
HTML;
        // Add card skeletons inside list
        for ($j = 0; $j < $cardCount; $j++) {
            $cardDelay = ($i * 150) + ($j * 80);
            echo <<<HTML
                <div class="skeleton-card-in-list p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700" style="animation-delay: {$cardDelay}ms;">
                    <div class="skeleton-line {$baseClass} h-4 w-4/5 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                    <div class="skeleton-line {$baseClass} h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
HTML;
        }
        echo <<<HTML
            </div>
            <!-- Add card button placeholder -->
            <div class="mt-3 pt-2">
                <div class="skeleton-button {$baseClass} h-9 w-full rounded-lg bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <span class="sr-only">Loading list...</span>
        </div>
HTML;
    }
}

/**
 * Full board with multiple lists
 */
function renderBoardListSkeleton($listCount, $class, $baseClass, $ariaAttrs) {
    echo "<div class=\"skeleton-board-container flex gap-4 overflow-x-auto pb-4 {$class}\" {$ariaAttrs}>";
    renderListSkeleton($listCount, '', $baseClass, '');
    echo "<span class=\"sr-only\">Loading board...</span></div>";
}

/**
 * Comment skeleton
 */
function renderCommentSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 100;
        echo <<<HTML
        <div class="skeleton-comment flex gap-3 p-3 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <!-- Avatar -->
            <div class="skeleton-avatar {$baseClass} h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex-shrink-0"></div>
            <!-- Content -->
            <div class="flex-1 space-y-2">
                <!-- Header: name + time -->
                <div class="flex items-center gap-2">
                    <div class="skeleton-line {$baseClass} h-4 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="skeleton-line {$baseClass} h-3 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
                <!-- Comment text -->
                <div class="skeleton-line {$baseClass} h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <span class="sr-only">Loading comment...</span>
        </div>
HTML;
    }
}

/**
 * Activity feed skeleton
 */
function renderActivitySkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 80;
        echo <<<HTML
        <div class="skeleton-activity flex items-start gap-3 py-3 border-b border-gray-100 dark:border-gray-700 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <!-- Icon -->
            <div class="skeleton-icon {$baseClass} h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex-shrink-0"></div>
            <!-- Content -->
            <div class="flex-1 space-y-1">
                <div class="skeleton-line {$baseClass} h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <span class="sr-only">Loading activity...</span>
        </div>
HTML;
    }
}

/**
 * Member list item skeleton
 */
function renderMemberSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 80;
        echo <<<HTML
        <div class="skeleton-member flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <!-- Avatar -->
            <div class="skeleton-avatar {$baseClass} h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            <!-- Info -->
            <div class="flex-1 space-y-1">
                <div class="skeleton-line {$baseClass} h-4 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-40 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <!-- Role badge -->
            <div class="skeleton-badge {$baseClass} h-6 w-16 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            <span class="sr-only">Loading member...</span>
        </div>
HTML;
    }
}

/**
 * Profile page skeleton
 */
function renderProfileSkeleton($class, $baseClass, $ariaAttrs) {
    echo <<<HTML
    <div class="skeleton-profile space-y-6 {$class}" {$ariaAttrs}>
        <!-- Header section -->
        <div class="flex items-center gap-6 p-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
            <!-- Large avatar -->
            <div class="skeleton-avatar {$baseClass} h-24 w-24 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            <!-- User info -->
            <div class="flex-1 space-y-3">
                <div class="skeleton-line {$baseClass} h-7 w-48 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-4 w-64 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex gap-4 mt-2">
                    <div class="skeleton-badge {$baseClass} h-6 w-24 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                    <div class="skeleton-badge {$baseClass} h-6 w-20 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
            <!-- Edit button -->
            <div class="skeleton-button {$baseClass} h-10 w-24 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
        </div>
        
        <!-- Stats section -->
        <div class="grid grid-cols-4 gap-4">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                <div class="skeleton-line {$baseClass} h-8 w-12 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                <div class="skeleton-line {$baseClass} h-8 w-12 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                <div class="skeleton-line {$baseClass} h-8 w-12 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                <div class="skeleton-line {$baseClass} h-8 w-12 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                <div class="skeleton-line {$baseClass} h-3 w-20 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
        </div>
        
        <!-- Activity section -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
            <div class="skeleton-line {$baseClass} h-5 w-32 rounded bg-gray-200 dark:bg-gray-700 mb-4"></div>
            <div class="space-y-3">
HTML;
    renderActivitySkeleton(5, '', $baseClass, '');
    echo <<<HTML
            </div>
        </div>
        <span class="sr-only">Loading profile...</span>
    </div>
HTML;
}

/**
 * Card detail modal skeleton
 */
function renderModalCardSkeleton($class, $baseClass, $ariaAttrs) {
    echo <<<HTML
    <div class="skeleton-modal-card space-y-6 {$class}" {$ariaAttrs}>
        <!-- Header -->
        <div class="space-y-3">
            <div class="skeleton-line {$baseClass} h-7 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="flex items-center gap-2">
                <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-4 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
        </div>
        
        <!-- Description -->
        <div class="space-y-2">
            <div class="skeleton-line {$baseClass} h-4 w-24 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
            <div class="skeleton-line {$baseClass} h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="skeleton-line {$baseClass} h-3 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="skeleton-line {$baseClass} h-3 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
        </div>
        
        <!-- Checklist -->
        <div class="space-y-2">
            <div class="skeleton-line {$baseClass} h-4 w-20 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
            <div class="flex items-center gap-2">
                <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-48 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="flex items-center gap-2">
                <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-40 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="flex items-center gap-2">
                <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-36 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
        </div>
        
        <!-- Comments -->
        <div class="space-y-3">
            <div class="skeleton-line {$baseClass} h-4 w-24 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
HTML;
    renderCommentSkeleton(3, '', $baseClass, '');
    echo <<<HTML
        </div>
        <span class="sr-only">Loading card details...</span>
    </div>
HTML;
}

/**
 * Members modal skeleton
 */
function renderModalMembersSkeleton($class, $baseClass, $ariaAttrs) {
    echo <<<HTML
    <div class="skeleton-modal-members space-y-4 {$class}" {$ariaAttrs}>
        <!-- Owner section -->
        <div class="space-y-2">
            <div class="skeleton-line {$baseClass} h-4 w-24 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
            <div class="flex items-center gap-3 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200/50 dark:border-amber-700/30">
                <div class="skeleton-avatar {$baseClass} h-12 w-12 rounded-full bg-amber-200 dark:bg-amber-700"></div>
                <div class="flex-1 space-y-1">
                    <div class="skeleton-line {$baseClass} h-4 w-32 rounded bg-amber-200 dark:bg-amber-700"></div>
                    <div class="skeleton-line {$baseClass} h-3 w-40 rounded bg-amber-200 dark:bg-amber-700"></div>
                </div>
                <div class="skeleton-badge {$baseClass} h-6 w-16 rounded-full bg-amber-200 dark:bg-amber-700"></div>
            </div>
        </div>
        
        <!-- Members section -->
        <div class="space-y-2">
            <div class="skeleton-line {$baseClass} h-4 w-20 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
HTML;
    renderMemberSkeleton(4, '', $baseClass, '');
    echo <<<HTML
        </div>
        <span class="sr-only">Loading members...</span>
    </div>
HTML;
}

/**
 * Search result skeleton
 */
function renderSearchResultSkeleton($count, $class, $baseClass, $ariaAttrs) {
    for ($i = 0; $i < $count; $i++) {
        $delay = $i * 60;
        echo <<<HTML
        <div class="skeleton-search-result flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 {$class}" {$ariaAttrs} style="animation-delay: {$delay}ms;">
            <!-- Icon -->
            <div class="skeleton-icon {$baseClass} h-8 w-8 rounded bg-gray-200 dark:bg-gray-700"></div>
            <!-- Content -->
            <div class="flex-1 space-y-1">
                <div class="skeleton-line {$baseClass} h-4 w-48 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="skeleton-line {$baseClass} h-3 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <!-- Arrow -->
            <div class="skeleton-icon {$baseClass} h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
            <span class="sr-only">Loading result...</span>
        </div>
HTML;
    }
}

/**
 * Avatar skeleton
 */
function renderAvatarSkeleton($count, $class, $baseClass) {
    for ($i = 0; $i < $count; $i++) {
        echo "<div class=\"skeleton-avatar {$baseClass} h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 {$class}\"></div>";
    }
}

/**
 * Text line skeleton
 */
function renderTextLineSkeleton($count, $class, $baseClass) {
    $widths = ['w-full', 'w-3/4', 'w-1/2', 'w-2/3', 'w-4/5'];
    for ($i = 0; $i < $count; $i++) {
        $width = $widths[$i % count($widths)];
        echo "<div class=\"skeleton-line {$baseClass} h-4 {$width} rounded bg-gray-200 dark:bg-gray-700 mb-2 {$class}\"></div>";
    }
}

/**
 * Button skeleton
 */
function renderButtonSkeleton($count, $class, $baseClass) {
    for ($i = 0; $i < $count; $i++) {
        echo "<div class=\"skeleton-button {$baseClass} h-10 w-24 rounded-lg bg-gray-200 dark:bg-gray-700 {$class}\"></div>";
    }
}

/**
 * Inline skeleton wrapper for JavaScript replacement
 * Returns HTML string instead of echoing
 */
function getSkeletonHTML($type = 'card', $count = 1, $class = '', $options = []) {
    ob_start();
    skeleton($type, $count, $class, $options);
    return ob_get_clean();
}
?>

