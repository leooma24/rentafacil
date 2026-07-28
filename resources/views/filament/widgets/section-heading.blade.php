<x-filament-widgets::widget>
    <div style="padding-top: .5rem;">
        <h2 style="font-size: .8125rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin: 0;">
            {{ $titulo }}
        </h2>

        @if ($descripcion)
            <p style="font-size: .875rem; color: #94a3b8; margin: .25rem 0 0;">{{ $descripcion }}</p>
        @endif

        <div style="height: 1px; background: rgba(100,116,139,.2); margin-top: .75rem;"></div>
    </div>
</x-filament-widgets::widget>
