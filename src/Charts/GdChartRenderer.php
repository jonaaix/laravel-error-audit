<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Charts;

use Aaix\LaravelErrorAudit\Contracts\ChartRenderer;
use Aaix\LaravelErrorAudit\Data\ChartSeries;
use Aaix\LaravelErrorAudit\Data\TimelineBucket;
use GdImage;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Draws the timeline straight to a PNG with ext-gd.
 *
 * This is the default because it is the only option that assumes nothing about
 * the machine the report runs on. Headless Chrome may be absent; every pure PHP
 * charting library on Packagist is either GPL or sold under a commercial
 * licence, neither of which an MIT package can pull in. So the drawing happens
 * here, limited to the one chart this report actually needs.
 */
class GdChartRenderer implements ChartRenderer
{
   private const SCALE = 2;

   private const PADDING_LEFT = 42;

   private const PADDING_RIGHT = 42;

   private const ERROR_AXIS_COLOUR = '#b91c1c';

   private const WARNING_AXIS_COLOUR = '#b45309';

   private const PADDING_TOP = 8;

   private const PADDING_BOTTOM = 24;

   private const GROUP_GAP = 2;

   private const SEGMENT_GAP = 2;

   // Share of each time slot the bar group fills; the rest is the gap between slots.
   private const SLOT_FILL = 0.82;

   private const AXIS_STEPS = 3;

   private const FONT_PIXELS = 11;

   public function __construct(
      private readonly LoggerInterface $logger,
      private readonly int $width = 600,
      private readonly int $height = 200,
      private readonly ?string $fontPath = null,
   ) {}

   public function render(array $timeline, array $series): ?string
   {
      if ($timeline === [] || $series === [] || ! extension_loaded('gd')) {
         return null;
      }

      $maxima = [
         'errors' => $this->maximumFor($timeline, $series, 'errors'),
         'warnings' => $this->maximumFor($timeline, $series, 'warnings'),
      ];

      if (max($maxima) <= 0) {
         return null;
      }

      try {
         return $this->draw($timeline, $series, $maxima);
      } catch (Throwable $exception) {
         $this->logger->warning('Error audit: chart rendering failed.', [
            'exception' => $exception->getMessage(),
         ]);

         return null;
      }
   }

   /**
    * @param  list<TimelineBucket>  $timeline
    * @param  list<ChartSeries>  $series
    * @param  array{errors: int, warnings: int}  $maxima
    */
   private function draw(array $timeline, array $series, array $maxima): string
   {
      $width = $this->width * self::SCALE;
      $height = $this->height * self::SCALE;

      $canvas = imagecreatetruecolor($width, $height);
      imagealphablending($canvas, true);
      imagefilledrectangle($canvas, 0, 0, $width, $height, $this->colour($canvas, '#ffffff'));

      $plotLeft = self::PADDING_LEFT * self::SCALE;
      $plotRight = $width - (self::PADDING_RIGHT * self::SCALE);
      $plotTop = self::PADDING_TOP * self::SCALE;
      $plotBottom = $height - (self::PADDING_BOTTOM * self::SCALE);

      // Errors and warnings live on separate axes: a quiet day with 49 errors
      // must not flatline under 7,400 warnings. Each level scales to its own
      // tallest stack — that stack IS the top of the chart on its axis.
      $ceilings = [
         'errors' => max($maxima['errors'], 1),
         'warnings' => max($maxima['warnings'], 1),
      ];

      $this->drawGrid($canvas, $plotLeft, $plotRight, $plotTop, $plotBottom, $ceilings, $maxima);
      $this->drawBars($canvas, $timeline, $series, $plotLeft, $plotRight, $plotTop, $plotBottom, $ceilings);
      $this->drawTimeAxis($canvas, $timeline, $plotLeft, $plotRight, $plotBottom);

      ob_start();
      imagepng($canvas, null, 9);
      $png = (string) ob_get_clean();

      imagedestroy($canvas);

      return $png;
   }

   /**
    * One shared set of grid lines, two value scales: the error axis on the
    * left in its own red, the warning axis on the right in amber, so the
    * colour of a number says which bars it measures.
    *
    * @param  array{errors: int, warnings: int}  $ceilings
    * @param  array{errors: int, warnings: int}  $maxima
    */
   private function drawGrid(
      GdImage $canvas,
      int $left,
      int $right,
      int $top,
      int $bottom,
      array $ceilings,
      array $maxima,
   ): void {
      $gridColour = $this->colour($canvas, '#f1f1f3');
      $errorColour = $this->colour($canvas, self::ERROR_AXIS_COLOUR);
      $warningColour = $this->colour($canvas, self::WARNING_AXIS_COLOUR);

      for ($step = 0; $step <= self::AXIS_STEPS; $step++) {
         $y = (int) round($bottom - (($bottom - $top) * ($step / self::AXIS_STEPS)));

         imagefilledrectangle($canvas, $left, $y, $right, $y + 1, $gridColour);

         if ($maxima['errors'] > 0) {
            $value = (int) round($ceilings['errors'] / self::AXIS_STEPS * $step);
            $this->text($canvas, number_format($value, 0, ',', '.'), $left - (7 * self::SCALE), $y + (4 * self::SCALE), $errorColour, alignRight: true);
         }

         if ($maxima['warnings'] > 0) {
            $value = (int) round($ceilings['warnings'] / self::AXIS_STEPS * $step);
            $this->text($canvas, number_format($value, 0, ',', '.'), $right + (7 * self::SCALE), $y + (4 * self::SCALE), $warningColour);
         }
      }
   }

