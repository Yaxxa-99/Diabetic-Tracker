<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DiaCare Pro — Setup Installer</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.installer { background: white; border-radius: 20px; width: 100%; max-width: 580px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.4); }
.header { background: linear-gradient(135deg, #1e3a5f, #2563eb); color: white; padding: 36px 36px 28px; }
.header .logo { font-size: 40px; margin-bottom: 10px; }
.header h1 { font-size: 26px; font-weight: 800; }
.header p { font-size: 14px; opacity: 0.8; margin-top: 5px; }
.body { padding: 32px 36px; }
.step { display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
.step:last-child { border-bottom: none; }
.step-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.step-icon.wait { background: #f1f5f9; }
.step-icon.ok { background: #d1fae5; }
.step-icon.err { background: #fee2e2; }
.step-info h3 { font-size: 14px; font-weight: 700; color: #0f172a; }
.step-info p { font-size: 12.5px; color: #64748b; margin-top: 3px; }
.step-info p.ok { color: #059669; font-weight: 600; }
.step-info p.err { color: #dc2626; font-weight: 600; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }
.form-control { width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; outline: none; transition: border-color 0.2s; }
.form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.btn { display: block; width: 100%; padding: 13px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.btn-install { background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; }
.btn-install:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.3); }
.btn-install:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.btn-go { background: linear-gradient(135deg, #059669, #10b981); color: white; margin-top: 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; }
.alert { padding: 14px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
.alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #2563eb; }
.alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
.alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #059669; }
.note { font-size: 12px; color: #94a3b8; text-align: center; margin-top: 14px; }
.divider { height: 1px; background: #f1f5f9; margin: 20px 0; }
.progress-wrap { display: none; }
.progress-bar-outer { background: #f1f5f9; border-radius: 99px; height: 8px; margin: 16px 0 8px; overflow: hidden; }
.progress-bar-inner { height: 100%; background: linear-gradient(90deg, #2563eb, #7c3aed); border-radius: 99px; width: 0; transition: width 0.4s ease; }
.progress-label { font-size: 12px; color: #64748b; text-align: center; }
pre { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; font-size: 11.5px; color: #475569; margin-top: 10px; max-height: 160px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
</style>
</head>
<body>

<?php

$DB_HOST = 'localhost';
$DB_USER = isset($_POST['db_user']) ? trim($_POST['db_user']) : 'root';
$DB_PASS = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
$DB_NAME = isset($_POST['db_name']) ? trim($_POST['db_name']) : 'diabetic_tracker';

$installing = isset($_POST['install']);
$results = [];
$allOk = false;

if ($installing) {
    // Step 1: Connect to MySQL
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS);
    if ($conn->connect_error) {
        $results[] = ['icon'=>'❌','status'=>'err','title'=>'MySQL Connection Failed','msg'=>'Error: ' . $conn->connect_error . '. Check your WAMP is running and credentials are correct.'];
    } else {
        $results[] = ['icon'=>'✅','status'=>'ok','title'=>'MySQL Connected','msg'=>'Successfully connected to MySQL server.'];
        $conn->set_charset('utf8mb4');

        // Step 2: Create Database
        if ($conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            $results[] = ['icon'=>'✅','status'=>'ok','title'=>'Database Created','msg'=>"Database '$DB_NAME' is ready."];
        } else {
            $results[] = ['icon'=>'❌','status'=>'err','title'=>'Database Creation Failed','msg'=>$conn->error];
        }

        $conn->select_db($DB_NAME);

        // Step 3: Create Tables
        $tables = [

"CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` VARCHAR(20) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `gender` ENUM('Male','Female','Other') NOT NULL,
    `phone` VARCHAR(20),
    `email` VARCHAR(100),
    `address` TEXT,
    `diabetes_type` ENUM('Type 1','Type 2','Gestational','Pre-diabetes','MODY','Other') NOT NULL,
    `diagnosis_date` DATE,
    `doctor_name` VARCHAR(100),
    `emergency_contact` VARCHAR(100),
    `emergency_phone` VARCHAR(20),
    `blood_group` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    `weight` DECIMAL(5,2),
    `height` DECIMAL(5,2),
    `allergies` TEXT,
    `complications` TEXT,
    `insurance_number` VARCHAR(50),
    `status` ENUM('Active','Inactive','Critical','Discharged') DEFAULT 'Active',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `blood_sugar_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `reading_type` ENUM('Fasting','Before Breakfast','After Breakfast','Before Lunch','After Lunch','Before Dinner','After Dinner','Bedtime','Random') NOT NULL,
    `blood_sugar_value` DECIMAL(6,2) NOT NULL,
    `unit` ENUM('mg/dL','mmol/L') DEFAULT 'mg/dL',
    `reading_date` DATE NOT NULL,
    `reading_time` TIME NOT NULL,
    `notes` TEXT,
    `recorded_by` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `hba1c_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `hba1c_value` DECIMAL(4,2) NOT NULL,
    `test_date` DATE NOT NULL,
    `lab_name` VARCHAR(100),
    `notes` TEXT,
    `recorded_by` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `medications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `medication_name` VARCHAR(100) NOT NULL,
    `dosage` VARCHAR(50),
    `frequency` VARCHAR(50),
    `start_date` DATE,
    `end_date` DATE,
    `prescribed_by` VARCHAR(100),
    `notes` TEXT,
    `status` ENUM('Active','Completed','Discontinued') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `appointment_date` DATETIME NOT NULL,
    `doctor_name` VARCHAR(100),
    `purpose` VARCHAR(200),
    `status` ENUM('Scheduled','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"

        ];

        $tableNames = ['patients','blood_sugar_readings','hba1c_records','medications','appointments'];
        $tableOk = true;
        foreach ($tables as $i => $sql) {
            if (!$conn->query($sql)) {
                $results[] = ['icon'=>'❌','status'=>'err','title'=>"Table '{$tableNames[$i]}' Failed",'msg'=>$conn->error];
                $tableOk = false;
            }
        }
        if ($tableOk) {
            $results[] = ['icon'=>'✅','status'=>'ok','title'=>'All Tables Created (5 tables)','msg'=>'patients, blood_sugar_readings, hba1c_records, medications, appointments'];
        }

        // Step 4: Insert Sample Data
        $check = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
        if ($check == 0) {
            $sampleSQL = "INSERT INTO `patients` (`patient_id`,`full_name`,`date_of_birth`,`gender`,`phone`,`email`,`address`,`diabetes_type`,`diagnosis_date`,`doctor_name`,`emergency_contact`,`emergency_phone`,`blood_group`,`weight`,`height`,`status`) VALUES
('DM-2024-001','Kamal Perera','1975-04-12','Male','+94771234567','kamal@email.com','45 Main Street, Colombo 03','Type 2','2020-01-15','Dr. Wijesinghe','Nimal Perera','+94771234568','O+',78.5,172.0,'Active'),
('DM-2024-002','Priya Fernando','1988-09-23','Female','+94772345678','priya@email.com','12 Lake Road, Kandy','Type 1','2010-06-20','Dr. Silva','Rajan Fernando','+94772345679','B+',62.0,158.0,'Active'),
('DM-2024-003','Ananda Rajapaksa','1960-12-05','Male','+94773456789','ananda@email.com','78 Temple Lane, Galle','Type 2','2015-03-10','Dr. Perera','Kamala Rajapaksa','+94773456790','A+',85.0,168.0,'Critical')";
            $conn->query($sampleSQL);

            $conn->query("INSERT INTO `blood_sugar_readings` (`patient_id`,`reading_type`,`blood_sugar_value`,`reading_date`,`reading_time`,`recorded_by`) VALUES
(1,'Fasting',126,'2025-01-10','07:00:00','Nurse Dilani'),
(1,'After Breakfast',185,'2025-01-10','09:30:00','Nurse Dilani'),
(1,'Before Lunch',145,'2025-01-10','12:00:00','Nurse Dilani'),
(1,'After Lunch',210,'2025-01-10','14:00:00','Nurse Dilani'),
(1,'Fasting',118,'2025-01-15','07:15:00','Nurse Dilani'),
(1,'After Breakfast',170,'2025-01-15','09:45:00','Nurse Dilani'),
(1,'Fasting',132,'2025-01-20','07:00:00','Nurse Dilani'),
(1,'After Breakfast',190,'2025-01-20','09:30:00','Nurse Dilani'),
(2,'Fasting',95,'2025-01-10','07:00:00','Nurse Kamala'),
(2,'After Breakfast',140,'2025-01-10','09:30:00','Nurse Kamala'),
(2,'Fasting',102,'2025-01-15','07:00:00','Nurse Kamala'),
(3,'Fasting',198,'2025-01-10','07:00:00','Nurse Dilani'),
(3,'After Breakfast',285,'2025-01-10','09:30:00','Nurse Dilani')");

            $conn->query("INSERT INTO `hba1c_records` (`patient_id`,`hba1c_value`,`test_date`,`lab_name`,`recorded_by`) VALUES
(1,7.8,'2024-10-01','City Lab','Dr. Wijesinghe'),
(1,7.2,'2025-01-01','City Lab','Dr. Wijesinghe'),
(2,6.5,'2024-10-01','Kandy Lab','Dr. Silva'),
(2,6.8,'2025-01-01','Kandy Lab','Dr. Silva'),
(3,9.5,'2024-10-01','Galle Lab','Dr. Perera'),
(3,10.2,'2025-01-01','Galle Lab','Dr. Perera')");

            $conn->query("INSERT INTO `medications` (`patient_id`,`medication_name`,`dosage`,`frequency`,`start_date`,`prescribed_by`,`status`) VALUES
(1,'Metformin','500mg','Twice daily','2020-02-01','Dr. Wijesinghe','Active'),
(1,'Glipizide','5mg','Once daily','2020-02-01','Dr. Wijesinghe','Active'),
(2,'Insulin Glargine','10 units','At bedtime','2010-07-01','Dr. Silva','Active'),
(3,'Metformin','1000mg','Twice daily','2015-04-01','Dr. Perera','Active'),
(3,'Insulin Regular','8 units','Before each meal','2022-01-01','Dr. Perera','Active')");

            $results[] = ['icon'=>'✅','status'=>'ok','title'=>'Sample Data Inserted','msg'=>'3 sample patients, blood sugar readings, HbA1c records and medications added.'];
        } else {
            $results[] = ['icon'=>'ℹ️','status'=>'ok','title'=>'Sample Data Skipped','msg'=>'Database already has patient records. No sample data inserted.'];
        }

        // Step 5: Update config.php
        $configPath = __DIR__ . '/config.php';
        $configContent = "<?php
// config.php - Database Configuration (Auto-configured by installer)
define('DB_HOST', '$DB_HOST');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');
define('DB_NAME', '$DB_NAME');
define('APP_NAME', 'DiaCare Pro');
define('APP_VERSION', '2.0');
define('HOSPITAL_NAME', 'Diabetic Care Unit');

function getDB() {
    static \$conn = null;
    if (\$conn === null) {
        \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (\$conn->connect_error) {
            die('<div style=\"color:red;padding:30px;font-family:sans-serif;max-width:600px;margin:40px auto\">
                <h2>&#9888; Database Connection Error</h2>
                <p>Could not connect to MySQL. Please run <a href=\"setup.php\">setup.php</a> again.</p>
                <p>Error: ' . htmlspecialchars(\$conn->connect_error) . '</p>
            </div>');
        }
        \$conn->set_charset('utf8mb4');
    }
    return \$conn;
}

function interpretBloodSugar(\$value, \$type) {
    if (strpos(\$type, 'Fasting') !== false || strpos(\$type, 'Before') !== false || \$type === 'Random') {
        if (\$value < 70) return ['level'=>'danger','label'=>'Hypoglycemia','color'=>'#e74c3c','icon'=>'&#11015;'];
        if (\$value <= 99) return ['level'=>'normal','label'=>'Normal','color'=>'#27ae60','icon'=>'&#9989;'];
        if (\$value <= 125) return ['level'=>'warning','label'=>'Pre-diabetic','color'=>'#f39c12','icon'=>'&#9888;'];
        if (\$value <= 180) return ['level'=>'high','label'=>'Diabetic Range','color'=>'#e67e22','icon'=>'&#128310;'];
        return ['level'=>'critical','label'=>'Critical High','color'=>'#c0392b','icon'=>'&#128680;'];
    } else {
        if (\$value < 70) return ['level'=>'danger','label'=>'Hypoglycemia','color'=>'#e74c3c','icon'=>'&#11015;'];
        if (\$value <= 139) return ['level'=>'normal','label'=>'Normal','color'=>'#27ae60','icon'=>'&#9989;'];
        if (\$value <= 199) return ['level'=>'warning','label'=>'Pre-diabetic','color'=>'#f39c12','icon'=>'&#9888;'];
        if (\$value <= 250) return ['level'=>'high','label'=>'Diabetic Range','color'=>'#e67e22','icon'=>'&#128310;'];
        return ['level'=>'critical','label'=>'Critical High','color'=>'#c0392b','icon'=>'&#128680;'];
    }
}

function interpretHbA1c(\$value) {
    if (\$value < 5.7) return ['label'=>'Normal','color'=>'#27ae60'];
    if (\$value < 6.5) return ['label'=>'Pre-diabetic','color'=>'#f39c12'];
    if (\$value < 8.0) return ['label'=>'Controlled','color'=>'#e67e22'];
    return ['label'=>'Uncontrolled','color'=>'#c0392b'];
}

function generatePatientID() {
    \$db = getDB();
    \$year = date('Y');
    \$result = \$db->query(\"SELECT COUNT(*) as cnt FROM patients WHERE patient_id LIKE 'DM-\$year-%'\");
    \$row = \$result->fetch_assoc();
    \$num = str_pad(\$row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return \"DM-\$year-\$num\";
}

function sanitize(\$data) {
    return htmlspecialchars(strip_tags(trim(\$data)));
}

function calculateAge(\$dob) {
    \$birthdate = new DateTime(\$dob);
    \$today = new DateTime();
    return \$birthdate->diff(\$today)->y;
}
?>";

        if (file_put_contents($configPath, $configContent) !== false) {
            $results[] = ['icon'=>'✅','status'=>'ok','title'=>'config.php Updated','msg'=>'Database settings saved to config.php automatically.'];
            $allOk = true;
        } else {
            $results[] = ['icon'=>'⚠️','status'=>'err','title'=>'config.php Write Warning','msg'=>'Could not auto-update config.php. Please manually update DB_USER and DB_PASS in config.php.'];
            $allOk = true; // DB is set up, just config write failed
        }

        $conn->close();
    }
}
?>

<div class="installer">
    <div class="header">
        <div class="logo">🩺</div>
        <h1>DiaCare Pro — Setup</h1>
        <p>Diabetic Care Management System Installer</p>
    </div>

    <div class="body">

        <?php if (!$installing): ?>
        <div class="alert alert-info">
            ℹ️ This will create the <strong>diabetic_tracker</strong> database, all tables, and insert sample data automatically.
        </div>

        <form method="POST">
            <input type="hidden" name="install" value="1">
            <div class="form-grid">
                <div class="form-group">
                    <label>MySQL Username</label>
                    <input type="text" name="db_user" class="form-control" value="root" required placeholder="root">
                </div>
                <div class="form-group">
                    <label>MySQL Password</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="(blank for WAMP default)">
                    <small style="font-size:11px;color:#94a3b8;margin-top:4px;display:block">Leave blank if using default WAMP</small>
                </div>
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" class="form-control" value="diabetic_tracker" required>
            </div>

            <div class="divider"></div>

            <div class="step">
                <div class="step-icon wait">1️⃣</div>
                <div class="step-info"><h3>Connect to MySQL</h3><p>Verify database credentials</p></div>
            </div>
            <div class="step">
                <div class="step-icon wait">2️⃣</div>
                <div class="step-info"><h3>Create Database</h3><p>Create 'diabetic_tracker' database</p></div>
            </div>
            <div class="step">
                <div class="step-icon wait">3️⃣</div>
                <div class="step-info"><h3>Create Tables (5 tables)</h3><p>patients, blood_sugar_readings, hba1c_records, medications, appointments</p></div>
            </div>
            <div class="step">
                <div class="step-icon wait">4️⃣</div>
                <div class="step-info"><h3>Insert Sample Data</h3><p>3 sample patients with readings</p></div>
            </div>
            <div class="step">
                <div class="step-icon wait">5️⃣</div>
                <div class="step-info"><h3>Update config.php</h3><p>Save settings automatically</p></div>
            </div>

            <div class="divider"></div>

            <button type="submit" class="btn btn-install" id="installBtn" onclick="this.disabled=true;this.textContent='⏳ Installing...';this.form.submit();">
                🚀 Install DiaCare Pro Now
            </button>
        </form>

        <?php else: ?>

        <?php
        $hasError = false;
        foreach ($results as $r) { if ($r['status'] === 'err') $hasError = true; }
        ?>

        <?php if ($allOk && !$hasError): ?>
        <div class="alert alert-success">
            🎉 <strong>Installation Complete!</strong> DiaCare Pro is ready to use.
        </div>
        <?php elseif ($hasError): ?>
        <div class="alert alert-error">
            ❌ <strong>Installation had errors.</strong> Check the steps below and fix issues, then try again.
        </div>
        <?php endif; ?>

        <?php foreach ($results as $r): ?>
        <div class="step">
            <div class="step-icon <?= $r['status'] ?>"><?= $r['icon'] ?></div>
            <div class="step-info">
                <h3><?= htmlspecialchars($r['title']) ?></h3>
                <p class="<?= $r['status'] ?>"><?= htmlspecialchars($r['msg']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($allOk && !$hasError): ?>
        <div class="divider"></div>
        <a href="index.php" class="btn btn-go">🏥 Open DiaCare Pro Dashboard →</a>
        <p class="note" style="margin-top:16px">You can delete <strong>setup.php</strong> after installation for security.</p>
        <?php elseif ($hasError): ?>
        <div class="divider"></div>
        <form method="POST">
            <input type="hidden" name="install" value="1">
            <input type="hidden" name="db_user" value="<?= htmlspecialchars($DB_USER) ?>">
            <input type="hidden" name="db_pass" value="<?= htmlspecialchars($DB_PASS) ?>">
            <input type="hidden" name="db_name" value="<?= htmlspecialchars($DB_NAME) ?>">

            <div style="background:#fef9f0;border:1px solid #fed7aa;border-radius:10px;padding:14px;margin-bottom:16px;">
                <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px">🔧 Common Fixes:</div>
                <ul style="font-size:12.5px;color:#78350f;padding-left:18px;line-height:1.8">
                    <li>Make sure <strong>WAMP is running</strong> (green icon in taskbar)</li>
                    <li>Click the WAMP icon → <strong>Start All Services</strong></li>
                    <li>Default WAMP MySQL user is <strong>root</strong> with <strong>no password</strong></li>
                    <li>Try opening <a href="http://localhost/phpmyadmin" target="_blank" style="color:#2563eb">phpMyAdmin</a> to verify MySQL works</li>
                    <li>Check if MySQL port 3306 is not blocked</li>
                </ul>
            </div>

            <button type="submit" class="btn btn-install">🔄 Try Again</button>
        </form>
        <a href="setup.php" style="display:block;text-align:center;margin-top:12px;font-size:13px;color:#64748b">← Change Settings</a>
        <?php endif; ?>

        <?php endif; ?>

        <p class="note">DiaCare Pro v2.0 — Hospital Diabetic Care System</p>
    </div>
</div>

</body>
</html>
