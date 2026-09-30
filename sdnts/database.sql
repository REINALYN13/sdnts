-- =====================================================
-- SDNTS - Santo Domingo National Trade School
-- Integrated Web-Based Student Performance Monitoring
-- and Academic Ranking System
-- Database: sdnts_db
-- =====================================================

CREATE DATABASE IF NOT EXISTS `sdnts_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sdnts_db`;

-- ---------------------------------------------------
-- Table: roles
-- ---------------------------------------------------
CREATE TABLE `roles` (
  `roleID` INT AUTO_INCREMENT PRIMARY KEY,
  `roleName` VARCHAR(50) NOT NULL,
  `roleDesc` TEXT,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO `roles` (`roleName`, `roleDesc`) VALUES
('Admin', 'System Administrator - Full access'),
('Principal', 'School Principal - View and report access'),
('Teacher', 'Class Teacher - Grade management'),
('Student', 'Student - View own records');

-- ---------------------------------------------------
-- Table: users
-- ---------------------------------------------------
CREATE TABLE `users` (
  `userID` INT AUTO_INCREMENT PRIMARY KEY,
  `employeeNo` VARCHAR(20) UNIQUE DEFAULT NULL,
  `firstName` VARCHAR(100) NOT NULL,
  `middleName` VARCHAR(100) DEFAULT NULL,
  `lastName` VARCHAR(100) NOT NULL,
  `extName` VARCHAR(10) DEFAULT NULL,
  `pic` VARCHAR(255) DEFAULT 'default.png',
  `roleID` INT NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `programID` INT DEFAULT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`roleID`) REFERENCES `roles`(`roleID`)
) ENGINE=InnoDB;

-- Default Admin: admin@sdnts.edu.ph / Admin@1234
INSERT INTO `users` (`employeeNo`,`firstName`,`lastName`,`roleID`,`email`,`password`) VALUES
('EMP-0001','System','Administrator',1,'admin@sdnts.edu.ph','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

-- ---------------------------------------------------
-- Table: programs
-- ---------------------------------------------------
CREATE TABLE `programs` (
  `progID` INT AUTO_INCREMENT PRIMARY KEY,
  `progName` VARCHAR(150) NOT NULL,
  `progDesc` TEXT,
  `progCode` VARCHAR(20) NOT NULL,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO `programs` (`progName`,`progDesc`,`progCode`) VALUES
('TVL - Information and Communication Technology','ICT Track','TVL-ICT'),
('TVL - Home Economics','Home Economics Track','TVL-HE'),
('TVL - Industrial Arts','Industrial Arts Track','TVL-IA'),
('TVL - Agri-Fishery Arts','Agri-Fishery Arts Track','TVL-AFA'),
('Academic - STEM','Science Technology Engineering Mathematics','STEM'),
('Academic - HUMSS','Humanities and Social Sciences','HUMSS'),
('Academic - ABM','Accountancy Business Management','ABM'),
('GAS','General Academic Strand','GAS');

-- ---------------------------------------------------
-- Table: strands
-- ---------------------------------------------------
CREATE TABLE `strands` (
  `strandID` INT AUTO_INCREMENT PRIMARY KEY,
  `strandName` VARCHAR(150) NOT NULL,
  `strandDesc` TEXT,
  `progID` INT NOT NULL,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`progID`) REFERENCES `programs`(`progID`)
) ENGINE=InnoDB;

INSERT INTO `strands` (`strandName`,`strandDesc`,`progID`) VALUES
('CSS - Computer Systems Servicing','Hardware and networking',1),
('Programming','Web and application development',1),
('Cookery','Food preparation and cooking',2),
('Bread and Pastry Production','Baking and pastry arts',2),
('Electrical Installation and Maintenance','Electrical works',3),
('Welding','Arc and gas welding',3),
('Crop Production','Plant and crop management',4),
('Animal Production','Livestock and poultry',4),
('STEM General','General STEM strand',5),
('HUMSS General','General HUMSS strand',6),
('ABM General','General ABM strand',7),
('GAS General','General Academic strand',8);

-- ---------------------------------------------------
-- Table: academicYear
-- ---------------------------------------------------
CREATE TABLE `academicYear` (
  `ayID` INT AUTO_INCREMENT PRIMARY KEY,
  `academicYear` VARCHAR(20) NOT NULL,
  `academicDesc` TEXT,
  `status` ENUM('active','inactive') DEFAULT 'inactive',
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO `academicYear` (`academicYear`,`academicDesc`,`status`) VALUES
('2023-2024','School Year 2023-2024','inactive'),
('2024-2025','School Year 2024-2025','active');

-- ---------------------------------------------------
-- Table: year (Grade Level)
-- ---------------------------------------------------
CREATE TABLE `year` (
  `yearID` INT AUTO_INCREMENT PRIMARY KEY,
  `yearName` VARCHAR(50) NOT NULL,
  `yearDesc` VARCHAR(100),
  `progID` INT NOT NULL,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`progID`) REFERENCES `programs`(`progID`)
) ENGINE=InnoDB;

INSERT INTO `year` (`yearName`,`yearDesc`,`progID`) VALUES
('Grade 11','Junior Senior High - Grade 11',1),
('Grade 12','Junior Senior High - Grade 12',1),
('Grade 11','Junior Senior High - Grade 11',2),
('Grade 12','Junior Senior High - Grade 12',2),
('Grade 11','Junior Senior High - Grade 11',3),
('Grade 12','Junior Senior High - Grade 12',3),
('Grade 11','Junior Senior High - Grade 11',4),
('Grade 12','Junior Senior High - Grade 12',4),
('Grade 11','Junior Senior High - Grade 11',5),
('Grade 12','Junior Senior High - Grade 12',5),
('Grade 11','Junior Senior High - Grade 11',6),
('Grade 12','Junior Senior High - Grade 12',6),
('Grade 11','Junior Senior High - Grade 11',7),
('Grade 12','Junior Senior High - Grade 12',7),
('Grade 11','Junior Senior High - Grade 11',8),
('Grade 12','Junior Senior High - Grade 12',8);

-- ---------------------------------------------------
-- Table: subjects
-- ---------------------------------------------------
CREATE TABLE `subjects` (
  `subjectID` INT AUTO_INCREMENT PRIMARY KEY,
  `subjectCode` VARCHAR(30) NOT NULL,
  `subjectName` VARCHAR(200) NOT NULL,
  `subjectDesc` TEXT,
  `units` DECIMAL(3,1) DEFAULT 1.0,
  `yearID` INT DEFAULT NULL,
  `progID` INT DEFAULT NULL,
  `isCore` TINYINT(1) DEFAULT 0 COMMENT '1=Core subject for all strands',
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`yearID`) REFERENCES `year`(`yearID`),
  FOREIGN KEY (`progID`) REFERENCES `programs`(`progID`)
) ENGINE=InnoDB;

