<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Official Attendance Register') }} — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', 'Battambang', system-ui, -apple-system, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Screen Toolbar */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .toolbar-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toolbar-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }
        .btn-primary {
            background-color: #0284c7;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #0369a1;
        }
        .btn-secondary {
            background-color: #ffffff;
            color: #475569;
            border-color: #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
            color: #1e293b;
        }

        /* Sheet Document Container */
        .page-sheet {
            max-width: 1200px;
            margin: 24px auto;
            background: #ffffff;
            padding: 32px 36px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        /* Official Header */
        .official-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .school-info {
            display: flex;
            gap: 14px;
            align-items: center;
        }
        .school-logo-badge {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            background: #0284c7;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .school-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .school-sub {
            font-size: 11px;
            color: #475569;
            margin-top: 2px;
        }
        .report-meta-box {
            text-align: right;
            font-size: 11px;
            color: #475569;
        }
        .report-title-main {
            font-size: 16px;
            font-weight: 700;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        /* Filter Meta Grid */
        .meta-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        .meta-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
        }
        .meta-value {
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 1px;
        }

        /* Executive KPI Summary */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .kpi-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            background: #ffffff;
            text-align: center;
        }
        .kpi-card.present { border-top: 3px solid #16a34a; }
        .kpi-card.late { border-top: 3px solid #d97706; }
        .kpi-card.excused { border-top: 3px solid #0284c7; }
        .kpi-card.unexcused { border-top: 3px solid #dc2626; }
        .kpi-card.total { border-top: 3px solid #475569; }
        .kpi-card.rate { border-top: 3px solid #059669; }

        .kpi-num {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .kpi-title {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-top: 2px;
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.3px;
        }
        tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            border: 1px solid transparent;
        }
        .status-present {
            background-color: #dcfce7;
            color: #15803d;
            border-color: #86efac;
        }
        .status-late {
            background-color: #fef3c7;
            color: #b45309;
            border-color: #fde68a;
        }
        .status-excused {
            background-color: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }
        .status-absent {
            background-color: #fee2e2;
            color: #b91c1c;
            border-color: #fca5a5;
        }
        .status-pending {
            background-color: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }

        /* Sign-off certification section */
        .signoff-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 36px;
            padding-top: 18px;
            border-top: 1px dashed #cbd5e1;
            page-break-inside: avoid;
        }
        .signoff-box {
            text-align: center;
        }
        .signoff-role {
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 50px;
        }
        .signoff-line {
            border-bottom: 1px solid #0f172a;
            margin: 0 auto 6px;
            width: 80%;
        }
        .signoff-name {
            font-size: 10px;
            color: #64748b;
        }

        /* Print Media Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                font-size: 10px;
            }
            .page-sheet {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            @page {
                size: A4 landscape;
                margin: 10mm 8mm;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
            .signoff-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Toolbar (Hidden when printing or saving as PDF) -->
    <div class="toolbar no-print">
        <div class="toolbar-title">
            <i class="fa-solid fa-file-pdf" style="color:#0284c7;"></i>
            <span>{{ __('Printable Attendance Report') }}</span>
        </div>
        <div class="toolbar-actions">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> {{ __('Print / Save as PDF') }}
            </button>
            <a href="{{ route('admin.reports.index', request()->query()) }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Reports') }}
            </a>
        </div>
    </div>

    <div class="page-sheet">
        <!-- Header -->
        <div class="official-header">
            <div class="school-info">
                <div class="school-logo-badge">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <div class="school-name">{{ \App\Models\SchoolSetting::get('school_name', config('app.name', 'Attendance System')) }}</div>
                    <div class="school-sub">
                        {{ \App\Models\SchoolSetting::get('academic_term', 'Semester 1') }} &bull; 
                        {{ \App\Models\SchoolSetting::get('academic_year', '2026-2027') }} &bull;
                        {{ __('Official Student Attendance Register & Audit Report') }}
                    </div>
                </div>
            </div>
            <div class="report-meta-box">
                <div class="report-title-main">{{ __('Official Register') }}</div>
                <div><strong>{{ __('Date:') }}</strong> {{ now()->format('Y-m-d H:i') }}</div>
                <div><strong>{{ __('Generated By:') }}</strong> {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</div>
            </div>
        </div>

        <!-- Filter Metadata -->
        <div class="meta-strip">
            <div class="meta-item">
                <span class="meta-label">{{ __('Period Range') }}</span>
                <span class="meta-value">
                    @if($startDate->isSameDay($endDate))
                        {{ $startDate->format('d M Y') }}
                    @else
                        {{ $startDate->format('d M Y') }} – {{ $endDate->format('d M Y') }}
                    @endif
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">{{ __('Campus') }}</span>
                <span class="meta-value">{{ $selectedCampus ? $selectedCampus->code.' - '.$selectedCampus->name : __('All Campuses') }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">{{ __('Class & Subject') }}</span>
                <span class="meta-value">
                    {{ $selectedClass ? $selectedClass->name : __('All Classes') }}
                    @if($subject)
                        &bull; {{ $subject === 'homeroom' ? __('Homeroom') : $subject }}
                    @endif
                    @if($periodNumber)
                        (#{{ $periodNumber }})
                    @endif
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">{{ __('Status Filter') }}</span>
                <span class="meta-value">{{ $finalStatus ? ucfirst(str_replace('_', ' ', $finalStatus)) : __('All Statuses') }}</span>
            </div>
        </div>

        <!-- KPI Strip -->
        <div class="kpi-row">
            <div class="kpi-card total">
                <div class="kpi-num">{{ $totalRecords }}</div>
                <div class="kpi-title">{{ __('Total Records') }}</div>
            </div>
            <div class="kpi-card present">
                <div class="kpi-num" style="color:#16a34a;">{{ $summary['present'] }}</div>
                <div class="kpi-title">{{ __('Present') }}</div>
            </div>
            <div class="kpi-card late">
                <div class="kpi-num" style="color:#d97706;">{{ $summary['late'] }}</div>
                <div class="kpi-title">{{ __('Late') }}</div>
            </div>
            <div class="kpi-card excused">
                <div class="kpi-num" style="color:#0284c7;">{{ $summary['excused'] }}</div>
                <div class="kpi-title">{{ __('Excused') }}</div>
            </div>
            <div class="kpi-card unexcused">
                <div class="kpi-num" style="color:#dc2626;">{{ $summary['absent_without_permission'] }}</div>
                <div class="kpi-title">{{ __('Unexcused') }}</div>
            </div>
            <div class="kpi-card rate">
                <div class="kpi-num" style="color:#059669;">{{ $attendanceRate }}%</div>
                <div class="kpi-title">{{ __('Attendance Rate') }}</div>
            </div>
        </div>

        @if(!empty($subjectBreakdown) && count($subjectBreakdown) > 1)
        <!-- Subject Breakdown Table -->
        <div style="margin-bottom:16px;">
            <div style="font-size:12px; font-weight:700; color:#1e293b; text-transform:uppercase; margin-bottom:6px;">
                {{ __('Subject & Period Attendance Breakdown') }}
            </div>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Subject / Period') }}</th>
                        <th style="text-align:center;">{{ __('Sessions') }}</th>
                        <th style="text-align:center;">{{ __('Total') }}</th>
                        <th style="text-align:center;">{{ __('Present') }}</th>
                        <th style="text-align:center;">{{ __('Late') }}</th>
                        <th style="text-align:center;">{{ __('Excused') }}</th>
                        <th style="text-align:center;">{{ __('Unexcused') }}</th>
                        <th style="text-align:right;">{{ __('Attendance Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjectBreakdown as $sb)
                    <tr>
                        <td><strong>{{ $sb['name'] }}</strong> ({{ $sb['period'] }})</td>
                        <td style="text-align:center;">{{ $sb['sessions_count'] }}</td>
                        <td style="text-align:center;">{{ $sb['total'] }}</td>
                        <td style="text-align:center; color:#16a34a; font-weight:600;">{{ $sb['present'] }}</td>
                        <td style="text-align:center; color:#d97706; font-weight:600;">{{ $sb['late'] }}</td>
                        <td style="text-align:center; color:#0284c7; font-weight:600;">{{ $sb['excused'] }}</td>
                        <td style="text-align:center; color:#dc2626; font-weight:600;">{{ $sb['unexcused'] }}</td>
                        <td style="text-align:right; font-weight:700;">{{ $sb['attendance_rate'] }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Main Student Register -->
        <div>
            <div style="font-size:12px; font-weight:700; color:#1e293b; text-transform:uppercase; margin-bottom:6px;">
                {{ __('Attendance Register Log') }} ({{ count($rows) }} {{ __('records') }})
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:30px; text-align:center;">#</th>
                        <th style="width:75px;">{{ __('Date') }}</th>
                        <th style="width:85px;">{{ __('Class') }}</th>
                        <th style="width:110px;">{{ __('Period / Subject') }}</th>
                        <th style="width:85px;">{{ __('Student ID') }}</th>
                        <th>{{ __('Student Name') }}</th>
                        <th style="width:75px; text-align:center;">{{ __('Teacher Mark') }}</th>
                        <th style="width:70px; text-align:center;">{{ __('Arrival') }}</th>
                        <th style="width:90px; text-align:center;">{{ __('Final Status') }}</th>
                        <th>{{ __('Note / Finalizer') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $idx => $att)
                    @php
                        $session = $att->session;
                        $status = $att->final_status ?? ($att->status ? 'pending' : null);
                    @endphp
                    <tr>
                        <td style="text-align:center; color:#64748b;">{{ $idx + 1 }}</td>
                        <td>{{ $session ? $session->session_date->format('Y-m-d') : '—' }}</td>
                        <td><strong>{{ $session?->classRoom?->name ?? '—' }}</strong></td>
                        <td>
                            @if($session?->schedule)
                                #{{ $session->schedule->period_number }} {{ $session->schedule->subject }}
                            @else
                                {{ __('Daily Homeroom') }}
                            @endif
                        </td>
                        <td style="font-family:monospace; color:#475569;">{{ $att->student->student_id ?? '—' }}</td>
                        <td><strong>{{ $att->student->name ?? '—' }}</strong></td>
                        <td style="text-align:center;">
                            {{ $att->status ? ucfirst($att->status) : '—' }}
                        </td>
                        <td style="text-align:center;">
                            {{ $att->arrived_at ? $att->arrived_at->format('H:i') : '—' }}
                            @if($att->minutes_late)
                                <div style="font-size:9px; color:#d97706;">+{{ $att->minutes_late }}m</div>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            @if($status === 'present')
                                <span class="status-pill status-present">{{ __('Present') }}</span>
                            @elseif($status === 'late')
                                <span class="status-pill status-late">{{ __('Late') }}</span>
                            @elseif($status === 'excused')
                                <span class="status-pill status-excused">{{ __('Excused') }}</span>
                            @elseif($status === 'absent_without_permission' || $status === 'absent')
                                <span class="status-pill status-absent">{{ __('Absent') }}</span>
                            @elseif($status === 'pending')
                                <span class="status-pill status-pending">{{ __('Pending') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($att->admin_note)
                                <span>{{ $att->admin_note }}</span>
                            @endif
                            @if($att->finalizer)
                                <div style="font-size:9px; color:#64748b;">{{ __('By:') }} {{ $att->finalizer->name }}</div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" style="text-align:center; padding:18px; color:#64748b;">
                            {{ __('No attendance records match the specified filters.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Official Sign-off & Stamp Block -->
        <div class="signoff-section">
            <div class="signoff-box">
                <div class="signoff-role">{{ __('Homeroom / Subject Teacher') }}</div>
                <div class="signoff-line"></div>
                <div class="signoff-name">{{ __('Date & Signature') }}</div>
            </div>
            <div class="signoff-box">
                <div class="signoff-role">{{ __('Head of Student Affairs') }}</div>
                <div class="signoff-line"></div>
                <div class="signoff-name">{{ __('Date & Signature') }}</div>
            </div>
            <div class="signoff-box">
                <div class="signoff-role">{{ __('Campus Director / Principal') }}</div>
                <div class="signoff-line"></div>
                <div class="signoff-name">{{ __('Official Seal & Signature') }}</div>
            </div>
        </div>
    </div>

</body>
</html>
