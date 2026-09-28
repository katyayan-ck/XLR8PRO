{{-- Effective data access (DEC-071) — what a user's saved scopes resolve to. @param \App\Services\IAM\DataScope\ScopeSet $scope --}}
@if ($scope->isUnrestricted())
    <p class="text-body-secondary small mb-0">Sees all data (no scope restriction, superadmin or bypass).</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-vcenter mb-0">
            <tbody>
                @foreach (\App\Services\IAM\MyAccountService::SCOPE_LEVELS as $level => $label)
                    @php($codes = $scope->allowed($level))
                    <tr>
                        <th scope="row" class="text-nowrap w-25">{{ $label }}</th>
                        <td class="text-break">
                            @if ($codes === null)
                                <span class="badge bg-success-lt">All</span>
                            @elseif ($codes === [])
                                <span class="badge bg-danger-lt">None</span>
                            @else
                                <span class="text-body-secondary small">{{ count($codes) }}:</span>
                                {{ implode(', ', array_slice($codes, 0, 12)) }}@if (count($codes) > 12) … @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-body-secondary small mt-2 mb-0">A parent covers all of its children unless a child is assigned. Records
        with an empty code on a level are {{ \App\Support\Facades\DataScope::unassignedVisible() ? 'shown' : 'hidden' }}.</p>
@endif
