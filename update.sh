#!/usr/bin/env bash

# ==============================================================================
# Script Update & Deployment - Duta Tunggal ERP Production / Staging
# ==============================================================================
# Deskripsi : Script otomatisasi pull kode terbaru, migrasi database,
#             sinkronisasi AR/AP, kompilasi aset, dan optimasi cache.
# Penggunaan: ./update.sh [options]
#   Options :
#     --branch=<name>   Tentukan git branch (default: main)
#     --skip-pull       Lewati git pull (bila sudah dipull manual)
#     --skip-npm        Lewati npm build
#     --quick           Mode cepat (hanya pull, migrate, cache; skip composer & npm)
#     --stash           Otomatis git stash perubahan lokal jika ada
#     --dev             Install dev dependencies di composer (untuk staging/dev)
#     --help, -h        Tampilkan bantuan
# ==============================================================================

set -eo pipefail

# ANSI Color Codes
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Default Configurations
DEFAULT_BRANCH="main"
BRANCH="$DEFAULT_BRANCH"
SKIP_PULL=false
SKIP_NPM=false
QUICK_MODE=false
AUTO_STASH=false
DEV_MODE=false
IN_MAINTENANCE=false

# Helper Functions
log_info() {
    echo -e "${BLUE}${BOLD}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}${BOLD}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}${BOLD}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}${BOLD}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${CYAN}${BOLD}==>${NC} ${BOLD}$1${NC}"
}

# Trap Error Handler: Pastikan aplikasi kembali aktif bila terjadi kegagalan
cleanup_on_failure() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        echo ""
        log_error "Deployment terhenti karena kesalahan (Exit Code: $exit_code)!"
        if [ "$IN_MAINTENANCE" = true ]; then
            log_warn "Mengembalikan aplikasi dari maintenance mode..."
            php artisan up || true
        fi
        log_error "Silakan periksa log di atas dan storage/logs/laravel.log untuk detail kendala."
    fi
}
trap cleanup_on_failure EXIT

# Parse CLI Arguments
while [ $# -gt 0 ]; do
    case "$1" in
        --branch=*)
            BRANCH="${1#*=}"
            shift
            ;;
        --branch)
            BRANCH="$2"
            shift 2
            ;;
        --skip-pull)
            SKIP_PULL=true
            shift
            ;;
        --skip-npm)
            SKIP_NPM=true
            shift
            ;;
        --quick)
            QUICK_MODE=true
            SKIP_NPM=true
            shift
            ;;
        --stash)
            AUTO_STASH=true
            shift
            ;;
        --dev)
            DEV_MODE=true
            shift
            ;;
        --help|-h)
            echo "Penggunaan: ./update.sh [opsi]"
            echo "Opsi:"
            echo "  --branch=<nama>  Pilih branch git yang akan dideploy (default: main)"
            echo "  --skip-pull      Lewati 'git pull' (bila sudah ditarik secara manual)"
            echo "  --skip-npm       Lewati instalasi & build aset frontend"
            echo "  --quick          Mode cepat (skip composer install & npm build)"
            echo "  --stash          Otomatis simpan perubahan lokal di server ke git stash"
            echo "  --dev            Install dependensi development Composer (untuk staging/dev)"
            echo "  --help, -h       Tampilkan petunjuk ini"
            exit 0
            ;;
        *)
            log_warn "Opsi tidak dikenal: $1 (diabaikan)"
            shift
            ;;
    esac
done

# Pastikan dijalankan dari root direktori proyek
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [ ! -f "artisan" ]; then
    log_error "File 'artisan' tidak ditemukan! Pastikan script dijalankan dari root proyek Laravel."
    exit 1
fi

echo -e "${BOLD}================================================================${NC}"
echo -e "${BOLD}${CYAN}   DUTA TUNGGAL ERP - PRODUCTION DEPLOYMENT & UPDATE SCRIPT   ${NC}"
echo -e "${BOLD}================================================================${NC}"
echo -e "Direktori  : ${CYAN}$SCRIPT_DIR${NC}"
echo -e "Target Git : ${CYAN}origin/$BRANCH${NC}"
echo -e "Waktu Mulai: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "----------------------------------------------------------------"

# Step 1: Maintenance Mode
log_step "[1/8] Mengaktifkan Maintenance Mode"
if php artisan down --retry=60 --secret="dt-deploy-bypass-$(date +%s)" 2>/dev/null; then
    IN_MAINTENANCE=true
    log_info "Aplikasi berada dalam mode pemeliharaan."
else
    # Fallback jika parameter di atas tidak didukung
    php artisan down || true
    IN_MAINTENANCE=true
    log_info "Aplikasi berada dalam mode pemeliharaan."
fi

