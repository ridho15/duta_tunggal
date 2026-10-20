@unless (config('app.debug'))
<script>
/**
 * Spesifikasi UI/UX 6.6 — kegagalan request Livewire tidak boleh tampil sebagai
 * halaman error/stack trace di dalam modal. Tampilkan notifikasi Bahasa Indonesia
 * dengan kode error yang sama dengan log server.
 */
document.addEventListener('livewire:init', () => {
    const notify = (title, body) => {
        if (window.FilamentNotification) {
            new window.FilamentNotification().title(title).body(body).danger().persistent().send();
        } else {
            window.alert(title + '\n\n' + body);
        }
    };

    Livewire.hook('request', ({ fail }) => {
        fail(({ status, content, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                if (window.confirm('Sesi Anda sudah berakhir karena halaman terlalu lama terbuka. Muat ulang halaman sekarang?')) {
                    window.location.reload();
                }
                return;
            }

            if (status === 403) {
                preventDefault();
                notify('Tidak punya akses', 'Aksi ini tidak termasuk hak akses peran Anda. Hubungi admin bila Anda memerlukannya.');
                return;
            }

            if (status >= 500) {
                preventDefault();
                const match = typeof content === 'string' ? content.match(/data-error-code="([^"]+)"/) : null;
                const code = match ? match[1] : 'SYS-' + status;
                notify('Terjadi kesalahan sistem', 'Data belum tersimpan. Silakan hubungi admin (kode: ' + code + ').');
            }
        });
    });
});
</script>
@endunless
