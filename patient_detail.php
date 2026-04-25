<?php
require_once 'config.php';
$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header("Location: patients.php"); exit; }
$id = intval($_GET['id']);

$result = $db->query("SELECT * FROM patients WHERE id=$id");
$p = $result->fetch_assoc();
if (!$p) { header("Location: patients.php"); exit; }

$page_title = $p['full_name'];

// Blood sugar readings for this patient
$readings = $db->query("SELECT * FROM blood_sugar_readings WHERE patient_id=$id ORDER BY reading_date DESC, reading_time DESC LIMIT 50");
$allReadings = [];
while ($r = $readings->fetch_assoc()) $allReadings[] = $r;

// HbA1c records
$hba1cRecords = $db->query("SELECT * FROM hba1c_records WHERE patient_id=$id ORDER BY test_date DESC");
$allHba1c = [];
while ($h = $hba1cRecords->fetch_assoc()) $allHba1c[] = $h;

// Medications
$meds = $db->query("SELECT * FROM medications WHERE patient_id=$id ORDER BY status, start_date DESC");
$allMeds = [];
while ($m = $meds->fetch_assoc()) $allMeds[] = $m;

// Chart data - last 30 days fasting
$chartData30 = $db->query("SELECT reading_date, blood_sugar_value, reading_type FROM blood_sugar_readings 
    WHERE patient_id=$id AND reading_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
    ORDER BY reading_date ASC, reading_time ASC");
$chartDates = []; $fastingVals = []; $postMealVals = [];
$byDate = [];
while ($row = $chartData30->fetch_assoc()) {
    $d = $row['reading_date'];
    if (!isset($byDate[$d])) $byDate[$d] = ['fasting' => null, 'post' => null];
    if (strpos($row['reading_type'], 'Fasting') !== false || strpos($row['reading_type'], 'Before') !== false) {
        $byDate[$d]['fasting'] = $row['blood_sugar_value'];
    } else {
        $byDate[$d]['post'] = $row['blood_sugar_value'];
    }
}
foreach ($byDate as $date => $vals) {
    $chartDates[] = date('d M', strtotime($date));
    $fastingVals[] = $vals['fasting'];
    $postMealVals[] = $vals['post'];
}

// HbA1c chart
$hba1cDates = array_map(fn($h) => date('M Y', strtotime($h['test_date'])), $allHba1c);
$hba1cVals = array_map(fn($h) => $h['hba1c_value'], $allHba1c);
$hba1cDates = array_reverse($hba1cDates);
$hba1cVals = array_reverse($hba1cVals);

// Stats
$lastBS = !empty($allReadings) ? $allReadings[0] : null;
$lastHba1c = !empty($allHba1c) ? $allHba1c[0] : null;
$age = calculateAge($p['date_of_birth']);
$bmi = ($p['weight'] && $p['height']) ? round($p['weight'] / pow($p['height']/100, 2), 1) : null;

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $p['full_name']), 0, 2)));
$avatarClass = $p['gender'] === 'Male' ? 'avatar-male' : ($p['gender'] === 'Female' ? 'avatar-female' : 'avatar-other');

require_once 'header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success"><span class="alert-icon">✅</span>
    <?= $_GET['msg'] === 'created' ? 'Patient registered successfully!' : 'Patient record updated successfully!' ?>
</div>
<?php endif; ?>

