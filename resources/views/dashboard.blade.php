@extends('layouts.app', ['title' => !empty($isExecutive) ? 'Dashboard' : 'Beranda'])

@section('breadcrumbs')
    <div class="flex items-center gap-2 text-sm">
        <span class="text-gray-500">Duta Tunggal</span>
        <span class="text-gray-300">/</span>
        <span class="font-bold text-gray-900">{{ !empty($isExecutive) ? 'Dashboard' : 'Beranda' }}</span>
    </div>
@endsection

@section('content')
    @if (!empty($isExecutive))
        @include('dashboard.executive')
    @else
        @include('dashboard.operational')
    @endif
@endsection
