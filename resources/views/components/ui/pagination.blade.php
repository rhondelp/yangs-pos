{{-- Default paginator view (set in AppServiceProvider): {{ $items->links() }} --}}
@if ($paginator->hasPages())
    @php($cell = 'inline-flex min-h-touch min-w-touch items-center justify-center rounded-control px-3 text-base tabular')
    <nav role="navigation" aria-label="{{ __('Page navigation') }}" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">
            {{ __('Showing :first–:last of :total', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $cell }} text-line" aria-disabled="true"><x-ui.icon name="chevron-left" class="size-5" /><span class="sr-only">{{ __('Previous') }}</span></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $cell }} text-fg hover:bg-raised"><x-ui.icon name="chevron-left" class="size-5" /><span class="sr-only">{{ __('Previous') }}</span></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="{{ $cell }} text-muted">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $cell }} bg-accent font-semibold text-on-accent">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $cell }} text-fg hover:bg-raised">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $cell }} text-fg hover:bg-raised"><x-ui.icon name="chevron-right" class="size-5" /><span class="sr-only">{{ __('Next') }}</span></a>
                @else
                    <span class="{{ $cell }} text-line" aria-disabled="true"><x-ui.icon name="chevron-right" class="size-5" /><span class="sr-only">{{ __('Next') }}</span></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
