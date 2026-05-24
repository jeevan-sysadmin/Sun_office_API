<?php
/**
 * MySQL Database Backup Class
 * Handles automatic database backups with rotation
 */

class DatabaseBackup {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'sun_office';
    private $backupDrive = 'E:';
    private $backupBasePath;
    private $backupPath;
    private $logFile;

    public function __construct() {
        // Display startup message
        if (php_sapi_name() === 'cli') {
            echo "Initializing Database Backup...\n";
        }
        
        // Set paths
        $this->backupBasePath = $this->backupDrive . '\\MySQL_Backups';
        $this->backupPath = $this->backupBasePath . '\\' . $this->database;
        $this->logFile = $this->backupBasePath . '\\backup_log.txt';
        
        // Create directories
        $this->createDirectories();
    }

    /**
     * Create necessary directories
     */
    private function createDirectories() {
        $directories = [
            $this->backupBasePath,
            $this->backupPath,
            $this->backupPath . '\\daily',
            $this->backupPath . '\\weekly',
            $this->backupPath . '\\monthly'
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (php_sapi_name() === 'cli') {
                    echo "Creating directory: {$dir}\n";
                }
                if (!mkdir($dir, 0777, true)) {
                    $this->writeLog("ERROR: Failed to create directory: {$dir}");
                    if (php_sapi_name() === 'cli') {
                        echo "ERROR: Failed to create directory: {$dir}\n";
                    }
                }
            }
        }
    }

    /**
     * Write to log file
     */
    private function writeLog($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if MySQL is running
     */
    private function isMySQLRunning() {
        $connection = @mysqli_connect($this->host, $this->username, $this->password);
        if ($connection) {
            mysqli_close($connection);
            return true;
        }
        return false;
    }

    /**
     * Find mysqldump executable
     */
    private function findMysqldump() {
        $possiblePaths = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'mysqldump'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                if (php_sapi_name() === 'cli') {
                    echo "Found mysqldump at: {$path}\n";
                }
                return $path;
            }
        }

        // Try to find using where command
        $output = [];
        exec('where mysqldump 2>nul', $output);
        if (!empty($output)) {
            if (php_sapi_name() === 'cli') {
                echo "Found mysqldump at: {$output[0]}\n";
            }
            return $output[0];
        }

        return false;
    }

    /**
     * Create database backup
     */
    public function createBackup() {
        try {
            if (php_sapi_name() === 'cli') {
                echo "Starting backup process...\n";
                echo "Checking MySQL connection...\n";
            }

            // Check MySQL connection
            if (!$this->isMySQLRunning()) {
                throw new Exception("Cannot connect to MySQL. Please make sure MySQL is running.");
            }

            if (php_sapi_name() === 'cli') {
                echo "MySQL is running.\n";
            }

            // Find mysqldump
            $mysqldump = $this->findMysqldump();
            if (!$mysqldump) {
                throw new Exception("mysqldump not found. Please check MySQL installation.");
            }

            // Generate filename
            $date = date('Y-m-d_H-i-s');
            $filename = "backup_{$date}.sql";
            $fullPath = $this->backupPath . "\\daily\\{$filename}";

            if (php_sapi_name() === 'cli') {
                echo "Creating backup: {$filename}\n";
            }

            // Build command
            $command = sprintf(
                '"%s" --user=%s --password=%s --host=%s --databases %s > "%s"',
                $mysqldump,
                $this->username,
                $this->password,
                $this->host,
                $this->database,
                $fullPath
            );

            // Execute backup
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullPath) && filesize($fullPath) > 0) {
                $fileSize = filesize($fullPath);
                if (php_sapi_name() === 'cli') {
                    echo "✓ Backup successful! ({$this->formatSize($fileSize)})\n";
                    echo "Saved to: {$fullPath}\n";
                }
                $this->writeLog("✓ Backup successful: {$filename} (" . $this->formatSize($fileSize) . ")");
                return true;
            } else {
                $errorMsg = !empty($output) ? implode("\n", $output) : "Unknown error";
                throw new Exception("mysqldump failed: " . $errorMsg);
            }

        } catch (Exception $e) {
            $errorMsg = "✗ Backup failed: " . $e->getMessage();
            $this->writeLog($errorMsg);
            if (php_sapi_name() === 'cli') {
                echo $errorMsg . "\n";
            }
            return false;
        }
    }

    /**
     * Format file size
     */
    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Execute if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo "MySQL Database Backup Tool\n";
    echo "==========================\n\n";
    
    $backup = new DatabaseBackup();
    $result = $backup->createBackup();
    
    echo "\n";
    if ($result) {
        echo "Backup completed successfully!\n";
        exit(0);
    } else {
        echo "Backup failed!\n";
        exit(1);
    }
}
?>