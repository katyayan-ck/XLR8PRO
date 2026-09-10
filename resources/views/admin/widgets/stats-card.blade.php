<a href="{{ $link ?? 'javascript:void(0)' }}" class="text-decoration-none">
    <div class="card stats-card border-left-{{ $color }} shadow-sm h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="text-muted small text-uppercase mb-1">{{ $title }}</h6>
                    <div class="stat-value text-{{ $color }}">{{ $value }}</div>
                    @if (isset($subtitle))
                        <p class="text-muted small mb-0"><i class="la la-clock-o"></i> {{ $subtitle }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <i class="la {{ $icon }} text-{{ $color }} opacity-5" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
</a>
