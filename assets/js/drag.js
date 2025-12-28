// Drag and Drop functionality is now handled directly in board.php
// This file is kept for backwards compatibility but the main logic
// has been moved to inline JavaScript in board.php for better integration
// with the SortableJS library and the board's DOM structure.

// The drag and drop implementation in board.php includes:
// - Card movement between lists
// - Card reordering within lists
// - Visual feedback during drag operations
// - Server-side persistence via AJAX calls to /actions/card/reorder.php
// - Toast notifications for successful moves
// - Error handling with page reload fallback

console.log('Drag and drop functionality loaded from board.php');
