@php
    $widgetTypes = ['core_services' => 'Core Services', 'commerce' => 'Commerce', 'support' => 'Support', 'payments' => 'Payments'];
    $widgetVisibility = ['public' => 'Public', 'internal' => 'Internal', 'pilot_only' => 'Pilot Only'];
@endphp
<label class="field"><span>Governorate</span>
    <select name="governorate_id">
        <option value="">Unassigned</option>
        @foreach ($governorates as $governorate)
            <option value="{{ $governorate->id }}" @selected((string) old('governorate_id', $widget?->governorate_id) === (string) $governorate->id)>{{ $governorate->name }}</option>
        @endforeach
    </select>
</label>
<label class="field"><span>Widget Name</span><input name="title" value="{{ old('title', $widget?->title) }}" required placeholder="Example: Payments Hub"></label>
<label class="field"><span>Widget Type</span>
    <select name="widget_type">
        <option value="">Not specified</option>
        @foreach ($widgetTypes as $value => $label)
            <option value="{{ $value }}" @selected(old('widget_type', $widget?->widget_type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label class="field"><span>Visibility</span>
    <select name="visibility">
        <option value="">Not specified</option>
        @foreach ($widgetVisibility as $value => $label)
            <option value="{{ $value }}" @selected(old('visibility', $widget?->visibility) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label class="field"><span>Primary Owner</span><input name="primary_owner" value="{{ old('primary_owner', $widget?->primary_owner) }}" placeholder="Example: Product Team Payments"></label>
<label class="field"><span>SLA Group</span><input name="sla_group" value="{{ old('sla_group', $widget?->sla_group) }}" placeholder="Example: Tier 1 Operations"></label>
<label class="field wide"><span>Widget Description</span><textarea name="description" rows="3" placeholder="Explain what this widget does and when it should be used.">{{ old('description', $widget?->description) }}</textarea></label>
