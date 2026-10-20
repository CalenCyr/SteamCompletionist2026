-- Adds the cached store capsule path used for game images.
-- NULL = not looked up yet (resolved on the next library refresh), '' = Steam has no store art for the app.
-- Run once on existing installs; database_tables.sql already includes it for new installs.
ALTER TABLE `steamGameDB` ADD COLUMN `capsule` varchar(255) default NULL AFTER `icon`;

-- Steam no longer returns logo hashes, so allow empty values to be stored safely.
ALTER TABLE `steamGameDB` MODIFY `logo` char(40) NOT NULL default '', MODIFY `icon` char(40) NOT NULL default '';
