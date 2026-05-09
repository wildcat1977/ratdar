@extends('layouts.app')

@section('title', __('捕鼠英雄榜 · Rat Radar'))

@section('content')
<div class="min-h-dvh bg-[#0d1117]">
    @include('partials.nav')

    <livewire:leaderboard />
</div>
@endsection
