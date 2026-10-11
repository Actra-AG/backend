-- Upgrade Actra Backend to 2.9.0
-- Database names follow the coding standard (naming.md, section 4): tables plural, link tables singular in
-- alphabetical order, timestamps *_at, booleans is_*, foreign keys <singular table>_id. RENAME keeps the data, the
-- indexes and the foreign keys (also those of project tables to auth_users.id).

RENAME TABLE
    `auth_api_key` TO `auth_api_keys`,
    `auth_group` TO `auth_groups`,
    `auth_ip_whitelist` TO `auth_ip_whitelists`,
    `auth_login` TO `auth_logins`,
    `auth_right` TO `auth_rights`,
    `auth_session` TO `auth_sessions`,
    `auth_token` TO `auth_tokens`,
    `auth_user` TO `auth_users`,
    `auth_user_group` TO `auth_group_user`,
    `auth_user_notification` TO `auth_user_notifications`,
    `auth_user_notification_recipient` TO `auth_user_notification_recipients`;

ALTER TABLE `auth_api_keys`
    RENAME COLUMN `registered` TO `created_at`;

ALTER TABLE `auth_logins`
    RENAME COLUMN `registered` TO `created_at`,
    RENAME COLUMN `session_id` TO `php_session_id`,
    RENAME INDEX `registered` TO `created_at`;

ALTER TABLE `auth_sessions`
    RENAME COLUMN `last_action` TO `last_action_at`,
    RENAME COLUMN `session_id` TO `php_session_id`;

ALTER TABLE `auth_tokens`
    RENAME COLUMN `registered` TO `created_at`,
    RENAME COLUMN `registered_client` TO `created_client`,
    RENAME COLUMN `claimed` TO `claimed_at`;

ALTER TABLE `auth_users`
    RENAME COLUMN `registered_by_id` TO `created_by_user_id`,
    RENAME COLUMN `registered` TO `created_at`,
    RENAME COLUMN `invited` TO `invited_at`,
    RENAME COLUMN `active` TO `is_active`,
    RENAME COLUMN `last_successful_login` TO `last_successful_login_at`,
    RENAME INDEX `registered_by_id` TO `created_by_user_id`;

ALTER TABLE `auth_user_notifications`
    RENAME COLUMN `auth_group_id` TO `group_id`,
    RENAME COLUMN `sent_by_id` TO `sent_by_user_id`,
    RENAME COLUMN `sent_date` TO `sent_at`,
    RENAME INDEX `auth_group_id` TO `group_id`,
    RENAME INDEX `sent_by_id` TO `sent_by_user_id`;

ALTER TABLE `auth_user_notification_recipients`
    RENAME COLUMN `notification_id` TO `user_notification_id`,
    RENAME COLUMN `auth_user_id` TO `user_id`,
    RENAME COLUMN `sent_date` TO `sent_at`,
    RENAME INDEX `notification_id` TO `user_notification_id`,
    RENAME INDEX `auth_user_id` TO `user_id`;
