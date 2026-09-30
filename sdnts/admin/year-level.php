<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Year Levels';
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
            trim($_POST['yearName'] ?? ''),
            trim($_POST['yearDesc'] ?? ''),
            (int)($_POST['progID'] ?? 0)
        ];

        if (!$d[0] || !$d[2]) {

            $_SESSION['flash_error'] = 'Fill required fields.';

        } elseif ($action === 'add') {

            $db->prepare("
                INSERT INTO year
                (yearName, yearDesc, progID)
                VALUES (?, ?, ?)
            ")->execute($d);

            $_SESSION['flash_success'] = 'Year level added.';

        } else {

            $id = (int)$_POST['yearID'];

            $d[] = $id;

            $db->prepare("
                UPDATE year
                SET yearName = ?,
                    yearDesc = ?,
                    progID = ?
                WHERE yearID = ?
            ")->execute($d);

            $_SESSION['flash_success'] = 'Updated.';
        }

    } elseif ($action === 'delete') {

        $db->prepare("
            UPDATE year
            SET dateDeleted = NOW()
            WHERE yearID = ?
        ")->execute([
            (int)$_POST['yearID']
        ]);

        $_SESSION['flash_success'] = 'Removed.';
    }

    header('Location: year-level.php');
    exit;
}


/* =========================================================
   GET YEAR LEVELS
========================================================= */

