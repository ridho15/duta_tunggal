@extends('errors.layout')

@php($errorCode = $errorCode ?? \App\Support\ErrorCode::make($status ?? 500))

@section('title', 'Terjadi kesalahan sistem')
@section('error_code', $errorCode)

@section('content')
    <div class="pill danger">Kesalahan sistem</div>
    <h1>Terjadi kesalahan sistem</h1>
    <p>Data Anda belum tersimpan. Silakan coba lagi beberapa saat lagi.</p>
    <p>Jika masih terjadi, hubungi admin dan sebutkan kode berikut:</p>
    <div class="code">{{ $errorCode }}</div>
    <div class="muted">Kode ini membantu admin menemukan penyebabnya.</div>
@endsection
