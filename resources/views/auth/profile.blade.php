@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('asset-page', 'admin-dashboard')
@section('eyebrow', 'Executive overview')
@section('heading', 'TARWIQA Dashboard')
@section('subtitle', 'Users, orders, partners, revenue, campaigns, and operational performance.')
@section('content')
    <div class="page-shell">
        <div class="ambient ambient-left"></div>
        <div class="ambient ambient-right"></div>

        <header class="topbar">
            <div>
                <p class="eyebrow">Customer Intelligence</p>
                <h1>User Profile</h1>
                <p class="subtitle">
                    Full profile view with editable fields, account controls, and connected activity surfaces.
                </p>
            </div>

            <div class="top-actions">
                <button class="action-btn danger" id="banUserBtn" type="button">Ban</button>
                <button class="action-btn warning" id="restrictUserBtn" type="button">Restrict</button>
                <a class="action-btn ghost" href="{{ route('admin.messages.index') }}">Send Message</a>
            </div>
        </header>

        <main class="profile-layout">
            <section class="hero-card">
                <div class="hero-main">
                    <div class="identity-block">
                        <div class="avatar" id="userAvatarInitials">{{ str($profileUser->name)->substr(0, 2)->upper() }}</div>
                        <div>
                            <div class="status-row">
                                <span class="pill online">{{ str($profileUser->status)->replace('_', ' ')->title() }}</span>
                                <span class="pill subtle" id="userTierPill">{{ str($profileUser->role)->replace('_', ' ')->title() }}</span>
                            </div>
                            <h2 id="userDisplayName">{{ $profileUser->name }}</h2>
                            <p class="hero-meta" id="userHeroMeta">
                                {{ $profileUser->governorate?->name ?? $profileUser->city?->name ?? 'Unassigned' }}
                                <span class="dot"></span>
                                {{ str($profileUser->role)->replace('_', ' ')->title() }}
                                <span class="dot"></span>
                                Joined {{ $profileUser->created_at?->format('d M Y') ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="hero-stats">
                        <article class="stat-card">
                            <span>Total Orders</span>
                            <strong>{{ number_format($orderCount) }}</strong>
                            <small>Orders placed by this account</small>
                        </article>
                        <article class="stat-card">
                            <span>Wallet Balance</span>
                            <strong>EGP {{ format_amount($walletBalance) }}</strong>
                            <small>{{ $profileUser->wallet ? 'Available wallet balance' : 'No wallet yet' }}</small>
                        </article>
                        <article class="stat-card">
                            <span>Account Status</span>
                            <strong>{{ str($profileUser->status)->replace('_', ' ')->title() }}</strong>
                            <small>Stored account status</small>
                        </article>
                    </div>
                </div>

                <aside class="side-panel">
                    <div class="panel-header">
                        <p>Account Controls</p>
                        <span>Live state</span>
                    </div>

                    <div class="state-grid">
                        <label class="select-field">
                            <span>Active</span>
                            <select id="activeStatusSelect">
                                <option selected>Enabled</option>
                                <option>Disabled</option>
                            </select>
                        </label>

                        <label class="select-field">
                            <span>Ban</span>
                            <select id="banStatusSelect">
                                <option selected>Not Banned</option>
                                <option>Temporary Ban</option>
                                <option>Permanent Ban</option>
                            </select>
                        </label>

                        <label class="select-field">
                            <span>Restricted</span>
                            <select id="restrictedStatusSelect">
                                <option selected>Open Access</option>
                                <option>Orders Only</option>
                                <option>Wallet Blocked</option>
                                <option>Messaging Blocked</option>
                            </select>
                        </label>

                        <label class="select-field">
                            <span>Customer Tier</span>
                            <select id="userTierSelect">
                                <option>Standard</option>
                                <option>Gold</option>
                                <option selected>VIP</option>
                            </select>
                        </label>
                        <div class="permission-field">
                            <div class="permission-copy">
                                <span>Screenshot Permission</span>
                                <small>Control whether this user can capture screenshots inside the
                                    application.</small>
                            </div>
                            <button class="permission-toggle allowed" id="screenshotPermissionToggle" type="button"
                                aria-pressed="true">
                                Screenshot Allowed
                            </button>
                        </div>
                    </div>

                    <div class="quick-links">
                        <button class="mini-link active" data-tab-target="orders-tab">Orders</button>
                        <button class="mini-link" data-tab-target="wallet-tab">Wallet Transactions</button>
                        <button class="mini-link" data-tab-target="logs-tab">Activity Logs</button>
                        <button class="mini-link" data-tab-target="addresses-tab">Addresses</button>
                    </div>
                </aside>
            </section>

            <section class="details-card">
                <div class="section-head">
                    <div>
                        <p class="eyebrow">Profile Details</p>
                        <h3>Identity & Contact Information</h3>
                    </div>
                    <button class="save-btn" id="saveProfileBtn" type="button">Save Changes</button>
                </div>

                <div class="details-grid">
                    <label class="field readonly">
                        <span>ID</span>
                        <input id="profileUserId" type="text" value="#USR-{{ str_pad((string) $profileUser->id, 6, '0', STR_PAD_LEFT) }}" readonly />
                    </label>

                    <label class="field readonly">
                        <span>Phone</span>
                        <input id="profilePhone" type="text" value="{{ $profileUser->phone ?: '—' }}" readonly />
                    </label>

                    <label class="field readonly">
                        <span>Type</span>
                        <input type="text" value="{{ str($profileUser->role)->replace('_', ' ')->title() }}" readonly />
                    </label>

                    <label class="field readonly">
                        <span>Created Date</span>
                        <input type="text" value="{{ $profileUser->created_at?->format('Y-m-d h:i A') ?? '—' }}" readonly />
                    </label>

                    <label class="field">
                        <span>Name</span>
                        <input id="nameInput" type="text" value="{{ $profileUser->name }}" readonly />
                    </label>

                    <label class="field">
                        <span>Additional Number</span>
                        <input type="text" value="{{ $profileUser->additional_phone ?: '—' }}" readonly />
                    </label>

                    <label class="field">
                        <span>Email</span>
                        <input id="profileEmail" type="email" value="{{ $profileUser->email }}" readonly />
                    </label>

                    <label class="field">
                        <span>City</span>
                        <input id="profileCity" type="text" value="{{ $profileUser->city?->name ?? '—' }}" readonly />
                    </label>

                    <label class="field wide">
                        <span>Address</span>
                        <input id="profileAddress" type="text" value="{{ $profileUser->address ?: '—' }}" readonly />
                    </label>

                    <label class="field">
                        <span>Platform</span>
                        <input type="text" value="{{ $profileUser->platform ? str($profileUser->platform)->title() : '—' }}" readonly />
                    </label>

                    <label class="field">
                        <span>Wallet</span>
                        <input type="text" value="EGP {{ format_amount($walletBalance) }}" readonly />
                    </label>
                </div>
            </section>

            <section class="tabs-card">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="orders-tab" type="button">Orders</button>
                    <button class="tab-btn" data-tab="wallet-tab" type="button">Wallet</button>
                    <button class="tab-btn" data-tab="logs-tab" type="button">Logs</button>
                    <button class="tab-btn" data-tab="addresses-tab" type="button">Addresses</button>
                    <button class="tab-btn" data-tab="messages-tab" type="button">Messages History</button>
                </div>

                <div class="tab-panel active" id="orders-tab">
                    <div class="panel-topline">
                        <div>
                            <p class="eyebrow">Orders</p>
                            <h3>Recent orders and fulfillment state</h3>
                        </div>
                        <button class="inline-btn" id="viewAllOrdersBtn" type="button">View All
                            Orders</button>
                    </div>

                    <div class="table-shell">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Channel</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentOrders as $order)
                                    <tr>
                                        <td>#ORD-{{ str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $order->service_date?->format('d M Y') ?? '—' }}</td>
                                        <td>{{ str($order->status)->replace('_', ' ')->title() }}</td>
                                        <td>{{ $order->payment_method ? str($order->payment_method)->replace('_', ' ')->title() : '—' }}</td>
                                        <td>EGP {{ format_amount($order->total) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">No orders yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-panel" id="wallet-tab">
                    <div class="panel-topline">
                        <div>
                            <p class="eyebrow">Wallet</p>
                            <h3>Balance movements and recent transactions</h3>
                        </div>
                        <a class="inline-btn" href="{{ route('admin.wallets.index') }}">Open Wallet Ledger</a>
                    </div>

                    <div class="timeline">
                        @forelse ($walletTransactions as $transaction)
                            <article class="timeline-item">
                                <div class="timeline-dot neutral"></div>
                                <div>
                                    <h4>{{ str($transaction->type)->replace('_', ' ')->title() }}</h4>
                                    <p>{{ $transaction->description ?: 'Wallet movement' }} · EGP {{ format_amount($transaction->amount) }}</p>
                                </div>
                                <time>{{ $transaction->created_at?->format('d M Y, h:i A') }}</time>
                            </article>
                        @empty
                            <article class="timeline-item">
                                <div>
                                    <h4>No wallet movements</h4>
                                    <p>EGP {{ format_amount($walletBalance) }}</p>
                                </div>
                            </article>
                        @endforelse
                    </div>
                </div>

                <div class="tab-panel" id="logs-tab">
                    <div class="panel-topline">
                        <div>
                            <p class="eyebrow">Logs</p>
                            <h3>Audit-friendly user activity stream</h3>
                        </div>
                        <button class="inline-btn" id="exportLogsBtn" type="button">Export Logs</button>
                    </div>

                    <div class="log-list">
                        <article class="log-item">
                            <strong>No activity log</strong>
                            <p>Account events are not stored for this profile yet.</p>
                        </article>
                    </div>
                </div>

                <div class="tab-panel" id="messages-tab">
                    <div class="panel-topline">
                        <div>
                            <p class="eyebrow">Messages</p>
                            <h3>Support and outbound communication history</h3>
                        </div>
                        <button class="inline-btn" type="button" onclick="location.href='{{ route('admin.messages.index') }}'">send
                            messgag</button>
                    </div>

                    <div class="message-stack">
                        <article class="message-item">
                            <p>No messages are stored on this profile.</p>
                        </article>
                    </div>
                </div>

                <div class="tab-panel" id="addresses-tab">
                    <div class="panel-topline">
                        <div>
                            <p class="eyebrow">Addresses</p>
                            <h3>Customer addresses with governorate and location control</h3>
                        </div>
                        <div class="address-actions">
                            <button class="inline-btn" id="addNewAddressBtn" type="button">Add New
                                Address</button>
                        </div>
                    </div>

                    <section class="address-summary-grid">
                        <article class="address-summary-card">
                            <span>Total Saved Addresses</span>
                            <strong id="addressCountValue">{{ number_format($addressCount) }}</strong>
                            <small>Available for the customer at checkout</small>
                        </article>
                        <article class="address-summary-card">
                            <span>Default Governorate</span>
                            <strong id="defaultGovernorateValue">{{ $profileUser->governorate?->name ?? '—' }}</strong>
                            <small>Most frequently selected by this customer</small>
                        </article>
                        <article class="address-summary-card">
                            <span>Admin Controlled Locations</span>
                            <strong>Enabled</strong>
                            <small>Customer chooses from dashboard-defined location names</small>
                        </article>
                    </section>

                    <div class="address-workspace">
                        <div class="table-shell">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Address ID</th>
                                        <th>Governorate</th>
                                        <th>Location</th>
                                        <th>Street</th>
                                        <th>Building</th>
                                        <th>Floor / Apartment</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="addressesTableBody"></tbody>
                            </table>
                        </div>

                        <div class="address-editor-card">
                            <div class="section-head">
                                <div>
                                    <p class="eyebrow">Address Editor</p>
                                    <h3 id="addressFormTitle">Add New Address</h3>
                                </div>
                                <button class="ghost-btn" id="resetAddressFormBtn" type="button">Reset
                                    Form</button>
                            </div>

                            <form class="address-form" id="addressForm">
                                <input id="addressEditId" type="hidden" />

                                <div class="address-form-grid">
                                    <label class="field readonly">
                                        <span>Address ID</span>
                                        <input id="addressIdPreview" type="text" value="Auto Generated" readonly />
                                    </label>

                                    <label class="field">
                                        <span>Governorate</span>
                                        <select id="governorateSelect" required>
                                            <option value="Alexandria">Alexandria</option>
                                            <option value="North Coast">North Coast</option>
                                            <option value="Beheira">Beheira</option>
                                            <option value="Cairo" selected>Cairo</option>
                                            <option value="New Cairo">New Cairo</option>
                                            <option value="Giza">Giza</option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Location</span>
                                        <select id="locationSelect" required></select>
                                    </label>

                                    <label class="field">
                                        <span>Street Name</span>
                                        <input id="streetInput" type="text" placeholder="Example: Nile Corniche"
                                            required />
                                    </label>

                                    <label class="field">
                                        <span>House Number</span>
                                        <input id="houseNumberInput" type="text" placeholder="Example: 12" required />
                                    </label>

                                    <label class="field">
                                        <span>House Name</span>
                                        <input id="houseNameInput" type="text"
                                            placeholder="Example: Al Yasmin Tower" />
                                    </label>

                                    <label class="field">
                                        <span>Floor Number</span>
                                        <input id="floorNumberInput" type="text" placeholder="Example: 6" />
                                    </label>

                                    <label class="field">
                                        <span>Apartment Number</span>
                                        <input id="apartmentNumberInput" type="text" placeholder="Example: 17B" />
                                    </label>

                                    <label class="field wide">
                                        <span>Address Notes</span>
                                        <input id="notesInput" type="text"
                                            placeholder="Example: Ring the bell twice and call before arrival" />
                                    </label>
                                </div>

                                <div class="address-form-actions">
                                    <button class="save-btn" id="saveAddressBtn" type="submit">Save
                                        Address</button>
                                    <button class="ghost-btn" id="cancelEditAddressBtn" type="button">Cancel
                                        Edit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    @include('partials.components.profile-modal')
    @include('partials.components.profile-toast')
    @include('partials.components.profile-templates')
@endsection
