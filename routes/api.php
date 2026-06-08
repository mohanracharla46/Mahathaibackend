<?php

use App\Http\Controllers\Api\CareerApplicationController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\ConciergeInquiryController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index']);
Route::post('users', [UserController::class, 'store']);
Route::patch('users/{user}', [UserController::class, 'update']);

Route::get('menu-categories', [MenuCategoryController::class, 'index']);
Route::post('menu-categories', [MenuCategoryController::class, 'store']);

Route::get('menu-items', [MenuItemController::class, 'index']);
Route::post('menu-items', [MenuItemController::class, 'store']);
Route::post('menu-items/upload-image', [MenuItemController::class, 'uploadImage']);
Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update']);
Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy']);

Route::get('carts', [CartController::class, 'index']);
Route::post('carts', [CartController::class, 'store']);

Route::get('cart-items', [CartItemController::class, 'index']);
Route::post('cart-items', [CartItemController::class, 'store']);

Route::get('orders', [OrderController::class, 'index']);
Route::post('orders', [OrderController::class, 'store']);

Route::get('rewards', [RewardController::class, 'index']);
Route::get('rewards/user/{user}', [RewardController::class, 'showForUser']);
Route::post('rewards/user/{user}/redeem', [RewardController::class, 'redeem']);

Route::get('promo-codes', [PromoCodeController::class, 'index']);
Route::post('promo-codes', [PromoCodeController::class, 'store']);

Route::get('order-items', [OrderItemController::class, 'index']);
Route::post('order-items', [OrderItemController::class, 'store']);

Route::get('reservations', [ReservationController::class, 'index']);
Route::post('reservations', [ReservationController::class, 'store']);
Route::patch('reservations/{reservation}', [ReservationController::class, 'update']);

Route::get('contact-messages', [ContactMessageController::class, 'index']);
Route::post('contact-messages', [ContactMessageController::class, 'store']);
Route::patch(
    'contact-messages/{contactMessage}/status',
    [ContactMessageController::class, 'update']
);

Route::get('feedback', [FeedbackController::class, 'index']);
Route::post('feedback', [FeedbackController::class, 'store']);

Route::get('concierge-inquiries', [ConciergeInquiryController::class, 'index']);
Route::get('concierge-inquiries/user/{userId}', [ConciergeInquiryController::class, 'userInquiries']);
Route::post('concierge-inquiries', [ConciergeInquiryController::class, 'store']);
Route::get('concierge-inquiries/{conciergeInquiry}', [ConciergeInquiryController::class, 'show']);
Route::put('concierge-inquiries/{conciergeInquiry}', [ConciergeInquiryController::class, 'update']);
Route::patch('concierge-inquiries/{conciergeInquiry}', [ConciergeInquiryController::class, 'update']);
Route::patch('concierge-inquiries/{conciergeInquiry}/status', [ConciergeInquiryController::class, 'update']);
Route::get('concierge-support', [ConciergeInquiryController::class, 'index']);
Route::get('concierge-support/user/{userId}', [ConciergeInquiryController::class, 'userInquiries']);
Route::get('concierge-support/customer/{userId}', [ConciergeInquiryController::class, 'userInquiries']);
Route::post('concierge-support', [ConciergeInquiryController::class, 'store']);
Route::get('concierge-support/{conciergeInquiry}', [ConciergeInquiryController::class, 'show']);
Route::put('concierge-support/{conciergeInquiry}', [ConciergeInquiryController::class, 'update']);
Route::patch('concierge-support/{conciergeInquiry}', [ConciergeInquiryController::class, 'update']);
Route::patch('concierge-support/{conciergeInquiry}/status', [ConciergeInquiryController::class, 'update']);

Route::get('career-applications', [CareerApplicationController::class, 'index']);
Route::post('career-applications', [CareerApplicationController::class, 'store']);
Route::patch('career-applications/{careerApplication}/status', [CareerApplicationController::class, 'updateStatus']);

Route::get('newsletter-subscriptions', [NewsletterSubscriptionController::class, 'index']);
Route::post('newsletter-subscriptions', [NewsletterSubscriptionController::class, 'store']);

Route::get('gift-cards', [GiftCardController::class, 'index']);
Route::post('gift-cards', [GiftCardController::class, 'store']);
