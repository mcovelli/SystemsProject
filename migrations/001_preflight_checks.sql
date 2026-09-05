-- =====================================================================
-- 001_preflight_checks.sql  --  READ ONLY. Changes nothing.
-- =====================================================================
-- Run this first, and again after 002, to see exactly what data would
-- reject a constraint. Every row returned here is a row that ALTER TABLE
-- would fail on.
--
--   mysql -h 127.0.0.1 -u root University --table < 001_preflight_checks.sql
-- =====================================================================

SELECT '--- current constraint state ---' AS report;

SELECT COUNT(*) AS declared_foreign_keys
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'University' AND CONSTRAINT_TYPE = 'FOREIGN KEY';

SELECT t.TABLE_NAME AS tables_without_primary_key
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA = 'University'
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS c
    WHERE c.CONSTRAINT_SCHEMA = t.TABLE_SCHEMA
      AND c.TABLE_NAME = t.TABLE_NAME
      AND c.CONSTRAINT_TYPE = 'PRIMARY KEY')
ORDER BY 1;

SELECT '--- primary key candidates: duplicates block the PK ---' AS report;

SELECT 'GradingScale.GradeLetter' AS candidate, COUNT(*) AS rows_total, COUNT(DISTINCT GradeLetter) AS distinct_keys FROM GradingScale
UNION ALL SELECT 'Lab.LabID',          COUNT(*), COUNT(DISTINCT LabID)      FROM Lab
UNION ALL SELECT 'Lecture.LectureID',  COUNT(*), COUNT(DISTINCT LectureID)  FROM Lecture
UNION ALL SELECT 'Office.OfficeID',    COUNT(*), COUNT(DISTINCT OfficeID)   FROM Office
UNION ALL SELECT 'AuditLog.LogID',     COUNT(*), COUNT(DISTINCT LogID)      FROM AuditLog
UNION ALL SELECT 'CoursePrerequisite (CourseID, PrerequisiteCourseID)',
                 COUNT(*), COUNT(DISTINCT CourseID, PrerequisiteCourseID)   FROM CoursePrerequisite;

SELECT '--- type reconciliation: values that would not survive ---' AS report;

SELECT 'StudentEnrollment.CourseID longer than 10' AS check_name, COUNT(*) AS violations
  FROM StudentEnrollment WHERE CHAR_LENGTH(CourseID) > 10
UNION ALL
SELECT 'StudentHistory.CourseID longer than 10', COUNT(*)
  FROM StudentHistory WHERE CHAR_LENGTH(CourseID) > 10
UNION ALL
SELECT 'Advisor.AssignedBy negative', COUNT(*)
  FROM Advisor WHERE AssignedBy < 0;

