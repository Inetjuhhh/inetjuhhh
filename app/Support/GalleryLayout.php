<?php

namespace App\Support;

/**
 * Lays out a photo gallery in "mixed rows": every row puts a landscape photo next to one or two
 * portrait photos, all at the same height, so nothing is cropped and the gallery doesn't end up
 * with a block of only portraits or only landscapes. Landscapes keep their order among
 * themselves, and so do portraits.
 */
class GalleryLayout
{
    /**
     * @template T of array{ratio: float}
     *
     * @param  list<T>  $photos  each with its width / height
     * @param  int  $portraitsPerRow  2 on a laptop, 1 on a phone
     * @return list<list<T>>
     */
    public static function rows(array $photos, int $portraitsPerRow = 2): array
    {
        $landscapes = array_values(array_filter($photos, fn (array $photo) => $photo['ratio'] >= 1));
        $portraits = array_values(array_filter($photos, fn (array $photo) => $photo['ratio'] < 1));
        $rows = [];

        while ($landscapes || $portraits) {
            if ($landscapes && $portraits) {
                // A landscape with its fair share of the portraits that are left
                $share = min($portraitsPerRow, (int) ceil(count($portraits) / count($landscapes)));
                $row = [array_shift($landscapes), ...array_splice($portraits, 0, $share)];
            } elseif ($landscapes) {
                $row = array_splice($landscapes, 0, $portraitsPerRow > 1 ? 2 : 1);
            } else {
                $row = array_splice($portraits, 0, $portraitsPerRow > 1 ? 3 : 2);
            }

            // Every other row the other way round, so the landscapes zigzag
            $rows[] = count($rows) % 2 ? array_reverse($row) : $row;
        }

        return $rows;
    }

    /**
     * The widths of a row side by side: a row's photos share its width in proportion to their shape,
     * which makes them all the same height.
     */
    public static function ratioSum(array $row): float
    {
        return array_sum(array_column($row, 'ratio'));
    }
}
