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
    private ?string $authSource = null;

    public function __construct(ComposerConfig $config, IOInterface $io)
    {
        $this->config = $config;
        $this->io = $io;
        $this->loadProjectAuth();
    }

    private function loadProjectAuth(): void
    {
        // Try project directory first
        $projectDir = getcwd();
        $projectAuthFile = $projectDir . '/auth.json';
        
        if (file_exists($projectAuthFile)) {
            $this->projectAuth = json_decode(file_get_contents($projectAuthFile), true);
            $this->authSource = 'project';
            $this->io->write('  <info>✓</info> Using authentication from project auth.json', true, IOInterface::VERBOSE);
            return;
        }

        // Try composer home directory next
        $composerHome = getenv('COMPOSER_HOME') ?: getenv('HOME') . '/.config/composer';
        $globalAuthFile = $composerHome . '/auth.json';
        
        if (file_exists($globalAuthFile)) {
            $this->projectAuth = json_decode(file_get_contents($globalAuthFile), true);
            $this->authSource = 'global';
            $this->io->write('  <info>✓</info> Using authentication from global auth.json', true, IOInterface::VERBOSE);
        }
    }

    public function getAuthHeaders(string $url): array
    {
        $headers = [];
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!$host) {
            return $headers;
        }

        // Try project auth.json first
        if ($this->projectAuth && isset($this->projectAuth['http-basic'][$host])) {
            $auth = $this->projectAuth['http-basic'][$host];
            if (!empty($auth['username']) && !empty($auth['password'])) {
                $headers[] = sprintf(
                    'Authorization: Basic %s',
                    base64_encode($auth['username'] . ':' . $auth['password'])
                );
                
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf(
                        '  <info>✓</info> Using %s credentials for %s (username: %s)',
                        $this->authSource,
                        $host,
                        $auth['username']
                    ), true, IOInterface::VERBOSE);
                }
                
                return $headers;
            }
        }

        // Try global auth config as fallback
        $authConfig = $this->config->get('http-basic') ?? [];
        if (isset($authConfig[$host])) {
            $auth = $authConfig[$host];
            if (!empty($auth['username']) && !empty($auth['password'])) {
                $headers[] = sprintf(
                    'Authorization: Basic %s',
                    base64_encode($auth['username'] . ':' . $auth['password'])
                );
                
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf(
                        '  <info>✓</info> Using composer config credentials for %s (username: %s)',
                        $host,
                        $auth['username']
                    ), true, IOInterface::VERBOSE);
                }
            }
        }

        if (empty($headers) && $this->io->isVeryVerbose()) {
            $this->io->write(sprintf(
                '  <warning>!</warning> No authentication found for %s',
                $host
            ), true, IOInterface::VERBOSE);
        }

        return $headers;
    }

    public function getAuthOptions(string $url): array
    {
        $headers = $this->getAuthHeaders($url);
        if (empty($headers)) {
            return [];
        }

        return [
            'http' => [
                'header' => $headers
            ]
        ];
    }

    public function hasAuthFor(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        return isset($this->projectAuth['http-basic'][$host]) || 
               isset($this->config->get('http-basic')[$host]);
    }
}
