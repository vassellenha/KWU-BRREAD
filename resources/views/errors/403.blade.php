@include('errors.partials.page', [
    'code' => '403',
    'icon' => 'lock',
    'heading' => 'This module is not on your account',
    'message' => 'Your role cannot open this page. Head back to the module you do have access to.',
    'actionUrl' => auth()->check() ? route(auth()->user()->homeRouteName()) : route('staff.login'),
    'actionLabel' => auth()->check() ? 'Back to my module' : 'Go to sign in',
])
