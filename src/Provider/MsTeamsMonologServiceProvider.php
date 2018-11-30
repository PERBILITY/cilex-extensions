<?php
namespace Perbility\Cilex\Provider;

use Cilex\Application;
use Cilex\ServiceProviderInterface;
use CMDISP\MonologMicrosoftTeams\TeamsLogHandler;
use Guzzle\Http\Client;
use Monolog\Logger;
use Perbility\Cilex\Handler\GuzzleTeamsLogHandler;
use Perbility\Cilex\Logger\TargetMappingLogger;
use Psr\Log\NullLogger;

/**
 * A provider that adds logging handlers to MS-Teams to a logger.
 * 
 * @author Sven Hüßner <sven.huessner@perbility.de>
 * @package Perbility\Cilex\Provider
 */
class MsTeamsMonologServiceProvider extends AbstractMonologServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        // Define a logger to be added to $app and store it as a service
        $app['msteams'] = $app->share(
            function () use ($app) {
                if (empty($app['msteams.webhooks'])) {
                    return new NullLogger();
                }
                
                $log = new TargetMappingLogger();
                $app['msteams.configure']($log);
                return $log;
            }
        );
        
        // Define handlers for the above created logger and store them as configurations
        $app['msteams.configure'] = $app->protect(
            function (TargetMappingLogger $targetLogger) use ($app) {
                $webhookConfigs = $app['msteams.webhooks'];
                
                // Handler defaults
                $defaults = [
                    'name' => 'MS Teams',
                    'level' => Logger::INFO,
                    'bubble' => true,
                ];
                
                // Create handlers for the logger according to config
                foreach ($webhookConfigs as $webhookConfig) {
                    $webhookConfig += $defaults;
                    $logger = new Logger('hcm');
                    
                    if (isset($webhookConfig["guzzle"]) && is_array($webhookConfig["guzzle"])) {
                        // For clusters behind a proxy we need a Guzzle handler
                        $logger->pushHandler(
                            new GuzzleTeamsLogHandler(
                                new Client('', $this->prepareGuzzleOptions($webhookConfig["guzzle"])),
                                $webhookConfig['url'],
                                Logger::toMonologLevel($webhookConfig['level']),
                                $webhookConfig['bubble']
                            )
                        );
                    } else {
                        $logger->pushHandler(
                            new TeamsLogHandler(
                                $webhookConfig['url'],
                                Logger::toMonologLevel($webhookConfig['level']),
                                $webhookConfig['bubble']
                            )
                        );
                    }
    
                    $targetLogger->addLogger($logger, $webhookConfig['targets']);
                }
            }
        );
    }
}
