<?php

namespace Tests;

use Env\Exceptions\EnvException;
use Env\Services\EnvService;
use Env\Services\EnvServiceInterface;
use Fs\Exceptions\FsException;
use Fs\Services\FsServiceInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\MockObject;

class EnvServiceTest extends TestCase
{
    protected MockObject&FsServiceInterface $fsService;

    protected EnvServiceInterface $envService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fsService = $this->getMockBuilder(FsServiceInterface::class)->getMock();

        $this->envService = new EnvService($this->fsService);
    }

    #[DataProvider('dataProviderLoadDotEnv')]
    #[RunInSeparateProcess]
    public function testLoadDotEnv(
        string $content,
        string $key,
        string $expectedValue,
    ): void {
        $path = '.env';

        $this->fsService->expects($this->once())
            ->method('readFile')
            ->with($path)
            ->willReturn($content);

        $this->envService->loadDotEnv($path);

        $this->assertEquals($expectedValue, $_SERVER[$key]);
        $this->assertEquals($expectedValue, $_ENV[$key]);
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function dataProviderLoadDotEnv(): array
    {
        return [
            [
                'content' => 'a=',
                'key' => 'a',
                'expectedValue' => '',
            ],
            [
                'content' => 'a=""',
                'key' => 'a',
                'expectedValue' => '',
            ],
            [
                'content' => 'a=\'\'',
                'key' => 'a',
                'expectedValue' => '',
            ],
            [
                'content' => 'a=a',
                'key' => 'a',
                'expectedValue' => 'a',
            ],
            [
                'content' => 'a="a"',
                'key' => 'a',
                'expectedValue' => 'a',
            ],
            [
                'content' => 'a=\'a\'',
                'key' => 'a',
                'expectedValue' => 'a',
            ],
            [
                'content' => 'a="a\\\\a"',
                'key' => 'a',
                'expectedValue' => 'a\\a',
            ],
            [
                'content' => 'a="a\\na"',
                'key' => 'a',
                'expectedValue' => "a\na",
            ],
            [
                'content' => 'a="a\\ta"',
                'key' => 'a',
                'expectedValue' => "a\ta",
            ],
            [
                'content' => 'a="a\\"a"',
                'key' => 'a',
                'expectedValue' => 'a"a',
            ],
            [
                'content' => 'a="a\\\\na"',
                'key' => 'a',
                'expectedValue' => 'a\\na',
            ],
            [
                'content' => 'a="a\\\\ta"',
                'key' => 'a',
                'expectedValue' => 'a\\ta',
            ],
            [
                'content' => 'a="a\\\\"a"',
                'key' => 'a',
                'expectedValue' => 'a\\"a',
            ],
            [
                'content' => 'a=\'a\\\\a\'',
                'key' => 'a',
                'expectedValue' => 'a\\\\a',
            ],
            [
                'content' => 'a=\'a\\na\'',
                'key' => 'a',
                'expectedValue' => 'a\\na',
            ],
            [
                'content' => 'a=\'a\\ta\'',
                'key' => 'a',
                'expectedValue' => 'a\\ta',
            ],
            [
                'content' => 'a=\'a\\"a\'',
                'key' => 'a',
                'expectedValue' => 'a\\"a',
            ],
        ];
    }

    #[DataProvider('dataProviderLoadDotEnvSkipRows')]
    #[RunInSeparateProcess]
    public function testLoadDotEnvSkipRows(
        string $content,
        string $key,
    ): void {
        $path = '.env';

        $this->fsService->expects($this->once())
            ->method('readFile')
            ->with($path)
            ->willReturn($content);

        $this->envService->loadDotEnv($path);

        $this->assertArrayNotHasKey($key, $_SERVER);
        $this->assertArrayNotHasKey($key, $_ENV);
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function dataProviderLoadDotEnvSkipRows(): array
    {
        return [
            [
                'content' => '',
                'key' => 'a',
            ],
            [
                'content' => 'a',
                'key' => 'a',
            ],
            [
                'content' => '#a=a',
                'key' => 'a',
            ],
            [
                'content' => '=a',
                'key' => '',
            ],
            [
                'content' => 'a a=a',
                'key' => 'a a',
            ],
        ];
    }

    #[DataProvider('dataProviderLoadDotEnvAlreadySet')]
    #[RunInSeparateProcess]
    public function testLoadDotEnvAlreadySet(
        bool $setServerValue,
        bool $setEnvValue,
    ): void {
        $key = $this->fakerService->getDataTypeGenerator()->randomString();
        $oldValue = $this->fakerService->getDataTypeGenerator()->randomString();
        $newValue = $this->fakerService->getDataTypeGenerator()->randomString();

        if ($setServerValue) {
            $_SERVER[$key] = $oldValue;
        }

        if ($setEnvValue) {
            $_ENV[$key] = $oldValue;
        }

        $path = '.env';

        $content = sprintf('%s=%s', $key, $newValue);

        $this->fsService->expects($this->once())
            ->method('readFile')
            ->with($path)
            ->willReturn($content);

        $this->envService->loadDotEnv($path);

        if ($setServerValue && $setEnvValue) {
            $this->assertEquals($oldValue, $_SERVER[$key]);
            $this->assertEquals($oldValue, $_ENV[$key]);
        } elseif ($setServerValue) {
            $this->assertEquals($oldValue, $_SERVER[$key]);
            $this->assertArrayNotHasKey($key, $_ENV);
        } elseif ($setEnvValue) {
            $this->assertArrayNotHasKey($key, $_SERVER);
            $this->assertEquals($oldValue, $_ENV[$key]);
        }
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderLoadDotEnvAlreadySet(): array
    {
        return [
            [
                'setServerValue' => true,
                'setEnvValue' => true,
            ],
            [
                'setServerValue' => true,
                'setEnvValue' => false,
            ],
            [
                'setServerValue' => false,
                'setEnvValue' => true,
            ],
        ];
    }

    #[RunInSeparateProcess]
    public function testLoadDotEnvFsServiceReadFileThrowsException(): void
    {
        $this->expectException(EnvException::class);
        $this->expectExceptionMessage('nope.');

        $this->fsService->expects($this->once())
            ->method('readFile')
            ->willThrowException(new FsException('nope.'));

        $this->envService->loadDotEnv('.env');
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('dataProviderGetEnv')]
    #[RunInSeparateProcess]
    public function testGetEnv(
        bool $setServerValue,
        ?string $serverValue,
        bool $setEnvValue,
        ?string $envValue,
        string $expectedValue,
    ): void {
        $key = $this->fakerService->getDataTypeGenerator()->randomString();

        if ($setServerValue) {
            $_SERVER[$key] = $serverValue;
        }

        if ($setEnvValue) {
            $_ENV[$key] = $envValue;
        }

        $value = $this->envService->getEnv($key);

        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderGetEnv(): array
    {
        return [
            [
                'setServerValue' => true,
                'serverValue' => 'a',
                'setEnvValue' => true,
                'envValue' => 'b',
                'expectedValue' => 'a',
            ],
            [
                'setServerValue' => true,
                'serverValue' => 'a',
                'setEnvValue' => false,
                'envValue' => 'b',
                'expectedValue' => 'a',
            ],
            [
                'setServerValue' => false,
                'serverValue' => 'a',
                'setEnvValue' => true,
                'envValue' => 'b',
                'expectedValue' => 'b',
            ],
            [
                'setServerValue' => true,
                'serverValue' => 'a',
                'setEnvValue' => true,
                'envValue' => null,
                'expectedValue' => 'a',
            ],
            [
                'setServerValue' => true,
                'serverValue' => null,
                'setEnvValue' => true,
                'envValue' => 'b',
                'expectedValue' => 'b',
            ],
        ];
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('dataProviderGetEnvFailedToGetEnvironmentVariable')]
    #[RunInSeparateProcess]
    public function testGetEnvFailedToGetEnvironmentVariable(
        bool $setServerValue,
        ?string $serverValue,
        bool $setEnvValue,
        ?string $envValue,
    ): void {
        $this->expectException(EnvException::class);
        $this->expectExceptionMessage('Failed to get the environment variable.');

        $key = $this->fakerService->getDataTypeGenerator()->randomString();

        if ($setServerValue) {
            $_SERVER[$key] = $serverValue;
        }

        if ($setEnvValue) {
            $_ENV[$key] = $envValue;
        }

        $this->envService->getEnv($key);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderGetEnvFailedToGetEnvironmentVariable(): array
    {
        return [
            [
                'setServerValue' => false,
                'serverValue' => 'a',
                'setEnvValue' => false,
                'envValue' => 'b',
            ],
            [
                'setServerValue' => true,
                'serverValue' => null,
                'setEnvValue' => true,
                'envValue' => null,
            ],
        ];
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('dataProviderGetEnvOrDefault')]
    #[RunInSeparateProcess]
    public function testGetEnvOrDefault(
        bool $setServerValue,
        ?string $serverValue,
        bool $setEnvValue,
        ?string $envValue,
        string $defaultValue,
        string $expectedValue,
    ): void {
        $key = $this->fakerService->getDataTypeGenerator()->randomString();

        if ($setServerValue) {
            $_SERVER[$key] = $serverValue;
        }

        if ($setEnvValue) {
            $_ENV[$key] = $envValue;
        }

        $value = $this->envService->getEnvOrDefault($key, $defaultValue);

        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderGetEnvOrDefault(): array
    {
        return [
            [
                'setServerValue' => true,
                'serverValue' => 'a',
                'setEnvValue' => true,
                'envValue' => 'b',
                'defaultValue' => 'c',
                'expectedValue' => 'a',
            ],
            [
                'setServerValue' => true,
                'serverValue' => 'a',
                'setEnvValue' => false,
                'envValue' => 'b',
                'defaultValue' => 'c',
                'expectedValue' => 'a',
            ],
            [
                'setServerValue' => false,
                'serverValue' => 'a',
                'setEnvValue' => true,
                'envValue' => 'b',
                'defaultValue' => 'c',
                'expectedValue' => 'b',
            ],
            [
                'setServerValue' => false,
                'serverValue' => 'a',
                'setEnvValue' => false,
                'envValue' => 'b',
                'defaultValue' => 'c',
                'expectedValue' => 'c',
            ],
            [
                'setServerValue' => true,
                'serverValue' => 'a',
                'setEnvValue' => true,
                'envValue' => null,
                'defaultValue' => 'c',
                'expectedValue' => 'a',
            ],
            [
                'setServerValue' => true,
                'serverValue' => null,
                'setEnvValue' => true,
                'envValue' => 'b',
                'defaultValue' => 'c',
                'expectedValue' => 'b',
            ],
            [
                'setServerValue' => true,
                'serverValue' => null,
                'setEnvValue' => true,
                'envValue' => null,
                'defaultValue' => 'c',
                'expectedValue' => 'c',
            ],
        ];
    }
}
