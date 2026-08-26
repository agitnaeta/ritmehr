{{-- Shared self-contained drawer JS. Wires every .btn-detail on the page to
     open the offcanvas drawer, fetches applicant detail JSON, and renders
     AI score + colored per-criteria bars + evidence + CV + interviews +
     timeline. Also handles the inline interview form and the M18 auto-prompt.
     Exposes window.recruitmentDrawerOpen(id). Include AFTER the drawer markup.
     Expects: $canEdit (bool). --}}
<script>
(function () {
    var canEdit = @json($canEdit);
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value;
    var base = "{{ backpack_url('recruitment/applicant') }}";
    var drawerEl = document.getElementById('applicantDrawer');
    if (!drawerEl) return;
    var drawer = new bootstrap.Offcanvas(drawerEl);
    var esc = function (s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; };

    // Colored score tier for per-criteria bars: hi>=80 green, mid 60-79 orange, lo<60 red.
    function tier(score) {
        if (score == null) return { cls: 'bg-secondary', hex: '#adb5bd' };
        if (score >= 80) return { cls: 'text-success', hex: '#198754' };
        if (score >= 60) return { cls: 'text-warning', hex: '#fd7e14' };
        return { cls: 'text-danger', hex: '#dc3545' };
    }

    function openDetail(id) {
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

                // AI score header
                var scoreEl = document.getElementById('drawer-ai-score');
                if (d.ai_score != null) {
                    scoreEl.textContent = 'Skor AI: ' + Math.round(d.ai_score) + '/100';
                } else if (d.vector_score != null) {
                    scoreEl.textContent = 'Shortlist ~' + Math.round(d.vector_score);
                } else {
                    scoreEl.textContent = 'Belum dinilai AI';
                }
                // Rank badge (#N dari M) when provided by the page.
                var rankEl = document.getElementById('drawer-ai-rank');
                if (rankEl) {
                    if (d.rank != null && d.rank_total != null) {
                        rankEl.textContent = '#' + d.rank + ' dari ' + d.rank_total;
                        rankEl.style.display = '';
                    } else {
                        rankEl.style.display = 'none';
                    }
                }
                document.getElementById('drawer-ai-model').textContent = d.ai_model ? ('model ' + d.ai_model) : '';
                var summary = d.ai_reasoning && d.ai_reasoning.summary ? d.ai_reasoning.summary : '';
                document.getElementById('drawer-ai-summary').textContent = summary;

                // Per-criteria: name + score + colored bar + reason + evidence.
                var critWrap = document.getElementById('drawer-ai-criteria');
                critWrap.innerHTML = '';
                var criteria = (d.ai_reasoning && d.ai_reasoning.criteria) || [];
                criteria.forEach(function (c) {
                    var t = tier(c.score);
                    var pct = (c.score != null ? Math.max(0, Math.min(100, c.score)) : 0);
                    var box = document.createElement('div');
                    box.className = 'border rounded p-2 mb-2';
                    box.innerHTML =
                        '<div class="d-flex justify-content-between align-items-center mb-1">' +
                            '<span class="fw-semibold small">' + esc(c.name) + '</span>' +
                            (c.score != null ? '<span class="fw-bold small ' + t.cls + '">' + esc(c.score) + '</span>' : '') +
                        '</div>' +
                        '<div style="height:6px;border-radius:4px;background:#eef1f5;overflow:hidden;margin-bottom:6px;">' +
                            '<div style="height:100%;border-radius:4px;width:' + pct + '%;background:' + t.hex + ';"></div>' +
                        '</div>' +
                        (c.reason ? '<div class="small text-body mb-1">' + esc(c.reason) + '</div>' : '') +
                        (c.evidence ? '<div class="small text-muted" style="background:#fafbfc;border-radius:6px;padding:5px 8px;"><span class="fw-semibold">Bukti:</span> ' + esc(c.evidence) + '</div>' : '');
                    critWrap.appendChild(box);
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

    // Expose so other page scripts can open the drawer programmatically.
    window.recruitmentDrawerOpen = openDetail;

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
    var ivFormEl = document.getElementById('drawer-iv-form');
    if (ivToggle && ivFormEl) {
        ivToggle.addEventListener('click', function () {
            ivFormEl.style.display = ivFormEl.style.display === 'none' ? 'block' : 'none';
        });

        ivFormEl.addEventListener('submit', function (e) {
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
                  if (d.ok) { openDetail(id); ivFormEl.reset(); }
                  else { alert(d.message || 'Gagal menjadwalkan.'); }
              })
              .catch(function () { submitBtn.disabled = false; alert('Gagal menjadwalkan wawancara.'); });
        });
    }
})();
</script>
