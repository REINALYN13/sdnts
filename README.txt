============================================================
  SDNTS - Integrated Web-Based Student Performance
  Monitoring and Academic Ranking System
  Sto. Domingo National Trade School
  Baloc, Sto. Domingo, Nueva Ecija
============================================================

INSTALLATION INSTRUCTIONS (XAMPP)
===================================

1. COPY FILES
   - Extract this ZIP
   - Copy the "sdnts" folder to: C:\xampp\htdocs\
   - Final path: C:\xampp\htdocs\sdnts\

2. START XAMPP
   - Open XAMPP Control Panel
   - Start Apache and MySQL

3. IMPORT DATABASE
   - Open browser: http://localhost/phpmyadmin
   - Click "New" → Create database named: sdnts_db
   - Click on sdnts_db → Import tab
   - Choose file: sdnts/database.sql → Click Go

4. CONFIGURE (if needed)
   - Open: sdnts/includes/config.php
   - Update DB_USER / DB_PASS if you changed XAMPP defaults
   - Default XAMPP: user=root, pass=(blank)

5. ACCESS THE SYSTEM
   - URL: http://localhost/sdnts
   - Default Admin Login:
     Email:    admin@sdnts.edu.ph
     Password: Admin@1234
   ⚠ Change admin password after first login!

============================================================
DEFAULT ACCOUNTS
============================================================
Role       Email                     Password
---------  ------------------------  ---------------
Admin      admin@sdnts.edu.ph        Admin@1234

============================================================
FEATURES
============================================================
✔ Automatic Student Numbers (SDNTS-00001)
✔ Automatic Employee Numbers (EMP-0001)
✔ Draft and Enrolled status system
✔ Auto subject assignment on enrollment
✔ 4-quarter grade management with auto average
✔ General Weighted Average (GWA) computation
✔ Academic Ranking with Honor classification
✔ Report Card printing (PDF via browser print)
✔ Master List printing
✔ Enrollment Reports
✔ Activity Logs
✔ Database Backup
✔ Role-based access (Admin/Principal/Teacher/Student)
✔ Secure login (bcrypt hashing, CSRF protection)
✔ PDO prepared statements (SQL injection safe)
✔ Session timeout after 30 minutes
✔ Responsive Bootstrap 5 UI

============================================================
ROLES & ACCESS
============================================================
Admin      - Full system access
Principal  - View reports, students, grades, rankings
Teacher    - Manage grades, view students
Student    - View own grades, schedule, ranking

============================================================
FOLDER STRUCTURE
============================================================
sdnts/
├── index.php          (Login page)
├── dashboard.php      (Main dashboard)
├── database.sql       (Full database with sample data)
├── logout.php
├── profile.php
├── includes/
│   ├── config.php     (DB + functions)
│   ├── header.php
│   └── footer.php
├── admin/
│   ├── students.php
│   ├── enrollment.php
│   ├── grades.php
│   ├── ranking.php
│   ├── users.php
│   ├── sections.php
│   ├── subjects.php
│   ├── programs.php
│   ├── strands.php
│   ├── academic-year.php
│   ├── year-level.php
│   ├── schedule.php
│   ├── settings.php
│   ├── backup.php
│   └── activity-log.php
├── student/
│   ├── my-grades.php
│   ├── my-schedule.php
│   └── my-ranking.php
├── reports/
│   ├── report-grades.php
│   ├── report-ranking.php
│   ├── report-enrollment.php
│   └── report-masterlist.php
└── assets/
    ├── css/style.css
    ├── js/main.js
    └── img/

============================================================
SUPPORT
============================================================
System developed for:
Santo Domingo National Trade School
Baloc, Sto. Domingo, Nueva Ecija

Default passing grade: 75
Honor thresholds:
  With Honor:         90.00
  With High Honor:    95.00
  With Highest Honor: 98.00
============================================================
