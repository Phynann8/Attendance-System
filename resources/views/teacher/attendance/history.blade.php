@extends('layouts.app')

@section('title', __('Attendance History'))

@section('content')
<div class="flex-between" style="align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <div class="page-title">{{ __('Attendance Sessions') }}</div>
        <div class="page-sub">{{ __('Your classes and their attendance sessions.') }}</div>
    </div>
    @if(isset($openableClasses) && $openableClasses->isNotEmpty())
        <button type="button" class="btn btn-primary" id="openSessionModalBtn" style="box-shadow: 0 4px 12px rgba(14, 116, 144, 0.2); display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-calendar-plus"></i>
            <span>{{ __('Open Today\'s Attendance') }}</span>
            <span style="background: rgba(255,255,255,0.25); color: #fff; padding: 1px 7px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                {{ $openableClasses->count() }}
            </span>
        </button>
    @endif
</div>

@if(isset($openableClasses) && $openableClasses->isNotEmpty())
    <!-- Searchable Modal for Opening Today's Attendance -->
    <div id="openSessionModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: #fff; border-radius: 14px; max-width: 620px; width: 92%; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); overflow: hidden;">
            
            <!-- Modal Header -->
            <div style="padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc;">
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-calendar-plus" style="color: var(--brand-gold);"></i>
                        {{ __('Open Today\'s Attendance') }}
                    </h3>
                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                        {{ now()->format('l, d M Y') }} · {{ __(':count classes ready to open', ['count' => $openableClasses->count()]) }}
                    </div>
                </div>
                <button type="button" id="closeModalBtn" style="background: none; border: none; font-size: 24px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 2px 8px; border-radius: 6px;">&times;</button>
            </div>

            <!-- Search & Campus Filters -->
            <div style="padding: 14px 22px; border-bottom: 1px solid #f1f5f9; background: #fff;">
                <div style="position: relative; margin-bottom: 10px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" id="classSearchInput" 
                           placeholder="{{ __('Search class name, grade, teacher...') }}" 
                           style="width: 100%; padding: 8px 12px 8px 34px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none;"
                           autocomplete="off">
                </div>

                @if(isset($campuses) && $campuses->count() > 1)
                    <div style="display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px;" id="campusTabs">
                        <button type="button" class="campus-tab-btn" data-campus="all"
                                style="padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid transparent; cursor: pointer; background: #0f172a; color: #fff;">
                            {{ __('All Campuses') }}
                        </button>
                        @foreach($campuses as $camp)
                            <button type="button" class="campus-tab-btn" data-campus="{{ $camp->id }}"
                                    style="padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; border: 1px solid #e2e8f0; cursor: pointer; background: #f8fafc; color: #475569;">
                                {{ $camp->code }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Class List -->
            <div id="classListContainer" style="padding: 12px 22px; overflow-y: auto; max-height: 400px; display: flex; flex-direction: column; gap: 8px;">
                @foreach($openableClasses as $cls)
                    <div class="class-card-item" 
                         data-campus-id="{{ $cls->campus_id ?? '' }}"
                         data-search="{{ strtolower($cls->name . ' ' . $cls->grade . ' ' . ($cls->campus?->code ?? '') . ' ' . ($cls->teacher?->name ?? '')) }}"
                         style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; border: 1px solid #bbf7d0;">
                                {{ substr($cls->name, 0, 3) }}
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong style="font-size: 14px; color: #0f172a;">{{ $cls->name }}</strong>
                                    @if($cls->campus)
                                        <span class="badge badge-slate" style="font-size: 10px; padding: 1px 5px; font-weight: 700;">{{ $cls->campus->code }}</span>
                                    @endif
                                    @if($cls->grade)
                                        <span class="muted" style="font-size: 12px;">· {{ $cls->grade }}</span>
                                    @endif
                                </div>
                                <div style="font-size: 12px; color: #64748b; margin-top: 1px;">
                                    <i class="fa-solid fa-chalkboard-user" style="font-size: 10px; color: #94a3b8; margin-right: 3px;"></i>
                                    {{ $cls->teacher->name ?? __('No teacher assigned') }}
                                </div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('teacher.attendance.open', $cls) }}" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-play" style="font-size: 10px;"></i>
                                {{ __('Open & Mark') }}
                            </button>
                        </form>
                    </div>
                @endforeach

                <div id="noMatchState" style="display: none; padding: 28px 12px; text-align: center; color: #94a3b8;">
                    <i class="fa-solid fa-magnifying-glass" style="font-size: 24px; margin-bottom: 6px; color: #cbd5e1;"></i>
                    <div style="font-size: 13px; font-weight: 600; color: #64748b;">{{ __('No matching classes found') }}</div>
                    <div style="font-size: 11px;">{{ __('Try a different search or campus filter.') }}</div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 10px 22px; border-top: 1px solid #f1f5f9; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #64748b;">
                <span>{{ __('Showing') }} <strong id="visibleCount">{{ $openableClasses->count() }}</strong> {{ __('classes') }}</span>
                <button type="button" id="cancelModalBtn" class="btn btn-sm btn-outline">{{ __('Close') }}</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('openSessionModal');
        const openBtn = document.getElementById('openSessionModalBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelModalBtn');
        const searchInput = document.getElementById('classSearchInput');
        const classItems = document.querySelectorAll('.class-card-item');
        const noMatchState = document.getElementById('noMatchState');
        const visibleCount = document.getElementById('visibleCount');
        const campusTabs = document.querySelectorAll('.campus-tab-btn');

        let selectedCampus = 'all';

        function showModal() {
            if (!modal) return;
            modal.style.display = 'flex';
            if (searchInput) {
                searchInput.value = '';
                filterClasses();
                setTimeout(() => searchInput.focus(), 50);
            }
        }

        function hideModal() {
            if (!modal) return;
            modal.style.display = 'none';
        }

        if (openBtn) openBtn.addEventListener('click', showModal);
        if (closeBtn) closeBtn.addEventListener('click', hideModal);
        if (cancelBtn) cancelBtn.addEventListener('click', hideModal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) hideModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                hideModal();
            }
        });

        function filterClasses() {
            const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
            let count = 0;

            classItems.forEach(item => {
                const itemSearch = item.getAttribute('data-search') || '';
                const itemCampus = item.getAttribute('data-campus-id') || '';

                const matchesSearch = query === '' || itemSearch.includes(query);
                const matchesCampus = selectedCampus === 'all' || itemCampus === selectedCampus;

                if (matchesSearch && matchesCampus) {
                    item.style.display = 'flex';
                    count++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (visibleCount) visibleCount.textContent = count;
            if (noMatchState) noMatchState.style.display = count === 0 ? 'block' : 'none';
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterClasses);
        }

        campusTabs.forEach(btn => {
            btn.addEventListener('click', function () {
                campusTabs.forEach(b => {
                    b.style.background = '#f8fafc';
                    b.style.color = '#475569';
                    b.style.border = '1px solid #e2e8f0';
                });
                btn.style.background = '#0f172a';
                btn.style.color = '#fff';
                btn.style.border = '1px solid transparent';

                selectedCampus = btn.getAttribute('data-campus');
                filterClasses();
            });
        });
    });
    </script>
@endif

<div class="card">
    <table>
        <thead>
            <tr>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Opened') }}</th>
                <th>{{ __('Submitted') }}</th>
                <th>{{ __('Students') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td><strong>{{ $session->classRoom->name }}</strong></td>
                    <td>{{ $session->session_date->format('D, d M Y') }}</td>
                    <td>{{ $session->opened_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $session->submitted_at?->format('H:i') ?? '—' }}</td>
                    <td>
                        <x-status-badge :status="$session->status" />
                        @if($session->isReopenPending())
                            <span class="badge badge-amber" style="margin-left: 4px; font-size: 11px;" title="{{ __('Waiting for confirmation to reopen') }}">
                                <i class="fa-solid fa-clock"></i> {{ __('Reopen Pending') }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('teacher.attendance.mark', $session) }}" class="btn btn-sm btn-outline">
                            {{ $session->status === 'open' ? __('Continue marking') : __('View') }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">{{ __('No attendance sessions yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection