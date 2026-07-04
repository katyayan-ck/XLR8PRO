@if (backpack_theme_config('show_powered_by') || backpack_theme_config('developer_link'))
<footer
    class="d-print-none {{ backpack_theme_config('classes.footer') ?? 'footer app-footer sticky-footer bg-transparent p-3 border-top-0' }}">
    <div class="{{ backpack_theme_config('options.useFluidContainers') ? 'container-fluid' : 'container-xxl' }}">
        <div
            class="@if (backpack_theme_config('developer_link') && backpack_theme_config('developer_name') && backpack_theme_config('show_powered_by')) row @endif text-center align-items-center flex-row-reverse">
            @if (backpack_theme_config('show_powered_by'))
            <div class="col-lg-auto ms-lg-auto">
                <ul class="list-inline list-inline-dots mb-0">
                    <li class="list-inline-item">
                        <b>{{ trans('backpack::base.powered_by') }}</b>
                        <a rel="noopener" target="_blank">
                            {{-- <b>
                                Insightech
                            </b> --}}
                            {{-- <Payment Details (Locked)> --}}
                                {{-- </Payment> --}}
                            <a href="https://insightechindia.in" rel="noopener" target="_blank">
                                <img src="{{ asset('images/ins_logo_full.png') }}" alt="Insightech Logo"
                                    style="height:20px;">
                            </a>
                    </li>
                </ul>
            </div>
            @endif
            @if (backpack_theme_config('developer_link') && backpack_theme_config('developer_name'))
            <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                <ul class="list-inline list-inline-dots mb-0">
                    <li class="list-inline-item">
                        {{-- {{ trans('backpack::base.handcrafted_by') }} --}}
                        <b>Made for</b>
                        <a href="{{ backpack_theme_config('developer_link') }}" rel="noopener" target="_blank">{{
                            backpack_theme_config('developer_name') }}</a>
                    </li>
                </ul>
            </div>
            @endif
        </div>
    </div>
</footer>
@endif