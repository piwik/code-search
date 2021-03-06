<?php

use GuzzleHttp\Client;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();
$app['debug'] = true;

$app->get('/', function () {
    return file_get_contents(__DIR__ . '/../templates/search.html');
});

$app->get('/api/search', function (Request $request) use ($app) {
    $includePiwik = $request->get('includePiwik', true);
    $query = $request->get('q');
    if (! $query) {
        return new Response('No query specified', 400);
    }

    $repositories = getPluginRepositories();
    if ($includePiwik) {
        $repositories[] = 'piwik/piwik';
    }
    $repositories = array_map(function ($plugin) {
        return 'repo:' . $plugin;
    }, $repositories);
    $query = $query . '+' . implode('+', $repositories);

    $client = new Client();
    $response = $client->get('https://api.github.com/search/code?q=' . $query, [
        'headers' => [
            'Accept' => 'application/vnd.github.v3.text-match+json',
        ],
    ]);

    return $app->json($response->json());
});

$app->run();

function getPluginRepositories()
{
    $cacheFile = __DIR__ . '/../cache/plugins.json';
    // The cache is refreshed every hour
    if (file_exists($cacheFile) && (filemtime($cacheFile) > (time() - 3600))) {
        return json_decode(file_get_contents($cacheFile));
    }

    $client = new Client();
    $response = $client->get('http://plugins.piwik.org/api/1.0/plugins');
    $plugins = $response->json();

    $plugins = array_map(function ($plugin) {
        $url = $plugin['repositoryUrl'];

        $url = str_replace('https://github.com/', '', $url);

        return $url;
    }, $plugins['plugins']);

    file_put_contents($cacheFile, json_encode($plugins, JSON_PRETTY_PRINT));

    return $plugins;
}
