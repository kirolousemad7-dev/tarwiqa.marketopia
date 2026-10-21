 @if ($errors->any())
     <div class="catalog-notice danger">
         <ul>
             @foreach ($errors->all() as $error)
                 <li>{{ $error }}</li>
             @endforeach
         </ul>
     </div>
 @endif

 <div class="user-tabs" role="tablist">
     <button class="wizard-tab active" type="button" data-maid-step="0">Personal</button>
     <button class="wizard-tab" type="button" data-maid-step="1">Work</button>
     <button class="wizard-tab" type="button" data-maid-step="2">Documents</button>
 </div>
 <div class="service-form-grid maid-form-sections">
     <div class="maid-step active" data-maid-step-panel="0">
     <div class="form-section-heading wide"><p class="eyebrow">Step 1</p><h3>Personal and contact information</h3></div>
     @if ($maid ?? false)
         <label class="field">
             <span>ID</span>
             <input value="{{ $maid->id }}" disabled readonly>
         </label>
     @else
         <input name="role" value="{{ request('role', 'customer') }}" hidden>
     @endif
     <label class="field">
         <span>Name</span>
         <input name="name" value="{{ old('name', $maid?->name) }}" required>
     </label>
     <label class="field">
         <span>Phone</span>
         <input name="phone" type="text" value="{{ old('phone', $maid?->phone) }}" required>
     </label>
     <label class="field">
         <span>Age</span>
         <input name="age" type="number" value="{{ old('age', $maid?->age) }}" required>
     </label>
     <label class="field">
         <span>Availability</span>
         <select name="status">
             @foreach (['active' => 'Active', 'paused' => 'Paused', 'expired' => 'Expired'] as $status => $label)
                 <option value="{{ $status }}" @selected(old('status', $maid?->status ?? 'active') === $status)>{{ $label }}</option>
             @endforeach
         </select>
     </label>

     </div>
     <div class="maid-step" data-maid-step-panel="1">
     <div class="form-section-heading wide"><p class="eyebrow">Step 2</p><h3>Employment, partner, and address</h3></div>
     <label class="field">
         <span>Start Date</span>
         <input name="start_date" type="date" value="{{ old('start_date', $maid?->start_date) }}" required>
     </label>
     <label class="field">
         <span>Address</span>
         <input name="address" value="{{ old('address', $maid?->address) }}">
     </label>
     <label class="field">
         <span>Partner</span>
         <select name="partner_id">
             <option value="">Unassigned</option>
             @foreach ($partners as $part)
                 <option value="{{ $part->id }}" @selected((int) old('partner_id', $maid?->partner_id) === (int) $part->id)>
                     {{ "{$part->name} - ".($part->governorate?->name ?? 'Unassigned governorate') }}</option>
             @endforeach
         </select>
     </label>
     <label class="field">
         <span>Gender</span>
         <select name="gender">
             @foreach (['male', 'female'] as $gender)
                 <option value="{{ $gender }}" @selected(old('gender', $maid?->gender ?? 'male') === $gender)>{{ str($gender)->title() }}</option>
             @endforeach
         </select>
     </label>
     <label class="field">
         <span>Off Day</span>
         <select name="off_day">
             @foreach (days_human() as $day)
                 <option value="{{ $day['value'] }}" @selected(old('off_day', $maid?->off_day) === $day['value'])>{{ str($day['name'])->title() }}
                 </option>
             @endforeach
         </select>
     </label>
     <label class="field">
         <span>Salary</span>
         <input name="salary" value="{{ old('salary', $maid?->salary) }}" placeholder="Monthly Salary">
     </label>
     </div>
     <div class="maid-step" data-maid-step-panel="2">
     <div class="form-section-heading wide"><p class="eyebrow">Step 3</p><h3>Documents, notes, and completion</h3></div>
     <label class="field"><span>Personal ID</span><input name="personal_id" value="{{ old('personal_id', $maid?->personal_id) }}" placeholder="National identification number"></label>
     <label class="field"><span>Document type</span><select name="doc_type"><option value="">Select document type</option>@foreach(['personal_id' => 'Personal ID', 'contract' => 'Contract', 'medical_report' => 'Medical report', 'police_clearance' => 'Police clearance', 'training_certificate' => 'Training certificate', 'profile_photo' => 'Profile photo', 'other' => 'Other'] as $type => $label)<option value="{{ $type }}" @selected(old('doc_type', $maid?->doc_type) === $type)>{{ $label }}</option>@endforeach</select></label>
     <label class="field wide"><span>Attachment</span><input name="attachment" type="file" accept="image/*,.pdf,.doc,.docx"><small class="field-hint">Identity or onboarding document linked to this maid profile.</small></label>
     <label class="field wide"><span>Notes</span><textarea name="notes">{{ old('notes', $maid?->notes) }}</textarea></label>
     </div>
 </div>
 <div class="form-actions catalog-form-actions">
     <button class="ui-button ghost" type="button" data-maid-prev>Previous</button>
     <button class="ui-button ghost" type="button" data-maid-next>Next</button>
     <button class="ui-button ghost" type="submit" name="is_draft" value="1">Save Draft</button>
     <button class="ui-button primary" type="submit">Save Maid</button>
 </div>
 <script>
 (() => {
     const panels = [...document.querySelectorAll("[data-maid-step-panel]")];
     const tabs = [...document.querySelectorAll(".wizard-tab")];
     let index = panels.findIndex((panel) => panel.querySelector(":invalid")) ;
     if (index < 0) index = 0;
     const show = (next) => {
         index = Math.max(0, Math.min(panels.length - 1, next));
         panels.forEach((panel, panelIndex) => panel.classList.toggle("active", panelIndex === index));
         tabs.forEach((tab) => tab.classList.toggle("active", Number(tab.dataset.maidStep) === index));
     };
     show(index);
     document.querySelector("[data-maid-prev]")?.addEventListener("click", () => show(index - 1));
     document.querySelector("[data-maid-next]")?.addEventListener("click", () => show(index + 1));
     tabs.forEach((tab) => tab.addEventListener("click", () => show(Number(tab.dataset.maidStep))));
 })();
 </script>

 @push('scripts')
     <script>
         const tabButtons = document.querySelectorAll(".tab-btn");
         const tabPanels = document.querySelectorAll(".tab-panel");

         function setActiveTab(targetId) {
             tabButtons.forEach((button) => {
                 button.classList.toggle(
                     "active",
                     button.dataset.tab === targetId,
                 );
             });

             tabPanels.forEach((panel) => {
                 panel.classList.toggle("active", panel.id === targetId);
             });
         }

         tabButtons.forEach((button) => {
             button.addEventListener("click", () =>
                 setActiveTab(button.dataset.tab),
             );
         });
     </script>
 @endpush
