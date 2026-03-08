<?php

namespace Env\Services;

use Env\Exceptions\EnvException;
use Fs\Exceptions\FsException;
use Fs\Services\FsServiceInterface;

class EnvService implements EnvServiceInterface
{
    public function __construct(
        protected FsServiceInterface $fsService,
    ) {
    }

    /**
     * @throws EnvException
     */
    public function loadDotEnv(string $path): void
    {
        try {
            $content = $this->fsService->readFile($path);
        } catch (FsException $exception) {
            throw new EnvException($exception->getMessage(), 0, $exception);
        }

        $rows = explode("\n", $content);

        foreach ($rows as $row) {
            $row = trim($row);

            if ('' === $row || !str_contains($row, '=') || str_starts_with($row, '#')) {
                continue;
            }

            [$key, $value] = explode('=', $row, 2);

            $key = trim($key);

            if ('' === $key || str_contains($key, ' ') || isset($_SERVER[$key]) || isset($_ENV[$key])) {
                continue;
            }

            $value = trim($value);

            if (1 === preg_match('/^(["\']).*\1$/', $value)) {
                $quote = $value[0];

                $value = substr($value, 1, -1);

                if ('"' === $quote) {
                    $value = preg_replace_callback(
                        '/\\\\(\\\\|n|t|")/',
                        function (array $matches) {
                            return match ($matches[1]) {
                                '\\' => '\\',
                                'n' => "\n",
                                't' => "\t",
                                '"' => '"',
                            };
                        },
                        $value
                    );
                }
            }

            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    /**
     * @throws EnvException
     */
    public function getEnv(string $key): string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;

        if (!is_string($value)) {
            throw new EnvException('Failed to get the environment variable.');
        }

        return $value;
    }

    public function getEnvOrDefault(string $key, string $default): string
    {
        try {
            return $this->getEnv($key);
        } catch (EnvException $exception) {
            return $default;
        }
    }
}
