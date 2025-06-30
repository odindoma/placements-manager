<?php
/**
 * Простой класс для логирования
 */
class Logger {
    private $logFile;
    
    public function __construct($logFile = 'logs/app.log') {
        $this->logFile = $logFile;
        
        // Создаем директорию для логов если не существует
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] $level: $message";
        
        if (!empty($context)) {
            $logLine .= ' ' . json_encode($context);
        }
        
        $logLine .= PHP_EOL;
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
}