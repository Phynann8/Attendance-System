@props(['status' => null, 'label' => null])

@php
    $map = [
        // permission statuses
        'pending' => 'badge-amber',
        'approved' => 'badge-green',
        'rejected' => 'badge-red',
        // teacher / final statuses
        'present' => 'badge-green',
        'absent' => 'badge-red',
        'permission' => 'badge-blue',
        'late' => 'badge-amber',
        'excused' => 'badge-blue',
        'absent_without_permission' => 'badge-red',
        // session status
        'open' => 'badge-green',
        'submitted' => 'badge-blue',
        'closed' => 'badge-slate',
        // case status
        'pending' => 'badge-amber',
        'closed' => 'badge-green',
        'escalated' => 'badge-red',
    ];
    $color = $map[$status] ?? 'badge';
    $label = $label ?? __(ucfirst(str_replace('_', ' ', $status ?? '')));
@endphp

<span class="badge {{ $color }}">{{ $label }}</span>