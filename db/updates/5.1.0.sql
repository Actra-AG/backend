-- Upgrade Actra Backend to 5.1.0
-- Log of the sent emails (page "Sent emails") and its right view_sent_emails, given to every group with the right
-- manage_users.

CREATE TABLE `sent_emails`
(
    `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sent_at`    datetime         NOT NULL,
    `from_email` varchar(254)     NOT NULL,
    `to_emails`  text             NOT NULL,
    `cc_emails`  text             NOT NULL,
    `bcc_emails` text             NOT NULL,
    `subject`    text             NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sent_at` (`sent_at`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `auth_rights` (`name`, `title`)
VALUES ('view_sent_emails', 'Gesendete E-Mails ansehen');

INSERT INTO `auth_group_right` (`group_id`, `right_name`)
SELECT `group_id`, 'view_sent_emails'
FROM `auth_group_right`
WHERE `right_name` = 'manage_users';
