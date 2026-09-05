-- =====================================================================
-- 004_fix_referential_actions.sql
-- Corrects seven ON DELETE rules from 002 that were either too
-- permissive (silent cascades through reference data) or too strict
-- (an audit stamp vetoing user deletion).
-- =====================================================================
--
--   mysql -h 127.0.0.1 -u root University < 004_fix_referential_actions.sql
--
-- ON UPDATE CASCADE is correct on all 89 constraints and is unchanged:
-- renaming a natural key (Course.CourseID, Users.Email, Room.RoomID,
-- Semester.SemesterID, Building.BuildingID) propagates to every child.
-- Verified by renaming a CourseID on a clone -- 16 dependent rows
-- followed it, none stranded. The schema has no triggers, so the usual
-- "cascades do not fire triggers" caveat does not apply here.
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. An audit stamp should not veto deleting a user
-- ---------------------------------------------------------------------
-- Advisor.AssignedBy records who made the assignment. As RESTRICT it
-- blocks deletion of any user who has ever assigned an advisor -- the
-- first thing that fails when DeleteUsers.php runs. Made nullable so
-- the reference can be blanked and the advising row survives.
ALTER TABLE `Advisor` DROP FOREIGN KEY `fk_Advisor_AssignedBy`;
ALTER TABLE `Advisor` MODIFY `AssignedBy` int unsigned NULL;
ALTER TABLE `Advisor`
  ADD CONSTRAINT `fk_Advisor_AssignedBy` FOREIGN KEY (`AssignedBy`)
  REFERENCES `Users` (`UserID`) ON DELETE SET NULL ON UPDATE CASCADE;


-- ---------------------------------------------------------------------
-- 2. Reference data must not cascade
-- ---------------------------------------------------------------------
-- Day, Period and Hold are small lookup tables. Under CASCADE, deleting
-- one row silently rewrote operational data: one Day took 9 TimeSlotDay
-- rows with it (unscheduling classes from that weekday), one Period took
-- 2, one Hold type took 31 student holds. Deleting reference data that
-- is in use should fail, not propagate.
ALTER TABLE `TimeSlotDay` DROP FOREIGN KEY `fk_TimeSlotDay_DayID`;
ALTER TABLE `TimeSlotDay`
  ADD CONSTRAINT `fk_TimeSlotDay_DayID` FOREIGN KEY (`DayID`)
  REFERENCES `Day` (`DayID`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `TimeSlotPeriod` DROP FOREIGN KEY `fk_TimeSlotPeriod_PeriodID`;
ALTER TABLE `TimeSlotPeriod`
  ADD CONSTRAINT `fk_TimeSlotPeriod_PeriodID` FOREIGN KEY (`PeriodID`)
  REFERENCES `Period` (`PeriodID`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `StudentHold` DROP FOREIGN KEY `fk_StudentHold_HoldID`;
ALTER TABLE `StudentHold`
  ADD CONSTRAINT `fk_StudentHold_HoldID` FOREIGN KEY (`HoldID`)
  REFERENCES `Hold` (`HoldID`) ON DELETE RESTRICT ON UPDATE CASCADE;


-- ---------------------------------------------------------------------
-- 3. Deleting a major must not erase student declarations
-- ---------------------------------------------------------------------
-- Measured on the live data: deleting one Major silently removed 123
-- StudentMajor rows. This is the exact failure that produced the 400
-- orphaned MajorRequirement rows 002 had to clean up. A major with
-- students should be undeletable until they are moved.
--
-- MajorRequirement and MinorRequirement stay CASCADE: those rows are the
-- major's own definition, and belong with it.
ALTER TABLE `StudentMajor` DROP FOREIGN KEY `fk_StudentMajor_MajorID`;
ALTER TABLE `StudentMajor`
  ADD CONSTRAINT `fk_StudentMajor_MajorID` FOREIGN KEY (`MajorID`)
  REFERENCES `Major` (`MajorID`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `StudentMinor` DROP FOREIGN KEY `fk_StudentMinor_MinorID`;
ALTER TABLE `StudentMinor`
  ADD CONSTRAINT `fk_StudentMinor_MinorID` FOREIGN KEY (`MinorID`)
  REFERENCES `Minor` (`MinorID`) ON DELETE RESTRICT ON UPDATE CASCADE;


-- ---------------------------------------------------------------------
-- 4. One parent, one policy
-- ---------------------------------------------------------------------
-- CourseSection.CRN was referenced three different ways: RESTRICT from
-- StudentEnrollment and StudentHistory, CASCADE from attendance. No
-- section currently has attendance without enrollment, so the cascade
-- never fires -- but the split policy is a trap for whoever reads it
-- next. Attendance is a record; it gets the same protection.
ALTER TABLE `CourseSectionAttendance` DROP FOREIGN KEY `fk_CourseSectionAttendance_CRN`;
ALTER TABLE `CourseSectionAttendance`
  ADD CONSTRAINT `fk_CourseSectionAttendance_CRN` FOREIGN KEY (`CRN`)
  REFERENCES `CourseSection` (`CRN`) ON DELETE RESTRICT ON UPDATE CASCADE;


-- ---------------------------------------------------------------------
-- Verification
-- ---------------------------------------------------------------------
SELECT rc.DELETE_RULE, COUNT(*) AS constraints
  FROM information_schema.REFERENTIAL_CONSTRAINTS rc
 WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
 GROUP BY rc.DELETE_RULE ORDER BY 2 DESC;
-- Expected after this script: 46 CASCADE, 26 RESTRICT, 17 SET NULL.
