<?php
require_once 'config.php';
$db = getDB();
$isEdit = isset($_GET['edit']) && is_numeric($_GET['edit']);
$patient = null;
$page_title = $isEdit ? 'Edit Patient' : 'Add New Patient';

if ($isEdit) {
    $id = intval($_GET['edit']);
    $result = $db->query("SELECT * FROM patients WHERE id=$id");
    $patient = $result->fetch_assoc();
    if (!$patient) { header("Location: patients.php"); exit; }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['full_name','date_of_birth','gender','diabetes_type'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) $errors[] = "Field '$f' is required.";
    }

    if (empty($errors)) {
        $fields = [
            'full_name' => sanitize($_POST['full_name']),
            'date_of_birth' => $_POST['date_of_birth'],
            'gender' => $_POST['gender'],
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'diabetes_type' => $_POST['diabetes_type'],
            'diagnosis_date' => !empty($_POST['diagnosis_date']) ? $_POST['diagnosis_date'] : null,
            'doctor_name' => sanitize($_POST['doctor_name'] ?? ''),
            'emergency_contact' => sanitize($_POST['emergency_contact'] ?? ''),
            'emergency_phone' => sanitize($_POST['emergency_phone'] ?? ''),
            'blood_group' => $_POST['blood_group'],
            'weight' => !empty($_POST['weight']) ? floatval($_POST['weight']) : null,
            'height' => !empty($_POST['height']) ? floatval($_POST['height']) : null,
            'allergies' => sanitize($_POST['allergies'] ?? ''),
            'complications' => sanitize($_POST['complications'] ?? ''),
            'insurance_number' => sanitize($_POST['insurance_number'] ?? ''),
            'status' => $_POST['status'],
            'notes' => sanitize($_POST['notes'] ?? '')
        ];

        if ($isEdit) {
            $sets = [];
            foreach ($fields as $k => $v) {
                if ($v === null) $sets[] = "`$k`=NULL";
                else $sets[] = "`$k`='" . $db->real_escape_string($v) . "'";
            }
            $db->query("UPDATE patients SET " . implode(',', $sets) . " WHERE id=" . intval($_GET['edit']));
            header("Location: patient_detail.php?id=" . intval($_GET['edit']) . "&msg=updated");
            exit;
        } else {
            $fields['patient_id'] = generatePatientID();
            $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($fields)));
            $vals = implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string($v) . "'", array_values($fields)));
            $db->query("INSERT INTO patients ($cols) VALUES ($vals)");
            $newId = $db->insert_id;
            header("Location: patient_detail.php?id=$newId&msg=created");
            exit;
        }
    }
}

$v = $patient ?: [];
function val($field, $v) { return isset($v[$field]) ? htmlspecialchars($v[$field]) : ''; }

require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $isEdit ? '✏️ Edit Patient' : '➕ Register New Patient' ?></h1>
        <p><?= $isEdit ? 'Update patient information and details' : 'Add a new diabetic patient to the system' ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if ($isEdit): ?>
        <a href="patient_detail.php?id=<?= $patient['id'] ?>" class="btn btn-secondary">← Back to Profile</a>
        <?php else: ?>
        <a href="patients.php" class="btn btn-secondary">← All Patients</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <span class="alert-icon">❌</span>
    <div><?php foreach ($errors as $e) echo "<div>• $e</div>"; ?></div>
</div>
<?php endif; ?>

