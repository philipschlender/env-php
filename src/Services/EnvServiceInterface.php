<?php

namespace Env\Services;

use Env\Exceptions\EnvException;

interface EnvServiceInterface
{
    /**
     * @throws EnvException
     */
    public function loadDotEnv(string $path): void;

    /**
     * @throws EnvException
     */
    public function getEnv(string $key): string;

    public function getEnvOrDefault(string $key, string $default): string;
}
