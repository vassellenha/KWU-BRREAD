@extends('layouts.staff')

@section('title', 'HR — Team & Duty Schedule')

@section('content')

    <div class="flex items-center justify-between mb-space-lg flex-wrap gap-space-sm">
        <div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Team &amp; Duty Schedule</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Staff directory and the automatic {{ \App\Services\ShiftScheduler::GROUP_SIZE }}-person /
                {{ \App\Services\ShiftScheduler::SHIFT_LENGTH_DAYS }}-day duty rotation.
            </p>
        </div>
    </div>

    {{-- STAT SUMMARY --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">groups</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Employees</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['total_employees'] }}, { formatter: (n) => Math.round(n) })">0</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">check_circle</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Active</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['active_employees'] }}, { formatter: (n) => Math.round(n) })">0</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">diversity_3</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Rotation Teams</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['teams'] }}, { formatter: (n) => Math.round(n) })">0</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">event_upcoming</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Next Rotation</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface">
                {{ $stats['next_rotation']?->format('d M') ?? '—' }}
            </p>
        </div>
    </div>

    {{-- TODAY'S DUTY --}}
    <div class="bg-gradient-to-br from-primary-container/25 via-surface-container-lowest to-tertiary-fixed/20 rounded-lg border border-outline-variant/30 p-space-lg mb-space-lg">
        <div class="flex items-center gap-1.5 mb-space-sm">
            <span class="material-symbols-outlined text-[18px] text-primary">today</span>
            <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">On duty today</span>
            <span class="font-label-md text-label-md text-on-surface-variant">
                ({{ $currentShift['period_start']->format('d M') }} – {{ $currentShift['period_end']->format('d M Y') }})
            </span>
        </div>

        @if ($currentShift['employees']->isEmpty())
            <p class="font-body-md text-body-md text-on-surface-variant">No active employees to schedule yet — add your first one below.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-sm">
                @foreach ($currentShift['employees'] as $employee)
                    <div class="flex items-center gap-space-sm bg-surface-container-lowest/90 backdrop-blur-sm rounded-lg pl-space-sm pr-space-md py-space-sm border border-outline-variant/30 shadow-sm">
                        <span class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-lg text-label-lg shrink-0">
                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                        </span>
                        <div class="leading-tight min-w-0">
                            <p class="font-label-lg text-label-lg text-on-surface truncate">{{ $employee->name }}</p>
                            @if ($employee->position)
                                <p class="font-label-md text-label-md text-on-surface-variant truncate">{{ $employee->position }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- UPCOMING ROTATION --}}
    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md mb-space-lg">
        <div class="flex items-center gap-1.5 mb-space-sm">
            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">event_repeat</span>
            <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Upcoming rotation</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-sm">
            @foreach ($upcoming as $i => $period)
                <div class="rounded-DEFAULT border {{ $i === 0 ? 'border-primary bg-primary-container/10' : 'border-outline-variant/30' }} p-space-sm">
                    <p class="font-label-md text-label-md text-on-surface-variant mb-1">
                        {{ $period['period_start']->format('d M') }} – {{ $period['period_end']->format('d M') }}
                        @if ($i === 0)
                            <span class="text-primary font-bold">· Now</span>
                        @endif
                    </p>
                    @if ($period['employees']->isEmpty())
                        <p class="font-body-sm text-body-sm text-on-surface-variant/60">—</p>
                    @else
                        <p class="font-body-sm text-body-sm text-on-surface">
                            {{ $period['employees']->pluck('name')->implode(', ') }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- EMPLOYEE DIRECTORY --}}
    <div x-data="{ showAddForm: false }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
        <div class="flex items-center justify-between p-space-md border-b border-outline-variant/30">
            <span class="font-title-md text-title-md text-on-surface">Employee Directory</span>
            <button type="button" @click="showAddForm = !showAddForm" class="press-scale inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                <span class="material-symbols-outlined text-[18px]" x-text="showAddForm ? 'close' : 'person_add'"></span>
                <span x-text="showAddForm ? 'Cancel' : 'Add Employee'"></span>
            </button>
        </div>

        <div
            x-show="showAddForm" x-cloak
            x-transition:enter="transition-[opacity,transform] duration-200 ease-[var(--ease-out)]"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="p-space-md border-b border-outline-variant/30 bg-surface-container/30"
        >
            <form method="POST" action="{{ route('staff.hr.store') }}" class="flex flex-wrap items-end gap-space-sm">
                @csrf
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                    <input type="text" name="name" required placeholder="e.g. Rizky Joestar" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Position (optional)</label>
                    <input type="text" name="position" placeholder="e.g. Store Crew" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Phone (optional)</label>
                    <input type="text" name="phone" placeholder="+62 812 3456 7890" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                    Save
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left">
                <thead>
                    <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Order</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Name</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Position</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Phone</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Status</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody x-data="{ openId: null }">
                    @forelse ($employees as $employee)
                        <tr class="border-b border-outline-variant/20 last:border-0 {{ ! $employee->is_active ? 'opacity-50' : '' }}">
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">{{ $employee->rotation_order }}</td>
                            <td class="px-space-md py-space-sm font-label-lg text-label-lg text-on-surface">{{ $employee->name }}</td>
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">{{ $employee->position ?? '—' }}</td>
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">{{ $employee->phone ?? '—' }}</td>
                            <td class="px-space-md py-space-sm">
                                @if ($employee->is_active)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-label-md text-label-md">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-space-md py-space-sm text-right">
                                <div class="inline-flex items-center gap-space-xs">
                                    <button type="button" @click="openId = openId === {{ $employee->id }} ? null : {{ $employee->id }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded-DEFAULT bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('staff.hr.toggle', $employee) }}">
                                        @csrf
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded-DEFAULT {{ $employee->is_active ? 'bg-error-container/20 text-error hover:bg-error-container/30' : 'bg-tertiary-fixed text-on-tertiary-fixed-variant hover:opacity-80' }} font-label-md text-label-md transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">{{ $employee->is_active ? 'person_off' : 'person' }}</span>
                                            {{ $employee->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="openId === {{ $employee->id }}" x-cloak>
                            <td colspan="6" class="px-space-md pb-space-md bg-surface-container/30">
                                <div
                                    x-show="openId === {{ $employee->id }}"
                                    x-transition:enter="transition-[opacity,transform] duration-200 ease-[var(--ease-out)]"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition-[opacity,transform] duration-150 ease-[var(--ease-out)]"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 -translate-y-1"
                                >
                                    <form method="POST" action="{{ route('staff.hr.update', $employee) }}" class="flex flex-wrap items-end gap-space-sm pt-space-sm">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                                            <input type="text" name="name" value="{{ $employee->name }}" required class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Position</label>
                                            <input type="text" name="position" value="{{ $employee->position }}" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Phone</label>
                                            <input type="text" name="phone" value="{{ $employee->phone }}" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Rotation order</label>
                                            <input type="number" name="rotation_order" value="{{ $employee->rotation_order }}" min="0" required class="w-24 rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                                            Save
                                        </button>
                                    </form>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-space-xs">
                                        Lower rotation order goes first. Employees are grouped into teams of {{ \App\Services\ShiftScheduler::GROUP_SIZE }} in that order.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-space-md py-space-xl text-center">
                                <p class="font-title-md text-title-md text-on-surface">No employees yet</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Add your first team member to start the duty rotation.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
