<?php
namespace Perbility\Cilex\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @author Marc Wörlein <marc.woerlein@perbility.de>
 * @package Perbility\Cilex\Logger
 */
class TargetMappingLogger extends AbstractLogger
{
    const CONTEXT_KEY_TARGET = 'target';
    
    /** @var LoggerInterface[][] */
    private $loggers = [];

    /**
     * @param LoggerInterface $logger
     * @param string|string[] $targets
     */
    public function addLogger(LoggerInterface $logger, $targets)
    {
        if ($logger instanceof NullLogger) {
            return;
        }
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        foreach ($targets as $target) {
            if (!is_string($target)) {
                throw new InvalidArgumentException('invalid target');
            }
            $this->loggers[$target][] = $logger;
        }
    }
    
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return null
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset($context[self::CONTEXT_KEY_TARGET])) {
            // no target, no logging
            return;
        }
        $targets = $context[self::CONTEXT_KEY_TARGET];
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        unset($context[self::CONTEXT_KEY_TARGET]);
        
        foreach ($targets as $target) {
            if (!is_string($target)) {
                throw new InvalidArgumentException('invalid target');
            }
            if (isset($this->loggers[$target])) {
                foreach ($this->loggers[$target] as $logger) {
                    $logger->log($level, $message, $context);
                }
            }
        }
    }
}
