<?php
/**
 * Backup Configuration File
 */

return [
    // Database settings
    'database' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',  // Add your MySQL password if set
        'name' => 'sun_office'
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