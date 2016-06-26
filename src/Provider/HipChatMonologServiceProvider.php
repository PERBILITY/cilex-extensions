<?php
namespace Perbility\Cilex\Provider;

use Cilex\Application;
use Cilex\ServiceProviderInterface;
use Guzzle\Http\Client;
use Monolog\Handler\HipChatHandler;
use Monolog\Logger;
use Perbility\Cilex\Handler\GuzzleHipChatHandler;
use Perbility\Cilex\Logger\TargetMappingLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\NullLogger;

/**
 * @author Marc Wörlein <marc.woerlein@perbility.de>
 * @package Perbility\Cilex\Provider
 */
class HipChatMonologServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        $app['hipchat'] = $app->share(
            function () use ($app) {
                if (empty($app['hipchat.rooms'])) {
                    return new NullLogger();
                }
                
                $log = new TargetMappingLogger();
                $app['hipchat.configure']($log);
                return $log;
            }
        );
        
        $app['hipchat.configure'] = $app->protect(
            function (TargetMappingLogger $log) use ($app) {
                $roomConfigs = $app['hipchat.rooms'];
                $defaults = [
                    'targets' => [],
                    'name' => 'HipChat',
                    'notify' => false,
                    'level' => Logger::INFO,
                    'bubble' => true,
                    'useSSL' => true,
                    'format' => 'text',
                    'host' => 'api.hipchat.com',
                    'version' => HipChatHandler::API_V2,
                    'guzzle' => false,
                ];
                
                if (isset($roomConfigs['_default'])) {
                    $defaults = $roomConfigs['_default'] + $defaults;
                    unset($roomConfigs['_default']);
                }
                
                foreach ($roomConfigs as $roomConfig) {
                    $roomConfig += $defaults;
                    $logger = new Logger('hcm');
                    if (!isset($roomConfig['room']) || !isset($roomConfig['token'])) {
                        throw new InvalidArgumentException('missing room/token configuration');
                    }
                    if (isset($roomConfig['guzzle']) && is_array($roomConfig['guzzle'])) {
                        $logger->pushHandler(new GuzzleHipChatHandler(
                            new Client('', $this->prepareGuzzleOptions($roomConfig['guzzle'])),
                            $roomConfig['token'],
                            $roomConfig['room'],
                            $roomConfig['name'],
                            $roomConfig['notify'],
                            $roomConfig['level'],
                            $roomConfig['bubble'],
                            $roomConfig['useSSL'],
                            $roomConfig['format'],
                            $roomConfig['host'],
                            $roomConfig['version']
                        ));
                    } else {
                        $logger->pushHandler(new HipChatHandler(
                            $roomConfig['token'],
                            $roomConfig['room'],
                            $roomConfig['name'],
                            $roomConfig['notify'],
                            $roomConfig['level'],
                            $roomConfig['bubble'],
                            $roomConfig['useSSL'],
                            $roomConfig['format'],
                            $roomConfig['host'],
                            $roomConfig['version']
                        ));
                    }
                    $log->addLogger($logger, $roomConfig['targets']);
                }
            }
        );
    }

    /**
     * @param mixed[] $config
     *
     * @return mixed[]
     */
    protected function prepareGuzzleOptions(array $config)
    {
        $options = [];

        // add curl proxy options
        if (count($config['proxy']) && $config['proxy']['host']) {
            $curlOptions = [CURLOPT_PROXY => $config['proxy']['host']];

            if ($config['proxy']['port']) {
                $curlOptions[CURLOPT_PROXYPORT] = $config['proxy']['port'];
            }

            $options['curl.options'] = $curlOptions;
        }

        // add ssl config
        if ($config['verify_ssl'] === false) {
            $options['ssl.certificate_authority'] = false;
        }

        return $options;
    }
}