-- Core subjects Grade 11 (shared by all)
INSERT INTO `subjects` (`subjectCode`,`subjectName`,`units`,`yearID`,`isCore`) VALUES
('OC','Oral Communication',1.0,1,1),
('RWS','Reading and Writing Skills',1.0,1,1),
('HUMSS1','Komunikasyon at Pananaliksik',1.0,1,1),
('21CLIT','21st Century Literature',1.0,1,1),
('CONTEMP','Contemporary Philippine Arts',1.0,1,1),
('PE1','Physical Education and Health 1',1.0,1,1),
('EMPTECH1','Empowerment Technology',1.0,1,1),
('EQ','Entrepreneurship',1.0,1,1),
('GEN-MATH','General Mathematics',1.0,1,1),
('STATS','Statistics and Probability',1.0,1,1),
('EARTH','Earth and Life Science',1.0,1,1),
('PP1','Personal Development',1.0,1,1);

-- Core subjects Grade 12
INSERT INTO `subjects` (`subjectCode`,`subjectName`,`units`,`yearID`,`isCore`) VALUES
('MEDIA','Media and Information Literacy',1.0,2,1),
('PHILO','Introduction to Philosophy',1.0,2,1),
('PRACT','Practical Research 2',1.0,2,1),
('ENGLISH12','English for Academic Purposes',1.0,2,1),
('PE2','Physical Education and Health 2',1.0,2,1),
('ENTREP2','Business Plan',1.0,2,1),
('RESEARCH','Research/Capstone Project',1.0,2,1),
('WORK-IMMERSION','Work Immersion / Research',1.0,2,1);

