<label>Status<select name="status" required><option value="under_review">Under Review</option><option value="waiting">Waiting</option><option value="accepted">Accepted Orders</option><option value="completed">Done Orders</option><option value="cancelled">Cancelled Order</option></select></label>
<label>Total price<input name="subtotal" type="number" min="0" step="0.01" value="0" data-price-input></label>
<label>Wallet<input name="wallet_amount" type="number" min="0" step="0.01" value="0" data-price-input></label>
<label>Deposit<input name="deposit_amount" type="number" min="0" step="0.01" value="0" data-price-input></label>
<label>Discount<input name="discount_total" type="number" min="0" step="0.01" value="0" data-price-input></label>
<label>Final price<input data-final-price value="0.00" readonly></label>
<label>Payment method<select name="payment_method"><option value="cash">Cash</option><option value="e_wallet">E-Wallet</option><option value="bank_transfer">Bank Transfer</option></select></label>
<label>Order date<input name="service_date" type="date"></label>
<label>Arrival time<input name="arrival_time" type="time"></label>
<label>Assigned partner<select name="partner_id"><option value="">Unassigned</option>@foreach ($context['partners'] as $partner)<option value="{{ $partner->id }}">{{ $partner->name }}</option>@endforeach</select></label>
<label>Add maids<select name="maid_ids[]" multiple>@foreach ($context['maids'] as $maid)<option value="{{ $maid->id }}">{{ $maid->name }}</option>@endforeach</select></label>
<label>Extra<select name="extra_ids[]" multiple>@foreach ($context['extras'] as $extra)<option value="{{ $extra->id }}">{{ $extra->title }}</option>@endforeach</select></label>
<label>Notes<textarea name="internal_notes"></textarea></label>
