<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_phpunit_cannot_select_the_working_sqlite_file(): void
    {
        $configured = (string) config('database.connections.sqlite.database');
        $working = database_path('database.sqlite');

        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', $configured);
        $this->assertNotSame($working, $configured);
        $this->assertTrue(in_array(config('database.connections.sqlite.url'), [null, ''], true));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('array', config('session.driver'));
    }
}
