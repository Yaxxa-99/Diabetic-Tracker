<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Analytics & Reports';

// Time range filter
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$days = in_array($days, [7, 14, 30, 60, 90, 180]) ? $days : 30;

// Overall BS trend
$trendData = $db->query("SELECT reading_date, 
    AVG(blood_sugar_value) as avg_bs,
    MAX(blood_sugar_value) as max_bs,
    MIN(blood_sugar_value) as min_bs,
    COUNT(*) as reading_count,
    SUM(CASE WHEN blood_sugar_value > 180 THEN 1 ELSE 0 END) as high_count
    FROM blood_sugar_readings 
    WHERE reading_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    GROUP BY reading_date ORDER BY reading_date ASC");
$tDates = $tAvg = $tMax = $tMin = $tHigh = [];
while ($r = $trendData->fetch_assoc()) {
    $tDates[] = date('d M', strtotime($r['reading_date']));
    $tAvg[] = round($r['avg_bs'], 1);
    $tMax[] = round($r['max_bs'], 1);
    $tMin[] = round($r['min_bs'], 1);
    $tHigh[] = $r['high_count'];
}

// By reading type distribution
$typeDistData = $db->query("SELECT reading_type, 
    AVG(blood_sugar_value) as avg_val,
    COUNT(*) as cnt,
    SUM(CASE WHEN blood_sugar_value < 70 THEN 1 ELSE 0 END) as low_cnt,
    SUM(CASE WHEN blood_sugar_value BETWEEN 70 AND 180 THEN 1 ELSE 0 END) as normal_cnt,
    SUM(CASE WHEN blood_sugar_value > 180 THEN 1 ELSE 0 END) as high_cnt
    FROM blood_sugar_readings 
    WHERE reading_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    GROUP BY reading_type ORDER BY cnt DESC");
$typeRows = [];
while ($r = $typeDistData->fetch_assoc()) $typeRows[] = $r;

// All patients with their avg, last reading, last HbA1c
$patientStats = $db->query("SELECT p.id, p.full_name, p.patient_id, p.diabetes_type, p.status, p.gender,
    AVG(bsr.blood_sugar_value) as avg_bs,
    MAX(bsr.blood_sugar_value) as max_bs,
    MIN(bsr.blood_sugar_value) as min_bs,
    COUNT(bsr.id) as reading_cnt,
    (SELECT blood_sugar_value FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_bs,
    (SELECT hba1c_value FROM hba1c_records WHERE patient_id=p.id ORDER BY test_date DESC LIMIT 1) as last_hba1c
    FROM patients p
    LEFT JOIN blood_sugar_readings bsr ON bsr.patient_id=p.id AND bsr.reading_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    GROUP BY p.id ORDER BY avg_bs DESC");
$allPatStats = [];
while ($r = $patientStats->fetch_assoc()) $allPatStats[] = $r;

// Status distribution
$statusDist = $db->query("SELECT 
    SUM(CASE WHEN blood_sugar_value < 70 THEN 1 ELSE 0 END) as low,
    SUM(CASE WHEN blood_sugar_value BETWEEN 70 AND 99 THEN 1 ELSE 0 END) as normal,
    SUM(CASE WHEN blood_sugar_value BETWEEN 100 AND 125 THEN 1 ELSE 0 END) as prediab,
    SUM(CASE WHEN blood_sugar_value BETWEEN 126 AND 180 THEN 1 ELSE 0 END) as diabetic,
    SUM(CASE WHEN blood_sugar_value > 180 THEN 1 ELSE 0 END) as critical,
    COUNT(*) as total
    FROM blood_sugar_readings WHERE reading_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)")->fetch_assoc();

// HbA1c averages by patient
$hba1cStats = $db->query("SELECT p.full_name, p.patient_id,
    AVG(h.hba1c_value) as avg_hba1c,
    MIN(h.hba1c_value) as min_hba1c,
    MAX(h.hba1c_value) as max_hba1c,
    COUNT(*) as test_count
    FROM hba1c_records h JOIN patients p ON h.patient_id=p.id
    WHERE h.test_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    GROUP BY p.id ORDER BY avg_hba1c DESC");
$hba1cRows = [];
while ($r = $hba1cStats->fetch_assoc()) $hba1cRows[] = $r;

require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1>📈 Analytics & Reports</h1>
        <p>Comprehensive blood sugar analysis and patient insights</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <!-- Time Range Selector -->
        <div style="display:flex;gap:4px;">
            <?php foreach ([7=>'7D', 14=>'14D', 30=>'30D', 60=>'60D', 90=>'3M', 180=>'6M'] as $d => $label): ?>
            <a href="?days=<?= $d ?>" class="btn <?= $days===$d?'btn-primary':'btn-secondary' ?> btn-sm"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <a href="generate_report.php" class="btn btn-outline">🖨️ Print Report</a>
    </div>
</div>

<!-- SUMMARY STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
    <?php
    $total = $statusDist['total'] ?: 1;
    $statsConfig = [
        ['low', '⬇️', 'Hypoglycemia', '#2563eb'],
        ['normal', '✅', 'Normal Range', '#059669'],
        ['prediab', '⚠️', 'Pre-Diabetic', '#d97706'],
        ['diabetic', '🔶', 'Diabetic Range', '#ea580c'],
        ['critical', '🚨', 'Critical High', '#dc2626'],
    ];
    foreach ($statsConfig as $sc):
        $cnt = $statusDist[$sc[0]];
        $pct = round($cnt / $total * 100);
    ?>
    <div class="stat-card" style="border-top:3px solid <?= $sc[3] ?>;">
        <div>
            <div style="font-size:28px;margin-bottom:6px"><?= $sc[1] ?></div>
            <div style="font-size:26px;font-weight:800;color:<?= $sc[3] ?>"><?= $cnt ?></div>
            <div style="font-size:12px;color:var(--text-medium);margin-top:2px"><?= $sc[2] ?></div>
            <div style="font-size:11px;color:var(--text-light)"><?= $pct ?>% of readings</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- CHARTS ROW 1 -->
<div class="grid-2 mb-4">
    <div class="card">
        <div class="card-header">
            <h3>📈 Blood Sugar Trend — Last <?= $days ?> Days</h3>
        </div>
        <div class="card-body">
            <div class="chart-container-lg">
                <canvas id="trendMainChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>🍩 Reading Status Distribution</h3></div>
        <div class="card-body">
            <div class="chart-container-lg">
                <canvas id="statusPieChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="grid-2 mb-4">
    <div class="card">
        <div class="card-header"><h3>📊 Average BS by Reading Type</h3></div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="typeBarChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>⚠️ High Readings Count Per Day</h3></div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="highCountChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- PATIENT COMPARISON TABLE -->
<div class="card mb-4">
    <div class="card-header">
        <div><h3>👥 Patient Blood Sugar Comparison</h3><p>Last <?= $days ?> days statistics per patient</p></div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Patient</th><th>Type</th><th>Readings</th><th>Average BS</th><th>Min BS</th><th>Max BS</th><th>Last BS</th><th>Last HbA1c</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($allPatStats as $ps):
                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $ps['full_name']), 0, 2)));
                $avInterp = $ps['avg_bs'] ? interpretBloodSugar($ps['avg_bs'], 'Fasting') : null;
                $lastInterp = $ps['last_bs'] ? interpretBloodSugar($ps['last_bs'], 'Fasting') : null;
                $hInterp = $ps['last_hba1c'] ? interpretHbA1c($ps['last_hba1c']) : null;
                $avatarClass = $ps['gender'] === 'Male' ? 'avatar-male' : 'avatar-female';
            ?>
            <tr>
                <td>
                    <div class="patient-name-cell">
                        <div class="patient-avatar <?= $avatarClass ?>"><?= $initials ?></div>
                        <div>
                            <div class="name"><a href="patient_detail.php?id=<?= $ps['id'] ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($ps['full_name']) ?></a></div>
                            <div class="pid"><?= $ps['patient_id'] ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-purple"><?= $ps['diabetes_type'] ?></span></td>
                <td style="text-align:center;font-weight:700"><?= $ps['reading_cnt'] ?: 0 ?></td>
                <td>
                    <?php if ($ps['avg_bs'] && $avInterp): ?>
                    <span style="font-weight:800;font-size:15px;color:<?= $avInterp['color'] ?>"><?= round($ps['avg_bs'],1) ?></span>
                    <span style="font-size:11px;color:var(--text-light)"> mg/dL</span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td style="color:#059669;font-weight:600"><?= $ps['min_bs'] ? round($ps['min_bs'],1) : '—' ?></td>
                <td style="color:#dc2626;font-weight:600"><?= $ps['max_bs'] ? round($ps['max_bs'],1) : '—' ?></td>
                <td>
                    <?php if ($ps['last_bs'] && $lastInterp): ?>
                    <span style="font-weight:700;color:<?= $lastInterp['color'] ?>"><?= $ps['last_bs'] ?></span>
                    <span style="font-size:11px;color:var(--text-light)"> mg/dL</span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($ps['last_hba1c'] && $hInterp): ?>
                    <span style="font-weight:700;color:<?= $hInterp['color'] ?>"><?= $ps['last_hba1c'] ?>%</span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><span class="badge status-<?= strtolower($ps['status']) ?>"><?= $ps['status'] ?></span></td>
                <td>
                    <div class="action-btns">
                        <a href="patient_detail.php?id=<?= $ps['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                        <a href="generate_report.php?pid=<?= $ps['id'] ?>" class="btn btn-outline btn-sm">Report</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- HBAIC TABLE -->
<?php if (!empty($hba1cRows)): ?>
<div class="card">
    <div class="card-header"><h3>🧪 HbA1c Summary — Last <?= $days ?> Days</h3></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Patient</th><th>Tests</th><th>Average HbA1c</th><th>Min</th><th>Max</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($hba1cRows as $h):
                $hInterp = interpretHbA1c($h['avg_hba1c']);
            ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($h['full_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-light)"><?= $h['patient_id'] ?></div>
                </td>
                <td><?= $h['test_count'] ?></td>
                <td>
                    <span style="font-weight:800;font-size:18px;color:<?= $hInterp['color'] ?>"><?= round($h['avg_hba1c'],2) ?>%</span>
                </td>
                <td style="color:#059669;font-weight:600"><?= round($h['min_hba1c'],2) ?>%</td>
                <td style="color:#dc2626;font-weight:600"><?= round($h['max_hba1c'],2) ?>%</td>
                <td><span class="badge" style="background:<?= $hInterp['color'] ?>20;color:<?= $hInterp['color'] ?>"><?= $hInterp['label'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// Main Trend Chart
new Chart(document.getElementById('trendMainChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($tDates ?: ['No Data']) ?>,
        datasets: [
            { label: 'Average (mg/dL)', data: <?= json_encode($tAvg ?: [0]) ?>, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.07)', fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#2563eb' },
            { label: 'Maximum', data: <?= json_encode($tMax ?: [0]) ?>, borderColor: '#dc2626', backgroundColor: 'transparent', tension: 0.4, borderDash: [4,3], borderWidth: 1.5, pointRadius: 3 },
            { label: 'Minimum', data: <?= json_encode($tMin ?: [0]) ?>, borderColor: '#059669', backgroundColor: 'transparent', tension: 0.4, borderDash: [4,3], borderWidth: 1.5, pointRadius: 3 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 14 } } },
        scales: { y: { min: 50, ticks: { callback: v => v + ' mg/dL', font: { size: 11 } }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } } }
    }
});

