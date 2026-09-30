<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Strands';
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
            trim($_POST['strandName'] ?? ''),
            trim($_POST['strandDesc'] ?? ''),
            (int)($_POST['progID'] ?? 0)
        ];

        if (!$d[0] || !$d[2]) {

            $_SESSION['flash_error'] = 'Fill required fields.';

        } elseif ($action === 'add') {

            $db->prepare("
                INSERT INTO strands
                (strandName, strandDesc, progID)
                VALUES (?, ?, ?)
            ")->execute($d);

            $_SESSION['flash_success'] = 'Strand added.';

        } else {

            $id = (int)$_POST['strandID'];

            $d[] = $id;

            $db->prepare("
                UPDATE strands
                SET strandName = ?,
                    strandDesc = ?,
                    progID = ?
                WHERE strandID = ?
            ")->execute($d);

            $_SESSION['flash_success'] = 'Updated.';
        }

    } elseif ($action === 'delete') {

        $db->prepare("
            UPDATE strands
            SET dateDeleted = NOW()
            WHERE strandID = ?
        ")->execute([
            (int)$_POST['strandID']
        ]);

        $_SESSION['flash_success'] = 'Removed.';
    }

    header('Location: strands.php');
    exit;
}


/* =========================================================
   GET STRANDS
========================================================= */

$list = $db->query("
    SELECT
        s.*,
        p.progName
    FROM strands s
    JOIN programs p
        ON s.progID = p.progID
    WHERE s.dateDeleted IS NULL
    ORDER BY p.progName, s.strandName
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


<!-- =========================================================
     PAGE DESIGN
========================================================= -->

<style>

/* Page header */
.strand-page-header {
    margin-bottom: 26px;
}

.strand-page-title {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 5px;

    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}

.strand-page-title i {
    color: #0d6efd;
}


/* Breadcrumb */
.strand-breadcrumb {
    margin: 0;
    padding: 0;

    font-size: .82rem;
}

.strand-breadcrumb a {
    color: #0d6efd;
    text-decoration: none;
}

.strand-breadcrumb a:hover {
    text-decoration: underline;
}

.strand-breadcrumb .breadcrumb-item.active {
    color: #6c757d;
}


/* Add button */
.strand-add-btn {
    font-size: .875rem;
    padding: .5rem .9rem;
}


/* Main card */
.strand-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;

    background: #fff;

    box-shadow: 0 .125rem .35rem rgba(0, 0, 0, .05);
}


/* Toolbar */
.strand-toolbar {
    padding: 16px 18px;

    border-bottom: 1px solid #e9ecef;

    background: #fff;
}

.strand-toolbar-label {
    font-size: .8rem;
    font-weight: 600;

    color: #495057;

    margin-bottom: 7px;
}


/* Search */
.strand-search {
    width: 280px;
}

.strand-search .input-group-text {
    background: #fff;

    border-right: 0;

    color: #6c757d;
}

.strand-search .form-control {
    border-left: 0;

    font-size: .85rem;
}

.strand-search .form-control:focus {
    border-color: #dee2e6;

    box-shadow: none;
}

.strand-search .input-group:focus-within {
    box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .08);
}


/* Table */
.strand-table {
    margin-bottom: 0;
}

.strand-table thead th {
    padding: 13px 18px;

    background: #f8f9fa;

    border-bottom: 1px solid #dee2e6;

    color: #495057;

    font-size: .76rem;
    font-weight: 600;

    white-space: nowrap;
}

.strand-table tbody td {
    padding: 14px 18px;

    color: #343a40;

    font-size: .84rem;

    vertical-align: middle;

    border-bottom: 1px solid #f0f1f2;
}

.strand-table tbody tr:last-child td {
    border-bottom: 0;
}

.strand-table tbody tr:hover {
    background-color: #f8fbff;
}


/* Strand name */
.strand-name {
    font-weight: 500;

    color: #212529;
}


