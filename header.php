<?php
// header.php - Shared sidebar and top bar
$current_page = basename($_SERVER['PHP_SELF'], '.php');
function navItem($page, $icon, $label, $current) {
    $active = ($current === $page || strpos($current, $page) !== false) ? 'active' : '';
    $href = $page . '.php';
    return "<a href='$href' class='nav-item $active'><span class='nav-icon'>$icon</span>$label</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>DiaCare Pro</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Extra inline tweaks */
        .action-btns { display: flex; gap: 6px; }
        .avatar-male { background: linear-gradient(135deg, #60a5fa, #3b82f6); color: white; }
        .avatar-female { background: linear-gradient(135deg, #f9a8d4, #ec4899); color: white; }
        .avatar-other { background: linear-gradient(135deg, #86efac, #22c55e); color: white; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="logo">
            <div class="logo-icon">🩺</div>
            <div class="logo-text">
                <h2>DiaCare Pro</h2>
                <span>Diabetic Care System</span>
            </div>
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Overview</div>
        <?= navItem('index', '📊', 'Dashboard', $current_page) ?>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Patient Management</div>
        <?= navItem('patients', '👥', 'All Patients', $current_page) ?>
        <?= navItem('patient_form', '➕', 'Add New Patient', $current_page) ?>
        <?= navItem('add_reading', '🩸', 'Record Blood Sugar', $current_page) ?>
        <?= navItem('add_hba1c', '🧪', 'Record HbA1c', $current_page) ?>
        <?= navItem('medications', '💊', 'Medications', $current_page) ?>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Analysis & Reports</div>
        <?= navItem('reports', '📈', 'Analytics & Graphs', $current_page) ?>
        <?= navItem('generate_report', '🖨️', 'Generate Report', $current_page) ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">👨‍⚕️</div>
            <div class="user-info">
                <p>Diabetic Care Unit</p>
                <span>Hospital Admin</span>
            </div>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-content">

<!-- TOP BAR -->
<header class="topbar">
    <button onclick="document.getElementById('sidebar').classList.toggle('open')" 
            style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-medium);display:none" id="menuBtn">☰</button>
    <div class="topbar-title">
        <h1><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></h1>
        <p><?= date('l, d F Y') ?> &nbsp;|&nbsp; <?= HOSPITAL_NAME ?></p>
    </div>
    <div class="topbar-actions">
        <a href="patients.php" class="btn btn-secondary btn-sm">👥 Patients</a>
        <a href="patient_form.php" class="btn btn-primary btn-sm">+ Add Patient</a>
        <a href="add_reading.php" class="btn btn-success btn-sm">🩸 Record Reading</a>
    </div>
</header>

<div class="page-content">
<script>
// Mobile menu
const menuBtn = document.getElementById('menuBtn');
if (window.innerWidth <= 1024) {
    if(menuBtn) menuBtn.style.display = 'block';
}
window.addEventListener('resize', () => {
    if(menuBtn) menuBtn.style.display = window.innerWidth <= 1024 ? 'block' : 'none';
});
</script>
