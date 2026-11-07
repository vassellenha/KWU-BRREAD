@extends('layouts.staff')

@section('title', 'HR — Team & Duty Schedule')

@section('content')

    @php
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $isRestoredAddForm = old('_form') === 'add';
    @endphp

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
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['total_employees'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">check_circle</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Active</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['active_employees'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">today</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">On Duty Now</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['on_duty'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">event_upcoming</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Next Rotation</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['next_rotation']->format('d M') }}</p>
        </div>
    </div>

    {{-- TODAY'S DUTY --}}
    <div class="bg-gradient-to-br from-primary-container/25 via-surface-container-lowest to-tertiary-fixed/20 rounded-lg border border-outline-variant/30 p-space-lg mb-space-lg">
        <div class="flex items-center flex-wrap gap-x-1.5 mb-space-sm">
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
                    <div class="flex items-center gap-space-sm bg-surface-container-lowest/90 rounded-lg pl-space-sm pr-space-md py-space-sm border border-outline-variant/30 shadow-sm">
                        <span class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-lg text-label-lg shrink-0">
                            {{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}
                        </span>
                        <div class="leading-tight min-w-0">
                            <p class="font-label-lg text-label-lg text-on-surface truncate" title="{{ $employee->name }}">{{ $employee->name }}</p>
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
                <div class="rounded border {{ $i === 0 ? 'border-primary bg-primary-container/10' : 'border-outline-variant/30' }} p-space-sm">
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
    <div x-data="{ showAddForm: @js($isRestoredAddForm) }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
        <div class="flex items-center justify-between gap-space-sm p-space-md border-b border-outline-variant/30">
            <h2 class="font-title-md text-title-md text-on-surface">Employee Directory</h2>
            <button type="button" @click="showAddForm = !showAddForm" :aria-expanded="showAddForm" class="press-scale shrink-0 inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                <span class="material-symbols-outlined text-[18px]" x-text="showAddForm ? 'close' : 'person_add'">person_add</span>
                <span x-text="showAddForm ? 'Cancel' : 'Add Employee'">Add Employee</span>
            </button>
        </div>

        <div x-show="showAddForm" x-cloak class="p-space-md border-b border-outline-variant/30 bg-surface-container/30">
            <form method="POST" action="{{ route('staff.hr.store') }}" class="flex flex-wrap items-end gap-space-sm">
                @csrf
                <input type="hidden" name="_form" value="add">
                <div>
                    <label for="new-name" class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                    <input id="new-name" type="text" name="name" data-filter="name" maxlength="255" value="{{ $isRestoredAddForm ? old('name') : '' }}" required placeholder="e.g. Rizky Joestar" class="{{ $fieldClasses }}">
                </div>
                <div>
                    <label for="new-position" class="block font-label-md text-label-md text-on-surface-variant mb-1">Position (optional)</label>
                    <input id="new-position" type="text" name="position" maxlength="255" value="{{ $isRestoredAddForm ? old('position') : '' }}" placeholder="e.g. Store Crew" class="{{ $fieldClasses }}">
                </div>
                <div>
                    <label for="new-phone" class="block font-label-md text-label-md text-on-surface-variant mb-1">Phone (optional)</label>
                    <input id="new-phone" type="tel" inputmode="tel" name="phone" data-filter="phone" maxlength="30" value="{{ $isRestoredAddForm ? old('phone') : '' }}" placeholder="+62 812 3456 7890" class="{{ $fieldClasses }}">
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
                <tbody x-data="{ openId: {{ old('_row') ? (int) old('_row') : 'null' }} }">
                    @forelse ($employees as $employee)
                        @php $isRestoredRow = (int) old('_row') === $employee->id; @endphp
                        <tr class="border-b border-outline-variant/20 last:border-0">
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant tabular-nums {{ $employee->is_active ? '' : 'opacity-50' }}">{{ $employee->rotation_order }}</td>
                            <td class="px-space-md py-space-sm font-label-lg text-label-lg text-on-surface {{ $employee->is_active ? '' : 'opacity-50' }}">{{ $employee->name }}</td>
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant {{ $employee->is_active ? '' : 'opacity-50' }}">{{ $employee->position ?? '—' }}</td>
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant tabular-nums {{ $employee->is_active ? '' : 'opacity-50' }}">{{ $employee->phone ?? '—' }}</td>
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
                                    <button type="button" @click="openId = openId === {{ $employee->id }} ? null : {{ $employee->id }}" :aria-expanded="openId === {{ $employee->id }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('staff.hr.toggle', $employee) }}">
                                        @csrf
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded {{ $employee->is_active ? 'text-error hover:bg-error-container/30' : 'bg-tertiary-fixed text-on-tertiary-fixed-variant hover:opacity-80' }} font-label-md text-label-md transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">{{ $employee->is_active ? 'person_off' : 'person' }}</span>
                                            {{ $employee->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="openId === {{ $employee->id }}" x-cloak>
                            <td colspan="6" class="px-space-md pb-space-md bg-surface-container/30">
                                <form method="POST" action="{{ route('staff.hr.update', $employee) }}" class="flex flex-wrap items-end gap-space-sm pt-space-sm">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="_row" value="{{ $employee->id }}">
                                    <div>
                                        <label for="name-{{ $employee->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                                        <input id="name-{{ $employee->id }}" type="text" name="name" data-filter="name" maxlength="255" value="{{ $isRestoredRow ? old('name') : $employee->name }}" required class="{{ $fieldClasses }}">
                                    </div>
                                    <div>
                                        <label for="position-{{ $employee->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Position</label>
                                        <input id="position-{{ $employee->id }}" type="text" name="position" maxlength="255" value="{{ $isRestoredRow ? old('position') : $employee->position }}" class="{{ $fieldClasses }}">
                                    </div>
                                    <div>
                                        <label for="phone-{{ $employee->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Phone</label>
                                        <input id="phone-{{ $employee->id }}" type="tel" inputmode="tel" name="phone" data-filter="phone" maxlength="30" value="{{ $isRestoredRow ? old('phone') : $employee->phone }}" class="{{ $fieldClasses }}">
                                    </div>
                                    <div>
                                        <label for="order-{{ $employee->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Rotation order</label>
                                        <input id="order-{{ $employee->id }}" type="text" inputmode="numeric" data-filter="integer" name="rotation_order" maxlength="4" value="{{ $isRestoredRow ? old('rotation_order') : $employee->rotation_order }}" required class="w-24 tabular-nums {{ $fieldClasses }}">
                                    </div>
                                    <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                                        Save
                                    </button>
                                </form>
                                <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-space-xs">
                                    Lower rotation order goes first. {{ \App\Services\ShiftScheduler::GROUP_SIZE }} active employees cover each shift, in that order.
                                </p>
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
