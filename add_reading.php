<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Record Blood Sugar';

// Get all patients for dropdown
$patients = $db->query("SELECT id, patient_id, full_name, diabetes_type FROM patients WHERE status != 'Discharged' ORDER BY full_name");
$patientList = [];
while ($p = $patients->fetch_assoc()) $patientList[] = $p;

$selectedPid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = intval($_POST['patient_id']);
    $readingType = $_POST['reading_type'];
    $value = floatval($_POST['blood_sugar_value']);
    $unit = $_POST['unit'];
    $readingDate = $_POST['reading_date'];
    $readingTime = $_POST['reading_time'];
    $notes = sanitize($_POST['notes'] ?? '');
    $recordedBy = sanitize($_POST['recorded_by'] ?? '');

    if ($patientId && $value > 0 && $readingDate && $readingTime) {
        $db->query("INSERT INTO blood_sugar_readings (patient_id, reading_type, blood_sugar_value, unit, reading_date, reading_time, notes, recorded_by)
            VALUES ($patientId, '$readingType', $value, '$unit', '$readingDate', '$readingTime', 
            '" . $db->real_escape_string($notes) . "', '" . $db->real_escape_string($recordedBy) . "')");
        
        $interp = interpretBloodSugar($value, $readingType);
        $success = "✅ Blood sugar reading of <strong>$value mg/dL</strong> recorded successfully! Status: <strong>{$interp['icon']} {$interp['label']}</strong>";
        $selectedPid = $patientId;
    } else {
        $error = "Please fill all required fields correctly.";
    }
}

