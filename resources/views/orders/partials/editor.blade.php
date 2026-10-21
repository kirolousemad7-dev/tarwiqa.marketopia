@php
    $context = app(\App\Http\Controllers\AdminParityController::class)->orderFormContext();
@endphp
<dialog class="prototype-dialog" id="orderAddDialog" aria-labelledby="orderAddTitle">
    <form method="POST" action="{{ route('admin.orders.store') }}">
        @csrf
        <h2 id="orderAddTitle">Add Order</h2>
        <label>Customer search<input type="search" data-customer-search placeholder="Phone, name, or user ID"></label>
        <label>Customer<select name="customer_id" required data-customer-results><option value="">Select a customer</option></select></label>
        <label>Address<select name="address_id" data-address-results><option value="">No saved address</option></select></label>
        <label>Package<select name="package_id"><option value="">No package</option>@foreach ($context['packages'] as $package)<option value="{{ $package->id }}">{{ $package->title }}</option>@endforeach</select></label>
        @include('orders.partials.editor-fields')
        <div class="prototype-dialog-actions"><button class="ui-button ghost" type="button" data-close-dialog>Cancel</button><button class="ui-button primary" type="submit">Create Order</button></div>
    </form>
</dialog>
<dialog class="prototype-dialog" id="orderEditDialog" aria-labelledby="orderEditTitle">
    <form method="POST" data-order-workflow>
        @csrf
        @method('PATCH')
        <h2 id="orderEditTitle">Edit Order</h2>
        <p data-order-reference></p>
        @include('orders.partials.editor-fields')
        <div class="prototype-dialog-actions"><button class="ui-button ghost" type="button" data-close-dialog>Cancel</button><button class="ui-button primary" type="submit">Save Changes</button></div>
    </form>
</dialog>
