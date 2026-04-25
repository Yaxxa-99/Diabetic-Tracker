<?php
// delete_hba1c.php
require_once 'config.php';
$db = getDB();
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['pid'])) {
    $id = intval($_GET['id']);
    $pid = intval($_GET['pid']);
    $db->query("DELETE FROM hba1c_records WHERE id=$id");
    header("Location: patient_detail.php?id=$pid#tab-hba1c");
} else {
    header("Location: patients.php");
}
exit;