-- ---------------------------------------------------
-- Table: sections
-- ---------------------------------------------------
CREATE TABLE `sections` (
  `sectionID` INT AUTO_INCREMENT PRIMARY KEY,
  `sectionName` VARCHAR(100) NOT NULL,
  `sectionDesc` TEXT,
  `sectionLimit` INT DEFAULT 40,
  `yearID` INT NOT NULL,
  `progID` INT NOT NULL,
  `userID` INT DEFAULT NULL COMMENT 'Adviser/Teacher',
  `ayID` INT NOT NULL,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`yearID`) REFERENCES `year`(`yearID`),
  FOREIGN KEY (`progID`) REFERENCES `programs`(`progID`),
  FOREIGN KEY (`userID`) REFERENCES `users`(`userID`),
  FOREIGN KEY (`ayID`) REFERENCES `academicYear`(`ayID`)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: schedule
-- ---------------------------------------------------
CREATE TABLE `schedule` (
  `schedID` INT AUTO_INCREMENT PRIMARY KEY,
  `scheduleCode` VARCHAR(30) NOT NULL,
  `sectionID` INT NOT NULL,
  `subjectID` INT NOT NULL,
  `userID` INT NOT NULL COMMENT 'Teacher',
  `ayID` INT NOT NULL,
  `dayOfWeek` VARCHAR(50),
  `timeStart` TIME,
  `timeEnd` TIME,
  `room` VARCHAR(50),
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`sectionID`) REFERENCES `sections`(`sectionID`),
  FOREIGN KEY (`subjectID`) REFERENCES `subjects`(`subjectID`),
  FOREIGN KEY (`userID`) REFERENCES `users`(`userID`),
  FOREIGN KEY (`ayID`) REFERENCES `academicYear`(`ayID`)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: students
-- ---------------------------------------------------
CREATE TABLE `students` (
  `studentID` INT AUTO_INCREMENT PRIMARY KEY,
  `studentNo` VARCHAR(20) UNIQUE NOT NULL,
  `lrn` VARCHAR(20) UNIQUE DEFAULT NULL COMMENT 'Learners Reference Number',
  `firstName` VARCHAR(100) NOT NULL,
  `middleName` VARCHAR(100) DEFAULT NULL,
  `lastName` VARCHAR(100) NOT NULL,
  `extName` VARCHAR(10) DEFAULT NULL,
  `dateofBirth` DATE NOT NULL,
  `isMale` TINYINT(1) DEFAULT 1,
  `address` TEXT,
  `contactNo` VARCHAR(20),
  `guardianName` VARCHAR(200),
  `guardianContact` VARCHAR(20),
  `progID` INT DEFAULT NULL,
  `strandID` INT DEFAULT NULL,
  `isEnroll` TINYINT(1) DEFAULT 0,
  `email` VARCHAR(150) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `pic` VARCHAR(255) DEFAULT 'default.png',
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`progID`) REFERENCES `programs`(`progID`),
  FOREIGN KEY (`strandID`) REFERENCES `strands`(`strandID`)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: registration (Enrollment)
-- ---------------------------------------------------
CREATE TABLE `registration` (
  `regID` INT AUTO_INCREMENT PRIMARY KEY,
  `regCode` VARCHAR(30) NOT NULL UNIQUE,
  `ayID` INT NOT NULL,
  `scheduleCode` VARCHAR(30),
  `sectionID` INT NOT NULL,
  `studentID` INT NOT NULL,
  `yearID` INT NOT NULL,
  `progID` INT NOT NULL,
  `strandID` INT DEFAULT NULL,
  `dateRegistration` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('draft','enrolled','dropped','graduated') DEFAULT 'draft',
  `isApproved` TINYINT(1) DEFAULT 0,
  `approvedBy` INT DEFAULT NULL,
  `approvedDate` DATETIME DEFAULT NULL,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`ayID`) REFERENCES `academicYear`(`ayID`),
  FOREIGN KEY (`sectionID`) REFERENCES `sections`(`sectionID`),
  FOREIGN KEY (`studentID`) REFERENCES `students`(`studentID`),
  FOREIGN KEY (`yearID`) REFERENCES `year`(`yearID`),
  FOREIGN KEY (`progID`) REFERENCES `programs`(`progID`),
  FOREIGN KEY (`strandID`) REFERENCES `strands`(`strandID`)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: grades
