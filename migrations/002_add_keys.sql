-- =====================================================================
-- 002_add_keys.sql
-- Gives all 56 remaining tables a primary key (6 added, 2 keyless scratch
-- tables dropped) and adds 89 foreign keys, with the data repairs needed.
-- =====================================================================
--
-- BACK UP FIRST. DDL in MySQL commits implicitly, so this script cannot
-- be wrapped in a transaction and a failure halfway leaves it partly
-- applied. 003_rollback.sql undoes the constraints but NOT the deletes
-- in Phase 2.
--
--   mysqldump -h 127.0.0.1 -u root --databases University > University_before_keys.sql
--   mysql -h 127.0.0.1 -u root University < 002_add_keys.sql
--
-- ---------------------------------------------------------------------
-- WHAT THIS CHANGES FOR THE APPLICATION
-- ---------------------------------------------------------------------
-- DeleteUsers.php issues a bare "DELETE FROM Users WHERE UserID = ?" and
-- cleans up nothing else -- which is how the orphans below were created.
-- The identity chain here is ON DELETE CASCADE, so that same statement
-- starts working correctly: deleting a user now removes their Login,
-- subtype rows, enrollments, attendance and degree audit with it.
--
-- If you would rather that deletion be BLOCKED for anyone holding
-- academic records, change ON DELETE CASCADE to ON DELETE RESTRICT on
-- the four constraints marked [TRANSCRIPT] in Phase 4. The delete will
-- then fail with errno 1451 instead of silently removing a transcript.
--
-- Referential policy used throughout:
--   CASCADE   - the child row has no meaning without its parent
--               (subtype rows, junctions, message copies, requirements)
--   SET NULL  - the reference is an optional assignment on a nullable
--               column (instructor, room, time slot, department)
--   RESTRICT  - the parent is structural and deleting it should fail
--               loudly (a course with sections, a term with enrollments)
-- ON UPDATE CASCADE everywhere, which matters for the natural keys:
-- Users.Email, Course.CourseID, Room.RoomID, Semester.SemesterID.
-- =====================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';


-- =====================================================================
-- PHASE 1  Type reconciliation
-- ---------------------------------------------------------------------
-- A foreign key requires the child and parent columns to have the same
-- type, signedness, length, charset and collation. These three columns
-- do not, which is the likeliest reason the constraints were dropped.
-- Verified against live data: max CourseID length is 8 in both tables,
-- and Advisor.AssignedBy holds no negative values.
-- =====================================================================

ALTER TABLE `StudentEnrollment`
  MODIFY `CourseID` varchar(10) COLLATE utf8mb4_general_ci NOT NULL;

ALTER TABLE `StudentHistory`
  MODIFY `CourseID` varchar(10) COLLATE utf8mb4_general_ci NOT NULL;

ALTER TABLE `Advisor`
  MODIFY `AssignedBy` int unsigned NOT NULL;


-- =====================================================================
-- PHASE 2  Data repair
-- ---------------------------------------------------------------------
-- Every statement here removes or rewrites rows that would reject a
-- constraint in Phase 3 or 4. Counts are from the live database as of
-- 2026-09-04; re-run 001_preflight_checks.sql to confirm yours.
-- =====================================================================

-- 2.1  Student.MajorID uses 0 as "no major declared" on all 1616 rows.
--      Zero is not a Major.MajorID (those run 1-28). The column is
--      already nullable, so NULL is the correct representation.
UPDATE `Student` SET `MajorID` = NULL WHERE `MajorID` = 0;

-- 2.2  Major 27 ("Test") points at department 12, which no longer exists.
UPDATE `Major` m
   SET m.`DeptID` = NULL
 WHERE m.`DeptID` IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM `Department` d WHERE d.`DeptID` = m.`DeptID`);

