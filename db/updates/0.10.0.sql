-- Upgrade Actra Backend to 0.10.0
-- Adds profile page access right for existing installations.

INSERT INTO `auth_right` (`name`, `title`)
VALUES ('backend_access', 'Zugriff ins Backend');

INSERT INTO `auth_group_right` (`groupID`, `rightName`)
SELECT DISTINCT auth_group_right.groupID,
                'backend_access'
FROM auth_group_right
WHERE NOT EXISTS (SELECT 1
                  FROM auth_group_right existing
                  WHERE existing.groupID = auth_group_right.groupID
                    AND existing.rightName = 'backend_access');