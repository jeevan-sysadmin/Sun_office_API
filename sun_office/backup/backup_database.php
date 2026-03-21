<?php
/**
 * MySQL Database Backup Script
 * Saves backups to specified drive on system startup
 */

class DatabaseBackup {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'sun_office';
    private $backupDrive = 'E:'; // Change this to your backup drive (E:, F:, etc.)
    private $backupPath;
    private $maxBackups = 30; // Keep last 30 backups
    private $logFile;

    public function __construct() {
        // Create backup directory path
        $this->backupPath = $this->backupDrive . '\\MySQL_Backups\\' . $this->database;
        $this->logFile = $this->backupDrive . '\\MySQL_Backups\\backup_log.txt';
        
        // Create directories if they don't exist
        $this->createDirectories();
    }

    /**
     * Create necessary directories
     */
    private function createDirectories() {
        $directories = [
            $this->backupDrive . '\\MySQL_Backups',
            $this->backupPath,
            $this->backupPath . '\\daily',
            $this->backupPath . '\\weekly',
            $this->backupPath . '\\monthly'
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    /**
     * Write to log file
     */
    private function writeLog($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Create database backup using mysqldump
     */
    public function createBackup() {
        try {
            // Generate backup filename
            $date = date('Y-m-d_H-i-s');
            $dayOfWeek = date('w'); // 0 (Sunday) to 6 (Saturday)
            $dayOfMonth = date('j'); // 1 to 31
            
            // Determine backup type and location
            if ($dayOfMonth == 1) {
                // Monthly backup (1st day of month)
                $backupType = 'monthly';
                $filename = "monthly_{$date}.sql";
                $fullPath = $this->backupPath . "\\monthly\\{$filename}";
            } elseif ($dayOfWeek == 0) {
                // Weekly backup (Sunday)
                $backupType = 'weekly';
                $filename = "weekly_{$date}.sql";
                $fullPath = $this->backupPath . "\\weekly\\{$filename}";
            } else {
                // Daily backup
                $backupType = 'daily';
                $filename = "daily_{$date}.sql";
                $fullPath = $this->backupPath . "\\daily\\{$filename}";
            }

            // Create compressed backup
            $this->writeLog("Starting {$backupType} backup: {$filename}");

            // mysqldump command
            $command = sprintf(
                '"C:\\xampp\\mysql\\bin\\mysqldump" --user=%s --password=%s --host=%s --routines --triggers --events --databases %s > "%s"',
                escapeshellarg($this->username),
                escapeshellarg($this->password),
                escapeshellarg($this->host),
                escapeshellarg($this->database),
                $fullPath
            );

            // Execute backup
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullPath) && filesize($fullPath) > 0) {
                // Create compressed version
                $this->compressBackup($fullPath);
                
                // Verify backup integrity
                if ($this->verifyBackup($fullPath . '.gz')) {
                    $this->writeLog("✓ Backup successful: {$filename}.gz (" . $this->formatSize(filesize($fullPath . '.gz')) . ")");
                    
                    // Clean up uncompressed file
                    unlink($fullPath);
                    
                    // Clean old backups
                    $this->cleanOldBackups($backupType);
                    
                    return true;
                } else {
                    throw new Exception("Backup verification failed");
                }
            } else {
                throw new Exception("mysqldump failed: " . implode("\n", $output));
            }

        } catch (Exception $e) {
            $this->writeLog("✗ Backup failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compress backup file using gzip
     */
    private function compressBackup($filePath) {
        $data = file_get_contents($filePath);
        $gzData = gzencode($data, 9);
        file_put_contents($filePath . '.gz', $gzData);
        return file_exists($filePath . '.gz');
    }

    /**
     * Verify backup file integrity
     */
    private function verifyBackup($filePath) {
        if (!file_exists($filePath) || filesize($filePath) < 100) {
            return false;
        }

        // Try to decompress and check first few lines
        $gz = gzopen($filePath, 'r');
        if ($gz) {
            $header = gzread($gz, 100);
            gzclose($gz);
            return strpos($header, '-- MySQL dump') !== false;
        }
        return false;
    }

    /**
     * Clean old backups
     */
    private function cleanOldBackups($backupType) {
        $path = $this->backupPath . "\\{$backupType}";
        $files = glob($path . "\\*.gz");
        
        // Sort by file creation time
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Determine retention policy
        switch ($backupType) {
            case 'daily':
                $keep = 7; // Keep last 7 daily backups
                break;
            case 'weekly':
                $keep = 4; // Keep last 4 weekly backups
                break;
            case 'monthly':
                $keep = 12; // Keep last 12 monthly backups
                break;
            default:
                $keep = 10;
        }

        // Remove old backups
        while (count($files) > $keep) {
            $fileToDelete = array_shift($files);
            unlink($fileToDelete);
            $this->writeLog("Deleted old backup: " . basename($fileToDelete));
        }
    }

    /**
     * Format file size
     */
    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Send email notification (optional)
     */
    private function sendNotification($subject, $message) {
        // Configure your email settings here
        $to = "admin@yourdomain.com";
        $headers = "From: backup@yourdomain.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // mail($to, $subject, $message, $headers);
    }
}

// Execute backup
$backup = new DatabaseBackup();
$result = $backup->createBackup();

// Output result if run from command line
if (php_sapi_name() === 'cli') {
    echo $result ? "Backup completed successfully\n" : "Backup failed\n";
}