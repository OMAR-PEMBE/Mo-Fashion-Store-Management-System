@props(['value', 'label' => null])
<x-badge :tone="\App\Support\Status::tone($value)" {{ $attributes->class(['whitespace-nowrap']) }}>{{ $label ?? \App\Support\Status::label($value) }}</x-badge>
