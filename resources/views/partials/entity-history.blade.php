<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="la la-comments"></i> Communication & History</h5>
    </div>
    <div class="card-body p-0">
        @if($threads->isEmpty())
        <div class="p-4 text-center text-muted">No history yet.</div>
        @else
        <div class="history-timeline p-3">
            @foreach($threads as $thread)
            @include('partials.entity-history-item', ['thread' => $thread])
            @endforeach
        </div>
        @endif
    </div>
</div>

<div class="modal fade" id="historyMediaModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Preview</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body text-center">

                <img id="historyModalImage" style="display:none;max-width:100%;">

                <iframe id="historyModalPdf" style="display:none;width:100%;height:80vh;">
                </iframe>

            </div>

        </div>
    </div>
</div>
<script>
    $(document).on(
    'click',
    '.history-media-thumb,.pdf-preview-btn',
    function(){

        let url  = $(this).data('url');
        let type = $(this).data('type');

        $('#historyModalImage').hide();
        $('#historyModalPdf').hide();

        if(type === 'image'){
            $('#historyModalImage')
                .attr('src',url)
                .show();
        }
        else{
            $('#historyModalPdf')
                .attr('src',url)
                .show();
        }

        $('#historyMediaModal').modal('show');
    }
);

</script>