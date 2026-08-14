<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0f172a">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ $settings->restaurantName() }}</title>

{{-- Applied before first paint so a dark terminal never flashes white. --}}
<script>
    if (localStorage.getItem('restro-theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
