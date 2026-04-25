<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Patients';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->query("DELETE FROM patients WHERE id=$id");
    header("Location: patients.php?msg=deleted");
    exit;
}

// Search and filter
$search = isset($_GET['search']) ? $db->real_escape_string($_GET['search']) : '';
$filterType = isset($_GET['type']) ? $db->real_escape_string($_GET['type']) : '';
$filterStatus = isset($_GET['status']) ? $db->real_escape_string($_GET['status']) : '';

$where = "WHERE 1=1";
if ($search) $where .= " AND (p.full_name LIKE '%$search%' OR p.patient_id LIKE '%$search%' OR p.phone LIKE '%$search%' OR p.doctor_name LIKE '%$search%')";
if ($filterType) $where .= " AND p.diabetes_type='$filterType'";
if ($filterStatus) $where .= " AND p.status='$filterStatus'";

$patients = $db->query("SELECT p.*, 
    (SELECT blood_sugar_value FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_bs,
    (SELECT reading_type FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_type,
    (SELECT reading_date FROM blood_sugar_readings WHERE patient_id=p.id ORDER BY reading_date DESC, reading_time DESC LIMIT 1) as last_date,
    (SELECT hba1c_value FROM hba1c_records WHERE patient_id=p.id ORDER BY test_date DESC LIMIT 1) as last_hba1c
    FROM patients p $where ORDER BY p.created_at DESC");

require_once 'header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-<?= $_GET['msg'] === 'deleted' ? 'error' : 'success' ?>">
    <span class="alert-icon"><?= $_GET['msg'] === 'deleted' ? '🗑️' : '✅' ?></span>
    <?= $_GET['msg'] === 'deleted' ? 'Patient record deleted successfully.' : 'Patient saved successfully!' ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Patient Registry</h1>
        <p>Manage all registered diabetic patients and their records</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="patient_form.php" class="btn btn-primary">➕ Add New Patient</a>
        <a href="generate_report.php" class="btn btn-outline">🖨️ Export Report</a>
    </div>
</div>

<!-- FILTER BAR -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 22px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:220px;">
                <label style="font-size:12px;font-weight:600;color:var(--text-medium);display:block;margin-bottom:5px;">SEARCH</label>
                <div class="search-bar">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" placeholder="Name, ID, phone, doctor..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-medium);display:block;margin-bottom:5px;">DIABETES TYPE</label>
                <select name="type" class="form-control" style="width:160px;">
                    <option value="">All Types</option>
                    <?php foreach (['Type 1','Type 2','Gestational','Pre-diabetes','MODY','Other'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType===$t?'selected':''?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-medium);display:block;margin-bottom:5px;">STATUS</label>
                <select name="status" class="form-control" style="width:140px;">
                    <option value="">All Status</option>
                    <?php foreach (['Active','Inactive','Critical','Discharged'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':''?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Search</button>
            <a href="patients.php" class="btn btn-secondary">↺ Reset</a>
        </form>
    </div>
</div>

<!-- PATIENTS TABLE -->
<div class="card">
    <div class="card-header">
        <div>
            <h3>All Patients</h3>
            <p><?= $patients->num_rows ?> patient(s) found</p>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Age/Gender</th>
                    <th>Diabetes Type</th>
                    <th>Doctor</th>
                    <th>Last BS Reading</th>
                    <th>HbA1c</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($patients->num_rows === 0): ?>
            <tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>No patients found</h3>
                    <p>Try adjusting your search or <a href="patient_form.php">add a new patient</a></p>
                </div>
            </td></tr>
            <?php endif; ?>

            <?php while ($p = $patients->fetch_assoc()):
                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $p['full_name']), 0, 2)));
                $avatarClass = $p['gender'] === 'Male' ? 'avatar-male' : ($p['gender'] === 'Female' ? 'avatar-female' : 'avatar-other');
                $age = calculateAge($p['date_of_birth']);
                $statusClass = 'status-' . strtolower($p['status']);
            ?>
            <tr>
                <td>
                    <div class="patient-name-cell">
                        <div class="patient-avatar <?= $avatarClass ?>"><?= $initials ?></div>
                        <div>
                            <div class="name">
                                <a href="patient_detail.php?id=<?= $p['id'] ?>" style="color:var(--text-dark);text-decoration:none;font-weight:600;">
                                    <?= htmlspecialchars($p['full_name']) ?>
                                </a>
                            </div>
                            <div class="pid"><?= htmlspecialchars($p['patient_id']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($p['phone']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="font-weight:600"><?= $age ?> yrs</span>
                    <span style="font-size:12px;color:var(--text-medium);display:block"><?= $p['gender'] ?></span>
                </td>
                <td><span class="badge badge-purple"><?= $p['diabetes_type'] ?></span></td>
                <td style="font-size:13px"><?= htmlspecialchars($p['doctor_name'] ?: '-') ?></td>
                <td>
                    <?php if ($p['last_bs']):
                        $interp = interpretBloodSugar($p['last_bs'], strpos($p['last_type'], 'After') !== false ? 'after' : 'Fasting');
                    ?>
                    <div style="font-weight:800;font-size:16px;color:<?= $interp['color'] ?>"><?= $p['last_bs'] ?> <span style="font-size:11px;font-weight:400;color:var(--text-light)">mg/dL</span></div>
                    <div style="font-size:11px;color:<?= $interp['color'] ?>"><?= $interp['icon'] ?> <?= $interp['label'] ?></div>
                    <div style="font-size:11px;color:var(--text-light)"><?= $p['last_type'] ?> &bull; <?= $p['last_date'] ? date('d M', strtotime($p['last_date'])) : '' ?></div>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:13px;">No reading yet</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['last_hba1c']):
                        $hInterp = interpretHbA1c($p['last_hba1c']);
                    ?>
                    <span style="font-weight:700;color:<?= $hInterp['color'] ?>"><?= $p['last_hba1c'] ?>%</span>
                    <span style="font-size:11px;color:<?= $hInterp['color'] ?>;display:block"><?= $hInterp['label'] ?></span>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:13px;">-</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $statusClass ?>"><?= $p['status'] ?></span></td>
                <td>
                    <div class="action-btns">
                        <a href="patient_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="View Details">👁️</a>
                        <a href="patient_form.php?edit=<?= $p['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Edit">✏️</a>
                        <a href="add_reading.php?pid=<?= $p['id'] ?>" class="btn btn-success btn-sm btn-icon" title="Add Reading">🩸</a>
                        <button onclick="confirmDelete('patients.php?delete=<?= $p['id'] ?>', '<?= htmlspecialchars($p['full_name']) ?>')" class="btn btn-danger btn-sm btn-icon" title="Delete">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
