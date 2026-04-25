<?php
// delete_reading.php
require_once 'config.php';
$db = getDB();
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['pid'])) {
    $id = intval($_GET['id']);
    $pid = intval($_GET['pid']);
    $db->query("DELETE FROM blood_sugar_readings WHERE id=$id");
    header("Location: patient_detail.php?id=$pid&msg=reading_deleted#tab-readings");
} else {
    header("Location: patients.php");
}
exit;
