<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit resolveUsing(string $key, \Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit recipientsUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit notifiableUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit channelsUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit senderUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit mailerUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit providerUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit modelUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit periodUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit minimumLevelUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit contextLevelUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit redactUsing(\Closure $resolver)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit filterIssues(\Closure $filter)
 * @method static \Aaix\LaravelErrorAudit\ErrorAudit sending(\Closure $callback)
 * @method static mixed value(string $key, mixed $default = null)
 *
 * @see \Aaix\LaravelErrorAudit\ErrorAudit
 */
class ErrorAudit extends Facade
{
   protected static function getFacadeAccessor(): string
   {
      return \Aaix\LaravelErrorAudit\ErrorAudit::class;
   }
}
