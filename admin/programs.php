<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Programs';
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
            trim($_POST['progName'] ?? ''),
            trim($_POST['progCode'] ?? ''),
            trim($_POST['progDesc'] ?? '')
        ];

        if (!$d[0] || !$d[1]) {

            $_SESSION['flash_error'] = 'Fill required fields.';

        } elseif ($action === 'add') {

            $db->prepare("
                INSERT INTO programs
                (progName, progCode, progDesc)
                VALUES (?, ?, ?)
            ")->execute($d);

            $_SESSION['flash_success'] = 'Program added.';

        } else {

            $id = (int) $_POST['progID'];

            $d[] = $id;

            $db->prepare("
                UPDATE programs
                SET progName = ?,
                    progCode = ?,
                    progDesc = ?
                WHERE progID = ?
            ")->execute($d);

            $_SESSION['flash_success'] = 'Updated.';
        }

    } elseif ($action === 'delete') {

        $db->prepare("
            UPDATE programs
            SET dateDeleted = NOW()
            WHERE progID = ?
        ")->execute([
            (int) $_POST['progID']
        ]);

        $_SESSION['flash_success'] = 'Removed.';
    }

    header('Location: programs.php');
    exit;
}


/* =========================================================
   GET PROGRAMS
========================================================= */

$list = $db->query("
    SELECT *
    FROM programs
    WHERE dateDeleted IS NULL
    ORDER BY progName
")->fetchAll();


include '../includes/header.php';
?>


<!-- =========================================================
     PAGE DESIGN
========================================================= -->

<style>

/* Header */
.program-page-header {
    margin-bottom: 26px;
}

.program-page-title {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 5px;

    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}

.program-page-title i {
    color: #0d6efd;
}


/* Breadcrumb */
.program-breadcrumb {
    margin: 0;
    padding: 0;

    font-size: .82rem;
}

.program-breadcrumb a {
    color: #0d6efd;
    text-decoration: none;
}

.program-breadcrumb a:hover {
    text-decoration: underline;
}

.program-breadcrumb .breadcrumb-item.active {
    color: #6c757d;
}


/* Add button */
.program-add-btn {
    font-size: .875rem;
    padding: .5rem .9rem;
}


/* Main card */
.program-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;

    background: #fff;

    box-shadow: 0 .125rem .35rem rgba(0,0,0,.05);
}


/* Search section */
.program-toolbar {
    padding: 16px 18px;

    border-bottom: 1px solid #e9ecef;

    background: #fff;
}

.program-toolbar-label {
    font-size: .8rem;
    font-weight: 600;

    color: #495057;

    margin-bottom: 7px;
}

.program-search {
    width: 280px;
}

.program-search .input-group-text {
    background: #fff;
    border-right: 0;

    color: #6c757d;
}

.program-search .form-control {
    border-left: 0;

    font-size: .85rem;
}

.program-search .form-control:focus {
    border-color: #dee2e6;
    box-shadow: none;
}

.program-search .input-group:focus-within {
    box-shadow: 0 0 0 .15rem rgba(13,110,253,.08);
}


/* Table */
.program-table {
    margin-bottom: 0;
}

.program-table thead th {
    padding: 13px 18px;

    background: #f8f9fa;

    border-bottom: 1px solid #dee2e6;

    color: #495057;

    font-size: .76rem;
    font-weight: 600;

    white-space: nowrap;
}

.program-table tbody td {
    padding: 14px 18px;

    color: #343a40;

    font-size: .84rem;

    vertical-align: middle;

    border-bottom: 1px solid #f0f1f2;
}

.program-table tbody tr:last-child td {
    border-bottom: 0;
}

.program-table tbody tr:hover {
    background-color: #f8fbff;
}


/* Program code */
.program-code {
    display: inline-block;

    padding: 4px 8px;

    background: #f1f3f5;

    border: 1px solid #dee2e6;

    border-radius: 5px;

    color: #495057;

    font-family:  Roboto;

    font-size: .75rem;
    font-weight: 600;
}


/* Program name */
.program-name {
    font-weight: 500;
    color: #212529;
}


/* Description */
.program-description {
    color: #6c757d;

    max-width: 480px;

    line-height: 1.4;
}


/* Action buttons */
.program-actions {
    white-space: nowrap;
}

.program-actions .btn {
    width: 32px;
    height: 32px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    border-radius: 5px;
}

.program-actions .btn + form {
    margin-left: 4px;
}


/* Empty state */
.program-empty {
    padding: 55px 20px;

    text-align: center;
}

.program-empty i {
    font-size: 38px;

    color: #adb5bd;

    margin-bottom: 12px;
}

.program-empty h6 {
    color: #343a40;

    font-weight: 600;
}

.program-empty p {
    color: #6c757d;

    font-size: .84rem;

    margin-bottom: 18px;
}


/* Modal */
.program-modal .modal-header {
    background: #0d6efd;
    color: #fff;
}

.program-modal .modal-title {
    font-size: 1rem;
    font-weight: 600;
}

.program-modal .form-label {
    font-size: .84rem;
    font-weight: 500;
}

.program-modal .form-control {
    font-size: .85rem;
}

.program-modal .modal-footer {
    background: #f8f9fa;
}


/* Responsive */
@media (max-width: 768px) {

    .program-page-header {
        align-items: flex-start !important;

        flex-direction: column;
    }

    .program-add-btn {
        margin-top: 12px;

        width: 100%;
    }

    .program-search {
        width: 100%;
    }

}

