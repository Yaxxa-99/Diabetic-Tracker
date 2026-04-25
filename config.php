<?php
// config.php - Database Configuration (Auto-configured by installer)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'diabetic_tracker');
define('APP_NAME', 'DiaCare Pro');
define('APP_VERSION', '2.0');
define('HOSPITAL_NAME', 'Diabetic Care Unit');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="color:red;padding:30px;font-family:sans-serif;max-width:600px;margin:40px auto">
                <h2>&#9888; Database Connection Error</h2>
                <p>Could not connect to MySQL. Please run <a href="setup.php">setup.php</a> again.</p>
                <p>Error: ' . htmlspecialchars($conn->connect_error) . '</p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function interpretBloodSugar($value, $type) {
    if (strpos($type, 'Fasting') !== false || strpos($type, 'Before') !== false || $type === 'Random') {
        if ($value < 70) return ['level'=>'danger','label'=>'Hypoglycemia','color'=>'#e74c3c','icon'=>'&#11015;'];
        if ($value <= 99) return ['level'=>'normal','label'=>'Normal','color'=>'#27ae60','icon'=>'&#9989;'];
        if ($value <= 125) return ['level'=>'warning','label'=>'Pre-diabetic','color'=>'#f39c12','icon'=>'&#9888;'];
        if ($value <= 180) return ['level'=>'high','label'=>'Diabetic Range','color'=>'#e67e22','icon'=>'&#128310;'];
        return ['level'=>'critical','label'=>'Critical High','color'=>'#c0392b','icon'=>'&#128680;'];
    } else {
        if ($value < 70) return ['level'=>'danger','label'=>'Hypoglycemia','color'=>'#e74c3c','icon'=>'&#11015;'];
        if ($value <= 139) return ['level'=>'normal','label'=>'Normal','color'=>'#27ae60','icon'=>'&#9989;'];
        if ($value <= 199) return ['level'=>'warning','label'=>'Pre-diabetic','color'=>'#f39c12','icon'=>'&#9888;'];
        if ($value <= 250) return ['level'=>'high','label'=>'Diabetic Range','color'=>'#e67e22','icon'=>'&#128310;'];
        return ['level'=>'critical','label'=>'Critical High','color'=>'#c0392b','icon'=>'&#128680;'];
    }
}

function interpretHbA1c($value) {
    if ($value < 5.7) return ['label'=>'Normal','color'=>'#27ae60'];
    if ($value < 6.5) return ['label'=>'Pre-diabetic','color'=>'#f39c12'];
    if ($value < 8.0) return ['label'=>'Controlled','color'=>'#e67e22'];
    return ['label'=>'Uncontrolled','color'=>'#c0392b'];
}

function generatePatientID() {
    $db = getDB();
    $year = date('Y');
    $result = $db->query("SELECT COUNT(*) as cnt FROM patients WHERE patient_id LIKE 'DM-$year-%'");
    $row = $result->fetch_assoc();
    $num = str_pad($row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return "DM-$year-$num";
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function calculateAge($dob) {
    $birthdate = new DateTime($dob);
    $today = new DateTime();
    return $birthdate->diff($today)->y;
}
?>