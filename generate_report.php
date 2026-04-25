<?php
require_once 'config.php';
$db = getDB();
$page_title = 'Generate Report';

$patientId = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$allPatients = $db->query("SELECT id, patient_id, full_name FROM patients ORDER BY full_name");
$patList = [];
while ($r = $allPatients->fetch_assoc()) $patList[] = $r;

$dateFrom = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo   = isset($_GET['to'])   ? $_GET['to']   : date('Y-m-d');

$generating = isset($_GET['generate']);
$reportData = null;
$patient    = null;

if ($generating && $patientId) {
    $result  = $db->query("SELECT * FROM patients WHERE id=$patientId");
    $patient = $result->fetch_assoc();

    if ($patient) {
        $readings = $db->query("SELECT * FROM blood_sugar_readings
            WHERE patient_id=$patientId AND reading_date BETWEEN '$dateFrom' AND '$dateTo'
            ORDER BY reading_date ASC, reading_time ASC");
        $allReadings = [];
        while ($r = $readings->fetch_assoc()) $allReadings[] = $r;
        $allReadingsDesc = array_reverse($allReadings);

        $hba1cRecs = $db->query("SELECT * FROM hba1c_records WHERE patient_id=$patientId ORDER BY test_date DESC");
        $allHba1c  = [];
        while ($r = $hba1cRecs->fetch_assoc()) $allHba1c[] = $r;

        $meds = $db->query("SELECT * FROM medications WHERE patient_id=$patientId AND status='Active'");
        $allMeds = [];
        while ($r = $meds->fetch_assoc()) $allMeds[] = $r;

        $statsQ = $db->query("SELECT
            AVG(blood_sugar_value) as avg_bs,
            MAX(blood_sugar_value) as max_bs,
            MIN(blood_sugar_value) as min_bs,
            COUNT(*)               as total,
            SUM(CASE WHEN blood_sugar_value < 70              THEN 1 ELSE 0 END) as low_cnt,
            SUM(CASE WHEN blood_sugar_value BETWEEN 70 AND 180 THEN 1 ELSE 0 END) as normal_cnt,
            SUM(CASE WHEN blood_sugar_value > 180             THEN 1 ELSE 0 END) as high_cnt
            FROM blood_sugar_readings
            WHERE patient_id=$patientId AND reading_date BETWEEN '$dateFrom' AND '$dateTo'");
        $stats = $statsQ->fetch_assoc();

        // Daily averages for chart
        $chartQ = $db->query("SELECT reading_date,
            ROUND(AVG(blood_sugar_value),1) as avg_val,
            ROUND(MAX(blood_sugar_value),1) as max_val,
            ROUND(MIN(blood_sugar_value),1) as min_val
            FROM blood_sugar_readings
            WHERE patient_id=$patientId AND reading_date BETWEEN '$dateFrom' AND '$dateTo'
            GROUP BY reading_date ORDER BY reading_date ASC");

        $chartDates = $chartAvg = $chartMax = $chartMin = [];
        while ($r = $chartQ->fetch_assoc()) {
            $chartDates[] = date('d/m', strtotime($r['reading_date']));
            $chartAvg[]   = (float)$r['avg_val'];
            $chartMax[]   = (float)$r['max_val'];
            $chartMin[]   = (float)$r['min_val'];
        }

        // Smart Y-axis: pad around actual data range
        $yMin = 40; $yMax = 300;
        if (!empty($chartAvg)) {
            $allVals = array_merge($chartMin, $chartAvg, $chartMax);
            $yMin = max(40,  floor((min($allVals) - 30) / 10) * 10);
            $yMax = min(600, ceil( (max($allVals) + 40) / 10) * 10);
        }

        $reportData = compact('allReadings','allReadingsDesc','allHba1c','allMeds',
                              'stats','chartDates','chartAvg','chartMax','chartMin','yMin','yMax');
    }
}

if (!$generating) { require_once 'header.php'; }
?>

<?php if (!$generating): ?>
<div class="page-header">
    <div class="page-header-left">
        <h1>🖨️ Generate Patient Report</h1>
        <p>Create a printable medical report</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Report Settings</h3></div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="generate" value="1">
                <div class="form-group mb-4">
                    <label class="form-label">Select Patient <span class="req">*</span></label>
                    <select name="pid" class="form-control" required>
                        <option value="">— Choose patient —</option>
                        <?php foreach ($patList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patientId==$p['id']?'selected':'' ?>>
                            <?= htmlspecialchars($p['patient_id']) ?> — <?= htmlspecialchars($p['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row cols-2 mb-3">
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
                    <?php foreach ([7=>'7 Days',30=>'30 Days',90=>'3 Months',180=>'6 Months'] as $d=>$l): ?>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="setRange(<?= $d ?>)"><?= $l ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">📄 Generate Report</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>📋 Report Includes</h3></div>
        <div class="card-body">
            <div style="display:grid;gap:8px;">
                <?php foreach ([
                    ['👤','Patient Profile & Demographics'],
                    ['🩺','Diabetes Type & Medical History'],
                    ['📊','Statistics (Avg, Min, Max, High count)'],
                    ['📈','Blood Sugar Trend Chart (fixed size)'],
                    ['🩸','Blood Sugar Reading History Table'],
                    ['🧪','HbA1c Test History'],
                    ['💊','Active Medications List'],
                    ['💡','Clinical Assessment & Recommendations'],
                    ['✍️','Doctor & Patient Signature Fields'],
                ] as $item): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:9px 13px;background:var(--surface-2);border-radius:9px;">
                    <span style="font-size:17px"><?= $item[0] ?></span>
                    <span style="font-size:13px;font-weight:500"><?= $item[1] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function setRange(days) {
    const to = new Date(), from = new Date();
    from.setDate(from.getDate() - days);
    document.querySelector('[name="from"]').value = from.toISOString().split('T')[0];
    document.querySelector('[name="to"]').value   = to.toISOString().split('T')[0];
}
</script>

<?php require_once 'footer.php'; ?>

<?php else: /* ====== PRINTABLE REPORT ====== */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diabetic Report — <?= htmlspecialchars($patient['full_name']) ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,Helvetica,sans-serif; color:#1a1a1a; background:#f0f4f8; font-size:11pt; line-height:1.4; }

/* TOOLBAR */
.toolbar { background:#0f172a; padding:11px 22px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
.toolbar-title { color:white; font-size:14px; font-weight:700; flex:1; }
.toolbar a, .toolbar button { color:#94a3b8; text-decoration:none; font-size:13px; padding:7px 14px; border:1px solid #334155; border-radius:7px; background:none; cursor:pointer; font-family:Arial,sans-serif; }
.tb-print { background:#2563eb !important; color:white !important; border-color:#2563eb !important; font-weight:700; padding:8px 20px !important; }

/* PAGE */
.report-page { max-width:860px; margin:20px auto; background:white; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.12); overflow:hidden; }
.report-body { padding:26px 30px; }

/* HEADER */
.rpt-hdr { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:14px; margin-bottom:18px; border-bottom:3px solid #2563eb; }
.rpt-hdr h1 { font-size:19px; color:#1e40af; font-weight:800; }
.rpt-hdr p  { font-size:11px; color:#64748b; margin-top:2px; }
.rpt-meta { text-align:right; }
.rpt-meta .rid { font-size:13px; font-weight:700; color:#1e40af; }
.rpt-meta p { font-size:11px; color:#64748b; margin-top:2px; }

/* PATIENT BANNER */
.pat-banner { background:linear-gradient(135deg,#0f172a,#1e3a5f); color:white; border-radius:10px; padding:16px 18px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.pat-banner h2 { font-size:19px; font-weight:800; }
.pat-id { font-size:12px; color:#94a3b8; margin-top:2px; }
.pat-meta { display:flex; flex-wrap:wrap; gap:8px; margin-top:9px; }
.pat-meta span { font-size:11px; color:#cbd5e1; background:rgba(255,255,255,.08); padding:3px 9px; border-radius:12px; }
.pat-status { padding:5px 13px; border-radius:13px; font-size:11px; font-weight:700; white-space:nowrap; flex-shrink:0; }
.s-active   { background:#d1fae5; color:#065f46; }
.s-critical { background:#fee2e2; color:#991b1b; }
.s-inactive { background:#f1f5f9; color:#475569; }

/* SECTION */
.sec { margin-bottom:20px; page-break-inside:avoid; }
.sec-title { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#1e40af; border-left:4px solid #2563eb; padding-left:9px; margin-bottom:11px; }

/* STATS */
.stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:9px; margin-bottom:12px; }
.stat-box { border:2px solid; border-radius:8px; padding:10px 8px; text-align:center; }
.stat-box .sv { font-size:21px; font-weight:900; line-height:1; }
.stat-box .sl { font-size:10px; color:#64748b; margin-top:3px; line-height:1.3; }

/* =====================================================
   CHART — THE FIX
   chart-outer: explicit fixed pixel height = no overflow
   chart-canvas-wrap: absolutely fills that space
   ===================================================== */
.chart-outer {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px 8px;
    margin-top: 8px;
    /* ✅ Fixed height: chart will NEVER go beyond this */
    height: 210px;
    position: relative;
    overflow: hidden;           /* extra safety — clips anything that tries to escape */
}
.chart-title {
    font-size: 10px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
}
/* canvas wrapper takes remaining space after the title */
.chart-canvas-wrap {
    position: absolute;
    top: 32px;          /* title height + padding */
    left: 10px;
    right: 10px;
    bottom: 8px;
}

/* TABLES */
table { width:100%; border-collapse:collapse; font-size:10.5px; margin-top:6px; }
thead tr { background:#1e40af; color:white; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
thead th { padding:7px 9px; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.3px; white-space:nowrap; }
tbody tr:nth-child(even) { background:#f8fafc; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
tbody td { padding:6px 9px; border-bottom:1px solid #e2e8f0; vertical-align:middle; }
tbody tr:last-child td { border-bottom:none; }

/* STATUS COLOURS */
.c-ok   { color:#059669; font-weight:700; }
.c-warn { color:#d97706; font-weight:700; }
.c-high { color:#ea580c; font-weight:700; }
.c-bad  { color:#dc2626; font-weight:700; }
.c-low  { color:#2563eb; font-weight:700; }

/* INFO GRID */
.info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
.info-item { background:#f8fafc; border-radius:7px; padding:8px 11px; }
.info-item .lbl { font-size:9.5px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.info-item .val { font-size:12px; font-weight:600; color:#0f172a; }

/* RECOMMENDATIONS */
.rec-box { background:#eff6ff; border-left:4px solid #2563eb; border-radius:8px; padding:12px 14px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
.rec-box p { font-size:11.5px; margin-bottom:5px; }
.rec-box p:last-child { margin-bottom:0; }

/* SIGNATURE */
.sig-grid { display:grid; grid-template-columns:1fr 1fr; gap:26px; margin-top:26px; }
.sig-box { border-top:2px solid #334155; padding-top:7px; }
.sig-box p { font-size:10px; color:#64748b; margin-bottom:4px; }
.sig-line  { height:38px; }

/* FOOTER */
.rpt-ftr { border-top:1px solid #e2e8f0; margin-top:20px; padding-top:9px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px; }
.rpt-ftr p { font-size:9.5px; color:#94a3b8; }

/* ===== PRINT ===== */
@media print {
    body { background:white !important; font-size:10pt; }
    .toolbar { display:none !important; }
    .report-page { margin:0 !important; box-shadow:none !important; border-radius:0 !important; }
    .report-body { padding:12px 18px; }
    /* chart stays exactly 210px on paper */
    .chart-outer { height:210px !important; overflow:hidden !important; }
    .sec { page-break-inside:avoid; }
}
</style>
</head>
<body>

<div class="toolbar">
    <span class="toolbar-title">📄 <?= htmlspecialchars($patient['full_name']) ?> — Diabetic Care Report</span>
    <a href="patient_detail.php?id=<?= $patient['id'] ?>">← Back</a>
    <a href="generate_report.php">⚙️ Settings</a>
    <button class="tb-print" onclick="window.print()">🖨️ Print / Save PDF</button>
</div>

<div class="report-page"><div class="report-body">

    <!-- HEADER -->
    <div class="rpt-hdr">
        <div>
            <h1>🩺 DiaCare Pro</h1>
            <p>Diabetic Care Management System</p>
            <p><?= HOSPITAL_NAME ?></p>
        </div>
        <div class="rpt-meta">
            <div class="rid">RPT-<?= date('Ymd') ?>-<?= str_pad($patient['id'],4,'0',STR_PAD_LEFT) ?></div>
            <p>Generated: <?= date('d F Y, H:i') ?></p>
            <p>Period: <?= date('d M Y',strtotime($dateFrom)) ?> — <?= date('d M Y',strtotime($dateTo)) ?></p>
            <p>Doctor: <?= htmlspecialchars($patient['doctor_name'] ?: 'N/A') ?></p>
        </div>
    </div>

    <!-- PATIENT BANNER -->
    <div class="pat-banner">
        <div>
            <h2><?= htmlspecialchars($patient['full_name']) ?></h2>
            <div class="pat-id">ID: <?= $patient['patient_id'] ?> &nbsp;·&nbsp; <?= $patient['diabetes_type'] ?> Diabetes</div>
            <div class="pat-meta">
                <span>Age: <?= calculateAge($patient['date_of_birth']) ?> yrs</span>
                <span><?= $patient['gender'] ?></span>
                <span>Blood Group: <?= $patient['blood_group'] ?></span>
                <?php if ($patient['phone']): ?><span><?= htmlspecialchars($patient['phone']) ?></span><?php endif; ?>
                <?php if ($patient['weight']): ?><span><?= $patient['weight'] ?> kg</span><?php endif; ?>
            </div>
        </div>
        <?php $sc = $patient['status']==='Active'?'s-active':($patient['status']==='Critical'?'s-critical':'s-inactive'); ?>
        <span class="pat-status <?= $sc ?>"><?= $patient['status'] ?></span>
    </div>

    <!-- BLOOD SUGAR STATISTICS + CHART -->
    <?php if ($reportData && $reportData['stats']['total'] > 0):
        $stats = $reportData['stats'];
        $ai    = interpretBloodSugar($stats['avg_bs'], 'Fasting');
    ?>
    <div class="sec">
        <div class="sec-title">📊 Blood Sugar Statistics — <?= date('d M',strtotime($dateFrom)) ?> to <?= date('d M Y',strtotime($dateTo)) ?></div>

        <div class="stats-row">
            <div class="stat-box" style="border-color:#3b82f640;background:#eff6ff">
                <div class="sv c-low"><?= $stats['total'] ?></div><div class="sl">Total Readings</div>
            </div>
            <div class="stat-box" style="border-color:<?= $ai['color'] ?>40;background:<?= $ai['color'] ?>10">
                <div class="sv" style="color:<?= $ai['color'] ?>"><?= round($stats['avg_bs'],1) ?></div><div class="sl">Average mg/dL</div>
            </div>
            <div class="stat-box" style="border-color:#dc262640;background:#fee2e210">
                <div class="sv c-bad"><?= round($stats['max_bs'],1) ?></div><div class="sl">Maximum mg/dL</div>
            </div>
            <div class="stat-box" style="border-color:#05966940;background:#d1fae510">
                <div class="sv c-ok"><?= round($stats['min_bs'],1) ?></div><div class="sl">Minimum mg/dL</div>
            </div>
            <div class="stat-box" style="border-color:#dc262640;background:#fee2e210">
                <div class="sv c-bad"><?= $stats['high_cnt'] ?></div><div class="sl">High Readings (&gt;180)</div>
            </div>
        </div>

        <!-- CHART with fixed-height container -->
        <?php if (!empty($reportData['chartDates'])): ?>
        <div class="chart-outer">
            <div class="chart-title">Blood Sugar Trend — Daily Average &nbsp;·&nbsp; — — Max &nbsp;·&nbsp; — — Min</div>
            <div class="chart-canvas-wrap">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- READING HISTORY -->
    <?php if (!empty($reportData['allReadingsDesc'])): ?>
    <div class="sec">
        <div class="sec-title">🩸 Blood Sugar Readings (<?= count($reportData['allReadingsDesc']) ?> total)</div>
        <table>
            <thead><tr><th>#</th><th>Date</th><th>Time</th><th>Type</th><th>Value</th><th>Status</th><th>Recorded By</th><th>Notes</th></tr></thead>
            <tbody>
            <?php $n=count($reportData['allReadingsDesc']); foreach ($reportData['allReadingsDesc'] as $i=>$r):
                $ri = interpretBloodSugar($r['blood_sugar_value'],$r['reading_type']);
                $cc = $ri['level']==='normal'?'c-ok':($ri['level']==='danger'?'c-low':($ri['level']==='warning'?'c-warn':($ri['level']==='high'?'c-high':'c-bad')));
            ?>
            <tr>
                <td style="color:#94a3b8"><?= $n-$i ?></td>
                <td><?= date('d M Y',strtotime($r['reading_date'])) ?></td>
                <td><?= date('H:i',strtotime($r['reading_time'])) ?></td>
                <td><?= $r['reading_type'] ?></td>
                <td class="<?= $cc ?>"><?= $r['blood_sugar_value'] ?> mg/dL</td>
                <td><?= $ri['icon'] ?> <?= $ri['label'] ?></td>
                <td><?= htmlspecialchars($r['recorded_by']??'—') ?></td>
                <td><?= htmlspecialchars($r['notes']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- HbA1c -->
    <?php if (!empty($reportData['allHba1c'])): ?>
    <div class="sec">
        <div class="sec-title">🧪 HbA1c Records</div>
        <table>
            <thead><tr><th>Date</th><th>HbA1c</th><th>Status</th><th>Laboratory</th><th>Recorded By</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($reportData['allHba1c'] as $h):
                $hi = interpretHbA1c($h['hba1c_value']);
                $hc = $hi['color']==='#27ae60'?'c-ok':($hi['color']==='#f39c12'?'c-warn':($hi['color']==='#e67e22'?'c-high':'c-bad'));
            ?>
            <tr>
                <td><?= date('d M Y',strtotime($h['test_date'])) ?></td>
                <td class="<?= $hc ?>"><?= $h['hba1c_value'] ?>%</td>
                <td><?= $hi['label'] ?></td>
                <td><?= htmlspecialchars($h['lab_name']??'—') ?></td>
                <td><?= htmlspecialchars($h['recorded_by']??'—') ?></td>
                <td><?= htmlspecialchars($h['notes']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- MEDICATIONS -->
    <?php if (!empty($reportData['allMeds'])): ?>
    <div class="sec">
        <div class="sec-title">💊 Active Medications</div>
        <table>
            <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Start Date</th><th>Prescribed By</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($reportData['allMeds'] as $m): ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($m['medication_name']) ?></td>
                <td><?= htmlspecialchars($m['dosage']??'—') ?></td>
                <td><?= htmlspecialchars($m['frequency']??'—') ?></td>
                <td><?= $m['start_date']?date('d M Y',strtotime($m['start_date'])):'—' ?></td>
                <td><?= htmlspecialchars($m['prescribed_by']??'—') ?></td>
                <td><?= htmlspecialchars($m['notes']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- CLINICAL ASSESSMENT -->
    <div class="sec">
        <div class="sec-title">💡 Clinical Assessment &amp; Recommendations</div>
        <div class="rec-box">
            <?php if ($reportData && $reportData['stats']['total'] > 0):
                $avg=$reportData['stats']['avg_bs'];
                $hp=round($reportData['stats']['high_cnt']/$reportData['stats']['total']*100);
            ?>
            <p><strong>Blood Sugar Control:</strong> Average <?= round($avg,1) ?> mg/dL over the report period.
                <?php if ($avg<=99): ?>✅ Within normal range. Continue current management.
                <?php elseif ($avg<=130): ?>🔷 Reasonably controlled. Maintain medication compliance and dietary plan.
                <?php elseif ($avg<=180): ?>⚠️ Moderately elevated. Review diet, activity, and medication compliance with doctor.
                <?php else: ?>🚨 Significantly elevated. Immediate review of treatment plan is recommended.
                <?php endif; ?>
            </p>
            <?php if ($hp>20): ?>
            <p>⚠️ <strong>High Reading Alert:</strong> <?= $hp ?>% of readings exceeded 180 mg/dL. Consider medication review with treating physician.</p>
            <?php endif; ?>
            <?php if (!empty($reportData['allHba1c'])): $lh=$reportData['allHba1c'][0]['hba1c_value']; $hi=interpretHbA1c($lh); ?>
            <p><strong>HbA1c:</strong> Latest <?= $lh ?>% — <strong><?= $hi['label'] ?></strong>.
                <?= $lh<7?'✅ Target (<7%) achieved.':'⚠️ Above 7% target. Consider intensifying management.' ?>
            </p>
            <?php endif; ?>
            <p><strong>Recommendations:</strong> Regular blood glucose monitoring, follow prescribed meal plan, take medications as directed, maintain regular follow-up, report hypoglycemia episodes (&lt;70 mg/dL) immediately.</p>
            <?php else: ?>
            <p>No blood sugar readings found for the selected period. Please ensure readings are recorded regularly.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- PATIENT PROFILE -->
    <div class="sec">
        <div class="sec-title">👤 Patient Profile</div>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">Full Name</div><div class="val"><?= htmlspecialchars($patient['full_name']) ?></div></div>
            <div class="info-item"><div class="lbl">Patient ID</div><div class="val"><?= $patient['patient_id'] ?></div></div>
            <div class="info-item"><div class="lbl">Date of Birth</div><div class="val"><?= date('d M Y',strtotime($patient['date_of_birth'])) ?> (<?= calculateAge($patient['date_of_birth']) ?> yrs)</div></div>
            <div class="info-item"><div class="lbl">Diabetes Type</div><div class="val"><?= $patient['diabetes_type'] ?></div></div>
            <div class="info-item"><div class="lbl">Diagnosis Date</div><div class="val"><?= $patient['diagnosis_date']?date('d M Y',strtotime($patient['diagnosis_date'])):'N/A' ?></div></div>
            <div class="info-item"><div class="lbl">Consulting Doctor</div><div class="val"><?= htmlspecialchars($patient['doctor_name']??'N/A') ?></div></div>
            <div class="info-item"><div class="lbl">Blood Group</div><div class="val"><?= $patient['blood_group'] ?></div></div>
            <div class="info-item"><div class="lbl">Weight / Height</div><div class="val"><?= $patient['weight']?$patient['weight'].' kg':'N/A' ?> / <?= $patient['height']?$patient['height'].' cm':'N/A' ?></div></div>
            <div class="info-item"><div class="lbl">Phone</div><div class="val"><?= htmlspecialchars($patient['phone']??'N/A') ?></div></div>
            <?php if (!empty($patient['allergies'])): ?>
            <div class="info-item" style="grid-column:1/-1"><div class="lbl">Allergies</div><div class="val" style="color:#dc2626"><?= htmlspecialchars($patient['allergies']) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($patient['complications'])): ?>
            <div class="info-item" style="grid-column:1/-1"><div class="lbl">Complications</div><div class="val"><?= htmlspecialchars($patient['complications']) ?></div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SIGNATURES -->
    <div class="sig-grid">
        <div class="sig-box">
            <p><strong>Consulting Doctor Signature &amp; Stamp</strong></p>
            <div class="sig-line"></div>
            <p>Dr. ___________________________________</p>
            <p>Date: _________________________________</p>
        </div>
        <div class="sig-box">
            <p><strong>Patient / Guardian Signature</strong></p>
            <div class="sig-line"></div>
            <p>Name: __________________________________</p>
            <p>Date: __________________________________</p>
        </div>
    </div>

    <div class="rpt-ftr">
        <p>DiaCare Pro — Diabetic Care Management System | <?= HOSPITAL_NAME ?></p>
        <p>RPT-<?= date('Ymd') ?>-<?= str_pad($patient['id'],4,'0',STR_PAD_LEFT) ?> | <?= date('d M Y H:i') ?></p>
        <p>⚠️ CONFIDENTIAL MEDICAL RECORD</p>
    </div>

</div></div><!-- /.report-body /.report-page -->

<?php if (!empty($reportData['chartDates'])): ?>
<script>
// Wait for full page load so the container has real pixel dimensions
window.addEventListener('load', function() {
    const canvas = document.getElementById('reportChart');
    if (!canvas) return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: <?= json_encode($reportData['chartDates']) ?>,
            datasets: [
                {
                    label: 'Daily Avg',
                    data: <?= json_encode($reportData['chartAvg']) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.07)',
                    fill: true, tension: 0.35,
                    pointRadius: <?= count($reportData['chartDates']) <= 15 ? 4 : 2 ?>,
                    pointBackgroundColor: '#2563eb',
                    borderWidth: 2.5
                },
                {
                    label: 'Max',
                    data: <?= json_encode($reportData['chartMax']) ?>,
                    borderColor: '#dc2626', fill: false, tension: 0.35,
                    pointRadius: 2, borderDash: [4,3], borderWidth: 1.5
                },
                {
                    label: 'Min',
                    data: <?= json_encode($reportData['chartMin']) ?>,
                    borderColor: '#059669', fill: false, tension: 0.35,
                    pointRadius: 2, borderDash: [4,3], borderWidth: 1.5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,   // fills .chart-canvas-wrap which is absolutely positioned
            animation: { duration: 0 },   // instant render — needed for print
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    min: <?= $reportData['yMin'] ?>,
                    max: <?= $reportData['yMax'] ?>,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 9 },
                        callback: v => v + ' mg/dL',
                        maxTicksLimit: 6
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 9 },
                        maxRotation: 45,
                        maxTicksLimit: <?= min(count($reportData['chartDates']), 18) ?>
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

</body></html>
<?php endif; ?>
