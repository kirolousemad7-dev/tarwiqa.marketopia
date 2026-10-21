@extends('layouts.app')
@section('title', str($role)->title() . 's')
@section('eyebrow', 'People and access')
@section('heading', str($role)->title() . 's')
@section('subtitle', $role === 'customer' ? 'Customer accounts can be deactivated, restricted, or banned. Records and history stay in place.' : 'Manage '.$role.' accounts, access, and assignments.')
@section('content')
    <section class="overview-metrics" aria-label="Business summary">
        <x-metrics title="Active Users" type="users" value="{{ $active_users }}" />
        <x-metrics title="Total Users" type="users" value="{{ $total_users }}" />
        <x-metrics title="Active Today" type="users" value="{{ $active_today }}" />
        <x-metrics title="Inactive Accounts" type="users" value="{{ $inactive_users }}" />
        <x-metrics title="Restricted Accounts" type="users" value="{{ $restricted_users }}" />
        <x-metrics title="Banned Accounts" type="users" value="{{ $suspended_users }}" />
        @if ($role == 'customer')
            <x-metrics title="Total Wallet" type="revenue" value="{{ $total_wallet }}" />
        @endif
        @if ($role == 'partner')
            <x-metrics title="Managed Maids" type="users" value="{{ $managed_maids }}" />
            <x-metrics title="Completed Orders" type="orders" value="{{ $completed_orders }}" />
        @endif
        @if ($role == 'supporter')
            <x-metrics title="Covered Governorates" type="users" value="{{ $covered_governorates }}" />
            <x-metrics title="Scoped Orders" type="orders" value="{{ $scoped_orders }}" />
        @endif
    </section>

    <div class="workspace-page users-page">
        <div class="page-actions prototype-bulk-bar">
            <a class="ui-button accent" href="{{ route('admin.users.create', ['role' => $role]) }}">Add {{ str($role)->title() }}</a>
            @if ($role === 'customer')
                <form id="bulk-users-form" method="POST" action="{{ route('admin.users.bulk-status') }}" data-bulk-users>
                    @csrf
                    <input type="hidden" name="action" value="deactivate">
                    <button class="ui-button ghost" type="submit" name="action" value="deactivate" onclick="return confirm('Deactivate the selected users? Their accounts will be disabled, but all orders and history will be preserved.')">Deactivate</button>
                    <button class="ui-button ghost" type="submit" name="action" value="activate" onclick="return confirm('Activate the selected users? They will be able to sign in again. Orders and history stay in place.')">Activate</button>
                    <button class="ui-button ghost" type="submit" name="action" value="restrict" onclick="return confirm('Restrict the selected users? Sign-in stays disabled until the accounts are restored. Orders and history will be preserved.')">Restrict</button>
                    <label>Ban type<select name="ban_type"><option value="temporary">Temporary ban</option><option value="permanent">Permanent ban</option></select></label>
                    <button class="ui-button primary" type="submit" name="action" value="ban" onclick="return confirm('Ban the selected users? They cannot sign in until an admin restores the accounts. Orders and history will be preserved.')">Ban</button>
                    <button class="ui-button ghost" type="button" data-open-dialog="notifyDialog">Send Push</button>
                    <button class="ui-button ghost" type="button" data-open-dialog="notifyDialog" data-notify-channel="email">Send Email</button>
                    <button class="ui-button ghost" type="button" data-open-dialog="notifyDialog" data-notify-channel="whatsapp">WhatsApp</button>
                </form>
                @if ($errors->has('channel'))<div class="catalog-notice danger">{{ $errors->first('channel') }}</div>@endif
                <dialog class="prototype-dialog" id="notifyDialog" aria-labelledby="notifyTitle">
                    <form method="POST" action="{{ route('admin.users.notify') }}">
                        @csrf
                        <h2 id="notifyTitle">Send message</h2>
                        <label>Channel<select name="channel"><option value="push">Push</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select></label>
                        <label>Subject<input name="subject" maxlength="160"></label>
                        <label>Message<textarea name="body" required></textarea></label>
                        <p>Selected customer checkboxes are submitted with this message. Delivery fails with a configuration error when the provider is not set.</p>
                        <div data-notify-ids></div>
                        <div class="prototype-dialog-actions"><button class="ui-button ghost" type="button" data-close-dialog>Cancel</button><button class="ui-button primary" type="submit">Send</button></div>
                    </form>
                </dialog>
            @endif
        </div>

        @if (session('status'))
            <div class="catalog-notice">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="catalog-notice danger">{{ $errors->first() }}</div>
        @endif

        <section class="surface-card">
            <div class="card-head catalog-head">
                <div>
                    <p class="eyebrow">Account directory</p>
                    <h2>All {{ str($role)->title() }}s</h2>
                </div>
                <div class="catalog-filters" data-table-filters>
                    <input data-table-search type="search" placeholder="Name, email or phone">
                    <button class="ui-button ghost" data-table-reset type="button">Reset</button>
                </div>
            </div>
            @if (in_array($role, ['supporter', 'partner'], true))
                <form class="toolbar" method="GET">
                    <label class="field"><span>Status</span><select name="status"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option><option value="restricted" @selected(request('status') === 'restricted')>Restricted</option><option value="suspended" @selected(request('status') === 'suspended')>Banned</option></select></label>
                    <label class="field"><span>Governorate</span><select name="governorate_id"><option value="">All</option>@foreach (\App\Models\Governorate::query()->orderBy('name')->get(['id', 'name']) as $governorate)<option value="{{ $governorate->id }}" @selected((string) request('governorate_id') === (string) $governorate->id)>{{ $governorate->name }}</option>@endforeach</select></label>
                    <button class="ui-button ghost" type="submit">Filter</button>
                    @if ($role === 'partner')<a class="ui-button ghost" href="{{ route('admin.partners.export', request()->only(['status', 'governorate_id'])) }}">Export Partners</a>@endif
                </form>
            @endif
            <div class="data-table-shell table-wrapper">
                <table data-yajra-table data-source="{{ route('admin.users.data', ['role' => $role] + request()->only(['status', 'governorate_id'])) }}"
                    data-kind="{{ $role }}s">
                    <thead>
                        <tr>
                            @if ($role === 'customer')
                                <th class="text-center"><input type="checkbox" data-select-all-users aria-label="Select all customers"></th>
                            @endif
                            <x-roles_table_columns role="{{ $role }}" />

                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="empty-table">Loading users…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="yajra-table-footer" data-table-footer></div>
        </section>
        <form id="row-user-action" method="POST" action="{{ route('admin.users.bulk-status') }}" hidden>@csrf<input name="ids[]"><input name="action" value="restrict"></form>
        <dialog id="userBanDialog" class="modal-dialog"><form class="modal-card" method="POST" action="{{ route('admin.users.bulk-status') }}" onsubmit="return confirm('Ban this user? They cannot sign in until an admin restores the account. Orders and history will be preserved.')">@csrf<input type="hidden" name="action" value="ban"><input type="hidden" name="ids[]"><h2>Ban customer</h2><label class="field"><span>Ban type</span><select name="ban_type"><option value="temporary">Temporary Ban</option><option value="permanent">Permanent Ban</option></select></label><div class="form-actions"><button class="ui-button ghost" type="button" data-close-dialog>Cancel</button><button class="ui-button primary" type="submit">Save</button></div></form></dialog>
    </div>
@endsection
