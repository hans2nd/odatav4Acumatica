@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="bg-white rounded-2xl shadow-card p-6 border border-surface-200/60">
        <h1 class="text-2xl font-bold text-surface-800 mb-2">{$title}</h1>
        <p class="text-surface-600">This is an auto-generated view for route: <code>{$routeName}</code></p>
    </div>
</div>
@endsection