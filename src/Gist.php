<?php

namespace Daydiff\Gist;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Description of Gist
 *
 * @author aleksandr.tabakov
 */
class Gist
{
    const VERSION = '0.0.2';

    const GITHUB_API_URL = 'https://api.github.com';
    const GITHUB_BASE_PATH = '';

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_UNAUTHORIZED = 401;

    const TOKEN_FILE = '.php-gist';

    /** @var Client **/
    private $client;


    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl(),
            'http_errors' => false
        ]);
    }

    public function login($credentials = [])
    {
        print "Obtaining OAuth2 access_token from github.\n";

        while (1) {
            print "GitHub username: ";
            $username = trim(fread(STDIN, 255));
            print "GitHub password: ";
            `stty -echo`;
            $password = trim(fread(STDIN, 255));
            `stty echo`;

            $auth = base64_encode("{$username}:{$password}");
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth
            ];
            $body = [
                'scopes' => ['gist'],
                'note' => "The gist php cli client",
                'note_url' => "https://github.com/daydiff/php-gist"
            ];
            $request = new Request('post', 'authorizations', $headers, json_encode($body));
            $response = $this->client->send($request);
            $json = json_decode($response->getBody(), 1);

            if (self::HTTP_CREATED == $response->getStatusCode()) {
                $this->saveToken($json['token']);
                print "Success! https://github.com/settings/tokens\n";
                return;
            } elseif (self::HTTP_UNAUTHORIZED == $response->getStatusCode()) {
                print "Error: {$json['message']}\n";
            } else {
                print "Got: {$response->getBody()}\n";
            }
        }
    }

    private function saveToken($token)
    {
        file_put_contents($this->getTokenFile(), $token);
    }

    private function getToken()
    {
        $token_file = $this->getTokenFile();

        if (file_exists($token_file) && is_readable($token_file)) {
            return file_get_contents($token_file);
        }

        return null;
    }

    private function getHome()
    {
        return DIRECTORY_SEPARATOR == '/' ? $_SERVER['HOME'] : $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
    }

    private function getTokenFile()
    {
        return $this->getHome() . '/' . self::TOKEN_FILE;
    }

    public function createMulti($files, $options)
    {
        $json = [];
        $json['description'] = isset($options['description']) ? $options['description'] : '';
        $json['public'] = isset($options['public']) ? $options['public'] : true;
        $json['files'] = [];
        $existing_gist = null;
        $token = null;

        foreach ($files as $name => $content) {
            $trimmed = trim($content);
            if ($trimmed == '') {
                $this->error('Cannot gist empty files');
            }
            $json['files'][basename($name)] = ['content' => $trimmed];
        }

        if (isset($options['update'])) {
            $existing_gist = $this->extractId($options['update']);
        }
        if (isset($options['anonymous'])) {
            $token = null;
        } else {
            $token = isset($options['access_token']) ? $options['access_token'] : $this->getToken();
        }

        $url = $this->baseUrl() . '/gists';
        $url .= $existing_gist ? '/' . $existing_gist : '';
        $url .= $token ? '?access_token=' . $token : '';

        $request = new Request('post', $url, ['Content-Type' => 'application/json'], json_encode($json));
        $response = $this->client->send($request);

        if ($response->getStatusCode() != self::HTTP_OK) {
            $this->error("Got {$response->getReasonPhrase()} from gist: {$response->getBody()}");
        }

        $this->onSuccess($response->getBody(), $options);
    }

    public function onSuccess($body, $options)
    {
        $json = json_decode($body, 1);
        print $json . "\n";
    }

    public function all($user = false)
    {
        $url = $this->baseUrl();

        if ($user) {
            $url .= "/users/{$user}/gists?per_page=100";
            return $this->gistsPages($url);
        }

        $token = $this->getToken();
        if (!$token) {
            $this->error("Not authenticated. Use 'gist --login' to login or 'gist -l username' to view public gists.");
        }

        $url .= "/gists?per_page=100&access_token=" . ($token);
        return $this->gistsPages($url);
    }

    public function gists($user = false)
    {
        $url = $this->baseUrl();

        if ($user) {
            $url .= "/users/{$user}/gists";
            $request = new Request('get', $url);
            $response = $this->client->send($request);

            $this->prettyGist($response);
            return;
        }

        $token = $this->getToken();
        if (!$token) {
            $this->error("Not authenticated. Use 'gist --login' to login or 'gist -l username' to view public gists.");
        }

        $url .= "/gists?access_token=" . ($token);
        $request = new Request('get', $url);
        $response = $this->client->send($request);

        $this->prettyGist($response);
    }

    public function read($id, $file_name = false)
    {
        $url = $this->baseUrl() . '/gists/' . $id;
        $request = new Request('get', $url);
        $response = $this->client->send($request);

        if ($response->getStatusCode() != self::HTTP_OK) {
            $this->error("Gist with id of {$id} does not exist.");
        }

        $body = json_decode($response->getBody(), 1);
        $files = $body["files"];

        if ($file_name && !isset($files[$file_name])) {
            $this->error("Gist with id of {$id} and file {$file_name} does not exist.");
        } elseif ($file_name) {
            $file = $files[$file_name];
        } else {
            $file = reset($files);
        }

        print $file['content'];
    }

    protected function gistsPages($url)
    {
        $request = new Request('get', $url);
        $response = $this->client->send($request);
        $this->prettyGist($response);
        $link = $response->getHeader('link');

        if ($link) {
            $links = explode(',', preg_replace('/(<|>|")/', '', $link[0]));
            $keys = [];
            $links = array_map(function($el) use(&$keys) {
                list($link, $key) = explode('; rel=', $el);
                $keys[] = $key;
                return $link;
            }, $links);
            $links = array_combine($keys, $links);

            if (isset($links['next'])) {
                return $this->gistsPages($links['next']);
            }
        }
    }

    private function prettyGist(Response $response)
    {
        $body = json_decode($response->getBody(), 1);

        if ($response->getStatusCode() !== self::HTTP_OK) {
            return $this->error($body['message']);
        }

        foreach ($body as $gist) {
            $description = $gist['description'] ?: implode(' ', array_keys($gist['files']));
            $description .= $gist['public'] ? '' : '(secret)';
            echo "{$gist['html_url']} " . strtr($description, "\n", ' ') . "\n";
        }
    }

    private function baseUrl()
    {
        return self::GITHUB_API_URL . self::GITHUB_BASE_PATH;
    }

    private function error($message)
    {
        print $message . "\n";
        exit;
    }

    private function extractId($gist)
    {
        $parts = explode('/', $gist);
        return array_pop($parts);
    }
}
