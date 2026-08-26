<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M01 polish — the org chart tree must eager-load staff + positions so the
 * recursive view never triggers a query per node (N+1 guard).
 */
class OrgChartTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_builds_nested_structure(): void
    {
        $root = Department::create(['name' => 'Head Office', 'code' => 'HO']);
        $child = Department::create(['name' => 'Teknologi', 'code' => 'IT', 'parent_id' => $root->id]);
        Department::create(['name' => 'HRD', 'code' => 'HR', 'parent_id' => $root->id]);

        $tree = Department::tree();

        $this->assertCount(1, $tree, 'satu root');
        $this->assertSame('Head Office', $tree[0]['department']->name);
        $this->assertCount(2, $tree[0]['children'], 'dua anak di bawah root');
    }

    public function test_tree_does_not_run_a_query_per_node(): void
    {
        // Build a chart with several departments, staff, and positions.
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF']);
        $root = Department::create(['name' => 'Head Office', 'code' => 'HO']);

        foreach (['IT', 'HR', 'OPS', 'FIN'] as $i => $code) {
            $d = Department::create(['name' => "Dept $code", 'code' => $code, 'parent_id' => $root->id]);
            User::create([
                'name' => "User $code", 'email' => "u{$i}@ex.test", 'password' => bcrypt('x'),
                'department_id' => $d->id, 'position_id' => $pos->id,
            ]);
        }

        DB::enableQueryLog();
        $tree = Department::tree();
        // Walk exactly like the blade view does: touch users + position + head.
        $walk = function ($nodes) use (&$walk) {
            foreach ($nodes as $n) {
                $n['department']->users->each(fn ($u) => $u->position?->name);
                $n['department']->head?->name;
                $walk($n['children']);
            }
        };
        $walk($tree);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 5 departments + 4 users here; a naive N+1 would be well into double
        // digits. Eager loading keeps it to a small constant.
        $this->assertLessThanOrEqual(6, $queries, "org chart ran $queries queries — N+1 regression?");
    }
}
