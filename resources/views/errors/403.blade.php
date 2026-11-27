@extends('errors.layout')

@section('title', 'Tidak punya akses')

@section('content')
    <div class="pill warning">Akses dibatasi</div>
    <h1>Anda tidak punya akses ke halaman ini</h1>
    @php($accessMessage = isset($exception) ? (string) $exception->getMessage() : '')
    @if (str_starts_with($accessMessage, 'Anda tidak memiliki akses'))
        <p>{{ $accessMessage }}</p>
    @else
        <p>Halaman ini tidak termasuk menu untuk peran Anda. Jika Anda memerlukannya untuk pekerjaan, minta Owner / Super Admin menambahkan hak akses di Pengaturan → Peran &amp; Hak Akses.</p>
    @endif
@endsection
