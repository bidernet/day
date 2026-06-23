    </div><!-- /.content -->
  </div><!-- /.main -->
</div><!-- /.layout -->
<?php $__ver = @trim(@file_get_contents(__DIR__ . '/../VERSION')); if ($__ver === '' || $__ver === false) $__ver = '?'; ?>
<script>
console.log('%c bidernet CRM %c v<?= htmlspecialchars($__ver, ENT_QUOTES) ?> ',
  'background:#14180b;color:#c6f02e;font-weight:bold;padding:3px 6px;border-radius:4px 0 0 4px',
  'background:#c6f02e;color:#14180b;font-weight:bold;padding:3px 6px;border-radius:0 4px 4px 0');
</script>
</body>
</html>
