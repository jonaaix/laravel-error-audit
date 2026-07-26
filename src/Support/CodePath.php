<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Support;

class CodePath
{
   /**
    * Shorten a repository relative path from the middle, keeping the leading
    * directory and the file itself.
    *
    * Done here rather than with CSS ellipsis because text-overflow is not
    * reliable in Outlook, and a path that silently loses its filename is worse
    * than one that never showed it.
    */
   public static function shorten(?string $frame, int $maxLength = 52): ?string
   {
      if ($frame === null || $frame === '') {
         return null;
      }

      $path = trim(explode(' ', $frame)[0]);

      if (mb_strlen($path) <= $maxLength) {
         return $path;
      }

      $segments = explode('/', $path);

      if (count($segments) <= 3) {
         return $path;
      }

      $head = $segments[0];
      $tail = array_slice($segments, -2);

      $shortened = $head.'/…/'.implode('/', $tail);

      return mb_strlen($shortened) <= mb_strlen($path) ? $shortened : $path;
   }
}
