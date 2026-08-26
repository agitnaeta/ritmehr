@extends(backpack_view('blank'))

@php($stageLabels = \App\Models\Applicant::STAGE_LABELS)
@php($pipeline = \App\Models\Applicant::PIPELINE)

@section('header')
    <section class="container-fluid">
        <h2>Papan Pipeline Rekrutmen <small>seret kartu antar kolom untuk memindahkan tahap</small></h2>
    </section>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Lowongan</label>
                <select name="job_opening_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">Semua Lowongan</option>
                    @foreach($openings as $o)
                        <option value="{{ $o->id }}" @selected($openingId == $o->id)>{{ $o->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <a href="{{ backpack_url('recruitment/calendar') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="la la-calendar"></i> Jadwal Wawancara
                </a>
            </div>
            @if($canEdit && $openingId)
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-primary" id="btn-rank-ai"
                        data-opening="{{ $openingId }}">
                    <i class="la la-robot"></i> Ranking dengan AI
                </button>
            </div>
            @endif
            <div class="col-auto ms-auto">
                <a href="{{ backpack_url('applicant') }}" class="btn btn-sm btn-outline-secondary" id="btn-table-view">
                    <i class="la la-table"></i> Tampilan Tabel
                </a>
            </div>
        </form>
    </div>
</div>

@if($canEdit)
{{-- M18-5 — bulk action bar, shown when ≥1 card is selected. --}}
<div class="card mb-3" id="bulk-bar" style="display:none;">
    <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-bold small"><span id="bulk-count">0</span> pelamar dipilih</span>
        <select class="form-control form-control-sm w-auto" id="bulk-move-stage">
            <option value="">Pindah ke tahap…</option>
            <option value="screening">Seleksi Berkas</option>
            <option value="interview">Wawancara</option>
            <option value="offer">Penawaran</option>
        </select>
        <button class="btn btn-sm btn-outline-primary" id="bulk-move-btn" type="button">Pindahkan</button>
        <button class="btn btn-sm btn-outline-danger" id="bulk-reject-btn" type="button">
            <i class="la la-times"></i> Tolak Terpilih
        </button>
        <button class="btn btn-sm btn-link text-muted" id="bulk-clear-btn" type="button">Batal pilih</button>
    </div>
</div>
@endif

<div class="pipeline-board" style="display:flex; gap:.75rem; overflow-x:auto; padding-bottom:1rem;">
    @foreach($pipeline as $stage)
        <div class="pipeline-col" data-stage="{{ $stage }}"
             style="flex:1 0 220px; min-width:220px; background:#f4f6f9; border-radius:.5rem;">
            <div class="px-2 py-2 fw-bold border-bottom d-flex justify-content-between">
                <span>{{ $stageLabels[$stage] }}</span>
                <span class="badge bg-secondary" data-count="{{ $stage }}">{{ count($byStage[$stage]) }}</span>
            </div>
            <div class="pipeline-drop p-2" data-stage="{{ $stage }}" style="min-height:120px;">
                @foreach($byStage[$stage] as $applicant)
                    <div class="card mb-2 pipeline-card" draggable="{{ $canEdit ? 'true' : 'false' }}"
                         data-id="{{ $applicant->id }}" style="cursor:{{ $canEdit ? 'grab' : 'default' }};">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="d-flex align-items-start gap-1">
                                    @if($canEdit && !$applicant->hired_user_id)
                                        <input type="checkbox" class="form-check-input bulk-check mt-0"
                                               data-id="{{ $applicant->id }}" title="Pilih">
                                    @endif
                                    <div class="fw-bold small">{{ $applicant->name }}</div>
                                </div>
                                <button class="btn btn-sm btn-link p-0 btn-detail" data-id="{{ $applicant->id }}"
                                        title="Lihat detail" style="line-height:1;">
                                    <i class="la la-external-link-alt"></i>
                                </button>
                            </div>
                            <div class="text-muted" style="font-size:.75rem;">
                                {{ $applicant->jobOpening?->title ?? '—' }}
                            </div>
                            @if($applicant->ai_score !== null)
                                <span class="badge bg-info mt-1" title="{{ $applicant->ai_reasoning['summary'] ?? '' }}">
                                    <i class="la la-robot"></i> Skor AI: {{ number_format($applicant->ai_score, 0) }}/100
                                </span>
                            @elseif($applicant->vector_score !== null)
                                <span class="badge bg-light text-dark mt-1" title="Kemiripan vektor (shortlist)">
                                    ~{{ number_format($applicant->vector_score, 0) }}
                                </span>
                            @endif
                            @if($applicant->hired_user_id)
                                <span class="badge bg-success mt-1">Akun dibuat</span>
                            @elseif($stage === 'offer' && $canEdit)
                                <button class="btn btn-sm btn-success mt-1 py-0 btn-hire" data-id="{{ $applicant->id }}">
                                    <i class="la la-check"></i> Terima
                                </button>
                            @endif
                            @if($canEdit && !$applicant->hired_user_id && $stage !== 'rejected')
                                <button class="btn btn-sm btn-outline-danger mt-1 py-0 btn-reject" data-id="{{ $applicant->id }}">
                                    <i class="la la-times"></i> Tolak
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

@if($canEdit)
<form id="hireForm" method="POST" style="display:none;">@csrf</form>
@endif

{{-- M18-3 — Applicant detail drawer (offcanvas). Populated via fetch on card click. --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="applicantDrawer" style="width:min(560px,95vw);">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-0" id="drawer-name">—</h5>
            <div class="text-muted small" id="drawer-sub">—</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">
        <div id="drawer-loading" class="text-center text-muted py-5" style="display:none;">
            <i class="la la-spinner la-spin la-2x"></i><div>Memuat…</div>
        </div>

        <div id="drawer-content">
            {{-- AI score + reasoning --}}
            <div class="mb-3" id="drawer-ai-section">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-info" id="drawer-ai-score">Skor AI: —</span>
                    <span class="text-muted small" id="drawer-ai-model"></span>
                </div>
                <div id="drawer-ai-summary" class="small text-muted mb-2"></div>
                <ul class="list-group list-group-flush small" id="drawer-ai-criteria"></ul>
            </div>

            {{-- CV preview --}}
            <div class="mb-3">
                <div class="fw-bold small mb-1"><i class="la la-file-pdf"></i> CV</div>
                <div id="drawer-cv-wrap">
                    <iframe id="drawer-cv" src="about:blank" style="width:100%;height:340px;border:1px solid var(--border,#ddd);border-radius:.4rem;"></iframe>
                </div>
                <div id="drawer-cv-none" class="text-muted small" style="display:none;">CV tidak tersedia.</div>
            </div>

            {{-- Interviews --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="fw-bold small"><i class="la la-comments"></i> Wawancara (<span id="drawer-iv-count">0</span>)</div>
                    @if($canEdit)
                    <button class="btn btn-sm btn-outline-primary py-0" type="button" id="drawer-iv-toggle">
                        <i class="la la-plus"></i> Jadwalkan
                    </button>
                    @endif
                </div>

                @if($canEdit)
                {{-- Inline scheduling form (applicant auto-filled, no dropdown re-pick). --}}
                <form id="drawer-iv-form" class="border rounded p-2 mb-2" style="display:none;">
                    <input type="hidden" id="iv-applicant-id" value="">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-0">Tanggal & Jam</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="iv-scheduled-at" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Mode</label>
                            <select class="form-control form-control-sm" id="iv-mode">
                                <option value="onsite">Tatap Muka</option>
                                <option value="online">Online</option>
                                <option value="phone">Telepon</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Pewawancara</label>
                            <select class="form-control form-control-sm" id="iv-interviewer">
                                <option value="">—</option>
                                @foreach($interviewers as $iv)
                                    <option value="{{ $iv->id }}">{{ $iv->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Lokasi / Link</label>
                            <input type="text" class="form-control form-control-sm" id="iv-location" placeholder="Ruang / Zoom">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-2" id="iv-submit">
                        <i class="la la-calendar-check"></i> Simpan Jadwal
                    </button>
                </form>
                @endif

                <ul class="list-group list-group-flush small" id="drawer-interviews"></ul>
            </div>

            {{-- Timeline --}}
            <div class="mb-3">
                <div class="fw-bold small mb-1"><i class="la la-history"></i> Riwayat Tahap</div>
                <ul class="list-group list-group-flush small" id="drawer-timeline"></ul>
            </div>
        </div>
    </div>
</div>
@endsection

@section('after_scripts')
<script>
(function () {
    var canEdit = @json($canEdit);
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('#hireForm input[name="_token"]')?.value;
    var base = "{{ backpack_url('recruitment/applicant') }}";

    // ── Hire button ────────────────────────────────────────
    document.querySelectorAll('.btn-hire').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!confirm('Terima pelamar ini dan buat akun karyawan?')) return;
            var id = btn.dataset.id;
            fetch(base + '/' + id + '/hire', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(function (r) { return r.json(); })
              .then(function (d) { if (d.ok) location.reload(); else alert(d.message || 'Gagal.'); })
              .catch(function () { alert('Gagal memproses.'); });
        });
    });

    // ── Reject button (M17-5) — deletes CV permanently ─────
    document.querySelectorAll('.btn-reject').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!confirm('Tolak pelamar ini? CV akan DIHAPUS PERMANEN. Akun kandidat tetap ada.')) return;
            var id = btn.dataset.id;
            fetch(base + '/' + id + '/reject', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(function (r) { return r.json(); })
              .then(function (d) { if (d.ok) location.reload(); else alert(d.message || 'Gagal.'); })
              .catch(function () { alert('Gagal memproses.'); });
        });
    });

    // ── Detail drawer (M18-3) ──────────────────────────────
    var drawerEl = document.getElementById('applicantDrawer');
    var drawer = drawerEl ? new bootstrap.Offcanvas(drawerEl) : null;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; };

    function openDetail(id) {
        if (!drawer) return;
        var idField = document.getElementById('iv-applicant-id');
        if (idField) idField.value = id;
        var ivForm = document.getElementById('drawer-iv-form');
        if (ivForm) ivForm.style.display = 'none';
        document.getElementById('drawer-loading').style.display = 'block';
        document.getElementById('drawer-content').style.display = 'none';
        drawer.show();

        fetch(base + '/' + id + '/detail', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                document.getElementById('drawer-name').textContent = d.name || '—';
                document.getElementById('drawer-sub').textContent =
                    (d.opening || '—') + ' · ' + (d.stage_label || '') + (d.email ? ' · ' + d.email : '');

                // AI score + reasoning
                var scoreEl = document.getElementById('drawer-ai-score');
                if (d.ai_score != null) {
                    scoreEl.textContent = 'Skor AI: ' + Math.round(d.ai_score) + '/100';
                } else if (d.vector_score != null) {
                    scoreEl.textContent = 'Shortlist ~' + Math.round(d.vector_score);
                } else {
                    scoreEl.textContent = 'Belum dinilai AI';
                }
                document.getElementById('drawer-ai-model').textContent = d.ai_model ? ('model ' + d.ai_model) : '';
                var summary = d.ai_reasoning && d.ai_reasoning.summary ? d.ai_reasoning.summary : '';
                document.getElementById('drawer-ai-summary').textContent = summary;
                var critWrap = document.getElementById('drawer-ai-criteria');
                critWrap.innerHTML = '';
                var criteria = (d.ai_reasoning && d.ai_reasoning.criteria) || [];
                criteria.forEach(function (c) {
                    var li = document.createElement('li');
                    li.className = 'list-group-item px-0';
                    li.innerHTML = '<strong>' + esc(c.name) + '</strong>' +
                        (c.score != null ? ' <span class="badge bg-secondary">' + esc(c.score) + '</span>' : '') +
                        (c.reason ? '<div class="text-muted">' + esc(c.reason) + '</div>' : '') +
                        (c.evidence ? '<div class="text-muted fst-italic">' + esc(c.evidence) + '</div>' : '');
                    critWrap.appendChild(li);
                });

                // CV preview
                var cv = document.getElementById('drawer-cv');
                var cvNone = document.getElementById('drawer-cv-none');
                if (d.has_cv && d.cv_url) {
                    cv.src = d.cv_url; cv.style.display = 'block'; cvNone.style.display = 'none';
                } else {
                    cv.src = 'about:blank'; cv.style.display = 'none'; cvNone.style.display = 'block';
                }

                // Interviews
                document.getElementById('drawer-iv-count').textContent = (d.interviews || []).length;
                var ivWrap = document.getElementById('drawer-interviews');
                ivWrap.innerHTML = '';
                if (!(d.interviews || []).length) {
                    ivWrap.innerHTML = '<li class="list-group-item px-0 text-muted">Belum ada wawancara.</li>';
                } else {
                    d.interviews.forEach(function (iv) {
                        var li = document.createElement('li');
                        li.className = 'list-group-item px-0';
                        li.innerHTML = '<strong>' + esc(iv.scheduled_at) + '</strong> · ' + esc(iv.mode) +
                            ' · ' + esc(iv.status) + (iv.interviewer ? ' · ' + esc(iv.interviewer) : '') +
                            (iv.score != null ? ' · skor ' + esc(iv.score) : '');
                        ivWrap.appendChild(li);
                    });
                }

                // Timeline
                var tl = document.getElementById('drawer-timeline');
                tl.innerHTML = '';
                if (!(d.timeline || []).length) {
                    tl.innerHTML = '<li class="list-group-item px-0 text-muted">Belum ada perpindahan tahap.</li>';
                } else {
                    d.timeline.forEach(function (t) {
                        var li = document.createElement('li');
                        li.className = 'list-group-item px-0';
                        li.innerHTML = '<span class="text-muted">' + esc(t.at) + '</span> — ' +
                            esc(t.from) + ' → <strong>' + esc(t.to) + '</strong>' +
                            ' <span class="text-muted">oleh ' + esc(t.actor) + '</span>' +
                            (t.note ? '<div class="text-muted">' + esc(t.note) + '</div>' : '');
                        tl.appendChild(li);
                    });
                }

                document.getElementById('drawer-loading').style.display = 'none';
                document.getElementById('drawer-content').style.display = 'block';
            })
            .catch(function () {
                document.getElementById('drawer-loading').innerHTML = '<div class="text-danger">Gagal memuat detail.</div>';
            });
    }

    document.querySelectorAll('.btn-detail').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openDetail(btn.dataset.id);
        });
    });

    // M18-4 auto-prompt: after a drop into the interview column reloaded the page,
    // reopen the drawer for that applicant with the scheduling form revealed.
    try {
        var promptId = sessionStorage.getItem('m18_prompt_interview');
        if (promptId) {
            sessionStorage.removeItem('m18_prompt_interview');
            setTimeout(function () {
                openDetail(promptId);
                var f = document.getElementById('drawer-iv-form');
                if (f) f.style.display = 'block';
            }, 400);
        }
    } catch (e) {}

    // ── Schedule interview from drawer (M18-4) ─────────────
    var ivToggle = document.getElementById('drawer-iv-toggle');
    var ivForm = document.getElementById('drawer-iv-form');
    if (ivToggle && ivForm) {
        ivToggle.addEventListener('click', function () {
            ivForm.style.display = ivForm.style.display === 'none' ? 'block' : 'none';
        });

        ivForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var id = document.getElementById('iv-applicant-id').value;
            var payload = {
                scheduled_at: document.getElementById('iv-scheduled-at').value,
                mode: document.getElementById('iv-mode').value,
                interviewer_id: document.getElementById('iv-interviewer').value || null,
                location: document.getElementById('iv-location').value || null,
                advance_stage: true,
            };
            if (!payload.scheduled_at) { alert('Isi tanggal & jam wawancara.'); return; }
            var submitBtn = document.getElementById('iv-submit');
            submitBtn.disabled = true;
            fetch(base + '/' + id + '/interview', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            }).then(function (r) { return r.json(); })
              .then(function (d) {
                  submitBtn.disabled = false;
                  if (d.ok) { openDetail(id); ivForm.reset(); }
                  else { alert(d.message || 'Gagal menjadwalkan.'); }
              })
              .catch(function () { submitBtn.disabled = false; alert('Gagal menjadwalkan wawancara.'); });
        });
    }

    // ── Rank with AI button (M17-4) ────────────────────────
    var rankBtn = document.getElementById('btn-rank-ai');
    if (rankBtn) {
        rankBtn.addEventListener('click', function () {
            var openingId = rankBtn.dataset.opening;
            rankBtn.disabled = true;
            rankBtn.innerHTML = '<i class="la la-spinner la-spin"></i> Menilai...';
            fetch("{{ backpack_url('recruitment/opening') }}/" + openingId + '/rank', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(function (r) { return r.json(); })
              .then(function (d) {
                  alert(d.message || 'Selesai.');
                  location.reload();
              })
              .catch(function () {
                  alert('Gagal menjalankan ranking AI.');
                  rankBtn.disabled = false;
                  rankBtn.innerHTML = '<i class="la la-robot"></i> Ranking dengan AI';
              });
        });
    }

    if (!canEdit) return;

    // ── Bulk selection + actions (M18-5) ───────────────────
    var selected = new Set();
    var bulkBar = document.getElementById('bulk-bar');
    function refreshBulkBar() {
        document.getElementById('bulk-count').textContent = selected.size;
        bulkBar.style.display = selected.size ? 'block' : 'none';
    }
    document.querySelectorAll('.bulk-check').forEach(function (chk) {
        chk.addEventListener('click', function (e) { e.stopPropagation(); });
        chk.addEventListener('change', function () {
            if (chk.checked) selected.add(chk.dataset.id); else selected.delete(chk.dataset.id);
            refreshBulkBar();
        });
    });
    function bulkSubmit(action, stage) {
        if (!selected.size) return;
        var confirmMsg = action === 'reject'
            ? 'Tolak ' + selected.size + ' pelamar? CV mereka akan DIHAPUS PERMANEN.'
            : 'Pindahkan ' + selected.size + ' pelamar?';
        if (!confirm(confirmMsg)) return;
        fetch("{{ backpack_url('recruitment/bulk') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: Array.from(selected).map(Number), action: action, stage: stage || null }),
        }).then(function (r) { return r.json(); })
          .then(function (d) { if (d.ok) location.reload(); else alert(d.message || 'Gagal.'); })
          .catch(function () { alert('Gagal memproses aksi massal.'); });
    }
    document.getElementById('bulk-reject-btn').addEventListener('click', function () { bulkSubmit('reject'); });
    document.getElementById('bulk-move-btn').addEventListener('click', function () {
        var stage = document.getElementById('bulk-move-stage').value;
        if (!stage) { alert('Pilih tahap tujuan.'); return; }
        bulkSubmit('move', stage);
    });
    document.getElementById('bulk-clear-btn').addEventListener('click', function () {
        selected.clear();
        document.querySelectorAll('.bulk-check').forEach(function (c) { c.checked = false; });
        refreshBulkBar();
    });

    // ── Drag & drop between stage columns ──────────────────
    var dragged = null;
    document.querySelectorAll('.pipeline-card').forEach(function (card) {
        card.addEventListener('dragstart', function () { dragged = card; card.style.opacity = '.5'; });
        card.addEventListener('dragend', function () { card.style.opacity = '1'; });
    });

    document.querySelectorAll('.pipeline-drop').forEach(function (col) {
        col.addEventListener('dragover', function (e) { e.preventDefault(); col.style.background = '#e9ecef'; });
        col.addEventListener('dragleave', function () { col.style.background = ''; });
        col.addEventListener('drop', function (e) {
            e.preventDefault();
            col.style.background = '';
            if (!dragged) return;
            var id = dragged.dataset.id;
            var stage = col.dataset.stage;
            fetch(base + '/' + id + '/stage', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ stage: stage }),
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                  if (res.ok && res.d.ok) {
                      // M18-4 auto-prompt: moving into the interview column offers
                      // to schedule an interview right away (applicant prefilled).
                      if (stage === 'interview') {
                          try { sessionStorage.setItem('m18_prompt_interview', id); } catch (e) {}
                      }
                      col.appendChild(dragged);  // optimistic move
                      location.reload();          // refresh counts + hire buttons
                  } else {
                      alert(res.d.message || 'Tidak bisa memindahkan pelamar.');
                  }
              })
              .catch(function () { alert('Gagal memindahkan.'); });
        });
    });
})();
</script>
@endsection
