<?php

namespace ETNA\Silex\Provider\Config;

use Silex\Application;
use Silex\ServiceProviderInterface;

use ETNA\Silex\Provider\ElasticSearch\ElasticSearchServiceProvider;

/**
*
*/
class ElasticSearch implements ServiceProviderInterface
{
    private $es_options;
    private $app;

    /**
     * @param null|string[] $es_options
     */
    public function __construct(array $es_options = null)
    {
        $es_options = $es_options ?: [
            "default",
        ];

        $this->es_options = [];
        foreach ($es_options as $db_name) {
            $elasicsearch_host = getenv(strtoupper("{$db_name}_ELASTICSEARCH_HOST"));
            $elasicsearch_type = getenv(strtoupper("{$db_name}_ELASTICSEARCH_TYPE"));
            if (false === $elasicsearch_host) {
                throw new \Exception(strtoupper($db_name) . "_ELASTICSEARCH_HOST doesn't exist");
            }
            if (false === $elasicsearch_type) {
                throw new \Exception(strtoupper($db_name) . "_ELASTICSEARCH_TYPE doesn't exist");

            }
            $this->es_options[$db_name] = [
                "host"    => $elasicsearch_host,
                "type"    => $elasicsearch_type
            ];
        }
    }

    /**
     *
     * @{inherit doc}
     */
    public function register(Application $app)
    {
        $this->app = $app;

        if (!isset($app["application_path"])) {
            throw new \Exception('$app["application_path"] is not set');
        }

        if (!isset($app["application_namespace"])) {
            throw new \Exception('$app["application_namespace"] is not set');
        }

        foreach ($this->es_options as $es_option) {
            $host_config                 = parse_url($es_option['host']);

            $app["elasticsearch.server"] = "{$host_config['scheme']}://{$host_config['host']}:{$host_config['port']}/";
            $app["elasticsearch.index"]  = ltrim($host_config['path'], '/');
            $app["elasticsearch.type"]   = $es_option['type'];
            break;
        }
        if (false === isset($app["elasticsearch.indexer"]) ||
            false === is_subclass_of($app["elasticsearch.indexer"], "ETNA\Silex\Provider\Config\AbstractETNAIndexer")) {
            throw new \Exception('You must provide $app["elasticsearch.indexer see AbstractETNAIndexer"]');
        }

        $app->register(new ElasticSearchServiceProvider());

        $app['elasticsearch.create_index'] = [$this, 'createIndex'];
        $app['elasticsearch.lock']         = [$this, 'lock'];
        $app['elasticsearch.unlock']       = [$this, 'unlock'];
    }

    public function createIndex($reset = false)
    {
        $app = $this->app;

        if (!isset($app["version"])) {
            throw new \Exception('$app["version"] is not set');
        }

        if (true === $reset) {
            echo "\nCreating elasticsearch index... {$app["elasticsearch.index"]}\n";
            $this->unlock();
            try {
                $app["elasticsearch"]->delete("/{$app["elasticsearch.index"]}-{$app["version"]}")->send();
                $app["elasticsearch"]->delete("/{$app["elasticsearch.index"]}")->send();
            } catch (\Exception $exception) {
                echo "Index {$app["elasticsearch.index"]} doesn't exist... \n";
            }
            $app["elasticsearch"]->put("/{$app["elasticsearch.index"]}-{$app["version"]}",                                       [], file_get_contents($app["application_path"] . "/app/Utils/elasticsearch-settings.json"))->send();
            $app["elasticsearch"]->put("/{$app["elasticsearch.index"]}-{$app["version"]}/{$app["elasticsearch.type"]}/_mapping", [], file_get_contents($app["application_path"] . "/app/Utils/elasticsearch-mapping.json"))->send();

            // Rajout de l'alias
            $aliases = json_encode(
                [
                    "actions" => [
                        [
                            "remove" => [
                                "index" => $app["elasticsearch.index"] . "-{$app["version"]}",
                                "alias" => $app["elasticsearch.index"]
                            ],
                        ],
                        [
                            "add" => [
                                "index" => $app["elasticsearch.index"] . "-{$app["version"]}",
                                "alias" => $app["elasticsearch.index"]
                            ]
                        ]
                    ]
                ], JSON_PRETTY_PRINT);
            $app["elasticsearch"]->post("/_aliases", [], $aliases)->send();
            echo "Index {$app["elasticsearch.index"]} created successfully!\n\n";
        }
    }

    public function lock()
    {
        $this->lockOrUnlockElasticSearch("lock");
    }

    public function unlock()
    {
        $this->lockOrUnlockElasticSearch("unlock");
    }

    /**
     * Bloque ou débloque les écritures sur l'elasticsearch
     *
     * @param string $action "lock" ou "unlock" pour faire l'action qui porte le même nom
     */
    private function lockOrUnlockElasticSearch($action)
    {
        switch (true) {
            case false === isset($this->app):
            case false === isset($this->app["elasticsearch.server"]):
            case false === isset($this->app["elasticsearch.index"]):
                throw new \Exception(__METHOD__ . "::{$action}: Missing parameter");
        }

        $action = ("lock" === $action) ? "true" : "false";

        $server = $this->app["elasticsearch.server"] . $this->app["elasticsearch.index"];
        exec(
            "curl -XPUT '" . $server . "/_settings' -d '
            {
                \"index\" : {
                    \"blocks.read_only\" : {$action}
                }
            }
            ' 2> /dev/null"
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
