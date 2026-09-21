{{-- Module -> Process -> Permission tree markup. Shared by demo/roles and demo/users.
     Checkbox states are set entirely by JS after render (see public/js/demo-rbac.js),
     based on whichever role/user is currently selected in the sidebar. --}}
<div class="rbac-tree" id="rbacTree">
    @foreach ($tree as $module)
        <div class="rbac-module" data-code="{{ $module['code'] }}">
            <div class="rbac-row rbac-row-module">
                <button type="button" class="rbac-caret" aria-label="Toggle">
                    <i class="bi bi-chevron-down"></i>
                </button>
                <input type="checkbox" class="rbac-check rbac-check-module" id="mod-{{ $module['code'] }}"
                       data-level="module" data-code="{{ $module['code'] }}">
                <label for="mod-{{ $module['code'] }}" class="rbac-label rbac-label-module">{{ $module['label'] }}</label>
                <span class="rbac-count badge text-bg-secondary" data-count-for="{{ $module['code'] }}">0/0</span>
                <div class="rbac-row-actions">
                    <button type="button" class="btn btn-link btn-sm rbac-select-all" data-scope="module" data-code="{{ $module['code'] }}">Select all</button>
                    <button type="button" class="btn btn-link btn-sm rbac-select-none" data-scope="module" data-code="{{ $module['code'] }}">Select none</button>
                </div>
            </div>
            <div class="rbac-module-body">
                @foreach ($module['processes'] as $process)
                    <div class="rbac-process" data-code="{{ $process['code'] }}" data-module="{{ $module['code'] }}">
                        <div class="rbac-row rbac-row-process">
                            <button type="button" class="rbac-caret" aria-label="Toggle">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <input type="checkbox" class="rbac-check rbac-check-process" id="proc-{{ $module['code'] }}-{{ $process['code'] }}"
                                   data-level="process" data-code="{{ $process['code'] }}" data-module="{{ $module['code'] }}">
                            <label for="proc-{{ $module['code'] }}-{{ $process['code'] }}" class="rbac-label rbac-label-process">{{ $process['label'] }}</label>
                            <span class="rbac-count badge text-bg-light" data-count-for="{{ $module['code'] }}.{{ $process['code'] }}">0/0</span>
                            <div class="rbac-row-actions">
                                <button type="button" class="btn btn-link btn-sm rbac-select-all" data-scope="process" data-code="{{ $process['code'] }}" data-module="{{ $module['code'] }}">Select all</button>
                                <button type="button" class="btn btn-link btn-sm rbac-select-none" data-scope="process" data-code="{{ $process['code'] }}" data-module="{{ $module['code'] }}">Select none</button>
                            </div>
                        </div>
                        <div class="rbac-process-body">
                            @foreach ($process['permissions'] as $permission)
                                <div class="rbac-row rbac-row-perm" data-code="{{ $permission['code'] }}">
                                    <input type="checkbox" class="rbac-check rbac-check-perm" id="perm-{{ $permission['code'] }}"
                                           data-level="perm" data-code="{{ $permission['code'] }}"
                                           data-process="{{ $process['code'] }}" data-module="{{ $module['code'] }}">
                                    <label for="perm-{{ $permission['code'] }}" class="rbac-label rbac-label-perm">
                                        {{ $permission['label'] }}
                                        <code class="rbac-perm-code">{{ $permission['code'] }}</code>
                                    </label>
                                    <span class="rbac-state-badge"></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