/* Program badge */
.strand-program {
    display: inline-block;

    padding: 5px 9px;

    background: #eaf2ff;

    border: 1px solid #cfe2ff;

    border-radius: 5px;

    color: #0d6efd;

    font-size: .74rem;
    font-weight: 600;
}


/* Description */
.strand-description {
    color: #6c757d;

    max-width: 480px;

    line-height: 1.4;
}


/* Action buttons */
.strand-actions {
    white-space: nowrap;
}

.strand-actions .btn {
    width: 32px;
    height: 32px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    border-radius: 5px;
}

.strand-actions .btn + form {
    margin-left: 4px;
}


/* Empty state */
.strand-empty {
    padding: 55px 20px;

    text-align: center;
}

.strand-empty i {
    display: block;

    margin-bottom: 12px;

    color: #adb5bd;

    font-size: 38px;
}

.strand-empty h6 {
    color: #343a40;

    font-weight: 600;
}

.strand-empty p {
    color: #6c757d;

    font-size: .84rem;

    margin-bottom: 18px;
}


/* Modal */
.strand-modal .modal-header {
    background: #0d6efd;

    color: #fff;
}

.strand-modal .modal-title {
    font-size: 1rem;

    font-weight: 600;
}

.strand-modal .form-label {
    font-size: .84rem;

    font-weight: 500;
}

.strand-modal .form-control,
.strand-modal .form-select {
    font-size: .85rem;
}

.strand-modal .modal-footer {
    background: #f8f9fa;
}


/* Responsive */
@media (max-width: 768px) {

    .strand-page-header {
        align-items: flex-start !important;

        flex-direction: column;
    }

    .strand-add-btn {
        width: 100%;

        margin-top: 12px;
    }

    .strand-search {
        width: 100%;
    }

    .strand-toolbar > div {
        flex-direction: column;

        align-items: stretch !important;

        gap: 12px;
    }

}

</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="strand-page-header d-flex justify-content-between align-items-center">

    <div>

        <div class="strand-page-title">

            <i class="fas fa-stream"></i>

            <span>Strands</span>

        </div>


        <!-- Breadcrumb -->

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb strand-breadcrumb">

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
                    Strands
                </li>

            </ol>

        </nav>

    </div>


    <!-- Add Strand -->

    <button
        type="button"
        class="btn btn-primary strand-add-btn"
        data-bs-toggle="modal"
        data-bs-target="#modalStrand"
        onclick="resetStrandForm()"
    >

        <i class="fas fa-plus me-2"></i>

        Add Strand

    </button>

</div>


<!-- =========================================================
     STRAND CARD
========================================================= -->

<div class="strand-card">


    <!-- =====================================================
         TOOLBAR / SEARCH
    ====================================================== -->

    <div class="strand-toolbar">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <div class="strand-toolbar-label">
                    Strand List
                </div>

                <small class="text-muted">
                    Manage strands under each program
                </small>

            </div>


            <!-- Search -->

            <div class="strand-search">

                <div class="input-group input-group-sm">

                    <span class="input-group-text">

                        <i class="fas fa-search"></i>

                    </span>

                    <input
                        type="text"
                        id="strandSearch"
                        class="form-control"
                        placeholder="Search strands..."
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
                class="table strand-table table-hover"
                id="strandTable"
            >

                <thead>

                    <tr>

                        <th style="width: 250px;">
                            Strand Name
                        </th>

                        <th style="width: 230px;">
                            Program
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

                <?php foreach ($list as $s): ?>

                    <tr>


                        <!-- STRAND NAME -->

                        <td>

                            <span class="strand-name">
                                <?= clean($s['strandName']) ?>
                            </span>

                        </td>


                        <!-- PROGRAM -->

                        <td>

                            <span class="strand-program">

                                <?= clean($s['progName']) ?>

                            </span>

                        </td>


                        <!-- DESCRIPTION -->

                        <td>

                            <?php if (!empty($s['strandDesc'])): ?>

                                <span class="strand-description">

                                    <?= clean($s['strandDesc']) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-muted">
                                    No description provided
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ACTIONS -->

                        <td class="text-center">

                            <div class="strand-actions">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-edit-strand"
                                    title="Edit Strand"

                                    data-s="<?= htmlspecialchars(
                                        json_encode($s),
                                        ENT_QUOTES
                                    ) ?>"

                                    data-bs-toggle="modal"
                                    data-bs-target="#modalStrand"
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
                                        name="strandID"
                                        value="<?= (int)$s['strandID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-delete"
                                        title="Delete Strand"
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

        <div class="strand-empty">

            <i class="fas fa-stream"></i>

            <h6>
                No Strands Found
            </h6>

            <p>
                There are currently no active strands.
            </p>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalStrand"
                onclick="resetStrandForm()"
            >

                <i class="fas fa-plus me-1"></i>

                Add Strand

            </button>

        </div>


    <?php endif; ?>

