#!/usr/bin/env bash

# ==============================================================================
# Script Update & Deployment - Duta Tunggal ERP Production / Staging
# ==============================================================================
# Deskripsi : Script otomatisasi deployment yang mendukung DOCKER CONTAINER
#             maupun NATIVE HOST secara cerdas (auto-detect).
# Penggunaan: ./update.sh [options]
#   Options :
#     --branch=<name>   Tentukan git branch (default: main)
#     --docker          Paksa deployment via Docker Container
#     --native          Paksa deployment via Native Host PHP
#     --skip-pull       Lewati git pull
#     --skip-build      Lewati rebuild container / npm build
#     --quick           Mode cepat (skip rebuild/composer & npm, langsung migrate & cache)
#     --stash           Otomatis git stash perubahan lokal jika ada konflik
#     --dev             Install dev dependencies di composer (bila mode native)
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
NC='\033[0m'

# Default Configurations
DEFAULT_BRANCH="main"
BRANCH="$DEFAULT_BRANCH"
MODE_OVERRIDE="auto"
SKIP_PULL=false
SKIP_BUILD=false
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
        --docker)
            MODE_OVERRIDE="docker"
            shift
            ;;
        --native)
            MODE_OVERRIDE="native"
            shift
            ;;
        --skip-pull)
            SKIP_PULL=true
            shift
            ;;
        --skip-build|--skip-npm)
            SKIP_BUILD=true
            shift
            ;;
        --quick)
            QUICK_MODE=true
            SKIP_BUILD=true
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
            echo "  --docker         Paksa eksekusi di dalam Docker Container"
            echo "  --native         Paksa eksekusi secara Native di Host OS"
            echo "  --skip-pull      Lewati 'git pull' kode terbaru"
            echo "  --skip-build     Lewati rebuild container / kompilasi frontend"
            echo "  --quick          Mode cepat (skip build, langsung migrate & cache)"
            echo "  --stash          Otomatis simpan perubahan lokal di server ke git stash"
            echo "  --dev            Install dependensi development (khusus mode native)"
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

# Deteksi Lingkungan: Docker vs Native
COMPOSE_FILE="docker-compose.duta-tunggal.yml"
IS_DOCKER=false
DOCKER_COMPOSE_CMD=()

if [ "$MODE_OVERRIDE" = "docker" ]; then
    IS_DOCKER=true
elif [ "$MODE_OVERRIDE" = "native" ]; then
    IS_DOCKER=false
else
    # Auto-detect: Jika docker command tersedia dan file compose ditemukan
    if command -v docker &> /dev/null && [ -f "$COMPOSE_FILE" ]; then
        IS_DOCKER=true
    else
        IS_DOCKER=false
    fi
fi

if [ "$IS_DOCKER" = true ]; then
    ENV_OPT=()
    if [ -f ".env.docker" ]; then
        ENV_OPT=("--env-file" ".env.docker")
    elif [ -f ".env" ]; then
        ENV_OPT=("--env-file" ".env")
    fi

    if docker compose version &>/dev/null; then
        DOCKER_COMPOSE_CMD=("docker" "compose" "${ENV_OPT[@]}" "-f" "$COMPOSE_FILE")
    elif command -v docker-compose &>/dev/null; then
        DOCKER_COMPOSE_CMD=("docker-compose" "${ENV_OPT[@]}" "-f" "$COMPOSE_FILE")
    else
        log_warn "Docker CLI ditemukan tapi 'docker compose' / 'docker-compose' tidak ada. Beralih ke mode Native."
        IS_DOCKER=false
    fi
fi

# Artisan Wrapper
run_artisan() {
    if [ "$IS_DOCKER" = true ]; then
        "${DOCKER_COMPOSE_CMD[@]}" exec -T app php artisan "$@"
    else
        php artisan "$@"
    fi
}

# Trap Error Handler: Pastikan aplikasi kembali aktif bila terjadi kegagalan
cleanup_on_failure() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        echo ""
        log_error "Deployment terhenti karena kesalahan (Exit Code: $exit_code)!"
        if [ "$IN_MAINTENANCE" = true ]; then
            log_warn "Mengembalikan aplikasi dari maintenance mode..."
            run_artisan up || true
        fi
        log_error "Silakan periksa pesan error di atas untuk detail kendala."
    fi
}
trap cleanup_on_failure EXIT

echo -e "${BOLD}================================================================${NC}"
echo -e "${BOLD}${CYAN}   DUTA TUNGGAL ERP - PRODUCTION DEPLOYMENT & UPDATE SCRIPT   ${NC}"
echo -e "${BOLD}================================================================${NC}"
echo -e "Direktori  : ${CYAN}$SCRIPT_DIR${NC}"
echo -e "Target Git : ${CYAN}origin/$BRANCH${NC}"
if [ "$IS_DOCKER" = true ]; then
    echo -e "Mode Eksekusi : ${GREEN}${BOLD}DOCKER CONTAINER${NC} (${COMPOSE_FILE})"
else
    echo -e "Mode Eksekusi : ${YELLOW}${BOLD}NATIVE HOST OS${NC}"
fi
echo -e "Waktu Mulai: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "----------------------------------------------------------------"

# Step 1: Maintenance Mode
log_step "[1/8] Mengaktifkan Maintenance Mode"
if run_artisan down --retry=60 --secret="dt-deploy-bypass-$(date +%s)" 2>/dev/null; then
    IN_MAINTENANCE=true
    log_info "Aplikasi berada dalam mode pemeliharaan."
else
    run_artisan down || true
    IN_MAINTENANCE=true
    log_info "Aplikasi berada dalam mode pemeliharaan."
