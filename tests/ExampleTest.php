<?php

declare(strict_types=1);

namespace Zing\Skeleton\Tests;

use Zing\Skeleton\Example;

class ExampleTest extends TestCase
{
    public function testFoo(): void
    {
        self::assertTrue(class_exists(Example::class));
        self::assertTrue(method_exists(Example::class, 'foo'));
        self::assertTrue((new Example())->foo());
    }
}
