<?php
namespace Perbility\Cilex\Provider;

use Cilex\Application;
use Cilex\ServiceProviderInterface;
use CMDISP\MonologMicrosoftTeams\TeamsLogHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

/**
 * A provider that adds logging handlers to MS-Teams to a logger.
 * 
 * @author Sven Hüßner <sven.huessner@perbility.de>
 * @package Perbility\Cilex\Provider
 */
class MsTeamsMonologServiceProvider implements ServiceProviderInterface
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
                
                $log = new Logger('msteams');
                $app['msteams.configure']($log);
                return $log;
            }
        );
        
        // Define handlers for the above created logger and store them as configurations
        $app['msteams.configure'] = $app->protect(
            function (Logger $logger) use ($app) {
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
                    
                    $logger->pushHandler(
                        new TeamsLogHandler(
                            $webhookConfig['url'],
                            Logger::toMonologLevel($webhookConfig['level']),
                            $webhookConfig['bubble']
                        )
                    );
                }
            }
        );
    }
}
