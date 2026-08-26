@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            Struktur Organisasi
            <small>
                <a href="{{ backpack_url('department') }}" class="d-print-none font-sm">
                    <i class="la la-angle-double-left"></i> Kembali ke Departemen
                </a>
            </small>
        </h2>
    </section>
@endsection

@php
    // Recursive renderer. Uses the eager-loaded `users`/`head` relations from
    // Department::tree() so there is no query per node (N+1-free).
    $renderNode = function ($node) use (&$renderNode) {
        $dept = $node['department'];
        $staff = $dept->users;              // already loaded + ordered
        $hasChildren = count($node['children']) > 0;
        $nodeId = 'dept-' . $dept->id;

        echo '<li>';
        echo '<div class="org-node">';

        // Header row: toggle + name + code
        echo '<div class="org-node-head">';
        if ($hasChildren) {
            echo '<button type="button" class="org-toggle" data-target="' . $nodeId . '" aria-label="Buka/tutup">'
                . '<i class="la la-minus-square"></i></button>';
        } else {
            echo '<span class="org-toggle-spacer"></span>';
        }
        echo '<span class="org-name">' . e($dept->name) . '</span>';
        if ($dept->code) {
            echo ' <span class="badge bg-secondary">' . e($dept->code) . '</span>';
        }
        echo '</div>';

        // Head of department
        echo '<div class="org-meta"><i class="la la-user-tie"></i> '
            . ($dept->head ? e($dept->head->name) : '<span class="text-muted">Tanpa kepala</span>')
            . '</div>';

        // Staff count + chips
        echo '<div class="org-meta"><i class="la la-users"></i> ' . $staff->count() . ' karyawan</div>';
        if ($staff->isNotEmpty()) {
            echo '<div class="org-staff">';
            foreach ($staff as $s) {
                echo '<span class="badge bg-light text-dark border me-1 mb-1">'
                    . e($s->name)
                    . ($s->position ? ' <span class="text-muted">· ' . e($s->position->name) . '</span>' : '')
                    . '</span>';
            }
            echo '</div>';
        }

        echo '</div>'; // .org-node

        if ($hasChildren) {
            echo '<ul id="' . $nodeId . '">';
            foreach ($node['children'] as $child) {
                $renderNode($child);
            }
            echo '</ul>';
        }
        echo '</li>';
    };
@endphp

@section('content')
<style>
    .org-toolbar { margin-bottom: .75rem; }
    .org-tree ul { list-style: none; padding-left: 1.75rem; border-left: 1px dashed #d0d5dd; margin-bottom: 0; }
    .org-tree > ul { padding-left: 0; border-left: 0; }
    .org-tree li { margin: .5rem 0; position: relative; }
    .org-node {
        display: inline-block; padding: .6rem .85rem; border: 1px solid #e3e6ef;
        border-radius: .5rem; background: #fff; min-width: 260px;
        box-shadow: 0 1px 2px rgba(16,24,40,.05);
    }
    .org-node-head { display: flex; align-items: center; gap: .4rem; font-size: 1rem; }
    .org-name { font-weight: 600; }
    .org-toggle {
        border: 0; background: transparent; padding: 0; cursor: pointer;
        color: #667085; line-height: 1; font-size: 1.1rem;
    }
    .org-toggle:hover { color: #1e3a8a; }
    .org-toggle-spacer { display: inline-block; width: 1.1rem; }
    .org-meta { font-size: .8rem; color: #667085; margin-top: .2rem; }
    .org-staff { margin-top: .4rem; }
    .org-tree li.collapsed > ul { display: none; }
    @media print { .org-toolbar, .d-print-none { display: none !important; } }
</style>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Hierarki Departemen</strong>
            </div>
            <div class="card-body">
                @if($tree->isEmpty())
                    <p class="text-muted mb-0">Belum ada departemen. Buat dulu di menu Departemen.</p>
                @else
                    <div class="org-toolbar d-print-none">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="expandAll">
                            <i class="la la-plus-square"></i> Buka Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAll">
                            <i class="la la-minus-square"></i> Tutup Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="la la-print"></i> Cetak
                        </button>
                    </div>
                    <div class="org-tree" id="orgTree">
                        <ul>
                            @foreach($tree as $node)
                                @php $renderNode($node); @endphp
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Belum Punya Departemen</strong></div>
            <div class="card-body">
                @forelse($unassigned as $user)
                    <div class="mb-1">
                        <a href="{{ backpack_url('user/' . $user->id . '/edit') }}">{{ $user->name }}</a>
                    </div>
                @empty
                    <p class="text-muted mb-0">Semua karyawan sudah punya departemen.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const tree = document.getElementById('orgTree');
    if (!tree) return;

    function setCollapsed(li, collapsed) {
        li.classList.toggle('collapsed', collapsed);
        const icon = li.querySelector(':scope > .org-node .org-toggle i');
        if (icon) icon.className = collapsed ? 'la la-plus-square' : 'la la-minus-square';
    }

    // Toggle a single node.
    tree.addEventListener('click', function (e) {
        const btn = e.target.closest('.org-toggle');
        if (!btn) return;
        const li = btn.closest('li');
        setCollapsed(li, !li.classList.contains('collapsed'));
    });

    // Expand / collapse all (only nodes that actually have children).
    document.getElementById('expandAll')?.addEventListener('click', () =>
        tree.querySelectorAll('li').forEach(li => { if (li.querySelector(':scope > ul')) setCollapsed(li, false); }));
    document.getElementById('collapseAll')?.addEventListener('click', () =>
        tree.querySelectorAll('li').forEach(li => { if (li.querySelector(':scope > ul')) setCollapsed(li, true); }));
})();
</script>
@endsection
