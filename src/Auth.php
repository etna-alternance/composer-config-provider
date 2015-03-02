<?php

namespace ETNA\Silex\Provider\Config;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Symfony\Component\HttpFoundation\Request;

use \ETNA\Silex\Provider\Auth\AuthServiceProvider;

class Auth implements ServiceProviderInterface
{
    private $auth_config;
    private $app;

    public function __construct($auth_config = null)
    {
        $auth_config       = $auth_config ?: [
            "auth.api_path"          => "^/?",
            "auth.force_guest"       => true,
            "auth.cookie_expiration" => false,
            "auth.before_function"   => [$this, 'authBeforeFunction']
        ];
        $this->auth_config = $auth_config;

        $auth_url               = getenv("AUTH_URL");
        $auth_cookie_expiration = getenv("AUTH_COOKIE_EXPIRATION");

        if (false === $auth_url) {
            throw new \Exception("AUTH_URL doesn't exist");
        }

        $this->auth_config["auth.authenticator_url"] = $auth_url;

        if (false !== $auth_cookie_expiration) {
            // transforme la chaine 'false' reçu de l'env en booleen.
            $auth_cookie_expiration = ($auth_cookie_expiration === 'false') ? false : $auth_cookie_expiration;

            $this->auth_config["auth.cookie_expiration"] = $auth_cookie_expiration;
        }
    }

    /**
     *
     * @{inherit doc}
     */
    public function register(Application $app)
    {
        $this->app = $app;

        if (true !== isset($app["application_env"])) {
            throw new \Exception('$app["application_env"] is not set');
        }

        if (true !== isset($app["application_name"])) {
            throw new \Exception('$app["application_name"] is not set');
        }

        if (true !== isset($app["application_path"])) {
            throw new \Exception('$app["application_path"] is not set');
        }

        $this->auth_config["auth.app_name"] = $app["application_name"];

        foreach ($this->auth_config as $conf_name => $conf_value) {
            $app[$conf_name] = $conf_value;
        }

        $app["auth.public_key.tmp_path"] = "{$app['application_path']}/tmp/public-{$app['application_env']}.key";

        $app->register(new AuthServiceProvider());
    }

    public function authBeforeFunction(Request $req)
    {
        // On autorise les OPTIONS sans auth
        if ('OPTIONS' === $req->getMethod()) {
            return;
        }

        if (!isset($req->user)) {
            return $this->app->json("Authorization Required", 401);
        }
    }

    /**
     *
     * @{inherit doc}
     */
    public function boot(Application $app)
    {
        $app->before($app['auth.before_function']);
    }
}