<!-- PATIENT HERO -->
<div class="patient-hero">
    <div class="hero-top">
        <div class="hero-avatar"><?= $initials ?></div>
        <div class="hero-info" style="flex:1">
            <h1><?= htmlspecialchars($p['full_name']) ?></h1>
            <div class="pid"><?= $p['patient_id'] ?> &nbsp;&bull;&nbsp; <?= $p['diabetes_type'] ?> Diabetes</div>
            <div class="hero-meta">
                <span class="hero-meta-item">🎂 <?= $age ?> years</span>
                <span class="hero-meta-item">⚧ <?= $p['gender'] ?></span>
                <?php if ($p['phone']): ?><span class="hero-meta-item">📞 <?= htmlspecialchars($p['phone']) ?></span><?php endif; ?>
                <?php if ($p['doctor_name']): ?><span class="hero-meta-item">👨‍⚕️ <?= htmlspecialchars($p['doctor_name']) ?></span><?php endif; ?>
                <span class="hero-meta-item">🩸 <?= $p['blood_group'] ?></span>
                <?php if ($bmi): ?><span class="hero-meta-item">⚖️ BMI: <?= $bmi ?></span><?php endif; ?>
            </div>
        </div>
        <div class="hero-actions">
            <a href="patient_form.php?edit=<?= $id ?>" class="btn btn-outline" style="border-color:rgba(255,255,255,0.3);color:white;">✏️ Edit</a>
            <a href="add_reading.php?pid=<?= $id ?>" class="btn btn-success">🩸 Add Reading</a>
            <a href="generate_report.php?pid=<?= $id ?>" class="btn btn-primary">🖨️ Report</a>
        </div>
    </div>

    <div class="quick-stats">
        <div class="quick-stat">
            <div class="qs-value" style="color:<?= $lastBS ? interpretBloodSugar($lastBS['blood_sugar_value'], $lastBS['reading_type'])['color'] : '#94a3b8' ?>">
                <?= $lastBS ? $lastBS['blood_sugar_value'] : '--' ?>
            </div>
            <div class="qs-label">Last BS (mg/dL)</div>
        </div>
        <div class="quick-stat">
            <div class="qs-value" style="color:<?= $lastHba1c ? interpretHbA1c($lastHba1c['hba1c_value'])['color'] : '#94a3b8' ?>">
                <?= $lastHba1c ? $lastHba1c['hba1c_value'] . '%' : '--' ?>
            </div>
            <div class="qs-label">Last HbA1c</div>
        </div>
        <div class="quick-stat">
            <div class="qs-value"><?= count($allReadings) ?></div>
            <div class="qs-label">Total Readings</div>
        </div>
        <div class="quick-stat">
            <div class="qs-value" style="color:<?= $p['status']==='Critical'?'#f87171':($p['status']==='Active'?'#4ade80':'#94a3b8') ?>">
                <?= $p['status'] ?>
            </div>
            <div class="qs-label">Patient Status</div>
        </div>
    </div>
</div>

<!-- TABS -->
<div class="tabs">
    <button class="tab-btn active" onclick="showTab('tab-overview', this)">📋 Overview</button>
    <button class="tab-btn" onclick="showTab('tab-readings', this)">🩸 Blood Sugar History</button>
    <button class="tab-btn" onclick="showTab('tab-charts', this)">📈 Charts & Analysis</button>
    <button class="tab-btn" onclick="showTab('tab-hba1c', this)">🧪 HbA1c Records</button>
    <button class="tab-btn" onclick="showTab('tab-meds', this)">💊 Medications</button>
</div>

