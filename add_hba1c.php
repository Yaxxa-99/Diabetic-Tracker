<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Record HbA1c';

$patients = $db->query("SELECT id, patient_id, full_name FROM patients WHERE status != 'Discharged' ORDER BY full_name");
$patientList = [];
while ($p = $patients->fetch_assoc()) $patientList[] = $p;

$selectedPid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = intval($_POST['patient_id']);
    $value = floatval($_POST['hba1c_value']);
    $testDate = $_POST['test_date'];
    $labName = sanitize($_POST['lab_name'] ?? '');
    $recordedBy = sanitize($_POST['recorded_by'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if ($patientId && $value > 0 && $testDate) {
        $db->query("INSERT INTO hba1c_records (patient_id, hba1c_value, test_date, lab_name, recorded_by, notes)
            VALUES ($patientId, $value, '$testDate', '" . $db->real_escape_string($labName) . "',
            '" . $db->real_escape_string($recordedBy) . "', '" . $db->real_escape_string($notes) . "')");
        $interp = interpretHbA1c($value);
        $success = "HbA1c of <strong>$value%</strong> recorded. Status: <strong>{$interp['label']}</strong>";
        $selectedPid = $patientId;
    } else {
        $error = "Please fill all required fields.";
    }
}

require_once 'header.php';
?>

<div class="page-header">
    <div><h1>🧪 Record HbA1c Test Result</h1><p>Enter glycated hemoglobin test result</p></div>
    <a href="patients.php" class="btn btn-secondary">← All Patients</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><span class="alert-icon">✅</span><div><?= $success ?></div></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><span class="alert-icon">❌</span><?= $error ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Enter HbA1c Details</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group mb-4">
                    <label>Select Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">— Choose patient —</option>
                        <?php foreach ($patientList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $selectedPid==$p['id']?'selected':'' ?>>
                            <?= htmlspecialchars($p['patient_id']) ?> — <?= htmlspecialchars($p['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>HbA1c Value (%) <span class="required">*</span></label>
                        <input type="number" name="hba1c_value" class="form-control" step="0.1" min="3" max="20" placeholder="e.g. 7.2" required id="hba1cVal" oninput="updateHbA1cStatus()">
                    </div>
                    <div class="form-group">
                        <label>Test Date <span class="required">*</span></label>
                        <input type="date" name="test_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Laboratory Name</label>
                        <input type="text" name="lab_name" class="form-control" placeholder="e.g. City Lab">
                    </div>
                    <div class="form-group">
                        <label>Recorded By</label>
                        <input type="text" name="recorded_by" class="form-control" placeholder="Doctor / Lab technician">
                    </div>
                    <div class="form-group form-full">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" placeholder="Observations, treatment changes, etc..."></textarea>
                    </div>
                </div>

                <div id="hba1cPreview" style="display:none;padding:14px;border-radius:10px;border:2px solid;margin:14px 0;">
                    <div id="hba1cStatus" style="font-size:16px;font-weight:700;"></div>
                    <div id="hba1cAdvice" style="font-size:12.5px;margin-top:5px;"></div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%">💾 Save HbA1c Record</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>📊 HbA1c Interpretation Guide</h3></div>
        <div class="card-body">
            <?php foreach ([
                ['< 5.7', 'Normal', '#27ae60', 'No diabetes. Maintain healthy lifestyle.'],
                ['5.7 – 6.4', 'Pre-diabetes', '#f39c12', 'Lifestyle modifications required. Increased diabetes risk.'],
                ['6.5 – 6.9', 'Diabetic (Well Controlled)', '#e67e22', 'Diabetes confirmed. Good blood sugar management.'],
                ['7.0 – 7.9', 'Diabetic (Acceptable)', '#ea580c', 'Acceptable control. Monitor and adjust if needed.'],
                ['8.0 – 9.9', 'Poorly Controlled', '#dc2626', 'Poor control. Review treatment plan urgently.'],
                ['≥ 10.0', 'Very Poorly Controlled', '#9b1c1c', 'Immediate intervention required. High complication risk.'],
            ] as $ref): ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:8px;background:<?= $ref[2] ?>10;margin-bottom:8px;border-left:3px solid <?= $ref[2] ?>;">
                <div style="min-width:60px;font-size:13px;font-weight:700;color:<?= $ref[2] ?>"><?= $ref[0] ?>%</div>
                <div>
                    <div style="font-size:13px;font-weight:700"><?= $ref[1] ?></div>
                    <div style="font-size:12px;color:var(--text-medium)"><?= $ref[3] ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:16px;padding:12px;background:var(--primary-light);border-radius:10px;">
                <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:4px">💡 KEY FACT</div>
                <p style="font-size:12.5px;color:var(--primary-dark)">HbA1c reflects the average blood sugar over the past 2–3 months. Recommended target for most diabetics: <strong>&lt; 7%</strong></p>
            </div>
        </div>
    </div>
</div>

<script>
function updateHbA1cStatus() {
    const val = parseFloat(document.getElementById('hba1cVal').value);
    const preview = document.getElementById('hba1cPreview');
    const status = document.getElementById('hba1cStatus');
    const advice = document.getElementById('hba1cAdvice');
    
    if (!val) { preview.style.display = 'none'; return; }
    
    let color, label, text;
    if (val < 5.7) { color = '#27ae60'; label = '✅ Normal'; text = 'No diabetes indicators.'; }
    else if (val < 6.5) { color = '#f39c12'; label = '⚠️ Pre-diabetes'; text = 'Lifestyle changes recommended.'; }
    else if (val < 7.0) { color = '#e67e22'; label = '🔷 Well Controlled Diabetes'; text = 'Good management. Continue treatment plan.'; }
    else if (val < 8.0) { color = '#ea580c'; label = '🔶 Acceptable Control'; text = 'Review and optimize treatment if needed.'; }
    else { color = '#dc2626'; label = '🚨 Poorly Controlled'; text = 'Urgent review of treatment plan required.'; }
    
    preview.style.display = 'block';
    preview.style.background = color + '18';
    preview.style.borderColor = color + '50';
    status.style.color = color;
    status.textContent = label + ' — ' + val + '%';
    advice.style.color = '#475569';
    advice.textContent = text;
}
</script>

<?php require_once 'footer.php'; ?>
