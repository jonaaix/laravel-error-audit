<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Charts;

use Aaix\LaravelErrorAudit\Data\ChartSeries;
use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\TimelineBucket;

/**
 * Turns the grouped issues into one stack segment per issue type.
 *
 * Bar height stays the occurrence count; the number of segments in it is the
 * number of distinct issue types active in that bucket. That difference is the
 * point of the chart: three hundred errors from one type is a runaway loop,
 * three hundred from eleven types is an application on the floor.
 *
 * Segments stay within their level's hue rather than taking a colour of their
 * own — red and amber already mean something here, and a fresh hue per type
 * would claim an identity the reader cannot look up.
 */
class TimelineSeriesBuilder
{
   private const ERROR_SHADES = ['#b91c1c', '#d03b3b', '#e05c5c', '#ea8080', '#f0a0a0'];

   private const WARNING_SHADES = ['#a3760f', '#c28f14', '#d4a520', '#e0bc55', '#ead89a'];

   public function __construct(private readonly int $maxSegmentsPerLevel = 5) {}

   /**
    * @param  array<string, IssueGroup>  $groups
    * @param  list<TimelineBucket>  $timeline
    * @return list<ChartSeries>
    */
   public function build(array $groups, array $timeline): array
   {
      return [
         ...$this->seriesFor($groups, $timeline, errors: true),
         ...$this->seriesFor($groups, $timeline, errors: false),
      ];
   }

   /**
    * @param  array<string, IssueGroup>  $groups
    * @param  list<TimelineBucket>  $timeline
    * @return list<ChartSeries>
    */
   private function seriesFor(array $groups, array $timeline, bool $errors): array
   {
      $matching = array_filter(
         $groups,
         fn (IssueGroup $group): bool => $group->level->isError() === $errors,
      );

      if ($matching === []) {
         return [];
      }

      usort($matching, fn (IssueGroup $a, IssueGroup $b): int => $b->count() <=> $a->count());

      $shades = $errors ? self::ERROR_SHADES : self::WARNING_SHADES;
      $stack = $errors ? 'errors' : 'warnings';

      $leading = array_slice($matching, 0, $this->maxSegmentsPerLevel);
      $remainder = array_slice($matching, $this->maxSegmentsPerLevel);

      $series = [];

      foreach ($leading as $index => $group) {
         $series[] = new ChartSeries(
            label: $group->title(),
            stack: $stack,
            color: $shades[$index % count($shades)],
            data: $this->countsAcross($group, $timeline),
         );
      }

      if ($remainder !== []) {
         $series[] = new ChartSeries(
            label: __('Other'),
            stack: $stack,
            color: $shades[count($shades) - 1],
            data: $this->combinedCountsAcross($remainder, $timeline),
         );
      }

      return $series;
   }

   /**
    * @param  list<TimelineBucket>  $timeline
    * @return list<int>
    */
   private function countsAcross(IssueGroup $group, array $timeline): array
   {
      return array_map(
         fn (TimelineBucket $bucket): int => $group->countInBucket($bucket->key),
         $timeline,
      );
   }

   /**
    * @param  list<IssueGroup>  $groups
    * @param  list<TimelineBucket>  $timeline
    * @return list<int>
    */
   private function combinedCountsAcross(array $groups, array $timeline): array
   {
      return array_map(
         function (TimelineBucket $bucket) use ($groups): int {
            $total = 0;

            foreach ($groups as $group) {
               $total += $group->countInBucket($bucket->key);
            }

            return $total;
         },
         $timeline,
      );
   }
}