<!-- TAB: OVERVIEW -->
<div id="tab-overview" class="tab-content active">
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h3>👤 Personal Details</h3></div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($p['full_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Patient ID</div><div class="info-value"><?= $p['patient_id'] ?></div></div>
                    <div class="info-item"><div class="info-label">Date of Birth</div><div class="info-value"><?= date('d M Y', strtotime($p['date_of_birth'])) ?> (<?= $age ?> yrs)</div></div>
                    <div class="info-item"><div class="info-label">Gender</div><div class="info-value"><?= $p['gender'] ?></div></div>
                    <div class="info-item"><div class="info-label">Blood Group</div><div class="info-value"><?= $p['blood_group'] ?></div></div>
                    <div class="info-item"><div class="info-label">Phone</div><div class="info-value"><?= htmlspecialchars($p['phone'] ?: 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($p['email'] ?: 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Insurance</div><div class="info-value"><?= htmlspecialchars($p['insurance_number'] ?: 'N/A') ?></div></div>
                    <?php if ($p['address']): ?>
                    <div class="info-item" style="grid-column:1/-1"><div class="info-label">Address</div><div class="info-value"><?= htmlspecialchars($p['address']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>🩺 Medical Details</h3></div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Diabetes Type</div>
                        <div class="info-value"><span class="badge badge-purple"><?= $p['diabetes_type'] ?></span></div>
                    </div>
                    <div class="info-item"><div class="info-label">Diagnosis Date</div><div class="info-value"><?= $p['diagnosis_date'] ? date('d M Y', strtotime($p['diagnosis_date'])) : 'N/A' ?></div></div>
                    <div class="info-item"><div class="info-label">Consulting Doctor</div><div class="info-value"><?= htmlspecialchars($p['doctor_name'] ?: 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Weight / Height</div><div class="info-value"><?= $p['weight'] ? $p['weight'].'kg' : 'N/A' ?> / <?= $p['height'] ? $p['height'].'cm' : 'N/A' ?></div></div>
                    <div class="info-item"><div class="info-label">BMI</div><div class="info-value"><?= $bmi ?: 'N/A' ?></div></div>
                    <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="badge status-<?= strtolower($p['status']) ?>"><?= $p['status'] ?></span></div></div>
                    <?php if ($p['allergies']): ?>
                    <div class="info-item" style="grid-column:1/-1"><div class="info-label">Allergies</div><div class="info-value" style="color:var(--danger)"><?= htmlspecialchars($p['allergies']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['complications']): ?>
                    <div class="info-item" style="grid-column:1/-1"><div class="info-label">Complications</div><div class="info-value"><?= htmlspecialchars($p['complications']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['notes']): ?>
                    <div class="info-item" style="grid-column:1/-1"><div class="info-label">Notes</div><div class="info-value"><?= htmlspecialchars($p['notes']) ?></div></div>
                    <?php endif; ?>
                </div>
                <div class="divider"></div>
                <div style="background:#f8fafc;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:var(--text-medium);margin-bottom:8px;">🆘 EMERGENCY CONTACT</div>
                    <div style="font-weight:600"><?= htmlspecialchars($p['emergency_contact'] ?: 'Not provided') ?></div>
                    <div style="font-size:13px;color:var(--text-medium)"><?= htmlspecialchars($p['emergency_phone'] ?: '') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Latest Reading Card -->
    <?php if ($lastBS):
        $interp = interpretBloodSugar($lastBS['blood_sugar_value'], $lastBS['reading_type']);
    ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3>🩸 Latest Blood Sugar Status</h3>
            <a href="add_reading.php?pid=<?= $id ?>" class="btn btn-success btn-sm">+ Add Reading</a>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:28px;flex-wrap:wrap;">
                <div style="text-align:center;padding:20px 32px;background:<?= $interp['color'] ?>15;border-radius:16px;border:2px solid <?= $interp['color'] ?>30;">
                    <div style="font-size:52px;font-weight:900;color:<?= $interp['color'] ?>"><?= $lastBS['blood_sugar_value'] ?></div>
                    <div style="font-size:14px;color:var(--text-medium);margin-top:2px">mg/dL</div>
                    <div style="font-size:16px;margin-top:8px"><?= $interp['icon'] ?> <?= $interp['label'] ?></div>
                </div>
                <div>
                    <div style="margin-bottom:10px"><span style="font-size:12px;color:var(--text-light);display:block">READING TYPE</span><strong><?= $lastBS['reading_type'] ?></strong></div>
                    <div style="margin-bottom:10px"><span style="font-size:12px;color:var(--text-light);display:block">DATE & TIME</span><strong><?= date('d M Y', strtotime($lastBS['reading_date'])) ?> at <?= date('H:i', strtotime($lastBS['reading_time'])) ?></strong></div>
                    <?php if ($lastBS['notes']): ?>
                    <div><span style="font-size:12px;color:var(--text-light);display:block">NOTES</span><strong><?= htmlspecialchars($lastBS['notes']) ?></strong></div>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:200px;">
                    <?php
                    $targetLabel = 'Target Range';
                    $targetRange = '70 - 99 mg/dL (Fasting)';
                    $deviation = $lastBS['blood_sugar_value'] - 100;
                    ?>
                    <div style="background:#f8fafc;border-radius:12px;padding:16px;">
                        <div style="font-size:12px;color:var(--text-light);margin-bottom:6px">ADVICE</div>
                        <?php if ($interp['level'] === 'normal'): ?>
                        <p style="color:var(--success);font-weight:600">✅ Blood sugar is within normal range. Continue current treatment plan.</p>
                        <?php elseif ($interp['level'] === 'warning'): ?>
                        <p style="color:var(--warning);font-weight:600">⚠️ Blood sugar is elevated (pre-diabetic range). Monitor closely and review diet.</p>
                        <?php elseif ($interp['level'] === 'high'): ?>
                        <p style="color:var(--danger);font-weight:600">🔶 Blood sugar is in diabetic range. Review medication dosage with doctor.</p>
                        <?php elseif ($interp['level'] === 'critical'): ?>
                        <p style="color:#9b1c1c;font-weight:700">🚨 CRITICAL: Blood sugar is dangerously high! Immediate medical attention required.</p>
                        <?php else: ?>
                        <p style="color:var(--primary);font-weight:600">⬇️ HYPOGLYCEMIA: Blood sugar is too low. Give glucose/sugar immediately.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- TAB: BLOOD SUGAR READINGS -->
<div id="tab-readings" class="tab-content">
    <div class="card">
        <div class="card-header">
            <div><h3>🩸 Blood Sugar Reading History</h3><p><?= count($allReadings) ?> total readings</p></div>
            <a href="add_reading.php?pid=<?= $id ?>" class="btn btn-success btn-sm">+ Add Reading</a>
        </div>
        <?php if (empty($allReadings)): ?>
        <div class="empty-state">
            <div class="empty-icon">🩸</div>
            <h3>No readings recorded yet</h3>
            <p><a href="add_reading.php?pid=<?= $id ?>">Record the first blood sugar reading</a></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Date & Time</th><th>Type</th><th>Blood Sugar</th><th>Status</th><th>Notes</th><th>Recorded By</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($allReadings as $i => $r):
                    $interp = interpretBloodSugar($r['blood_sugar_value'], $r['reading_type']);
                ?>
                <tr>
                    <td style="color:var(--text-light);font-size:12px"><?= count($allReadings) - $i ?></td>
                    <td>
                        <div style="font-weight:600"><?= date('d M Y', strtotime($r['reading_date'])) ?></div>
                        <div style="font-size:12px;color:var(--text-light)"><?= date('H:i', strtotime($r['reading_time'])) ?></div>
                    </td>
                    <td><span class="badge badge-secondary"><?= $r['reading_type'] ?></span></td>
                    <td>
                        <span style="font-size:20px;font-weight:900;color:<?= $interp['color'] ?>"><?= $r['blood_sugar_value'] ?></span>
                        <span style="font-size:11px;color:var(--text-light)"> <?= $r['unit'] ?></span>
                    </td>
                    <td><span class="badge" style="background:<?= $interp['color'] ?>20;color:<?= $interp['color'] ?>"><?= $interp['icon'] ?> <?= $interp['label'] ?></span></td>
                    <td style="font-size:12px;max-width:150px"><?= htmlspecialchars($r['notes'] ?: '-') ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($r['recorded_by'] ?: '-') ?></td>
                    <td>
                        <button onclick="confirmDelete('delete_reading.php?id=<?= $r['id'] ?>&pid=<?= $id ?>', 'this reading')" class="btn btn-danger btn-sm btn-icon">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TAB: CHARTS -->
<div id="tab-charts" class="tab-content">
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h3>📈 Blood Sugar Trend (Last 30 Days)</h3></div>
            <div class="card-body">
                <div class="chart-container-lg">
                    <canvas id="patientTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>🧪 HbA1c Trend</h3></div>
            <div class="card-body">
                <div class="chart-container-lg">
                    <canvas id="hba1cTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Reading type distribution
    $typeQuery = $db->query("SELECT reading_type, AVG(blood_sugar_value) as avg_val, COUNT(*) as cnt FROM blood_sugar_readings WHERE patient_id=$id GROUP BY reading_type");
    $typeAvgLabels = []; $typeAvgVals = [];
    while ($t = $typeQuery->fetch_assoc()) {
        $typeAvgLabels[] = $t['reading_type'];
        $typeAvgVals[] = round($t['avg_val'], 1);
    }
    ?>

    <div class="card mt-4">
        <div class="card-header"><h3>📊 Average Reading by Type</h3></div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="typeAvgChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TAB: HbA1c -->
<div id="tab-hba1c" class="tab-content">
    <div class="card">
        <div class="card-header">
            <div><h3>🧪 HbA1c Records</h3></div>
            <a href="add_hba1c.php?pid=<?= $id ?>" class="btn btn-primary btn-sm">+ Add HbA1c</a>
        </div>
        <?php if (empty($allHba1c)): ?>
        <div class="empty-state"><div class="empty-icon">🧪</div><h3>No HbA1c records</h3></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Test Date</th><th>HbA1c Value</th><th>Status</th><th>Lab</th><th>Recorded By</th><th>Notes</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($allHba1c as $h):
                    $hInterp = interpretHbA1c($h['hba1c_value']);
                ?>
                <tr>
                    <td><strong><?= date('d M Y', strtotime($h['test_date'])) ?></strong></td>
                    <td><span style="font-size:22px;font-weight:900;color:<?= $hInterp['color'] ?>"><?= $h['hba1c_value'] ?>%</span></td>
                    <td><span class="badge" style="background:<?= $hInterp['color'] ?>20;color:<?= $hInterp['color'] ?>"><?= $hInterp['label'] ?></span></td>
                    <td><?= htmlspecialchars($h['lab_name'] ?: '-') ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($h['recorded_by'] ?: '-') ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($h['notes'] ?: '-') ?></td>
                    <td><button onclick="confirmDelete('delete_hba1c.php?id=<?= $h['id'] ?>&pid=<?= $id ?>', 'this HbA1c record')" class="btn btn-danger btn-sm btn-icon">🗑️</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TAB: MEDICATIONS -->
<div id="tab-meds" class="tab-content">
    <div class="card">
        <div class="card-header">
            <div><h3>💊 Medications</h3></div>
            <a href="medications.php?pid=<?= $id ?>" class="btn btn-primary btn-sm">+ Add Medication</a>
        </div>
        <?php if (empty($allMeds)): ?>
        <div class="empty-state"><div class="empty-icon">💊</div><h3>No medications recorded</h3></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Start Date</th><th>Prescribed By</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($allMeds as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['medication_name']) ?></strong></td>
                    <td><?= htmlspecialchars($m['dosage'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($m['frequency'] ?: '-') ?></td>
                    <td><?= $m['start_date'] ? date('d M Y', strtotime($m['start_date'])) : '-' ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($m['prescribed_by'] ?: '-') ?></td>
                    <td>
                        <span class="badge <?= $m['status']==='Active'?'badge-success':($m['status']==='Completed'?'badge-info':'badge-danger') ?>">
                            <?= $m['status'] ?>
                        </span>
                    </td>
                    <td style="font-size:12px"><?= htmlspecialchars($m['notes'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Patient Blood Sugar Trend Chart
new Chart(document.getElementById('patientTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartDates ?: ['No Data']) ?>,
        datasets: [
            {
                label: 'Fasting/Before Meal',
                data: <?= json_encode($fastingVals ?: [null]) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.07)',
                fill: true, tension: 0.4, spanGaps: true,
                pointBackgroundColor: '#2563eb', pointRadius: 4, borderWidth: 2.5
            },
            {
                label: 'Post-Meal',
                data: <?= json_encode($postMealVals ?: [null]) ?>,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220,38,38,0.05)',
                fill: false, tension: 0.4, spanGaps: true,
                pointBackgroundColor: '#dc2626', pointRadius: 4, borderWidth: 2.5
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 14 } },
            annotation: {}
        },
        scales: {
            y: { min: 40, grid: { color: '#f1f5f9' },
                 ticks: { callback: v => v + ' mg/dL', font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// HbA1c Chart
new Chart(document.getElementById('hba1cTrendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($hba1cDates ?: ['No Data']) ?>,
        datasets: [{
            label: 'HbA1c (%)',
            data: <?= json_encode($hba1cVals ?: [0]) ?>,
            backgroundColor: <?= json_encode(array_map(fn($v) => interpretHbA1c($v)['color'] . '90', $hba1cVals ?: [0])) ?>,
            borderColor: <?= json_encode(array_map(fn($v) => interpretHbA1c($v)['color'], $hba1cVals ?: [0])) ?>,
            borderWidth: 2,
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: false, min: 4,
                 ticks: { callback: v => v + '%', font: { size: 11 } },
                 grid: { color: '#f1f5f9' }
               },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Type Average Chart
new Chart(document.getElementById('typeAvgChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($typeAvgLabels ?: ['No Data']) ?>,
        datasets: [{
            label: 'Average Blood Sugar (mg/dL)',
            data: <?= json_encode($typeAvgVals ?: [0]) ?>,
            backgroundColor: ['rgba(37,99,235,0.7)','rgba(220,38,38,0.7)','rgba(5,150,105,0.7)','rgba(217,119,6,0.7)','rgba(124,58,237,0.7)','rgba(8,145,178,0.7)','rgba(239,68,68,0.7)','rgba(16,185,129,0.7)','rgba(245,158,11,0.7)'],
            borderRadius: 8,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: false, min: 50,
                 ticks: { callback: v => v + ' mg/dL', font: { size: 11 } },
                 grid: { color: '#f1f5f9' }
               },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});
</script>

<?php require_once 'footer.php'; ?>
