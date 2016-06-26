<?php
namespace Perbility\Cilex\Handler;

use Guzzle\Http\Client;
use Monolog\Handler\HipChatHandler;
use Monolog\Logger;

/**
 * @author Marc Wörlein <marc.woerlein@perbility.de>
 * @package Perbility\Helix\ClusterManager\Service\Logger
 */
class GuzzleHipChatHandler extends HipChatHandler
{
    /** @var Client */
    private $client;
    
    /** @var string */
    private $protocol;

    /**
     * GuzzleHipChatHandler constructor.
     *
     * @param Client $client
     * @param string $token
     * @param string $room
     * @param string $name
     * @param bool $notify
     * @param bool|int $level
     * @param bool $bubble
     * @param bool $useSSL
     * @param string $format
     * @param string $host
     * @param string $version
     */
    public function __construct(
        Client $client,
        $token,
        $room,
        $name = 'Monolog',
        $notify = false,
        $level = Logger::CRITICAL,
        $bubble = true,
        $useSSL = true,
        $format = 'text',
        $host = 'api.hipchat.com',
        $version = self::API_V1
    ) {
        parent::__construct($token, $room, $name, $notify, $level, $bubble, $useSSL, $format, $host, $version);
        $this->protocol = $useSSL ? 'https://': 'http://';
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
        $data = $this->generateDataStream($record);
        
        // extract url and content out of http data stream to send it via guzzle
        list ($header, $body) = explode("\r\n\r\n", $data, 2);
        list ($postLine, $hostLine) = explode("\r\n", $header);
        $host = substr($hostLine, strlen('HOST: '));
        $url = substr($postLine, strlen('POST '), -strlen(' HTTP/1.1'));
        parse_str($body, $dataArray);
        
        $uri = $this->protocol . $host . $url;
        $this->client->post($uri, ['Content-Type' => 'application/json'], json_encode($dataArray))->send();
    }
}
