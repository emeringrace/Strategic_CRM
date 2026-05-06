  </div><!-- /.page-content -->
</div><!-- /.main -->

<script>
// ── Modal Helpers ─────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(b => {
  b.addEventListener('click', e => {
    if (e.target === b) b.classList.remove('open');
  });
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open').forEach(b =>
      b.classList.remove('open')
    );
  }
});
</script>
</body>
</html>
