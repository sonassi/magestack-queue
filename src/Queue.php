<?php

namespace MageStack\Queue;

use MageStack\Queue\Backend\SQLite;
use MageStack\Queue\Backend\MySQL;

class Queue
{

    protected $db = null;
    protected $path = null;

    protected $tableName = null;
    protected $threshold = null;
    protected $timer = null;

    public function __construct($config)
    {

        switch ($config['database']['driver']) {
            case 'sqlite':
                $pathToDb = $config['path']. '/' .$config['database']['name'] . '.sqlite';
                $this->db = new SQLite($pathToDb);
                break;

            case 'mysql':
                $this->db = new MySQL($config['database']);
                break;
        }

        $this->queueTable = $config['database']['queue_table'];
        $this->metricsTable = $config['database']['metrics_table'];
        $this->threshold = $config['threshold'];
        $this->timer = $config['timer'];
        $this->path = $config['path'];

        return $this;
    }

    public function createTable()
    {
        $query = "DROP TABLE {$this->queueTable};";
        $result = $this->db->exec($query);

        $query = "
            CREATE TABLE IF NOT EXISTS {$this->queueTable} (
            ip BIGINT(10) NOT NULL UNIQUE,
            eta INT(10) NULL DEFAULT 0,
            position INT(10) NOT NULL DEFAULT 0,
            is_queueing BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            entered_at TIMESTAMP NULL DEFAULT NULL,
            waiting_time INT(5) NOT NULL DEFAULT ".$this->timer.");
        ";
        $result = $this->db->exec($query);

        $query = "CREATE INDEX queue_index ON {$this->queueTable} (is_queueing, ip, updated_at);";
        $result = $this->db->exec($query);

        return $result;
    }

    public function flushQueue()
    {
        $query = "DELETE FROM {$this->queueTable}";
        return ($this->db->query($query)) ? true : false;
    }

    public function simulateQueue($queueSize)
    {
        for ($i = 0; $i <= $queueSize; $i++) {
            $ip = long2ip(rand(167772160, 184549375));
            $isQueueing = ($i >= $this->threshold) ? 1 : 0;
            $this->insertOrUpdateVisitor($ip, $isQueueing);
        }
    }

    public function getStatus()
    {
        $query = "
            SELECT *
            FROM {$this->queueTable}
            ORDER BY position";
        $result = $this->db->query($query);

        $visitors = [];
        while ($row = $result->fetchArray()) {
            $visitors[] = $row;
        }

        $result = [
            'visitors' => $visitors,
            'total_visitors' => count($visitors),
            'visitors_on_site' => $this->getVisitorCount(),
        ];

        $result['visitors_in_queue'] = $result['total_visitors'] - $result['visitors_on_site'];

        return $result;
    }

    public function getVisitorCount()
    {
        $query = "
            SELECT count(ip) AS counter
            FROM {$this->queueTable}
            WHERE is_queueing = 0";
        $result = $this->db->query($query);
        $row = $result->fetchArray();

        return isset($row['counter']) ? (int) $row['counter'] : 0;
    }

    public function insertOrUpdateVisitor($ip, $isQueuing = 0)
    {
        $existingData = $this->getDataByIp($ip);

        if (!$existingData) {
            // MySQL can't do subquery on same table
            $query = "SELECT (IFNULL(MAX(position), 0) + 1) AS position FROM {$this->queueTable} WHERE is_queueing = 1";
            $result = $this->db->query($query);
            $position = $result->fetchArray()['position'];

            $query = "
                INSERT INTO {$this->queueTable} (ip, is_queueing, created_at, position, eta)
                VALUES (
                    " . ip2long($ip) . ",
                    {$isQueuing},
                    '" . date('Y-m-d H:i:s') . "',
                    $position,
                    " . (time() + $this->getAverageWaitTime()) . "
                )";
            $createdAt = date('Y-m-d H:i:s');
        } else {
            $query = "
                UPDATE {$this->queueTable}
                SET is_queueing = {$isQueuing}
                WHERE ip = '".ip2long($ip)."'";
            $createdAt = $existingData['created_at'];
        }

        $result = $this->db->exec($query);

        if (!$isQueuing) {
            $waitingTime = time() - strtotime($createdAt);

            $query = "
                UPDATE {$this->queueTable}
                SET entered_at = '" . date('Y-m-d H:i:s') . "', waiting_time = {$waitingTime}
                WHERE ip = '".ip2long($ip)."'";
            $result = $this->db->exec($query);

            setcookie('queue_status', 'bypass', time() + $this->timer * 3600);
        } else
            setcookie('queue_status', 'queueing', time() + $this->timer * 3600);


        return $result;
    }