SELECT '--- orphaned rows, per candidate foreign key ---' AS report;
SELECT 'Login.LoginID -> Users.UserID' AS fk, COUNT(*) AS orphans FROM `Login` c LEFT JOIN `Users` p ON c.`LoginID` = p.`UserID` WHERE c.`LoginID` IS NOT NULL AND p.`UserID` IS NULL
UNION ALL
SELECT 'Login.Email -> Users.Email' AS fk, COUNT(*) AS orphans FROM `Login` c LEFT JOIN `Users` p ON c.`Email` = p.`Email` WHERE c.`Email` IS NOT NULL AND p.`Email` IS NULL
UNION ALL
SELECT 'Admin.AdminID -> Users.UserID' AS fk, COUNT(*) AS orphans FROM `Admin` c LEFT JOIN `Users` p ON c.`AdminID` = p.`UserID` WHERE c.`AdminID` IS NOT NULL AND p.`UserID` IS NULL
UNION ALL
SELECT 'ViewAdmin.AdminID -> Admin.AdminID' AS fk, COUNT(*) AS orphans FROM `ViewAdmin` c LEFT JOIN `Admin` p ON c.`AdminID` = p.`AdminID` WHERE c.`AdminID` IS NOT NULL AND p.`AdminID` IS NULL
UNION ALL
SELECT 'UpdateAdmin.AdminID -> Admin.AdminID' AS fk, COUNT(*) AS orphans FROM `UpdateAdmin` c LEFT JOIN `Admin` p ON c.`AdminID` = p.`AdminID` WHERE c.`AdminID` IS NOT NULL AND p.`AdminID` IS NULL
UNION ALL
SELECT 'StatStaff.StatStaffID -> Users.UserID' AS fk, COUNT(*) AS orphans FROM `StatStaff` c LEFT JOIN `Users` p ON c.`StatStaffID` = p.`UserID` WHERE c.`StatStaffID` IS NOT NULL AND p.`UserID` IS NULL
UNION ALL
SELECT 'Faculty.FacultyID -> Users.UserID' AS fk, COUNT(*) AS orphans FROM `Faculty` c LEFT JOIN `Users` p ON c.`FacultyID` = p.`UserID` WHERE c.`FacultyID` IS NOT NULL AND p.`UserID` IS NULL
UNION ALL
SELECT 'Faculty.OfficeID -> Room.RoomID' AS fk, COUNT(*) AS orphans FROM `Faculty` c LEFT JOIN `Room` p ON c.`OfficeID` = p.`RoomID` WHERE c.`OfficeID` IS NOT NULL AND p.`RoomID` IS NULL
UNION ALL
SELECT 'FullTimeFaculty.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `FullTimeFaculty` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'PartTimeFaculty.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `PartTimeFaculty` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'Faculty_Dept.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `Faculty_Dept` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'Faculty_Dept.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Faculty_Dept` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'Chair.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `Chair` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'Advisor.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `Advisor` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'Advisor.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `Advisor` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'Advisor.AssignedBy -> Users.UserID' AS fk, COUNT(*) AS orphans FROM `Advisor` c LEFT JOIN `Users` p ON c.`AssignedBy` = p.`UserID` WHERE c.`AssignedBy` IS NOT NULL AND p.`UserID` IS NULL
UNION ALL
SELECT 'FacultyHistory.CRN -> CourseSection.CRN' AS fk, COUNT(*) AS orphans FROM `FacultyHistory` c LEFT JOIN `CourseSection` p ON c.`CRN` = p.`CRN` WHERE c.`CRN` IS NOT NULL AND p.`CRN` IS NULL
UNION ALL
SELECT 'FacultyHistory.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `FacultyHistory` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'FacultyHistory.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `FacultyHistory` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'FacultyHistory.SemesterID -> Semester.SemesterID' AS fk, COUNT(*) AS orphans FROM `FacultyHistory` c LEFT JOIN `Semester` p ON c.`SemesterID` = p.`SemesterID` WHERE c.`SemesterID` IS NOT NULL AND p.`SemesterID` IS NULL
UNION ALL
SELECT 'FacultyHistory.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `FacultyHistory` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'Student.StudentID -> Users.UserID' AS fk, COUNT(*) AS orphans FROM `Student` c LEFT JOIN `Users` p ON c.`StudentID` = p.`UserID` WHERE c.`StudentID` IS NOT NULL AND p.`UserID` IS NULL
UNION ALL
SELECT 'Student.MajorID -> Major.MajorID' AS fk, COUNT(*) AS orphans FROM `Student` c LEFT JOIN `Major` p ON c.`MajorID` = p.`MajorID` WHERE c.`MajorID` IS NOT NULL AND p.`MajorID` IS NULL
UNION ALL
SELECT 'Student.MinorID -> Minor.MinorID' AS fk, COUNT(*) AS orphans FROM `Student` c LEFT JOIN `Minor` p ON c.`MinorID` = p.`MinorID` WHERE c.`MinorID` IS NOT NULL AND p.`MinorID` IS NULL
UNION ALL
SELECT 'Undergraduate.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `Undergraduate` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'Undergraduate.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Undergraduate` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'Graduate.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `Graduate` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'Graduate.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Graduate` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'Graduate.ProgramID -> Program.ProgramID' AS fk, COUNT(*) AS orphans FROM `Graduate` c LEFT JOIN `Program` p ON c.`ProgramID` = p.`ProgramID` WHERE c.`ProgramID` IS NOT NULL AND p.`ProgramID` IS NULL
UNION ALL
SELECT 'FullTimeUG.StudentID -> Undergraduate.StudentID' AS fk, COUNT(*) AS orphans FROM `FullTimeUG` c LEFT JOIN `Undergraduate` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'PartTimeUG.StudentID -> Undergraduate.StudentID' AS fk, COUNT(*) AS orphans FROM `PartTimeUG` c LEFT JOIN `Undergraduate` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'FullTimeGrad.StudentID -> Graduate.StudentID' AS fk, COUNT(*) AS orphans FROM `FullTimeGrad` c LEFT JOIN `Graduate` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'PartTimeGrad.StudentID -> Graduate.StudentID' AS fk, COUNT(*) AS orphans FROM `PartTimeGrad` c LEFT JOIN `Graduate` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'StudentHold.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `StudentHold` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'StudentHold.HoldID -> Hold.HoldID' AS fk, COUNT(*) AS orphans FROM `StudentHold` c LEFT JOIN `Hold` p ON c.`HoldID` = p.`HoldID` WHERE c.`HoldID` IS NOT NULL AND p.`HoldID` IS NULL
UNION ALL
SELECT 'Department.RoomID -> Room.RoomID' AS fk, COUNT(*) AS orphans FROM `Department` c LEFT JOIN `Room` p ON c.`RoomID` = p.`RoomID` WHERE c.`RoomID` IS NOT NULL AND p.`RoomID` IS NULL
UNION ALL
SELECT 'Department.ChairID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `Department` c LEFT JOIN `Faculty` p ON c.`ChairID` = p.`FacultyID` WHERE c.`ChairID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'Course.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Course` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'CoursePrerequisite.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `CoursePrerequisite` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'CoursePrerequisite.PrerequisiteCourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `CoursePrerequisite` c LEFT JOIN `Course` p ON c.`PrerequisiteCourseID` = p.`CourseID` WHERE c.`PrerequisiteCourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'Major.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Major` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'Minor.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Minor` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'Program.DeptID -> Department.DeptID' AS fk, COUNT(*) AS orphans FROM `Program` c LEFT JOIN `Department` p ON c.`DeptID` = p.`DeptID` WHERE c.`DeptID` IS NOT NULL AND p.`DeptID` IS NULL
UNION ALL
SELECT 'MajorRequirement.MajorID -> Major.MajorID' AS fk, COUNT(*) AS orphans FROM `MajorRequirement` c LEFT JOIN `Major` p ON c.`MajorID` = p.`MajorID` WHERE c.`MajorID` IS NOT NULL AND p.`MajorID` IS NULL
UNION ALL
SELECT 'MajorRequirement.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `MajorRequirement` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'MinorRequirement.MinorID -> Minor.MinorID' AS fk, COUNT(*) AS orphans FROM `MinorRequirement` c LEFT JOIN `Minor` p ON c.`MinorID` = p.`MinorID` WHERE c.`MinorID` IS NOT NULL AND p.`MinorID` IS NULL
UNION ALL
SELECT 'MinorRequirement.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `MinorRequirement` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'ProgramRequirement.ProgramID -> Program.ProgramID' AS fk, COUNT(*) AS orphans FROM `ProgramRequirement` c LEFT JOIN `Program` p ON c.`ProgramID` = p.`ProgramID` WHERE c.`ProgramID` IS NOT NULL AND p.`ProgramID` IS NULL
UNION ALL
SELECT 'ProgramRequirement.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `ProgramRequirement` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'StudentMajor.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `StudentMajor` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'StudentMajor.MajorID -> Major.MajorID' AS fk, COUNT(*) AS orphans FROM `StudentMajor` c LEFT JOIN `Major` p ON c.`MajorID` = p.`MajorID` WHERE c.`MajorID` IS NOT NULL AND p.`MajorID` IS NULL
UNION ALL
SELECT 'StudentMinor.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `StudentMinor` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'StudentMinor.MinorID -> Minor.MinorID' AS fk, COUNT(*) AS orphans FROM `StudentMinor` c LEFT JOIN `Minor` p ON c.`MinorID` = p.`MinorID` WHERE c.`MinorID` IS NOT NULL AND p.`MinorID` IS NULL
UNION ALL
SELECT 'DegreeAudit.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `DegreeAudit` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'DegreeAudit.MajorID -> Major.MajorID' AS fk, COUNT(*) AS orphans FROM `DegreeAudit` c LEFT JOIN `Major` p ON c.`MajorID` = p.`MajorID` WHERE c.`MajorID` IS NOT NULL AND p.`MajorID` IS NULL
UNION ALL
SELECT 'DegreeAudit.MinorID -> Minor.MinorID' AS fk, COUNT(*) AS orphans FROM `DegreeAudit` c LEFT JOIN `Minor` p ON c.`MinorID` = p.`MinorID` WHERE c.`MinorID` IS NOT NULL AND p.`MinorID` IS NULL
UNION ALL
SELECT 'CourseSection.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `CourseSection` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'CourseSection.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `CourseSection` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
UNION ALL
SELECT 'CourseSection.TimeSlotID -> TimeSlot.TS_ID' AS fk, COUNT(*) AS orphans FROM `CourseSection` c LEFT JOIN `TimeSlot` p ON c.`TimeSlotID` = p.`TS_ID` WHERE c.`TimeSlotID` IS NOT NULL AND p.`TS_ID` IS NULL
UNION ALL
SELECT 'CourseSection.RoomID -> Room.RoomID' AS fk, COUNT(*) AS orphans FROM `CourseSection` c LEFT JOIN `Room` p ON c.`RoomID` = p.`RoomID` WHERE c.`RoomID` IS NOT NULL AND p.`RoomID` IS NULL
UNION ALL
SELECT 'CourseSection.SemesterID -> Semester.SemesterID' AS fk, COUNT(*) AS orphans FROM `CourseSection` c LEFT JOIN `Semester` p ON c.`SemesterID` = p.`SemesterID` WHERE c.`SemesterID` IS NOT NULL AND p.`SemesterID` IS NULL
UNION ALL
SELECT 'TimeSlotDay.TS_ID -> TimeSlot.TS_ID' AS fk, COUNT(*) AS orphans FROM `TimeSlotDay` c LEFT JOIN `TimeSlot` p ON c.`TS_ID` = p.`TS_ID` WHERE c.`TS_ID` IS NOT NULL AND p.`TS_ID` IS NULL
UNION ALL
SELECT 'TimeSlotDay.DayID -> Day.DayID' AS fk, COUNT(*) AS orphans FROM `TimeSlotDay` c LEFT JOIN `Day` p ON c.`DayID` = p.`DayID` WHERE c.`DayID` IS NOT NULL AND p.`DayID` IS NULL
UNION ALL
SELECT 'TimeSlotPeriod.TS_ID -> TimeSlot.TS_ID' AS fk, COUNT(*) AS orphans FROM `TimeSlotPeriod` c LEFT JOIN `TimeSlot` p ON c.`TS_ID` = p.`TS_ID` WHERE c.`TS_ID` IS NOT NULL AND p.`TS_ID` IS NULL
UNION ALL
SELECT 'TimeSlotPeriod.PeriodID -> Period.PeriodID' AS fk, COUNT(*) AS orphans FROM `TimeSlotPeriod` c LEFT JOIN `Period` p ON c.`PeriodID` = p.`PeriodID` WHERE c.`PeriodID` IS NOT NULL AND p.`PeriodID` IS NULL
UNION ALL
SELECT 'Room.BuildingID -> Building.BuildingID' AS fk, COUNT(*) AS orphans FROM `Room` c LEFT JOIN `Building` p ON c.`BuildingID` = p.`BuildingID` WHERE c.`BuildingID` IS NOT NULL AND p.`BuildingID` IS NULL
UNION ALL
SELECT 'Lecture.LectureID -> Room.RoomID' AS fk, COUNT(*) AS orphans FROM `Lecture` c LEFT JOIN `Room` p ON c.`LectureID` = p.`RoomID` WHERE c.`LectureID` IS NOT NULL AND p.`RoomID` IS NULL
UNION ALL
SELECT 'Office.OfficeID -> Room.RoomID' AS fk, COUNT(*) AS orphans FROM `Office` c LEFT JOIN `Room` p ON c.`OfficeID` = p.`RoomID` WHERE c.`OfficeID` IS NOT NULL AND p.`RoomID` IS NULL
UNION ALL
SELECT 'Lab.LabID -> Room.RoomID' AS fk, COUNT(*) AS orphans FROM `Lab` c LEFT JOIN `Room` p ON c.`LabID` = p.`RoomID` WHERE c.`LabID` IS NOT NULL AND p.`RoomID` IS NULL
UNION ALL
SELECT 'StudentEnrollment.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `StudentEnrollment` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'StudentEnrollment.SemesterID -> Semester.SemesterID' AS fk, COUNT(*) AS orphans FROM `StudentEnrollment` c LEFT JOIN `Semester` p ON c.`SemesterID` = p.`SemesterID` WHERE c.`SemesterID` IS NOT NULL AND p.`SemesterID` IS NULL
UNION ALL
SELECT 'StudentEnrollment.CRN -> CourseSection.CRN' AS fk, COUNT(*) AS orphans FROM `StudentEnrollment` c LEFT JOIN `CourseSection` p ON c.`CRN` = p.`CRN` WHERE c.`CRN` IS NOT NULL AND p.`CRN` IS NULL
UNION ALL
SELECT 'StudentEnrollment.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `StudentEnrollment` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'StudentHistory.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `StudentHistory` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'StudentHistory.CRN -> CourseSection.CRN' AS fk, COUNT(*) AS orphans FROM `StudentHistory` c LEFT JOIN `CourseSection` p ON c.`CRN` = p.`CRN` WHERE c.`CRN` IS NOT NULL AND p.`CRN` IS NULL
UNION ALL
SELECT 'StudentHistory.SemesterID -> Semester.SemesterID' AS fk, COUNT(*) AS orphans FROM `StudentHistory` c LEFT JOIN `Semester` p ON c.`SemesterID` = p.`SemesterID` WHERE c.`SemesterID` IS NOT NULL AND p.`SemesterID` IS NULL
UNION ALL
SELECT 'StudentHistory.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `StudentHistory` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'CourseSectionAttendance.StudentID -> Student.StudentID' AS fk, COUNT(*) AS orphans FROM `CourseSectionAttendance` c LEFT JOIN `Student` p ON c.`StudentID` = p.`StudentID` WHERE c.`StudentID` IS NOT NULL AND p.`StudentID` IS NULL
UNION ALL
SELECT 'CourseSectionAttendance.CRN -> CourseSection.CRN' AS fk, COUNT(*) AS orphans FROM `CourseSectionAttendance` c LEFT JOIN `CourseSection` p ON c.`CRN` = p.`CRN` WHERE c.`CRN` IS NOT NULL AND p.`CRN` IS NULL
UNION ALL
SELECT 'CourseSectionAttendance.CourseID -> Course.CourseID' AS fk, COUNT(*) AS orphans FROM `CourseSectionAttendance` c LEFT JOIN `Course` p ON c.`CourseID` = p.`CourseID` WHERE c.`CourseID` IS NOT NULL AND p.`CourseID` IS NULL
UNION ALL
SELECT 'Messages.SenderEmail -> Users.Email' AS fk, COUNT(*) AS orphans FROM `Messages` c LEFT JOIN `Users` p ON c.`SenderEmail` = p.`Email` WHERE c.`SenderEmail` IS NOT NULL AND p.`Email` IS NULL
UNION ALL
SELECT 'Messages.RecipientEmail -> Users.Email' AS fk, COUNT(*) AS orphans FROM `Messages` c LEFT JOIN `Users` p ON c.`RecipientEmail` = p.`Email` WHERE c.`RecipientEmail` IS NOT NULL AND p.`Email` IS NULL
UNION ALL
SELECT 'MessageCopies.MessageID -> Messages.MessageID' AS fk, COUNT(*) AS orphans FROM `MessageCopies` c LEFT JOIN `Messages` p ON c.`MessageID` = p.`MessageID` WHERE c.`MessageID` IS NOT NULL AND p.`MessageID` IS NULL
UNION ALL
SELECT 'MessageCopies.OwnerEmail -> Users.Email' AS fk, COUNT(*) AS orphans FROM `MessageCopies` c LEFT JOIN `Users` p ON c.`OwnerEmail` = p.`Email` WHERE c.`OwnerEmail` IS NOT NULL AND p.`Email` IS NULL
UNION ALL
SELECT 'ArchiveMessages.SenderEmail -> Users.Email' AS fk, COUNT(*) AS orphans FROM `ArchiveMessages` c LEFT JOIN `Users` p ON c.`SenderEmail` = p.`Email` WHERE c.`SenderEmail` IS NOT NULL AND p.`Email` IS NULL
UNION ALL
SELECT 'ArchiveMessages.RecipientEmail -> Users.Email' AS fk, COUNT(*) AS orphans FROM `ArchiveMessages` c LEFT JOIN `Users` p ON c.`RecipientEmail` = p.`Email` WHERE c.`RecipientEmail` IS NOT NULL AND p.`Email` IS NULL
UNION ALL
SELECT 'AdminAnnouncements.AdminID -> Admin.AdminID' AS fk, COUNT(*) AS orphans FROM `AdminAnnouncements` c LEFT JOIN `Admin` p ON c.`AdminID` = p.`AdminID` WHERE c.`AdminID` IS NOT NULL AND p.`AdminID` IS NULL
UNION ALL
SELECT 'CourseAnnouncements.CRN -> CourseSection.CRN' AS fk, COUNT(*) AS orphans FROM `CourseAnnouncements` c LEFT JOIN `CourseSection` p ON c.`CRN` = p.`CRN` WHERE c.`CRN` IS NOT NULL AND p.`CRN` IS NULL
UNION ALL
SELECT 'CourseAnnouncements.FacultyID -> Faculty.FacultyID' AS fk, COUNT(*) AS orphans FROM `CourseAnnouncements` c LEFT JOIN `Faculty` p ON c.`FacultyID` = p.`FacultyID` WHERE c.`FacultyID` IS NOT NULL AND p.`FacultyID` IS NULL
ORDER BY orphans DESC, fk;

SELECT '--- sentinel values masquerading as references ---' AS report;

SELECT 'Student.MajorID = 0 (means "undeclared", not a Major row)' AS finding, COUNT(*) AS rows_affected
  FROM Student WHERE MajorID = 0;
