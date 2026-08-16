@props(['class' => 'size-24'])

{{-- A friendly mark for the customer's screen, drawn in the logo's gold so it
     belongs to the restaurant rather than looking like a stock emoji. The
     gentle bob is suppressed for anyone who has asked for less motion. --}}
<svg {{ $attributes->merge(['class' => $class]) }}
     viewBox="0 0 100 100"
     fill="none"
     role="img"
     aria-label="Thank you">
    <circle cx="50" cy="50" r="44" stroke="currentColor" stroke-width="6"/>
    <circle cx="35" cy="40" r="5.5" fill="currentColor"/>
    <circle cx="65" cy="40" r="5.5" fill="currentColor"/>
    <path d="M30 60c4.8 8.6 12 12.9 20 12.9S65.2 68.6 70 60"
          stroke="currentColor"
          stroke-width="6"
          stroke-linecap="round"/>
</svg>