    public function updateVisitorActivity($ip)
    {
        $query = "
            UPDATE {$this->queueTable}
            SET updated_at = '" . date('Y-m-d H:i:s') . "'
            WHERE ip = '".ip2long($ip)."'";
        $result = $this->db->exec($query);

        return $result;
    }

    public function getDataByIp($ip)
    {
        $query = "
            SELECT *
            FROM {$this->queueTable}
            WHERE ip = '".ip2long($ip)."'";
        $result = $this->db->query($query);
        $row = $result->fetchArray();

        return $row;
    }

    public function isQueueing($ip)
    {
        $data = $this->getDataByIp($ip);
        if (!$data)
            return false;

        return isset($data['is_queueing']) ? (bool) $data['is_queueing'] : 0;
    }

    public function checkAccess($ip)
    {
        $visitorsCount = $this->getVisitorCount();

        // The current visitor count is lower than the threshold
        // so permit the user access
        if ($visitorsCount < $this->threshold) {
            $this->insertOrUpdateVisitor($ip);
            return true;
        }

        $this->insertOrUpdateVisitor($ip, 1);
        return false;
    }

    /*
     * This method is used on each queue user request
     * It should be as efficient as possible
     */
    public function getQueueStats($ip)
    {
        $query = "
            SELECT eta, position, (
                    SELECT count(ip) AS total
                    FROM {$this->queueTable}
                    WHERE is_queueing = 1
                ) as total
            FROM {$this->queueTable}
            WHERE ip = '".ip2long($ip)."'";
        $result = $this->db->query($query);
        $row = $result->fetchArray();

        if ($row['eta'] - time() <= 1)
            $row['eta'] = time() + $this->timer;

        return $row;
    }

    public function getAverageWaitTime()
    {
        // Calculate the average wait time
        $query = "
            SELECT AVG(waiting_time) as avg_waiting_time
            FROM  {$this->queueTable}
            WHERE is_queueing = 0";
        $result = $this->db->query($query);
        $avgWaitTime = $result->fetchArray()['avg_waiting_time'];

        if (is_null($avgWaitTime) || $avgWaitTime < 0)
            $avgWaitTime = $this->timer;

        return round($avgWaitTime, 0);
    }

    public function updateQueueEntries()
    {
        // Kick users out of the site/queue that are inactive
        $query = "
            DELETE FROM {$this->queueTable}
            WHERE updated_at < '". date('Y-m-d H:i:s', time() - $this->timer) . "'";
        $result = $this->db->exec($query);

        $visitorsCount = $this->getVisitorCount();
        $slotsAvailable = $this->threshold - $visitorsCount;

        // This code normally would be done in a single query
        // but MySQL and SQLite don't support the same methods
        // Ie. Limit in subquery
        if ($slotsAvailable > 0) {
            $query = "
                UPDATE {$this->queueTable}
                SET is_queueing = 0, position = 0, updated_at = '" . date('Y-m-d H:i:s') . "'
                WHERE ip IN (
                    SELECT ip
                    FROM  {$this->queueTable}
                    WHERE is_queueing = 1
                    ORDER BY position ASC
                    LIMIT 0, {$slotsAvailable}
                )";
            $result = $this->db->exec($query);
        }

        // Clean up position for users who directly entered the site
        $query = "
            UPDATE {$this->queueTable}
            SET position = 0
            WHERE is_queueing  = 0";
        $result = $this->db->exec($query);

        $query = "
            SELECT MIN(position) as min_position
            FROM {$this->queueTable}
            WHERE is_queueing  = 1";
        $result = $this->db->query($query);
        $positionOffset = $result->fetchArray()['min_position'] - 1;

        $query = "
            UPDATE {$this->queueTable}
            SET position = position - {$positionOffset}
            WHERE is_queueing  = 1";
        $result = $this->db->exec($query);

    }

    public function showQueueAndDie($ip)
    {
        $stats = $this->getQueueStats($ip);

        $stats['eta'] = round(($stats['eta'] - time()) / 60, 0);
        $stats['position'] = ($stats['position'] == 0) ? '~' : $stats['position'];

        $template = file_get_contents($this->path . '/src/view/queue-landing.phtml');
        $template = str_ireplace(
            ['{{queue.position}}', '{{queue.eta}}', '{{queue.total}}', '{{remote_addr}}', '{{server.http_host}}', '{{date.year}}'],
            [$stats['position'], $stats['eta'], $stats['total'], $ip, htmlentities($_SERVER['HTTP_HOST']), date('Y')],
            $template);

        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 300');

        echo $template;
        exit();
    }
}