<?php

namespace ETNA\Silex\Provider\Config;

use Silex\Application;
use Silex\ServiceProviderInterface;

use ETNA\Silex\Provider\RabbitMQ\RabbitMQServiceProvider;

class RabbitMQ implements ServiceProviderInterface
{
    private $rmq_config;

    public function __construct($rmq_config = null)
    {
        $rmq_config = $rmq_config ?: [];
        $this->rmq_config['exchanges'] = isset($rmq_config['exchanges']) ? $rmq_config['exchanges'] : [];
        $this->rmq_config['queues']    = isset($rmq_config['queues'])    ? $rmq_config['queues']    : [];
    }

    /**
     *
     * @{inherit doc}
     */
    public function register(Application $app)
    {
        if (true !== isset($app["application_env"])) {
            throw new \Exception('$app["application_env"] is not set');
        }

        $rmq_url   = getenv('RABBITMQ_URL');
        $rmq_vhost = getenv('RABBITMQ_VHOST');

        if (false === $rmq_url) {
            throw new \Exception('RABBITMQ_URL is not defined');
        }

        if (false === $rmq_vhost) {
            throw new \Exception('RABBITMQ_VHOST is not defined');
        }

        $config = parse_url($rmq_url);

        foreach (["host", "port", "user", "pass"] as $config_key) {
            if (!isset($config[$config_key])) {
                throw new \Exception("Invalid RABBITMQ_URL : cannot resolve {$config_key}");
            }
        }

        $app['amqp.chans.options'] = [
            'default'  => [
                'host'     => $config['host'],
                'port'     => $config['port'],
                'user'     => $config['user'],
                'password' => $config['pass'],
                'vhost'    => $rmq_vhost,
                'ssl'      => in_array($app['application_env'], ['development', 'production']),
            ]
        ];

        $app['amqp.exchanges.options'] = $this->rmq_config['exchanges'];
        $app['amqp.queues.options']    = $this->rmq_config['queues'];

        $app->register(new RabbitMQServiceProvider());
    }

    /**
     *
     * @{inherit doc}
     */
    public function boot(Application $app)
    {
        return $app;
    }
}
