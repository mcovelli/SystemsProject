-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 02, 2025 at 09:59 PM
-- Server version: 12.0.2-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `University`
--

-- --------------------------------------------------------

--
-- Table structure for table `Admin`
--

CREATE TABLE `Admin` (
  `AdminID` int(10) UNSIGNED NOT NULL,
  `SecurityType` enum('VIEW','UPDATE') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `AdminAnnouncements`
--

CREATE TABLE `AdminAnnouncements` (
  `AnnouncementID` int(11) NOT NULL,
  `TargetGroup` enum('ALL','STUDENTS','FACULTY','ADMINS') NOT NULL DEFAULT 'ALL',
  `AdminID` int(10) UNSIGNED NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `DatePosted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Advisor`
--

CREATE TABLE `Advisor` (
  `FacultyID` int(10) UNSIGNED NOT NULL,
  `StudentID` int(10) UNSIGNED NOT NULL,
  `DOA` date NOT NULL,
  `Status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `AssignedBy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ArchiveMessages`
--

CREATE TABLE `ArchiveMessages` (
  `ArchiveID` int(11) NOT NULL,
  `SenderEmail` varchar(100) NOT NULL,
  `RecipientEmail` varchar(100) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `DatePosted` datetime DEFAULT NULL,
  `DateArchived` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `AuditLog`
--

CREATE TABLE `AuditLog` (
  `LogID` int(11) NOT NULL,
  `TableName` varchar(50) DEFAULT NULL,
  `RecordID` varchar(50) DEFAULT NULL,
  `Operation` enum('INSERT','UPDATE','DELETE') DEFAULT NULL,
  `ChangedBy` varchar(50) DEFAULT NULL,
  `ChangeDate` datetime DEFAULT current_timestamp(),
  `Details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Building`
--

CREATE TABLE `Building` (
  `BuildingID` varchar(3) NOT NULL DEFAULT 'TBD',
  `BuildingName` varchar(50) NOT NULL,
  `BuildingType` enum('Academic') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Chair`
--

CREATE TABLE `Chair` (
  `ChairID` int(11) NOT NULL,
  `FacultyID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Course`
--

CREATE TABLE `Course` (
  `CourseID` varchar(10) NOT NULL,
  `CourseName` varchar(50) NOT NULL,
  `DeptID` int(11) NOT NULL,
  `Course_Desc` text DEFAULT NULL,
  `Credits` int(11) NOT NULL DEFAULT 3,
  `CourseType` enum('UNDERGRAD','GRAD') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CourseAnnouncements`
--

CREATE TABLE `CourseAnnouncements` (
  `AnnouncementID` int(11) NOT NULL,
  `CRN` int(11) NOT NULL,
  `FacultyID` int(10) UNSIGNED NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `DatePosted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CoursePrerequisite`
--

CREATE TABLE `CoursePrerequisite` (
  `CourseID` varchar(10) NOT NULL,
  `PrerequisiteCourseID` varchar(10) NOT NULL,
  `MinGradeRequired` varchar(5) NOT NULL DEFAULT 'C',
  `DecisionDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CourseSection`
--

CREATE TABLE `CourseSection` (
  `CRN` int(11) NOT NULL,
  `CourseID` varchar(10) NOT NULL,
  `CourseSectionNo` int(11) NOT NULL,
  `FacultyID` int(10) UNSIGNED DEFAULT NULL,
  `TimeSlotID` int(11) DEFAULT NULL,
  `RoomID` varchar(16) DEFAULT NULL,
  `Year` int(11) DEFAULT NULL,
  `SemesterID` varchar(16) DEFAULT NULL,
  `AvailableSeats` int(11) DEFAULT NULL,
  `Status` enum('PLANNED','IN-PROGRESS','COMPLETED','CANCELLED') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CourseSectionAttendance`
--

CREATE TABLE `CourseSectionAttendance` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `CRN` int(11) NOT NULL,
  `CourseID` varchar(10) NOT NULL,
  `AttendanceDate` date NOT NULL,
  `PresentAbsent` enum('PRESENT','ABSENT') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Day`
--

CREATE TABLE `Day` (
  `DayID` int(11) NOT NULL,
  `DayOfWeek` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `DegreeAudit`
--

CREATE TABLE `DegreeAudit` (
  `DegreeAuditID` int(10) UNSIGNED NOT NULL,
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MajorID` int(11) DEFAULT NULL,
  `MinorID` int(11) DEFAULT NULL,
  `GenerateDate` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Status` varchar(32) NOT NULL DEFAULT 'ACTIVE',
  `Credits_Completed` int(11) NOT NULL DEFAULT 0,
  `Credits_Remaining` int(11) NOT NULL DEFAULT 0,
  `CumulativeGPA` decimal(3,2) DEFAULT 0.00,
  `Courses_Taken` text DEFAULT NULL,
  `Courses_Needed` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `DegreeAudit_backup`
--

CREATE TABLE `DegreeAudit_backup` (
  `DegreeAuditID` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MajorID` int(11) DEFAULT NULL,
  `MinorID` int(11) DEFAULT NULL,
  `GenerateDate` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Status` varchar(32) NOT NULL DEFAULT 'ACTIVE',
  `Credits_Completed` int(11) NOT NULL DEFAULT 0,
  `Credits_Remaining` int(11) NOT NULL DEFAULT 0,
  `CumulativeGPA` decimal(3,2) DEFAULT 0.00,
  `Courses_Taken` int(11) DEFAULT 0,
  `Courses_Needed` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Department`
--

CREATE TABLE `Department` (
  `DeptID` int(11) NOT NULL,
  `DeptName` varchar(25) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `RoomID` varchar(16) NOT NULL,
  `ChairID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Faculty`
--

CREATE TABLE `Faculty` (
  `FacultyID` int(10) UNSIGNED NOT NULL,
  `OfficeID` varchar(16) NOT NULL,
  `Specialty` varchar(25) NOT NULL,
  `Ranking` varchar(25) NOT NULL,
  `FacultyType` enum('PartTimeFaculty','FullTimeFaculty') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FacultyHistory`
--

CREATE TABLE `FacultyHistory` (
  `CRN` int(11) NOT NULL,
  `FacultyID` int(10) UNSIGNED DEFAULT NULL,
  `CourseID` varchar(10) DEFAULT NULL,
  `SemesterID` varchar(16) DEFAULT NULL,
  `FacultyHistoryID` int(11) NOT NULL,
  `Year` int(11) DEFAULT NULL,
  `AssignedDate` datetime DEFAULT NULL,
  `Status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `DeptID` int(11) DEFAULT NULL,
  `HistoryID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Faculty_Dept`
--

CREATE TABLE `Faculty_Dept` (
  `FacultyID` int(10) UNSIGNED NOT NULL,
  `DeptID` int(11) NOT NULL,
  `DOA` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FullTimeFaculty`
--

CREATE TABLE `FullTimeFaculty` (
  `FacultyID` int(10) UNSIGNED NOT NULL,
  `MaxCourses` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FullTimeGrad`
--

CREATE TABLE `FullTimeGrad` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MaxCredits` int(11) DEFAULT 12,
  `MinCredits` int(11) DEFAULT 9,
  `Year` int(11) NOT NULL,
  `CreditsEarned` int(11) NOT NULL,
  `ThesisYear` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FullTimeUG`
--

CREATE TABLE `FullTimeUG` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MaxCredits` int(11) NOT NULL,
  `MinCredits` int(11) NOT NULL,
  `Year` enum('Freshman','Sophomore','Junior','Senior') NOT NULL,
  `CreditsEarned` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GradingScale`
--

CREATE TABLE `GradingScale` (
  `GradeLetter` varchar(2) NOT NULL,
  `GradeValue` decimal(3,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Graduate`
--

CREATE TABLE `Graduate` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `DeptID` int(11) DEFAULT NULL,
  `Year` int(11) NOT NULL,
  `GradStudentType` enum('FullTimeGrad','PartTimeGrad') NOT NULL,
  `ProgramID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Hold`
--

CREATE TABLE `Hold` (
  `HoldID` int(11) NOT NULL,
  `HoldType` enum('FINANCIAL','ACADEMIC','HEALTH') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Lab`
--

CREATE TABLE `Lab` (
  `LabID` varchar(16) NOT NULL,
  `NumWorkStations` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Lecture`
--

CREATE TABLE `Lecture` (
  `LectureID` varchar(16) NOT NULL,
  `NumSeats` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Login`
--

CREATE TABLE `Login` (
  `LoginID` int(10) UNSIGNED NOT NULL,
  `Email` varchar(100) NOT NULL,
  `UserType` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `LoginAttempts` int(11) NOT NULL DEFAULT 0,
  `ResetToken` char(64) DEFAULT NULL,
  `ResetExpiry` datetime DEFAULT NULL,
  `MustReset` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Major`
--

CREATE TABLE `Major` (
  `MajorID` int(11) NOT NULL,
  `DeptID` int(11) DEFAULT NULL,
  `MajorName` varchar(50) NOT NULL,
  `CreditsNeeded` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MajorRequirement`
--

CREATE TABLE `MajorRequirement` (
  `MajorID` int(11) NOT NULL,
  `CourseID` varchar(10) NOT NULL,
  `RequirementType` enum('Core','Elective') DEFAULT NULL,
  `SemesterLevel` tinyint(3) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MessageCopies`
--

CREATE TABLE `MessageCopies` (
  `CopyID` int(11) NOT NULL,
  `MessageID` int(11) NOT NULL,
  `OwnerEmail` varchar(100) NOT NULL,
  `Folder` enum('INBOX','SENT','ARCHIVED') DEFAULT 'INBOX',
  `IsDeleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Messages`
--

CREATE TABLE `Messages` (
  `MessageID` int(11) NOT NULL,
  `SenderEmail` varchar(100) NOT NULL,
  `RecipientEmail` varchar(100) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `DatePosted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Minor`
--

CREATE TABLE `Minor` (
  `MinorID` int(11) NOT NULL,
  `DeptID` int(11) NOT NULL,
  `MinorName` varchar(50) NOT NULL,
  `CreditsNeeded` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MinorRequirement`
--

CREATE TABLE `MinorRequirement` (
  `MinorID` int(11) NOT NULL,
  `CourseID` varchar(10) NOT NULL,
  `RequirementType` enum('Core','Elective') DEFAULT NULL,
  `SemesterLevel` tinyint(3) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Office`
--

CREATE TABLE `Office` (
  `OfficeID` varchar(16) NOT NULL,
  `NumDesks` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PartTimeFaculty`
--

CREATE TABLE `PartTimeFaculty` (
  `FacultyID` int(10) UNSIGNED NOT NULL,
  `MaxCourses` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PartTimeGrad`
--

CREATE TABLE `PartTimeGrad` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MaxCredits` int(11) DEFAULT 6,
  `MinCredits` int(11) DEFAULT 3,
  `Year` int(11) NOT NULL,
  `CreditsEarned` int(11) NOT NULL,
  `ThesisYear` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PartTimeUG`
--

CREATE TABLE `PartTimeUG` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MaxCredits` int(11) NOT NULL,
  `MinCredits` int(11) NOT NULL,
  `Year` enum('Freshman','Sophomore','Junior','Senior') NOT NULL,
  `CreditsEarned` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Period`
--

CREATE TABLE `Period` (
  `PeriodID` int(11) NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Program`
--

CREATE TABLE `Program` (
  `ProgramID` int(11) NOT NULL,
  `ProgramCode` varchar(10) NOT NULL,
  `ProgramName` varchar(100) NOT NULL,
  `DegreeLevel` enum('PhD','MA','MS') NOT NULL,
  `DeptID` int(11) DEFAULT NULL,
  `CreditsRequired` int(11) DEFAULT 30,
  `Status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ProgramRequirement`
--

CREATE TABLE `ProgramRequirement` (
  `RequirementID` int(11) NOT NULL,
  `ProgramID` int(11) NOT NULL,
  `CourseID` varchar(10) NOT NULL,
  `RequirementType` enum('Core','Elective','Capstone') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Room`
--

CREATE TABLE `Room` (
  `RoomID` varchar(4) NOT NULL,
  `RoomNo` varchar(16) NOT NULL DEFAULT 'TBD',
  `BuildingID` varchar(3) NOT NULL DEFAULT 'TBD',
  `RoomType` enum('Lecture','Office','Lab') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Semester`
--

CREATE TABLE `Semester` (
  `SemesterID` varchar(16) NOT NULL,
  `SemesterName` enum('SPRING','FALL') NOT NULL,
  `Year` int(11) NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `AddDropDeadline` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StatStaff`
--

CREATE TABLE `StatStaff` (
  `StatStaffID` int(10) UNSIGNED NOT NULL,
  `StaffName` varchar(25) NOT NULL,
  `Status` enum('Active','Inactive') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Student`
--

CREATE TABLE `Student` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MajorID` int(11) DEFAULT NULL,
  `MinorID` int(11) DEFAULT NULL,
  `StudentType` enum('Graduate','Undergraduate') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StudentEnrollment`
--

CREATE TABLE `StudentEnrollment` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `SemesterID` varchar(16) NOT NULL,
  `CRN` int(11) NOT NULL,
  `CourseID` varchar(16) NOT NULL,
  `Status` enum('COMPLETED','ENROLLED','DROPPED','IN-PROGRESS','WAITLIST','PLANNED') DEFAULT NULL,
  `EnrollmentDate` date NOT NULL,
  `Grade` varchar(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StudentHistory`
--

CREATE TABLE `StudentHistory` (
  `HistoryID` int(11) NOT NULL,
  `StudentID` int(10) UNSIGNED NOT NULL,
  `CRN` int(11) NOT NULL,
  `SemesterID` varchar(16) DEFAULT NULL,
  `Grade` varchar(2) DEFAULT NULL,
  `CourseID` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `StudentHistory`
--
DELIMITER $$
CREATE TRIGGER `trg_recompute_degree_audit_del` AFTER DELETE ON `StudentHistory` FOR EACH ROW BEGIN
  CALL UpdateDegreeAudit(OLD.StudentID);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recompute_degree_audit_ins` AFTER INSERT ON `StudentHistory` FOR EACH ROW BEGIN
  CALL UpdateDegreeAudit(NEW.StudentID);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recompute_degree_audit_upd` AFTER UPDATE ON `StudentHistory` FOR EACH ROW BEGIN
  CALL UpdateDegreeAudit(NEW.StudentID);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `StudentHold`
--

CREATE TABLE `StudentHold` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `HoldID` int(11) NOT NULL,
  `DateOfHold` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StudentMajor`
--

CREATE TABLE `StudentMajor` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MajorID` int(11) NOT NULL,
  `DateOfDeclaration` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StudentMinor`
--

CREATE TABLE `StudentMinor` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `MinorID` int(11) NOT NULL,
  `DateOfDeclaration` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TimeSlot`
--

CREATE TABLE `TimeSlot` (
  `TS_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TimeSlotDay`
--

CREATE TABLE `TimeSlotDay` (
  `TS_ID` int(11) NOT NULL,
  `DayID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TimeSlotPeriod`
--

CREATE TABLE `TimeSlotPeriod` (
  `TS_ID` int(11) NOT NULL,
  `PeriodID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Undergraduate`
--

CREATE TABLE `Undergraduate` (
  `StudentID` int(10) UNSIGNED NOT NULL,
  `DeptID` int(11) DEFAULT NULL,
  `UGStudentType` enum('FullTimeUG','PartTimeUG') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `UpdateAdmin`
--

CREATE TABLE `UpdateAdmin` (
  `AdminID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Users`
--

CREATE TABLE `Users` (
  `UserID` int(10) UNSIGNED NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `MiddleName` varchar(50) DEFAULT NULL,
  `LastName` varchar(50) NOT NULL,
  `HouseNumber` int(11) DEFAULT NULL,
  `Street` varchar(100) NOT NULL,
  `City` varchar(50) NOT NULL,
  `State` varchar(25) NOT NULL,
  `ZIP` varchar(10) NOT NULL,
  `Gender` enum('M','F') DEFAULT NULL,
  `DOB` date DEFAULT NULL,
  `UserType` enum('Student','Faculty','Admin','StatStaff') NOT NULL,
  `Email` varchar(100) NOT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ViewAdmin`
--

CREATE TABLE `ViewAdmin` (
  `AdminID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Admin`
--
ALTER TABLE `Admin`
  ADD PRIMARY KEY (`AdminID`),
  ADD KEY `idx_admin_userid` (`AdminID`);

--
-- Indexes for table `AdminAnnouncements`
--
ALTER TABLE `AdminAnnouncements`
  ADD PRIMARY KEY (`AnnouncementID`),
  ADD KEY `idx_admin` (`AdminID`);

--
-- Indexes for table `Advisor`
--
ALTER TABLE `Advisor`
  ADD PRIMARY KEY (`FacultyID`,`StudentID`),
  ADD UNIQUE KEY `unique_student` (`StudentID`);

--
-- Indexes for table `ArchiveMessages`
--
ALTER TABLE `ArchiveMessages`
  ADD PRIMARY KEY (`ArchiveID`),
  ADD KEY `SenderEmail` (`SenderEmail`),
  ADD KEY `RecipientEmail` (`RecipientEmail`);

--
-- Indexes for table `Chair`
--
ALTER TABLE `Chair`
  ADD PRIMARY KEY (`ChairID`),
  ADD KEY `fk_Chair_Faculty` (`FacultyID`);

--
-- Indexes for table `Course`
--
ALTER TABLE `Course`
  ADD PRIMARY KEY (`CourseID`),
  ADD KEY `idx_course_dept` (`DeptID`);

--
-- Indexes for table `CourseAnnouncements`
--
ALTER TABLE `CourseAnnouncements`
  ADD PRIMARY KEY (`AnnouncementID`),
  ADD KEY `CourseSectionID` (`CRN`),
  ADD KEY `FacultyID` (`FacultyID`);

--
-- Indexes for table `CoursePrerequisite`
--
ALTER TABLE `CoursePrerequisite`
  ADD KEY `idx_prereq_course` (`CourseID`),
  ADD KEY `idx_prereq_required` (`PrerequisiteCourseID`);

--
-- Indexes for table `CourseSection`
--
ALTER TABLE `CourseSection`
  ADD PRIMARY KEY (`CRN`),
  ADD KEY `idx_section_course` (`CourseID`),
  ADD KEY `idx_section_faculty` (`FacultyID`),
  ADD KEY `idx_section_timeslot` (`TimeSlotID`),
  ADD KEY `idx_section_room` (`RoomID`),
  ADD KEY `idx_section_semester` (`SemesterID`);

--
-- Indexes for table `CourseSectionAttendance`
--
ALTER TABLE `CourseSectionAttendance`
  ADD PRIMARY KEY (`StudentID`,`CRN`,`AttendanceDate`),
  ADD UNIQUE KEY `uniq_attendance` (`StudentID`,`CRN`,`AttendanceDate`),
  ADD KEY `fk_CSA_CourseSection` (`CRN`);

--
-- Indexes for table `Day`
--
ALTER TABLE `Day`
  ADD PRIMARY KEY (`DayID`);

--
-- Indexes for table `DegreeAudit`
--
ALTER TABLE `DegreeAudit`
  ADD PRIMARY KEY (`DegreeAuditID`),
  ADD UNIQUE KEY `uniq_audit_student` (`StudentID`),
  ADD KEY `fk_DegreeAudit_Major` (`MajorID`),
  ADD KEY `fk_DegreeAudit_Minor` (`MinorID`);

--
-- Indexes for table `Department`
--
ALTER TABLE `Department`
  ADD PRIMARY KEY (`DeptID`),
  ADD KEY `fk_department_chair` (`ChairID`),
  ADD KEY `fk_Department_Room` (`RoomID`);

--
-- Indexes for table `Faculty`
--
ALTER TABLE `Faculty`
  ADD PRIMARY KEY (`FacultyID`),
  ADD KEY `idx_faculty_userid` (`FacultyID`),
  ADD KEY `fk_Faculty_OfficeRoom` (`OfficeID`);

--
-- Indexes for table `FacultyHistory`
--
ALTER TABLE `FacultyHistory`
  ADD PRIMARY KEY (`HistoryID`),
  ADD KEY `idx_fachist_faculty` (`FacultyID`),
  ADD KEY `idx_fachist_course` (`CourseID`),
  ADD KEY `idx_fachist_semester` (`SemesterID`);

--
-- Indexes for table `Faculty_Dept`
--
ALTER TABLE `Faculty_Dept`
  ADD PRIMARY KEY (`FacultyID`,`DeptID`);

--
-- Indexes for table `FullTimeFaculty`
--
ALTER TABLE `FullTimeFaculty`
  ADD PRIMARY KEY (`FacultyID`),
  ADD KEY `idx_fulltimefaculty_facultyid` (`FacultyID`);

--
-- Indexes for table `FullTimeGrad`
--
ALTER TABLE `FullTimeGrad`
  ADD PRIMARY KEY (`StudentID`),
  ADD KEY `idx_fulltimegrad_student` (`StudentID`);

--
-- Indexes for table `FullTimeUG`
--
ALTER TABLE `FullTimeUG`
  ADD PRIMARY KEY (`StudentID`),
  ADD KEY `idx_fulltimeug_student` (`StudentID`);

--
-- Indexes for table `Graduate`
--
ALTER TABLE `Graduate`
  ADD PRIMARY KEY (`StudentID`),
  ADD KEY `fk_grad_program` (`ProgramID`);

--
-- Indexes for table `Hold`
--
ALTER TABLE `Hold`
  ADD PRIMARY KEY (`HoldID`);

--
-- Indexes for table `Login`
--
ALTER TABLE `Login`
  ADD PRIMARY KEY (`LoginID`),
  ADD KEY `idx_login_userid` (`LoginID`);

--
-- Indexes for table `Major`
--
ALTER TABLE `Major`
  ADD PRIMARY KEY (`MajorID`),
  ADD UNIQUE KEY `MajorName` (`MajorName`),
  ADD KEY `idx_major_dept` (`DeptID`);

--
-- Indexes for table `MajorRequirement`
--
ALTER TABLE `MajorRequirement`
  ADD PRIMARY KEY (`MajorID`,`CourseID`);

--
-- Indexes for table `MessageCopies`
--
ALTER TABLE `MessageCopies`
  ADD PRIMARY KEY (`CopyID`),
  ADD KEY `MessageID` (`MessageID`),
  ADD KEY `OwnerEmail` (`OwnerEmail`);

--
-- Indexes for table `Messages`
--
ALTER TABLE `Messages`
  ADD PRIMARY KEY (`MessageID`);

--
-- Indexes for table `Minor`
--
ALTER TABLE `Minor`
  ADD PRIMARY KEY (`MinorID`),
  ADD UNIQUE KEY `MinorName` (`MinorName`),
  ADD KEY `idx_minor_dept` (`DeptID`);

--
-- Indexes for table `MinorRequirement`
--
ALTER TABLE `MinorRequirement`
  ADD PRIMARY KEY (`MinorID`,`CourseID`);

--
-- Indexes for table `PartTimeFaculty`
--
ALTER TABLE `PartTimeFaculty`
  ADD PRIMARY KEY (`FacultyID`),
  ADD KEY `idx_parttimefaculty_facultyid` (`FacultyID`);

--
-- Indexes for table `PartTimeGrad`
--
ALTER TABLE `PartTimeGrad`
  ADD PRIMARY KEY (`StudentID`),
  ADD KEY `idx_parttimegrad_student` (`StudentID`);

--
-- Indexes for table `PartTimeUG`
--
ALTER TABLE `PartTimeUG`
  ADD PRIMARY KEY (`StudentID`),
  ADD KEY `idx_parttimeug_student` (`StudentID`);

--
-- Indexes for table `Period`
--
ALTER TABLE `Period`
  ADD PRIMARY KEY (`PeriodID`);

--
-- Indexes for table `Program`
--
ALTER TABLE `Program`
  ADD PRIMARY KEY (`ProgramID`),
  ADD UNIQUE KEY `ProgramCode` (`ProgramCode`),
  ADD KEY `fk_program_department` (`DeptID`);

--
-- Indexes for table `ProgramRequirement`
--
ALTER TABLE `ProgramRequirement`
  ADD PRIMARY KEY (`RequirementID`),
  ADD KEY `fk_pr_program` (`ProgramID`),
  ADD KEY `fk_pr_course` (`CourseID`);

--
-- Indexes for table `Room`
--
ALTER TABLE `Room`
  ADD PRIMARY KEY (`RoomID`);

--
-- Indexes for table `Semester`
--
ALTER TABLE `Semester`
  ADD PRIMARY KEY (`SemesterID`);

--
-- Indexes for table `StatStaff`
--
ALTER TABLE `StatStaff`
  ADD PRIMARY KEY (`StatStaffID`);

--
-- Indexes for table `Student`
--
ALTER TABLE `Student`
  ADD PRIMARY KEY (`StudentID`);

--
-- Indexes for table `StudentEnrollment`
--
ALTER TABLE `StudentEnrollment`
  ADD PRIMARY KEY (`StudentID`,`SemesterID`,`CRN`),
  ADD KEY `idx_enroll_student` (`StudentID`),
  ADD KEY `idx_enroll_crn` (`CRN`),
  ADD KEY `idx_enroll_course` (`CourseID`),
  ADD KEY `idx_enroll_semester` (`SemesterID`);

--
-- Indexes for table `StudentHistory`
--
ALTER TABLE `StudentHistory`
  ADD PRIMARY KEY (`HistoryID`),
  ADD KEY `idx_stuhist_student` (`StudentID`),
  ADD KEY `idx_stuhist_course` (`CourseID`),
  ADD KEY `idx_stuhist_semester` (`SemesterID`);

--
-- Indexes for table `StudentHold`
--
ALTER TABLE `StudentHold`
  ADD PRIMARY KEY (`StudentID`,`HoldID`),
  ADD KEY `idx_stuhold_student` (`StudentID`),
  ADD KEY `idx_stuhold_hold` (`HoldID`);

--
-- Indexes for table `StudentMajor`
--
ALTER TABLE `StudentMajor`
  ADD PRIMARY KEY (`StudentID`,`MajorID`),
  ADD KEY `idx_studentmajor_student` (`StudentID`),
  ADD KEY `idx_studentmajor_major` (`MajorID`);

--
-- Indexes for table `StudentMinor`
--
ALTER TABLE `StudentMinor`
  ADD PRIMARY KEY (`StudentID`,`MinorID`),
  ADD KEY `idx_studentminor_student` (`StudentID`),
  ADD KEY `idx_studentminor_minor` (`MinorID`);

--
-- Indexes for table `TimeSlot`
--
ALTER TABLE `TimeSlot`
  ADD PRIMARY KEY (`TS_ID`);

--
-- Indexes for table `TimeSlotDay`
--
ALTER TABLE `TimeSlotDay`
  ADD PRIMARY KEY (`TS_ID`,`DayID`);

--
-- Indexes for table `TimeSlotPeriod`
--
ALTER TABLE `TimeSlotPeriod`
  ADD PRIMARY KEY (`TS_ID`,`PeriodID`);

--
-- Indexes for table `Undergraduate`
--
ALTER TABLE `Undergraduate`
  ADD PRIMARY KEY (`StudentID`);

--
-- Indexes for table `UpdateAdmin`
--
ALTER TABLE `UpdateAdmin`
  ADD PRIMARY KEY (`AdminID`),
  ADD KEY `idx_updateadmin_adminid` (`AdminID`);

--
-- Indexes for table `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `ViewAdmin`
--
ALTER TABLE `ViewAdmin`
  ADD PRIMARY KEY (`AdminID`),
  ADD KEY `idx_viewadmin_adminid` (`AdminID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `AdminAnnouncements`
--
ALTER TABLE `AdminAnnouncements`
  MODIFY `AnnouncementID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ArchiveMessages`
--
ALTER TABLE `ArchiveMessages`
  MODIFY `ArchiveID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `CourseAnnouncements`
--
ALTER TABLE `CourseAnnouncements`
  MODIFY `AnnouncementID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `CourseSection`
--
ALTER TABLE `CourseSection`
  MODIFY `CRN` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `DegreeAudit`
--
ALTER TABLE `DegreeAudit`
  MODIFY `DegreeAuditID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Department`
--
ALTER TABLE `Department`
  MODIFY `DeptID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `FacultyHistory`
--
ALTER TABLE `FacultyHistory`
  MODIFY `HistoryID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Major`
--
ALTER TABLE `Major`
  MODIFY `MajorID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MessageCopies`
--
ALTER TABLE `MessageCopies`
  MODIFY `CopyID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Messages`
--
ALTER TABLE `Messages`
  MODIFY `MessageID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Minor`
--
ALTER TABLE `Minor`
  MODIFY `MinorID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Program`
--
ALTER TABLE `Program`
  MODIFY `ProgramID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ProgramRequirement`
--
ALTER TABLE `ProgramRequirement`
  MODIFY `RequirementID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `StudentHistory`
--
ALTER TABLE `StudentHistory`
  MODIFY `HistoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Users`
--
ALTER TABLE `Users`
  MODIFY `UserID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `AdminAnnouncements`
--
ALTER TABLE `AdminAnnouncements`
  ADD CONSTRAINT `fk_course_announcements_admin` FOREIGN KEY (`AdminID`) REFERENCES `Admin` (`AdminID`);

--
-- Constraints for table `Advisor`
--
ALTER TABLE `Advisor`
  ADD CONSTRAINT `fk_Advisor_Faculty` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`),
  ADD CONSTRAINT `fk_Advisor_Student` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`);

--
-- Constraints for table `Chair`
--
ALTER TABLE `Chair`
  ADD CONSTRAINT `fk_Chair_Faculty` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`);

--
-- Constraints for table `Course`
--
ALTER TABLE `Course`
  ADD CONSTRAINT `fk_Course_Department` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`);

--
-- Constraints for table `CourseAnnouncements`
--
ALTER TABLE `CourseAnnouncements`
  ADD CONSTRAINT `courseannouncements_ibfk_1` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`),
  ADD CONSTRAINT `courseannouncements_ibfk_2` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`);

--
-- Constraints for table `CoursePrerequisite`
--
ALTER TABLE `CoursePrerequisite`
  ADD CONSTRAINT `fk_CoursePrereq_Course` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`),
  ADD CONSTRAINT `fk_CoursePrereq_PrereqCourse` FOREIGN KEY (`PrerequisiteCourseID`) REFERENCES `Course` (`CourseID`);

--
-- Constraints for table `CourseSection`
--
ALTER TABLE `CourseSection`
  ADD CONSTRAINT `fk_CourseSection_Course` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`),
  ADD CONSTRAINT `fk_CourseSection_Faculty` FOREIGN KEY (`FacultyID`) REFERENCES `Faculty` (`FacultyID`),
  ADD CONSTRAINT `fk_CourseSection_Room` FOREIGN KEY (`RoomID`) REFERENCES `Room` (`RoomID`),
  ADD CONSTRAINT `fk_CourseSection_Semester` FOREIGN KEY (`SemesterID`) REFERENCES `Semester` (`SemesterID`);

--
-- Constraints for table `CourseSectionAttendance`
--
ALTER TABLE `CourseSectionAttendance`
  ADD CONSTRAINT `fk_CSA_CourseSection` FOREIGN KEY (`CRN`) REFERENCES `CourseSection` (`CRN`),
  ADD CONSTRAINT `fk_CSA_Student` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`);

--
-- Constraints for table `DegreeAudit`
--
ALTER TABLE `DegreeAudit`
  ADD CONSTRAINT `fk_DegreeAudit_Major` FOREIGN KEY (`MajorID`) REFERENCES `Major` (`MajorID`),
  ADD CONSTRAINT `fk_DegreeAudit_Minor` FOREIGN KEY (`MinorID`) REFERENCES `Minor` (`MinorID`),
  ADD CONSTRAINT `fk_DegreeAudit_Student` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`),
  ADD CONSTRAINT `fk_deg_student` FOREIGN KEY (`StudentID`) REFERENCES `Student` (`StudentID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `Department`
--
ALTER TABLE `Department`
  ADD CONSTRAINT `fk_Department_Room` FOREIGN KEY (`RoomID`) REFERENCES `Room` (`RoomID`),
  ADD CONSTRAINT `fk_department_chair` FOREIGN KEY (`ChairID`) REFERENCES `Faculty` (`FacultyID`) ON UPDATE CASCADE;

--
-- Constraints for table `Faculty`
--
ALTER TABLE `Faculty`
  ADD CONSTRAINT `fk_Faculty_OfficeRoom` FOREIGN KEY (`OfficeID`) REFERENCES `Room` (`RoomID`);

--
-- Constraints for table `Graduate`
--
ALTER TABLE `Graduate`
  ADD CONSTRAINT `fk_grad_program` FOREIGN KEY (`ProgramID`) REFERENCES `Program` (`ProgramID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `MessageCopies`
--
ALTER TABLE `MessageCopies`
  ADD CONSTRAINT `messagecopies_ibfk_1` FOREIGN KEY (`MessageID`) REFERENCES `Messages` (`MessageID`),
  ADD CONSTRAINT `messagecopies_ibfk_2` FOREIGN KEY (`OwnerEmail`) REFERENCES `Users` (`Email`);

--
-- Constraints for table `Program`
--
ALTER TABLE `Program`
  ADD CONSTRAINT `fk_program_department` FOREIGN KEY (`DeptID`) REFERENCES `Department` (`DeptID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ProgramRequirement`
--
ALTER TABLE `ProgramRequirement`
  ADD CONSTRAINT `fk_pr_course` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_program` FOREIGN KEY (`ProgramID`) REFERENCES `Program` (`ProgramID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `programrequirement_ibfk_1` FOREIGN KEY (`ProgramID`) REFERENCES `Program` (`ProgramID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `programrequirement_ibfk_2` FOREIGN KEY (`CourseID`) REFERENCES `Course` (`CourseID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
