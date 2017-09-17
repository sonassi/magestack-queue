<?php

namespace MageStack\Queue;

use SQLite3;

class Queue
{

    protected $db = null;
    protected $path = null;

    protected $tableName = null;
    protected $threshold = null;
    protected $timer = null;

    public function __construct($config)
    {
        $pathToDb = $config['path'].'/'.$config['db_name'];
        $this->db = new SQLite3($pathToDb);

        $this->tableName = $config['table_name'];
        $this->threshold = $config['threshold'];
        $this->timer = $config['timer'];
        $this->path = $config['path'];

        return $this;
    }

    public function createTable()
    {
        $query = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
            ip VARCHAR(50) NOT NULL UNIQUE,
            is_queueing BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            entered_at TIMESTAMP DEFAULT NULL,
            waiting_time INT(5) NOT NULL DEFAULT 0)";

        $result = $this->db->exec($query);

        return $result;
    }

    public function getVisitorCount()
    {
        $query = "
            SELECT count(ip) AS counter
            FROM {$this->tableName}
            WHERE is_queueing = 0";
        $result = $this->db->query($query);
        $row = $result->fetchArray();

        return isset($row['counter']) ? (int)$row['counter'] : 0;
    }

    public function insertOrUpdateVisitor($ip, $queue = 0)
    {
        $existingData = $this->getDataByIp($ip);

        if (!$existingData) {
            $query = "
                INSERT INTO {$this->tableName} (ip, is_queueing)
                VALUES ('{$ip}', {$queue})";
            $createdAt = date('Y-m-d H:i:s');
        } else {
            $query = "
                UPDATE {$this->tableName}
                SET is_queueing = {$queue}
                WHERE ip = '{$ip}'";
            $createdAt = $existingData['created_at'];
        }

        $result = $this->db->exec($query);

        if (!$queue) {
            $waitingTime = time() - strtotime($createdAt);

            $query = "
                UPDATE {$this->tableName}
                SET entered_at = DATETIME('now'), waiting_time = {$waitingTime}
                WHERE ip = '{$ip}'";
            $result = $this->db->exec($query);

            setcookie('queue_status', 'bypass', time() + $this->timer*60);
        } else
            setcookie('queue_status', 'queueing', time() + $this->timer*60);


        return $result;
    }

    public function updateVisitorActivity($ip)
    {
        $query = "
            UPDATE {$this->tableName}
            SET updated_at = DATETIME('now')
            WHERE ip = '{$ip}'";
        $result = $this->db->exec($query);

        return $result;
    }

    public function getDataByIp($ip)
    {
        $query = "
            SELECT *
            FROM {$this->tableName}
            WHERE ip = '{$ip}'";
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

    public function getPosition($ip)
    {
        $query = "
            SELECT ip
            FROM {$this->tableName}
            WHERE is_queueing = 1
            ORDER BY updated_at
            DESC";
        $result = $this->db->query($query);

        $pos = 1;
        while ($row = $result->fetchArray()) {
            if ($row['ip'] == $ip)
                return $pos;

            $pos++;
        }

        return false;
    }

    public function updateQueueEntries()
    {
        $query = "
            DELETE FROM {$this->tableName}
            WHERE updated_at < datetime('now','-{$this->timer} minutes')";
        $result = $this->db->exec($query);

        $visitorsCount = $this->getVisitorCount();
        $slotLeft = $this->threshold - $visitorsCount;

        if ($slotLeft > 0) {
            $query = "
                UPDATE {$this->tableName}
                SET is_queueing = 0
                WHERE ip IN (
                    SELECT ip
                    FROM  {$this->tableName}
                    WHERE is_queueing = 1
                    ORDER BY updated_at
                    DESC LIMIT 0, {$slotLeft}
                )";
            $result = $this->db->exec($query);
        }
    }

    public function showTemplate($ip)
    {
        $tpl = file_get_contents($this->path.'/templates/queue.phtml');
        $tpl = str_ireplace('{{queue_position}}', $this->getPosition($ip), $tpl);
        echo $tpl;
    }
}