<?php
namespace Core;

/**
 * Class Logger
 *
 * Lightweight singleton logger with configurable log levels,
 * context support and file/console output.
 *
 * Configuration is loaded from config/app.php (key: 'logger').
 *
 * Example:
 *   Logger::getInstance()->info('User logged in', ['id' => 1]);
 *   Logger::getInstance()->error('Unhandled exception', ['exception' => $e]);
 */
class Logger
{
    /**
     * Singleton instance
     *
     * @var Logger|null
     */
    private static ?Logger $instance = null;

    /**
     * Enable logging to file
     *
     * @var bool
     */
    private bool $log_to_file = true;

    /**
     * Enable logging to console (STDOUT)
     *
     * @var bool
     */
    private bool $log_to_console = false;

    /**
     * Log file name
     *
     * @var string
     */
    private string $log_file = 'app.log';

    /**
     * Allowed log levels in ascending order
     *
     * @var string[]
     */
    private array $log_levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];

    /**
     * Directory where log files are stored
     *
     * @var string
     */
    private string $log_file_path = __DIR__ . '/../storage/logs';

    /**
     * Minimum log level to be written
     *
     * @var string
     */
    private string $min_level = 'DEBUG';

    /**
     * Logger constructor.
     *
     * Loads configuration from config/app.php.
     * Constructor is private to enforce singleton pattern.
     */
    private function __construct()
    {
        $config_path = __DIR__ . '/../config/app.php';

        if (file_exists($config_path)) {
            $config = include $config_path;

            if (isset($config['logger'])) {
                $loggerCfg = $config['logger'];

                $this->log_to_file    = $loggerCfg['log_to_file'] ?? $this->log_to_file;
                $this->log_to_console = $loggerCfg['log_to_console'] ?? $this->log_to_console;
                $this->log_file       = $loggerCfg['log_file'] ?? $this->log_file;
                $this->log_file_path  = $loggerCfg['log_file_path'] ?? $this->log_file_path;
                $this->min_level      = $loggerCfg['min_level'] ?? $this->min_level;

            }
        }
    }

    /**
     * Returns the singleton Logger instance.
     *
     * @return Logger
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }

        return self::$instance;
    }

    /**
     * Generic logging method with context support.
     *
     * @param string $log_level One of DEBUG, INFO, WARNING, ERROR
     * @param string $message Log message
     * @param array  $context Additional context data
     *
     * @throws \InvalidArgumentException
     */
    public function log(string $log_level, string $message = "", array $context = []): void
    {
        if (!in_array($log_level, $this->log_levels, true)) {
            throw new \InvalidArgumentException("Invalid log level: $log_level");
        }

        if ($this->shouldLog($log_level)) {
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = $context
                ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '';

            $formatted_message = "[$timestamp] [$log_level] $message$contextStr" . PHP_EOL;

            if ($this->log_to_file) {
                $this->writeToFile($formatted_message);
            }

            if ($this->log_to_console) {
                echo (PHP_SAPI === 'cli' ? '\n' : '<br>');
                echo $formatted_message;
                echo (PHP_SAPI === 'cli' ? '\n' : '<br>');
            }
        }
    }

    /**
     * Logs a DEBUG level message.
     *
     * @param string $message
     * @param array  $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Logs an INFO level message.
     *
     * @param string $message
     * @param array  $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Logs a WARNING level message.
     *
     * @param string $message
     * @param array  $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Logs an ERROR level message.
     *
     * @param string $message
     * @param array  $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
        throw new \Exception($message);
    }

    /**
     * Determines whether a message should be logged
     * based on the configured minimum log level.
     *
     * @param string $level
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        $order = array_flip($this->log_levels);
        return $order[$level] >= $order[$this->min_level];
    }

    /**
     * Writes the formatted log message to a file.
     *
     * Creates the log directory and file if they do not exist.
     *
     * @param string $message
     * @return void
     */
    private function writeToFile(string $message): void
    {
        $file_path = rtrim($this->log_file_path, '/') . '/' . $this->log_file;

        if (!is_dir($this->log_file_path)) {
            mkdir($this->log_file_path, 02770, true);
        }

        if (!file_exists($file_path)) {
            touch($file_path);
        }

        file_put_contents($file_path, $message, FILE_APPEND);
    }
}
