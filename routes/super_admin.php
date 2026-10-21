<?php

use App\Http\Controllers\AdminOperationsController;
use App\Http\Controllers\AdminParityController;
use App\Http\Controllers\CatalogResourceController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\MaidViewController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrderViewController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserViewController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::name('admin.')
    ->middleware(['role:super_admin', 'access:dashboard.admin'])
    ->group(function (): void {
        Route::get('/overview', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');

        Route::post('/users/notify', [AdminParityController::class, 'notify'])
            ->middleware('access:users.manage')
            ->name('users.notify');
        Route::post('/users/bulk-status', [UserViewController::class, 'bulkStatus'])
            ->middleware('access:users.manage')
            ->name('users.bulk-status');
        Route::get('/partners/export', [UserViewController::class, 'exportPartners'])
            ->middleware('access:users.manage')
            ->name('partners.export');
        Route::post('/partners/{user}/chat', [UserViewController::class, 'partnerChat'])
            ->whereNumber('user')
            ->middleware('access:messages.manage')
            ->name('partners.chat');
        Route::post('/users/{user}/temporary-password', [UserViewController::class, 'temporaryPassword'])
            ->whereNumber('user')
            ->middleware('access:users.manage')
            ->name('users.temporary-password');
        Route::get('/users/{user}/logs.csv', [UserViewController::class, 'exportLogs'])
            ->whereNumber('user')
            ->middleware('access:users.manage')
            ->name('users.logs');
        Route::get('/users/data/{role}', [UserViewController::class, 'data'])
            ->whereIn('role', ['customer', 'partner', 'supporter'])
            ->middleware('access:users.manage')
            ->name('users.data');
        Route::get('/users/{role?}', [UserViewController::class, 'index'])
            ->whereIn('role', ['customer', 'partner', 'supporter'])
            ->middleware('access:users.manage')
            ->name('users.index');
        Route::resource('users', UserViewController::class)->except([
            'index', 'show', 'destroy',
        ])->middleware('access:users.manage')->whereNumber('user');

        Route::get('maids/data', [MaidViewController::class, 'data'])->middleware('access:maids.manage')->name('maids.data');
        Route::patch('maids/{maid}/operations', [MaidViewController::class, 'operate'])->middleware('access:maids.manage')->name('maids.operations');
        Route::resource('maids', MaidViewController::class)->except(['show'])->middleware('access:maids.manage');

        Route::get('/services/data', [ServiceController::class, 'data'])
            ->middleware('access:services.manage')
            ->name('services.data');
        Route::resource('services', ServiceController::class)
            ->except('show')
            ->middleware('access:services.manage');

        Route::get('/products/data', [ProductController::class, 'data'])
            ->middleware('access:products.manage')
            ->name('products.data');
        Route::resource('products', ProductController::class)
            ->except('show')
            ->middleware('access:products.manage');

        Route::prefix('catalog/{resource}')
            ->name('catalog.')
            ->middleware('access:catalog.manage')
            ->whereIn('resource', ['service-types', 'service-categories', 'packages'])
            ->group(function (): void {
                Route::get('/', [CatalogResourceController::class, 'index'])->name('index');
                Route::get('/data', [CatalogResourceController::class, 'data'])->name('data');
                Route::get('/create', [CatalogResourceController::class, 'create'])->name('create');
                Route::post('/', [CatalogResourceController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [CatalogResourceController::class, 'edit'])->name('edit')->whereNumber('id');
                Route::put('/{id}', [CatalogResourceController::class, 'update'])->name('update')->whereNumber('id');
                Route::patch('/{id}/toggle', [CatalogResourceController::class, 'toggle'])->name('toggle')->whereNumber('id');
                Route::patch('/{id}/toggle', [CatalogResourceController::class, 'toggle'])->name('toggle')->whereNumber('id');
                Route::delete('/{id}', [CatalogResourceController::class, 'destroy'])->name('destroy')->whereNumber('id');
            });

        Route::get('/cities/data', [CityController::class, 'data'])
            ->middleware('access:cities.manage')
            ->name('cities.data');
        Route::resource('cities', CityController::class)
            ->except('show')
            ->middleware('access:cities.manage');

        Route::get('/faqs/data', [FaqController::class, 'data'])
            ->middleware('access:faqs.manage')
            ->name('faqs.data');
        Route::resource('faqs', FaqController::class)
            ->except('show')
            ->middleware('access:faqs.manage');

        Route::get('/wallets', [WalletController::class, 'index'])->middleware('access:wallets.manage')->name('wallets.index');
        Route::get('/wallets/data', [WalletController::class, 'data'])->middleware('access:wallets.manage')->name('wallets.data');
        Route::post('/wallets/adjust', [WalletController::class, 'adjust'])->middleware('access:wallets.manage')->name('wallets.adjust');

        Route::get('/custom-packages', [CatalogResourceController::class, 'customPackages'])
            ->middleware('access:catalog.manage')->name('custom-packages.index');
        Route::post('/custom-packages', [AdminParityController::class, 'savePackages'])
            ->middleware('access:catalog.manage')->name('custom-packages.save');
        Route::get('/custom-packages/export', [AdminParityController::class, 'exportPackages'])
            ->middleware('access:catalog.manage')->name('custom-packages.export');
        Route::get('/catalog/full', [CatalogResourceController::class, 'full'])
            ->middleware('access:catalog.manage')->name('catalog.full');
        Route::get('/catalog/full/data', [CatalogResourceController::class, 'fullData'])
            ->middleware('access:catalog.manage')->name('catalog.full.data');

        Route::get('/messages', [MessageController::class, 'index'])->middleware('access:messages.manage')->name('messages.index');
        Route::get('/partners/messages', [MessageController::class, 'partners'])->middleware('access:messages.manage')->name('messages.partners');
        Route::get('/messages/create', [MessageController::class, 'create'])->middleware('access:messages.manage')->name('messages.create');
        Route::post('/messages', [MessageController::class, 'store'])->middleware('access:messages.manage')->name('messages.store');
        Route::post('/messages/inbox', [AdminParityController::class, 'inboxAction'])->middleware('access:messages.manage')->name('messages.inbox');
        Route::post('/messages/rules', [AdminParityController::class, 'saveInboxRules'])->middleware('access:messages.manage')->name('messages.rules');
        Route::post('/messages/{conversation}/reply', [AdminParityController::class, 'reply'])->middleware('access:messages.manage')->name('messages.reply');

        Route::prefix('orders')->name('orders.')->middleware('access:orders.view')->group(function (): void {
            Route::get('/scheduled', [AdminParityController::class, 'scheduled'])->name('scheduled');
            Route::post('/scheduled', [AdminParityController::class, 'saveSchedule'])->name('scheduled.save');
            Route::get('/customer-lookup', [AdminParityController::class, 'customerLookup'])->name('customer-lookup');
            Route::post('/', [AdminParityController::class, 'storeOrder'])->name('store');
            Route::get('/export/{status}', [AdminParityController::class, 'exportQueue'])->name('export');
            Route::delete('/{order}', [AdminParityController::class, 'destroyOrder'])->name('destroy')->whereNumber('order');
            Route::get('/accepted', [OrderViewController::class, 'accepted'])->name('accepted');
            Route::get('/accepted/data', [OrderViewController::class, 'acceptedData'])->name('accepted.data');
            Route::get('/done', [OrderViewController::class, 'done'])->name('done');
            Route::get('/done/data', [OrderViewController::class, 'doneData'])->name('done.data');
            Route::get('/cancelled', [OrderViewController::class, 'cancelled'])->name('cancelled');
            Route::get('/cancelled/data', [OrderViewController::class, 'cancelledData'])->name('cancelled.data');
            Route::get('/reviews', [OrderViewController::class, 'reviews'])->name('reviews');
            Route::get('/reviews/data', [OrderViewController::class, 'reviewsData'])->name('reviews.data');
            Route::get('/reviews/export', [OrderViewController::class, 'exportReviews'])->name('reviews.export');
            Route::patch('/reviews/{review}/status', [OrderViewController::class, 'updateReviewStatus'])->name('reviews.status')->whereNumber('review');
            Route::get('/{status}', [AdminOperationsController::class, 'orders'])->whereIn('status', ['under_review', 'waiting', 'completed', 'cancelled'])->name('lifecycle');
            Route::patch('/{order}/workflow', [AdminOperationsController::class, 'updateOrder'])->name('workflow.update')->whereNumber('order');
        });

        Route::get('/approvals', [AdminOperationsController::class, 'approvals'])->middleware('access:approvals.manage')->name('approvals.index');
        Route::patch('/approvals/{approvalRequest}', [AdminOperationsController::class, 'decideApproval'])->middleware('access:approvals.manage')->name('approvals.decide');
        Route::get('/extras', [AdminOperationsController::class, 'extras'])->middleware('access:extras.manage')->name('extras.index');
        Route::get('/extras/export', [AdminOperationsController::class, 'exportExtras'])->middleware('access:extras.manage')->name('extras.export');
        Route::post('/extras', [AdminOperationsController::class, 'storeExtra'])->middleware('access:extras.manage')->name('extras.store');
        Route::patch('/extras/{extra}', [AdminOperationsController::class, 'updateExtra'])->middleware('access:extras.manage')->name('extras.update');
        Route::patch('/extras/{extra}/toggle', [AdminOperationsController::class, 'toggleExtra'])->middleware('access:extras.manage')->name('extras.toggle');
        Route::get('/coverage', [AdminOperationsController::class, 'coverage'])->middleware('access:locations.manage')->name('coverage.index');
        Route::post('/governorates', [AdminOperationsController::class, 'storeGovernorate'])->middleware('access:locations.manage')->name('governorates.store');
        Route::patch('/governorates/{governorate}', [AdminOperationsController::class, 'updateGovernorate'])->middleware('access:locations.manage')->name('governorates.update');
        Route::patch('/governorates/{governorate}/toggle', [AdminOperationsController::class, 'toggleGovernorate'])->middleware('access:locations.manage')->name('governorates.toggle');
        Route::post('/governorates/{governorate}/areas', [AdminOperationsController::class, 'storeArea'])->middleware('access:locations.manage')->name('areas.store');
        Route::patch('/areas/{area}/toggle', [AdminOperationsController::class, 'toggleArea'])->middleware('access:locations.manage')->name('areas.toggle');
        Route::patch('/areas/{area}', [AdminOperationsController::class, 'updateArea'])->middleware('access:locations.manage')->name('areas.update');
        Route::delete('/areas/{area}', [AdminOperationsController::class, 'destroyArea'])->middleware('access:locations.manage')->name('areas.destroy');
        Route::post('/coverage/sync', [AdminOperationsController::class, 'syncCoverage'])->middleware('access:locations.manage')->name('coverage.sync');
        Route::post('/coverage/addresses', [AdminOperationsController::class, 'storeAddress'])->middleware('access:locations.manage')->name('coverage.addresses.store');
        Route::get('/ads', [AdminOperationsController::class, 'ads'])->middleware('access:ads.manage')->name('ads.index');
        Route::get('/ads/history', [AdminOperationsController::class, 'adsHistory'])->middleware('access:ads.manage')->name('ads.history');
        Route::post('/ads', [AdminOperationsController::class, 'storeAd'])->middleware('access:ads.manage')->name('ads.store');
        Route::patch('/ads/{ad}', [AdminOperationsController::class, 'updateAd'])->middleware('access:ads.manage')->name('ads.update');
        Route::patch('/ads/{ad}/toggle', [AdminOperationsController::class, 'toggleAd'])->middleware('access:ads.manage')->name('ads.toggle');
        Route::get('/shares', [AdminOperationsController::class, 'shares'])->middleware('access:shares.view')->name('shares.index');
        Route::get('/shares/export', [AdminOperationsController::class, 'exportShares'])->middleware('access:shares.view')->name('shares.export');
        Route::get('/logs-inquiries', [AdminOperationsController::class, 'inquiries'])->middleware('access:orders.view')->name('inquiries.index');
        Route::get('/logs-inquiries/export', [AdminParityController::class, 'exportInquiries'])->middleware('access:orders.view')->name('inquiries.export');
    });