-- 2.3  Identities whose Users row was deleted without cleaning up after
--      it: 14 Student, 1 Admin, 1 StatStaff. Their Login rows show them
--      to be test accounts (test.ug@nu.edu, test2.ug@nu.edu, ...).
--      Staged first, because deleting the parents would otherwise make
--      the child rows unfindable.
DROP TABLE IF EXISTS `_mig_dead_users`;
CREATE TABLE `_mig_dead_users` (`id` int unsigned PRIMARY KEY) ENGINE=InnoDB;

INSERT IGNORE INTO `_mig_dead_users` (`id`)
SELECT s.`StudentID` FROM `Student` s
 WHERE NOT EXISTS (SELECT 1 FROM `Users` u WHERE u.`UserID` = s.`StudentID`);
INSERT IGNORE INTO `_mig_dead_users` (`id`)
SELECT a.`AdminID` FROM `Admin` a
 WHERE NOT EXISTS (SELECT 1 FROM `Users` u WHERE u.`UserID` = a.`AdminID`);
INSERT IGNORE INTO `_mig_dead_users` (`id`)
SELECT st.`StatStaffID` FROM `StatStaff` st
 WHERE NOT EXISTS (SELECT 1 FROM `Users` u WHERE u.`UserID` = st.`StatStaffID`);

-- Child records first (1105 attendance, 96 enrollments, 27 history rows).
DELETE FROM `CourseSectionAttendance` WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `StudentEnrollment`       WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `StudentHistory`          WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `DegreeAudit`             WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `Advisor`                 WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `StudentMajor`            WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `StudentMinor`            WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `StudentHold`             WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `FullTimeUG`              WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `PartTimeUG`              WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `FullTimeGrad`            WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `PartTimeGrad`            WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `Undergraduate`           WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `Graduate`                WHERE `StudentID` IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `ViewAdmin`               WHERE `AdminID`   IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `UpdateAdmin`             WHERE `AdminID`   IN (SELECT `id` FROM `_mig_dead_users`);
-- Then the subtype rows themselves.
DELETE FROM `Student`                 WHERE `StudentID`   IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `Admin`                   WHERE `AdminID`     IN (SELECT `id` FROM `_mig_dead_users`);
DELETE FROM `StatStaff`               WHERE `StatStaffID` IN (SELECT `id` FROM `_mig_dead_users`);

-- Second-order orphans: admin-privilege rows whose Admin row is gone for
-- any other reason.
DELETE va FROM `ViewAdmin` va
 WHERE NOT EXISTS (SELECT 1 FROM `Admin` a WHERE a.`AdminID` = va.`AdminID`);
DELETE ua FROM `UpdateAdmin` ua
 WHERE NOT EXISTS (SELECT 1 FROM `Admin` a WHERE a.`AdminID` = ua.`AdminID`);

DROP TABLE `_mig_dead_users`;

-- 2.4  Faculty purged from Users and Faculty, leaving load rows, history
--      and credentials behind: 164 FullTimeFaculty, 25 PartTimeFaculty,
--      101 FacultyHistory. No CourseSection references any of them, so
--      no live offering loses its instructor.
DELETE ff FROM `FullTimeFaculty` ff
 WHERE NOT EXISTS (SELECT 1 FROM `Faculty` f WHERE f.`FacultyID` = ff.`FacultyID`);

DELETE pf FROM `PartTimeFaculty` pf
 WHERE NOT EXISTS (SELECT 1 FROM `Faculty` f WHERE f.`FacultyID` = pf.`FacultyID`);

-- FacultyHistory.FacultyID is nullable and the constraint is SET NULL,
-- so blank the reference rather than destroying the teaching record.
UPDATE `FacultyHistory` fh
   SET fh.`FacultyID` = NULL
 WHERE fh.`FacultyID` IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM `Faculty` f WHERE f.`FacultyID` = fh.`FacultyID`);

-- 2.5  Credentials with no user behind them (205 rows, including the
--      purged faculty above and the demo account faculty.user@nu.edu).
--      These cannot authenticate today -- every login path joins Users.
DELETE l FROM `Login` l
 WHERE NOT EXISTS (SELECT 1 FROM `Users` u WHERE u.`UserID` = l.`LoginID`)
    OR NOT EXISTS (SELECT 1 FROM `Users` u WHERE u.`Email`  = l.`Email`);

