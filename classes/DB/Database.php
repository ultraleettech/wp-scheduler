<?php

namespace Ultraleet\WP\Scheduler\DB;

/**
 * Database class.
 *
 * @package Ultraleet\WP\Scheduler\DB
 *
 * Table names:
 * @property string $tasks
 *
 * @mixin \wpdb
 */
class Database
{
    /** @var \wpdb */
    protected $wpdb;
    protected $tables;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = [
            'tasks' => $wpdb->prefix . 'ultraleet_scheduler_tasks',
        ];
    }

    public function setup()
    {
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$this->tasks}'") != $this->tasks) {
            $format = file_get_contents(ULTRALEET_WP_SCHEDULER_DB_PATH . 'create_tasks_table.sql');
            $query = sprintf($format, $this->tasks, $this->wpdb->get_charset_collate());
            $this->wpdb->query($query);
        }
    }

    /**
     * Class getter returns table name.
     *
     * @param string $name
     * @return string
     */
    public function __get(string $name)
    {
        return $this->tables[$name] ?? $this->wpdb->$name;
    }

    /**
     * Mix in wpdb methods.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->wpdb, $name], $arguments);
    }
}
