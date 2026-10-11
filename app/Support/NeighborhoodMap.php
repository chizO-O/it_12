<?php

namespace App\Support;

/**
 * Compact stacked-neighborhood schematic (NOT a surveyed subdivision plan).
 *
 * All real blocks are arranged in natural numerical order within one SVG.
 * One shared road separates each adjacent pair of rounded lot blocks; trees
 * decorate the margins. Never invent lots or infer physical coordinates from
 * block/lot numbers. The source grid comes from ALL property lots, so a status
 * filter or pagination does not change parcel positions.
 */
final class NeighborhoodMap
{
    private const CELL_HEIGHT = 92;
    private const CELL_WIDTH = 94;
    private const TOP_PADDING = 51;
    private const BOTTOM_PADDING = 31;
    private const SIDE_PADDING = 43;
    private const BLOCK_GAP = 80;

    /**
     * @param array{positions:array<int,array{row:int,column:int}>,blocks:array<string,array{columns:int,rows:int}>} $grid
     * @return array{width:int,height:int,blocks:array<string,array<string,int>>,roads:array<int,array{path:string,label:string,label_x:float,label_y:float}>,trees:array<int,array{x:float,y:float,r:int}>}
     */
    public static function build(array $grid): array
    {
        $entries = $grid['blocks'] ?? [];
        uksort($entries, [LotGrid::class, 'compareBlockKeys']);
        $maxColumns = 0;
        foreach ($entries as $block) {
            $maxColumns = max($maxColumns, (int) ($block['columns'] ?? 1));
        }

        // Compact for one or two lots, but able to grow for dense blocks.
        $width = (int) max(440, min(1320, $maxColumns * self::CELL_WIDTH + 178));
        $blocks = [];
        $roads = [];
        $trees = [];
        $cursorY = 38;
        $keys = array_keys($entries);
        $count = count($keys);

        foreach ($keys as $index => $key) {
            $meta = $entries[$key];
            $columns = max(1, (int) ($meta['columns'] ?? 1));
            $rows = max(1, (int) ($meta['rows'] ?? 1));
            $cellWidth = min(self::CELL_WIDTH, ($width - 148) / $columns);
            $innerWidth = $columns * $cellWidth;
            $capsuleWidth = $innerWidth + self::SIDE_PADDING * 2;
            $capsuleHeight = $rows * self::CELL_HEIGHT + self::TOP_PADDING + self::BOTTOM_PADDING;
            $capsuleX = ($width - $capsuleWidth) / 2;
            $parcelX = $capsuleX + self::SIDE_PADDING;
            $parcelY = $cursorY + self::TOP_PADDING;

            $blocks[$key] = [
                'x' => (int) round($parcelX),
                'y' => (int) round($parcelY),
                'width' => (int) round($innerWidth),
                'height' => $rows * self::CELL_HEIGHT,
                'capsule_x' => (int) round($capsuleX),
                'capsule_y' => $cursorY,
                'capsule_width' => (int) round($capsuleWidth),
                'capsule_height' => $capsuleHeight,
                'label_x' => (int) round($width / 2),
                'label_y' => $cursorY + 31,
                'columns' => $columns,
                'rows' => $rows,
                // Kept for callers that already inspect this metadata; all
                // blocks occupy one vertically stacked column.
                'side' => 0,
            ];

            // Trees surround the capsules, not the parcel polygons.
            $treeRows = max(2, (int) ceil($capsuleHeight / 76));
            for ($t = 0; $t <= $treeRows; $t++) {
                $treeY = $cursorY + 25 + ($capsuleHeight - 50) * $t / $treeRows;
                foreach ([$capsuleX - 17, $capsuleX + $capsuleWidth + 17] as $treeX) {
                    if ($treeX > 9 && $treeX < $width - 9) {
                        $trees[] = ['x' => round($treeX, 1), 'y' => round($treeY, 1), 'r' => 8];
                    }
                }
            }

            $endY = $cursorY + $capsuleHeight;
            // A few trees at the outside ends tie the entire site together;
            // none should appear between a parcel and its label.
            if ($index === 0 || $index === $count - 1) {
                $outerTreeY = $index === 0 ? $cursorY - 16 : $endY + 16;
                for ($x = $capsuleX + 30; $x < $capsuleX + $capsuleWidth - 14; $x += 76) {
                    $trees[] = ['x' => round($x, 1), 'y' => (float) $outerTreeY, 'r' => 7];
                }
            }
            if ($index < $count - 1) {
                // One and only one shared lane between adjacent oval blocks.
                $roadY = $endY + self::BLOCK_GAP / 2;
                $roads[] = [
                    'path' => sprintf('M -30 %.1f Q %.1f %.1f %.1f %.1f T %.1f %.1f',
                        $roadY + 2, $width * .25, $roadY - 4,
                        $width * .5, $roadY, $width + 30, $roadY + 2),
                    'label' => 'ILLUSTRATIVE LANE',
                    'label_x' => (float) ($width / 2),
                    'label_y' => (float) ($roadY + 4),
                ];
                for ($x = 42; $x < $width - 24; $x += 79) {
                    if (abs($x - $width / 2) < 126) {
                        continue; // Road name remains readable.
                    }
                    $trees[] = ['x' => (float) $x, 'y' => (float) ($roadY - 29), 'r' => 7];
                    $trees[] = ['x' => (float) ($x + 17), 'y' => (float) ($roadY + 29), 'r' => 7];
                }
                $cursorY = $endY + self::BLOCK_GAP;
            } else {
                $cursorY = $endY;
            }
        }

        $height = max(250, $cursorY + 38);
        return compact('width', 'height', 'blocks', 'roads', 'trees');
    }

    /**
     * Draw an actual recorded lot in its assigned schematic cell.
     *
     * @param array<string,int> $block
     * @param array{row:int,column:int} $position
     * @return array{points:string,x:float,y:float,angle:int,font:int,row:int,column:int}
     */
    public static function parcel(array $block, array $position): array
    {
        $columns = max(1, (int) $block['columns']);
        $cellWidth = (float) $block['width'] / $columns;
        $cellHeight = (float) self::CELL_HEIGHT;
        $row = max(0, (int) $position['row']);
        $column = max(0, (int) $position['column']);
        $x = (float) $block['x'] + $cellWidth * $column;
        $y = (float) $block['y'] + $cellHeight * $row;

        // A small consistent taper adds a schematic feel without changing
        // identity, sequence or geometry when lots change status.
        $slant = min(7.0, $cellWidth * .08) * (($row + $column) % 2 === 0 ? 1 : -1);
        $topLeft = $x + max(0, $slant);
        $bottomLeft = $x + max(0, -$slant);
        $points = sprintf('%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f',
            $topLeft, $y,
            $topLeft + $cellWidth - abs($slant), $y,
            $bottomLeft + $cellWidth - abs($slant), $y + $cellHeight,
            $bottomLeft, $y + $cellHeight);

        return [
            'points' => $points,
            'x' => round($x + $cellWidth / 2, 2),
            'y' => round($y + $cellHeight / 2, 2),
            'angle' => $cellWidth < 84 ? -90 : 0,
            'font' => $cellWidth < 55 ? 10 : 12,
            'row' => $row,
            'column' => $column,
        ];
    }
}
