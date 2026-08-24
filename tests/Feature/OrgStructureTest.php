<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgStructureTest extends TestCase
{
    use RefreshDatabase;

    private function dept(string $name, ?int $parentId = null): Department
    {
        return Department::create([
            'name'      => $name,
            'code'      => strtoupper(substr(md5($name), 0, 6)),
            'parent_id' => $parentId,
        ]);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    public function test_descendants_walks_the_whole_subtree(): void
    {
        $root = $this->dept('Root');
        $it = $this->dept('IT', $root->id);
        $infra = $this->dept('Infra', $it->id);
        $devs = $this->dept('Devs', $it->id);
        $unrelated = $this->dept('Finance');

        $names = $root->descendants()->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['IT', 'Infra', 'Devs'], $names);
        $this->assertNotContains('Finance', $names);
        $this->assertEqualsCanonicalizing(
            [$root->id, $it->id, $infra->id, $devs->id],
            $root->selfAndDescendantIds()
        );
    }

    public function test_full_path_renders_the_ancestor_chain(): void
    {
        $root = $this->dept('Head Office');
        $it = $this->dept('IT', $root->id);
        $infra = $this->dept('Infrastructure', $it->id);

        $this->assertSame('Head Office > IT > Infrastructure', $infra->fresh()->fullPath());
        $this->assertSame('Head Office', $root->fullPath());
    }

    public function test_cycle_detection_rejects_self_and_descendants_as_parent(): void
    {
        $root = $this->dept('Root');
        $child = $this->dept('Child', $root->id);
        $grandchild = $this->dept('Grandchild', $child->id);
        $other = $this->dept('Other');

        $this->assertTrue($root->wouldCycle($root->id), 'a department cannot be its own parent');
        $this->assertTrue($root->wouldCycle($child->id), 'parent cannot be its own child');
        $this->assertTrue($root->wouldCycle($grandchild->id), 'parent cannot be a deeper descendant');

        $this->assertFalse($root->wouldCycle($other->id));
        $this->assertFalse($root->wouldCycle(null));
    }

    public function test_descendants_terminates_even_if_the_data_contains_a_cycle(): void
    {
        $a = $this->dept('A');
        $b = $this->dept('B', $a->id);

        // Force a cycle past the application guard, as a bad import might.
        Department::withoutEvents(fn () => $a->forceFill(['parent_id' => $b->id])->save());

        // The assertion that matters is that this returns at all.
        $this->assertNotNull($a->fresh()->descendants());
    }

    public function test_tree_nests_children_under_their_parent(): void
    {
        $root = $this->dept('Root');
        $this->dept('Child A', $root->id);
        $this->dept('Child B', $root->id);
        $this->dept('Standalone');

        $tree = Department::tree();

        $this->assertCount(2, $tree, 'two roots expected');
        $rootNode = $tree->firstWhere(fn ($n) => $n['department']->name === 'Root');
        $this->assertCount(2, $rootNode['children']);
    }

    public function test_employed_scope_excludes_resigned_and_terminated(): void
    {
        $this->user('Active', ['employment_status' => User::STATUS_ACTIVE]);
        $this->user('Probation', ['employment_status' => User::STATUS_PROBATION]);
        $this->user('Resigned', ['employment_status' => User::STATUS_RESIGNED]);
        $this->user('Terminated', ['employment_status' => User::STATUS_TERMINATED]);

        $this->assertEqualsCanonicalizing(
            ['Active', 'Probation'],
            User::employed()->pluck('name')->all()
        );
    }

    public function test_employment_status_defaults_to_active(): void
    {
        $user = $this->user('Fresh');

        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->employment_status);
        $this->assertTrue($user->fresh()->isEmployed());
    }

    public function test_months_of_service_is_zero_without_a_join_date(): void
    {
        $user = $this->user('No Join Date');

        $this->assertSame(0, $user->monthsOfService());
    }

    public function test_months_of_service_counts_whole_months(): void
    {
        $user = $this->user('Veteran', ['join_date' => now()->subMonths(18)->toDateString()]);

        $this->assertSame(18, $user->fresh()->monthsOfService());
    }

    public function test_department_and_position_relationships_resolve(): void
    {
        $dept = $this->dept('Engineering');
        $position = Position::create([
            'name'          => 'Senior Engineer',
            'level'         => 3,
            'department_id' => $dept->id,
        ]);
        $head = $this->user('Head', ['department_id' => $dept->id]);
        $dept->update(['head_user_id' => $head->id]);

        $staff = $this->user('Staff', [
            'department_id' => $dept->id,
            'position_id'   => $position->id,
            'manager_id'    => $head->id,
        ]);

        $staff->refresh();
        $this->assertSame('Engineering', $staff->department->name);
        $this->assertSame('Senior Engineer', $staff->position->name);
        $this->assertSame('Head', $staff->manager->name);
        $this->assertSame('Head', $dept->fresh()->head->name);
        $this->assertTrue($head->fresh()->headedDepartments->contains('id', $dept->id));
        $this->assertCount(2, $dept->fresh()->users);
    }

    public function test_deleting_a_department_nulls_the_reference_rather_than_the_user(): void
    {
        $dept = $this->dept('Doomed');
        $user = $this->user('Survivor', ['department_id' => $dept->id]);

        $dept->delete();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'department_id' => null]);
    }
}
