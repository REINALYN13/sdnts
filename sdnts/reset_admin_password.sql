-- =====================================================
-- SDNTS - Reset Admin Password
-- Run this in phpMyAdmin if you cannot login
-- Password will be reset to: Admin@1234
-- =====================================================
USE sdnts_db;

UPDATE users 
SET password = '$2y$10$LEMcZ7tkm1jydhB/HT059OPY3rte.geCGC8nn2W.3L65u/VS/i/sO'
WHERE email = 'admin@sdnts.edu.ph';

-- Verify the update
SELECT userID, employeeNo, firstName, lastName, email, 
       IF(password LIKE '$2y$%', 'Hash OK ✓', 'Hash ERROR ✗') as hash_status
FROM users 
WHERE email = 'admin@sdnts.edu.ph';
