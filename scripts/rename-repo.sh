#!/usr/bin/env bash
#
# rename-repo.sh — Rename repo GitHub agitnaeta/absensi -> agitnaeta/ritmehr,
# set description + topics untuk SEO, lalu update remote lokal.
#
# Aman dijalankan ulang (idempoten): tiap langkah dicek dulu, kalau sudah beres
# akan di-skip. Butuh GitHub CLI (`gh`) yang sudah login.
#
# Pemakaian:
#   ./rename-repo.sh
#
set -euo pipefail

OWNER="agitnaeta"
OLD="absensi"
NEW="ritmehr"
DESC="RitmeHR — aplikasi absensi QR & HRIS open source: penggajian, PPh 21, BPJS, cuti, kasbon, portal karyawan. Laravel + Backpack."
TOPICS=(hris absensi payroll laravel indonesia pph21 bpjs attendance human-resources backpack-crud)

# Warna sederhana
green(){ printf '\033[32m%s\033[0m\n' "$*"; }
yellow(){ printf '\033[33m%s\033[0m\n' "$*"; }
red(){ printf '\033[31m%s\033[0m\n' "$*"; }
step(){ printf '\n\033[1m▶ %s\033[0m\n' "$*"; }

# ── 0. Prasyarat ───────────────────────────────────────────
step "Cek prasyarat"
if ! command -v gh >/dev/null 2>&1; then
  red "GitHub CLI (gh) tidak ditemukan. Install: brew install gh"
  exit 1
fi
if ! gh auth status >/dev/null 2>&1; then
  red "gh belum login. Jalankan dulu: gh auth login"
  exit 1
fi
green "gh terpasang & sudah login."

# Tentukan repo mana yang masih ada (OLD atau sudah ke-rename jadi NEW).
if gh repo view "$OWNER/$NEW" >/dev/null 2>&1; then
  REPO="$OWNER/$NEW"
  yellow "Repo sudah bernama '$NEW' — langkah rename dilewati."
  RENAMED=1
elif gh repo view "$OWNER/$OLD" >/dev/null 2>&1; then
  REPO="$OWNER/$OLD"
  RENAMED=0
else
  red "Repo $OWNER/$OLD maupun $OWNER/$NEW tidak ditemukan / tidak ada akses."
  exit 1
fi

# ── 1. Rename ──────────────────────────────────────────────
step "Rename repo: $OWNER/$OLD -> $OWNER/$NEW"
if [ "${RENAMED:-0}" -eq 1 ]; then
  green "Sudah ter-rename, skip."
else
  gh repo rename "$NEW" --repo "$OWNER/$OLD" --yes
  REPO="$OWNER/$NEW"
  green "Repo di-rename ke $REPO (GitHub otomatis bikin redirect dari nama lama)."
fi

# ── 2. Description + topics ─────────────────────────────────
step "Set description + topics"
TOPIC_ARGS=()
for t in "${TOPICS[@]}"; do TOPIC_ARGS+=(--add-topic "$t"); done
gh repo edit "$OWNER/$NEW" --description "$DESC" "${TOPIC_ARGS[@]}"
green "Description & ${#TOPICS[@]} topics ter-set."

# ── 3. Update remote lokal ─────────────────────────────────
step "Update git remote lokal"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  CUR_URL="$(git remote get-url origin 2>/dev/null || echo '')"
  NEW_URL="git@github.com:$OWNER/$NEW.git"
  if [ "$CUR_URL" = "$NEW_URL" ]; then
    green "Remote origin sudah menunjuk ke $NEW_URL — skip."
  else
    git remote set-url origin "$NEW_URL"
    green "Remote origin: $CUR_URL -> $NEW_URL"
  fi
else
  yellow "Bukan di dalam git repo — lewati update remote. Jalankan manual bila perlu:"
  echo "  git remote set-url origin git@github.com:$OWNER/$NEW.git"
fi

# ── 4. Verifikasi ──────────────────────────────────────────
step "Verifikasi"
gh repo view "$OWNER/$NEW" --json nameWithOwner,description,repositoryTopics \
  --jq '"Repo : \(.nameWithOwner)\nDesc : \(.description)\nTopik: \([.repositoryTopics[].name] | join(\", \"))"' \
  2>/dev/null || gh repo view "$OWNER/$NEW"

echo
git remote -v 2>/dev/null | sed 's/^/  /' || true

echo
green "Selesai. Coba: git fetch origin && git status"
