<?php
/**
 * Simple utility to allow multiple servers to have the same cron job scheduled, but only one of them run the job.
 * For this to be effective, all servers must use an NTP time source to keep relatively in time sync. The min delay 
 * variable is used to allow for a small amount of time variation (i.e. set it to 30 seconds), so this means that
 * the task will not be run if the server gets the lock, but the task "last ran time" was within the last 30 seconds.
 * We use an extra memcache metadata key to store this "last ran time" value for the other servers to test.
 * 
 * This script will not run the task itself, it will return 0 (success) or 1 (failure), so use it with the shell
 * && operator, for example as below:
 * 
 * Example: cronmutex.php default randommutexname 30 && echo hi
 * 
 * Written by Andrew Eross
 */

require 'Memtex.php';

function main($config, $memcache_server, $mutex_name, $min_delay)
{    
    $mtex = new Memtex();
    $mtex->verbose(false);

    // err on the side of caution, don't run if we can't contact the memcache server
    if ($mtex->connect($config[$memcache_server]['memcache_server'], $config[$memcache_server]['memcache_port']) == false)
    {
        fprintf(STDERR, "ERROR: Couldn't connect to memcache\n");
        exit(1);
    }
    
    // get the memcache lock
    if ($mtex->lock($mutex_name, 10))
    {
        // retrieve the last ran time and check that it wasn't less than min_delay seconds ago
        if (check_min_delay($mtex, $mutex_name, $min_delay) == true)
        {
            // save the lock data for 30 days
            $mtex->set_lock_metadata($mutex_name, time(), 2592000);
            $mtex->unlock($mutex_name);
            exit(0);
        }
        else
        {
            $mtex->unlock($mutex_name);
            //echo "NOTICE: Didn't meet min_delay requirement\n";
            exit(1);
        }
    }
    else 
    {
        // This would happen only if there's two competing requests to the mutex at almost
        // the same time, but it could happen, so this is the losing case
        //echo "NOTICE: Didn't get lock\n";
        exit(1);
    }
}

/**
 * Check the time on the lock to ensure it is older than the minimum delay required
 * This is to protect against the job being run multiple times since it cannot be
 * assumed that the cron jobs will run so accurately on time that the lock will be in
 * use at precisely the same time.
 *
 * Returns true if the timestamp is old enough to let the task run, false if it is more recent
 * than the min_delay allowed number of seconds
 */
function check_min_delay($mtex, $mutex_name, $min_delay)
{
    $meta = $mtex->get_lock_metadata($mutex_name);
    if ($meta === false)
        return true;
        
    if ( (time() - $meta) < $min_delay )
    {
        return false;
    }
    else
    {
        return true;
    }
}

/*
* Print command usage information
*/
function usage()
{
    global $argv;
    echo $argv[0].": [memcache server name] [mutex name] [min delay]\n";
    echo "\t[memcache server name]: Memcache server config name\n";
    echo "\t[mutex name]: Unique string name for the mutex\n";
    echo "\t[min delay]: Min time (secs) required since last job run, max 2592000 (30 days)\n\n";
    echo "\tReturns 1 on success (meaning run the task), 0 on failure\n\n";
    echo "\tExample: cronmutex.php server randommutexname 30 && /bin/task\n";
    exit(1);
}

$config = array
(
    'default' => array
    (
        'memcache_server' => '127.0.0.1',
        'memcache_port' => 11211,
    ),
);

if (!isset($argv[1]))
{
    fprintf(STDERR, "ERROR: Missing memcache server name\n\n");
    usage();
}

if (!isset($argv[2]))
{
    fprintf(STDERR, "ERROR: Missing mutex name\n\n");
    usage();
}

if (!isset($argv[3]))
{
    fprintf(STDERR, "ERROR: Missing min delay\n\n");
    usage();
}

if (!isset($config[$argv[1]]))
{
    fprintf(STDERR, "ERROR: Invalid server name specified, edit this file to add the server\n");
    exit(1);    
}

main($config, $argv[1], $argv[2], $argv[3]);
 
?>