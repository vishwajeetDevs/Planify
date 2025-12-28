<!-- Common Scripts -->
    <script src="../assets/js/theme.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/theme.js'); ?>"></script>
    <script src="../assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/mention.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/mention.js'); ?>"></script>
    <script src="../assets/js/writing-assistant.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/writing-assistant.js'); ?>"></script>
    
    <?php if (isset($includeDragDrop) && $includeDragDrop): ?>
    <script src="../assets/js/drag.js"></script>
    <?php endif; ?>
    
</body>
</html>