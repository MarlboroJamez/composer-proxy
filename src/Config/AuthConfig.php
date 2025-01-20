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
    private ?array $projectAuth = null;

    public function __construct(ComposerConfig $config, IOInterface $io)
    {
        $this->config = $config;
        $this->io = $io;
        $this->loadProjectAuth();
    }

    private function loadProjectAuth(): void
    {
        // Try to load auth.json from the project directory
        $projectDir = getcwd();
        $authFile = $projectDir . '/auth.json';
        
        if (file_exists($authFile)) {
            $this->projectAuth = json_decode(file_get_contents($authFile), true);
        }
    }

    public function getAuthOptions(string $url): array
    {
        $options = [];
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!$host) {
            return $options;
        }

        // Try project auth.json first
        if ($this->projectAuth && isset($this->projectAuth['http-basic'][$host])) {
            $auth = $this->projectAuth['http-basic'][$host];
            if (!empty($auth['username']) && !empty($auth['password'])) {
                $options['http'] = [
                    'header' => [
                        'Authorization: Basic ' . base64_encode($auth['username'] . ':' . $auth['password'])
                    ]
                ];
                return $options;
            }
        }

        // Try global auth config
        $authConfig = $this->config->get('http-basic') ?? [];
        if (isset($authConfig[$host])) {
            $auth = $authConfig[$host];
            if (!empty($auth['username']) && !empty($auth['password'])) {
                $options['http'] = [
                    'header' => [
                        'Authorization: Basic ' . base64_encode($auth['username'] . ':' . $auth['password'])
                    ]
                ];
            }
        }

        return $options;
    }
}
