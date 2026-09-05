-- =====================================================================
-- 005_soft_delete.sql
-- Retire records instead of deleting them.
--   * Major and Minor gain a Status column, matching Program
--   * Users, which already has one, becomes undeletable outright
-- =====================================================================
--
--   mysql -h 127.0.0.1 -u root University < 005_soft_delete.sql
--
-- This supersedes the CASCADE approach: with nothing ever hard-deleted,
-- the RESTRICT rules from 004 never fire, and no student loses a major
-- declaration because a major was retired. Existing declarations,
-- requirements, transcripts and degree audits all stay intact.
--
-- The application half of this change lives in:
--   DeleteUsers.php          UPDATE Status instead of DELETE
--   DeleteMajorsMinors.php   UPDATE Status instead of DELETE
--   get_majors.php, get_minors.php, DeclareMajor.php, UpdateUsers.php
--                            offer only ACTIVE options in the pickers
-- =====================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------
-- 1. Status on Major and Minor
-- ---------------------------------------------------------------------
-- Same shape as Program.Status, which already works this way. Existing
-- rows default to ACTIVE, so nothing changes until something is retired.
ALTER TABLE `Major`
  ADD COLUMN `Status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
  AFTER `CreditsNeeded`;

ALTER TABLE `Minor`
  ADD COLUMN `Status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
  AFTER `CreditsNeeded`;


-- ---------------------------------------------------------------------
-- 2. Make Users undeletable
-- ---------------------------------------------------------------------
-- Users.Status enum('ACTIVE','INACTIVE') already exists and login.php
-- already refuses anyone who is not ACTIVE, so deactivating locks the
-- account out immediately. What was missing was anything stopping a
-- DELETE, which is how the orphans 002 cleaned up were created.
--
-- A BEFORE DELETE trigger blocks every path -- the app, the CLI, phpMyAdmin
-- -- rather than trusting each caller to behave. Users is the root of the
-- identity chain and nothing cascades into it, so the usual InnoDB caveat
-- that cascades do not fire triggers does not create a hole here.
--
-- To run a deliberate purge, drop the trigger, delete, then recreate it.
DROP TRIGGER IF EXISTS `trg_Users_block_delete`;

DELIMITER $$
CREATE TRIGGER `trg_Users_block_delete`
BEFORE DELETE ON `Users`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Users cannot be deleted. Set Status = ''INACTIVE'' to deactivate the account.';
END$$
DELIMITER ;

-- The ON DELETE CASCADE rules running down from Users are now
-- unreachable while the trigger is in place. They are left as they are
-- so a deliberate purge still cleans up correctly.


-- ---------------------------------------------------------------------
-- Verification
-- ---------------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'Status'
   AND TABLE_NAME IN ('Users','Program','Major','Minor')
 ORDER BY TABLE_NAME;

SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
  FROM information_schema.TRIGGERS
 WHERE TRIGGER_SCHEMA = DATABASE();


-- =====================================================================
-- Optional: the same guard on Major and Minor
-- ---------------------------------------------------------------------
-- 004 already blocks deleting a major or minor that has students, so the
-- only thing still hard-deletable is an unused one. If you want retiring
-- to be the only route there too, uncomment:
--
-- DELIMITER $$
-- CREATE TRIGGER `trg_Major_block_delete` BEFORE DELETE ON `Major`
-- FOR EACH ROW BEGIN
--   SIGNAL SQLSTATE '45000'
--     SET MESSAGE_TEXT = 'Majors cannot be deleted. Set Status = ''INACTIVE'' instead.';
-- END$$
-- CREATE TRIGGER `trg_Minor_block_delete` BEFORE DELETE ON `Minor`
-- FOR EACH ROW BEGIN
--   SIGNAL SQLSTATE '45000'
--     SET MESSAGE_TEXT = 'Minors cannot be deleted. Set Status = ''INACTIVE'' instead.';
-- END$$
-- DELIMITER ;
-- =====================================================================
