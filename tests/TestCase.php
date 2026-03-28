<?php

declare(strict_types=1);

namespace Micilini\VideoStream\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static string $fixturesPath;
    protected static string $tmpPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = __DIR__ . '/fixtures';
        self::$tmpPath = sys_get_temp_dir() . '/video-stream-tests-' . uniqid();

        if (!is_dir(self::$tmpPath)) {
            mkdir(self::$tmpPath, 0775, true);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$tmpPath)) {
            $files = glob(self::$tmpPath . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir(self::$tmpPath);
        }
    }

    protected function fixturePath(string $filename): string
    {
        return self::$fixturesPath . DIRECTORY_SEPARATOR . $filename;
    }

    protected function tmpPath(?string $filename = null): string
    {
        if ($filename === null) {
            return self::$tmpPath;
        }
        return self::$tmpPath . DIRECTORY_SEPARATOR . $filename;
    }
}
