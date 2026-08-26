{{-- Shared applicant detail drawer (offcanvas). Used by pipeline (M18) and
     ranking (M21). Populated via fetch on .btn-detail click — see the
     detail-drawer-js partial. Expects: $canEdit (bool), $interviewers (collection). --}}
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
            {{-- AI score + reasoning (M21: score header + colored per-criteria bars + evidence) --}}
            <div class="mb-3" id="drawer-ai-section">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-info" id="drawer-ai-score">Skor AI: —</span>
                    <span class="badge bg-primary" id="drawer-ai-rank" style="display:none;"></span>
                    <span class="text-muted small" id="drawer-ai-model"></span>
                </div>
                <div id="drawer-ai-summary" class="small text-muted mb-2"></div>
                <div id="drawer-ai-criteria"></div>
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
