{{-- Success and refusal messages. Business rule refusals arrive as `error`
     from the exception handler in bootstrap/app.php. --}}

@if (session('status'))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-semibold text-emerald-800
                dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" role="status">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-base font-semibold text-rose-800
                dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-800
                dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
        <p class="font-semibold">Please check the following:</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
