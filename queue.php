<?php

require_once('classes/Queue.php');

/*** CONFIG DETAILS ***/

$config = array(
    'dbName' => 'queue.sqlite',
    'tableName' => 'queue',
    'threshold' => 1,
    'timer' => 10, //in minutes
    'path' => realpath(dirname(__FILE__))
);

/*** CONFIG DETAILS ***/

$whitelistIp = [ '#172\.16\.0\.*#' ];
$whitelistUri = [ '#paypal#', '#api/v2_soap#' ];

foreach ($whitelistIp as $regex) {
    if (preg_match($regex, $_SERVER['REMOTE_ADDR']))
        return true;
}

foreach ($whitelistUri as $regex) {
    if (preg_match($regex, $_SERVER['REQUEST_URI']))
        return true;
}

$mode = isset($argv[1]) ? $argv[1] : 'default';

$queueObj = new Queue($config);
$ip = $_SERVER['REMOTE_ADDR'];

switch ($mode) {

    case 'install' :

        try {
            $queueObj->createTable();
        }
        catch(Exception $exception) {
            exit($exception->getMessage());
        }
        exit('Installed!');

        break;

    case 'update':

        $queueObj->updateQueueEntries();
        break;

    default:

        if (empty($ip))
            return;

        $data = $queueObj->getDataByIp($ip);

        if ($data) { //The IP is already using the site, we update them

            if ($queueObj->isQueueing($ip)) {

                if ($queueObj->checkAccess($ip))
                    return;

                $queueObj->showTemplate($ip); //To queuing page
                exit;
            }
            else {

                if (is_null($data['entered_at'])) {
                    $queueObj->insertOrUpdateVisitor($ip, 0);
                }

                $queueObj->updateVisitorActivity($ip);
                return; //Abort and let the user continue his journey
            }
        }
        else { //The IP isn't yet in the queue table

            if ($queueObj->checkAccess($ip))
                return;

            $queueObj->showTemplate($ip); //To queuing page
            exit;
        }

        break;
}