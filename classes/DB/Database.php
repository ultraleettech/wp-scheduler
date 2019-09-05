<?php

namespace Ultraleet\WP\Scheduler\DB;

use Psr\Log\LoggerInterface;

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

    /** @var LoggerInterface */
    protected $logger;

    public function __construct($logger)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = [
            'tasks' => $wpdb->prefix . 'ultraleet_scheduler_tasks',
        ];
        $this->logger = $logger;
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
     * Insert rows in batch.
     *
     * @param array $rows
     * @param string $table
     *
     * @todo Insert correctly when row arrays are not indexed in the same column order
     */
    public function insertBatch(array $rows, string $table): void
    {
        if (count($rows)) {
            $columns = array_keys(current($rows));
            $format = '(' . implode(',', array_fill(0, count($columns), "'%s'")) . ')';
            foreach ($columns as $index => $column) {
                $columns[$index] = "`$column`";
            }
            $columns = implode(',', $columns);
            $values = [];
            foreach ($rows as $row) {
                $values[] = vsprintf($format, array_map('esc_sql', $row));
            }
            $table = $this->tables[$table] ?? $table;
            $sql = "INSERT INTO {$table} ($columns) VALUES " . implode(',', $values);
            $this->wpdb->query($sql);
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
