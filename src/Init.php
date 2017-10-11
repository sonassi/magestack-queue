<?php

namespace MageStack\Queue;

class Init
{
    public function __construct()
    {
        $this->config = include realpath(__DIR__) . '/../config.php';

        if ($this->shouldInit($this->config['whitelist']) && $this->config['enabled']) {

            $this->queue = new Queue($this->config);

            if ($this->isCli()) {
                $this->configure();
                return;
            }

            $this->startQueue($_SERVER['REMOTE_ADDR']);
            return;
        }
    }

    private function usage()
    {
        $filename = __FILE__;

        return <<<USAGE
Usage:  php -f queue.php -- [options]

    --install          Create SQL Lite database for tracking queue entries
    --cron             Update the queue metrics
    --flush            Delete entire queue (both users in and out of the queue)
    --status           Show queue statistics
    --simulate [0-9]+  Insert defined number of users into the queue

USAGE;
    }

    private function configure()
    {
        $shortopts  = "";
        $longopts  = [
            'install',
            'cron',
            'flush',
            'status',
            'simulate:'
        ];
        $options = getopt($shortopts, $longopts);

        if (!count($options)) {
            echo $this->usage();
            exit(1);
        }

        if (isset($options['install'])) {
            try {
                $this->queue->createTable();
            } catch (Exception $exception) {
                exit($exception->getMessage());
            }
            printf("Database created sucessfully\n");
            exit();

        } else if (isset($options['cron'])) {
            $this->queue->updateQueueEntries();
            printf("Metrics updated sucessfully\n");
            exit();
        } else if (isset($options['flush'])) {
            $this->queue->flushQueue();
            printf("Queue flushed sucessfully\n");
            exit();
        }  else if (isset($options['simulate'])) {
            $this->queue->simulateQueue($options['simulate']);
            printf("Inserted %s simulated users\n", $options['simulate']);
            exit();
        } else if (isset($options['status'])) {
            $this->getStatus();
            exit();
        }

    }

    private function getStatus()
    {
        $results = $this->queue->getStatus();
        $enabled = ($this->config['enabled']) ? 'Enabled' : 'Disabled';
        echo <<<STATUS
Status:          {$enabled}

Threshold:       {$this->config['threshold']} users
Time on site:    {$this->config['timer']} seconds

Users in queue:  {$results['visitors_in_queue']}
Users on site:   {$results['visitors_on_site']}
Total users:     {$results['total_visitors']}
Average wait:    {$this->queue->getAverageWaitTime()}

Visitors
========

STATUS;

        $mask = "|%9.9s | %-15.15s | %-8.8s |\n";
        printf($mask, 'Position', 'IP', 'Status');
        $position = 1;
        foreach ($results['visitors'] as $visitor) {
            printf(
                $mask,
                $visitor['position'],
                long2ip($visitor['ip']),
                ($visitor['is_queueing']) ? 'Queuing' : 'Browsing'
            );
            if ($visitor['is_queueing'] == 1)
                $position++;
        }

    }

    private function startQueue($ip)
    {
        $data = $this->queue->getDataByIp($ip);

        // The IP is already accessing the site, so update information
        if ($data) {
            if ($this->queue->isQueueing($ip)) {
                if ($this->queue->checkAccess($ip))
                    return;

                $this->queue->showQueueAndDie($ip); //To queuing page
                exit;
            } else {
                if (is_null($data['entered_at'])) {
                    $this->queue->insertOrUpdateVisitor($ip, 0);
                }

                $this->queue->updateVisitorActivity($ip);
                return; //Abort and let the user continue his journey
            }
        } else { //The IP isn't yet in the queue table
            if ($this->queue->checkAccess($ip))
                return;

            $this->queue->showQueueAndDie($ip); //To queuing page
            exit;
        }
    }

    private function shouldInit($whitelist)
    {
        if ($this->isCli())
            return true;

        if (isset($whitelist['enabled']) && $whitelist['enabled'] != true)
            return false;

        if (isset($whitelist['ip']) && is_array($whitelist['ip'])) {
            foreach ($whitelist['ip'] as $ip) {
                $regex = sprintf('#%s#', $ip);
                if (preg_match($regex, $_SERVER['REMOTE_ADDR']))
                    return false;
            }
        }

        if (isset($whitelist['uri']) && is_array($whitelist['uri'])) {
            foreach ($whitelist['uri'] as $uri) {
                $regex = sprintf('#%s#', $uri);
                if (preg_match($regex, $_SERVER['REQUEST_URI']))
                    return false;
            }
        }

        return true;
    }

    private function isCli()
    {
        return (php_sapi_name() === 'cli');
    }

}
