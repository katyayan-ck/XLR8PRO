<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="la {{ $icon }}"></i> {{ $title }}</h6>
    </div>
    <div class="card-body p-0">
        @forelse($items as $item)
            <div class="activity-item px-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        @if ($type === 'enquiry')
                            <strong>{{ $item->customer_name ?? 'N/A' }}</strong>
                            <p class="text-muted small mb-1">
                                {{ Str::limit($item->message ?? ($item->subject ?? 'No details'), 50) }}</p>
                        @elseif($type === 'booking')
                            <strong>{{ $item->customer_name ?? 'N/A' }}</strong>
                            <p class="text-muted small mb-1">Vehicle Booking</p>
                        @endif
                        <small class="text-muted"><i class="la la-clock-o"></i>
                            {{ $item->created_at->diffForHumans() }}</small>
                    </div>
                    <div>
                        <a href="{{ backpack_url($type . '/' . $item->id . '/show') }}" class="btn btn-sm btn-light"
                            title="View">
                            <i class="la la-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-3">
                <p class="text-muted small text-center mb-0"><i class="la la-inbox"></i> No recent {{ $title }}
                </p>
            </div>
        @endforelse
    </div>
</div>
