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
    const GITHUB_API_URL = 'https://api.github.com/';
    const GITHUB_BASE_PATH = '';

    const CREATED = 201;
    const UNAUTHORIZED = 401;

    const TOKEN_FILE = '.php-gist';

    /** @var Client **/
    private $client;


    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::GITHUB_API_URL,
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
            `stty -echo`;

            $auth = base64_encode("{$username}:{$password}");
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth
            ];
            $body = [
                'scopes' => ['gist'],
                'note' => "The gist php cli client test",
                'note_url' => "https://github.com/daydiff/php-gist"
            ];
            $request = new Request('post', 'authorizations', $headers, json_encode($body));
            $response = $this->client->send($request);
            $json = json_decode($response->getBody(), 1);

            if (self::CREATED == $response->getStatusCode()) {
                $this->saveToken($json['token']);
                print "Success! https://github.com/settings/tokens\n";
                return;
            } elseif (self::UNAUTHORIZED == $response->getStatusCode()) {
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
        $tokenFile = $this->getTokenFile();

        if (file_exists($tokenFile) && is_readable($tokenFile)) {
            return file_get_contents($tokenFile);
        }

        return null;
    }

    private function getHome()
    {
        return $_SERVER['HOME'];
    }

    private function getTokenFile()
    {
        return $this->getHome() . '/' . GIST_TOKEN_FILE;
    }
}
