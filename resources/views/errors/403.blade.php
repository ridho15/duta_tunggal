@extends('errors.layout')

@section('title', 'Tidak punya akses')

@section('content')
    <div class="pill warning">Akses dibatasi</div>
    <h1>Anda tidak punya akses ke halaman ini</h1>
    <p>Halaman ini tidak termasuk menu untuk peran Anda. Jika Anda memerlukannya untuk pekerjaan, minta admin menambahkan hak akses.</p>
@endsection