</div>


<!-- =========================================================
     ADD / EDIT STRAND MODAL
========================================================= -->

<div
    class="modal fade strand-modal"
    id="modalStrand"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form
                method="POST"
                id="strandForm"
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
                    id="stAction"
                >

                <input
                    type="hidden"
                    name="strandID"
                    id="stID"
                >


                <!-- MODAL HEADER -->

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="stTitle"
                    >

                        <i class="fas fa-stream me-2"></i>

                        Add Strand

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
                            for="st_prog"
                            class="form-label"
                        >

                            Program

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <select
                            name="progID"
                            id="st_prog"
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


                    <!-- STRAND NAME -->

                    <div class="mb-3">

                        <label
                            for="st_name"
                            class="form-label"
                        >

                            Strand Name

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <input
                            type="text"
                            name="strandName"
                            id="st_name"
                            class="form-control"
                            required
                            placeholder="Enter strand name"
                        >

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="mb-0">

                        <label
                            for="st_desc"
                            class="form-label"
                        >

                            Description

                        </label>

                        <textarea
                            name="strandDesc"
                            id="st_desc"
                            class="form-control"
                            rows="3"
                            placeholder="Enter strand description"
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

                        <span id="stSaveText">
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
   RESET FORM FOR ADD
========================================================= */

function resetStrandForm() {

    document.getElementById('strandForm').reset();

    document.getElementById('stAction').value =
        'add';

    document.getElementById('stID').value =
        '';

    document.getElementById('stTitle').innerHTML =
        '<i class="fas fa-stream me-2"></i>Add Strand';

    document.getElementById('stSaveText').textContent =
        'Save';

}


/* =========================================================
   EDIT STRAND
========================================================= */

document.querySelectorAll('.btn-edit-strand').forEach(function(btn) {

    btn.addEventListener('click', function() {

        var s = JSON.parse(
            this.getAttribute('data-s')
        );

        document.getElementById('stAction').value =
            'edit';

        document.getElementById('stID').value =
            s.strandID || '';

        document.getElementById('stTitle').innerHTML =
            '<i class="fas fa-edit me-2"></i>Edit Strand';

        document.getElementById('stSaveText').textContent =
            'Update';

        document.getElementById('st_prog').value =
            s.progID || '';

        document.getElementById('st_name').value =
            s.strandName || '';

        document.getElementById('st_desc').value =
            s.strandDesc || '';

    });

});


/* =========================================================
   SEARCH
========================================================= */

var searchInput =
    document.getElementById('strandSearch');

if (searchInput) {

    searchInput.addEventListener('input', function() {

        var value =
            this.value.toLowerCase().trim();

        document
            .querySelectorAll('#strandTable tbody tr')
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
            'Are you sure you want to remove this strand?'
        )) {

            event.preventDefault();

        }

    });

});

</script>


<?php include '../includes/footer.php'; ?>

