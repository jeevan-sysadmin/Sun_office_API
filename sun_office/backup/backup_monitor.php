<?php
/**
 * Backup Monitor Script
 * Shows backup status and statistics
 */

class BackupMonitor {
    private $backupDrive = 'E:';
    private $backupPath;
    private $database = 'sun_office';

    public function __construct() {
        $this->backupPath = $this->backupDrive . '\\MySQL_Backups\\' . $this->database;
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats() {
        $stats = [
            'daily' => $this->getBackupInfo('daily'),
            'weekly' => $this->getBackupInfo('weekly'),
            'monthly' => $this->getBackupInfo('monthly'),
            'total_size' => 0,
            'total_backups' => 0
        ];

        foreach (['daily', 'weekly', 'monthly'] as $type) {
            $stats['total_backups'] += $stats[$type]['count'];
            $stats['total_size'] += $stats[$type]['total_size'];
        }

        $stats['total_size_formatted'] = $this->formatSize($stats['total_size']);
        
        return $stats;
    }

    /**
     * Get backup info for specific type
     */
    private function getBackupInfo($type) {
        $path = $this->backupPath . "\\{$type}";
        $files = glob($path . "\\*.gz");
        
        $info = [
            'count' => count($files),
            'files' => [],
            'total_size' => 0,
            'latest' => null,
            'oldest' => null
        ];

        if (!empty($files)) {
            foreach ($files as $file) {
                $size = filesize($file);
                $info['total_size'] += $size;
                $info['files'][] = [
                    'name' => basename($file),
                    'size' => $size,
                    'size_formatted' => $this->formatSize($size),
                    'date' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }

            // Sort by date
            usort($info['files'], function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            $info['latest'] = $info['files'][0];
            $info['oldest'] = end($info['files']);
        }

        $info['total_size_formatted'] = $this->formatSize($info['total_size']);
        
        return $info;
    }

    /**
     * Get latest log entries
     */
    public function getLogEntries($lines = 50) {
        $logFile = $this->backupDrive . '\\MySQL_Backups\\backup_log.txt';
        
        if (!file_exists($logFile)) {
            return [];
        }

        $logs = file($logFile);
        $logs = array_reverse($logs);
        return array_slice($logs, 0, $lines);
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
     * Display backup dashboard
     */
    public function displayDashboard() {
        $stats = $this->getBackupStats();
        $logs = $this->getLogEntries(20);

        echo "========================================\n";
        echo "   MySQL Database Backup Monitor\n";
        echo "   Database: {$this->database}\n";
        echo "========================================\n\n";

        echo "Backup Statistics:\n";
        echo "-----------------\n";
        echo "Total Backups: {$stats['total_backups']}\n";
        echo "Total Size: {$stats['total_size_formatted']}\n\n";

        foreach (['daily', 'weekly', 'monthly'] as $type) {
            echo ucfirst($type) . " Backups:\n";
            echo str_repeat('-', strlen($type) + 9) . "\n";
            echo "Count: {$stats[$type]['count']}\n";
            echo "Total Size: {$stats[$type]['total_size_formatted']}\n";
            
            if ($stats[$type]['latest']) {
                echo "Latest: {$stats[$type]['latest']['name']}\n";
                echo "Size: {$stats[$type]['latest']['size_formatted']}\n";
                echo "Date: {$stats[$type]['latest']['date']}\n";
            }
            echo "\n";
        }

        echo "\nRecent Log Entries:\n";
        echo "------------------\n";
        foreach ($logs as $log) {
            echo trim($log) . "\n";
        }
    }
}

// Run monitor
if (php_sapi_name() === 'cli') {
    $monitor = new BackupMonitor();
    $monitor->displayDashboard();
}