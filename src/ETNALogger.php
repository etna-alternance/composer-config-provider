<?php

namespace ETNA\Silex\Provider\Config;

use Silex\Application;
use Silex\ServiceProviderInterface;

use \Ibsciss\Silex\Provider\SuperMonologServiceProvider;
use \DZunke\SlackBundle\Silex\Provider\SlackServiceProvider;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

class ETNALogger implements ServiceProviderInterface
{
    /**
     *
     * @{inherit doc}
     */
    public function register(Application $app)
    {
        if (true !== isset($app["application_path"])) {
            throw new \Exception('$app["application_path"] is not set');
        }

        if (true !== isset($app["application_name"])) {
            throw new \Exception('$app["application_name"] is not set');
        }

        $app_name = $app["application_name"];

        $app->register(
            new SuperMonologServiceProvider(),
            [
                'monolog.logfile'               => "{$app["application_path"]}/tmp/log/{$app_name}.log",
                'monolog.name'                  => $app_name,
                'monolog.level'                 => (true === $app['debug']) ? Logger::DEBUG : Logger::ERROR,
                'monolog.fingerscrossed.level'  => Logger::CRITICAL,
                'monolog.rotatingfile'          => true,
                'monolog.rotatingfile.maxfiles' => 7
            ]
        );

        if (true !== $app['debug']) {
            $syslog    = new SyslogHandler($app_name, "user");
            $formatter = new LineFormatter("%message% %context%");
            $syslog->setFormatter($formatter);
            $app['monolog']->pushHandler($syslog);

            $this->slackLogger($app, $app_name);
        }

        $app['logs'] = $app['monolog'];
    }

    private function slackLogger(Application $app, $app_name)
    {
        $slack_token = getenv("SLACK_TOKEN");
        if (false === $slack_token) {
            return;
        }

        $app['dz.slack.options'] = [
            'token'         => $slack_token,
            'identities'    => [
                $app_name => []
            ],
            'logging'       => [
                'enabled'  => true,
                'channel'  => "#error",
                'identity' => $app_name
            ]
        ];
        $app->register(new SlackServiceProvider());

        $app['monolog.handler.slack']->setFormatter(
            new LineFormatter(
                "```[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n```"
            )
        );
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
