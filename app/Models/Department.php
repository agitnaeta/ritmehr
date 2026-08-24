<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Department extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'head_user_id',
    ];

    // ── Relationships ──────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class, 'department_id');
    }

    // ── Hierarchy helpers ──────────────────────────────────

    /**
     * Every department beneath this one, at any depth (excludes self).
     */
    public function descendants(): Collection
    {
        $all = static::all();

        return $this->collectDescendants($all, $this->id);
    }

    /**
     * This department's id plus all descendant ids — the usual thing you want
     * when filtering "everything under department X".
     */
    public function selfAndDescendantIds(): array
    {
        return $this->descendants()->pluck('id')->prepend($this->id)->all();
    }

    private function collectDescendants(Collection $all, $parentId, array $seen = []): Collection
    {
        // Guard against a cycle introduced by bad data — without this a
        // parent/child loop would recurse until the process dies.
        if (in_array($parentId, $seen, true)) {
            return collect();
        }

        $seen[] = $parentId;
        $children = $all->where('parent_id', $parentId)->values();

        return $children->flatMap(
            fn (Department $child) => collect([$child])
                ->merge($this->collectDescendants($all, $child->id, $seen))
        );
    }

    /**
     * "Parent > Child > Grandchild" — handy for dropdowns and reports.
     */
    public function fullPath(): string
    {
        $parts = [$this->name];
        $node = $this;
        $depth = 0;

        while ($node->parent && $depth++ < 10) {
            $node = $node->parent;
            array_unshift($parts, $node->name);
        }

        return implode(' > ', $parts);
    }

    /**
     * Would setting $parentId as this department's parent create a cycle?
     */
    public function wouldCycle($parentId): bool
    {
        if (! $parentId) {
            return false;
        }

        if ((int) $parentId === (int) $this->id) {
            return true;
        }

        return in_array((int) $parentId, $this->descendants()->pluck('id')->map('intval')->all(), true);
    }

    /**
     * Build a nested tree of all departments, roots first.
     */
    public static function tree(): Collection
    {
        $all = static::with('head')->get();

        $build = function ($parentId) use ($all, &$build) {
            return $all->where('parent_id', $parentId)->values()->map(fn ($d) => [
                'department' => $d,
                'children'   => $build($d->id),
            ]);
        };

        return $build(null);
    }
}
