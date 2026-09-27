<div class="d-flex align-items-center gap-3 me-3">
    <x-notify.bell kind="A" icon="la-exclamation-triangle" color="danger" label="Alerts" />
    <x-notify.bell kind="N" icon="la-bell" color="warning" label="Notifications" />
    <x-notify.bell kind="M" icon="la-envelope" color="success" label="Messages" />
</div>

<style>
    @media (max-width: 991.98px) {
        .notify-bell .dropdown-menu {
            width: 92vw !important;
            max-width: 340px !important;
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
            margin-top: 8px !important;
        }
    }
</style>
