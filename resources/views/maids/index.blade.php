@extends('layouts.app')
@section('title', 'Maids')
@section('eyebrow', 'Workforce operations')
@section('heading', 'Maids Management')
@section('subtitle', 'Operational directory for maids, including identity, schedule, partner, and documents.')
@section('content')
    <section class="overview-metrics" aria-label="Business summary">
        <x-metrics title="Total Maids" type="revenue" value="{{ $total_maids }}" />
        <x-metrics title="Active Maids" type="revenue" value="{{ $active_maids }}" />
        <x-metrics title="Available Today" type="revenue" value="{{ $avaliable_today }}" />
        <x-metrics title="Done Orders" type="revenue" value="{{ $done_orders }}" />
        <x-metrics title="Documents Pending" type="revenue" value="{{ $doc_pending }}" />
    </section>

    <div class="workspace-page users-page">
        <div class="page-actions">
            <button class="ui-button ghost" type="button" data-export-table="maids-directory">Export</button>
            <a class="ui-button accent" href="{{ route('admin.maids.create') }}">Add Maid</a>
        </div>

        @if (session('status'))
            <div class="catalog-notice">{{ session('status') }}</div>
        @endif

        <section class="surface-card">
            <div class="card-head catalog-head">
                <div>
                    <p class="eyebrow">Workforce directory</p>
                    <h2>Available Maids In System</h2>
                </div>
                <div class="catalog-filters" data-table-filters>
                    <input data-table-search type="search" placeholder="Name, ID, or partner">
                    <select data-maid-status aria-label="Status filter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="expired">Expired</option>
                    </select>
                    <button class="ui-button ghost" data-table-reset type="button">Reset</button>
                </div>
            </div>
            <div class="data-table-shell table-wrapper">
                <form class="toolbar" method="GET"><label class="field"><span>Partner</span><select name="partner_id" onchange="this.form.submit()"><option value="">All partners</option>@foreach ($partners as $partner)<option value="{{ $partner->id }}" @selected((string) request('partner_id') === (string) $partner->id)>{{ $partner->name }}</option>@endforeach</select></label><label><input type="checkbox" name="drafts" value="1" @checked(request()->boolean('drafts')) onchange="this.form.submit()"> Show drafts</label></form>
                <table id="maids-directory" data-yajra-table data-source="{{ route('admin.maids.data', request()->only(['partner_id', 'drafts'])) }}" data-kind="maids">
                    <thead>
                        <tr>
                            <th>Maid</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Gender</th>
                            <th>Off day</th>
                            <th>Partner</th>
                            <th>Salary</th>
                            <th>Done orders</th>
                            <th>Documents</th>
                            <th>Start date</th>
                            <th>Age</th>
                            <th>Address</th>
                            <th>Personal ID</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="empty-table">Loading maids</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="yajra-table-footer" data-table-footer></div>
        </section>
        <dialog id="maidDetailsDialog" class="modal-dialog"><form class="modal-card" method="POST" data-maid-operate action="{{ route('admin.maids.index') }}">@csrf @method('PATCH')<p class="eyebrow">Maid Action</p><h2 data-maid-title>Maid</h2><p data-maid-summary></p><label class="field"><span>Status</span><select name="status"><option value="active">Active</option><option value="paused">Paused</option><option value="expired">Expired</option></select></label><label class="field"><span>Assigned partner</span><select name="partner_id">@foreach ($partners as $partner)<option value="{{ $partner->id }}">{{ $partner->name }}</option>@endforeach</select></label><label class="field"><span>Salary</span><input name="salary" type="number" min="0" step="0.01" required></label><label class="field"><span>Off day</span><select name="off_day">@foreach (days_human() as $day)<option value="{{ $day['value'] }}">{{ $day['name'] }}</option>@endforeach</select></label><label class="field wide"><span>Notes</span><textarea name="notes" rows="3"></textarea></label><div class="form-actions"><button class="ui-button ghost" type="button" data-close-dialog>Close</button><button class="ui-button primary" type="submit">Save Changes</button></div></form></dialog>
    </div>
@endsection
