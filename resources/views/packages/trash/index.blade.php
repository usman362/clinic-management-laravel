@extends('layouts.app')
@section('title')
    {{ __('Deleted Packages') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')

        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 class="h3 fw-bold mb-1">{{ __('Deleted Packages') }}</h1>
                <p class="text-muted mb-0">
                    Packages and feedback packages that have been deleted by admin.
                    You can restore them or remove them permanently.
                </p>
            </div>
            <a href="{{ route('appointments.index') }}" class="btn btn-outline-primary">
                {{ __('messages.common.back') }}
            </a>
        </div>

        <livewire:trashed-packages-table />

        <div id="notification" class="notification-message"></div>
    </div>
@endsection

@push('scripts')
<script>
    // AP-16: Restore + permanent-delete handlers for the deleted-packages
    // page. Both confirm via SweetAlert (matching the rest of the app),
    // hit their JSON endpoint, and refresh the Livewire table on success.
    (function () {
        function refreshTable() {
            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('refresh');
                Livewire.dispatch('resetPage');
            }
        }

        $(document).off('click.pkgRestore').on('click.pkgRestore', '.package-restore-btn', function () {
            var id = $(this).attr('data-id');
            swal({
                title: 'Restore package?',
                text: 'The package and all its appointments will be reinstated. The patient will be notified.',
                buttons: { confirm: 'Restore', cancel: 'Cancel' },
                reverseButtons: true,
                icon: 'info'
            }).then(function (ok) {
                if (!ok) return;
                $.ajax({
                    url: '{{ url('admin/packages/trash') }}/' + id + '/restore',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function () {
                        swal({ icon: 'success', title: 'Restored', timer: 1500, buttons: false });
                        refreshTable();
                    },
                    error: function (xhr) {
                        swal({ icon: 'error', title: 'Error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Restore failed.' });
                    }
                });
            });
        });

        $(document).off('click.pkgForce').on('click.pkgForce', '.package-force-delete-btn', function () {
            var id = $(this).attr('data-id');
            swal({
                title: 'Permanently delete?',
                text: 'This cannot be undone. All appointments, transactions, consent documents and Google calendar links for this package will be erased.',
                buttons: { confirm: 'Delete forever', cancel: 'Cancel' },
                reverseButtons: true,
                icon: 'warning',
                dangerMode: true
            }).then(function (ok) {
                if (!ok) return;
                $.ajax({
                    url: '{{ url('admin/packages/trash') }}/' + id,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    dataType: 'json',
                    success: function () {
                        swal({ icon: 'success', title: 'Deleted', timer: 1500, buttons: false });
                        refreshTable();
                    },
                    error: function (xhr) {
                        swal({ icon: 'error', title: 'Error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Delete failed.' });
                    }
                });
            });
        });
    })();
</script>
@endpush
