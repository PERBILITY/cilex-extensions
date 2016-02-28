<?php
namespace Perbility\ResourceHubClient;

use DirectoryIterator;
use Exception;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

class LocalStore
{
    const DATA_DIR = 'data';
    const LOCK_FILE = 'lock';
    const DIRECTORIES = [
        self::DATA_DIR
    ];
    
    private $path;
    private $locked = false;
    private $log;
    
    public function __construct($path, LoggerInterface $log)
    {
        if (!is_writable($path)) {
            throw new Exception("Path $path is not writable");
        }
        
        $this->path = $path;
        $this->log = $log;
    }
    
    public function __destruct()
    {
        if ($this->locked) {
            $this->unlock();
        }
    }
    
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
    
    public function init()
    {
        foreach (static::DIRECTORIES as $dir) {
            mkdir($this->path . '/' . $dir);
        }
        
        $this->setValue('initialized', [
            'timestamp' => (new DateTime())->format('c'),
            'username'  => trim(shell_exec('whoami'))
        ]);
        
        touch($this->path . '/' . self::LOCK_FILE);
        $this->log->info("Initialized local-store at " . $this->path);
    }
    
    public function isInitialized()
    {
        return null !== $this->getValue('initialized');
    }
    
    public function set($key, $value)
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        $this->setValue($key, $value);
    }
    
    public function get($key, $default = null)
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        $this->getValue($key, $default);
    }
    
    public function lock()
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        
        if ($this->locked) {
            return;
        }
        
        $handle = fopen($this->path . '/' . self::LOCK_FILE, 'r');
        if (!flock($handle, LOCK_EX)) {
            throw new Exception("Lock failed");
        }
        
        $this->locked = true;
        fclose($handle);
        $this->log->debug("Acquired lock");
    }
    
    public function unlock()
    {
        if (!$this->isInitialized()) {
            throw new Exception("Local store is not initialized");
        }
        
        if (!$this->locked) {
            return;
        }
        
        $handle = fopen($this->path . '/' . self::LOCK_FILE, 'r');
        if (!flock($handle, LOCK_UN)) {
            throw new Exception("Unlock failed");
        }
        
        $this->locked = false;
        fclose($handle);
        $this->log->debug("Released lock");
    }
    
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
