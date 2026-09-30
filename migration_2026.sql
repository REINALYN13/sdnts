-- =====================================================
-- SDNTS - Migration 2026-09
-- Run this ONCE against an EXISTING sdnts_db database
-- (phpMyAdmin > sdnts_db > SQL tab > paste > Go).
-- A fresh install using the updated database.sql already
-- contains everything in this file, so you do NOT need to
-- run this script after importing the new database.sql.
--
-- What this adds:
--  1) "deletedBy" accountability columns for archive/history
--  2) Configurable grade min/max system settings
--  3) Indexes that the new teacher-ownership queries rely on
--  4) DB-level guardrails (CHECK constraints) on grade values
-- =====================================================
USE `sdnts_db`;

-- ---------------------------------------------------
-- 1) Archive accountability ("who archived this record")
-- ---------------------------------------------------
ALTER TABLE `students`
  ADD COLUMN `deletedBy` INT DEFAULT NULL AFTER `dateDeleted`,
  ADD CONSTRAINT `fk_students_deletedBy` FOREIGN KEY (`deletedBy`) REFERENCES `users`(`userID`);

ALTER TABLE `grades`
  ADD COLUMN `deletedBy` INT DEFAULT NULL AFTER `dateDeleted`,
  ADD CONSTRAINT `fk_grades_deletedBy` FOREIGN KEY (`deletedBy`) REFERENCES `users`(`userID`);

ALTER TABLE `registration`
  ADD COLUMN `deletedBy` INT DEFAULT NULL AFTER `dateDeleted`,
  ADD CONSTRAINT `fk_registration_deletedBy` FOREIGN KEY (`deletedBy`) REFERENCES `users`(`userID`);

ALTER TABLE `schedule`
  ADD COLUMN `deletedBy` INT DEFAULT NULL AFTER `dateDeleted`,
  ADD CONSTRAINT `fk_schedule_deletedBy` FOREIGN KEY (`deletedBy`) REFERENCES `users`(`userID`);

ALTER TABLE `sections`
  ADD COLUMN `deletedBy` INT DEFAULT NULL AFTER `dateDeleted`,
  ADD CONSTRAINT `fk_sections_deletedBy` FOREIGN KEY (`deletedBy`) REFERENCES `users`(`userID`);

ALTER TABLE `users`
  ADD COLUMN `deletedBy` INT DEFAULT NULL AFTER `dateDeleted`,
  ADD CONSTRAINT `fk_users_deletedBy` FOREIGN KEY (`deletedBy`) REFERENCES `users`(`userID`);

-- ---------------------------------------------------
-- 2) Configurable grade range (defaults = existing 60-100 scale,
--    so behavior is unchanged until an Admin edits Settings)
-- ---------------------------------------------------
INSERT INTO `system_settings` (`settingKey`,`settingValue`) VALUES
  ('grade_min','60.00'),
  ('grade_max','100.00')
ON DUPLICATE KEY UPDATE settingValue = settingValue;

-- ---------------------------------------------------
-- 3) Indexes used by the new teacher-ownership lookups
--    (teacherSectionIDs / teacherOwnsSection / teacherOwnsStudent)
-- ---------------------------------------------------
ALTER TABLE `schedule`     ADD INDEX `idx_sched_user_ay` (`userID`,`ayID`);
ALTER TABLE `schedule`     ADD INDEX `idx_sched_section_ay` (`sectionID`,`ayID`);
ALTER TABLE `registration` ADD INDEX `idx_reg_section_ay_status` (`sectionID`,`ayID`,`status`);
ALTER TABLE `registration` ADD INDEX `idx_reg_student_ay` (`studentID`,`ayID`);
ALTER TABLE `grades`       ADD INDEX `idx_grades_student_ay` (`studentID`,`ayID`);

-- ---------------------------------------------------
-- 4) DB-level guardrail on grade values (defense-in-depth
--    behind the app-level min/max validation). This is a wide
--    outer bound; the real min/max is enforced by the app from
--    system_settings.grade_min / grade_max.
--    NOTE: MySQL/MariaDB versions before MySQL 8.0.16 / MariaDB
--    10.2.1 parse but silently ignore CHECK constraints - the
--    app-level validation in admin/grades.php remains the real
--    line of defense either way.
-- ---------------------------------------------------
ALTER TABLE `grades`
  ADD CONSTRAINT `chk_grades_first`  CHECK (`firstGrading`  IS NULL OR (`firstGrading`  BETWEEN 0 AND 100)),
  ADD CONSTRAINT `chk_grades_second` CHECK (`secondGrading` IS NULL OR (`secondGrading` BETWEEN 0 AND 100)),
  ADD CONSTRAINT `chk_grades_third`  CHECK (`thirdGrading`  IS NULL OR (`thirdGrading`  BETWEEN 0 AND 100)),
  ADD CONSTRAINT `chk_grades_fourth` CHECK (`fourthGrading` IS NULL OR (`fourthGrading` BETWEEN 0 AND 100));
