<?php
/**
 * Backup Configuration File
 */

require_once __DIR__ . '/../api/config/database.php';

$databaseConfig = getDatabaseConfig();

return [
    // Database settings
    'database' => [
        'host' => $databaseConfig['host'],
        'username' => $databaseConfig['username'],
        'password' => $databaseConfig['password'],
        'name' => $databaseConfig['db_name']
    ],
    
    // Backup settings
    'backup' => [
        'drive' => 'E:',  // Change this to your backup drive
        'path' => 'MySQL_Backups',
        'retention' => [
            'daily' => 7,    // Keep 7 daily backups
            'weekly' => 4,   // Keep 4 weekly backups
            'monthly' => 12  // Keep 12 monthly backups
        ]
    ],
    
    // MySQL paths (auto-detected if commented)
    'mysql' => [
        // 'mysqldump' => 'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        // 'mysql' => 'C:\\xampp\\mysql\\bin\\mysql.exe'
    ]
];
?>
