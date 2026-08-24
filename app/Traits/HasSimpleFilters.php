<?php

namespace App\Traits;

/**
 * Query-string filtering for Backpack list views.
 *
 * Backpack's own `addFilter()` is a PRO feature and throws
 * BackpackProRequiredException on the free edition, so filtering is done here
 * with plain GET parameters plus a rendered filter bar.
 *
 * Usage inside setupListOperation():
 *
 *   $this->applySimpleFilters([
 *       ['name' => 'department_id', 'label' => 'Departemen', 'type' => 'select',
 *        'options' => Department::pluck('name', 'id')->toArray()],
 *       ['name' => 'status', 'label' => 'Status', 'type' => 'select',
 *        'options' => [...], 'column' => 'leave_requests.status'],
 *   ]);
 *
 * Supported types: select, text, date, month.
 * Optional per-filter keys:
 *   - `column`  : column to compare (defaults to `name`)
 *   - `apply`   : closure(query, value) for anything non-trivial
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
trait HasSimpleFilters
{
    protected function applySimpleFilters(array $filters): void
    {
        $active = [];

        foreach ($filters as $filter) {
            $name = $filter['name'];
            $value = request()->query($name);

            $active[] = $filter + ['value' => $value];

            if ($value === null || $value === '') {
                continue;
            }

            if (isset($filter['apply']) && is_callable($filter['apply'])) {
                // addClause() invokes a closure with the query only, so the
                // value has to be bound in rather than passed alongside.
                $apply = $filter['apply'];
                $this->crud->addClause(fn ($query) => $apply($query, $value));

                continue;
            }

            $column = $filter['column'] ?? $name;

            if (($filter['type'] ?? 'select') === 'text') {
                $this->crud->addClause('where', $column, 'like', '%' . $value . '%');
            } else {
                $this->crud->addClause('where', $column, $value);
            }
        }

        // Stash for the filter bar view.
        $this->crud->set('simple_filters', $active);
        $this->crud->addButtonFromView('top', 'simple_filters', 'simple_filters', 'beginning');
    }
}
