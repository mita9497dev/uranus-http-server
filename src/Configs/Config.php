<?php

namespace Mita\UranusHttpServer\Configs;

use Dotenv\Dotenv;
use InvalidArgumentException;

class Config
{
    protected $configs = [];

    public function __construct(array $defaultConfig = [], array $userConfig = [])
    {
        $this->configs = $this->mergeConfigs($defaultConfig, $userConfig);
        $this->initEnv();
        $this->loadEnvConfig();
    }

    protected function mergeConfigs(array $defaultConfig, array $userConfig): array
    {
        $mergedConfig = $defaultConfig;

        foreach ($userConfig as $key => $value) {
            if (is_array($value) && isset($mergedConfig[$key]) && is_array($mergedConfig[$key])) {
                $mergedConfig[$key] = $this->mergeConfigs($mergedConfig[$key], $value);
            } else {
                $mergedConfig[$key] = $value;
            }
        }

        return $mergedConfig;
    }

    public function initEnv(): void 
    {
        try {
            $dir = defined('ROOT_DIR') ? constant('ROOT_DIR') : dirname(__DIR__, 2);
            $dotenv = Dotenv::createUnsafeImmutable($dir, '.env');
            $dotenv->load();

            $env = getenv('APP_ENV') ?: 'development';
            
            $envFile = ".env";
            if ($env === 'production') {
                $envFile = ".production.env";

            } elseif ($env === 'staging') {
                $envFile = ".staging.env";

            } else if ($env === 'development') {
                $envFile = ".development.env";

            } else if ($env === 'docker') {
                $envFile = ".docker.env";
            }
            
            $envPath = $dir . DIRECTORY_SEPARATOR . $envFile;
            if (file_exists($envPath) && $envFile !== '.env') {
                $dotenv = Dotenv::createUnsafeImmutable($dir, $envFile);
                $dotenv->load();
            }

        } catch (\Throwable $e) {}
    }

    protected function loadEnvConfig(): void
    {
        foreach ($this->configs as $key => $value) {
            $envKey = strtoupper(str_replace('.', '_', $key));
            $envValue = getenv($envKey);
            if ($envValue) {
                $this->configs[$key] = $envValue;

            } elseif (is_array($value)) {
                $this->configs[$key] = $this->loadEnvArrayConfig($this->configs[$key], $envKey);

            } else {
                $this->configs[$key] = $value;
            }
        }
    }
 
    protected function loadEnvArrayConfig(array $config, string $parentKey): array
    {
        $result = [];
        foreach ($config as $key => $value) {
            $envKey = $parentKey . '_' . strtoupper(str_replace('.', '_', $key));
            $envValue = getenv($envKey);
            if ($envValue) {
                $result[$key] = $envValue;

            } elseif (is_array($value)) {
                $result[$key] = $this->loadEnvArrayConfig($value, $envKey);

            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function mergeConfig(array $config): void
    {
        $this->configs = array_merge_recursive($this->configs, $config);
    }

    public function load($file): void
    {
        if (!file_exists($file)) {
            throw new InvalidArgumentException("Config file not found: {$file}");
        }

        $config = require $file;

        if (!is_array($config)) {
            throw new InvalidArgumentException("Config file must return an array");
        }

        $this->configs = array_merge($this->configs, $config);
    }
    
    public function setConfig(array $config, bool $merge = true): void
    {
        if ($merge) {
            $this->configs = array_merge($this->configs, $config);
        } else {
            $this->configs = $config;
        }
    }

    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->configs;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    public function getAll(): array
    {
        return $this->configs;
    }
}
