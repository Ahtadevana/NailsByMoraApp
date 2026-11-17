export async function createQuickRecord(url, values) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        throw new Error('Token keamanan tidak ditemukan. Muat ulang halaman lalu coba lagi.');
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(values),
    });
    const result = await response.json();

    if (!response.ok) {
        const firstError = Object.values(result.errors ?? {}).flat()[0];
        throw new Error(firstError ?? result.message ?? 'Data gagal ditambahkan. Silakan coba lagi.');
    }

    return result;
}
