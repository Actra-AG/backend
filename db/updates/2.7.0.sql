-- Upgrade Actra Backend to 2.7.0
-- One-time tokens are stored as SHA-256 hash only (auth_token.token -> token_hash). Existing tokens are hashed, so the
-- token log keeps its rows; links of password reset mails sent before the update no longer work (new links have 22
-- characters), login codes requested before the update must be requested again.
-- The public ID of API keys is compared case-sensitively.

ALTER TABLE `auth_token`
    ADD COLUMN `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' AFTER `claimed_client`;

UPDATE `auth_token`
SET `token_hash` = SHA2(`token`, 256);

ALTER TABLE `auth_token`
    ALTER COLUMN `token_hash` DROP DEFAULT,
    DROP INDEX `token`,
    DROP COLUMN `token`,
    ADD KEY `token_hash` (`token_hash`);

ALTER TABLE `auth_api_key`
    MODIFY `public_id` char(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

-- The seed user of data.sql of earlier versions: give it your own address or delete it (see UPGRADE.md)
-- SELECT id, email, active FROM auth_user WHERE email = 'admin@actra.ch';