require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1>🩸 Record Blood Sugar Reading</h1>
        <p>Enter fasting, pre-meal, or post-meal blood sugar values</p>
    </div>
    <a href="patients.php" class="btn btn-secondary">← All Patients</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><span class="alert-icon">✅</span><div><?= $success ?></div></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><span class="alert-icon">❌</span><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- FORM -->
    <div class="card">
        <div class="card-header"><h3>Enter Reading Details</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group mb-4">
                    <label>Select Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required id="patientSelect" onchange="loadPatientInfo(this)">
                        <option value="">— Choose patient —</option>
                        <?php foreach ($patientList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $selectedPid==$p['id']?'selected':'' ?>>
                            <?= htmlspecialchars($p['patient_id']) ?> — <?= htmlspecialchars($p['full_name']) ?> (<?= $p['diabetes_type'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Reading Type <span class="required">*</span></label>
                        <select name="reading_type" class="form-control" required>
                            <optgroup label="Fasting">
                                <option value="Fasting">Fasting (morning)</option>
                            </optgroup>
                            <optgroup label="Before Meals">
                                <option value="Before Breakfast">Before Breakfast</option>
                                <option value="Before Lunch">Before Lunch</option>
                                <option value="Before Dinner">Before Dinner</option>
                            </optgroup>
                            <optgroup label="After Meals (2hr post)">
                                <option value="After Breakfast">After Breakfast</option>
                                <option value="After Lunch">After Lunch</option>
                                <option value="After Dinner">After Dinner</option>
                            </optgroup>
                            <optgroup label="Other">
                                <option value="Bedtime">Bedtime</option>
                                <option value="Random">Random</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Unit</label>
                        <select name="unit" class="form-control">
                            <option value="mg/dL">mg/dL (USA/Asia)</option>
                            <option value="mmol/L">mmol/L (UK/Europe)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Blood Sugar Value <span class="required">*</span></label>
                        <input type="number" name="blood_sugar_value" class="form-control" step="0.1" min="20" max="800" 
                               placeholder="e.g. 126" required id="bsValue" oninput="updateStatus()">
                    </div>

                    <div class="form-group">
                        <label>Reading Date <span class="required">*</span></label>
                        <input type="date" name="reading_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Reading Time <span class="required">*</span></label>
                        <input type="time" name="reading_time" class="form-control" value="<?= date('H:i') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Recorded By</label>
                        <input type="text" name="recorded_by" class="form-control" placeholder="Nurse / Doctor name">
                    </div>

                    <div class="form-group form-full">
                        <label>Notes / Observations</label>
                        <textarea name="notes" class="form-control" placeholder="Symptoms, medications taken, meal info, etc..."></textarea>
                    </div>
                </div>

                <!-- Live Status Preview -->
                <div id="statusPreview" style="display:none;margin:16px 0;padding:16px;border-radius:12px;border:2px solid;transition:all 0.3s;">
                    <div style="font-size:12px;font-weight:700;color:var(--text-medium);margin-bottom:4px">LIVE STATUS PREVIEW</div>
                    <div id="statusText" style="font-size:18px;font-weight:700;"></div>
                    <div id="adviceText" style="font-size:13px;margin-top:6px;"></div>
                </div>

                <div style="display:flex;gap:12px;margin-top:4px;">
                    <button type="submit" class="btn btn-success btn-lg" style="flex:1;">💾 Save Reading</button>
                    <button type="reset" class="btn btn-secondary btn-lg">↺ Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- QUICK REFERENCE + LAST READINGS -->
    <div style="display:flex;flex-direction:column;gap:18px;">
        <!-- Reference -->
        <div class="card">
            <div class="card-header"><h3>📋 Quick Reference</h3></div>
            <div class="card-body" style="padding:14px 18px;">
                <div style="margin-bottom:12px;">
                    <div style="font-size:12px;font-weight:700;color:var(--text-medium);margin-bottom:8px">FASTING BLOOD SUGAR</div>
                    <div style="display:grid;gap:6px;">
                        <?php foreach ([
                            ['< 70', 'Hypoglycemia', '#e74c3c', '⬇️'],
                            ['70 - 99', 'Normal', '#27ae60', '✅'],
                            ['100 - 125', 'Pre-diabetic', '#f39c12', '⚠️'],
                            ['126 - 180', 'Diabetic', '#e67e22', '🔶'],
                            ['> 180', 'Critical High', '#c0392b', '🚨'],
                        ] as $ref): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 12px;background:<?= $ref[2] ?>15;border-radius:8px;border-left:3px solid <?= $ref[2] ?>;">
                            <span style="font-weight:600;font-size:13px"><?= $ref[3] ?> <?= $ref[1] ?></span>
                            <span style="font-size:12px;color:var(--text-medium)"><?= $ref[0] ?> mg/dL</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:12px;font-weight:700;color:var(--text-medium);margin-bottom:8px">POST-MEAL (2hr)</div>
                    <div style="display:grid;gap:6px;">
                        <?php foreach ([
                            ['< 70', 'Hypoglycemia', '#e74c3c'],
                            ['< 140', 'Normal', '#27ae60'],
                            ['140 - 199', 'Pre-diabetic', '#f39c12'],
                            ['200 - 250', 'Diabetic', '#e67e22'],
                            ['> 250', 'Critical High', '#c0392b'],
                        ] as $ref): ?>
                        <div style="display:flex;justify-content:space-between;padding:6px 12px;background:<?= $ref[2] ?>10;border-radius:7px;font-size:12.5px;">
                            <span style="color:var(--text-medium)"><?= $ref[0] ?> mg/dL</span>
                            <span style="color:<?= $ref[2] ?>;font-weight:600"><?= $ref[1] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- HbA1c Reference -->
        <div class="card">
            <div class="card-header"><h3>🧪 HbA1c Reference</h3></div>
            <div class="card-body" style="padding:14px 18px;">
                <div style="display:grid;gap:6px;">
                    <?php foreach ([
                        ['< 5.7%', 'Normal', '#27ae60'],
                        ['5.7 - 6.4%', 'Pre-diabetic', '#f39c12'],
                        ['6.5 - 7.9%', 'Diabetic (controlled)', '#e67e22'],
                        ['≥ 8.0%', 'Uncontrolled', '#c0392b'],
                    ] as $ref): ?>
                    <div style="display:flex;justify-content:space-between;padding:7px 12px;border-radius:7px;background:<?= $ref[2] ?>12;">
                        <span style="font-size:13px;font-weight:600;color:<?= $ref[2] ?>"><?= $ref[1] ?></span>
                        <span style="font-size:13px;color:var(--text-medium)"><?= $ref[0] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:12px;">
                    <a href="add_hba1c.php<?= $selectedPid ? '?pid='.$selectedPid : '' ?>" class="btn btn-outline" style="width:100%;justify-content:center;">🧪 Record HbA1c Instead</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const statusColors = {
    normal: { bg: '#d1fae5', border: '#059669', text: '#065f46' },
    warning: { bg: '#fef3c7', border: '#d97706', text: '#92400e' },
    high: { bg: '#ffedd5', border: '#ea580c', text: '#9a3412' },
    critical: { bg: '#fee2e2', border: '#dc2626', text: '#991b1b' },
    danger: { bg: '#dbeafe', border: '#2563eb', text: '#1e40af' }
};

function updateStatus() {
    const val = parseFloat(document.getElementById('bsValue').value);
    const preview = document.getElementById('statusPreview');
    const statusText = document.getElementById('statusText');
    const adviceText = document.getElementById('adviceText');
    
    if (!val || val < 20) { preview.style.display = 'none'; return; }
    
    let level, label, advice, icon;
    if (val < 70) {
        level = 'danger'; label = '⬇️ Hypoglycemia'; 
        advice = 'ALERT: Blood sugar is dangerously LOW. Give glucose/sugar immediately. Check again in 15 min.';
    } else if (val <= 99) {
        level = 'normal'; label = '✅ Normal Range';
        advice = 'Blood sugar is within normal range. Continue current management plan.';
    } else if (val <= 125) {
        level = 'warning'; label = '⚠️ Pre-Diabetic Range';
        advice = 'Monitor closely. Review diet and activity. Consult doctor if persistent.';
    } else if (val <= 180) {
        level = 'high'; label = '🔶 Diabetic Range';
        advice = 'Blood sugar is elevated. Review medication dosage. Schedule follow-up.';
    } else if (val <= 300) {
        level = 'critical'; label = '🔴 High — Immediate Attention';
        advice = 'Blood sugar is significantly elevated. Review insulin/medication immediately.';
    } else {
        level = 'critical'; label = '🚨 CRITICAL — Emergency!';
        advice = 'CRITICAL: Blood sugar is dangerously high! Immediate medical intervention required!';
    }
    
    const c = statusColors[level];
    preview.style.display = 'block';
    preview.style.background = c.bg;
    preview.style.borderColor = c.border;
    statusText.style.color = c.text;
    statusText.textContent = label + ' — ' + val + ' mg/dL';
    adviceText.style.color = c.text;
    adviceText.textContent = advice;
}

function loadPatientInfo(sel) {
    // Update page with patient info if needed
}
</script>

<?php require_once 'footer.php'; ?>
