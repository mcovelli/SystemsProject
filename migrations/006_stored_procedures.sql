-- =====================================================================
-- 006_stored_procedures.sql
-- Creates the two stored procedures the PHP calls but that were never
-- defined in the database:
--   GenerateUserEmail   CreateUsers.php:121
--   UpdateDegreeAudit   degree_audit.php:152
-- =====================================================================
--
--   mysql -h 127.0.0.1 -u root University < 006_stored_procedures.sql
--
-- Both were reverse-engineered from the data they are supposed to
-- produce, not invented: the 2,021 existing addresses fix the email
-- format and its collision scheme, and the 520 populated DegreeAudit
-- rows fix the shape of Courses_Taken and the credit and GPA figures.
--
-- Note the DEFINER caveat: these are created as the user running this
-- script (root@localhost on a stock XAMPP install) and mysqldump records
-- that name. Importing the dump as a different MySQL user will fail on
-- the DEFINER clause -- same as the trigger added in 005.
-- =====================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `GenerateUserEmail`;
DROP PROCEDURE IF EXISTS `UpdateDegreeAudit`;

DELIMITER $$

-- ---------------------------------------------------------------------
-- GenerateUserEmail(first, middle, last) -> OUT email
-- ---------------------------------------------------------------------
-- Produces first.m.last@nu.edu, lower case, middle initial included only
-- when there is a middle name. Derived from the live data: 1,900 of the
-- 2,017 nu.edu addresses match that pattern exactly, and the rest differ
-- only by the collision suffix below.
--
-- Collisions take an incrementing integer: the first Melissa L Miller is
-- melissa.l.miller@nu.edu, the second melissa.l.miller1@nu.edu. The data
-- holds 109 addresses ending in 1 and 5 ending in 2, and no unsuffixed
-- address is ever skipped, so the base form is always tried first.
--
-- Users.Email is UNIQUE and Login.Email references it, so checking Users
-- alone is sufficient.
CREATE PROCEDURE `GenerateUserEmail`(
    IN  p_first  VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_middle VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_last   VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_email  VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
)
BEGIN
    -- Declared with the columns' own collation. The database default is
    -- utf8mb4_0900_ai_ci while every table is utf8mb4_general_ci, so a
    -- variable left to the default cannot be compared against
    -- Users.Email at all -- it raises "Illegal mix of collations".
    DECLARE v_first   VARCHAR(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    DECLARE v_last    VARCHAR(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    DECLARE v_middle  VARCHAR(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    DECLARE v_initial VARCHAR(2)   CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_base    VARCHAR(90)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    DECLARE v_try     VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    DECLARE v_n       INT DEFAULT 0;

    -- Every name in the table is plain alphabetic today; stripping
    -- anything else keeps a hyphen or apostrophe from producing an
    -- address that cannot be typed.
    SET v_first  = LOWER(REGEXP_REPLACE(COALESCE(p_first , ''), '[^A-Za-z0-9]', ''));
    SET v_last   = LOWER(REGEXP_REPLACE(COALESCE(p_last  , ''), '[^A-Za-z0-9]', ''));
    SET v_middle = LOWER(REGEXP_REPLACE(COALESCE(p_middle, ''), '[^A-Za-z0-9]', ''));

    IF v_first = '' OR v_last = '' THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'GenerateUserEmail needs a first and last name.';
    END IF;

    IF v_middle <> '' THEN
        SET v_initial = CONCAT(LEFT(v_middle, 1), '.');
    END IF;

    SET v_base = CONCAT(v_first, '.', v_initial, v_last);
    SET v_try  = CONCAT(v_base, '@nu.edu');

    WHILE (SELECT COUNT(*) FROM `Users` WHERE `Email` = v_try) > 0 DO
        SET v_n   = v_n + 1;
        SET v_try = CONCAT(v_base, v_n, '@nu.edu');
    END WHILE;

    SET p_email = v_try;
END$$


-- ---------------------------------------------------------------------
-- UpdateDegreeAudit(studentID)
-- ---------------------------------------------------------------------
-- Recomputes one student's DegreeAudit row from their transcript and
-- their declared majors and minors, then upserts it. DegreeAudit has
-- UNIQUE(StudentID), so one row per student is maintained in place and
-- GenerateDate refreshes itself via ON UPDATE CURRENT_TIMESTAMP.
--
-- Two rules the existing data could not settle, because every course is
-- worth 3 credits and no student holds an F:
--   * GPA is credit-weighted over every graded attempt, F included
--   * earned credits count passing grades only, so an F earns none
-- Both are the conventional reading; they agree with all 520 populated
-- rows and only diverge once grades or credit values vary.
CREATE PROCEDURE `UpdateDegreeAudit`(
    IN p_studentID INT UNSIGNED
)
BEGIN
    DECLARE v_credits_done INT DEFAULT 0;
    DECLARE v_credits_req  INT DEFAULT 0;
    DECLARE v_gpa          DECIMAL(3,2) DEFAULT 0.00;
    DECLARE v_taken        TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;
    DECLARE v_needed       TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;
    DECLARE v_major        INT DEFAULT NULL;
    DECLARE v_minor        INT DEFAULT NULL;

    IF NOT EXISTS (SELECT 1 FROM `Student` WHERE `StudentID` = p_studentID) THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'UpdateDegreeAudit called for an unknown StudentID.';
    END IF;

    -- The default 1024-byte cap would silently truncate a long transcript.
    SET SESSION group_concat_max_len = 65535;

    -- Credits earned: passing grades only.
    SELECT COALESCE(SUM(c.`Credits`), 0)
      INTO v_credits_done
      FROM `StudentHistory` sh
      JOIN `Course` c ON c.`CourseID` = sh.`CourseID`
     WHERE sh.`StudentID` = p_studentID
       AND sh.`Grade` IS NOT NULL
       AND sh.`Grade` <> 'F';

    -- GPA: credit-weighted across every graded attempt.
    SELECT COALESCE(ROUND(SUM(g.`GradeValue` * c.`Credits`) / NULLIF(SUM(c.`Credits`), 0), 2), 0.00)
      INTO v_gpa
      FROM `StudentHistory` sh
      JOIN `Course` c        ON c.`CourseID`   = sh.`CourseID`
      JOIN `GradingScale` g  ON g.`GradeLetter` = sh.`Grade`
     WHERE sh.`StudentID` = p_studentID;

    -- Courses_Taken: comma-separated course codes, as the column already holds.
    SELECT GROUP_CONCAT(DISTINCT sh.`CourseID` ORDER BY sh.`CourseID` SEPARATOR ', ')
      INTO v_taken
      FROM `StudentHistory` sh
     WHERE sh.`StudentID` = p_studentID;

    -- Credits required. degree_audit.php shows the graduate program's
    -- CreditsRequired for a graduate student and otherwise a single
    -- major's CreditsNeeded, so this matches that rather than summing
    -- across a double major -- the stored figure and the displayed one
    -- should not disagree.
    -- Student.StudentType is blank on 1,418 of 1,602 rows, while the
    -- Graduate subtype table holds 311 -- so the enum alone misclassifies
    -- most graduate students. Membership of Graduate is the reliable
    -- signal and is taken as authoritative here.
    -- degree_audit.php line 44 still branches on StudentType alone; until
    -- that matches, the two can disagree for a graduate student whose
    -- StudentType was never filled in.
    SELECT CASE
             WHEN s.`StudentType` = 'Graduate'
               OR EXISTS (SELECT 1 FROM `Graduate` g2
                           WHERE g2.`StudentID` = p_studentID
                             AND g2.`ProgramID` IS NOT NULL) THEN
               (SELECT p.`CreditsRequired`
                  FROM `Graduate` g
                  JOIN `Program` p ON p.`ProgramID` = g.`ProgramID`
                 WHERE g.`StudentID` = p_studentID)
             ELSE
               (SELECT m.`CreditsNeeded`
                  FROM `StudentMajor` sm
                  JOIN `Major` m ON m.`MajorID` = sm.`MajorID`
                 WHERE sm.`StudentID` = p_studentID
                 ORDER BY sm.`DateOfDeclaration`, sm.`MajorID`
                 LIMIT 1)
           END
      INTO v_credits_req
      FROM `Student` s
     WHERE s.`StudentID` = p_studentID;

    SET v_credits_req = COALESCE(v_credits_req, 0);

    -- Courses_Needed: newline-separated, with "--- ... ---" section
    -- headers. degree_audit.php splits on newlines and drops any line
    -- starting with ---, so the headers are for reading the column
    -- directly and never reach the page.
    SELECT GROUP_CONCAT(line ORDER BY grp, kind, line SEPARATOR '\n')
      INTO v_needed
      FROM (
            SELECT CONCAT('1', m.`MajorName`) AS grp, 0 AS kind,
                   CONCAT('--- Major: ', m.`MajorName`, ' ---') AS line
              FROM `StudentMajor` sm
              JOIN `Major` m ON m.`MajorID` = sm.`MajorID`
             WHERE sm.`StudentID` = p_studentID

            UNION ALL

            SELECT CONCAT('1', m.`MajorName`), 1,
                   CONCAT(mr.`CourseID`, ' - ', c.`CourseName`)
              FROM `StudentMajor` sm
              JOIN `Major` m             ON m.`MajorID`  = sm.`MajorID`
              JOIN `MajorRequirement` mr ON mr.`MajorID` = sm.`MajorID`
              JOIN `Course` c            ON c.`CourseID` = mr.`CourseID`
             WHERE sm.`StudentID` = p_studentID
               AND NOT EXISTS (SELECT 1 FROM `StudentHistory` sh
                                WHERE sh.`StudentID` = p_studentID
                                  AND sh.`CourseID`  = mr.`CourseID`
                                  AND sh.`Grade` <> 'F')

            UNION ALL

            SELECT CONCAT('2', mn.`MinorName`), 0,
                   CONCAT('--- Minor: ', mn.`MinorName`, ' ---')
              FROM `StudentMinor` smn
              JOIN `Minor` mn ON mn.`MinorID` = smn.`MinorID`
             WHERE smn.`StudentID` = p_studentID

            UNION ALL

            SELECT CONCAT('2', mn.`MinorName`), 1,
                   CONCAT(mr.`CourseID`, ' - ', c.`CourseName`)
              FROM `StudentMinor` smn
              JOIN `Minor` mn            ON mn.`MinorID`  = smn.`MinorID`
              JOIN `MinorRequirement` mr ON mr.`MinorID`  = smn.`MinorID`
              JOIN `Course` c            ON c.`CourseID`  = mr.`CourseID`
             WHERE smn.`StudentID` = p_studentID
               AND NOT EXISTS (SELECT 1 FROM `StudentHistory` sh
                                WHERE sh.`StudentID` = p_studentID
                                  AND sh.`CourseID`  = mr.`CourseID`
                                  AND sh.`Grade` <> 'F')
           ) AS needed_lines;

    -- The declared major and minor recorded on the audit row itself.
    SELECT `MajorID` INTO v_major FROM `StudentMajor`
     WHERE `StudentID` = p_studentID ORDER BY `DateOfDeclaration`, `MajorID` LIMIT 1;
    SELECT `MinorID` INTO v_minor FROM `StudentMinor`
     WHERE `StudentID` = p_studentID ORDER BY `DateOfDeclaration`, `MinorID` LIMIT 1;

    INSERT INTO `DegreeAudit`
        (`StudentID`, `MajorID`, `MinorID`, `Status`,
         `Credits_Completed`, `Credits_Remaining`, `CumulativeGPA`,
         `Courses_Taken`, `Courses_Needed`)
    VALUES
        (p_studentID, v_major, v_minor, 'ACTIVE',
         v_credits_done, GREATEST(v_credits_req - v_credits_done, 0), v_gpa,
         v_taken, v_needed)
    ON DUPLICATE KEY UPDATE
        `MajorID`           = v_major,
        `MinorID`           = v_minor,
        `Credits_Completed` = v_credits_done,
        `Credits_Remaining` = GREATEST(v_credits_req - v_credits_done, 0),
        `CumulativeGPA`     = v_gpa,
        `Courses_Taken`     = v_taken,
        `Courses_Needed`    = v_needed;
END$$

DELIMITER ;

SELECT ROUTINE_NAME, ROUTINE_TYPE
  FROM information_schema.ROUTINES
 WHERE ROUTINE_SCHEMA = DATABASE()
 ORDER BY ROUTINE_NAME;
