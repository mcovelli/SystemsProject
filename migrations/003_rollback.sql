-- =====================================================================
-- 003_rollback.sql
-- Removes everything 002_add_keys.sql added, in reverse order.
-- =====================================================================
--
-- This undoes the CONSTRAINTS ONLY. It does not restore the rows that
-- Phase 2 deleted, and it does not undo the column type changes in
-- Phase 1. To get those back, restore the dump you took before running
-- 002:
--
--   mysql -h 127.0.0.1 -u root < University_before_keys.sql
--
--   mysql -h 127.0.0.1 -u root University < 003_rollback.sql
-- =====================================================================

SET NAMES utf8mb4;

-- --- Phase 5 ---------------------------------------------------------
ALTER TABLE `Users` ADD UNIQUE KEY `Email_2` (`Email`);

-- --- Phase 4: foreign keys, children before parents ------------------
ALTER TABLE `CourseAnnouncements` DROP FOREIGN KEY `fk_CourseAnnouncements_FacultyID`;
ALTER TABLE `CourseAnnouncements` DROP FOREIGN KEY `fk_CourseAnnouncements_CRN`;
ALTER TABLE `AdminAnnouncements` DROP FOREIGN KEY `fk_AdminAnnouncements_AdminID`;
ALTER TABLE `ArchiveMessages` DROP FOREIGN KEY `fk_ArchiveMessages_RecipientEmail`;
ALTER TABLE `ArchiveMessages` DROP FOREIGN KEY `fk_ArchiveMessages_SenderEmail`;
ALTER TABLE `MessageCopies` DROP FOREIGN KEY `fk_MessageCopies_OwnerEmail`;
ALTER TABLE `MessageCopies` DROP FOREIGN KEY `fk_MessageCopies_MessageID`;
ALTER TABLE `Messages` DROP FOREIGN KEY `fk_Messages_RecipientEmail`;
ALTER TABLE `Messages` DROP FOREIGN KEY `fk_Messages_SenderEmail`;
ALTER TABLE `CourseSectionAttendance` DROP FOREIGN KEY `fk_CourseSectionAttendance_CourseID`;
ALTER TABLE `CourseSectionAttendance` DROP FOREIGN KEY `fk_CourseSectionAttendance_CRN`;
ALTER TABLE `CourseSectionAttendance` DROP FOREIGN KEY `fk_CourseSectionAttendance_StudentID`;
ALTER TABLE `StudentHistory` DROP FOREIGN KEY `fk_StudentHistory_CourseID`;
ALTER TABLE `StudentHistory` DROP FOREIGN KEY `fk_StudentHistory_SemesterID`;
ALTER TABLE `StudentHistory` DROP FOREIGN KEY `fk_StudentHistory_CRN`;
ALTER TABLE `StudentHistory` DROP FOREIGN KEY `fk_StudentHistory_StudentID`;
ALTER TABLE `StudentEnrollment` DROP FOREIGN KEY `fk_StudentEnrollment_CourseID`;
ALTER TABLE `StudentEnrollment` DROP FOREIGN KEY `fk_StudentEnrollment_CRN`;
ALTER TABLE `StudentEnrollment` DROP FOREIGN KEY `fk_StudentEnrollment_SemesterID`;
ALTER TABLE `StudentEnrollment` DROP FOREIGN KEY `fk_StudentEnrollment_StudentID`;
ALTER TABLE `Lab` DROP FOREIGN KEY `fk_Lab_LabID`;
ALTER TABLE `Office` DROP FOREIGN KEY `fk_Office_OfficeID`;
ALTER TABLE `Lecture` DROP FOREIGN KEY `fk_Lecture_LectureID`;
ALTER TABLE `Room` DROP FOREIGN KEY `fk_Room_BuildingID`;
ALTER TABLE `TimeSlotPeriod` DROP FOREIGN KEY `fk_TimeSlotPeriod_PeriodID`;
ALTER TABLE `TimeSlotPeriod` DROP FOREIGN KEY `fk_TimeSlotPeriod_TS_ID`;
ALTER TABLE `TimeSlotDay` DROP FOREIGN KEY `fk_TimeSlotDay_DayID`;
ALTER TABLE `TimeSlotDay` DROP FOREIGN KEY `fk_TimeSlotDay_TS_ID`;
ALTER TABLE `CourseSection` DROP FOREIGN KEY `fk_CourseSection_SemesterID`;
ALTER TABLE `CourseSection` DROP FOREIGN KEY `fk_CourseSection_RoomID`;
ALTER TABLE `CourseSection` DROP FOREIGN KEY `fk_CourseSection_TimeSlotID`;
ALTER TABLE `CourseSection` DROP FOREIGN KEY `fk_CourseSection_FacultyID`;
ALTER TABLE `CourseSection` DROP FOREIGN KEY `fk_CourseSection_CourseID`;
ALTER TABLE `DegreeAudit` DROP FOREIGN KEY `fk_DegreeAudit_MinorID`;
ALTER TABLE `DegreeAudit` DROP FOREIGN KEY `fk_DegreeAudit_MajorID`;
ALTER TABLE `DegreeAudit` DROP FOREIGN KEY `fk_DegreeAudit_StudentID`;
ALTER TABLE `StudentMinor` DROP FOREIGN KEY `fk_StudentMinor_MinorID`;
ALTER TABLE `StudentMinor` DROP FOREIGN KEY `fk_StudentMinor_StudentID`;
ALTER TABLE `StudentMajor` DROP FOREIGN KEY `fk_StudentMajor_MajorID`;
ALTER TABLE `StudentMajor` DROP FOREIGN KEY `fk_StudentMajor_StudentID`;
ALTER TABLE `ProgramRequirement` DROP FOREIGN KEY `fk_ProgramRequirement_CourseID`;
ALTER TABLE `ProgramRequirement` DROP FOREIGN KEY `fk_ProgramRequirement_ProgramID`;
ALTER TABLE `MinorRequirement` DROP FOREIGN KEY `fk_MinorRequirement_CourseID`;
ALTER TABLE `MinorRequirement` DROP FOREIGN KEY `fk_MinorRequirement_MinorID`;
ALTER TABLE `MajorRequirement` DROP FOREIGN KEY `fk_MajorRequirement_CourseID`;
ALTER TABLE `MajorRequirement` DROP FOREIGN KEY `fk_MajorRequirement_MajorID`;
ALTER TABLE `Program` DROP FOREIGN KEY `fk_Program_DeptID`;
ALTER TABLE `Minor` DROP FOREIGN KEY `fk_Minor_DeptID`;
ALTER TABLE `Major` DROP FOREIGN KEY `fk_Major_DeptID`;
ALTER TABLE `CoursePrerequisite` DROP FOREIGN KEY `fk_CoursePrerequisite_PrerequisiteCourseID`;
ALTER TABLE `CoursePrerequisite` DROP FOREIGN KEY `fk_CoursePrerequisite_CourseID`;
ALTER TABLE `Course` DROP FOREIGN KEY `fk_Course_DeptID`;
ALTER TABLE `Department` DROP FOREIGN KEY `fk_Department_ChairID`;
ALTER TABLE `Department` DROP FOREIGN KEY `fk_Department_RoomID`;
ALTER TABLE `StudentHold` DROP FOREIGN KEY `fk_StudentHold_HoldID`;
ALTER TABLE `StudentHold` DROP FOREIGN KEY `fk_StudentHold_StudentID`;
ALTER TABLE `PartTimeGrad` DROP FOREIGN KEY `fk_PartTimeGrad_StudentID`;
ALTER TABLE `FullTimeGrad` DROP FOREIGN KEY `fk_FullTimeGrad_StudentID`;
ALTER TABLE `PartTimeUG` DROP FOREIGN KEY `fk_PartTimeUG_StudentID`;
ALTER TABLE `FullTimeUG` DROP FOREIGN KEY `fk_FullTimeUG_StudentID`;
ALTER TABLE `Graduate` DROP FOREIGN KEY `fk_Graduate_ProgramID`;
ALTER TABLE `Graduate` DROP FOREIGN KEY `fk_Graduate_DeptID`;
ALTER TABLE `Graduate` DROP FOREIGN KEY `fk_Graduate_StudentID`;
ALTER TABLE `Undergraduate` DROP FOREIGN KEY `fk_Undergraduate_DeptID`;
ALTER TABLE `Undergraduate` DROP FOREIGN KEY `fk_Undergraduate_StudentID`;
ALTER TABLE `Student` DROP FOREIGN KEY `fk_Student_MinorID`;
ALTER TABLE `Student` DROP FOREIGN KEY `fk_Student_MajorID`;
ALTER TABLE `Student` DROP FOREIGN KEY `fk_Student_StudentID`;
ALTER TABLE `FacultyHistory` DROP FOREIGN KEY `fk_FacultyHistory_DeptID`;
ALTER TABLE `FacultyHistory` DROP FOREIGN KEY `fk_FacultyHistory_SemesterID`;
ALTER TABLE `FacultyHistory` DROP FOREIGN KEY `fk_FacultyHistory_CourseID`;
ALTER TABLE `FacultyHistory` DROP FOREIGN KEY `fk_FacultyHistory_FacultyID`;
ALTER TABLE `FacultyHistory` DROP FOREIGN KEY `fk_FacultyHistory_CRN`;
ALTER TABLE `Advisor` DROP FOREIGN KEY `fk_Advisor_AssignedBy`;
ALTER TABLE `Advisor` DROP FOREIGN KEY `fk_Advisor_StudentID`;
ALTER TABLE `Advisor` DROP FOREIGN KEY `fk_Advisor_FacultyID`;
ALTER TABLE `Chair` DROP FOREIGN KEY `fk_Chair_FacultyID`;
ALTER TABLE `Faculty_Dept` DROP FOREIGN KEY `fk_Faculty_Dept_DeptID`;
ALTER TABLE `Faculty_Dept` DROP FOREIGN KEY `fk_Faculty_Dept_FacultyID`;
ALTER TABLE `PartTimeFaculty` DROP FOREIGN KEY `fk_PartTimeFaculty_FacultyID`;
ALTER TABLE `FullTimeFaculty` DROP FOREIGN KEY `fk_FullTimeFaculty_FacultyID`;
ALTER TABLE `Faculty` DROP FOREIGN KEY `fk_Faculty_OfficeID`;
ALTER TABLE `Faculty` DROP FOREIGN KEY `fk_Faculty_FacultyID`;
ALTER TABLE `StatStaff` DROP FOREIGN KEY `fk_StatStaff_StatStaffID`;
ALTER TABLE `UpdateAdmin` DROP FOREIGN KEY `fk_UpdateAdmin_AdminID`;
ALTER TABLE `ViewAdmin` DROP FOREIGN KEY `fk_ViewAdmin_AdminID`;
ALTER TABLE `Admin` DROP FOREIGN KEY `fk_Admin_AdminID`;
ALTER TABLE `Login` DROP FOREIGN KEY `fk_Login_Email`;
ALTER TABLE `Login` DROP FOREIGN KEY `fk_Login_LoginID`;

-- --- Phase 3: primary keys -------------------------------------------
ALTER TABLE `AuditLog` MODIFY `LogID` int NOT NULL, DROP PRIMARY KEY;
ALTER TABLE `CoursePrerequisite` DROP PRIMARY KEY;
ALTER TABLE `Lab`     DROP PRIMARY KEY;
ALTER TABLE `Office`  DROP PRIMARY KEY;
ALTER TABLE `Lecture` DROP PRIMARY KEY;
ALTER TABLE `GradingScale` DROP PRIMARY KEY;

-- --- Phase 1: column types (values already fit, so this is lossless) --
ALTER TABLE `Advisor` MODIFY `AssignedBy` int NOT NULL;
ALTER TABLE `StudentHistory`    MODIFY `CourseID` varchar(16) COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `StudentEnrollment` MODIFY `CourseID` varchar(16) COLLATE utf8mb4_general_ci NOT NULL;

SELECT COUNT(*) AS foreign_keys_remaining
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'University' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
