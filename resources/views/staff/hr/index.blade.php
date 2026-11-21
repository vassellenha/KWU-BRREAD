@extends('layouts.staff')

@section('title', 'HR — Team & Duty Schedule')

@section('content')

    @php
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $isRestoredAddForm = old('_form') === 'add';
    @endphp

    <x-staff.page-header icon="groups" title="Team & Duty Schedule">
        Staff directory and the automatic {{ \App\Services\ShiftScheduler::GROUP_SIZE }}-person /
        {{ \App\Services\ShiftScheduler::SHIFT_LENGTH_DAYS }}-day duty rotation.
    </x-staff.page-header>

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

    {{-- EMPLOYEE DIRECTORY: the rows below are one "save all" form. --}}
    <div x-data="batchForm({ url: @js(route('staff.hr.bulk')) })">
    @include('partials.batch-hint')
    <p id="grid-help" class="sr-only">Use the arrow keys to move between cells. Up and Down change row, Left and Right change cell. Shift plus Up or Down changes a number. Press Control S to save all changes.</p>
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

        <div x-grid-nav class="relative overflow-x-auto">
            <table class="w-full min-w-[860px] text-left" aria-describedby="grid-help">
                <thead>
                    <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Order</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Name</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Position</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Phone</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Active</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Revert</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr data-grid-row data-batch-row="{{ $employee->id }}" data-version="{{ $employee->rowVersion() }}" class="border-b border-outline-variant/20 last:border-0">
                            <td class="px-space-md py-space-sm align-top" data-batch-title>
                                <x-staff.stepper :label="'rotation order of '.$employee->name">
                                    <input type="text" inputmode="numeric" data-filter="integer" maxlength="4" data-batch-field="rotation_order" data-cell="rotation_order" data-stepper="1" data-min="0" data-max="9999" value="{{ $employee->rotation_order }}" aria-label="Rotation order of {{ $employee->name }}" class="w-20 tabular-nums {{ $fieldClasses }}">
                                </x-staff.stepper>
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <input type="text" data-filter="name" maxlength="255" data-batch-field="name" data-cell="name" value="{{ $employee->name }}" aria-label="Name" class="w-56 {{ $fieldClasses }}">
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <input type="text" maxlength="255" data-batch-field="position" data-cell="position" value="{{ $employee->position }}" aria-label="Position of {{ $employee->name }}" placeholder="Not set" class="w-48 {{ $fieldClasses }}">
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <input type="tel" inputmode="tel" data-filter="phone" maxlength="30" data-batch-field="phone" data-cell="phone" value="{{ $employee->phone }}" aria-label="Phone of {{ $employee->name }}" placeholder="Not set" class="w-48 tabular-nums {{ $fieldClasses }}">
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <label class="batch-switch" title="Active employees are part of the duty rotation">
                                    <input type="checkbox" class="peer sr-only" data-batch-field="is_active" data-cell="is_active" data-on="Active" data-off="Inactive" aria-label="{{ $employee->name }} is active" @checked($employee->is_active)>
                                    <span class="batch-switch__track" aria-hidden="true"></span>
                                </label>
                            </td>
                            <td class="px-space-md py-space-sm align-top text-right">
                                <button type="button" data-batch-revert @click="revertRow($el.closest('[data-batch-row]'))" aria-label="Revert {{ $employee->name }}" class="press-scale items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">undo</span>
                                    Revert
                                </button>
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
        <p class="p-space-md border-t border-outline-variant/30 font-body-sm text-body-sm text-on-surface-variant/80">
            Lower rotation order goes first. {{ \App\Services\ShiftScheduler::GROUP_SIZE }} active employees cover each shift, in that order.
        </p>
    </div>

    @include('partials.batch-bar')
    </div>

@endsection
