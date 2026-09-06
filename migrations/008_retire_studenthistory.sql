-- =====================================================================
-- 008_retire_studenthistory.sql
-- Points UpdateDegreeAudit at StudentEnrollment and drops StudentHistory.
-- =====================================================================
--
--   mysql -h 127.0.0.1 -u root University < 008_retire_studenthistory.sql
--
-- ---------------------------------------------------------------------
-- WHY
-- ---------------------------------------------------------------------
-- StudentHistory holds nothing StudentEnrollment does not already hold.
-- Measured against the live database:
--
--     StudentHistory                                  6,696 rows,   520 students
--     StudentEnrollment                              31,056 rows, 1,385 students
--     StudentHistory rows with no StudentEnrollment       0
--     StudentEnrollment rows with no StudentHistory  24,360
--
-- Every one of the 6,696 maps to a StudentEnrollment row whose Status is
-- COMPLETED, and all 6,696 grades agree exactly. StudentHistory is a
-- strict subset: the same fact, recorded twice, with no Status column to
-- say which enrolments it covers.
--
-- The consequence is that the degree audit has been reading the smaller
-- copy. StudentEnrollment carries 15,627 COMPLETED rows against
-- StudentHistory's 6,696, so 8,931 graded courses -- and 865 students --
-- were invisible to it. Credits_Completed, CumulativeGPA, Courses_Taken
-- and Courses_Needed were all computed from the subset.
--
-- Keeping the two in step was also nobody's job. grade.php and
-- grade_update.php UPDATEd StudentEnrollment and then INSERTed a second
-- row into StudentHistory, so a corrected grade appended rather than
-- replaced. drop_course.php DELETEd from StudentHistory on StudentID and
-- CRN with no semester, so dropping a section this term erased the
-- completed record of the same CRN taken in an earlier one.
--
-- ---------------------------------------------------------------------
-- WHAT CHANGES IN THE FIGURES
-- ---------------------------------------------------------------------
-- This is not a no-op rewrite. Audits recompute over the full set, so
-- Credits_Completed and CumulativeGPA move for any student who had
-- COMPLETED enrolments outside StudentHistory, and the 865 students with
-- no StudentHistory rows at all get real figures for the first time.
-- That is the point of the change, but it is a data change, not just a
-- schema one -- take the dump in step 2 of migrations/README.md first.
--
-- Status = 'COMPLETED' is the filter throughout. Today the enum holds
-- only COMPLETED and PLANNED and every COMPLETED row is graded, so
-- Grade IS NOT NULL alone would give the same answer; naming the status
-- keeps that true once DROPPED, WAITLIST, ENROLLED or IN-PROGRESS rows
-- appear, which the enum already allows and drop_course.php already
-- writes.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Refuse to run if the two tables have diverged
-- ---------------------------------------------------------------------
-- The drop below is irreversible without the dump. If any StudentHistory
-- row is not mirrored by a COMPLETED StudentEnrollment row carrying the
-- same grade, this database is not the one the change was verified
-- against and the script stops here.

SELECT COUNT(*) INTO @unmirrored
  FROM `StudentHistory` sh
  LEFT JOIN `StudentEnrollment` se
         ON se.`StudentID` = sh.`StudentID`
        AND se.`CRN`       = sh.`CRN`
        AND se.`Status`    = 'COMPLETED'
        AND se.`Grade` <=> sh.`Grade`
 WHERE se.`StudentID` IS NULL;

SET @msg = CONCAT('Aborting: ', @unmirrored,
                  ' StudentHistory rows have no matching COMPLETED '
                  'StudentEnrollment row with the same grade.');

SELECT @unmirrored AS unmirrored_rows;

-- Raises 45000 and stops the script when the count is non-zero.
DROP PROCEDURE IF EXISTS `_mig008_guard`;
DELIMITER $$
CREATE PROCEDURE `_mig008_guard`(IN n INT, IN msg TEXT)
BEGIN
    IF n <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = msg;
    END IF;
