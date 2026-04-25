<?php // footer.php ?>
</div><!-- end page-content -->
</div><!-- end main-content -->

<script>
// Tab functionality
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const el = document.getElementById(tabId);
    if (el) el.classList.add('active');
    if (btn) btn.classList.add('active');
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.transition = 'opacity 0.5s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 500); }, 4000);
});

// Confirm delete
function confirmDelete(url, name) {
    if (confirm('⚠️ Are you sure you want to delete ' + name + '?\n\nThis action cannot be undone.')) {
        window.location.href = url;
    }
}
</script>
</body>
</html>
