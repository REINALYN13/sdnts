
<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Manage Subjects';
$db = getDB();
$csrf = generateCSRF();


/* =========================================================
   PROCESS ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {

        $d = [
            trim($_POST['subjectCode'] ?? ''),
            trim($_POST['subjectName'] ?? ''),
            trim($_POST['subjectDesc'] ?? ''),
            (float)($_POST['units'] ?? 1),
            (int)($_POST['yearID'] ?? 0) ?: null,
            (int)($_POST['progID'] ?? 0) ?: null,
            (int)($_POST['isCore'] ?? 0)
        ];

        if (!$d[0] || !$d[1]) {

            $_SESSION['flash_error'] = 'Fill required fields.';

        } elseif ($action === 'add') {

            $db->prepare("
                INSERT INTO subjects
                (
                    subjectCode,
                    subjectName,
                    subjectDesc,
                    units,
                    yearID,
                    progID,
                    isCore
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute($d);

            $_SESSION['flash_success'] = 'Subject added.';

        } else {

            $sid = (int)$_POST['subjectID'];

            $d[] = $sid;

            $db->prepare("
                UPDATE subjects
                SET subjectCode = ?,
                    subjectName = ?,
                    subjectDesc = ?,
                    units = ?,
                    yearID = ?,
                    progID = ?,
                    isCore = ?
                WHERE subjectID = ?
            ")->execute($d);

            $_SESSION['flash_success'] = 'Subject updated.';
        }

    } elseif ($action === 'delete') {

        $db->prepare("
            UPDATE subjects
            SET dateDeleted = NOW()
            WHERE subjectID = ?
        ")->execute([
            (int)$_POST['subjectID']
        ]);

        $_SESSION['flash_success'] = 'Subject removed.';
    }

    header('Location: subjects.php');
    exit;
}


/* =========================================================
   GET SUBJECTS
========================================================= */

