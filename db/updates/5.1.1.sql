-- Upgrade Actra Backend to 5.1.1
-- The log of sent emails (5.1.0) stored the login code in the subject of the login code mail. Masks the code in the
-- rows with the default subjects of the backend (English and German). Subjects of own texts (loginTokenSubject in
-- ActraBackendSettings::$messages) are not covered: empty the log instead (TRUNCATE sent_emails, see UPGRADE.md).

UPDATE `sent_emails`
SET `subject` = CONCAT('••••••', SUBSTRING(`subject`, 7))
WHERE `subject` LIKE '______ is your confirmation code'
   OR `subject` LIKE '______ ist Ihr Bestätigungscode';
