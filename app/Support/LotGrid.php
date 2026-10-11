<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Produces stable schematic positions from the property's entire lot inventory.
 * Explicit positions are stored on the lot; unpositioned lots fill the unused cells.
 * Positions do not describe surveyed land boundaries.
 */
class LotGrid
{
    public static function blockKey(?string $block): string
    {
        $normalized = mb_strtolower(trim($block ?? ''));

        return $normalized === '' ? 'unassigned' : 'block:'.$normalized;
    }

    /**
     * @param  Collection<int, \App\Models\Lot>  $lots  Every lot in the property, regardless of status/page
     * @return array{positions: array<int, array{row: int, column: int}>, blocks: array<string, array{columns: int, rows: int}>}
     */
    public static function build(Collection $lots): array
    {
        $result = ['positions' => [], 'blocks' => []];

        foreach ($lots->groupBy(fn ($lot) => self::blockKey($lot->block_no)) as $block => $blockLots) {
            $columns = min(12, max(6, (int) ceil($blockLots->count() / 2), (int) $blockLots->max('grid_column')));
            $occupied = [];
            $maxRow = 0;

            // Reserve manually assigned cells before placing automatically arranged lots.
            foreach ($blockLots as $lot) {
                if ($lot->grid_row === null || $lot->grid_column === null) {
                    continue;
                }

                $row = (int) $lot->grid_row - 1;
                $column = (int) $lot->grid_column - 1;
                $occupied[$row.':'.$column] = true;
                $result['positions'][$lot->id] = ['row' => $row, 'column' => $column];
                $maxRow = max($maxRow, $row);
            }

            $slot = 0;
            foreach ($blockLots as $lot) {
                if (isset($result['positions'][$lot->id])) {
                    continue;
                }

                while (isset($occupied[intdiv($slot, $columns).':'.($slot % $columns)])) {
                    $slot++;
                }

                $row = intdiv($slot, $columns);
                $column = $slot % $columns;
                $occupied[$row.':'.$column] = true;
                $result['positions'][$lot->id] = ['row' => $row, 'column' => $column];
                $maxRow = max($maxRow, $row);
                $slot++;
            }

            $result['blocks'][$block] = ['columns' => $columns, 'rows' => $maxRow + 1];
        }

        return $result;
    }
}
