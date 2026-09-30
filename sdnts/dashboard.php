<?php
require_once 'includes/config.php';

$pageTitle = 'Dashboard';
include 'includes/header.php';

$db = getDB();
$ay = getActiveAY();

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$totalStudents = $db->query("
    SELECT COUNT(*)
    FROM students
    WHERE dateDeleted IS NULL
")->fetchColumn();

$enrolledStudents = $db->query("
    SELECT COUNT(DISTINCT studentID)
    FROM registration
    WHERE status = 'enrolled'
    AND ayID = {$ay['ayID']}
")->fetchColumn();

$totalTeachers = $db->query("
    SELECT COUNT(*)
    FROM users
    WHERE roleID = 3
    AND dateDeleted IS NULL
    AND isActive = 1
")->fetchColumn();

$totalSections = $db->query("
    SELECT COUNT(*)
    FROM sections
    WHERE dateDeleted IS NULL
    AND ayID = {$ay['ayID']}
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Enrollment Percentage
|--------------------------------------------------------------------------
*/

$enrollmentRate = $totalStudents > 0
    ? round(($enrolledStudents / $totalStudents) * 100, 1)
    : 0;

/*
|--------------------------------------------------------------------------
| Recent Enrollments
|--------------------------------------------------------------------------
*/

$recentEnroll = $db->prepare("
    SELECT
        r.*,
        s.firstName,
        s.lastName,
        s.studentNo,
        sec.sectionName,
        p.progName
    FROM registration r
    JOIN students s ON r.studentID = s.studentID
    JOIN sections sec ON r.sectionID = sec.sectionID
    JOIN programs p ON r.progID = p.progID
    WHERE r.ayID = ?
    ORDER BY r.dateRegistration DESC
    LIMIT 8
");

$recentEnroll->execute([$ay['ayID']]);

/*
|--------------------------------------------------------------------------
| Top Students
|--------------------------------------------------------------------------
*/

$topStudents = $db->prepare("
    SELECT *
    FROM v_student_gwa
    WHERE ayID = ?
    ORDER BY gwa DESC
    LIMIT 8
");

$topStudents->execute([$ay['ayID']]);

/*
|--------------------------------------------------------------------------
| Grade Distribution
|--------------------------------------------------------------------------
*/

$gradeStats = $db->prepare("
    SELECT
        SUM(CASE WHEN average >= 90 THEN 1 ELSE 0 END) AS outstanding,
        SUM(CASE WHEN average >= 85 AND average < 90 THEN 1 ELSE 0 END) AS vSatisfactory,
        SUM(CASE WHEN average >= 80 AND average < 85 THEN 1 ELSE 0 END) AS satisfactory,
        SUM(CASE WHEN average >= 75 AND average < 80 THEN 1 ELSE 0 END) AS fairly,
        SUM(CASE WHEN average < 75 THEN 1 ELSE 0 END) AS failed
    FROM grades
    WHERE ayID = ?
    AND average IS NOT NULL
    AND dateDeleted IS NULL
");

$gradeStats->execute([$ay['ayID']]);
$gStats = $gradeStats->fetch();

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function initials($first, $last)
{
    $f = trim($first) !== ''
        ? mb_strtoupper(mb_substr(trim($first), 0, 1))
        : '';

    $l = trim($last) !== ''
        ? mb_strtoupper(mb_substr(trim($last), 0, 1))
        : '';

    return $f . $l;
}

function gwaClass($gwa)
{
    if ($gwa >= 90) {
        return 'excellent';
    }

    if ($gwa >= 85) {
        return 'good';
    }

    if ($gwa >= 75) {
        return 'average';
    }

    return 'low';
}

$labels = [
    'Outstanding',
    'Very Satisfactory',
    'Satisfactory',
    'Fairly Satisfactory',
    'Did Not Meet Expectations'
];

$ranges = [
    '90 and above',
    '85–89',
    '80–84',
    '75–79',
    'Below 75'
];

$values = [
    (int)($gStats['outstanding'] ?? 0),
    (int)($gStats['vSatisfactory'] ?? 0),
    (int)($gStats['satisfactory'] ?? 0),
    (int)($gStats['fairly'] ?? 0),
    (int)($gStats['failed'] ?? 0)
];

$gradeTotal = array_sum($values);

?>

<style>

/* ==========================================================================
   SDNTS DASHBOARD
   ========================================================================== */

.sis-dashboard {

    --dash-navy: #10233F;
    --dash-navy-2: #16304F;
    --dash-blue: #2F5687;

    --dash-gold: #B8862E;
    --dash-gold-soft: #FBF1DD;

    --dash-green: #3E7A5B;
    --dash-green-soft: #E4F1EA;

    --dash-purple: #5B4B8A;
    --dash-purple-soft: #EDE9F6;

    --dash-red: #B5432F;
    --dash-red-soft: #F8E7E2;

    --dash-bg: #F7F5F0;
    --dash-white: #FFFFFF;

    --dash-text: #1A1D23;
    --dash-muted: #6E7480;
    --dash-line: #E7E3D9;

    font-family: 'Roboto', Roboto, RobotoFont, "Roboto",  Roboto;

    margin: -1.5rem -1.5rem 0;
    padding: 1.5rem;

    background:
        radial-gradient(
            circle at 100% 0%,
            rgba(184,134,46,.08),
            transparent 30%
        ),
        var(--dash-bg);

    min-height: calc(100vh - 70px);

    color: var(--dash-text);
}

.sis-dashboard *,
.sis-dashboard *::before,
.sis-dashboard *::after {
    box-sizing: border-box;
}

/* ==========================================================================
   HERO
   ========================================================================== */

.dashboard-hero {

    position: relative;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 1.5rem;

    padding: 1.75rem 1.8rem;

    margin-bottom: 1.25rem;

    border-radius: 18px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            var(--dash-navy),
            var(--dash-navy-2)
        );

    color: white;

    box-shadow:
        0 10px 30px rgba(16,35,63,.12);
}

.dashboard-hero::before {

    content: "";

    position: absolute;

    width: 260px;
    height: 260px;

    right: -100px;
    top: -150px;

    border-radius: 50%;

    background: rgba(255,255,255,.05);
}

.dashboard-hero::after {

    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    right: 100px;
    bottom: -130px;

    border-radius: 50%;

    background: rgba(184,134,46,.12);
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-eyebrow {

    font-family: 'Roboto',  Roboto;

    text-transform: uppercase;

    letter-spacing: .13em;

    font-size: .68rem;

    font-weight: 700;

    color: #DDBB6C;

    margin-bottom: .4rem;
}

.hero-title {

    margin: 0;

    color: white !important;

    font-family: 'Roboto', Roboto, Roboto;

    font-size: 1.8rem;

    font-weight: 600;

    line-height: 1.2;
}

.hero-subtitle {

    margin: .45rem 0 0;

    color: rgba(255,255,255,.68);

    font-size: .82rem;
}

.hero-year {

    position: relative;

    z-index: 2;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    gap: .65rem;

    padding: .65rem 1rem;

    border-radius: 999px;

    background: rgba(255,255,255,.09);

    border: 1px solid rgba(255,255,255,.12);

    color: white;

    font-family: ' Roboto',  Roboto;

    font-size: .78rem;

    font-weight: 700;

    backdrop-filter: blur(8px);
}

.hero-year i {
    color: #DDBB6C;
}


/* ==========================================================================
   STAT CARDS
   ========================================================================== */

.dashboard-stats {

    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 1rem;

    margin-bottom: 1.25rem;
}

.dashboard-stat {

    position: relative;

    background: white;

    border: 1px solid var(--dash-line);

    border-radius: 15px;

    padding: 1.1rem 1.15rem;

    overflow: hidden;

    transition:
        transform .18s ease,
        box-shadow .18s ease;
}

.dashboard-stat:hover {

    transform: translateY(-3px);

    box-shadow:
        0 10px 25px rgba(16,35,63,.09);
}

.dashboard-stat::after {

    content: "";

    position: absolute;

    width: 85px;
    height: 85px;

    right: -30px;
    bottom: -35px;

    border-radius: 50%;

    background: var(--stat-soft);

    opacity: .75;
}

.dashboard-stat.navy {
    --stat-color: var(--dash-blue);
    --stat-soft: #E7EEF6;
}

.dashboard-stat.green {
    --stat-color: var(--dash-green);
    --stat-soft: var(--dash-green-soft);
}

.dashboard-stat.gold {
    --stat-color: var(--dash-gold);
    --stat-soft: var(--dash-gold-soft);
}

.dashboard-stat.purple {
    --stat-color: var(--dash-purple);
    --stat-soft: var(--dash-purple-soft);
}

.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: .9rem;
}

.stat-icon {

    width: 39px;
    height: 39px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: var(--stat-color);

    color: white;

    font-size: .9rem;

    box-shadow: 0 5px 12px rgba(16,35,63,.1);
}

.stat-kicker {

    font-family: ' Roboto',  Roboto;

    font-size: .62rem;

    text-transform: uppercase;

    letter-spacing: .06em;

    color: var(--dash-muted);

    font-weight: 700;
}

.stat-number {

    position: relative;

    z-index: 2;

    font-family: ' Roboto',  Roboto;

    font-size: 1.55rem;

    line-height: 1;

    font-weight: 700;

    color: var(--dash-navy);

    font-variant-numeric: tabular-nums;
}

.stat-label {

    position: relative;

    z-index: 2;

    margin-top: .38rem;

    color: var(--dash-muted);

    font-size: .76rem;

    font-weight: 500;
}

.stat-foot {

    position: relative;

    z-index: 2;

    margin-top: .65rem;

    display: flex;

    align-items: center;

    gap: .35rem;

    font-size: .68rem;

    color: var(--stat-color);

    font-weight: 700;
}

/* ==========================================================================
   MAIN GRID
   ========================================================================== */

.dashboard-grid {

    display: grid;

    grid-template-columns: minmax(0, 1.45fr) minmax(320px, .85fr);

    gap: 1rem;

    align-items: stretch;
}

.dashboard-left,
.dashboard-right {

    display: flex;

    flex-direction: column;

    gap: 1rem;

    min-width: 0;
}

/* ==========================================================================
   PANELS
   ========================================================================== */

.dashboard-panel {

    background: white;

    border: 1px solid var(--dash-line);

    border-radius: 15px;

    overflow: hidden;

    box-shadow: 0 2px 5px rgba(16,35,63,.025);
}

.panel-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 1rem;

    padding: 1rem 1.2rem;

    border-bottom: 1px solid var(--dash-line);
}

.panel-title {

    display: flex;

    align-items: center;

    gap: .55rem;

    margin: 0;

    font-family: ' Roboto',  Roboto,  Roboto;

    color: var(--dash-navy);

    font-size: 1rem;

    font-weight: 600;
}

.panel-title-icon {

    width: 28px;
    height: 28px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: var(--dash-gold-soft);

    color: var(--dash-gold);

    font-size: .72rem;
}

.panel-link {

    display: inline-flex;

    align-items: center;

    gap: .3rem;

    padding: .35rem .65rem;

    border-radius: 999px;

    border: 1px solid var(--dash-line);

    color: var(--dash-navy);

    text-decoration: none !important;

    font-size: .68rem;

    font-weight: 700;

    transition: all .15s ease;
}

.panel-link:hover {

    background: var(--dash-navy);

    color: white !important;

    border-color: var(--dash-navy);
}

/* ==========================================================================
   TOP STUDENTS
   ========================================================================== */

.student-list {

    list-style: none;

    margin: 0;

    padding: 0;
}

.student-row {

    display: flex;

    align-items: center;

    gap: .75rem;

    padding: .75rem 1.2rem;

    border-bottom: 1px solid #F0EEE8;

    transition: background .12s ease;
}

.student-row:last-child {
    border-bottom: none;
}

.student-row:hover {
    background: #FCFBF8;
}

.student-rank {

    width: 27px;
    height: 27px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 8px;

    background: #F0EEE8;

    color: var(--dash-muted);

    font-family: ' Roboto',  Roboto;

    font-size: .68rem;

    font-weight: 700;
}

.student-rank.first {

    background: linear-gradient(135deg,#E8C568,#B8862E);

    color: white;
}

.student-rank.second {

    background: linear-gradient(135deg,#C7CDD4,#8A929D);

    color: white;
}

.student-rank.third {

    background: linear-gradient(135deg,#D09268,#A15A34);

    color: white;
}

.student-avatar {

    width: 34px;
    height: 34px;

    flex-shrink: 0;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #E7EEF6;

    color: var(--dash-blue);

    font-size: .67rem;

    font-weight: 700;
}

.student-info {

    flex: 1;

    min-width: 0;
}

.student-name {

    font-size: .8rem;

    font-weight: 700;

    color: var(--dash-text);

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.student-number {

    margin-top: 2px;

    font-family: ' Roboto',  Roboto;

    color: var(--dash-muted);

    font-size: .61rem;
}

.student-section {

    color: var(--dash-muted);

    font-size: .67rem;

    white-space: nowrap;

    max-width: 100px;

    overflow: hidden;

    text-overflow: ellipsis;
}

.gwa {

    min-width: 52px;

    padding: .28rem .45rem;

    border-radius: 7px;

    text-align: center;

    font-family: ' Roboto',  Roboto;

    font-size: .69rem;

    font-weight: 700;

    color: white;
}

.gwa.excellent {
    background: var(--dash-green);
}

.gwa.good {
    background: var(--dash-blue);
}

.gwa.average {
    background: var(--dash-gold);
}

.gwa.low {
    background: var(--dash-red);
}

/* ==========================================================================
   GRADE DISTRIBUTION
   ========================================================================== */

.grade-content {
    padding: 1.15rem 1.2rem;
}

.grade-summary {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: .8rem;
}

.grade-total-label {

    color: var(--dash-muted);

    font-size: .7rem;
}

.grade-total-number {

    color: var(--dash-navy);

    font-family: ' Roboto',  Roboto;

    font-size: 1.05rem;

    font-weight: 700;
}

.grade-bar {

    height: 13px;

    display: flex;

    overflow: hidden;

    border-radius: 999px;

    background: #F0EEE8;

    margin-bottom: 1rem;
}

.grade-segment {
    height: 100%;
}

.grade-segment.s0 {
    background: var(--dash-green);
}

.grade-segment.s1 {
    background: var(--dash-blue);
}

.grade-segment.s2 {
    background: #70A7C9;
}

.grade-segment.s3 {
    background: var(--dash-gold);
}

.grade-segment.s4 {
    background: var(--dash-red);
}

.grade-legend {

    display: grid;

    grid-template-columns: 1fr;

    gap: .1rem;
}

.grade-row {

    display: flex;

    align-items: center;

    gap: .5rem;

    padding: .35rem 0;
}

.grade-dot {

    width: 8px;
    height: 8px;

    flex-shrink: 0;

    border-radius: 50%;
}

.grade-dot.s0 {
    background: var(--dash-green);
}

.grade-dot.s1 {
    background: var(--dash-blue);
}

.grade-dot.s2 {
    background: #70A7C9;
}

.grade-dot.s3 {
    background: var(--dash-gold);
}

.grade-dot.s4 {
    background: var(--dash-red);
}

.grade-name {

    flex: 1;

    color: var(--dash-muted);

    font-size: .7rem;
}

.grade-range {

    color: #A0A5AD;

    font-size: .62rem;
}

.grade-value {

    min-width: 25px;

    text-align: right;

    color: var(--dash-text);

    font-family: ' Roboto',  Roboto;

    font-size: .66rem;

    font-weight: 700;
}

.grade-percent {

    width: 35px;

    text-align: right;

    color: var(--dash-muted);

    font-family: ' Roboto',  Roboto;

    font-size: .6rem;
}

/* ==========================================================================
   RECENT ENROLLMENTS
   ========================================================================== */

.enrollment-list {

    list-style: none;

    margin: 0;

    padding: .4rem 1.2rem;
}

.enrollment-item {

    position: relative;

    display: flex;

    align-items: center;

    gap: .75rem;

    padding: .65rem 0;

    border-bottom: 1px solid #F0EEE8;
}

.enrollment-item:last-child {
    border-bottom: none;
}

.enrollment-marker {

    width: 9px;
    height: 9px;

    flex-shrink: 0;

    border-radius: 50%;

    background: var(--dash-green);

    box-shadow: 0 0 0 4px var(--dash-green-soft);
}

.enrollment-marker.pending {
o
    background: var(--dash-gold);

    box-shadow: 0 0 0 4px var(--dash-gold-soft);
}

.enrollment-info {
    flex: 1;
    min-width: 0;
}

.enrollment-name {

    font-size: .74rem;

    font-weight: 700;

    color: var(--dash-text);

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.enrollment-meta {

    margin-top: 2px;

    color: var(--dash-muted);

    font-size: .62rem;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.status {

    flex-shrink: 0;

    padding: .2rem .5rem;

    border-radius: 999px;

    font-size: .57rem;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .04em;
}

.status.enrolled {

    color: var(--dash-green);

    background: var(--dash-green-soft);
}

.status.pending {

    color: var(--dash-gold);

    background: var(--dash-gold-soft);
}

/* ==========================================================================
   EMPTY STATE
   ========================================================================== */

.dashboard-empty {

    padding: 2rem 1rem;

    text-align: center;

    color: var(--dash-muted);

    font-size: .75rem;
}

.dashboard-empty i {

    display: block;

    margin-bottom: .5rem;

    color: #C9C6BE;

    font-size: 1.4rem;
}

/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 1100px) {

    .dashboard-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 767px) {

    .sis-dashboard {

        margin: -1rem -0.75rem 0;

        padding: 1rem;

    }

    .dashboard-hero {

        align-items: flex-start;

        flex-direction: column;

        padding: 1.3rem;

    }

    .hero-title {
        font-size: 1.4rem;
    }

    .hero-year {
        font-size: .68rem;
    }

    .quick-actions {
        padding: .75rem;
    }

    .quick-label {
        width: 100%;
    }

    .dashboard-stats {
        gap: .65rem;
    }

    .dashboard-stat {
        padding: .9rem;
    }

    .stat-number {
        font-size: 1.3rem;
    }

    .student-section {
        display: none;
    }

    .student-row {
        padding-left: .85rem;
        padding-right: .85rem;
    }

}

@media (max-width: 480px) {

    .dashboard-stats {
        grid-template-columns: 1fr 1fr;
    }

    .student-rank {
        display: none;
    }

    .student-avatar {
        width: 30px;
        height: 30px;
    }

    .panel-header {
        padding: .85rem;
    }

}

/* ==========================================================================
   REDUCE MOTION
   ========================================================================== */

@media (prefers-reduced-motion: reduce) {

    .dashboard-stat,
    .quick-action,
    .student-row {
        transition: none;
    }

}

</style>


<div class="sis-dashboard">

    <!-- ================================================================
         HERO
         ================================================================ -->

    <section class="dashboard-hero">

        <div class="hero-content">

            <div class="hero-eyebrow">
                School Information System
            </div>

            <h1 class="hero-title">
                Good day! Here's today's overview.
            </h1>

            <p class="hero-subtitle">
                Monitor students, enrollment, academic performance, and school activity.
            </p>

        </div>

        <div class="hero-year">
            <i class="fas fa-calendar-alt"></i>

            S.Y. <?= clean($ay['academicYear']) ?>

        </div>

    </section>



    <!-- ================================================================
         STATISTICS
         ================================================================ -->

    <div class="dashboard-stats">

        <!-- Students -->

        <div class="dashboard-stat navy">

            <div class="stat-top">

                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>

                <span class="stat-kicker">
                    Population
                </span>

            </div>

            <div class="stat-number">
                <?= number_format($totalStudents) ?>
            </div>

            <div class="stat-label">
                Total Students
            </div>

            <div class="stat-foot">
                <i class="fas fa-database"></i>
                Active student records
            </div>

        </div>


        <!-- Enrollment -->

        <div class="dashboard-stat green">

            <div class="stat-top">

                <div class="stat-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>

                <span class="stat-kicker">
                    Current Year
                </span>

            </div>

            <div class="stat-number">
                <?= number_format($enrolledStudents) ?>
            </div>

            <div class="stat-label">
                Enrolled Students
            </div>

            <div class="stat-foot">
                <i class="fas fa-arrow-up"></i>
                <?= number_format($enrollmentRate, 1) ?>% of student population
            </div>

        </div>


        <!-- Teachers -->

        <div class="dashboard-stat gold">

            <div class="stat-top">

                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>

                <span class="stat-kicker">
                    Faculty
                </span>

            </div>

            <div class="stat-number">
                <?= number_format($totalTeachers) ?>
            </div>

            <div class="stat-label">
                Active Teachers
            </div>

            <div class="stat-foot">
                <i class="fas fa-check-circle"></i>
                Currently active
            </div>

        </div>


        <!-- Sections -->

        <div class="dashboard-stat purple">

            <div class="stat-top">

                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>

                <span class="stat-kicker">
                    Current Year
                </span>

            </div>

            <div class="stat-number">
                <?= number_format($totalSections) ?>
            </div>

            <div class="stat-label">
                Active Sections
            </div>

            <div class="stat-foot">
                <i class="fas fa-layer-group"></i>
                Academic sections
            </div>

        </div>

    </div>


    <!-- ================================================================
         MAIN CONTENT
         ================================================================ -->

    <div class="dashboard-grid">


        <!-- ============================================================
             LEFT COLUMN
             ============================================================ -->

        <div class="dashboard-left">


            <!-- TOP STUDENTS -->

            <section class="dashboard-panel">

                <div class="panel-header">

                    <h2 class="panel-title">

                        <span class="panel-title-icon">
                            <i class="fas fa-trophy"></i>
                        </span>

                        Top Students

                    </h2>

                    <a
                        href="<?= SITE_URL ?>/admin/ranking.php"
                        class="panel-link"
                    >
                        View All
                        <i class="fas fa-arrow-right"></i>
                    </a>

                </div>


                <ul class="student-list">

                    <?php if ($topStudents->rowCount() === 0): ?>

                        <li class="dashboard-empty">

                            <i class="fas fa-award"></i>

                            No ranked students yet for this school year.

                        </li>

                    <?php else: ?>

                        <?php
                        $rank = 1;

                        while ($s = $topStudents->fetch()):
                        ?>

                            <li class="student-row">

                                <!-- Rank -->

                                <div class="student-rank
                                    <?= $rank === 1 ? 'first' : '' ?>
                                    <?= $rank === 2 ? 'second' : '' ?>
                                    <?= $rank === 3 ? 'third' : '' ?>
                                ">

                                    <?= $rank ?>

                                </div>


                                <!-- Avatar -->

                                <div class="student-avatar">

                                    <?= clean(
                                        initials(
                                            $s['firstName'],
                                            $s['lastName']
                                        )
                                    ) ?>

                                </div>


                                <!-- Student -->

                                <div class="student-info">

                                    <div class="student-name">

                                        <?= clean(
                                            $s['lastName'] .
                                            ', ' .
                                            $s['firstName']
                                        ) ?>

                                    </div>

                                    <div class="student-number">

                                        <?= clean($s['studentNo']) ?>

                                    </div>

                                </div>


                                <!-- Section -->

                                <div class="student-section">

                                    <?= clean($s['sectionName'] ?? '') ?>

                                </div>


                                <!-- GWA -->

                                <?php
                                $gwa = (float)$s['gwa'];
                                ?>

                                <div class="gwa <?= gwaClass($gwa) ?>">

                                    <?= number_format($gwa, 2) ?>

                                </div>

                            </li>

                            <?php
                            $rank++;
                            ?>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </ul>

            </section>


            <!-- RECENT ENROLLMENTS -->

            <section class="dashboard-panel">

                <div class="panel-header">

                    <h2 class="panel-title">

                        <span class="panel-title-icon">
                            <i class="fas fa-clock"></i>
                        </span>

                        Recent Enrollments

                    </h2>

                    <a
                        href="<?= SITE_URL ?>/admin/enrollment.php"
                        class="panel-link"
                    >
                        View All
                        <i class="fas fa-arrow-right"></i>
                    </a>

                </div>


                <?php if ($recentEnroll->rowCount() === 0): ?>

                    <div class="dashboard-empty">

                        <i class="fas fa-clipboard-list"></i>

                        No recent enrollments to show.

                    </div>

                <?php else: ?>

                    <ul class="enrollment-list">

                        <?php while ($e = $recentEnroll->fetch()): ?>

                            <?php
                            $isEnrolled =
                                strtolower($e['status']) === 'enrolled';
                            ?>

                            <li class="enrollment-item">

                                <div class="enrollment-marker
                                    <?= $isEnrolled ? '' : 'pending' ?>
                                "></div>


                                <div class="enrollment-info">

                                    <div class="enrollment-name">

                                        <?= clean(
                                            $e['lastName'] .
                                            ', ' .
                                            $e['firstName']
                                        ) ?>

                                    </div>

                                    <div class="enrollment-meta">

                                        <?= clean($e['sectionName']) ?>

                                        &middot;

                                        <?= clean($e['progName']) ?>

                                    </div>

                                </div>


                                <span class="status
                                    <?= $isEnrolled ? 'enrolled' : 'pending' ?>
                                ">

                                    <?= clean($e['status']) ?>

                                </span>

                            </li>

                        <?php endwhile; ?>

                    </ul>

                <?php endif; ?>

            </section>


        </div>


        <!-- ============================================================
             RIGHT COLUMN
             ============================================================ -->

        <div class="dashboard-right">


            <!-- GRADE DISTRIBUTION -->

            <section class="dashboard-panel">

                <div class="panel-header">

                    <h2 class="panel-title">

                        <span class="panel-title-icon">
                            <i class="fas fa-chart-bar"></i>
                        </span>

                        Grade Distribution

                    </h2>

                </div>


                <div class="grade-content">

                    <div class="grade-summary">

                        <span class="grade-total-label">
                            Recorded grade results
                        </span>

                        <span class="grade-total-number">
                            <?= number_format($gradeTotal) ?>
                        </span>

                    </div>


                    <!-- Spectrum -->

                    <div class="grade-bar">

                        <?php foreach ($values as $i => $value): ?>

                            <?php
                            $width = $gradeTotal > 0
                                ? round(($value / $gradeTotal) * 100, 2)
                                : 0;
                            ?>

                            <div
                                class="grade-segment s<?= $i ?>"
                                style="width: <?= $width ?>%"
                            ></div>

                        <?php endforeach; ?>

                    </div>


                    <!-- Legend -->

                    <div class="grade-legend">

                        <?php foreach ($labels as $i => $label): ?>

                            <?php
                            $percent = $gradeTotal > 0
                                ? round(($values[$i] / $gradeTotal) * 100)
                                : 0;
                            ?>

                            <div class="grade-row">

                                <span class="grade-dot s<?= $i ?>"></span>

                                <span class="grade-name">

                                    <?= $label ?>

                                    <span class="grade-range">
                                        (<?= $ranges[$i] ?>)
                                    </span>

                                </span>

                                <span class="grade-value">
                                    <?= number_format($values[$i]) ?>
                                </span>

                                <span class="grade-percent">
                                    <?= $percent ?>%
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </section>


            <!-- ACADEMIC SNAPSHOT -->

            <section class="dashboard-panel">

                <div class="panel-header">

                    <h2 class="panel-title">

                        <span class="panel-title-icon">
                            <i class="fas fa-school"></i>
                        </span>

                        Academic Snapshot

                    </h2>

                </div>


                <div class="grade-content">

                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr;
                            gap:.65rem;
                        "
                    >

                        <a
                            href="<?= SITE_URL ?>/admin/students.php"
                            style="
                                text-decoration:none;
                                padding:.85rem;
                                border-radius:10px;
                                background:#F7F9FC;
                                border:1px solid #E7EEF6;
                            "
                        >

                            <div
                                style="
                                    color:#2F5687;
                                    font-size:.72rem;
                                    font-weight:700;
                                "
                            >
                                STUDENTS
                            </div>

                            <div
                                style="
                                    margin-top:.2rem;
                                    color:#10233F;
                                    font-family:' Roboto', Roboto;
                                    font-size:1.05rem;
                                    font-weight:700;
                                "
                            >
                                <?= number_format($totalStudents) ?>
                            </div>

                        </a>


                        <a
                            href="<?= SITE_URL ?>/admin/enrollment.php"
                            style="
                                text-decoration:none;
                                padding:.85rem;
                                border-radius:10px;
                                background:#F4FAF6;
                                border:1px solid #E4F1EA;
                            "
                        >

                            <div
                                style="
                                    color:#3E7A5B;
                                    font-size:.72rem;
                                    font-weight:700;
                                "
                            >
                                ENROLLED
                            </div>

                            <div
                                style="
                                    margin-top:.2rem;
                                    color:#10233F;
                                    font-family:' Roboto', Roboto;
                                    font-size:1.05rem;
                                    font-weight:700;
                                "
                            >
                                <?= number_format($enrolledStudents) ?>
                            </div>

                        </a>


                        <a
                            href="<?= SITE_URL ?>/admin/users.php"
                            style="
                                text-decoration:none;
                                padding:.85rem;
                                border-radius:10px;
                                background:#FFF9EF;
                                border:1px solid #FBF1DD;
                            "
                        >

                            <div
                                style="
                                    color:#B8862E;
                                    font-size:.72rem;
                                    font-weight:700;
                                "
                            >
                                TEACHERS
                            </div>

                            <div
                                style="
                                    margin-top:.2rem;
                                    color:#10233F;
                                    font-family:' Roboto', Roboto;
                                    font-size:1.05rem;
                                    font-weight:700;
                                "
                            >
                                <?= number_format($totalTeachers) ?>
                            </div>

                        </a>


                        <a
                            href="<?= SITE_URL ?>/admin/sections.php"
                            style="
                                text-decoration:none;
                                padding:.85rem;
                                border-radius:10px;
                                background:#F8F6FC;
                                border:1px solid #EDE9F6;
                            "
                        >

                            <div
                                style="
                                    color:#5B4B8A;
                                    font-size:.72rem;
                                    font-weight:700;
                                "
                            >
                                SECTIONS
                            </div>

                            <div
                                style="
                                    margin-top:.2rem;
                                    color:#10233F;
                                    font-family:' Roboto', Roboto;
                                    font-size:1.05rem;
                                    font-weight:700;
                                "
                            >
                                <?= number_format($totalSections) ?>
                            </div>

                        </a>

                    </div>

                </div>

            </section>


        </div>

    </div>

</div>


<?php include 'includes/footer.php'; ?>