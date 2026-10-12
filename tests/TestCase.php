<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Luôn dùng SQLite trong bộ nhớ khi chạy test.
     *
     * Máy có thể đang đặt biến DB_CONNECTION ở cấp hệ điều hành cho MySQL
     * hoặc SQL Server. Khai báo tại đây giúp test không truy cập nhầm vào
     * cơ sở dữ liệu thật của dự án.
     */
    public function createApplication()
    {
        foreach ([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
