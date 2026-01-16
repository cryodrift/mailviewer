SELECT 'DROP VIEW IF EXISTS "' || name || '";'
FROM sqlite_master
WHERE type = 'view';
--END;
