    </main>
</div>

<!-- TASK DETAIL DRAWER -->
<?php include __DIR__ . '/task_drawer.php'; ?>

<!-- MODALS -->
<?php include __DIR__ . '/modals.php'; ?>

<!-- TaskManager JAVASCRIPT ENGINE -->
<script src="/taskmanager/Public/js/app.js?v=<?= time() ?>"></script>
<!-- Dynamic HMVC Module Scripts -->
<?= \Core\AssetManager::renderScripts() ?>
</body>
</html>