$subjects = $db->query("
    SELECT
        s.*,
        y.yearName,
        p.progName
    FROM subjects s
    LEFT JOIN year y
        ON s.yearID = y.yearID
    LEFT JOIN programs p
        ON s.progID = p.progID
    WHERE s.dateDeleted IS NULL
    ORDER BY s.subjectCode
")->fetchAll();


/* =========================================================
   GET PROGRAMS
========================================================= */

$programs = $db->query("
    SELECT *
    FROM programs
    WHERE dateDeleted IS NULL
    ORDER BY progName
")->fetchAll();


/* =========================================================
   GET YEAR LEVELS
========================================================= */

$yearLevels = $db->query("
    SELECT *
    FROM year
    WHERE dateDeleted IS NULL
    ORDER BY yearName
")->fetchAll();


include '../includes/header.php';
?>


<style>

/* =========================================================
   SUBJECT PAGE
   Fonts are NOT changed.
========================================================= */

.subject-page-header {
    margin-bottom: 26px;
}

.subject-page-title {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 5px;

    font-size: 1.25rem;
    font-weight: 600;

    color: #212529;
}

.subject-page-title i {
    color: #0d6efd;
}


/* =========================================================
   BREADCRUMB
========================================================= */

.subject-breadcrumb {
    margin: 0;
    padding: 0;

    font-size: .82rem;
}

.subject-breadcrumb a {
    color: #0d6efd;

    text-decoration: none;
}

.subject-breadcrumb a:hover {
    text-decoration: underline;
}

.subject-breadcrumb .breadcrumb-item.active {
    color: #6c757d;
}


/* =========================================================
   ADD BUTTON
========================================================= */

.subject-add-btn {
    font-size: .875rem;

    padding: .5rem .9rem;
}


/* =========================================================
   MAIN CARD
========================================================= */

.subject-card {
    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

    box-shadow:
        0 .125rem .35rem rgba(0, 0, 0, .05);
}


/* =========================================================
   TOOLBAR
========================================================= */

.subject-toolbar {
    padding: 16px 18px;

    border-bottom: 1px solid #e9ecef;

    background: #fff;
}

.subject-toolbar-label {
    margin-bottom: 7px;

    color: #495057;

    font-size: .8rem;
    font-weight: 600;
}

.subject-toolbar small {
    font-size: .78rem;
}


/* =========================================================
   SEARCH
========================================================= */

.subject-search {
    width: 280px;
}

.subject-search .input-group-text {
    background: #fff;

    color: #6c757d;

    border-right: 0;
}

.subject-search .form-control {
    border-left: 0;

    font-size: .85rem;
}

.subject-search .form-control:focus {
    border-color: #dee2e6;

    box-shadow: none;
}

.subject-search .input-group:focus-within {
    box-shadow:
        0 0 0 .15rem rgba(13, 110, 253, .08);
}


/* =========================================================
   TABLE
========================================================= */

.subject-table {
    margin-bottom: 0;
}

.subject-table thead th {
    padding: 13px 18px;

    background: #f8f9fa;

    border-bottom: 1px solid #dee2e6;

    color: #495057;

    font-size: .76rem;
    font-weight: 600;

    white-space: nowrap;
}

.subject-table tbody td {
    padding: 14px 18px;

    color: #343a40;

    font-size: .84rem;

    vertical-align: middle;

    border-bottom: 1px solid #f0f1f2;
}

.subject-table tbody tr:last-child td {
    border-bottom: 0;
}

.subject-table tbody tr:hover {
    background-color: #f8fbff;
}


/* =========================================================
   SUBJECT CODE
========================================================= */

.subject-code {
    display: inline-block;

    padding: 4px 7px;

    background: #f8f9fa;

    border: 1px solid #e1e5e9;

    border-radius: 4px;

    color: #495057;

    font-family:  Roboto;

    font-size: .76rem;
}


/* =========================================================
   SUBJECT NAME
========================================================= */

.subject-name {
    color: #212529;

    font-weight: 500;
}


/* =========================================================
   UNITS
========================================================= */

.subject-units {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-width: 38px;

    padding: 4px 7px;

    background: #f8f9fa;

    border-radius: 4px;

    color: #495057;

    font-size: .76rem;
}


/* =========================================================
   YEAR / PROGRAM
========================================================= */

.subject-assignment {
    color: #495057;
}

.subject-all {
    color: #6c757d;

    font-style: italic;
}


/* =========================================================
   CORE BADGE
========================================================= */

.subject-core {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 5px;

    font-size: .74rem;
    font-weight: 600;
}

.subject-core.core {
    background: #e8f5ee;

    border: 1px solid #c8ead6;

    color: #198754;
}

.subject-core.elective {
    background: #f1f3f5;

    border: 1px solid #dee2e6;

    color: #6c757d;
}


/* =========================================================
   ACTIONS
========================================================= */

.subject-actions {
    white-space: nowrap;
}

.subject-actions .btn {
    width: 32px;
    height: 32px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    border-radius: 5px;
}

.subject-actions form {
    display: inline-block;

    margin-left: 4px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.subject-empty {
    padding: 55px 20px;

    text-align: center;
}

.subject-empty i {
    display: block;

    margin-bottom: 12px;

    color: #adb5bd;

    font-size: 38px;
}

.subject-empty h6 {
    color: #343a40;

    font-weight: 600;
}

.subject-empty p {
    margin-bottom: 18px;

    color: #6c757d;

    font-size: .84rem;
}


/* =========================================================
   MODAL
========================================================= */

.subject-modal .modal-header {
    background: #0d6efd;

    color: #fff;
}

.subject-modal .modal-title {
    font-size: 1rem;

    font-weight: 600;
}

.subject-modal .form-label {
    font-size: .84rem;

    font-weight: 500;
}

.subject-modal .form-control,
.subject-modal .form-select {
    font-size: .85rem;
}

.subject-modal .modal-footer {
    background: #f8f9fa;
}


/* =========================================================
   CHECKBOX
========================================================= */

.subject-core-check {
    padding: 11px 13px;

    background: #f8f9fa;

    border: 1px solid #e9ecef;

    border-radius: 6px;
}

.subject-core-check .form-check-label {
    color: #495057;

    font-size: .84rem;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .subject-page-header {
        align-items: flex-start !important;

        flex-direction: column;
    }

    .subject-add-btn {
        width: 100%;

        margin-top: 12px;
    }

    .subject-search {
        width: 100%;
    }

    .subject-toolbar > div {
        flex-direction: column;

        align-items: stretch !important;

        gap: 12px;
    }

}

</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="subject-page-header d-flex justify-content-between align-items-center">

    <div>

        <div class="subject-page-title">

            <i class="fas fa-list"></i>

            <span>Subjects</span>

        </div>


        <!-- Breadcrumb -->

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb subject-breadcrumb">

                <li class="breadcrumb-item">

                    <a href="<?= SITE_URL ?>/dashboard.php">

                        <i class="fas fa-home me-1"></i>

                        Home

                    </a>

                </li>

                <li
                    class="breadcrumb-item active"
                    aria-current="page"
                >

                    Subjects

                </li>

            </ol>

        </nav>

    </div>


    <!-- Add Subject -->

    <button
        type="button"
        class="btn btn-primary subject-add-btn"
        data-bs-toggle="modal"
        data-bs-target="#modalSubj"
        onclick="resetSubjectForm()"
    >

        <i class="fas fa-plus me-2"></i>

        Add Subject

    </button>

</div>


<!-- =========================================================
     SUBJECT CARD
========================================================= -->

<div class="subject-card">


    <!-- =====================================================
         TOOLBAR
    ====================================================== -->

    <div class="subject-toolbar">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <div class="subject-toolbar-label">

                    Subject List

                </div>

                <small class="text-muted">

                    Manage subjects, units, programs, and year levels

                </small>

            </div>


            <!-- Search -->

            <div class="subject-search">

                <div class="input-group input-group-sm">

                    <span class="input-group-text">

                        <i class="fas fa-search"></i>

                    </span>

                    <input
                        type="text"
                        id="subjectSearch"
                        class="form-control"
                        placeholder="Search subjects..."
                        autocomplete="off"
                    >

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         TABLE
    ====================================================== -->

    <?php if (!empty($subjects)): ?>

        <div class="table-responsive">

            <table
                class="table subject-table table-hover"
                id="subjectTable"
            >

                <thead>

                    <tr>

                        <th style="width: 120px;">
                            Code
                        </th>

                        <th style="min-width: 210px;">
                            Subject Name
                        </th>

                        <th style="width: 80px;">
                            Units
                        </th>

                        <th style="width: 150px;">
                            Year Level
                        </th>

                        <th style="width: 180px;">
                            Program
                        </th>

                        <th style="width: 110px;">
                            Core
                        </th>

                        <th
                            style="width: 110px;"
                            class="text-center"
                        >
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($subjects as $s): ?>

                    <tr>


                        <!-- CODE -->

                        <td>

                            <span class="subject-code">

                                <?= clean($s['subjectCode']) ?>

                            </span>

                        </td>


                        <!-- SUBJECT NAME -->

                        <td>

                            <span class="subject-name">

                                <?= clean($s['subjectName']) ?>

                            </span>

                        </td>


                        <!-- UNITS -->

                        <td>

                            <span class="subject-units">

                                <?= clean($s['units']) ?>

                            </span>

                        </td>


                        <!-- YEAR LEVEL -->

                        <td>

                            <?php if (!empty($s['yearName'])): ?>

                                <span class="subject-assignment">

                                    <?= clean($s['yearName']) ?>

                                </span>

                            <?php else: ?>

                                <span class="subject-all">
                                    All Levels
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- PROGRAM -->

                        <td>

                            <?php if (!empty($s['progName'])): ?>

                                <span class="subject-assignment">

                                    <?= clean($s['progName']) ?>

                                </span>

                            <?php else: ?>

                                <span class="subject-all">
                                    All Programs
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- CORE -->

                        <td>

                            <?php if ($s['isCore']): ?>

                                <span class="subject-core core">

                                    <i class="fas fa-check me-1"></i>

                                    Core

                                </span>

                            <?php else: ?>

                                <span class="subject-core elective">

                                    Elective

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ACTIONS -->

                        <td class="text-center">

                            <div class="subject-actions">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-edit-subj"
                                    title="Edit Subject"

                                    data-subj="<?= htmlspecialchars(
                                        json_encode($s),
                                        ENT_QUOTES
                                    ) ?>"

                                    data-bs-toggle="modal"
                                    data-bs-target="#modalSubj"
                                >

                                    <i class="fas fa-edit"></i>

                                </button>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    class="d-inline"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= $csrf ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="subjectID"
                                        value="<?= (int)$s['subjectID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-delete"
                                        title="Delete Subject"
                                    >

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


    <?php else: ?>


        <!-- =================================================
             EMPTY STATE
        ================================================== -->

        <div class="subject-empty">

            <i class="fas fa-list"></i>

            <h6>
                No Subjects Found
            </h6>

            <p>
                There are currently no active subjects.
            </p>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalSubj"
                onclick="resetSubjectForm()"
            >

                <i class="fas fa-plus me-1"></i>

                Add Subject

            </button>

        </div>


    <?php endif; ?>

</div>


<!-- =========================================================
     ADD / EDIT SUBJECT MODAL
========================================================= -->

<div
    class="modal fade subject-modal"
    id="modalSubj"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <form
                method="POST"
                id="subjForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $csrf ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="add"
                    id="subjAction"
                >

                <input
                    type="hidden"
                    name="subjectID"
                    id="subjID"
                >


                <!-- MODAL HEADER -->

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="subjTitle"
                    >

                        <i class="fas fa-list me-2"></i>

                        Add Subject

                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>


                <!-- MODAL BODY -->

                <div class="modal-body">

                    <div class="row g-3">


                        <!-- SUBJECT CODE -->

                        <div class="col-md-4">

                            <label
                                for="sj_code"
                                class="form-label"
                            >

                                Subject Code

                                <span class="text-danger">
                                    *
                                </span>

                            </label>

                            <input
                                type="text"
                                name="subjectCode"
                                id="sj_code"
                                class="form-control"
                                required
                                placeholder="e.g. ICT101"
                            >

                        </div>


                        <!-- SUBJECT NAME -->

                        <div class="col-md-8">

                            <label
                                for="sj_name"
                                class="form-label"
                            >

                                Subject Name

                                <span class="text-danger">
                                    *
                                </span>

                            </label>

                            <input
                                type="text"
                                name="subjectName"
                                id="sj_name"
                                class="form-control"
                                required
                                placeholder="Enter subject name"
                            >

                        </div>


                        <!-- UNITS -->

                        <div class="col-md-4">

                            <label
                                for="sj_units"
                                class="form-label"
                            >

                                Units

                            </label>

                            <input
                                type="number"
                                name="units"
                                id="sj_units"
                                class="form-control"
                                value="1"
                                step="0.5"
                                min="0.5"
                            >

                        </div>


                        <!-- YEAR LEVEL -->

                        <div class="col-md-4">

                            <label
                                for="sj_year"
                                class="form-label"
                            >

                                Year Level

                            </label>

                            <select
                                name="yearID"
                                id="sj_year"
                                class="form-select"
                            >

                                <option value="">
                                    All Levels
                                </option>

                                <?php foreach ($yearLevels as $y): ?>

                                    <option
                                        value="<?= (int)$y['yearID'] ?>"
                                    >

                                        <?= clean($y['yearName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- PROGRAM -->

                        <div class="col-md-4">

                            <label
                                for="sj_prog"
                                class="form-label"
                            >

                                Program

                            </label>

                            <select
                                name="progID"
                                id="sj_prog"
                                class="form-select"
                            >

                                <option value="">
                                    All Programs
                                </option>

                                <?php foreach ($programs as $p): ?>

                                    <option
                                        value="<?= (int)$p['progID'] ?>"
                                    >

                                        <?= clean($p['progName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- CORE SUBJECT -->

                        <div class="col-12">

                            <div class="subject-core-check">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="isCore"
                                        id="sj_core"
                                        class="form-check-input"
                                        value="1"
                                    >

                                    <label
                                        class="form-check-label"
                                        for="sj_core"
                                    >

                                        <strong>
                                            Core Subject
                                        </strong>

                                        <span class="text-muted ms-1">
                                            (auto-assigned on enrollment)
                                        </span>

                                    </label>

                                </div>

                            </div>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="col-12">

                            <label
                                for="sj_desc"
                                class="form-label"
                            >

                                Description

                            </label>

                            <textarea
                                name="subjectDesc"
                                id="sj_desc"
                                class="form-control"
                                rows="3"
                                placeholder="Enter subject description"
                            ></textarea>

                        </div>

                    </div>

                </div>


                <!-- MODAL FOOTER -->

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fas fa-save me-2"></i>

                        <span id="subjSaveText">
                            Save
                        </span>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

/* =========================================================
   RESET SUBJECT FORM
========================================================= */

function resetSubjectForm() {

    document.getElementById('subjForm').reset();

    document.getElementById('subjAction').value =
        'add';

    document.getElementById('subjID').value =
        '';

    document.getElementById('subjTitle').innerHTML =
        '<i class="fas fa-list me-2"></i>Add Subject';

    document.getElementById('subjSaveText').textContent =
        'Save';

    document.getElementById('sj_units').value =
        '1';

}


/* =========================================================
   EDIT SUBJECT
========================================================= */

document
    .querySelectorAll('.btn-edit-subj')
    .forEach(function(btn) {

        btn.addEventListener('click', function() {

            var s = JSON.parse(
                this.getAttribute('data-subj')
            );

            document.getElementById('subjAction').value =
                'edit';

            document.getElementById('subjID').value =
                s.subjectID || '';

            document.getElementById('subjTitle').innerHTML =
                '<i class="fas fa-edit me-2"></i>Edit Subject';

            document.getElementById('subjSaveText').textContent =
                'Update';

            document.getElementById('sj_code').value =
                s.subjectCode || '';

            document.getElementById('sj_name').value =
                s.subjectName || '';

            document.getElementById('sj_units').value =
                s.units || 1;

            document.getElementById('sj_year').value =
                s.yearID || '';

            document.getElementById('sj_prog').value =
                s.progID || '';

            document.getElementById('sj_core').checked =
                s.isCore == 1;

            document.getElementById('sj_desc').value =
                s.subjectDesc || '';

        });

    });


/* =========================================================
   SEARCH SUBJECTS
========================================================= */

var searchInput =
    document.getElementById('subjectSearch');

if (searchInput) {

    searchInput.addEventListener('input', function() {

        var value =
            this.value.toLowerCase().trim();

        document
            .querySelectorAll('#subjectTable tbody tr')
            .forEach(function(row) {

                var text =
                    row.textContent.toLowerCase();

                row.style.display =
                    text.includes(value)
                        ? ''
                        : 'none';

            });

    });

}


/* =========================================================
   DELETE CONFIRMATION
========================================================= */

document
    .querySelectorAll('.btn-delete')
    .forEach(function(btn) {

        btn.addEventListener('click', function(event) {

            if (!confirm(
                'Are you sure you want to remove this subject?'
            )) {

                event.preventDefault();

            }

        });

    });

</script>


<?php include '../includes/footer.php'; ?>