<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use Composer\Factory;
use Composer\Config as ComposerConfig;
use Composer\IO\IOInterface;

class AuthConfig
{
    private ComposerConfig $config;
    private IOInterface $io;

    public function __construct(ComposerConfig $config, IOInterface $io)
    {
        $this->config = $config;
        $this->io = $io;
    }

    public function getAuthHeaders(string $url): array
    {
        $headers = [];
        
        // Get authentication configuration
        $authConfig = $this->config->get('http-basic') ?? [];
        
        // Try to find credentials for the given URL
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && isset($authConfig[$host])) {
            $auth = $authConfig[$host];
            if (!empty($auth['username']) && !empty($auth['password'])) {
                $headers[] = sprintf(
                    'Authorization: Basic %s',
                    base64_encode($auth['username'] . ':' . $auth['password'])
                );
            }
        }

        return $headers;
    }

    public function configureAuthentication(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return;
        }

        // Check if we already have credentials
        $authConfig = $this->config->get('http-basic') ?? [];
        if (isset($authConfig[$host])) {
            return;
        }

        // Try to load auth.json from the project directory
        $projectDir = getcwd();
        $authFile = $projectDir . '/auth.json';
        
        if (file_exists($authFile)) {
            $auth = json_decode(file_get_contents($authFile), true);
            if (isset($auth['http-basic'][$host])) {
                $this->config->merge([
                    'config' => [
                        'http-basic' => [
                            $host => $auth['http-basic'][$host]
                        ]
                    ]
                ]);
            }
        }
    }
}
