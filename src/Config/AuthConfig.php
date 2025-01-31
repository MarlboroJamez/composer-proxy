<?php

declare(strict_types=1);

namespace Molo\ComposerProxy\Config;

use Composer\Factory;
use Composer\Config as ComposerConfig;
use Composer\IO\IOInterface;

class AuthConfig
{
    private const PACKAGIST_HOSTS = [
        'repo.packagist.com',
        'packagist.com',
        'packagist.org'
    ];

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
            return;
        }

        // Try composer config as fallback
        $this->projectAuth = ['http-basic' => $this->config->get('http-basic') ?? []];
        if (!empty($this->projectAuth['http-basic'])) {
            $this->authSource = 'config';
            $this->io->write('  <info>✓</info> Using authentication from composer config', true, IOInterface::VERBOSE);
        }
    }

    private function getPackagistCredentials(): ?array
    {
        if (!$this->projectAuth) {
            return null;
        }

        // First try http-basic auth
        foreach (self::PACKAGIST_HOSTS as $host) {
            if (isset($this->projectAuth['http-basic'][$host])) {
                $auth = $this->projectAuth['http-basic'][$host];
                if (!empty($auth['username']) && !empty($auth['password'])) {
                    return $auth;
                }
            }
        }

        // Then try config auth
        $configAuth = $this->config->get('http-basic') ?? [];
        foreach (self::PACKAGIST_HOSTS as $host) {
            if (isset($configAuth[$host])) {
                $auth = $configAuth[$host];
                if (!empty($auth['username']) && !empty($auth['password'])) {
                    return $auth;
                }
            }
        }

        return null;
    }

    public function getAuthHeaders(string $url): array
    {
        $headers = [];
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!$host) {
            return $headers;
        }
    
        // For proxy URLs, use packagist credentials
        if (strpos($host, 'composer-proxy') !== false) {
            $packagistAuth = $this->getPackagistCredentials();
            if ($packagistAuth && !empty($packagistAuth['username']) && !empty($packagistAuth['password'])) {
                $headers[] = sprintf(
                    'Authorization: Basic %s',
                    base64_encode($packagistAuth['username'] . ':' . $packagistAuth['password'])
                );
                
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf(
                        '  <info>✓</info> Using packagist credentials for proxy %s',
                        $host
                    ), true, IOInterface::VERBOSE);
                }
                
                return $headers;
            } else {
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf(
                        '  <warning>!</warning> No packagist credentials found for proxy %s',
                        $host
                    ), true, IOInterface::VERBOSE);
                }
            }
        }
    
        // For other URLs, use direct host auth
        if (isset($this->projectAuth['http-basic'][$host])) {
            $auth = $this->projectAuth['http-basic'][$host];
            if (!empty($auth['username']) && !empty($auth['password'])) {
                $headers[] = sprintf(
                    'Authorization: Basic %s',
                    base64_encode($auth['username'] . ':' . $auth['password'])
                );
                
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf(
                        '  <info>✓</info> Using %s credentials for %s',
                        $this->authSource,
                        $host
                    ), true, IOInterface::VERBOSE);
                }
            }
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

        if (strpos($host, 'composer-proxy') !== false) {
            return $this->getPackagistCredentials() !== null;
        }

        return isset($this->projectAuth['http-basic'][$host]) || 
               isset($this->config->get('http-basic')[$host]);
    }
}
