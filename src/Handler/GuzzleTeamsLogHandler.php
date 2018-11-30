<?php
namespace Perbility\Cilex\Handler;

use CMDISP\MonologMicrosoftTeams\TeamsLogHandler;
use Guzzle\Http\Client;
use Monolog\Logger;

/**
 * @author Marc Wörlein <marc.woerlein@perbility.de>
 * @package Perbility\Helix\ClusterManager\Service\Logger
 */
class GuzzleTeamsLogHandler extends TeamsLogHandler
{
    /** @var string */
    private $url;
    
    /** @var Client */
    private $client;
    
    /**
     * @param Client $client
     * @param $url
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(
        Client $client,
        $url,
        $level = Logger::DEBUG,
        $bubble = true
    ) {
        parent::__construct($url, $level, $bubble);
        
        $this->url = $url;
        $this->client = $client;
    }
    
    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     *
     * @return void
     */
    protected function write(array $record)
    {
        $jsonMessage = json_encode($this->getMessage($record));
        $this->client->post(
            $this->url,
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($jsonMessage)
            ],
            $jsonMessage
        )->send();
    }
}