-- 2.6  Requirement rows for majors that were deleted (400 rows across 10
--      dead MajorIDs). Major holds 12 rows; MajorRequirement references 21.
DELETE mr FROM `MajorRequirement` mr
 WHERE NOT EXISTS (SELECT 1 FROM `Major` m WHERE m.`MajorID` = mr.`MajorID`);

-- 2.7  Declarations pointing at deleted majors.
DELETE sm FROM `StudentMajor` sm
 WHERE NOT EXISTS (SELECT 1 FROM `Major` m WHERE m.`MajorID` = sm.`MajorID`);

-- 2.8  Student 1 has a FullTimeUG row but no Undergraduate row, and an
--      empty string in StudentType (not a valid enum value). The
--      FullTimeUG row is what tells us the classification, so reinstate
--      the missing parent rather than discarding the load record.
UPDATE `Student` SET `StudentType` = 'Undergraduate'
 WHERE `StudentID` = 1 AND `StudentType` NOT IN ('Undergraduate','Graduate');

INSERT INTO `Undergraduate` (`StudentID`, `DeptID`, `UGStudentType`)
SELECT f.`StudentID`, NULL, 'FullTimeUG'
  FROM `FullTimeUG` f
 WHERE NOT EXISTS (SELECT 1 FROM `Undergraduate` u WHERE u.`StudentID` = f.`StudentID`)
   AND EXISTS     (SELECT 1 FROM `Student` s       WHERE s.`StudentID` = f.`StudentID`);


-- =====================================================================
-- PHASE 3  Primary keys
-- ---------------------------------------------------------------------
-- Eight tables had none. All eight were verified free of duplicates and
-- NULLs in the key columns before this was written.
-- =====================================================================

-- Letter-to-points lookup; also gives Grade columns something to join to.
ALTER TABLE `GradingScale`
  MODIFY `GradeLetter` varchar(2) COLLATE utf8mb4_general_ci NOT NULL,
  ADD PRIMARY KEY (`GradeLetter`);

-- Room subtypes: one row per room, keyed on the room it describes.
ALTER TABLE `Lecture`  ADD PRIMARY KEY (`LectureID`);
ALTER TABLE `Office`   ADD PRIMARY KEY (`OfficeID`);
ALTER TABLE `Lab`      ADD PRIMARY KEY (`LabID`);

-- The prerequisite relation is the pair, not either column alone.
ALTER TABLE `CoursePrerequisite`
  ADD PRIMARY KEY (`CourseID`, `PrerequisiteCourseID`);

-- AuditLog is empty and LogID was never auto-incrementing, so the
-- application had to supply it by hand. Make the table generate it.
ALTER TABLE `AuditLog`
  MODIFY `LogID` int NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (`LogID`);

-- The two remaining keyless tables are scratch, not schema:
--   DegreeAudit_backup  1601 rows, predates the retype of
--                       Courses_Taken / Courses_Needed from int to text
--   KeepFaculty         939 rows, 313 distinct -- the "faculty to keep"
--                       list from the purge, each ID stored three times
-- Verified unreferenced: neither name appears in any .php, .js or .html
-- file in the project. Dropped, so that every remaining table has a key.
DROP TABLE IF EXISTS `DegreeAudit_backup`;
DROP TABLE IF EXISTS `KeepFaculty`;


-- =====================================================================
-- PHASE 4  Foreign keys
-- ---------------------------------------------------------------------
-- 89 constraints. Where an fk_* index already exists InnoDB reuses it,
-- so this adds no duplicate indexes; where none exists InnoDB creates
-- one, which is why several tables gain an index here.
--
-- [TRANSCRIPT] marks the four cascades that reach academic records.
-- =====================================================================

