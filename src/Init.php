<?php

namespace MageStack\Queue;

class Init
{
    public function __construct()
    {
        $config = include realpath(__DIR__) . '/../config.php';

        if ($this->shouldInit($config['whitelist'])) {

            $this->queue = new Queue($config);

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

    --install       Create SQL Lite database for tracking queue entries
    --cron          Update the queue metrics

USAGE;
    }

    private function configure()
    {
        $shortopts  = "";
        $longopts  = [
            'install',
            'cron'
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
        }
    }

    private function startQueue($ip)
    {
        $data = $this->queue->getDataByIp($ip);

        if ($data) { //The IP is already using the site, we update them
            if ($this->queue->isQueueing($ip)) {
                if ($this->queue->checkAccess($ip))
                    return;

                $this->queue->showTemplate($ip); //To queuing page
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

            $this->queue->showTemplate($ip); //To queuing page
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
                var_dump($uri);
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