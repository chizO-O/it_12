<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Automatically groups and arranges lots by their block and natural lot number.
 * This is a schematic ordering, never a survey or a physical lot location.
 * Existing legacy grid_row/grid_column values are intentionally ignored.
 */
class LotGrid
{
    public static function blockKey(?string $block): string
    {
        $normalized = mb_strtolower(trim($block ?? ''));

        return $normalized === '' ? 'unassigned' : 'block:'.$normalized;
    }

    public static function compareBlockKeys(string $left, string $right): int
    {
        if ($left === 'unassigned') {
            return $right === 'unassigned' ? 0 : 1;
        }

        if ($right === 'unassigned') {
            return -1;
        }

        return strnatcasecmp(substr($left, 6), substr($right, 6));
    }

    /**
     * @param  Collection<int, \App\Models\Lot>  $lots All lots of the property, regardless of status/page
     * @return array{positions: array<int, array{row: int, column: int}>, blocks: array<string, array{columns: int, rows: int}>}
     */
    public static function build(Collection $lots): array
    {
        $result = ['positions' => [], 'blocks' => []];

        $blocks = $lots->groupBy(fn ($lot) => self::blockKey($lot->block_no))
            ->sortKeysUsing([self::class, 'compareBlockKeys']);

        foreach ($blocks as $block => $blockLots) {
            // Stable natural-number sorting, independent of insertion order, status and pagination.
            $sortedLots = $blockLots->sort(function ($first, $second): int {
                $numberOrder = strnatcasecmp(trim($first->lot_no), trim($second->lot_no));

                return $numberOrder !== 0 ? $numberOrder : ($first->id <=> $second->id);
            })->values();

            // Compact layout: a small block is not forced into six empty columns.
            $count = $sortedLots->count();
            // Never allocate more columns than there are real lots. In particular,
            // a one-lot block must occupy exactly one column, not two.
            $columns = min($count, 10, max(1, (int) ceil(sqrt($count * 1.6))));
            $rows = (int) ceil($count / $columns);

            foreach ($sortedLots as $index => $lot) {
                $row = intdiv($index, $columns);
                $offset = $index % $columns;
                // Snake rows make sequential lot numbers visually adjacent at row turns.
                $column = $row % 2 === 0 ? $offset : $columns - 1 - $offset;

                $result['positions'][$lot->id] = ['row' => $row, 'column' => $column];
            }

            $result['blocks'][$block] = ['columns' => $columns, 'rows' => $rows];
        }

        return $result;
    }
}