fi

# Step 2: Git Pull
if [ "$SKIP_PULL" = false ]; then
    log_step "[2/8] Menarik Pembaruan Kode Terbaru dari Git ($BRANCH)"
    
    if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
        if [ "$AUTO_STASH" = true ]; then
            log_warn "Terdapat perubahan lokal di server. Menyimpan ke stash otomatis..."
            git stash push -m "Auto-stashed by update.sh on $(date '+%Y-%m-%d %H:%M:%S')"
        else
            log_warn "Terdapat perubahan lokal di server. Jika pull gagal, jalankan dengan --stash."
        fi
    fi

    # Gunakan remote yang tersedia (dt atau origin)
    REMOTE="origin"
    if ! git remote get-url origin &>/dev/null; then
        if git remote get-url dt &>/dev/null; then
            REMOTE="dt"
        fi
    fi

    git fetch "$REMOTE" "$BRANCH"
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "HEAD")
    if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
        log_info "Berpindah branch dari $CURRENT_BRANCH ke $BRANCH..."
        git checkout "$BRANCH"
    fi
    git pull "$REMOTE" "$BRANCH"
    log_success "Kode terbaru berhasil ditarik: $(git log -1 --pretty=format:'%h - %s (%cr)')"
else
    log_step "[2/8] Melewati Git Pull (--skip-pull aktif)"
fi

# Step 3: Build & Dependencies
if [ "$IS_DOCKER" = true ]; then
    log_step "[3/8] Menyiapkan Lingkungan Docker & Dependensi"
    if [ "$SKIP_BUILD" = false ]; then
        log_info "Membangun ulang image aplikasi Docker (termasuk Composer & Frontend build)..."
        "${DOCKER_COMPOSE_CMD[@]}" build app
        "${DOCKER_COMPOSE_CMD[@]}" up -d app
        log_success "Image dan container aplikasi berhasil diperbarui."
    else
        log_info "Memastikan container aplikasi aktif (--skip-build)..."
        "${DOCKER_COMPOSE_CMD[@]}" up -d app
    fi
else
    # Native Mode
    log_step "[3/8] Memperbarui Dependensi PHP (Composer Native)"
    if [ "$QUICK_MODE" = false ]; then
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
            log_warn "Composer binary tidak ditemukan di PATH, melewati."
        fi
    else
        log_info "Melewati Composer install (--quick aktif)."
    fi
fi

# Step 4: Database Migrations
log_step "[4/8] Menjalankan Database Migrations (termasuk auto-repair & data patch)"
run_artisan migrate --force
log_success "Migrasi database berhasil dijalankan."

# Step 5: AR/AP Data Harmonization & Repair Sync
log_step "[5/8] Menyelaraskan Saldo AR/AP & Rekalkulasi Retur"
run_artisan ar-ap:sync --force || log_warn "Perintah ar-ap:sync selesai dengan catatan."
log_success "Sinkronisasi saldo AR/AP selesai."

# Step 6: Frontend Assets (khusus bila Native Mode, karena di Docker sudah dibuild saat step 3)
if [ "$IS_DOCKER" = false ]; then
    if [ "$SKIP_BUILD" = false ]; then
        log_step "[6/8] Kompilasi Aset Frontend (Vite Native)"
        if command -v npm &> /dev/null; then
            if [ -f "package.json" ]; then
                if [ ! -d "node_modules" ]; then
                    log_info "Direktori node_modules belum ada. Menjalankan npm install..."
                    npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund
                fi
                npm run build
                log_success "Aset frontend berhasil dikompilasi."
            fi
        else
            log_warn "NPM tidak ditemukan di PATH sistem, melewati build frontend."
        fi
    else
        log_step "[6/8] Melewati Kompilasi Frontend (--skip-build aktif)"
    fi
else
    log_step "[6/8] Frontend Assets sudah dikompilasi di dalam Dockerfile"
fi

# Step 7: Clear & Rebuild Caches (Optimization)
log_step "[7/8] Membersihkan & Membangun Ulang Cache Aplikasi"

# 1. Jalankan upgrade Filament terlebih dahulu (agar reset cache bawaannya tidak menghapus cache yang baru dibangun)
run_artisan filament:upgrade || true

# 2. Bersihkan cache lama
run_artisan optimize:clear

# 3. Bangun cache Laravel
run_artisan config:cache
run_artisan route:cache
run_artisan view:cache

# 4. Bangun cache Filament 3
run_artisan filament:optimize || true

# 5. Restart worker queue
run_artisan queue:restart || true

# 6. Pastikan permission folder storage & cache aman
if [ "$IS_DOCKER" = true ]; then
    "${DOCKER_COMPOSE_CMD[@]}" exec -T app chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    "${DOCKER_COMPOSE_CMD[@]}" exec -T app chmod -R 775 storage bootstrap/cache 2>/dev/null || true
else
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
fi

log_success "Seluruh cache & optimasi berhasil dimuat ulang."

# Step 8: Matikan Maintenance Mode (Up)
log_step "[8/8] Menonaktifkan Maintenance Mode (Aplikasi LIVE)"
run_artisan up
IN_MAINTENANCE=false

echo ""
echo -e "${BOLD}================================================================${NC}"
echo -e "${GREEN}${BOLD}   DEPLOI BERHASIL! DUTA TUNGGAL ERP SELESAI DIPERBARUI   ${NC}"
echo -e "${BOLD}================================================================${NC}"
echo -e "Versi Git    : ${CYAN}$(git log -1 --pretty=format:'%h - %s (%cd)')${NC}"
echo -e "Waktu Selesai: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "----------------------------------------------------------------\n"