-- Login
ALTER TABLE `Login`
  ADD CONSTRAINT `fk_Login_LoginID` FOREIGN KEY (`LoginID`) REFERENCES `Users` (`UserID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Login`
  ADD CONSTRAINT `fk_Login_Email` FOREIGN KEY (`Email`) REFERENCES `Users` (`Email`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Admin
ALTER TABLE `Admin`
  ADD CONSTRAINT `fk_Admin_AdminID` FOREIGN KEY (`AdminID`) REFERENCES `Users` (`UserID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ViewAdmin
ALTER TABLE `ViewAdmin`
  ADD CONSTRAINT `fk_ViewAdmin_AdminID` FOREIGN KEY (`AdminID`) REFERENCES `Admin` (`AdminID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- UpdateAdmin
ALTER TABLE `UpdateAdmin`
  ADD CONSTRAINT `fk_UpdateAdmin_AdminID` FOREIGN KEY (`AdminID`) REFERENCES `Admin` (`AdminID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- StatStaff
ALTER TABLE `StatStaff`
  ADD CONSTRAINT `fk_StatStaff_StatStaffID` FOREIGN KEY (`StatStaffID`) REFERENCES `Users` (`UserID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Faculty
ALTER TABLE `Faculty`
  ADD CONSTRAINT `fk_Faculty_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Users` (`UserID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Faculty`
  ADD CONSTRAINT `fk_Faculty_OfficeID` FOREIGN KEY (`OfficeID`) REFERENCES `Room` (`RoomID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- FullTimeFaculty
ALTER TABLE `FullTimeFaculty`
  ADD CONSTRAINT `fk_FullTimeFaculty_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- PartTimeFaculty
ALTER TABLE `PartTimeFaculty`
  ADD CONSTRAINT `fk_PartTimeFaculty_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Faculty_Dept
ALTER TABLE `Faculty_Dept`
  ADD CONSTRAINT `fk_Faculty_Dept_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Faculty_Dept`
  ADD CONSTRAINT `fk_Faculty_Dept_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Chair
ALTER TABLE `Chair`
  ADD CONSTRAINT `fk_Chair_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Advisor
ALTER TABLE `Advisor`
  ADD CONSTRAINT `fk_Advisor_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Advisor`
  ADD CONSTRAINT `fk_Advisor_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Advisor`
  ADD CONSTRAINT `fk_Advisor_AssignedBy` FOREIGN KEY (`AssignedBy`) REFERENCES `Users` (`UserID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- FacultyHistory
ALTER TABLE `FacultyHistory`
  ADD CONSTRAINT `fk_FacultyHistory_CRN` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `FacultyHistory`
  ADD CONSTRAINT `fk_FacultyHistory_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `FacultyHistory`
  ADD CONSTRAINT `fk_FacultyHistory_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `FacultyHistory`
  ADD CONSTRAINT `fk_FacultyHistory_SemesterID` FOREIGN KEY (`SemesterID`) REFERENCES `Semester` (`SemesterID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `FacultyHistory`
  ADD CONSTRAINT `fk_FacultyHistory_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Student
ALTER TABLE `Student`
  ADD CONSTRAINT `fk_Student_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Users` (`UserID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Student`
  ADD CONSTRAINT `fk_Student_MajorID` FOREIGN KEY (`MajorID`) REFERENCES `Major` (`MajorID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `Student`
  ADD CONSTRAINT `fk_Student_MinorID` FOREIGN KEY (`MinorID`) REFERENCES `Minor` (`MinorID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Undergraduate
ALTER TABLE `Undergraduate`
  ADD CONSTRAINT `fk_Undergraduate_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Undergraduate`
  ADD CONSTRAINT `fk_Undergraduate_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Graduate
ALTER TABLE `Graduate`
  ADD CONSTRAINT `fk_Graduate_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `Graduate`
  ADD CONSTRAINT `fk_Graduate_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `Graduate`
  ADD CONSTRAINT `fk_Graduate_ProgramID` FOREIGN KEY (`ProgramID`) REFERENCES `Program` (`ProgramID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- FullTimeUG
ALTER TABLE `FullTimeUG`
  ADD CONSTRAINT `fk_FullTimeUG_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Undergraduate` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- PartTimeUG
ALTER TABLE `PartTimeUG`
  ADD CONSTRAINT `fk_PartTimeUG_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Undergraduate` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- FullTimeGrad
ALTER TABLE `FullTimeGrad`
  ADD CONSTRAINT `fk_FullTimeGrad_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Graduate` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- PartTimeGrad
ALTER TABLE `PartTimeGrad`
  ADD CONSTRAINT `fk_PartTimeGrad_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Graduate` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- StudentHold
ALTER TABLE `StudentHold`
  ADD CONSTRAINT `fk_StudentHold_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `StudentHold`
  ADD CONSTRAINT `fk_StudentHold_HoldID` FOREIGN KEY (`HoldID`) REFERENCES `Hold` (`HoldID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Department
ALTER TABLE `Department`
  ADD CONSTRAINT `fk_Department_RoomID` FOREIGN KEY (`RoomID`) REFERENCES `Room` (`RoomID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `Department`
  ADD CONSTRAINT `fk_Department_ChairID` FOREIGN KEY (`ChairID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Course
ALTER TABLE `Course`
  ADD CONSTRAINT `fk_Course_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- CoursePrerequisite
ALTER TABLE `CoursePrerequisite`
  ADD CONSTRAINT `fk_CoursePrerequisite_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `CoursePrerequisite`
  ADD CONSTRAINT `fk_CoursePrerequisite_PrerequisiteCourseID` FOREIGN KEY (`PrerequisiteCourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Major
ALTER TABLE `Major`
  ADD CONSTRAINT `fk_Major_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Minor
ALTER TABLE `Minor`
  ADD CONSTRAINT `fk_Minor_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Program
ALTER TABLE `Program`
  ADD CONSTRAINT `fk_Program_DeptID` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- MajorRequirement
ALTER TABLE `MajorRequirement`
  ADD CONSTRAINT `fk_MajorRequirement_MajorID` FOREIGN KEY (`MajorID`) REFERENCES `Major` (`MajorID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `MajorRequirement`
  ADD CONSTRAINT `fk_MajorRequirement_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- MinorRequirement
ALTER TABLE `MinorRequirement`
  ADD CONSTRAINT `fk_MinorRequirement_MinorID` FOREIGN KEY (`MinorID`) REFERENCES `Minor` (`MinorID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `MinorRequirement`
  ADD CONSTRAINT `fk_MinorRequirement_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ProgramRequirement
ALTER TABLE `ProgramRequirement`
  ADD CONSTRAINT `fk_ProgramRequirement_ProgramID` FOREIGN KEY (`ProgramID`) REFERENCES `Program` (`ProgramID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `ProgramRequirement`
  ADD CONSTRAINT `fk_ProgramRequirement_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- StudentMajor
ALTER TABLE `StudentMajor`
  ADD CONSTRAINT `fk_StudentMajor_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `StudentMajor`
  ADD CONSTRAINT `fk_StudentMajor_MajorID` FOREIGN KEY (`MajorID`) REFERENCES `Major` (`MajorID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- StudentMinor
ALTER TABLE `StudentMinor`
  ADD CONSTRAINT `fk_StudentMinor_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `StudentMinor`
  ADD CONSTRAINT `fk_StudentMinor_MinorID` FOREIGN KEY (`MinorID`) REFERENCES `Minor` (`MinorID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- DegreeAudit
ALTER TABLE `DegreeAudit`
  ADD CONSTRAINT `fk_DegreeAudit_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE /* [TRANSCRIPT] */ ON UPDATE CASCADE;
ALTER TABLE `DegreeAudit`
  ADD CONSTRAINT `fk_DegreeAudit_MajorID` FOREIGN KEY (`MajorID`) REFERENCES `Major` (`MajorID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `DegreeAudit`
  ADD CONSTRAINT `fk_DegreeAudit_MinorID` FOREIGN KEY (`MinorID`) REFERENCES `Minor` (`MinorID`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- CourseSection
ALTER TABLE `CourseSection`
  ADD CONSTRAINT `fk_CourseSection_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `CourseSection`
  ADD CONSTRAINT `fk_CourseSection_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `CourseSection`
  ADD CONSTRAINT `fk_CourseSection_TimeSlotID` FOREIGN KEY (`TimeSlotID`) REFERENCES `TimeSlot` (`TS_ID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `CourseSection`
  ADD CONSTRAINT `fk_CourseSection_RoomID` FOREIGN KEY (`RoomID`) REFERENCES `Room` (`RoomID`)
  ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `CourseSection`
  ADD CONSTRAINT `fk_CourseSection_SemesterID` FOREIGN KEY (`SemesterID`) REFERENCES `Semester` (`SemesterID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- TimeSlotDay
ALTER TABLE `TimeSlotDay`
  ADD CONSTRAINT `fk_TimeSlotDay_TS_ID` FOREIGN KEY (`TS_ID`) REFERENCES `TimeSlot` (`TS_ID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `TimeSlotDay`
  ADD CONSTRAINT `fk_TimeSlotDay_DayID` FOREIGN KEY (`DayID`) REFERENCES `Day` (`DayID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- TimeSlotPeriod
ALTER TABLE `TimeSlotPeriod`
  ADD CONSTRAINT `fk_TimeSlotPeriod_TS_ID` FOREIGN KEY (`TS_ID`) REFERENCES `TimeSlot` (`TS_ID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `TimeSlotPeriod`
  ADD CONSTRAINT `fk_TimeSlotPeriod_PeriodID` FOREIGN KEY (`PeriodID`) REFERENCES `Period` (`PeriodID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Room
ALTER TABLE `Room`
  ADD CONSTRAINT `fk_Room_BuildingID` FOREIGN KEY (`BuildingID`) REFERENCES `Building` (`BuildingID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Lecture
ALTER TABLE `Lecture`
  ADD CONSTRAINT `fk_Lecture_LectureID` FOREIGN KEY (`LectureID`) REFERENCES `Room` (`RoomID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Office
ALTER TABLE `Office`
  ADD CONSTRAINT `fk_Office_OfficeID` FOREIGN KEY (`OfficeID`) REFERENCES `Room` (`RoomID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Lab
ALTER TABLE `Lab`
  ADD CONSTRAINT `fk_Lab_LabID` FOREIGN KEY (`LabID`) REFERENCES `Room` (`RoomID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- StudentEnrollment
ALTER TABLE `StudentEnrollment`
  ADD CONSTRAINT `fk_StudentEnrollment_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE /* [TRANSCRIPT] */ ON UPDATE CASCADE;
ALTER TABLE `StudentEnrollment`
  ADD CONSTRAINT `fk_StudentEnrollment_SemesterID` FOREIGN KEY (`SemesterID`) REFERENCES `Semester` (`SemesterID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `StudentEnrollment`
  ADD CONSTRAINT `fk_StudentEnrollment_CRN` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `StudentEnrollment`
  ADD CONSTRAINT `fk_StudentEnrollment_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- StudentHistory
ALTER TABLE `StudentHistory`
  ADD CONSTRAINT `fk_StudentHistory_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE /* [TRANSCRIPT] */ ON UPDATE CASCADE;
ALTER TABLE `StudentHistory`
  ADD CONSTRAINT `fk_StudentHistory_CRN` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `StudentHistory`
  ADD CONSTRAINT `fk_StudentHistory_SemesterID` FOREIGN KEY (`SemesterID`) REFERENCES `Semester` (`SemesterID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `StudentHistory`
  ADD CONSTRAINT `fk_StudentHistory_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- CourseSectionAttendance
ALTER TABLE `CourseSectionAttendance`
  ADD CONSTRAINT `fk_CourseSectionAttendance_StudentID` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`)
  ON DELETE CASCADE /* [TRANSCRIPT] */ ON UPDATE CASCADE;
ALTER TABLE `CourseSectionAttendance`
  ADD CONSTRAINT `fk_CourseSectionAttendance_CRN` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `CourseSectionAttendance`
  ADD CONSTRAINT `fk_CourseSectionAttendance_CourseID` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Messages
ALTER TABLE `Messages`
  ADD CONSTRAINT `fk_Messages_SenderEmail` FOREIGN KEY (`SenderEmail`) REFERENCES `Users` (`Email`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `Messages`
  ADD CONSTRAINT `fk_Messages_RecipientEmail` FOREIGN KEY (`RecipientEmail`) REFERENCES `Users` (`Email`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- MessageCopies
ALTER TABLE `MessageCopies`
  ADD CONSTRAINT `fk_MessageCopies_MessageID` FOREIGN KEY (`MessageID`) REFERENCES `Messages` (`MessageID`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `MessageCopies`
  ADD CONSTRAINT `fk_MessageCopies_OwnerEmail` FOREIGN KEY (`OwnerEmail`) REFERENCES `Users` (`Email`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ArchiveMessages
ALTER TABLE `ArchiveMessages`
  ADD CONSTRAINT `fk_ArchiveMessages_SenderEmail` FOREIGN KEY (`SenderEmail`) REFERENCES `Users` (`Email`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `ArchiveMessages`
  ADD CONSTRAINT `fk_ArchiveMessages_RecipientEmail` FOREIGN KEY (`RecipientEmail`) REFERENCES `Users` (`Email`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- AdminAnnouncements
ALTER TABLE `AdminAnnouncements`
  ADD CONSTRAINT `fk_AdminAnnouncements_AdminID` FOREIGN KEY (`AdminID`) REFERENCES `Admin` (`AdminID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- CourseAnnouncements
ALTER TABLE `CourseAnnouncements`
  ADD CONSTRAINT `fk_CourseAnnouncements_CRN` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `CourseAnnouncements`
  ADD CONSTRAINT `fk_CourseAnnouncements_FacultyID` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- =====================================================================
-- PHASE 5  Housekeeping
-- =====================================================================

-- Users.Email carries two identical unique indexes, `Email` and
-- `Email_2`. The foreign keys above reference Users.Email and need one
-- of them; the second is dead weight on every insert and update.
-- Dropped last, so the constraints in Phase 4 have an index to bind to.
ALTER TABLE `Users` DROP INDEX `Email_2`;

-- Optional: the grade columns now have a lookup table with a key on it,
-- and every value in both columns is already one of the 11 valid
-- letters. Uncomment to enforce it.
-- ALTER TABLE `StudentEnrollment`
--   ADD CONSTRAINT `fk_StudentEnrollment_Grade` FOREIGN KEY (`Grade`)
--   REFERENCES `GradingScale` (`GradeLetter`) ON DELETE RESTRICT ON UPDATE CASCADE;
-- ALTER TABLE `StudentHistory`
--   ADD CONSTRAINT `fk_StudentHistory_Grade` FOREIGN KEY (`Grade`)
--   REFERENCES `GradingScale` (`GradeLetter`) ON DELETE RESTRICT ON UPDATE CASCADE;


-- =====================================================================
-- PHASE 6  Verification
-- =====================================================================

SELECT COUNT(*) AS foreign_keys_now_declared
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'University' AND CONSTRAINT_TYPE = 'FOREIGN KEY';

SELECT COUNT(*) AS tables_still_without_primary_key
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA = 'University'
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS c
    WHERE c.CONSTRAINT_SCHEMA = t.TABLE_SCHEMA
      AND c.TABLE_NAME = t.TABLE_NAME
      AND c.CONSTRAINT_TYPE = 'PRIMARY KEY');

-- Expected: 89 foreign keys, and 0 tables without a primary key.
-- All 56 remaining tables have a primary key.
