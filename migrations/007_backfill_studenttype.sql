-- =====================================================================
-- 007_backfill_studenttype.sql
-- Rebuilds Student.StudentType from the subtype tables and stops it
-- drifting again.
-- =====================================================================
--
--   mysql -h 127.0.0.1 -u root University < 007_backfill_studenttype.sql
--
-- ---------------------------------------------------------------------
-- WHY
-- ---------------------------------------------------------------------
-- Of 1,602 students, only 96 had a StudentType that matched reality:
--
--   1,148  blank, actually Undergraduate
--     270  blank, actually Graduate
--      62  said Graduate,      actually Undergraduate
--      26  said Undergraduate, actually Graduate
--      81  said Undergraduate, correct
--      15  said Graduate,      correct
--
-- Seven places branch on this column -- degree_audit.php,
-- student_dashboard.php, student_profile.php, transcript.php,
-- ViewAdvisees.php, ViewStudents.php and get_students.php -- plus
-- statstaff_dashboard.php counts by it, reporting 77 graduates and 107
-- undergraduates against real totals of 311 and 1,291. get_students.php
-- filters on it and so offers 184 of 1,602 students.
--
-- The Undergraduate and Graduate tables are the reliable source: every
-- student appears in exactly one of them (1,291 + 311 = 1,602, none in
-- both, none in neither) and foreign keys keep them that way.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Before
-- ---------------------------------------------------------------------
SELECT IFNULL(NULLIF(s.`StudentType`,''),'(blank)') AS student_type_says,
       CASE WHEN EXISTS (SELECT 1 FROM `Graduate` g WHERE g.`StudentID` = s.`StudentID`)
            THEN 'Graduate' ELSE 'Undergraduate' END AS actually_is,
       COUNT(*) AS rows_before
  FROM `Student` s
 GROUP BY student_type_says, actually_is
 ORDER BY rows_before DESC;


-- ---------------------------------------------------------------------
-- 2. Backfill from the subtype tables
-- ---------------------------------------------------------------------
UPDATE `Student` s
   SET s.`StudentType` = 'Graduate'
 WHERE EXISTS (SELECT 1 FROM `Graduate` g WHERE g.`StudentID` = s.`StudentID`)
   AND s.`StudentType` <> 'Graduate';

UPDATE `Student` s
   SET s.`StudentType` = 'Undergraduate'
 WHERE EXISTS (SELECT 1 FROM `Undergraduate` u WHERE u.`StudentID` = s.`StudentID`)
   AND s.`StudentType` <> 'Undergraduate';


-- ---------------------------------------------------------------------
-- 3. Stop it happening again
-- ---------------------------------------------------------------------
-- The blanks are how MySQL records an invalid ENUM value when strict
-- mode is off: rather than rejecting it, the server stores the special
-- empty-string member. This project sets no sql_mode, so that path is
-- open on every insert.
--
-- A CHECK constraint closes it at the database, for every writer --
-- CreateUsers.php, UpdateUsers.php, phpMyAdmin, a stray script -- rather
-- than trusting each one to validate first. MySQL has enforced CHECK
-- since 8.0.16.
ALTER TABLE `Student`
  ADD CONSTRAINT `chk_Student_StudentType`
  CHECK (`StudentType` IN ('Graduate','Undergraduate'));


-- ---------------------------------------------------------------------
-- 4. After
-- ---------------------------------------------------------------------
SELECT s.`StudentType`,
       COUNT(*) AS rows_after,
       SUM(s.`StudentType` = CASE WHEN EXISTS (SELECT 1 FROM `Graduate` g WHERE g.`StudentID` = s.`StudentID`)
                                  THEN 'Graduate' ELSE 'Undergraduate' END) AS agreeing_with_subtype_table
  FROM `Student` s
 GROUP BY s.`StudentType`;
-- Expected: Undergraduate 1291, Graduate 311, each fully agreeing.