   /**
    * @param  list<TimelineBucket>  $timeline
    * @param  list<ChartSeries>  $series
    * @param  array{errors: int, warnings: int}  $ceilings
    */
   private function drawBars(
      GdImage $canvas,
      array $timeline,
      array $series,
      int $left,
      int $right,
      int $top,
      int $bottom,
      array $ceilings,
   ): void {
      $slotWidth = ($right - $left) / max(count($timeline), 1);
      $stacks = ['errors', 'warnings'];
      $groupGap = self::GROUP_GAP * self::SCALE;
      $barWidth = max(self::SCALE, (int) floor(($slotWidth * self::SLOT_FILL - $groupGap) / count($stacks)));

      foreach ($timeline as $index => $bucket) {
         $slotCentre = $left + ($slotWidth * ($index + 0.5));
         $groupWidth = ($barWidth * count($stacks)) + $groupGap;
         $cursorX = (int) round($slotCentre - ($groupWidth / 2));

         foreach ($stacks as $stack) {
            $baseline = $bottom;

            foreach ($series as $item) {
               if ($item->stack !== $stack) {
                  continue;
               }

               $value = $item->data[$index] ?? 0;

               if ($value <= 0) {
                  continue;
               }

               $segmentHeight = max(
                  self::SCALE,
                  (int) round((($bottom - $top) * $value) / $ceilings[$stack]),
               );

               $segmentTop = max($top, $baseline - $segmentHeight);

               imagefilledrectangle(
                  $canvas,
                  $cursorX,
                  $segmentTop,
                  $cursorX + $barWidth - 1,
                  $baseline,
                  $this->colour($canvas, $item->color),
               );

               $baseline = $segmentTop - (self::SEGMENT_GAP * self::SCALE);

               if ($baseline <= $top) {
                  break;
               }
            }

            $cursorX += $barWidth + $groupGap;
         }
      }

      imagefilledrectangle($canvas, $left, $bottom, $right, $bottom + 1, $this->colour($canvas, '#d4d4d8'));
   }

   /**
    * @param  list<TimelineBucket>  $timeline
    */
   private function drawTimeAxis(GdImage $canvas, array $timeline, int $left, int $right, int $bottom): void
   {
      $labelColour = $this->colour($canvas, '#a1a1aa');
      $slotWidth = ($right - $left) / max(count($timeline), 1);

      $widest = 0;

      foreach ($timeline as $bucket) {
         $widest = max($widest, $this->measureText($bucket->label));
      }

      // Label every slot whose text fits without touching its neighbour. Short
      // hour labels fit each of 24 slots; longer date labels thin out on their own.
      $every = max(1, (int) ceil(($widest + (6 * self::SCALE)) / max($slotWidth, 1)));

      foreach ($timeline as $index => $bucket) {
         if ($index % $every !== 0) {
            continue;
         }

         $this->text(
            $canvas,
            $bucket->label,
            (int) round($left + ($slotWidth * ($index + 0.5))),
            $bottom + (15 * self::SCALE),
            $labelColour,
            alignCentre: true,
         );
      }
   }

   private function measureText(string $value): int
   {
      $font = $this->font();

      if ($font === null) {
         return strlen($value) * 6;
      }

      $box = imagettfbbox(self::FONT_PIXELS * self::SCALE * 0.75, 0, $font, $value);

      return (int) abs($box[2] - $box[0]);
   }

   private function text(
      GdImage $canvas,
      string $value,
      int $x,
      int $y,
      int $colour,
      bool $alignRight = false,
      bool $alignCentre = false,
   ): void {
      $size = self::FONT_PIXELS * self::SCALE * 0.75;
      $font = $this->font();

      if ($font === null) {
         imagestring($canvas, 2, $x, $y - 12, $value, $colour);

         return;
      }

      $box = imagettfbbox($size, 0, $font, $value);
      $textWidth = (int) abs($box[2] - $box[0]);

      if ($alignRight) {
         $x -= $textWidth;
      } elseif ($alignCentre) {
         $x -= (int) round($textWidth / 2);
      }

      imagettftext($canvas, $size, 0, $x, $y, $colour, $font, $value);
   }

   private function font(): ?string
   {
      $path = $this->fontPath ?? __DIR__.'/../../resources/fonts/open_sans.ttf';

      return is_file($path) && function_exists('imagettftext') ? $path : null;
   }

   /**
    * The tallest stacked bar of one level across the timeline.
    *
    * @param  list<TimelineBucket>  $timeline
    * @param  list<ChartSeries>  $series
    */
   private function maximumFor(array $timeline, array $series, string $stack): int
   {
      $maximum = 0;

      foreach (array_keys($timeline) as $index) {
         $total = 0;

         foreach ($series as $item) {
            if ($item->stack === $stack) {
               $total += $item->data[$index] ?? 0;
            }
         }

         $maximum = max($maximum, $total);
      }

      return $maximum;
   }

   private function colour(GdImage $canvas, string $hex): int
   {
      [$red, $green, $blue] = sscanf(ltrim($hex, '#'), '%2x%2x%2x') ?? [0, 0, 0];

      return imagecolorallocate($canvas, (int) $red, (int) $green, (int) $blue);
   }
}
