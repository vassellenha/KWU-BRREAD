@include('errors.partials.page', [
    'code' => '419',
    'icon' => 'schedule',
    'heading' => 'This page sat open too long',
    'message' => 'Your session expired, so nothing was saved. Reload the page and try that again.',
    'actionUrl' => auth()->check() ? route(auth()->user()->homeRouteName()) : url()->previous(),
    'actionLabel' => 'Reload',
])
