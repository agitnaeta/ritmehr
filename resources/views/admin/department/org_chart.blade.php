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
    // Recursive renderer — depth is bounded because Department::tree() is built
    // from parent_id links that the CRUD refuses to make cyclic.
    $renderNode = function ($node) use (&$renderNode) {
        $dept = $node['department'];
        $staff = $dept->users()->orderBy('name')->get();
        echo '<li>';
        echo '<div class="org-node">';
        echo '<strong>' . e($dept->name) . '</strong>';
        if ($dept->code) {
            echo ' <span class="badge bg-secondary">' . e($dept->code) . '</span>';
        }
        echo '<div class="small text-muted">Kepala: ' . e($dept->head?->name ?? '—') . '</div>';
        echo '<div class="small text-muted">' . $staff->count() . ' karyawan</div>';
        if ($staff->isNotEmpty()) {
            echo '<div class="small mt-1">';
            foreach ($staff as $s) {
                echo '<span class="badge bg-light text-dark me-1 mb-1">'
                    . e($s->name)
                    . ($s->position ? ' · ' . e($s->position->name) : '')
                    . '</span>';
            }
            echo '</div>';
        }
        echo '</div>';
        if (count($node['children'])) {
            echo '<ul>';
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
    .org-tree ul { list-style: none; padding-left: 1.5rem; border-left: 1px dashed #d0d5dd; }
    .org-tree > ul { padding-left: 0; border-left: 0; }
    .org-tree li { margin: .5rem 0; }
    .org-node {
        display: inline-block; padding: .5rem .75rem; border: 1px solid #e3e6ef;
        border-radius: .375rem; background: #fff; min-width: 240px;
    }
</style>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><strong>Hierarki Departemen</strong></div>
            <div class="card-body org-tree">
                @if($tree->isEmpty())
                    <p class="text-muted mb-0">Belum ada departemen. Buat dulu di menu Departemen.</p>
                @else
                    <ul>
                        @foreach($tree as $node)
                            {!! '' !!}@php $renderNode($node); @endphp
                        @endforeach
                    </ul>
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
@endsection
