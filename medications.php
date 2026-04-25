<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Medications';

$patientId = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$patients = $db->query("SELECT id, patient_id, full_name FROM patients ORDER BY full_name");
$patList = [];
while ($p = $patients->fetch_assoc()) $patList[] = $p;

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = intval($_POST['patient_id']);
    $name = sanitize($_POST['medication_name']);
    $dosage = sanitize($_POST['dosage'] ?? '');
    $freq = sanitize($_POST['frequency'] ?? '');
    $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $prescribed = sanitize($_POST['prescribed_by'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $status = $_POST['status'];

    if ($pid && $name) {
        $db->query("INSERT INTO medications (patient_id, medication_name, dosage, frequency, start_date, prescribed_by, notes, status)
            VALUES ($pid, '" . $db->real_escape_string($name) . "', '" . $db->real_escape_string($dosage) . "',
            '" . $db->real_escape_string($freq) . "', " . ($start ? "'$start'" : "NULL") . ",
            '" . $db->real_escape_string($prescribed) . "', '" . $db->real_escape_string($notes) . "', '$status')");
        $success = "Medication '$name' added successfully!";
        $patientId = $pid;
    } else {
        $error = "Patient and medication name are required.";
    }
}

if (isset($_GET['delete_med']) && is_numeric($_GET['delete_med'])) {
    $mid = intval($_GET['delete_med']);
    $db->query("DELETE FROM medications WHERE id=$mid");
    header("Location: medications.php?pid=$patientId&msg=deleted");
    exit;
}

// Get medications
$whereClause = $patientId ? "WHERE m.patient_id=$patientId" : "";
$meds = $db->query("SELECT m.*, p.full_name, p.patient_id as pid FROM medications m JOIN patients p ON m.patient_id=p.id $whereClause ORDER BY m.status, m.created_at DESC");
$allMeds = [];
while ($r = $meds->fetch_assoc()) $allMeds[] = $r;

require_once 'header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><span class="alert-icon">✅</span><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg']==='deleted'): ?>
<div class="alert alert-error"><span class="alert-icon">🗑️</span>Medication deleted.</div>
<?php endif; ?>

<div class="page-header">
    <div><h1>💊 Medication Management</h1><p>Track prescribed medications for diabetic patients</p></div>
</div>

<div class="grid-2">
    <!-- ADD MEDICATION FORM -->
    <div class="card">
        <div class="card-header"><h3>➕ Add Medication</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group mb-4">
                    <label>Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">— Select patient —</option>
                        <?php foreach ($patList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patientId==$p['id']?'selected':''?>>
                            <?= htmlspecialchars($p['patient_id']) ?> — <?= htmlspecialchars($p['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group form-full">
                        <label>Medication Name <span class="required">*</span></label>
                        <input type="text" name="medication_name" class="form-control" placeholder="e.g. Metformin, Insulin Glargine" required>
                    </div>
                    <div class="form-group">
                        <label>Dosage</label>
                        <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg, 10 units">
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="frequency" class="form-control">
                            <option value="">Select...</option>
                            <option>Once daily</option>
                            <option>Twice daily</option>
                            <option>Three times daily</option>
                            <option>Before each meal</option>
                            <option>Before breakfast</option>
                            <option>At bedtime</option>
                            <option>Weekly</option>
                            <option>As needed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Discontinued">Discontinued</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prescribed By</label>
                        <input type="text" name="prescribed_by" class="form-control" placeholder="Doctor name">
                    </div>
                    <div class="form-group form-full">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">💊 Add Medication</button>
            </form>
        </div>
    </div>

    <!-- COMMON DIABETIC MEDICATIONS REFERENCE -->
    <div class="card">
        <div class="card-header"><h3>📋 Common Diabetic Medications</h3></div>
        <div class="card-body" style="padding:14px 18px;">
            <?php foreach ([
                ['Metformin', 'Biguanide - First-line Type 2 treatment', '#2563eb'],
                ['Glipizide / Glibenclamide', 'Sulfonylurea - Stimulates insulin release', '#7c3aed'],
                ['Sitagliptin (Januvia)', 'DPP-4 inhibitor - Lowers blood sugar', '#059669'],
                ['Empagliflozin (Jardiance)', 'SGLT2 inhibitor - Removes glucose via urine', '#0891b2'],
                ['Insulin Regular', 'Short-acting insulin - Meal coverage', '#ea580c'],
                ['Insulin Glargine', 'Long-acting basal insulin', '#d97706'],
                ['Liraglutide (Victoza)', 'GLP-1 receptor agonist - Reduces appetite', '#dc2626'],
                ['Pioglitazone (Actos)', 'Thiazolidinedione - Improves insulin sensitivity', '#8b5cf6'],
            ] as $med): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 12px;border-left:3px solid <?= $med[2] ?>;margin-bottom:7px;background:<?= $med[2] ?>08;border-radius:0 7px 7px 0;">
                <div>
                    <div style="font-size:13px;font-weight:700;color:<?= $med[2] ?>"><?= $med[0] ?></div>
                    <div style="font-size:11.5px;color:var(--text-medium)"><?= $med[1] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MEDICATIONS TABLE -->
<div class="card mt-4">
    <div class="card-header">
        <div><h3>All Medications</h3><p><?= count($allMeds) ?> records</p></div>
        <div style="display:flex;gap:8px">
            <form method="GET" style="display:flex;gap:8px">
                <select name="pid" class="form-control" style="width:200px;" onchange="this.form.submit()">
                    <option value="">All Patients</option>
                    <?php foreach ($patList as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $patientId==$p['id']?'selected':''?>>
                        <?= htmlspecialchars($p['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Patient</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Start Date</th><th>Prescribed By</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (empty($allMeds)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">💊</div><h3>No medications found</h3></div></td></tr>
            <?php endif; ?>
            <?php foreach ($allMeds as $m): ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($m['full_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-light)"><?= $m['pid'] ?></div>
                </td>
                <td><strong><?= htmlspecialchars($m['medication_name']) ?></strong>
                    <?php if ($m['notes']): ?><div style="font-size:11px;color:var(--text-light)"><?= htmlspecialchars($m['notes']) ?></div><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($m['dosage'] ?: '—') ?></td>
                <td><?= htmlspecialchars($m['frequency'] ?: '—') ?></td>
                <td><?= $m['start_date'] ? date('d M Y', strtotime($m['start_date'])) : '—' ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($m['prescribed_by'] ?: '—') ?></td>
                <td>
                    <span class="badge <?= $m['status']==='Active'?'badge-success':($m['status']==='Completed'?'badge-info':'badge-danger') ?>">
                        <?= $m['status'] ?>
                    </span>
                </td>
                <td>
                    <button onclick="confirmDelete('medications.php?delete_med=<?= $m['id'] ?>&pid=<?= $m['patient_id'] ?>', '<?= htmlspecialchars($m['medication_name']) ?>')" class="btn btn-danger btn-sm btn-icon">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
