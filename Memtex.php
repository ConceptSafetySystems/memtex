<?php
class Memtex
{
    // memcache class
    private $mcache;
    private $verbose = false;
    
    /**
     * host: Memcache host
     * port: Memcache port
     */
    public function connect($host, $port)
    {
        $this->verbose_output("connect(): Connecting to $host:$port");
        $this->mcache = memcache_connect($host, $port);
        
        if ($this->mcache === false)
        {
            $this->verbose_output("connect(): returning false due to memcache = false");
            return false;
        }
        
        $this->verbose_output("connect(): success");
        
        return true;
    }
    
    /** 
     * Request a lock for the specified lock name. Will not block, will instantly return true or false
     * name: Unique name for the lock (see uniqid() if you need a way to generate one)
     * expire: Expiry time in seconds. If unlock() is not explicitly called, the lock is released after this amount of time.
               Set this value carefully, it will block any other process from getting this lock until this expires in case the lock is
               not gracefully released. Recommended value is a few seconds, or however long is definitely longer than you could reasonably
               need the lock. Maximum value is 2592000 (30 days), 0 means never expire. For longer durations, can be set to a unix time-stamp
               value, as per memcache expiry specification (http://php.net/manual/en/memcache.set.php)
     * Returns true if the lock was obtained, or false if not
     */
    public function lock($name, $expire)
    {
        $this->verbose_output("lock() called: name - $name, expire - $expire");
        
        // creates a lock value with a guid prefixed by the current machine's hostname
        $lockvalue = uniqid(php_uname('n'), true);
        
        $this->verbose_output("lock(): lockvalue: $lockvalue");
        
        if ($this->mcache === false)
        {
            $this->verbose_output("lock(): returning false due to memcache = false");
            return false;
        }
        
        if ($this->mcache->add($name, $lockvalue, false, $expire) === false)
        {
            $this->verbose_output("lock(): returning false due to memcache add() fail, implies item already exists");
            return false;
        }
        
        // we retrieve the value and check it against the desired lock value because there are claims that add() above
        // can return true from concurrent threads
        if ($this->mcache->get($name) !== $lockvalue)
        {
            $this->verbose_output("lock(): returning false due to memcache get() check fail");
            return false;
        }
        
        $this->verbose_output("lock(): returning true");
        
        return true;
    }
    
    /** 
     * Retrieve meta-data for the lock (meta-data being whatever extra data you'd like to store)
     * name: Name of the lock
     * Returns false on failure, or the meta-data
     */
    public function get_lock_metadata($name)
    {
        $this->verbose_output("get_lock_metadata() called: name - $name");
        
        if($this->mcache === false) 
        {
            $this->verbose_output("get_lock_metadata(): returning false due to memcache = false");
            return false;
        }

        $item = $this->mcache->get($name."_metadata");
        
        if ($item === false)
        {
            $this->verbose_output("get_lock_metadata(): returning false due to memcache get() fail");
            return false;
        }
        
        $this->verbose_output("get_lock_metadata(): success returning $item");
        
        return $item;
    }
    
    /** 
     * Set some meta-data for the lock (meta-data being whatever extra data you'd like to store)
     * name: Name of the lock
     * data: Meta-data for the lock
     * expire: Expiry time for the meta-data. Maximum value is 2592000 (30 days), 0 means never expire. 
               Per memcache specification: http://php.net/manual/en/memcache.set.php.
     * Returns true on success, false on failure
     */
    public function set_lock_metadata($name, $data, $expire)
    {
        $this->verbose_output("set_lock_metadata() called: name - $name, data - $data, expire - $expire");
        
        if($this->mcache === false) 
        {
            $this->verbose_output("set_lock_metadata(): returning false due to memcache = false");
            return false;
        }
            
        if ($this->mcache->replace($name."_metadata", $data, false, $expire) === false)
        {
            if ($this->mcache->set($name."_metadata", $data, false, $expire) === false)
            {
                $this->verbose_output("set_lock_metadata(): returning false due to memcache set() fail");
                return false;
            }
        }
        
        $this->verbose_output("set_lock_metadata(): success");

        return true;
    }
    
    /** 
     * name: Name for the lock to unlock
     * Returns true on success, false on failure
     */
    public function unlock($name)
    {
        $this->verbose_output("unlock() called: name - $name");
        
        if($this->mcache === false) 
        {
            $this->verbose_output("unlock(): returning false due to memcache = false");
            return false;
        }

        if ($this->mcache->delete($name) === false)
        {
            $this->verbose_output("unlock(): returning false due to memcache delete() fail");
            return false;
        }
        
        $this->verbose_output("unlock(): success");
        
        return true;
    }
    
    
    /** 
     * v: True for verbose, false for not
     */
    public function verbose($v)
    {
        $this->verbose = $v;
    }
    
    /** 
     * If verbose is enabled, output the message
     */
    public function verbose_output($m)
    {
        if ($this->verbose)
            echo "(Memtex): $m\n";
    }
}
?>