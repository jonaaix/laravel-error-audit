<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Tests;

use Aaix\LaravelErrorAudit\ErrorAuditServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
   protected function getPackageProviders($app): array
   {
      return [ErrorAuditServiceProvider::class];
   }

   protected function defineEnvironment($app): void
   {
      $app['config']->set('app.name', 'Acme IMS');
      $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
      $app['config']->set('cache.default', 'array');
      $app['config']->set('error-audit.ai.enabled', false);
      $app['config']->set('error-audit.preview.enabled', true);
   }
}