// Status Pie Chart
new Chart(document.getElementById('statusPieChart'), {
    type: 'doughnut',
    data: {
        labels: ['Hypoglycemia', 'Normal', 'Pre-Diabetic', 'Diabetic', 'Critical High'],
        datasets: [{ 
            data: [<?= $statusDist['low'] ?>, <?= $statusDist['normal'] ?>, <?= $statusDist['prediab'] ?>, <?= $statusDist['diabetic'] ?>, <?= $statusDist['critical'] ?>],
            backgroundColor: ['#3b82f6','#10b981','#f59e0b','#f97316','#ef4444'],
            borderWidth: 3, borderColor: '#fff', hoverOffset: 6
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } } }
    }
});

// Type Bar Chart
new Chart(document.getElementById('typeBarChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($typeRows, 'reading_type')) ?>,
        datasets: [{ 
            label: 'Average (mg/dL)',
            data: <?= json_encode(array_map(fn($r) => round($r['avg_val'],1), $typeRows)) ?>,
            backgroundColor: 'rgba(37,99,235,0.75)', borderRadius: 8, borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => v + ' mg/dL', font: { size: 11 } }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 35 } } }
    }
});

// High Readings Chart
new Chart(document.getElementById('highCountChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($tDates ?: ['No Data']) ?>,
        datasets: [{ 
            label: 'High Readings (>180)',
            data: <?= json_encode($tHigh ?: [0]) ?>,
            backgroundColor: 'rgba(220,38,38,0.7)', borderRadius: 6, borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } } }
    }
});
</script>

<?php require_once 'footer.php'; ?>