-- ---------------------------------------------------
CREATE TABLE `grades` (
  `gradeID` INT AUTO_INCREMENT PRIMARY KEY,
  `ayID` INT NOT NULL,
  `regCode` VARCHAR(30) NOT NULL,
  `studentID` INT NOT NULL,
  `subjectID` INT NOT NULL,
  `schedID` INT DEFAULT NULL,
  `firstGrading` DECIMAL(5,2) DEFAULT NULL,
  `firstGradingIsPost` TINYINT(1) DEFAULT 0,
  `secondGrading` DECIMAL(5,2) DEFAULT NULL,
  `secondGradingIsPost` TINYINT(1) DEFAULT 0,
  `thirdGrading` DECIMAL(5,2) DEFAULT NULL,
  `thirdGradingIsPost` TINYINT(1) DEFAULT 0,
  `fourthGrading` DECIMAL(5,2) DEFAULT NULL,
  `fourthGradingIsPost` TINYINT(1) DEFAULT 0,
  `average` DECIMAL(5,2) DEFAULT NULL,
  `remarks` ENUM('Passed','Failed','Incomplete','Dropped') DEFAULT NULL,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`ayID`) REFERENCES `academicYear`(`ayID`),
  FOREIGN KEY (`studentID`) REFERENCES `students`(`studentID`),
  FOREIGN KEY (`subjectID`) REFERENCES `subjects`(`subjectID`)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: ranking