</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="program-page-header d-flex justify-content-between align-items-center">

    <div>

        <div class="program-page-title">

            <i class="fas fa-book"></i>

            <span>Programs</span>

        </div>


        <!-- Breadcrumb -->

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb program-breadcrumb">

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
                    Programs
                </li>

            </ol>

        </nav>

    </div>


    <!-- Add Program -->

    <button
        type="button"
        class="btn btn-primary program-add-btn"
        data-bs-toggle="modal"
        data-bs-target="#modalProg"
        onclick="resetProgramForm()"
    >

        <i class="fas fa-plus me-2"></i>

        Add Program

    </button>

</div>


<!-- =========================================================
     PROGRAM CARD
========================================================= -->

<div class="program-card">


    <!-- =====================================================
         SEARCH / TOOLBAR
    ====================================================== -->

    <div class="program-toolbar">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <div class="program-toolbar-label">
                    Program List
                </div>

                <small class="text-muted">
                    Manage all active programs
                </small>

            </div>


            <!-- Search -->

            <div class="program-search">

                <div class="input-group input-group-sm">

                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>

                    <input
                        type="text"
                        id="programSearch"
                        class="form-control"
                        placeholder="Search programs..."
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
                class="table program-table table-hover"
                id="programTable"
            >

                <thead>

                    <tr>

                        <th style="width: 130px;">
                            Code
                        </th>

                        <th style="width: 260px;">
                            Program Name
                        </th>

                        <th>
                            Description
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

                <?php foreach ($list as $p): ?>

                    <tr>


                        <!-- CODE -->

                        <td>

                            <span class="program-code">
                                <?= clean($p['progCode']) ?>
                            </span>

                        </td>


                        <!-- NAME -->

                        <td>

                            <span class="program-name">
                                <?= clean($p['progName']) ?>
                            </span>

                        </td>


                        <!-- DESCRIPTION -->

                        <td>

                            <?php if (!empty($p['progDesc'])): ?>

                                <span class="program-description">
                                    <?= clean($p['progDesc']) ?>
                                </span>

                            <?php else: ?>

                                <span class="text-muted">
                                    No description provided
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ACTIONS -->

                        <td class="text-center">

                            <div class="program-actions">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-edit-prog"
                                    title="Edit Program"

                                    data-prog="<?= htmlspecialchars(
                                        json_encode($p),
                                        ENT_QUOTES
                                    ) ?>"

                                    data-bs-toggle="modal"
                                    data-bs-target="#modalProg"
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
                                        name="progID"
                                        value="<?= (int)$p['progID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-delete"
                                        title="Delete Program"
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

        <div class="program-empty">

            <i class="fas fa-book-open d-block"></i>

            <h6>
                No Programs Found
            </h6>

            <p>
                There are currently no active programs.
            </p>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalProg"
                onclick="resetProgramForm()"
            >

                <i class="fas fa-plus me-1"></i>

                Add Program

            </button>

        </div>


    <?php endif; ?>

</div>


<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal fade program-modal"
    id="modalProg"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form
                method="POST"
                id="progForm"
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
                    id="progAction"
                >

                <input
                    type="hidden"
                    name="progID"
                    id="progID"
                >


                <!-- MODAL HEADER -->

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="progTitle"
                    >

                        <i class="fas fa-book me-2"></i>

                        Add Program

                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <!-- MODAL BODY -->

                <div class="modal-body">


                    <div class="mb-3">

                        <label
                            for="p_code"
                            class="form-label"
                        >

                            Program Code
                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="text"
                            name="progCode"
                            id="p_code"
                            class="form-control"
                            required
                            placeholder="e.g. TVL-ICT"
                        >

                    </div>


                    <div class="mb-3">

                        <label
                            for="p_name"
                            class="form-label"
                        >

                            Program Name
                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="text"
                            name="progName"
                            id="p_name"
                            class="form-control"
                            required
                            placeholder="Enter program name"
                        >

                    </div>


                    <div class="mb-0">

                        <label
                            for="p_desc"
                            class="form-label"
                        >
                            Description
                        </label>

                        <textarea
                            name="progDesc"
                            id="p_desc"
                            class="form-control"
                            rows="3"
                            placeholder="Enter program description"
                        ></textarea>

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

                        <span id="saveButtonText">
                            Save
                        </span>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

/* =========================================================
   RESET FORM
========================================================= */

function resetProgramForm() {

    document.getElementById('progForm').reset();

    document.getElementById('progAction').value = 'add';

    document.getElementById('progID').value = '';

    document.getElementById('progTitle').innerHTML =
        '<i class="fas fa-book me-2"></i>Add Program';

    document.getElementById('saveButtonText').textContent =
        'Save';

}


/* =========================================================
   EDIT PROGRAM
========================================================= */

document.querySelectorAll('.btn-edit-prog').forEach(function(btn) {

    btn.addEventListener('click', function() {

        var p = JSON.parse(
            this.getAttribute('data-prog')
        );

        document.getElementById('progAction').value =
            'edit';

        document.getElementById('progID').value =
            p.progID;

        document.getElementById('progTitle').innerHTML =
            '<i class="fas fa-edit me-2"></i>Edit Program';

        document.getElementById('saveButtonText').textContent =
            'Update';

        document.getElementById('p_code').value =
            p.progCode || '';

        document.getElementById('p_name').value =
            p.progName || '';

        document.getElementById('p_desc').value =
            p.progDesc || '';

    });

});


/* =========================================================
   SEARCH
========================================================= */

var searchInput =
    document.getElementById('programSearch');

if (searchInput) {

    searchInput.addEventListener('input', function() {

        var value =
            this.value.toLowerCase().trim();

        document
            .querySelectorAll('#programTable tbody tr')
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
            'Are you sure you want to remove this program?'
        )) {

            event.preventDefault();

        }

    });

});

</script>


<?php include '../includes/footer.php'; ?>