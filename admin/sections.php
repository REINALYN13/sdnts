
<?php
require_once '../includes/config.php';

requireRole([ROLE_ADMIN]);

$pageTitle = 'Manage Sections';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function redirectSections()
{
    header('Location: sections.php');
    exit;
}

function postInt($key, $default = 0)
{
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}

function postString($key)
{
    return trim($_POST[$key] ?? '');
}


/* =========================================================
   HANDLE POST REQUESTS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* -----------------------------------------------------
       CSRF VALIDATION
    ----------------------------------------------------- */

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {

        $_SESSION['flash_error'] =
            'Invalid security token. Please try again.';

        redirectSections();
    }

    $action = $_POST['action'] ?? '';


    /* =====================================================
       ADD / EDIT SECTION
    ===================================================== */

    if ($action === 'add' || $action === 'edit') {

        $sectionName  = postString('sectionName');
        $sectionDesc  = postString('sectionDesc');

        $sectionLimit = postInt('sectionLimit', 40);
        $yearID       = postInt('yearID');
        $progID       = postInt('progID');

        /*
         * Adviser is optional.
         * If no adviser is selected, save NULL.
         */
        $userID = postInt('userID');
        $userID = ($userID > 0) ? $userID : null;

        $sectionID = postInt('sectionID');


        /* -------------------------------------------------
           VALIDATION
        ------------------------------------------------- */

        if (
            $sectionName === '' ||
            $yearID <= 0 ||
            $progID <= 0
        ) {

            $_SESSION['flash_error'] =
                'Please fill in all required fields.';

            redirectSections();
        }


        if ($sectionLimit < 1 || $sectionLimit > 100) {

            $_SESSION['flash_error'] =
                'Section capacity must be between 1 and 100.';

            redirectSections();
        }


        /* =================================================
           CHECK DUPLICATE SECTION
        ================================================= */

        if ($action === 'add') {

            $check = $db->prepare("
                SELECT COUNT(*)
                FROM sections
                WHERE sectionName = ?
                  AND yearID = ?
                  AND progID = ?
                  AND ayID = ?
                  AND dateDeleted IS NULL
            ");

            $check->execute([
                $sectionName,
                $yearID,
                $progID,
                $ay['ayID']
            ]);

        } else {

            $check = $db->prepare("
                SELECT COUNT(*)
                FROM sections
                WHERE sectionName = ?
                  AND yearID = ?
                  AND progID = ?
                  AND ayID = ?
                  AND sectionID != ?
                  AND dateDeleted IS NULL
            ");

            $check->execute([
                $sectionName,
                $yearID,
                $progID,
                $ay['ayID'],
                $sectionID
            ]);
        }


        $duplicate = (int)$check->fetchColumn();


        if ($duplicate > 0) {

            $_SESSION['flash_error'] =
                'A section with the same name already exists for this program and year level.';

            redirectSections();
        }


        /* =================================================
           ADD SECTION
        ================================================= */

        if ($action === 'add') {

            $stmt = $db->prepare("
                INSERT INTO sections
                (
                    sectionName,
                    sectionDesc,
                    sectionLimit,
                    yearID,
                    progID,
                    userID,
                    ayID
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $sectionName,
                $sectionDesc,
                $sectionLimit,
                $yearID,
                $progID,
                $userID,
                $ay['ayID']
            ]);

            $_SESSION['flash_success'] =
                'Section "' . $sectionName . '" was successfully added.';

            redirectSections();
        }


        /* =================================================
           EDIT SECTION
        ================================================= */

        if ($action === 'edit') {

            if ($sectionID <= 0) {

                $_SESSION['flash_error'] =
                    'Invalid section selected.';

                redirectSections();
            }


            /* ---------------------------------------------
               GET CURRENT ENROLLMENT
            --------------------------------------------- */

            $countStudents = $db->prepare("
                SELECT COUNT(*)
                FROM registration
                WHERE sectionID = ?
                  AND ayID = ?
                  AND status = 'enrolled'
                  AND dateDeleted IS NULL
            ");

            $countStudents->execute([
                $sectionID,
                $ay['ayID']
            ]);

            $currentStudents =
                (int)$countStudents->fetchColumn();


            /* ---------------------------------------------
               PREVENT CAPACITY BELOW ENROLLMENT
            --------------------------------------------- */

            if ($sectionLimit < $currentStudents) {

                $_SESSION['flash_error'] =
                    "Section capacity cannot be lower than the current enrollment of {$currentStudents} student(s).";

                redirectSections();
            }


            /* ---------------------------------------------
               UPDATE SECTION
            --------------------------------------------- */

            $stmt = $db->prepare("
                UPDATE sections
                SET
                    sectionName = ?,
                    sectionDesc = ?,
                    sectionLimit = ?,
                    yearID = ?,
                    progID = ?,
                    userID = ?
                WHERE sectionID = ?
                  AND dateDeleted IS NULL
            ");

            $stmt->execute([
                $sectionName,
                $sectionDesc,
                $sectionLimit,
                $yearID,
                $progID,
                $userID,
                $sectionID
            ]);

            $_SESSION['flash_success'] =
                'Section "' . $sectionName . '" was successfully updated.';

            redirectSections();
        }
    }


    /* =====================================================
       DELETE SECTION
    ===================================================== */

    elseif ($action === 'delete') {

        $sectionID = postInt('sectionID');


        if ($sectionID <= 0) {

            $_SESSION['flash_error'] =
                'Invalid section selected.';

            redirectSections();
        }


        /* ---------------------------------------------
           CHECK ENROLLED STUDENTS
        --------------------------------------------- */

        $checkStudents = $db->prepare("
            SELECT COUNT(*)
            FROM registration
            WHERE sectionID = ?
              AND ayID = ?
              AND status = 'enrolled'
              AND dateDeleted IS NULL
        ");

        $checkStudents->execute([
            $sectionID,
            $ay['ayID']
        ]);

        $enrolled =
            (int)$checkStudents->fetchColumn();


        if ($enrolled > 0) {

            $_SESSION['flash_error'] =
                "This section cannot be removed because it currently has {$enrolled} enrolled student(s).";

            redirectSections();
        }


        /* ---------------------------------------------
           SOFT DELETE
        --------------------------------------------- */

        $delete = $db->prepare("
            UPDATE sections
            SET dateDeleted = NOW(),
                deletedBy = ?
            WHERE sectionID = ?
              AND dateDeleted IS NULL
        ");

        $delete->execute([
            $_SESSION['userID'],
            $sectionID
        ]);


        $_SESSION['flash_success'] =
            'Section was successfully removed.';

        redirectSections();
    }
}


/* =========================================================
   GET ACTIVE SECTIONS

   IMPORTANT:
   We use COUNT(CASE ... THEN 1 END)
   instead of r.registrationID because the registration
   table does not contain a registrationID column.
========================================================= */

$sql = "
    SELECT
        sec.*,

        p.progName,

        y.yearName,

        u.firstName,
        u.lastName,

        COUNT(
            CASE
                WHEN r.status = 'enrolled'
                 AND r.dateDeleted IS NULL
                THEN 1
            END
        ) AS enrolledCount

    FROM sections sec

    INNER JOIN programs p
        ON sec.progID = p.progID

    INNER JOIN year y
        ON sec.yearID = y.yearID

    LEFT JOIN users u
        ON sec.userID = u.userID

    LEFT JOIN registration r
        ON sec.sectionID = r.sectionID
       AND r.ayID = sec.ayID

    WHERE sec.ayID = ?
      AND sec.dateDeleted IS NULL

    GROUP BY
        sec.sectionID,
        p.progName,
        y.yearName,
        u.firstName,
        u.lastName

    ORDER BY
        p.progName ASC,
        y.yearName ASC,
        sec.sectionName ASC
";


$sections = $db->prepare($sql);

$sections->execute([
    $ay['ayID']
]);


/* =========================================================
   GET PROGRAMS
========================================================= */

$programs = $db->query("
    SELECT *
    FROM programs
    WHERE dateDeleted IS NULL
    ORDER BY progName ASC
")->fetchAll();


/* =========================================================
   GET YEAR LEVELS
========================================================= */

$yearLevels = $db->query("
    SELECT *
    FROM year
    WHERE dateDeleted IS NULL
    ORDER BY yearID ASC
")->fetchAll();


/* =========================================================
   GET TEACHERS
========================================================= */

$teachers = $db->query("
    SELECT *
    FROM users
    WHERE roleID = 3
      AND isActive = 1
      AND dateDeleted IS NULL
    ORDER BY lastName ASC, firstName ASC
")->fetchAll();


/* =========================================================
   HEADER
========================================================= */

include '../includes/header.php';

?>


<!-- =======================================================
     PAGE HEADER
======================================================= -->

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <div class="d-flex align-items-center gap-2">

            <div
                class="rounded-3 bg-primary bg-opacity-10
                       text-primary d-flex align-items-center
                       justify-content-center"
                style="width:48px;height:48px;"
            >
                <i class="fas fa-chalkboard fa-lg"></i>
            </div>

            <div>

                <h4 class="mb-1 fw-bold">
                    Manage Sections
                </h4>

                <nav aria-label="breadcrumb">

                    <ol class="breadcrumb mb-0 small">

                        <li class="breadcrumb-item">

                            <a
                                href="<?= SITE_URL ?>/dashboard.php"
                                class="text-decoration-none"
                            >
                                Home
                            </a>

                        </li>

                        <li class="breadcrumb-item active">
                            Sections
                        </li>

                    </ol>

                </nav>

            </div>

        </div>

    </div>


    <button
        type="button"
        class="btn btn-primary px-4"
        data-bs-toggle="modal"
        data-bs-target="#modalSection"
        id="btnAddSection"
    >

        <i class="fas fa-plus me-2"></i>

        Add Section

    </button>

</div>





    


    
    </div>

</div>



<!-- =======================================================
     SECTIONS TABLE
======================================================= -->

<div class="card border-0 shadow-sm overflow-hidden">


    <!-- CARD HEADER -->

    <div class="card-header bg-white border-0 py-3">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

            <div>

                <h5 class="mb-1 fw-semibold">
                    Section List
                </h5>

                <small class="text-muted">
                    Manage sections, advisers, and enrollment capacity.
                </small>

            </div>

        </div>

    </div>



    <!-- CARD BODY -->

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0 datatable">


                <!-- TABLE HEADER -->

                <thead class="table-light">

                    <tr>

                        <th class="ps-4">
                            Section
                        </th>

                        <th>
                            Program
                        </th>

                        <th>
                            Year Level
                        </th>

                        <th>
                            Adviser
                        </th>

                        <th>
                            Capacity
                        </th>

                        <th>
                            Enrollment
                        </th>

                        <th class="text-end pe-4">
                            Actions
                        </th>

                    </tr>

                </thead>



                <!-- TABLE BODY -->

                <tbody>

                <?php

                $hasSections = false;

                while ($s = $sections->fetch()):

                    $hasSections = true;


                    /* -------------------------------------
                       ENROLLMENT
                    ------------------------------------- */

                    $cnt =
                        (int)$s['enrolledCount'];

                    $limit =
                        max(
                            1,
                            (int)$s['sectionLimit']
                        );


                    $percentage =
                        min(
                            100,
                            round(
                                ($cnt / $limit) * 100
                            )
                        );


                    /* -------------------------------------
                       CAPACITY STATUS
                    ------------------------------------- */

                    if ($cnt >= $limit) {

                        $capacityClass = 'danger';
                        $capacityText = 'Full';

                    } elseif ($percentage >= 80) {

                        $capacityClass = 'warning';
                        $capacityText = 'Almost Full';

                    } else {

                        $capacityClass = 'success';
                        $capacityText = 'Available';
                    }


                    /* -------------------------------------
                       ADVISER
                    ------------------------------------- */

                    $adviser = '-';

                    if (
                        !empty($s['firstName']) ||
                        !empty($s['lastName'])
                    ) {

                        $adviser =
                            trim(
                                $s['lastName'] .
                                ', ' .
                                $s['firstName']
                            );
                    }


                    /* -------------------------------------
                       DATA FOR EDIT MODAL
                    ------------------------------------- */

                    $sectionData = [

                        'sectionID' =>
                            $s['sectionID'],

                        'sectionName' =>
                            $s['sectionName'],

                        'sectionDesc' =>
                            $s['sectionDesc'],

                        'sectionLimit' =>
                            $s['sectionLimit'],

                        'yearID' =>
                            $s['yearID'],

                        'progID' =>
                            $s['progID'],

                        'userID' =>
                            $s['userID']

                    ];

                ?>


                    <tr>


                        <!-- SECTION -->

                        <td class="ps-4">

                            <div class="d-flex align-items-center">

                                <div
                                    class="rounded-3 bg-light
                                           d-flex align-items-center
                                           justify-content-center me-3"
                                    style="width:42px;height:42px;"
                                >

                                    <i class="fas fa-users text-primary"></i>

                                </div>


                                <div>

                                    <div class="fw-semibold">

                                        <?= clean(
                                            $s['sectionName']
                                        ) ?>

                                    </div>


                                    <?php if (!empty($s['sectionDesc'])): ?>

                                        <small
                                            class="text-muted"
                                            title="<?= clean(
                                                $s['sectionDesc']
                                            ) ?>"
                                        >

                                            <?= clean(
                                                mb_strimwidth(
                                                    $s['sectionDesc'],
                                                    0,
                                                    55,
                                                    '...'
                                                )
                                            ) ?>

                                        </small>

                                    <?php else: ?>

                                        <small class="text-muted">
                                            No description
                                        </small>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </td>



                        <!-- PROGRAM -->

                        <td>

                            <span
                                class="badge bg-primary
                                       bg-opacity-10 text-primary"
                            >

                                <?= clean(
                                    $s['progName']
                                ) ?>

                            </span>

                        </td>



                        <!-- YEAR LEVEL -->

                        <td>

                            <span class="fw-medium">

                                <?= clean(
                                    $s['yearName']
                                ) ?>

                            </span>

                        </td>



                        <!-- ADVISER -->

                        <td>

                            <?php if ($adviser !== '-'): ?>

                                <div class="d-flex align-items-center">

                                    <div
                                        class="rounded-circle bg-secondary
                                               bg-opacity-10 text-secondary
                                               d-flex align-items-center
                                               justify-content-center me-2"
                                        style="width:34px;height:34px;"
                                    >

                                        <i class="fas fa-user"></i>

                                    </div>


                                    <span>

                                        <?= clean(
                                            $adviser
                                        ) ?>

                                    </span>

                                </div>

                            <?php else: ?>

                                <span class="text-muted">

                                    <i class="fas fa-minus me-1"></i>

                                    No adviser

                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- CAPACITY -->

                        <td>

                            <span class="fw-semibold">

                                <?= $limit ?>

                            </span>

                            <small class="text-muted">
                                students
                            </small>

                        </td>



                        <!-- ENROLLMENT -->

                        <td style="min-width:160px;">

                            <div
                                class="d-flex
                                       justify-content-between
                                       mb-1"
                            >

                                <small class="fw-semibold">

                                    <?= $cnt ?>
                                    /
                                    <?= $limit ?>

                                </small>


                                <small
                                    class="text-<?= $capacityClass ?>"
                                >

                                    <?= $capacityText ?>

                                </small>

                            </div>


                            <div
                                class="progress"
                                style="height:6px;"
                                title="<?= $percentage ?>% occupied"
                            >

                                <div
                                    class="progress-bar bg-<?= $capacityClass ?>"
                                    role="progressbar"
                                    style="width:<?= $percentage ?>%;"
                                    aria-valuenow="<?= $percentage ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                ></div>

                            </div>

                        </td>



                        <!-- ACTIONS -->

                        <td class="text-end pe-4">

                            <div class="btn-group btn-group-sm">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-edit-sec"
                                    data-sec="<?= htmlspecialchars(
                                        json_encode(
                                            $sectionData,
                                            JSON_UNESCAPED_UNICODE
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalSection"
                                    title="Edit Section"
                                >

                                    <i class="fas fa-edit"></i>

                                </button>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirmDelete(
                                        '<?= htmlspecialchars(
                                            $s['sectionName'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>'
                                    );"
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
                                        name="sectionID"
                                        value="<?= $s['sectionID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger"
                                        title="Remove Section"
                                    >

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>


                <?php endwhile; ?>


                <!-- EMPTY STATE -->

                <?php if (!$hasSections): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="text-center py-5"
                        >

                            <div class="mb-3">

                                <div
                                    class="rounded-circle bg-light
                                           d-inline-flex
                                           align-items-center
                                           justify-content-center"
                                    style="width:70px;height:70px;"
                                >

                                    <i
                                        class="fas fa-chalkboard
                                               fa-2x text-muted"
                                    ></i>

                                </div>

                            </div>


                            <h6 class="fw-semibold">
                                No Sections Found
                            </h6>


                            <p class="text-muted mb-3">

                                There are no active sections
                                for the current academic year.

                            </p>


                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#modalSection"
                            >

                                <i class="fas fa-plus me-2"></i>

                                Create First Section

                            </button>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>



<!-- =======================================================
     ADD / EDIT SECTION MODAL
======================================================= -->

<div
    class="modal fade"
    id="modalSection"
    tabindex="-1"
    aria-labelledby="secTitle"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered"
    >

        <div
            class="modal-content border-0 shadow"
        >

            <form
                method="POST"
                id="secForm"
                autocomplete="off"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $csrf ?>"
                >


                <!-- ACTION -->

                <input
                    type="hidden"
                    name="action"
                    value="add"
                    id="secAction"
                >


                <!-- SECTION ID -->

                <input
                    type="hidden"
                    name="sectionID"
                    id="secID"
                    value=""
                >



                <!-- =================================================
                     MODAL HEADER
                ================================================= -->

                <div
                    class="modal-header bg-primary text-white"
                >

                    <div class="d-flex align-items-center">

                        <div
                            class="rounded-circle
                                   bg-white bg-opacity-10
                                   d-flex align-items-center
                                   justify-content-center me-3"
                            style="width:40px;height:40px;"
                        >

                            <i class="fas fa-chalkboard"></i>

                        </div>


                        <div>

                            <h5
                                class="modal-title mb-0"
                                id="secTitle"
                            >
                                Add Section
                            </h5>

                            <small class="opacity-75">
                                Create or update a section
                            </small>

                        </div>

                    </div>


                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>



                <!-- =================================================
                     MODAL BODY
                ================================================= -->

                <div class="modal-body p-4">


                    <!-- SECTION NAME -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold"
                            for="sec_name"
                        >

                            Section Name

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <div class="input-group">

                            <span class="input-group-text">

                                <i class="fas fa-users"></i>

                            </span>


                            <input
                                type="text"
                                name="sectionName"
                                id="sec_name"
                                class="form-control"
                                placeholder="e.g. Grade 7 - Rizal"
                                maxlength="100"
                                required
                            >

                        </div>

                    </div>



                    <!-- PROGRAM -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold"
                            for="sec_prog"
                        >

                            Program

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <select
                            name="progID"
                            id="sec_prog"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Program --
                            </option>


                            <?php foreach ($programs as $p): ?>

                                <option
                                    value="<?= $p['progID'] ?>"
                                >

                                    <?= clean(
                                        $p['progName']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- YEAR LEVEL -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold"
                            for="sec_year"
                        >

                            Year Level

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <select
                            name="yearID"
                            id="sec_year"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Year Level --
                            </option>


                            <?php foreach ($yearLevels as $y): ?>

                                <option
                                    value="<?= $y['yearID'] ?>"
                                >

                                    <?= clean(
                                        $y['yearName']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- ADVISER -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold"
                            for="sec_user"
                        >

                            Adviser

                            <span class="text-muted fw-normal">
                                (Optional)
                            </span>

                        </label>


                        <select
                            name="userID"
                            id="sec_user"
                            class="form-select"
                        >

                            <option value="">
                                -- No Adviser --
                            </option>


                            <?php foreach ($teachers as $t): ?>

                                <option
                                    value="<?= $t['userID'] ?>"
                                >

                                    <?= clean(
                                        $t['lastName'] .
                                        ', ' .
                                        $t['firstName']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- SECTION CAPACITY -->

                    <div class="mb-3">

                        <label
                            class="form-label fw-semibold"
                            for="sec_lim"
                        >

                            Section Capacity

                        </label>


                        <div class="input-group">

                            <input
                                type="number"
                                name="sectionLimit"
                                id="sec_lim"
                                class="form-control"
                                value="40"
                                min="1"
                                max="100"
                                required
                            >


                            <span class="input-group-text">
                                students
                            </span>

                        </div>


                        <div class="form-text">

                            Maximum number of students
                            allowed in this section.

                        </div>

                    </div>



                    <!-- DESCRIPTION -->

                    <div class="mb-0">

                        <label
                            class="form-label fw-semibold"
                            for="sec_desc"
                        >

                            Description

                            <span class="text-muted fw-normal">
                                (Optional)
                            </span>

                        </label>


                        <textarea
                            name="sectionDesc"
                            id="sec_desc"
                            class="form-control"
                            rows="3"
                            maxlength="255"
                            placeholder="Add a short description about this section..."
                        ></textarea>

                    </div>

                </div>



                <!-- =================================================
                     MODAL FOOTER
                ================================================= -->

                <div
                    class="modal-footer bg-light"
                >

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                        id="secSaveBtn"
                    >

                        <i class="fas fa-save me-2"></i>

                        Save Section

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>



<!-- =======================================================
     JAVASCRIPT
======================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {


    /* =====================================================
       ELEMENTS
    ===================================================== */

    const form =
        document.getElementById('secForm');

    const action =
        document.getElementById('secAction');

    const sectionID =
        document.getElementById('secID');

    const title =
        document.getElementById('secTitle');

    const name =
        document.getElementById('sec_name');

    const program =
        document.getElementById('sec_prog');

    const year =
        document.getElementById('sec_year');

    const adviser =
        document.getElementById('sec_user');

    const limit =
        document.getElementById('sec_lim');

    const description =
        document.getElementById('sec_desc');

    const saveBtn =
        document.getElementById('secSaveBtn');

    const addButton =
        document.getElementById('btnAddSection');

    const modal =
        document.getElementById('modalSection');



    /* =====================================================
       RESET FORM
    ===================================================== */

    function resetForm()
    {

        form.reset();

        action.value = 'add';

        sectionID.value = '';

        title.textContent =
            'Add Section';

        saveBtn.innerHTML =
            '<i class="fas fa-save me-2"></i>Save Section';

        limit.value = 40;

    }



    /* =====================================================
       ADD SECTION BUTTON
    ===================================================== */

    if (addButton)
    {

        addButton.addEventListener(
            'click',
            function ()
            {
                resetForm();
            }
        );

    }



    /* =====================================================
       EDIT SECTION
    ===================================================== */

    document
        .querySelectorAll('.btn-edit-sec')
        .forEach(function (btn)
        {

            btn.addEventListener(
                'click',
                function ()
                {

                    try
                    {

                        const section =
                            JSON.parse(
                                this.getAttribute(
                                    'data-sec'
                                )
                            );


                        action.value =
                            'edit';


                        sectionID.value =
                            section.sectionID || '';


                        title.textContent =
                            'Edit Section';


                        name.value =
                            section.sectionName || '';


                        program.value =
                            section.progID || '';


                        year.value =
                            section.yearID || '';


                        adviser.value =
                            section.userID || '';


                        limit.value =
                            section.sectionLimit || 40;


                        description.value =
                            section.sectionDesc || '';


                        saveBtn.innerHTML =
                            '<i class="fas fa-save me-2"></i>Update Section';

                    }
                    catch (error)
                    {

                        console.error(
                            'Unable to load section data:',
                            error
                        );

                        alert(
                            'Unable to load section information.'
                        );

                    }

                }
            );

        });



    /* =====================================================
       FORM VALIDATION
    ===================================================== */

    form.addEventListener(
        'submit',
        function (event)
        {

            const sectionName =
                name.value.trim();

            const sectionLimit =
                parseInt(
                    limit.value,
                    10
                );


            if (sectionName.length < 1)
            {

                event.preventDefault();

                name.focus();

                alert(
                    'Please enter a section name.'
                );

                return;

            }


            if (
                isNaN(sectionLimit) ||
                sectionLimit < 1 ||
                sectionLimit > 100
            )
            {

                event.preventDefault();

                limit.focus();

                alert(
                    'Section capacity must be between 1 and 100.'
                );

                return;

            }

        }
    );



    /* =====================================================
       RESET MODAL AFTER CLOSE
    ===================================================== */

    if (modal)
    {

        modal.addEventListener(
            'hidden.bs.modal',
            function ()
            {

                resetForm();

            }
        );

    }

});



/* =========================================================
   DELETE CONFIRMATION
========================================================= */

function confirmDelete(sectionName)
{

    return confirm(

        'Are you sure you want to remove the section "' +
        sectionName +
        '"?\n\n' +

        'The section will be soft-deleted and hidden ' +
        'from the active section list.'

    );

}

</script>


<?php

/* =========================================================
   FOOTER
========================================================= */

include '../includes/footer.php';

?>
