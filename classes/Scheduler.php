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
    const RUN_TIME = 180;
    const BATCH_SIZE = 25;

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
     *
     * @todo Refactor by extracting classes and methods.
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
     *
     * @todo Refactor by extracting classes and methods.
     * @todo Housekeeping (reset stale running tasks)
     */
    public function run()
    {
        $pid = getmypid();
        $batchSize = apply_filters('ultraleet_scheduler_batch_size', static::BATCH_SIZE);
        $runTime = apply_filters('ultraleet_scheduler_run_time', static::RUN_TIME);
        $startTime = time();
        $tasksCompleted = 0;
        do {
            $format = "SELECT * FROM {$this->getDb()->tasks} WHERE timestamp <= %d AND status = 'pending' LIMIT 0, $batchSize";
            $sql = $this->getDb()->prepare($format, time());
            $tasks = $this->getDb()->get_results($sql, ARRAY_A);
            if (!empty($tasks) && !$tasksCompleted && isset($this->logger)) {
                $this->logger->debug("SCHEDULER [$pid]: Processing task queue.");
            }
            $taskIds = array_map(function ($task) {
                return $task['id'];
            }, $tasks);
            if (!empty($taskIds)) {
                $format = implode(',', array_fill(0, count($taskIds), '%d'));
                $query = $this->getDb()->prepare(
                    "UPDATE {$this->getDb()->tasks} SET status = 'running' WHERE id IN ($format)",
                    $taskIds
                );
                $this->getDb()->query($query);
            }
            foreach ($tasks as $task) {
                do_action($task['hook'], json_decode($task['data'], true));
                $this->getDb()->query(
                    "UPDATE {$this->getDb()->tasks} SET status = 'complete' WHERE id = {$task['id']}"
                );
                $tasksCompleted++;
            }
        } while (!empty($tasks) && (time() <= $startTime + $runTime));
        if (isset($this->logger) && $tasksCompleted) {
            $time = time() - $startTime;
            $this->logger->debug("SCHEDULER [$pid]: $tasksCompleted tasks completed in $time seconds.");
        }
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

// Make sure constants and functions are defined
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ultraleet-wp-scheduler.php';