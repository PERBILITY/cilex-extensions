<?php
namespace Perbility\Cilex\Store;

use DirectoryIterator;
use Exception;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Local store to handle locking and persist application data
 *
 * @package Perbility\Cilex\Store
 */
class LocalStore
{
    const DATA_DIR = 'data';
    const LOCK_FILE = 'lock';
    
    /** @var string */
    private $path;
    
    /** @var resource */
    private $lockHandle;
    
    /** @var LoggerInterface */
    private $log;

    /**
     * @param string $path
     * @param LoggerInterface $log
     *
     * @throws Exception
     */
    public function __construct($path, LoggerInterface $log)
    {
        if (!is_writable($path)) {
            throw new Exception("Path $path is not writable");
        }
        
        $this->path = $path;
        $this->log = $log;
    }
    
    /**
     */
    public function __destruct()
    {
        if (null !== $this->lockHandle) {
            $this->unlock();
        }
    }
    
    /**
     * removes all data from store
     */
    public function clear()
    {
        $it = new DirectoryIterator($this->path);
        foreach ($it as $file) {
            if ($file->isDot()) {
                continue;
            }
            
            // use `rm -rf` -- Deleting recursively with PHP is cumbersome
            $command = sprintf('rm -rf %s', escapeshellarg($file->getPathname()));
            shell_exec($command);
        }
        
        $this->log->info("Cleared local-store at " . $this->path);
    }
    
    /**
     * initialize empty store
     */
    public function init()
    {
        foreach ($this->getDirectoryNames() as $dir) {
            mkdir($this->path . '/' . $dir);
        }
        touch($this->path . '/' . self::LOCK_FILE);
        
        $this->setValue('initialized', [
            'timestamp' => (new DateTime())->format('c'),
            'username'  => trim(shell_exec('whoami'))
        ]);
        $this->log->info("Initialized local-store at " . $this->path);
    }
    
    /**
     * @return bool
     */
    public function isInitialized()
    {
        return null !== $this->getValue('initialized');
    }
    
    /**
     * @param string $key
     * @param mixed $value
     *
     * @throws Exception if store is not initialized
     */
    public function set($key, $value)
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        $this->setValue($key, $value);
    }
    
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     * 
     * @throws Exception if store is not initialized
     */
    public function get($key, $default = null)
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        return $this->getValue($key, $default);
    }

    /**
     * Locks the store exclusive
     * @param bool $nonblocking
     *
     * @throws Exception if store is not initialized
     * @throws Exception if lock cannot be acquired
     */
    public function lock($nonblocking = false)
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        
        if (null !== $this->lockHandle) {
            return;
        }
        
        $handle = fopen($this->path . '/' . self::LOCK_FILE, 'r+');
        if (!flock($handle, $nonblocking ? LOCK_EX|LOCK_NB : LOCK_EX)) {
            throw new Exception("Lock failed");
        }
        
        $this->lockHandle = $handle;
        $this->log->debug("Acquired lock");
    }

    /**
     * Release exclusiv lock
     * 
     * @throws Exception if store is not initialized
     * @throws Exception if lock cannot be released
     */
    public function unlock()
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        
        if (null === $this->lockHandle) {
            return;
        }
        
        if (!flock($this->lockHandle, LOCK_UN)) {
            throw new Exception("Unlock failed");
        }
        
        fclose($this->lockHandle);
        $this->lockHandle = null;
        $this->log->debug("Released lock");
    }
    
    /**
     * all known subdirectories of the store, can be overwritten from spezialized store implementations
     * 
     * @return string[]
     */
    protected function getDirectoryNames()
    {
        return [static::DATA_DIR];
    }
    
    /**
     * @param string $key
     * @param mixed $value
     */
    private function setValue($key, $value)
    {
        $value = Yaml::dump($value);
        $file = sprintf(
            "%s/%s/%s.yml",
            $this->path,
            static::DATA_DIR,
            preg_replace('/[^\w.-]/', '_', $key)
        );
        file_put_contents($file, $value);
    }
    
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getValue($key, $default = null)
    {
        $file = sprintf(
            "%s/%s/%s.yml",
            $this->path,
            static::DATA_DIR,
            preg_replace('/[^\w.-]/', '_', $key)
        );
        if (!file_exists($file)) {
            return $default;
        }
        return Yaml::parse(file_get_contents($file));
    }
}
