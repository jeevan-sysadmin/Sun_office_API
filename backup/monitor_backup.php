<?php
/**
 * Backup Monitor - Check backup status
 */

class BackupMonitor {
    private $backupDrive = 'E:';
    private $database = 'sun_office';
    private $backupPath;
    private $logFile;

    public function __construct() {
        $this->backupPath = $this->backupDrive . '\\MySQL_Backups\\' . $this->database;
        $this->logFile = $this->backupDrive . '\\MySQL_Backups\\backup_log.txt';
    }

    public function displayStatus() {
        $this->clearScreen();
        
        echo "╔════════════════════════════════════════╗\n";
        echo "║    MySQL Backup Monitor                ║\n";
        echo "║    Database: {$this->database}              ║\n";
        echo "╚════════════════════════════════════════╝\n\n";

        // Check backup directories
        $this->checkBackupStatus();
        
        // Show recent backups
        $this->showRecentBackups();
        
        // Show last log entries
        $this->showRecentLogs();
        
        echo "\nPress any key to refresh or Ctrl+C to exit...\n";
    }

    private function checkBackupStatus() {
        $types = ['daily', 'weekly', 'monthly'];
        
        echo "BACKUP STATUS:\n";
        echo "──────────────\n";
        
        foreach ($types as $type) {
            $path = $this->backupPath . '\\' . $type;
            if (file_exists($path)) {
                $files = glob($path . '\\*.gz');
                $count = count($files);
                
                if ($count > 0) {
                    // Get latest backup
                    $latest = max($files);
                    $latestTime = filemtime($latest);
                    $hoursAgo = round((time() - $latestTime) / 3600, 1);
                    
                    echo sprintf("  %-7s : %2d backups (latest: %s, %s hours ago)\n", 
                        ucfirst($type), $count, date('Y-m-d H:i', $latestTime), $hoursAgo);
                } else {
                    echo sprintf("  %-7s : No backups found\n", ucfirst($type));
                }
            } else {
                echo sprintf("  %-7s : Directory not found\n", ucfirst($type));
            }
        }
        echo "\n";
    }

    private function showRecentBackups() {
        echo "RECENT BACKUPS:\n";
        echo "───────────────\n";
        
        $allFiles = [];
        $types = ['daily', 'weekly', 'monthly'];
        
        foreach ($types as $type) {
            $path = $this->backupPath . '\\' . $type;
            if (file_exists($path)) {
                $files = glob($path . '\\*.gz');
                foreach ($files as $file) {
                    $allFiles[$file] = filemtime($file);
                }
            }
        }
        
        // Sort by date (newest first)
        arsort($allFiles);
        $recentFiles = array_slice($allFiles, 0, 10, true);
        
        if (empty($recentFiles)) {
            echo "  No backups found\n\n";
            return;
        }
        
        foreach ($recentFiles as $file => $time) {
            $size = filesize($file);
            $name = basename($file);
            $type = explode('_', $name)[0];
            
            printf("  %s %-8s %-25s %s\n", 
                date('Y-m-d H:i', $time),
                '[' . $type . ']',
                $name,
                $this->formatSize($size)
            );
        }
        echo "\n";
    }

    private function showRecentLogs() {
        if (!file_exists($this->logFile)) {
            echo "No log file found\n";
            return;
        }
        
        echo "RECENT LOG ENTRIES:\n";
        echo "──────────────────\n";
        
        $logs = file($this->logFile);
        $logs = array_reverse($logs);
        $recentLogs = array_slice($logs, 0, 5);
        
        foreach ($recentLogs as $log) {
            echo "  " . trim($log) . "\n";
        }
    }

    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function clearScreen() {
        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            system('cls');
        } else {
            system('clear');
        }
    }
}

// Run monitor
if (php_sapi_name() === 'cli') {
    $monitor = new BackupMonitor();
    
    // Refresh every 10 seconds if no argument provided
    if ($argc > 1 && $argv[1] == '--once') {
        $monitor->displayStatus();
    } else {
        while (true) {
            $monitor->displayStatus();
            sleep(10);
        }
    }
}
?>