<?php

namespace Tests\Unit;

use App\Support\NeighborhoodMap;
use PHPUnit\Framework\TestCase;

class NeighborhoodMapTest extends TestCase
{
    private function grid(): array
    {
        return [
            'blocks' => [
                'block:10' => ['columns' => 2, 'rows' => 1],
                'block:2' => ['columns' => 2, 'rows' => 2],
                'block:3' => ['columns' => 1, 'rows' => 1],
            ],
            'positions' => [
                5 => ['row' => 0, 'column' => 0],
                6 => ['row' => 0, 'column' => 1],
            ],
        ];
    }

    public function test_blocks_stack_in_one_compact_canvas_with_only_shared_roads(): void
    {
        $map = NeighborhoodMap::build($this->grid());

        $this->assertGreaterThanOrEqual(440, $map['width']);
        $this->assertCount(3, $map['blocks']);
        $this->assertGreaterThan(500, $map['height']);
        $this->assertCount(2, $map['roads']); // One shared lane for each adjacent block pair.
        $this->assertNotEmpty($map['trees']);
        $this->assertSame(['block:2', 'block:3', 'block:10'], array_keys($map['blocks']));
        $this->assertSame(0, $map['blocks']['block:2']['side']);
        $this->assertSame(0, $map['blocks']['block:3']['side']);
        $this->assertGreaterThan($map['blocks']['block:2']['capsule_y'], $map['blocks']['block:3']['capsule_y']);
        $this->assertGreaterThan($map['blocks']['block:3']['capsule_y'], $map['blocks']['block:10']['capsule_y']);
    }

    public function test_single_block_does_not_expect_a_second_block_in_the_first_band(): void
    {
        $map = NeighborhoodMap::build([
            'blocks' => ['block:2' => ['columns' => 1, 'rows' => 1]],
            'positions' => [],
        ]);

        $this->assertCount(1, $map['blocks']);
        $this->assertSame(['block:2'], array_keys($map['blocks']));
        $this->assertSame(0, $map['blocks']['block:2']['side']);
        $this->assertGreaterThan(0, $map['height']);
        $this->assertCount(0, $map['roads']); // No artificial road with just one block.
        $this->assertLessThan(400, $map['height']);
    }

    public function test_two_capsules_share_one_road_with_compact_spacing(): void
    {
        $map = NeighborhoodMap::build([
            'blocks' => [
                'block:2' => ['columns' => 1, 'rows' => 1],
                'block:3' => ['columns' => 1, 'rows' => 1],
            ],
            'positions' => [],
        ]);
        $upper = $map['blocks']['block:2'];
        $lower = $map['blocks']['block:3'];
        $gap = $lower['capsule_y'] - ($upper['capsule_y'] + $upper['capsule_height']);
        $this->assertSame(80, $gap);
        $this->assertCount(1, $map['roads']);
        $this->assertSame('ILLUSTRATIVE LANE', $map['roads'][0]['label']);
        $this->assertSame($upper['capsule_x'], $lower['capsule_x']);
        $this->assertLessThan(550, $map['height']);
    }

    public function test_odd_number_of_blocks_has_no_missing_right_side_array_key(): void
    {
        $map = NeighborhoodMap::build([
            'blocks' => [
                'block:4' => ['columns' => 1, 'rows' => 2],
                'block:2' => ['columns' => 2, 'rows' => 1],
                'block:9' => ['columns' => 1, 'rows' => 3],
                'block:7' => ['columns' => 2, 'rows' => 1],
                'block:5' => ['columns' => 1, 'rows' => 1],
            ],
            'positions' => [],
        ]);

        $this->assertSame(['block:2', 'block:4', 'block:5', 'block:7', 'block:9'], array_keys($map['blocks']));
        $this->assertSame(0, $map['blocks']['block:9']['side']);
        $this->assertCount(5, $map['blocks']);
        $this->assertGreaterThan(0, $map['height']);
    }

    public function test_parcel_coordinates_are_stable_and_inside_the_block(): void
    {
        $map = NeighborhoodMap::build($this->grid());
        $block = $map['blocks']['block:2'];
        $first = NeighborhoodMap::parcel($block, ['row' => 0, 'column' => 0]);
        $second = NeighborhoodMap::parcel($block, ['row' => 0, 'column' => 1]);
        $again = NeighborhoodMap::parcel($block, ['row' => 0, 'column' => 0]);

        $this->assertSame($first, $again);
        $this->assertNotSame($first['points'], $second['points']);
        $this->assertGreaterThan($first['x'], $second['x']);
        $this->assertStringContainsString(',', $first['points']);
    }

    public function test_reordering_source_blocks_does_not_change_neighborhood(): void
    {
        $grid = $this->grid();
        $reversed = $grid;
        $reversed['blocks'] = array_reverse($grid['blocks'], true);

        $this->assertSame(NeighborhoodMap::build($grid), NeighborhoodMap::build($reversed));
    }
}