-- ---------------------------------------------------
CREATE TABLE `ranking` (
  `rankID` INT AUTO_INCREMENT PRIMARY KEY,
  `studentID` INT NOT NULL,
  `ayID` INT NOT NULL,
  `sectionID` INT NOT NULL,
  `termID` INT DEFAULT NULL,
  `rankName` INT DEFAULT NULL COMMENT 'Rank number',
  `sectionName` VARCHAR(100),
  `gwa` DECIMAL(5,2) DEFAULT NULL COMMENT 'General Weighted Average',
  `honor` VARCHAR(100) DEFAULT NULL,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL,
  FOREIGN KEY (`studentID`) REFERENCES `students`(`studentID`),
  FOREIGN KEY (`ayID`) REFERENCES `academicYear`(`ayID`),
  FOREIGN KEY (`sectionID`) REFERENCES `sections`(`sectionID`)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: term
-- ---------------------------------------------------
CREATE TABLE `term` (
  `termID` INT AUTO_INCREMENT PRIMARY KEY,
  `termName` VARCHAR(50) NOT NULL,
  `termDesc` TEXT,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dateDeleted` DATETIME DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO `term` (`termName`,`termDesc`) VALUES
('1st Grading','First Grading Period'),
('2nd Grading','Second Grading Period'),
('3rd Grading','Third Grading Period'),
('4th Grading','Fourth Grading Period');

-- ---------------------------------------------------
-- Table: activity_log
-- ---------------------------------------------------
CREATE TABLE `activity_log` (
  `logID` INT AUTO_INCREMENT PRIMARY KEY,
  `userID` INT DEFAULT NULL,
  `studentID` INT DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `module` VARCHAR(100),
  `details` TEXT,
  `ipAddress` VARCHAR(45),
  `userAgent` TEXT,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: backup_log
-- ---------------------------------------------------
CREATE TABLE `backup_log` (
  `backupID` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255),
  `filesize` BIGINT,
  `createdBy` INT,
  `dateCreated` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: system_settings
-- ---------------------------------------------------
CREATE TABLE `system_settings` (
  `settingID` INT AUTO_INCREMENT PRIMARY KEY,
  `settingKey` VARCHAR(100) UNIQUE NOT NULL,
  `settingValue` TEXT,
  `dateUpdated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `system_settings` (`settingKey`,`settingValue`) VALUES
('school_name','Santo Domingo National Trade School'),
('school_address','Baloc, Sto. Domingo, Nueva Ecija'),
('school_contact','(044) 123-4567'),
('school_email','sdnts@deped.gov.ph'),
('school_logo','school_logo.png'),
('active_ay','2'),
('grading_period','1'),
('passing_grade','75.00'),
('honor_with_highest','98.00'),
('honor_with_high','95.00'),
('honor_with','90.00');

-- ---------------------------------------------------
-- View: v_student_gwa
-- Automatically computes GWA from grades
-- ---------------------------------------------------
CREATE OR REPLACE VIEW `v_student_gwa` AS
SELECT 
  g.studentID,
  g.ayID,
  s.firstName,
  s.middleName,
  s.lastName,
  s.studentNo,
  ROUND(AVG(g.average),2) as gwa,
  COUNT(g.subjectID) as totalSubjects,
  SUM(CASE WHEN g.remarks = 'Failed' THEN 1 ELSE 0 END) as failedCount,
  r.sectionID,
  sec.sectionName,
  p.progName,
  y.yearName
FROM grades g
JOIN students s ON g.studentID = s.studentID
JOIN registration r ON g.regCode = r.regCode
JOIN sections sec ON r.sectionID = sec.sectionID
JOIN programs p ON r.progID = p.progID
JOIN year y ON r.yearID = y.yearID
WHERE g.average IS NOT NULL AND g.dateDeleted IS NULL
GROUP BY g.studentID, g.ayID, r.sectionID;

-- ---------------------------------------------------
-- Trigger: Generate Student Number
-- ---------------------------------------------------
DELIMITER $$
CREATE TRIGGER `before_insert_student`
BEFORE INSERT ON `students`
FOR EACH ROW
BEGIN
  DECLARE newNo INT;
  SELECT IFNULL(MAX(CAST(SUBSTRING(studentNo,8) AS UNSIGNED)),0)+1 INTO newNo FROM students;
  SET NEW.studentNo = CONCAT('SDNTS-', LPAD(newNo,5,'0'));
END$$
DELIMITER ;

-- ---------------------------------------------------
-- Trigger: Generate Employee Number
-- ---------------------------------------------------
DELIMITER $$
CREATE TRIGGER `before_insert_user`
BEFORE INSERT ON `users`
FOR EACH ROW
BEGIN
  DECLARE newNo INT;
  IF NEW.employeeNo IS NULL THEN
    SELECT IFNULL(MAX(CAST(SUBSTRING(employeeNo,5) AS UNSIGNED)),0)+1 INTO newNo FROM users WHERE employeeNo IS NOT NULL;
    SET NEW.employeeNo = CONCAT('EMP-', LPAD(newNo,4,'0'));
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------
-- Trigger: Auto-calculate grade average
-- ---------------------------------------------------
DELIMITER $$
CREATE TRIGGER `before_update_grades`
BEFORE UPDATE ON `grades`
FOR EACH ROW
BEGIN
  DECLARE cnt INT DEFAULT 0;
  DECLARE total DECIMAL(10,2) DEFAULT 0;

  IF NEW.firstGrading IS NOT NULL THEN SET cnt = cnt+1; SET total = total+NEW.firstGrading; END IF;
  IF NEW.secondGrading IS NOT NULL THEN SET cnt = cnt+1; SET total = total+NEW.secondGrading; END IF;
  IF NEW.thirdGrading IS NOT NULL THEN SET cnt = cnt+1; SET total = total+NEW.thirdGrading; END IF;
  IF NEW.fourthGrading IS NOT NULL THEN SET cnt = cnt+1; SET total = total+NEW.fourthGrading; END IF;

  IF cnt > 0 THEN
    SET NEW.average = ROUND(total/cnt, 2);
    SET NEW.remarks = IF(NEW.average >= 75, 'Passed', 'Failed');
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------
-- Trigger: Auto-compute ranking after grade update
-- ---------------------------------------------------
DELIMITER $$
CREATE TRIGGER `after_grade_update_ranking`
AFTER UPDATE ON `grades`
FOR EACH ROW
BEGIN
  -- Ranking will be computed via PHP procedure for accuracy
  -- Placeholder trigger for future automation
  SET @dummy = 0;
END$$
DELIMITER ;

-- Stored Procedure: Compute Rankings for a section
DELIMITER $$
CREATE PROCEDURE `sp_compute_ranking`(IN p_ayID INT, IN p_sectionID INT)
BEGIN
  -- Delete existing rankings for this period
  DELETE FROM ranking WHERE ayID = p_ayID AND sectionID = p_sectionID;

  -- Insert computed rankings
  INSERT INTO ranking (studentID, ayID, sectionID, gwa, rankName, honor)
  SELECT 
    v.studentID,
    v.ayID,
    v.sectionID,
    v.gwa,
    RANK() OVER (PARTITION BY v.sectionID ORDER BY v.gwa DESC) as rankName,
    CASE 
      WHEN v.gwa >= (SELECT settingValue FROM system_settings WHERE settingKey='honor_with_highest') THEN 'With Highest Honor'
      WHEN v.gwa >= (SELECT settingValue FROM system_settings WHERE settingKey='honor_with_high') THEN 'With High Honor'
      WHEN v.gwa >= (SELECT settingValue FROM system_settings WHERE settingKey='honor_with') THEN 'With Honor'
      ELSE NULL
    END as honor
  FROM v_student_gwa v
  WHERE v.ayID = p_ayID AND v.sectionID = p_sectionID;
END$$
DELIMITER ;
