-- =====================================================================
-- 009_recompute_degree_audits.sql
-- Rebuilds every stored DegreeAudit row with the 008 procedure.
-- =====================================================================
--
--   mysql -h 127.0.0.1 -u root University < 009_recompute_degree_audits.sql
--
-- ---------------------------------------------------------------------
-- WHY
-- ---------------------------------------------------------------------
-- 008 repointed UpdateDegreeAudit at StudentEnrollment but did not call
-- it, so every DegreeAudit row still held the figure computed from
-- StudentHistory. Immediately after 008, 1,081 of 1,602 rows read zero
-- credits while 1,358 students had COMPLETED enrolments.
--
-- degree_audit.php calls the procedure when a student's audit is opened,
-- so the rows would have corrected themselves one at a time on view.
-- Everything that reads DegreeAudit without calling the procedure would
-- have shown the stale figure until then -- the Cumulative GPA tile on
-- student_dashboard.php, transcript.php, and the statstaff dashboard's
-- counts.
--
-- Safe to re-run. UpdateDegreeAudit upserts on the UNIQUE(StudentID),
-- so this converges rather than accumulating, and it is the same call
-- degree_audit.php already makes.
-- =====================================================================

SET NAMES utf8mb4;

-- Refuse to run against the pre-008 procedure, which would rewrite every
-- row from a table that no longer exists.
SELECT COUNT(*) INTO @stale
  FROM information_schema.ROUTINES
 WHERE ROUTINE_SCHEMA = DATABASE()
   AND ROUTINE_NAME   = 'UpdateDegreeAudit'
   AND ROUTINE_DEFINITION NOT LIKE '%StudentEnrollment%';

DROP PROCEDURE IF EXISTS `_mig009_guard`;
DELIMITER $$
CREATE PROCEDURE `_mig009_guard`(IN n INT)
BEGIN
    IF n <> 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Aborting: UpdateDegreeAudit does not read StudentEnrollment. Apply 008 first.';
    END IF;
END$$
DELIMITER ;
CALL `_mig009_guard`(@stale);
DROP PROCEDURE `_mig009_guard`;

-- ---------------------------------------------------------------------
-- Recompute
-- ---------------------------------------------------------------------
-- One CALL per student. 006 measured the whole set at about a second.

DROP PROCEDURE IF EXISTS `_mig009_recompute_all`;
DELIMITER $$
CREATE PROCEDURE `_mig009_recompute_all`()
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_sid  INT UNSIGNED;
    DECLARE cur CURSOR FOR SELECT `StudentID` FROM `Student`;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    student_loop: LOOP
        FETCH cur INTO v_sid;
        IF v_done THEN LEAVE student_loop; END IF;
        CALL `UpdateDegreeAudit`(v_sid);
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL `_mig009_recompute_all`();
DROP PROCEDURE `_mig009_recompute_all`;

-- ---------------------------------------------------------------------
-- Verify
-- ---------------------------------------------------------------------

SELECT 'audits with credits but no completed enrolment' AS check_name,
       COUNT(*) AS should_be_zero
  FROM `DegreeAudit` da
 WHERE da.`Credits_Completed` > 0
   AND NOT EXISTS (SELECT 1 FROM `StudentEnrollment` se
                    WHERE se.`StudentID` = da.`StudentID`
                      AND se.`Status`    = 'COMPLETED');

SELECT 'audits at zero despite passing grades' AS check_name,
       COUNT(*) AS should_be_zero
  FROM `DegreeAudit` da
 WHERE da.`Credits_Completed` = 0
   AND EXISTS (SELECT 1 FROM `StudentEnrollment` se
                WHERE se.`StudentID` = da.`StudentID`
                  AND se.`Status`    = 'COMPLETED'
                  AND se.`Grade` IS NOT NULL
                  AND se.`Grade` <> 'F');

SELECT 'GPA outside 0.00-4.00' AS check_name, COUNT(*) AS should_be_zero
  FROM `DegreeAudit` WHERE `CumulativeGPA` < 0 OR `CumulativeGPA` > 4.00;

SELECT 'negative credits remaining' AS check_name, COUNT(*) AS should_be_zero
  FROM `DegreeAudit` WHERE `Credits_Remaining` < 0;

SELECT COUNT(*)                          AS audits,
       SUM(`Credits_Completed` > 0)      AS with_credits,
       ROUND(AVG(`CumulativeGPA`), 3)    AS avg_gpa
  FROM `DegreeAudit`;