<form method="POST">
<!-- SECTION 1: Personal Information -->
<div class="card mb-4">
    <div class="card-header">
        <h3>👤 Personal Information</h3>
    </div>
    <div class="card-body">
        <div class="form-grid-3">
            <div class="form-group form-full" style="grid-column:1/3">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name" class="form-control" value="<?= val('full_name', $v) ?>" placeholder="e.g. John Silva" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['Active','Inactive','Critical','Discharged'] as $s): ?>
                    <option value="<?= $s ?>" <?= val('status', $v)===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date of Birth <span class="required">*</span></label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= val('date_of_birth', $v) ?>" required>
            </div>
            <div class="form-group">
                <label>Gender <span class="required">*</span></label>
                <select name="gender" class="form-control" required>
                    <option value="">Select...</option>
                    <?php foreach (['Male','Female','Other'] as $g): ?>
                    <option value="<?= $g ?>" <?= val('gender', $v)===$g?'selected':'' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Blood Group</label>
                <select name="blood_group" class="form-control">
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $bg): ?>
                    <option value="<?= $bg ?>" <?= val('blood_group', $v)===$bg?'selected':'' ?>><?= $bg ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?= val('phone', $v) ?>" placeholder="+94771234567">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= val('email', $v) ?>" placeholder="patient@email.com">
            </div>
            <div class="form-group form-full">
                <label>Home Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Full address..."><?= val('address', $v) ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: Medical Information -->
<div class="card mb-4">
    <div class="card-header">
        <h3>🩺 Medical Information</h3>
    </div>
    <div class="card-body">
        <div class="form-grid-3">
            <div class="form-group">
                <label>Diabetes Type <span class="required">*</span></label>
                <select name="diabetes_type" class="form-control" required>
                    <option value="">Select type...</option>
                    <?php foreach (['Type 1','Type 2','Gestational','Pre-diabetes','MODY','Other'] as $t): ?>
                    <option value="<?= $t ?>" <?= val('diabetes_type', $v)===$t?'selected':'' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Diagnosis Date</label>
                <input type="date" name="diagnosis_date" class="form-control" value="<?= val('diagnosis_date', $v) ?>">
            </div>
            <div class="form-group">
                <label>Consulting Doctor</label>
                <input type="text" name="doctor_name" class="form-control" value="<?= val('doctor_name', $v) ?>" placeholder="Dr. Name">
            </div>
            <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" name="weight" class="form-control" step="0.1" min="20" max="300" value="<?= val('weight', $v) ?>" placeholder="e.g. 72.5">
            </div>
            <div class="form-group">
                <label>Height (cm)</label>
                <input type="number" name="height" class="form-control" step="0.1" min="50" max="250" value="<?= val('height', $v) ?>" placeholder="e.g. 168.0">
            </div>
            <div class="form-group">
                <label>Insurance Number</label>
                <input type="text" name="insurance_number" class="form-control" value="<?= val('insurance_number', $v) ?>" placeholder="Policy number">
            </div>
            <div class="form-group form-full">
                <label>Known Allergies</label>
                <textarea name="allergies" class="form-control" rows="2" placeholder="List any known allergies..."><?= val('allergies', $v) ?></textarea>
            </div>
            <div class="form-group form-full">
                <label>Diabetic Complications</label>
                <textarea name="complications" class="form-control" rows="2" placeholder="Neuropathy, retinopathy, nephropathy, etc..."><?= val('complications', $v) ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 3: Emergency Contact -->
<div class="card mb-4">
    <div class="card-header">
        <h3>🆘 Emergency Contact & Notes</h3>
    </div>
    <div class="card-body">
        <div class="form-grid-3">
            <div class="form-group">
                <label>Emergency Contact Name</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= val('emergency_contact', $v) ?>" placeholder="Contact person name">
            </div>
            <div class="form-group">
                <label>Emergency Phone</label>
                <input type="tel" name="emergency_phone" class="form-control" value="<?= val('emergency_phone', $v) ?>" placeholder="+94771234567">
            </div>
            <div class="form-group form-full">
                <label>Additional Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Any other relevant medical notes..."><?= val('notes', $v) ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- SUBMIT -->
<div style="display:flex;gap:12px;justify-content:flex-end;">
    <a href="patients.php" class="btn btn-secondary btn-lg">Cancel</a>
    <button type="submit" class="btn btn-primary btn-lg">
        <?= $isEdit ? '💾 Update Patient' : '✅ Register Patient' ?>
    </button>
</div>
</form>

<?php require_once 'footer.php'; ?>
