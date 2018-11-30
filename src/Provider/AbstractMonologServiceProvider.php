<?php
namespace Perbility\Cilex\Provider;

/**
 * @author Sven Huessner <sven.huessner@perbility.de>
 * @package Perbility\Cilex\Provider
 */
abstract class AbstractMonologServiceProvider
{
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