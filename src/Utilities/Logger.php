<?php
/**
 * Logger Utility
 *
 * File-based logging with levels and rotation.
 *
 * @package    InvoiceForge
 * @subpackage Utilities
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Utilities;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class.
 *
 * Provides file-based logging with multiple levels.
 * Log files are stored in wp-content/uploads/invoiceforge-logs/
 *
 * @since 1.0.0
 */
class Logger
{
    /**
     * Log level constants.
     *
     * @since 1.0.0
     */
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    /**
     * Log level priorities (lower = less severe).
     *
     * @since 1.0.0
     * @var array<string, int>
     */
    private const LEVEL_PRIORITY = [
        self::LEVEL_DEBUG   => 0,
        self::LEVEL_INFO    => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR   => 3,
    ];

    /**
     * The log directory path.
     *
     * @since 1.0.0
     * @var string
     */
    private string $logDir;

    /**
     * Minimum log level to record.
     *
     * @since 1.0.0
     * @var string
     */
    private string $minLevel;

    /**
     * Number of days to keep log files.
     *
     * @since 1.0.0
     * @var int
     */
    private int $retentionDays;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string|null $logDir        Custom log directory path.
     * @param string      $minLevel      Minimum log level.
     * @param int         $retentionDays Days to keep log files.
     */
    public function __construct(
        ?string $logDir = null,
        string $minLevel = self::LEVEL_DEBUG,
        int $retentionDays = 30
    ) {
        if ($logDir === null) {
            $upload_dir = wp_upload_dir();
            $this->logDir = $upload_dir['basedir'] . '/invoiceforge-logs/';
        } else {
            $this->logDir = trailingslashit($logDir);
        }

        $this->minLevel = $minLevel;
        $this->retentionDays = $retentionDays;

        // Ensure log directory exists
        $this->ensureDirectory();
    }

    /**
     * Log a debug message.
     *
     * @since 1.0.0
     *
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @since 1.0.0
     *
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @since 1.0.0
     *
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @since 1.0.0
     *
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a message with a specific level.
     *
     * @since 1.0.0
     *
     * @param string               $level   The log level.
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Check if this level should be logged
        if (!$this->shouldLog($level)) {
            return;
        }

        // Format the log entry
        $entry = $this->formatEntry($level, $message, $context);

        // Write to file
        $this->write($entry);

        // Periodically clean old logs
        $this->maybeCleanOldLogs();
    }

    /**
     * Check if a level should be logged.
     *
     * @since 1.0.0
     *
     * @param string $level The log level to check.
     * @return bool True if the level should be logged.
     */
    private function shouldLog(string $level): bool
    {
        $level_priority = self::LEVEL_PRIORITY[$level] ?? 0;
        $min_priority = self::LEVEL_PRIORITY[$this->minLevel] ?? 0;

        return $level_priority >= $min_priority;
    }

    /**
     * Format a log entry.
     *
     * @since 1.0.0
     *
     * @param string               $level   The log level.
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return string The formatted log entry.
     */
    private function formatEntry(string $level, string $message, array $context): string
    {
        $timestamp = current_time('Y-m-d H:i:s');

        // Interpolate context into message
        $message = $this->interpolate($message, $context);

        // Add context as JSON if not empty
        $context_string = '';
        if (!empty($context)) {
            $context_string = ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $context_string
        );
    }

    /**
     * Interpolate context values into the message.
     *
     * @since 1.0.0
     *
     * @param string               $message The message with placeholders.
     * @param array<string, mixed> $context The context values.
     * @return string The interpolated message.
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Write a log entry to file.
     *
     * @since 1.0.0
     *
     * @param string $entry The log entry to write.
     * @return void
     */
    private function write(string $entry): void
    {
        $filename = $this->getLogFilename();

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get the current log filename.
     *
     * @since 1.0.0
     *
     * @return string The log filename.
     */
    private function getLogFilename(): string
    {
        $date = current_time('Y-m-d');
        return $this->logDir . 'invoiceforge-' . $date . '.log';
    }

    /**
     * Ensure the log directory exists.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function ensureDirectory(): void
    {
        if (!file_exists($this->logDir)) {
            wp_mkdir_p($this->logDir);

            // Add .htaccess to protect log files
            $htaccess = $this->logDir . '.htaccess';
            if (!file_exists($htaccess)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents($htaccess, "Order deny,allow\nDeny from all");
            }

            // Add index.php for extra protection
            $index = $this->logDir . 'index.php';
            if (!file_exists($index)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents($index, '<?php // Silence is golden.');
            }
        }
    }

    /**
     * Maybe clean old log files.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function maybeCleanOldLogs(): void
    {
        // Only run occasionally (1% chance per log write)
        if (wp_rand(1, 100) !== 1) {
            return;
        }

        $this->cleanOldLogs();
    }

    /**
     * Clean old log files.
     *
     * @since 1.0.0
     *
     * @return int Number of files deleted.
     */
    public function cleanOldLogs(): int
    {
        $deleted = 0;
        $cutoff = strtotime('-' . $this->retentionDays . ' days');

        $files = glob($this->logDir . 'invoiceforge-*.log');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            // Extract date from filename
            if (preg_match('/invoiceforge-(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $file_date = strtotime($matches[1]);
                if ($file_date && $file_date < $cutoff) {
                    wp_delete_file($file);
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $this->info('Cleaned old log files', ['count' => $deleted]);
        }

        return $deleted;
    }

    /**
     * Get all log files.
     *
     * @since 1.0.0
     *
     * @return array<int, array{filename: string, size: int, modified: int}> Array of log file info.
     */
    public function getLogFiles(): array
    {
        $files = glob($this->logDir . 'invoiceforge-*.log');
        $result = [];

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $result[] = [
                'filename' => basename($file),
                'size'     => (int) filesize($file),
                'modified' => (int) filemtime($file),
            ];
        }

        // Sort by modified date (newest first)
        usort($result, fn($a, $b) => $b['modified'] <=> $a['modified']);

        return $result;
    }

    /**
     * Read a log file.
     *
     * @since 1.0.0
     *
     * @param string $filename The log filename.
     * @param int    $lines    Number of lines to read (0 for all).
     * @return string|null The log content or null if not found.
     */
    public function readLog(string $filename, int $lines = 0): ?string
    {
        $filepath = $this->logDir . sanitize_file_name($filename);

        if (!file_exists($filepath)) {
            return null;
        }

        if ($lines === 0) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            return file_get_contents($filepath);
        }

        // Read last N lines
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file
        $all_lines = file($filepath, FILE_IGNORE_NEW_LINES);

        if ($all_lines === false) {
            return null;
        }

        $last_lines = array_slice($all_lines, -$lines);
        return implode("\n", $last_lines);
    }

    /**
     * Get the log directory path.
     *
     * @since 1.0.0
     *
     * @return string The log directory path.
     */
    public function getLogDir(): string
    {
        return $this->logDir;
    }
}
