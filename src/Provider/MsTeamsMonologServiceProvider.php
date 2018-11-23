<?php
namespace Perbility\Cilex\Provider;

use Cilex\Application;
use Cilex\ServiceProviderInterface;

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
    }
}
