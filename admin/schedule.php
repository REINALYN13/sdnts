<?php
require_once '../includes/config.php';

requireRole([ROLE_ADMIN]);

$pageTitle = 'Schedule';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function redirectSchedule()
{
    header('Location: schedule.php');
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

function formatScheduleTime($time)
{
    if (empty($time)) {
        return '-';
    }

    $timestamp = strtotime($time);

    if ($timestamp === false) {
        return clean($time);
    }

    return date('h:i A', $timestamp);
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

        redirectSchedule();
    }

    $action = $_POST['action'] ?? '';


    /* =====================================================
       ADD / EDIT SCHEDULE
    ===================================================== */

    if ($action === 'add' || $action === 'edit') {

        $sectionID = postInt('sectionID');
        $subjectID = postInt('subjectID');
        $userID    = postInt('userID');

        $dayOfWeek = postString('dayOfWeek');
        $timeStart = postString('timeStart');
        $timeEnd   = postString('timeEnd');
        $room      = postString('room');

        $schedID = postInt('schedID');


        /* -------------------------------------------------
           REQUIRED FIELD VALIDATION
        ------------------------------------------------- */

        if (
            $sectionID <= 0 ||
            $subjectID <= 0 ||
            $userID <= 0
        ) {

            $_SESSION['flash_error'] =
                'Please select a section, subject, and teacher.';

            redirectSchedule();
        }


        /* -------------------------------------------------
           DAY VALIDATION
        ------------------------------------------------- */

        $validDays = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        ];

        if (
            $dayOfWeek !== '' &&
            !in_array($dayOfWeek, $validDays, true)
        ) {

            $_SESSION['flash_error'] =
                'Invalid day selected.';

            redirectSchedule();
        }


        /* -------------------------------------------------
           TIME VALIDATION
        ------------------------------------------------- */

        if ($timeStart !== '' && $timeEnd !== '') {

            if ($timeStart >= $timeEnd) {

                $_SESSION['flash_error'] =
                    'End time must be later than start time.';

                redirectSchedule();
            }

        } elseif (
            ($timeStart !== '' && $timeEnd === '') ||
            ($timeStart === '' && $timeEnd !== '')
        ) {

            $_SESSION['flash_error'] =
                'Please provide both start time and end time.';

            redirectSchedule();
        }


        /*
         * Convert empty values to NULL.
         */
        $timeStartDB =
            ($timeStart !== '') ? $timeStart : null;

        $timeEndDB =
            ($timeEnd !== '') ? $timeEnd : null;

        $dayDB =
            ($dayOfWeek !== '') ? $dayOfWeek : null;

        $roomDB =
            ($room !== '') ? $room : null;


        /* =================================================
           VERIFY SECTION
        ================================================= */

        $checkSection = $db->prepare("
            SELECT sectionID
            FROM sections
            WHERE sectionID = ?
              AND ayID = ?
              AND dateDeleted IS NULL
            LIMIT 1
        ");

        $checkSection->execute([
            $sectionID,
            $ay['ayID']
        ]);

        if (!$checkSection->fetchColumn()) {

            $_SESSION['flash_error'] =
                'The selected section is invalid or inactive.';

            redirectSchedule();
        }


        /* =================================================
           VERIFY SUBJECT
        ================================================= */

        $checkSubject = $db->prepare("
            SELECT subjectID
            FROM subjects
            WHERE subjectID = ?
              AND dateDeleted IS NULL
            LIMIT 1
        ");

        $checkSubject->execute([
            $subjectID
        ]);

        if (!$checkSubject->fetchColumn()) {

            $_SESSION['flash_error'] =
                'The selected subject is invalid or inactive.';

            redirectSchedule();
        }


        /* =================================================
           VERIFY TEACHER
        ================================================= */

        $checkTeacher = $db->prepare("
            SELECT userID
            FROM users
            WHERE userID = ?
              AND roleID = 3
              AND isActive = 1
              AND dateDeleted IS NULL
            LIMIT 1
        ");

        $checkTeacher->execute([
            $userID
        ]);

        if (!$checkTeacher->fetchColumn()) {

            $_SESSION['flash_error'] =
                'The selected teacher is invalid or inactive.';

            redirectSchedule();
        }


        /* =================================================
           CHECK SCHEDULE CONFLICT
        =================================================

           Conflict is checked only when:
           - Same academic year
           - Same day
           - Both schedules have times
           - Time ranges overlap

           A conflict is detected when:
           existing.start < new.end
           AND existing.end > new.start
        ================================================= */

        if (
            $dayDB !== null &&
            $timeStartDB !== null &&
            $timeEndDB !== null
        ) {

            $conflictSQL = "
                SELECT
                    sch.schedID,
                    sch.sectionID,
                    sch.userID,
                    sch.room,

                    sec.sectionName,

                    u.firstName,
                    u.lastName

                FROM schedule sch

                INNER JOIN sections sec
                    ON sch.sectionID = sec.sectionID

                INNER JOIN users u
                    ON sch.userID = u.userID

                WHERE sch.ayID = ?
                  AND sch.dateDeleted IS NULL

                  AND sch.dayOfWeek = ?

                  AND sch.timeStart < ?
                  AND sch.timeEnd > ?
            ";

            $params = [
                $ay['ayID'],
                $dayDB,
                $timeEndDB,
                $timeStartDB
            ];


            /*
             * Exclude the current schedule when editing.
             */
            if ($action === 'edit' && $schedID > 0) {

                $conflictSQL .= "
                    AND sch.schedID != ?
                ";

                $params[] = $schedID;
            }


            $conflictSQL .= "
                AND (
                    sch.sectionID = ?
                    OR sch.userID = ?
                    OR (
                        ? IS NOT NULL
                        AND ? <> ''
                        AND sch.room = ?
                    )
                )

                LIMIT 1
            ";


            $params[] = $sectionID;
            $params[] = $userID;

            $params[] = $roomDB;
            $params[] = $roomDB;
            $params[] = $roomDB;


            $conflict =
                $db->prepare($conflictSQL);

            $conflict->execute($params);

            $conflictData =
                $conflict->fetch(PDO::FETCH_ASSOC);


            if ($conflictData) {

                /*
                 * Determine the type of conflict.
                 */

                if (
                    (int)$conflictData['sectionID'] ===
                    $sectionID
                ) {

                    $_SESSION['flash_error'] =
                        'Schedule conflict: this section already has another class during the selected time.';

                } elseif (
                    (int)$conflictData['userID'] ===
                    $userID
                ) {

                    $teacherName =
                        trim(
                            $conflictData['lastName'] .
                            ', ' .
                            $conflictData['firstName']
                        );

                    $_SESSION['flash_error'] =
                        'Schedule conflict: ' .
                        $teacherName .
                        ' already has another class during the selected time.';

                } else {

                    $_SESSION['flash_error'] =
                        'Schedule conflict: the selected room is already occupied during the selected time.';

                }

                redirectSchedule();
            }
        }


        /* =================================================
           CHECK DUPLICATE SUBJECT FOR SAME SECTION
        ================================================= */

        $duplicateSQL = "
            SELECT COUNT(*)
            FROM schedule
            WHERE sectionID = ?
              AND subjectID = ?
              AND ayID = ?
              AND dateDeleted IS NULL
        ";

        $duplicateParams = [
            $sectionID,
            $subjectID,
            $ay['ayID']
        ];


        if ($action === 'edit' && $schedID > 0) {

            $duplicateSQL .= "
                AND schedID != ?
            ";

            $duplicateParams[] =
                $schedID;
        }


        $duplicate =
            $db->prepare($duplicateSQL);

        $duplicate->execute(
            $duplicateParams
        );


        if ((int)$duplicate->fetchColumn() > 0) {

            $_SESSION['flash_error'] =
                'This subject is already assigned to the selected section.';

            redirectSchedule();
        }


        /* =================================================
           ADD SCHEDULE
        ================================================= */

        if ($action === 'add') {

            $scheduleCode =
                'SCHED-' .
                $sectionID .
                '-' .
                $subjectID .
                '-' .
                $ay['ayID'] .
                '-' .
                time();


            $stmt = $db->prepare("
                INSERT INTO schedule
                (
                    scheduleCode,
                    sectionID,
                    subjectID,
                    userID,
                    ayID,
                    dayOfWeek,
                    timeStart,
                    timeEnd,
                    room
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");


            $stmt->execute([
                $scheduleCode,
                $sectionID,
                $subjectID,
                $userID,
                $ay['ayID'],
                $dayDB,
                $timeStartDB,
                $timeEndDB,
                $roomDB
            ]);


            $_SESSION['flash_success'] =
                'Schedule was successfully added.';

            redirectSchedule();
        }


        /* =================================================
           EDIT SCHEDULE
        ================================================= */

        if ($action === 'edit') {

            if ($schedID <= 0) {

                $_SESSION['flash_error'] =
                    'Invalid schedule selected.';

                redirectSchedule();
            }


            $stmt = $db->prepare("
                UPDATE schedule
                SET
                    sectionID = ?,
                    subjectID = ?,
                    userID = ?,
                    dayOfWeek = ?,
                    timeStart = ?,
                    timeEnd = ?,
                    room = ?
                WHERE schedID = ?
                  AND ayID = ?
                  AND dateDeleted IS NULL
            ");


            $stmt->execute([
                $sectionID,
                $subjectID,
                $userID,
                $dayDB,
                $timeStartDB,
                $timeEndDB,
                $roomDB,
                $schedID,
                $ay['ayID']
            ]);


            $_SESSION['flash_success'] =
                'Schedule was successfully updated.';

            redirectSchedule();
        }
    }


    /* =====================================================
       DELETE SCHEDULE
    ===================================================== */

    elseif ($action === 'delete') {

        $schedID =
            postInt('schedID');


        if ($schedID <= 0) {

            $_SESSION['flash_error'] =
                'Invalid schedule selected.';

            redirectSchedule();
        }


        $delete = $db->prepare("
            UPDATE schedule
            SET dateDeleted = NOW(),
                deletedBy = ?
            WHERE schedID = ?
              AND ayID = ?
              AND dateDeleted IS NULL
        ");


        $delete->execute([
            $_SESSION['userID'],
            $schedID,
            $ay['ayID']
        ]);


        if ($delete->rowCount() > 0) {

            $_SESSION['flash_success'] =
                'Schedule was successfully removed.';

        } else {

            $_SESSION['flash_error'] =
                'Schedule could not be found or was already removed.';
        }


        redirectSchedule();
    }
}


/* =========================================================
   GET SCHEDULES
========================================================= */

$schedules = $db->prepare("
    SELECT

        sch.*,

        sec.sectionName,

        sub.subjectCode,
        sub.subjectName,

        u.firstName,
        u.lastName

    FROM schedule sch

    INNER JOIN sections sec
        ON sch.sectionID = sec.sectionID

    INNER JOIN subjects sub
        ON sch.subjectID = sub.subjectID

    INNER JOIN users u
        ON sch.userID = u.userID

    WHERE sch.ayID = ?
      AND sch.dateDeleted IS NULL
      AND sec.dateDeleted IS NULL
      AND sub.dateDeleted IS NULL

    ORDER BY
        FIELD(
            sch.dayOfWeek,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        ),
        sch.timeStart,
        sec.sectionName,
        sub.subjectName
");


$schedules->execute([
    $ay['ayID']
]);


/* =========================================================
   GET SECTIONS
========================================================= */

$sections = $db->prepare("
    SELECT
        sec.*,
        p.progName

    FROM sections sec

    INNER JOIN programs p
        ON sec.progID = p.progID

    WHERE sec.ayID = ?
      AND sec.dateDeleted IS NULL
      AND p.dateDeleted IS NULL

    ORDER BY
        p.progName ASC,
        sec.sectionName ASC
");


$sections->execute([
    $ay['ayID']
]);

$sectionsList =
    $sections->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   GET SUBJECTS
========================================================= */

$subjects = $db->query("
    SELECT *
    FROM subjects
    WHERE dateDeleted IS NULL
    ORDER BY subjectName ASC
")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   GET TEACHERS
========================================================= */

$teachers = $db->query("
    SELECT *
    FROM users
    WHERE roleID = 3
      AND isActive = 1
      AND dateDeleted IS NULL
    ORDER BY
        lastName ASC,
        firstName ASC
")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   DAYS
========================================================= */

$days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday'
];


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
                       text-primary
                       d-flex align-items-center
                       justify-content-center"
                style="width:48px;height:48px;"
            >

                <i class="fas fa-calendar-alt fa-lg"></i>

            </div>


            <div>

                <h4 class="mb-1 fw-bold">
                    Manage Schedule
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
                            Schedule
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
        data-bs-target="#modalSched"
        id="btnAddSchedule"
    >

        <i class="fas fa-plus me-2"></i>

        Add Schedule

    </button>

</div>







<!-- =======================================================
     SCHEDULE TABLE
======================================================= -->

<div class="card border-0 shadow-sm overflow-hidden">


    <!-- CARD HEADER -->

    <div class="card-header bg-white border-0 py-3">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5 class="mb-1 fw-semibold">
                    Class Schedule
                </h5>

                <small class="text-muted">
                    Manage subjects, teachers, rooms, and class times.
                </small>

            </div>

        </div>

    </div>



    <!-- CARD BODY -->

    <div class="card-body p-0">

        <div class="table-responsive">

            <table
                class="table table-hover align-middle mb-0 datatable"
            >

                <thead class="table-light">

                    <tr>

                        <th class="ps-4">
                            Section
                        </th>

                        <th>
                            Subject
                        </th>

                        <th>
                            Teacher
                        </th>

                        <th>
                            Day
                        </th>

                        <th>
                            Time
                        </th>

                        <th>
                            Room
                        </th>

                        <th class="text-end pe-4">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $hasSchedules = false;

                while ($s = $schedules->fetch()):

                    $hasSchedules = true;


                    /* -------------------------------------
                       TEACHER NAME
                    ------------------------------------- */

                    $teacherName =
                        trim(
                            $s['lastName'] .
                            ', ' .
                            $s['firstName']
                        );


                    /* -------------------------------------
                       TIME
                    ------------------------------------- */

                    $timeDisplay = '-';

                    if (
                        !empty($s['timeStart']) &&
                        !empty($s['timeEnd'])
                    ) {

                        $timeDisplay =
                            formatScheduleTime(
                                $s['timeStart']
                            ) .
                            ' - ' .
                            formatScheduleTime(
                                $s['timeEnd']
                            );
                    }


                    /* -------------------------------------
                       DAY
                    ------------------------------------- */

                    $dayDisplay =
                        !empty($s['dayOfWeek'])
                            ? $s['dayOfWeek']
                            : '-';


                    /* -------------------------------------
                       EDIT DATA
                    ------------------------------------- */

                    $scheduleData = [

                        'schedID' =>
                            $s['schedID'],

                        'sectionID' =>
                            $s['sectionID'],

                        'subjectID' =>
                            $s['subjectID'],

                        'userID' =>
                            $s['userID'],

                        'dayOfWeek' =>
                            $s['dayOfWeek'],

                        'timeStart' =>
                            $s['timeStart'],

                        'timeEnd' =>
                            $s['timeEnd'],

                        'room' =>
                            $s['room']

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

                                </div>

                            </div>

                        </td>



                        <!-- SUBJECT -->

                        <td>

                            <div>

                                <code class="text-primary fw-semibold">

                                    <?= clean(
                                        $s['subjectCode']
                                    ) ?>

                                </code>

                            </div>


                            <small class="text-muted">

                                <?= clean(
                                    $s['subjectName']
                                ) ?>

                            </small>

                        </td>



                        <!-- TEACHER -->

                        <td>

                            <div class="d-flex align-items-center">

                                <div
                                    class="rounded-circle
                                           bg-secondary
                                           bg-opacity-10
                                           text-secondary
                                           d-flex align-items-center
                                           justify-content-center me-2"
                                    style="width:34px;height:34px;"
                                >

                                    <i class="fas fa-user"></i>

                                </div>


                                <span>

                                    <?= clean(
                                        $teacherName
                                    ) ?>

                                </span>

                            </div>

                        </td>



                        <!-- DAY -->

                        <td>

                            <?php if ($dayDisplay !== '-'): ?>

                                <span
                                    class="badge bg-primary
                                           bg-opacity-10
                                           text-primary"
                                >

                                    <?= clean(
                                        $dayDisplay
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-muted">
                                    -
                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- TIME -->

                        <td>

                            <?php if ($timeDisplay !== '-'): ?>

                                <div
                                    class="d-flex align-items-center"
                                >

                                    <i
                                        class="fas fa-clock
                                               text-muted me-2"
                                    ></i>

                                    <span class="fw-medium">

                                        <?= clean(
                                            $timeDisplay
                                        ) ?>

                                    </span>

                                </div>

                            <?php else: ?>

                                <span class="text-muted">
                                    No time set
                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- ROOM -->

                        <td>

                            <?php if (!empty($s['room'])): ?>

                                <span>

                                    <i
                                        class="fas fa-door-open
                                               text-muted me-1"
                                    ></i>

                                    <?= clean(
                                        $s['room']
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-muted">
                                    No room
                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- ACTIONS -->

                        <td class="text-end pe-4">

                            <div class="btn-group btn-group-sm">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-edit-schedule"
                                    data-s="<?= htmlspecialchars(
                                        json_encode(
                                            $scheduleData,
                                            JSON_UNESCAPED_UNICODE
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalSched"
                                    title="Edit Schedule"
                                >

                                    <i class="fas fa-edit"></i>

                                </button>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    class="d-inline"
                                    onsubmit="return confirmDeleteSchedule();"
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
                                        name="schedID"
                                        value="<?= $s['schedID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger"
                                        title="Remove Schedule"
                                    >

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>


                <?php endwhile; ?>


                <!-- EMPTY STATE -->

                <?php if (!$hasSchedules): ?>

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
                                        class="fas fa-calendar-alt
                                               fa-2x text-muted"
                                    ></i>

                                </div>

                            </div>


                            <h6 class="fw-semibold">
                                No Schedule Found
                            </h6>


                            <p class="text-muted mb-3">

                                No active class schedules
                                have been created for this
                                academic year.

                            </p>


                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#modalSched"
                            >

                                <i class="fas fa-plus me-2"></i>

                                Create First Schedule

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
     ADD / EDIT SCHEDULE MODAL
======================================================= -->

<div
    class="modal fade"
    id="modalSched"
    tabindex="-1"
    aria-labelledby="schTitle"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-lg modal-dialog-centered"
    >

        <div
            class="modal-content border-0 shadow"
        >

            <form
                method="POST"
                id="schedForm"
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
                    id="schAction"
                >


                <!-- SCHEDULE ID -->

                <input
                    type="hidden"
                    name="schedID"
                    id="schID"
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

                            <i class="fas fa-calendar-alt"></i>

                        </div>


                        <div>

                            <h5
                                class="modal-title mb-0"
                                id="schTitle"
                            >
                                Add Schedule
                            </h5>

                            <small class="opacity-75">
                                Create or update a class schedule
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

                    <div class="row g-3">


                        <!-- SECTION -->

                        <div class="col-md-6">

                            <label
                                class="form-label fw-semibold"
                                for="sch_sec"
                            >

                                Section

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <select
                                name="sectionID"
                                id="sch_sec"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Select Section --
                                </option>


                                <?php foreach (
                                    $sectionsList
                                    as $sec
                                ): ?>

                                    <option
                                        value="<?= $sec['sectionID'] ?>"
                                    >

                                        <?= clean(
                                            $sec['sectionName'] .
                                            ' (' .
                                            $sec['progName'] .
                                            ')'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- SUBJECT -->

                        <div class="col-md-6">

                            <label
                                class="form-label fw-semibold"
                                for="sch_sub"
                            >

                                Subject

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <select
                                name="subjectID"
                                id="sch_sub"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Select Subject --
                                </option>


                                <?php foreach (
                                    $subjects
                                    as $sub
                                ): ?>

                                    <option
                                        value="<?= $sub['subjectID'] ?>"
                                    >

                                        <?= clean(
                                            $sub['subjectCode'] .
                                            ' - ' .
                                            $sub['subjectName']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- TEACHER -->

                        <div class="col-md-6">

                            <label
                                class="form-label fw-semibold"
                                for="sch_user"
                            >

                                Teacher

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <select
                                name="userID"
                                id="sch_user"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Select Teacher --
                                </option>


                                <?php foreach (
                                    $teachers
                                    as $t
                                ): ?>

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



                        <!-- DAY -->

                        <div class="col-md-6">

                            <label
                                class="form-label fw-semibold"
                                for="sch_day"
                            >

                                Day

                            </label>


                            <select
                                name="dayOfWeek"
                                id="sch_day"
                                class="form-select"
                            >

                                <option value="">
                                    -- Select Day --
                                </option>


                                <?php foreach (
                                    $days
                                    as $day
                                ): ?>

                                    <option
                                        value="<?= $day ?>"
                                    >

                                        <?= $day ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- START TIME -->

                        <div class="col-md-3">

                            <label
                                class="form-label fw-semibold"
                                for="sch_ts"
                            >

                                Start Time

                            </label>


                            <input
                                type="time"
                                name="timeStart"
                                id="sch_ts"
                                class="form-control"
                            >

                        </div>



                        <!-- END TIME -->

                        <div class="col-md-3">

                            <label
                                class="form-label fw-semibold"
                                for="sch_te"
                            >

                                End Time

                            </label>


                            <input
                                type="time"
                                name="timeEnd"
                                id="sch_te"
                                class="form-control"
                            >

                        </div>



                        <!-- ROOM -->

                        <div class="col-md-6">

                            <label
                                class="form-label fw-semibold"
                                for="sch_room"
                            >

                                Room

                                <span class="text-muted fw-normal">
                                    (Optional)
                                </span>

                            </label>


                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="fas fa-door-open"></i>

                                </span>


                                <input
                                    type="text"
                                    name="room"
                                    id="sch_room"
                                    class="form-control"
                                    placeholder="e.g. Room 101"
                                    maxlength="100"
                                >

                            </div>

                        </div>

                    </div>



                    <!-- INFORMATION -->

                    <div
                        class="alert alert-light border
                               mt-4 mb-0"
                    >

                        <div class="d-flex">

                            <i
                                class="fas fa-info-circle
                                       text-primary me-2 mt-1"
                            ></i>

                            <small class="text-muted">

                                The system will automatically check
                                for overlapping schedules involving
                                the selected section, teacher, or room.

                            </small>

                        </div>

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
                        id="schSaveBtn"
                    >

                        <i class="fas fa-save me-2"></i>

                        Save Schedule

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
        document.getElementById('schedForm');

    const action =
        document.getElementById('schAction');

    const scheduleID =
        document.getElementById('schID');

    const title =
        document.getElementById('schTitle');

    const section =
        document.getElementById('sch_sec');

    const subject =
        document.getElementById('sch_sub');

    const teacher =
        document.getElementById('sch_user');

    const day =
        document.getElementById('sch_day');

    const startTime =
        document.getElementById('sch_ts');

    const endTime =
        document.getElementById('sch_te');

    const room =
        document.getElementById('sch_room');

    const saveBtn =
        document.getElementById('schSaveBtn');

    const addButton =
        document.getElementById('btnAddSchedule');

    const modal =
        document.getElementById('modalSched');



    /* =====================================================
       RESET FORM
    ===================================================== */

    function resetForm()
    {

        form.reset();

        action.value = 'add';

        scheduleID.value = '';

        title.textContent =
            'Add Schedule';

        saveBtn.innerHTML =
            '<i class="fas fa-save me-2"></i>Save Schedule';

    }



    /* =====================================================
       ADD BUTTON
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
       EDIT BUTTON
    ===================================================== */

    document
        .querySelectorAll('.btn-edit-schedule')
        .forEach(function (btn)
        {

            btn.addEventListener(
                'click',
                function ()
                {

                    try
                    {

                        const schedule =
                            JSON.parse(
                                this.getAttribute(
                                    'data-s'
                                )
                            );


                        action.value =
                            'edit';


                        scheduleID.value =
                            schedule.schedID || '';


                        title.textContent =
                            'Edit Schedule';


                        section.value =
                            schedule.sectionID || '';


                        subject.value =
                            schedule.subjectID || '';


                        teacher.value =
                            schedule.userID || '';


                        day.value =
                            schedule.dayOfWeek || '';


                        startTime.value =
                            schedule.timeStart || '';


                        endTime.value =
                            schedule.timeEnd || '';


                        room.value =
                            schedule.room || '';


                        saveBtn.innerHTML =
                            '<i class="fas fa-save me-2"></i>Update Schedule';

                    }
                    catch (error)
                    {

                        console.error(
                            'Unable to load schedule data:',
                            error
                        );

                        alert(
                            'Unable to load schedule information.'
                        );

                    }

                }
            );

        });



    /* =====================================================
       CLIENT-SIDE TIME VALIDATION
    ===================================================== */

    function validateTime()
    {

        if (
            startTime.value &&
            endTime.value
        )
        {

            if (
                startTime.value >=
                endTime.value
            )
            {

                endTime.setCustomValidity(
                    'End time must be later than start time.'
                );

                return false;

            }

        }


        endTime.setCustomValidity('');

        return true;

    }


    startTime.addEventListener(
        'change',
        validateTime
    );

    endTime.addEventListener(
        'change',
        validateTime
    );



    /* =====================================================
       FORM VALIDATION
    ===================================================== */

    form.addEventListener(
        'submit',
        function (event)
        {

            if (!validateTime())
            {

                event.preventDefault();

                endTime.reportValidity();

                return;

            }


            if (
                startTime.value &&
                !endTime.value
            )
            {

                event.preventDefault();

                alert(
                    'Please enter the end time.'
                );

                endTime.focus();

                return;

            }


            if (
                !startTime.value &&
                endTime.value
            )
            {

                event.preventDefault();

                alert(
                    'Please enter the start time.'
                );

                startTime.focus();

                return;

            }

        }
    );



    /* =====================================================
       RESET AFTER MODAL CLOSE
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

function confirmDeleteSchedule()
{

    return confirm(

        'Are you sure you want to remove this schedule?\n\n' +

        'The schedule will be soft-deleted and removed ' +
        'from the active schedule list.'

    );

}

</script>


<?php

include '../includes/footer.php';

?>