$list = $db->query("
    SELECT
        y.*,
        p.progName
    FROM year y
    JOIN programs p
        ON y.progID = p.progID
    WHERE y.dateDeleted IS NULL
    ORDER BY p.progName, y.yearName
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


include '../includes/header.php';
?>


<style>

/* =========================================================
   PAGE HEADER
========================================================= */

.year-page-header {
    margin-bottom: 26px;
}

.year-page-title {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 5px;

    font-size: 1.25rem;
    font-weight: 600;

    color: #212529;
}

.year-page-title i {
    color: #0d6efd;
}


/* =========================================================
   BREADCRUMB
========================================================= */

.year-breadcrumb {
    margin: 0;
    padding: 0;

    font-size: .82rem;
}

.year-breadcrumb a {
    color: #0d6efd;

    text-decoration: none;
}

.year-breadcrumb a:hover {
    text-decoration: underline;
}

.year-breadcrumb .breadcrumb-item.active {
    color: #6c757d;
}


/* =========================================================
   ADD BUTTON
========================================================= */

.year-add-btn {
    font-size: .875rem;

    padding: .5rem .9rem;
}


/* =========================================================
   MAIN CARD
========================================================= */

.year-card {
    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

    box-shadow:
        0 .125rem .35rem rgba(0, 0, 0, .05);
}


/* =========================================================
   TOOLBAR
========================================================= */

.year-toolbar {
    padding: 16px 18px;

    border-bottom: 1px solid #e9ecef;

    background: #fff;
}

.year-toolbar-label {
    margin-bottom: 7px;

    color: #495057;

    font-size: .8rem;
    font-weight: 600;
}

.year-toolbar small {
    font-size: .78rem;
}


/* =========================================================
   SEARCH BAR
========================================================= */

.year-search {
    width: 280px;
}

.year-search .input-group-text {
    background: #fff;

    color: #6c757d;

    border-right: 0;
}

.year-search .form-control {
    border-left: 0;

    font-size: .85rem;
}

.year-search .form-control:focus {
    border-color: #dee2e6;

    box-shadow: none;
}

.year-search .input-group:focus-within {
    box-shadow:
        0 0 0 .15rem rgba(13, 110, 253, .08);
}


/* =========================================================
   TABLE
========================================================= */

.year-table {
    margin-bottom: 0;
}

.year-table thead th {
    padding: 13px 18px;

    background: #f8f9fa;

    border-bottom: 1px solid #dee2e6;

    color: #495057;

    font-size: .76rem;
    font-weight: 600;

    white-space: nowrap;
}

.year-table tbody td {
    padding: 14px 18px;

    color: #343a40;

    font-size: .84rem;

    vertical-align: middle;

    border-bottom: 1px solid #f0f1f2;
}

.year-table tbody tr:last-child td {
    border-bottom: 0;
}

.year-table tbody tr:hover {
    background-color: #f8fbff;
}


/* =========================================================
   YEAR LEVEL
========================================================= */

.year-name {
    font-weight: 500;

    color: #212529;
}


/* =========================================================
   PROGRAM BADGE
========================================================= */

.year-program {
    display: inline-block;

    padding: 5px 9px;

    background: #eaf2ff;

    border: 1px solid #cfe2ff;

    border-radius: 5px;

    color: #0d6efd;

    font-size: .74rem;
    font-weight: 600;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.year-description {
    color: #6c757d;

    max-width: 450px;

    line-height: 1.4;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.year-actions {
    white-space: nowrap;
}

.year-actions .btn {
    width: 32px;
    height: 32px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    border-radius: 5px;
}

.year-actions form {
    display: inline-block;

    margin-left: 4px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.year-empty {
    padding: 55px 20px;

    text-align: center;
}

.year-empty i {
    display: block;

    margin-bottom: 12px;

    color: #adb5bd;

    font-size: 38px;
}

.year-empty h6 {
    color: #343a40;

    font-weight: 600;
}

.year-empty p {
    margin-bottom: 18px;

    color: #6c757d;

    font-size: .84rem;
}


/* =========================================================
   MODAL
========================================================= */

.year-modal .modal-header {
    background: #0d6efd;

    color: #fff;
}

.year-modal .modal-title {
    font-size: 1rem;

    font-weight: 600;
}

.year-modal .form-label {
    font-size: .84rem;

    font-weight: 500;
}

.year-modal .form-control,
.year-modal .form-select {
    font-size: .85rem;
}

.year-modal .modal-footer {
    background: #f8f9fa;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .year-page-header {
        align-items: flex-start !important;

        flex-direction: column;
    }

    .year-add-btn {
        width: 100%;

        margin-top: 12px;
    }

    .year-search {
        width: 100%;
    }

    .year-toolbar > div {
        flex-direction: column;

        align-items: stretch !important;

        gap: 12px;
    }

}

</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="year-page-header d-flex justify-content-between align-items-center">

    <div>

        <div class="year-page-title">

            <i class="fas fa-layer-group"></i>

            <span>Year Levels</span>

        </div>


        <!-- Breadcrumb -->

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb year-breadcrumb">

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

                    Year Levels

                </li>

            </ol>

        </nav>

    </div>


    <!-- Add Button -->

    <button
        type="button"
        class="btn btn-primary year-add-btn"
        data-bs-toggle="modal"
        data-bs-target="#modalYear"
        onclick="resetYearForm()"
    >

        <i class="fas fa-plus me-2"></i>

        Add Year Level

    </button>

</div>


<!-- =========================================================
     MAIN CARD
========================================================= -->

<div class="year-card">


    <!-- =====================================================
         TOOLBAR
    ====================================================== -->

    <div class="year-toolbar">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <div class="year-toolbar-label">

                    Year Level List

                </div>

                <small class="text-muted">

                    Manage year levels assigned to each program

                </small>

            </div>


            <!-- Search -->

            <div class="year-search">

                <div class="input-group input-group-sm">

                    <span class="input-group-text">

                        <i class="fas fa-search"></i>

                    </span>

                    <input
                        type="text"
                        id="yearSearch"
                        class="form-control"
                        placeholder="Search year levels..."
                        autocomplete="off"
                    >

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         TABLE
    ====================================================== -->

    <?php if (!empty($list)): ?>

        <div class="table-responsive">

            <table
                class="table year-table table-hover"
                id="yearTable"
            >

                <thead>

                    <tr>

                        <th style="width: 230px;">
                            Year Level
                        </th>

                        <th>
                            Description
                        </th>

                        <th style="width: 230px;">
                            Program
                        </th>

                        <th
                            style="width: 120px;"
                            class="text-center"
                        >
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($list as $y): ?>

                    <tr>


                        <!-- YEAR LEVEL -->

                        <td>

                            <span class="year-name">

                                <?= clean($y['yearName']) ?>

                            </span>

                        </td>


                        <!-- DESCRIPTION -->

                        <td>

                            <?php if (!empty($y['yearDesc'])): ?>

                                <span class="year-description">

                                    <?= clean($y['yearDesc']) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-muted">

                                    No description provided

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- PROGRAM -->

                        <td>

                            <span class="year-program">

                                <?= clean($y['progName']) ?>

                            </span>

                        </td>


                        <!-- ACTIONS -->

                        <td class="text-center">

                            <div class="year-actions">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-ey"
                                    title="Edit Year Level"

                                    data-y="<?= htmlspecialchars(
                                        json_encode($y),
                                        ENT_QUOTES
                                    ) ?>"

                                    data-bs-toggle="modal"
                                    data-bs-target="#modalYear"
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
                                        name="yearID"
                                        value="<?= (int)$y['yearID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-delete"
                                        title="Delete Year Level"
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

        <div class="year-empty">

            <i class="fas fa-layer-group"></i>

            <h6>
                No Year Levels Found
            </h6>

            <p>
                There are currently no active year levels.
            </p>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalYear"
                onclick="resetYearForm()"
            >

                <i class="fas fa-plus me-1"></i>

                Add Year Level

            </button>

        </div>


    <?php endif; ?>

</div>


<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal fade year-modal"
    id="modalYear"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form
                method="POST"
                id="yearForm"
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
                    id="yAction"
                >

                <input
                    type="hidden"
                    name="yearID"
                    id="yID"
                >


                <!-- MODAL HEADER -->

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="yTitle"
                    >

                        <i class="fas fa-layer-group me-2"></i>

                        Add Year Level

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


                    <!-- PROGRAM -->

                    <div class="mb-3">

                        <label
                            for="y_prog"
                            class="form-label"
                        >

                            Program

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <select
                            name="progID"
                            id="y_prog"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Program --
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


                    <!-- YEAR LEVEL NAME -->

                    <div class="mb-3">

                        <label
                            for="y_name"
                            class="form-label"
                        >

                            Year Level Name

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <input
                            type="text"
                            name="yearName"
                            id="y_name"
                            class="form-control"
                            required
                            placeholder="e.g. Grade 11"
                        >

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="mb-0">

                        <label
                            for="y_desc"
                            class="form-label"
                        >

                            Description

                        </label>

                        <input
                            type="text"
                            name="yearDesc"
                            id="y_desc"
                            class="form-control"
                            placeholder="e.g. Senior High Grade 11"
                        >

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

                        <span id="ySaveText">
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
   RESET FORM
========================================================= */

function resetYearForm() {

    document.getElementById('yearForm').reset();

    document.getElementById('yAction').value =
        'add';

    document.getElementById('yID').value =
        '';

    document.getElementById('yTitle').innerHTML =
        '<i class="fas fa-layer-group me-2"></i>Add Year Level';

    document.getElementById('ySaveText').textContent =
        'Save';

}


/* =========================================================
   EDIT YEAR LEVEL
========================================================= */

document.querySelectorAll('.btn-ey').forEach(function(btn) {

    btn.addEventListener('click', function() {

        var y = JSON.parse(
            this.getAttribute('data-y')
        );

        document.getElementById('yAction').value =
            'edit';

        document.getElementById('yID').value =
            y.yearID || '';

        document.getElementById('yTitle').innerHTML =
            '<i class="fas fa-edit me-2"></i>Edit Year Level';

        document.getElementById('ySaveText').textContent =
            'Update';

        document.getElementById('y_prog').value =
            y.progID || '';

        document.getElementById('y_name').value =
            y.yearName || '';

        document.getElementById('y_desc').value =
            y.yearDesc || '';

    });

});


/* =========================================================
   SEARCH
========================================================= */

var searchInput =
    document.getElementById('yearSearch');

if (searchInput) {

    searchInput.addEventListener('input', function() {

        var value =
            this.value.toLowerCase().trim();

        document
            .querySelectorAll('#yearTable tbody tr')
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

document.querySelectorAll('.btn-delete').forEach(function(btn) {

    btn.addEventListener('click', function(event) {

        if (!confirm(
            'Are you sure you want to remove this year level?'
        )) {

            event.preventDefault();

        }

    });

});

</script>


<?php include '../includes/footer.php'; ?>