<?php

namespace Ultraleet\WP\Scheduler;

use Psr\Log\LoggerInterface;
use Ultraleet\WP\Scheduler\DB\Database;

/**
 * Main scheduler class.
 *
 * @package Ultraleet\WP\Scheduler
 *
 * @property Database $db
 * @property Cron $cron
 * @property LoggerInterface $logger
 */
class Scheduler
{
    const CRON_HOOK = 'ultraleet_scheduler_process_queue';
    const CRON_SCHEDULE = 'every_minute';

    protected $pluginFile;
    protected $db;
    protected $cron;
    protected $logger;

    /**
     * Scheduler constructor.
     *
     * @param string $pluginFile REQUIRED for deactivation hook to unschedule the queue processing event.
     */
    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
        add_action('plugins_loaded', [$this, 'boot'], 0, 0);
    }

    /**
     * Boot the library after all plugins have been loaded.
     */
    public function boot()
    {
        $this->getDb()->setup();
        add_action('init', [$this, 'init'], 1);
    }

    /**
     * Initialization tasks.
     */
    public function init()
    {
        add_filter('cron_schedules', [$this->getCron(), 'addCronSchedule']);
        $this->getCron()->schedule();
        register_deactivation_hook($this->pluginFile, [$this->getCron(), 'unschedule']);
        add_action(static::CRON_HOOK, [$this, 'run']);
    }

    /**
     * Schedule a new task to be run after the specified time (or at the earliest if omitted).
     *
     * @param string $group
     * @param string $hook
     * @param $data
     * @param int $time
     * @param string $type
     */
    public function schedule(string $group, string $hook, $data, $time = null, $type = 'action')
    {
        $timestamp = $time ?: time();
        $format = "INSERT INTO {$this->getDb()->tasks} (type, `group`, hook, data, timestamp) VALUES (%s, %s, %s, %s, %d)";
        $sql = $this->getDb()->prepare($format, $type, $group, $hook, json_encode($data), $timestamp);
        $this->getDb()->query($sql);
    }

    /**
     * Process scheduled tasks.
     */
    public function run()
    {

    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return Scheduler
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return Database
     */
    public function getDb()
    {
        if (!isset($this->db)) {
            $this->db = new Database();
        }
        return $this->db;
    }

    /**
     * @return Cron
     */
    public function getCron()
    {
        if (!isset($this->cron)) {
            $this->cron = new Cron();
        }
        return $this->cron;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        $getter = 'get' . ucfirst($name);
        return $this->$getter();
    }
}

// Make sure constants are set
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ultraleet-wp-scheduler.php';
