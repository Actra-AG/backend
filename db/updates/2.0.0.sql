-- Upgrade Actra Backend to 2.0.0
-- Tables, columns and indexes in snake_case (coding standard, naming.md): e.g. auth_ipWhitelist -> auth_ip_whitelist,
-- ID -> id, userID -> user_id, firstName -> first_name. Data and foreign keys are kept.

SET FOREIGN_KEY_CHECKS = 0;

-- Also renames the foreign key auth_ipWhitelist_ibfk_1 to auth_ip_whitelist_ibfk_1 (MariaDB, MySQL 8)
RENAME TABLE `auth_ipWhitelist` TO `auth_ip_whitelist`;

ALTER TABLE `auth_api_key`
    RENAME COLUMN `userID` TO `user_id`,
    RENAME COLUMN `publicID` TO `public_id`,
    RENAME COLUMN `apiKey` TO `api_key`,
    RENAME INDEX `publicID` TO `public_id`,
    RENAME INDEX `apiKey` TO `api_key`;

ALTER TABLE `auth_group`
    RENAME COLUMN `ID` TO `id`;

ALTER TABLE `auth_group_right`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `groupID` TO `group_id`,
    RENAME COLUMN `rightName` TO `right_name`,
    RENAME INDEX `groupID` TO `group_id`,
    RENAME INDEX `rightName` TO `right_name`;

ALTER TABLE `auth_ip_whitelist`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `userID` TO `user_id`,
    RENAME COLUMN `ipAddress` TO `ip_address`,
    RENAME INDEX `userID` TO `user_id`;

ALTER TABLE `auth_login`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `userID` TO `user_id`,
    RENAME COLUMN `sessionId` TO `session_id`,
    RENAME COLUMN `ipAddress` TO `ip_address`,
    RENAME INDEX `userID` TO `user_id`;

ALTER TABLE `auth_session`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `parentID` TO `parent_id`,
    RENAME COLUMN `userID` TO `user_id`,
    RENAME COLUMN `lastAction` TO `last_action`,
    RENAME COLUMN `sessionId` TO `session_id`,
    RENAME COLUMN `ipAddress` TO `ip_address`,
    RENAME INDEX `parentID` TO `parent_id`,
    RENAME INDEX `userID` TO `user_id`;

ALTER TABLE `auth_token`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `userID` TO `user_id`,
    RENAME COLUMN `registeredClient` TO `registered_client`,
    RENAME COLUMN `claimedClient` TO `claimed_client`,
    RENAME INDEX `userID` TO `user_id`;

ALTER TABLE `auth_user`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `registeredByID` TO `registered_by_id`,
    RENAME COLUMN `firstName` TO `first_name`,
    RENAME COLUMN `lastName` TO `last_name`,
    RENAME COLUMN `lastSuccessfulLogin` TO `last_successful_login`,
    RENAME COLUMN `passwordSalt` TO `password_salt`,
    RENAME COLUMN `passwordHash` TO `password_hash`,
    RENAME COLUMN `wrongLoginAttempts` TO `wrong_login_attempts`,
    RENAME INDEX `registeredByID` TO `registered_by_id`;

ALTER TABLE `auth_user_group`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `userID` TO `user_id`,
    RENAME COLUMN `groupID` TO `group_id`,
    RENAME INDEX `userID` TO `user_id`,
    RENAME INDEX `groupID` TO `group_id`;

ALTER TABLE `auth_user_notification`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `authGroupID` TO `auth_group_id`,
    RENAME COLUMN `sentByID` TO `sent_by_id`,
    RENAME COLUMN `sentDate` TO `sent_date`,
    RENAME INDEX `authGroupID` TO `auth_group_id`,
    RENAME INDEX `sentByID` TO `sent_by_id`;

ALTER TABLE `auth_user_notification_recipient`
    RENAME COLUMN `ID` TO `id`,
    RENAME COLUMN `notificationID` TO `notification_id`,
    RENAME COLUMN `authUserID` TO `auth_user_id`,
    RENAME COLUMN `sentDate` TO `sent_date`,
    RENAME INDEX `notificationID` TO `notification_id`,
    RENAME INDEX `authUserID` TO `auth_user_id`;


SET FOREIGN_KEY_CHECKS = 1;