# Step 2: Git Pull
if [ "$SKIP_PULL" = false ]; then
    log_step "[2/8] Menarik Pembaruan Kode Terbaru dari Git ($BRANCH)"
    
    # Cek apakah ada perubahan lokal yang belum di-commit
    if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
        if [ "$AUTO_STASH" = true ]; then
            log_warn "Terdapat perubahan lokal di server. Menyimpan ke stash otomatis..."
            git stash push -m "Auto-stashed by update.sh on $(date '+%Y-%m-%d %H:%M:%S')"
        else
            log_warn "Terdapat perubahan lokal di server. Jika pull gagal, jalankan dengan --stash."
        fi
    fi

    git fetch origin "$BRANCH"
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "HEAD")
    if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
        log_info "Berpindah branch dari $CURRENT_BRANCH ke $BRANCH..."
        git checkout "$BRANCH"
    fi
    git pull origin "$BRANCH"
    log_success "Kode terbaru berhasil ditarik: $(git log -1 --pretty=format:'%h - %s (%cr)')"
else
    log_step "[2/8] Melewati Git Pull (--skip-pull aktif)"
fi

# Step 3: Composer Dependencies
if [ "$QUICK_MODE" = false ]; then
    log_step "[3/8] Memperbarui Dependensi PHP (Composer)"
    
    COMPOSER_CMD=""
    if command -v composer &> /dev/null; then
        COMPOSER_CMD="composer"
    elif [ -f "composer.phar" ]; then
        COMPOSER_CMD="php composer.phar"
    fi

    if [ -n "$COMPOSER_CMD" ]; then
        if [ "$DEV_MODE" = true ]; then
            $COMPOSER_CMD install --optimize-autoloader --no-interaction
        else
            $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction
        fi
        log_success "Dependensi Composer berhasil diselaraskan."
    else
        log_warn "Composer binary tidak ditemukan di PATH atau direktori proyek, melewati langkah ini."
    fi
else
    log_step "[3/8] Melewati Composer Install (--quick mode)"
fi

# Step 4: Database Migrations
log_step "[4/8] Menjalankan Database Migrations (termasuk auto-repair & data patch)"
php artisan migrate --force
log_success "Migrasi database berhasil dijalankan."

# Step 5: AR/AP Data Harmonization & Repair Sync
log_step "[5/8] Menyelaraskan Saldo AR/AP & Rekalkulasi Retur"
php artisan ar-ap:sync --force || log_warn "Perintah ar-ap:sync selesai dengan catatan."
log_success "Sinkronisasi saldo AR/AP selesai."

# Step 6: Frontend Assets (Vite)
if [ "$SKIP_NPM" = false ] && [ "$QUICK_MODE" = false ]; then
    log_step "[6/8] Kompilasi Aset Frontend (Vite Build)"
    if command -v npm &> /dev/null; then
        if [ -f "package.json" ]; then
            if [ ! -d "node_modules" ]; then
                log_info "Direktori node_modules belum ada. Menjalankan instalasi paket frontend..."
                npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund
            fi
            npm run build
            log_success "Aset frontend berhasil dikompilasi."
        fi
    else
        log_warn "NPM binary tidak ditemukan di PATH sistem, melewati build frontend."
    fi
else
    log_step "[6/8] Melewati Kompilasi Frontend"
fi

# Step 7: Clear & Rebuild Caches (Optimization)
log_step "[7/8] Membersihkan & Membangun Ulang Cache Aplikasi"

# Jalankan upgrade Filament terlebih dahulu (agar clear cache internalnya tidak menghapus cache yang baru kita buat)
php artisan filament:upgrade || true

# Bersihkan cache lama
php artisan optimize:clear

# Bangun cache framework Laravel 12
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Bangun cache komponen Filament 3
php artisan filament:optimize || true

# Restart worker queue (supervisor/daemon) bila aktif
php artisan queue:restart || true

# Pastikan permission folder storage & bootstrap cache aman
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

log_success "Seluruh cache & optimasi berhasil dimuat ulang."

# Step 8: Matikan Maintenance Mode (Up)
log_step "[8/8] Menonaktifkan Maintenance Mode (Aplikasi LIVE)"
php artisan up
IN_MAINTENANCE=false

echo ""
echo -e "${BOLD}================================================================${NC}"
echo -e "${GREEN}${BOLD}   DEPLOI BERHASIL! DUTA TUNGGAL ERP SELESAI DIPERBARUI   ${NC}"
echo -e "${BOLD}================================================================${NC}"
echo -e "Versi Git    : ${CYAN}$(git log -1 --pretty=format:'%h - %s (%cd)')${NC}"
echo -e "Waktu Selesai: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "----------------------------------------------------------------\n"

