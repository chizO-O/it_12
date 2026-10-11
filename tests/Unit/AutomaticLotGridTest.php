<?php

namespace Tests\Unit;

use App\Models\Lot;
use App\Support\LotGrid;
use PHPUnit\Framework\TestCase;

class AutomaticLotGridTest extends TestCase
{
    private function lot(int $id, string $block, string $number, ?int $oldRow = null, ?int $oldColumn = null): Lot
    {
        $lot = new Lot(['block_no' => $block, 'lot_no' => $number]);
        $lot->id = $id;
        $lot->grid_row = $oldRow;
        $lot->grid_column = $oldColumn;

        return $lot;
    }

    public function test_lots_are_auto_arranged_naturally_per_block_regardless_of_insert_order(): void
    {
        $lots = collect([
            $this->lot(1, 'Block-X', 'Lot 10', 4, 7),
            $this->lot(2, 'Block-X', 'Lot 2', 8, 9),
            $this->lot(3, 'Block-X', 'Lot 1'),
            $this->lot(4, 'Block-Y', 'Lot 1'),
        ]);

        $layout = LotGrid::build($lots);
        $this->assertSame(['row' => 0, 'column' => 0], $layout['positions'][3]);
        $this->assertSame(['row' => 0, 'column' => 1], $layout['positions'][2]);
        $this->assertSame(['row' => 0, 'column' => 2], $layout['positions'][1]);
        $this->assertSame(['row' => 0, 'column' => 0], $layout['positions'][4]);
        $this->assertSame($layout, LotGrid::build($lots->reverse()->values()));
    }

    public function test_small_blocks_are_not_forced_into_six_columns(): void
    {
        $one = LotGrid::build(collect([$this->lot(1, '2', '1')]));
        $this->assertSame(['columns' => 1, 'rows' => 1], $one['blocks']['block:2']);

        $two = LotGrid::build(collect([$this->lot(2, '2', '2'), $this->lot(1, '2', '1')]));
        $this->assertSame(['columns' => 2, 'rows' => 1], $two['blocks']['block:2']);
    }

    public function test_block_numbers_sort_naturally_and_unassigned_blocks_are_last(): void
    {
        $keys = collect([
            LotGrid::blockKey('10') => true,
            LotGrid::blockKey(null) => true,
            LotGrid::blockKey('2') => true,
        ])->sortKeysUsing([LotGrid::class, 'compareBlockKeys'])->keys()->all();

        $this->assertSame(['block:2', 'block:10', 'unassigned'], $keys);
    }
}
