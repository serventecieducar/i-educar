{{--
    Crédito de autoria exibido nos módulos do BI.
    Uso: <x-bi-powered />
--}}
@props([
    'author' => null,
    'url' => null,
])
@php
    $author = $author ?? config('bis.powered_by.author', 'JaderGabriel');
    $url = $url ?? config('bis.powered_by.url', 'https://github.com/jadergabriel');
@endphp

<div class="bi-powered bi-no-print" {{ $attributes }}>
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="bi-powered-link" title="{{ $url }}">
        <i class="fa fa-github" aria-hidden="true"></i>
        <span>Powered by {{ $author }}</span>
    </a>
</div>
