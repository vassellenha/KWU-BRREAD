@include('errors.partials.page', [
    'code' => '404',
    'icon' => 'search',
    'heading' => 'Page not found',
    'message' => 'This page does not exist, or it moved. Try the menu or head back home.',
    'actionUrl' => route('home'),
    'actionLabel' => 'Back to home',
])
