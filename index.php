<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Dashboard';

// Fetch stats
$totalPatients = $db->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
$activePatients = $db->query("SELECT COUNT(*) as c FROM patients WHERE status='Active'")->fetch_assoc()['c'];
$criticalPatients = $db->query("SELECT COUNT(*) as c FROM patients WHERE status='Critical'")->fetch_assoc()['c'];
$todayReadings = $db->query("SELECT COUNT(*) as c FROM blood_sugar_readings WHERE reading_date=CURDATE()")->fetch_assoc()['c'];
$highReadings = $db->query("SELECT COUNT(*) as c FROM blood_sugar_readings WHERE blood_sugar_value > 180 AND reading_date=CURDATE()")->fetch_assoc()['c'];

// Recent patients
$recentPatients = $db->query("SELECT p.*, 
    (SELECT blood_sugar_value FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_bs,
    (SELECT reading_type FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_type,
    (SELECT reading_date FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_date
    FROM patients p ORDER BY p.created_at DESC LIMIT 8");

// Recent readings
$recentReadings = $db->query("SELECT bsr.*, p.full_name, p.patient_id as pid 
    FROM blood_sugar_readings bsr 
    JOIN patients p ON bsr.patient_id = p.id 
    ORDER BY bsr.reading_date DESC, bsr.reading_time DESC LIMIT 10");

// Chart data - last 7 days average
$chartData = $db->query("SELECT reading_date, 
    AVG(blood_sugar_value) as avg_bs,
    MAX(blood_sugar_value) as max_bs,
    MIN(blood_sugar_value) as min_bs,
    COUNT(*) as count
    FROM blood_sugar_readings 
    WHERE reading_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY reading_date ORDER BY reading_date ASC");
$chartLabels = []; $chartAvg = []; $chartMax = []; $chartMin = [];
while ($row = $chartData->fetch_assoc()) {
    $chartLabels[] = date('d M', strtotime($row['reading_date']));
    $chartAvg[] = round($row['avg_bs'], 1);
    $chartMax[] = round($row['max_bs'], 1);
    $chartMin[] = round($row['min_bs'], 1);
}

// Diabetes type distribution
$typeData = $db->query("SELECT diabetes_type, COUNT(*) as count FROM patients GROUP BY diabetes_type");
$typeLabels = []; $typeCounts = [];
while ($row = $typeData->fetch_assoc()) {
    $typeLabels[] = $row['diabetes_type'];
    $typeCounts[] = $row['count'];
}

// Status distribution
$statusData = $db->query("SELECT status, COUNT(*) as count FROM patients GROUP BY status");
$statusMap = ['Active' => 0, 'Inactive' => 0, 'Critical' => 0, 'Discharged' => 0];
while ($row = $statusData->fetch_assoc()) {
    $statusMap[$row['status']] = $row['count'];
}

require_once 'header.php';
?>

<!-- STATS GRID -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon blue">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalPatients ?></div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-change up">↑ Registered</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">✅</div>
        <div class="stat-info">
            <div class="stat-value"><?= $activePatients ?></div>
            <div class="stat-label">Active Patients</div>
            <div class="stat-change up">Under Care</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon red">🚨</div>
        <div class="stat-info">
            <div class="stat-value"><?= $criticalPatients ?></div>
            <div class="stat-label">Critical Cases</div>
            <div class="stat-change down">Need Attention</div>
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon teal">🩸</div>
        <div class="stat-info">
            <div class="stat-value"><?= $todayReadings ?></div>
            <div class="stat-label">Today's Readings</div>
            <div class="stat-change up">Recorded Today</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon orange">⚠️</div>
        <div class="stat-info">
            <div class="stat-value"><?= $highReadings ?></div>
            <div class="stat-label">High Readings Today</div>
            <div class="stat-change down">>180 mg/dL</div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="grid-2 mb-4">
    <div class="card">
        <div class="card-header">
            <div>
                <h3>📈 Blood Sugar Trend (Last 7 Days)</h3>
                <p>Average, min and max readings across all patients</p>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div><h3>🍩 Patient Distribution</h3>
            <p>By diabetes type</p></div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="typeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- REFERENCE TABLE + STATUS -->
<div class="grid-2 mb-4">
    <div class="card">
        <div class="card-header">
            <h3>📋 Blood Sugar Reference Guide</h3>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr><th>Condition</th><th>Fasting (mg/dL)</th><th>Post-Meal (mg/dL)</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Normal</strong></td><td>70 - 99</td><td>&lt; 140</td><td><span class="badge badge-success">✅ Normal</span></td></tr>
                    <tr><td><strong>Pre-Diabetes</strong></td><td>100 - 125</td><td>140 - 199</td><td><span class="badge badge-warning">⚠️ Monitor</span></td></tr>
                    <tr><td><strong>Diabetes</strong></td><td>≥ 126</td><td>≥ 200</td><td><span class="badge badge-danger">🔴 Diabetic</span></td></tr>
                    <tr><td><strong>Hypoglycemia</strong></td><td>&lt; 70</td><td>&lt; 70</td><td><span class="badge badge-info">⬇️ Low</span></td></tr>
                    <tr><td><strong>Critical High</strong></td><td>&gt; 300</td><td>&gt; 300</td><td><span class="badge" style="background:#9b1c1c;color:white">🚨 Critical</span></td></tr>
                </tbody>
            </table>
            <div class="divider"></div>
            <p style="font-size:12px;color:var(--text-light)">HbA1c: Normal &lt;5.7% | Pre-diabetes 5.7-6.4% | Diabetes ≥6.5% | Well controlled &lt;7% | Uncontrolled ≥8%</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📊 Patient Status Overview</h3>
        </div>
        <div class="card-body">
            <?php foreach ($statusMap as $status => $count): 
                $pct = $totalPatients > 0 ? round($count/$totalPatients*100) : 0;
                $barClass = $status === 'Active' ? 'green' : ($status === 'Critical' ? 'red' : 'yellow');
            ?>
            <div style="margin-bottom:18px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:13px;font-weight:600;"><?= $status ?> Patients</span>
                    <span style="font-size:13px;color:var(--text-medium);"><?= $count ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress">
                    <div class="progress-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="divider"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px;">
                <a href="generate_report.php" class="btn btn-primary" style="justify-content:center;">🖨️ Generate Report</a>
                <a href="reports.php" class="btn btn-outline" style="justify-content:center;">📈 View Analytics</a>
            </div>
        </div>
    </div>
</div>

<!-- RECENT PATIENTS + READINGS -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div><h3>👥 Recent Patients</h3><p>Latest registrations</p></div>
            <a href="patients.php" class="btn btn-secondary btn-sm">View All →</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Patient</th><th>Type</th><th>Last BS</th><th>Status</th></tr></thead>
                <tbody>
                <?php while ($p = $recentPatients->fetch_assoc()):
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $p['full_name']), 0, 2)));
                    $avatarClass = $p['gender'] === 'Male' ? 'avatar-male' : ($p['gender'] === 'Female' ? 'avatar-female' : 'avatar-other');
                    $statusClass = 'status-' . strtolower($p['status']);
                ?>
                <tr>
                    <td>
                        <div class="patient-name-cell">
                            <div class="patient-avatar <?= $avatarClass ?>"><?= $initials ?></div>
                            <div>
                                <div class="name"><a href="patient_detail.php?id=<?= $p['id'] ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($p['full_name']) ?></a></div>
                                <div class="pid"><?= htmlspecialchars($p['patient_id']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-purple"><?= $p['diabetes_type'] ?></span></td>
                    <td>
                        <?php if ($p['last_bs']):
                            $interp = interpretBloodSugar($p['last_bs'], strpos($p['last_type'], 'After') !== false ? 'after' : 'Fasting');
                        ?>
                        <span style="font-weight:700;color:<?= $interp['color'] ?>"><?= $p['last_bs'] ?> <span style="font-size:11px;font-weight:400">mg/dL</span></span>
                        <?php else: ?><span class="text-muted">No reading</span><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $statusClass ?>"><?= $p['status'] ?></span></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h3>🩸 Recent Blood Sugar Readings</h3><p>Latest recorded values</p></div>
            <a href="add_reading.php" class="btn btn-success btn-sm">+ Add</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Patient</th><th>Type</th><th>Value</th><th>Date</th></tr></thead>
                <tbody>
                <?php while ($r = $recentReadings->fetch_assoc()):
                    $interp = interpretBloodSugar($r['blood_sugar_value'], strpos($r['reading_type'], 'After') !== false ? 'after' : 'Fasting');
                ?>
                <tr>
                    <td>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($r['full_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-light)"><?= $r['pid'] ?></div>
                    </td>
                    <td style="font-size:12px;color:var(--text-medium)"><?= $r['reading_type'] ?></td>
                    <td>
                        <span style="font-weight:800;font-size:15px;color:<?= $interp['color'] ?>"><?= $r['blood_sugar_value'] ?></span>
                        <span style="font-size:11px;color:var(--text-light)"> mg/dL</span>
                        <div style="font-size:11px;color:<?= $interp['color'] ?>"><?= $interp['icon'] ?> <?= $interp['label'] ?></div>
                    </td>
                    <td style="font-size:12px;color:var(--text-medium)"><?= date('d M', strtotime($r['reading_date'])) ?><br><?= date('H:i', strtotime($r['reading_time'])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Trend Chart
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels ?: ['No Data']) ?>,
        datasets: [
            {
                label: 'Average',
                data: <?= json_encode($chartAvg ?: [0]) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.08)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#2563eb',
                pointRadius: 5,
                borderWidth: 2.5
            },
            {
                label: 'Max',
                data: <?= json_encode($chartMax ?: [0]) ?>,
                borderColor: '#dc2626',
                backgroundColor: 'transparent',
                tension: 0.4,
                borderDash: [5,3],
                pointRadius: 3,
                borderWidth: 1.5
            },
            {
                label: 'Min',
                data: <?= json_encode($chartMin ?: [0]) ?>,
                borderColor: '#059669',
                backgroundColor: 'transparent',
                tension: 0.4,
                borderDash: [5,3],
                pointRadius: 3,
                borderWidth: 1.5
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 14 } } },
        scales: {
            y: { beginAtZero: false, min: 50, grid: { color: '#f1f5f9' },
                 ticks: { font: { size: 11 }, callback: v => v + ' mg/dL' } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Type Donut Chart
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($typeLabels ?: ['No Data']) ?>,
        datasets: [{
            data: <?= json_encode($typeCounts ?: [1]) ?>,
            backgroundColor: ['#2563eb','#7c3aed','#059669','#d97706','#dc2626','#0891b2'],
            borderWidth: 3, borderColor: '#fff',
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } }
        }
    }
});
</script>

<?php require_once 'footer.php'; ?>
