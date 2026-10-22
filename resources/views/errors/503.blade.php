@php
    $title ??= 'Service temporarily unavailable';
    $message ??= 'The service is temporarily unavailable. Please try again shortly.';
@endphp

<x-error-page
    code='503'
    :title='$title'
    :message='$message'
/>
