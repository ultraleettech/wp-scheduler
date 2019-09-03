<?php

namespace Ultraleet\WP\Scheduler;

use Psr\Log\LoggerInterface;

class Scheduler
{
    /** @var \wpdb */
    protected $wpdb;
    protected $logger;
    protected $tableTasks;

    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'boot'], 0, 0);
    }

    public function boot()
    {
        global $wpdb;
        $this->tableTasks = $wpdb->prefix . 'ultraleet_scheduler_tasks';
        $this->wpdb = $wpdb;
        $this->maybeSetup();
    }

    public function maybeSetup()
    {
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$this->tableTasks}'") != $this->tableTasks) {
            $format = file_get_contents(ULTRALEET_WP_SCHEDULER_DB_PATH . 'create_tasks_table.sql');
            $query = sprintf($format, $this->tableTasks, $this->wpdb->get_charset_collate());
            $this->wpdb->query($query);
        }
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
        $sql = "INSERT INTO {$this->tableTasks} (type, `group`, hook, data, timestamp)";
        $data = json_encode($data);
        $this->wpdb->query($this->wpdb->prepare($sql, $type, $group, $hook, $data, $time));
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
}

// Make sure constants are set
require_once dirname(__DIR__) . 'ultraleet-wp-scheduler.php';
