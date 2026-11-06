<?php defined('APP') || exit; ?>
    </main>
</div>
</div>
<script src="<?= e(base_url()) ?>/js/lib/jquery.min.js"></script>
<script src="<?= e(base_url()) ?>/js/lib/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url()) ?>/js/lib/dataTables.min.js"></script>
<script src="<?= e(base_url()) ?>/js/lib/dataTables.bootstrap5.min.js"></script>
<script src="<?= e(base_url()) ?>/js/lib/sweetalert2.all.min.js"></script>
<script src="<?= e(base_url()) ?>/js/funcion.js"></script>
<?php foreach ($scripts as $script): ?>
    <script src="<?= e(base_url()) ?>/js/<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