END$$
DELIMITER ;
CALL `_mig008_guard`(@unmirrored, @msg);
DROP PROCEDURE `_mig008_guard`;

-- ---------------------------------------------------------------------
-- 2. Recreate UpdateDegreeAudit against StudentEnrollment
-- ---------------------------------------------------------------------
-- Identical to the 006 version except for the source table and the
-- Status filter. The two rules 006 settled still hold: GPA is
-- credit-weighted over every graded attempt including F, and earned
-- credits count passing grades only.
--
-- Same DEFINER caveat as 006 -- created as the user running this script,
-- and mysqldump records that name.

DROP PROCEDURE IF EXISTS `UpdateDegreeAudit`;

DELIMITER $$

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

    -- Credits earned: completed enrolments with a passing grade.
    SELECT COALESCE(SUM(c.`Credits`), 0)
      INTO v_credits_done
      FROM `StudentEnrollment` se
      JOIN `Course` c ON c.`CourseID` = se.`CourseID`
     WHERE se.`StudentID` = p_studentID
       AND se.`Status`    = 'COMPLETED'
       AND se.`Grade` IS NOT NULL
       AND se.`Grade` <> 'F';

    -- GPA: credit-weighted across every graded attempt.
    SELECT COALESCE(ROUND(SUM(g.`GradeValue` * c.`Credits`) / NULLIF(SUM(c.`Credits`), 0), 2), 0.00)
      INTO v_gpa
      FROM `StudentEnrollment` se
      JOIN `Course` c        ON c.`CourseID`    = se.`CourseID`
      JOIN `GradingScale` g  ON g.`GradeLetter` = se.`Grade`
     WHERE se.`StudentID` = p_studentID
       AND se.`Status`    = 'COMPLETED';

    -- Courses_Taken: comma-separated course codes, as the column already holds.
    SELECT GROUP_CONCAT(DISTINCT se.`CourseID` ORDER BY se.`CourseID` SEPARATOR ', ')
      INTO v_taken
      FROM `StudentEnrollment` se
     WHERE se.`StudentID` = p_studentID
       AND se.`Status`    = 'COMPLETED';

    -- Credits required. degree_audit.php shows the graduate program's
    -- CreditsRequired for a graduate student and otherwise a single
    -- major's CreditsNeeded, so this matches that rather than summing
    -- across a double major.
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
    -- starting with ---, so the headers never reach the page.
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
               AND NOT EXISTS (SELECT 1 FROM `StudentEnrollment` se
                                WHERE se.`StudentID` = p_studentID
                                  AND se.`CourseID`  = mr.`CourseID`
                                  AND se.`Status`    = 'COMPLETED'
                                  AND se.`Grade` <> 'F')

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
               AND NOT EXISTS (SELECT 1 FROM `StudentEnrollment` se
                                WHERE se.`StudentID` = p_studentID
                                  AND se.`CourseID`  = mr.`CourseID`
                                  AND se.`Status`    = 'COMPLETED'
                                  AND se.`Grade` <> 'F')
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

-- ---------------------------------------------------------------------
-- 3. Drop StudentHistory
-- ---------------------------------------------------------------------
-- 002 gave the table four foreign keys. DROP TABLE removes them with it;
-- nothing references StudentHistory, so no other constraint needs
-- touching first.

DROP TABLE IF EXISTS `StudentHistory`;

-- ---------------------------------------------------------------------
-- 4. Verify
-- ---------------------------------------------------------------------

SELECT 'StudentHistory still present' AS check_name,
       COUNT(*) AS should_be_zero
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StudentHistory';

SELECT 'routines that still mention StudentHistory' AS check_name,
       COUNT(*) AS should_be_zero
  FROM information_schema.ROUTINES
 WHERE ROUTINE_SCHEMA = DATABASE()
   AND ROUTINE_DEFINITION LIKE '%StudentHistory%';

SELECT ROUTINE_NAME, ROUTINE_TYPE
  FROM information_schema.ROUTINES
 WHERE ROUTINE_SCHEMA = DATABASE()
 ORDER BY ROUTINE_NAME;
