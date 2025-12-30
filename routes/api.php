<?php

use App\Http\Controllers\API\AdminDashboard;
use App\Http\Controllers\API\AdminProfile;
use App\Http\Controllers\API\AppController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ContentControll;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\EmailNotificationController;
use App\Http\Controllers\API\FinancialController;
use App\Http\Controllers\API\NotificationCenter;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PerformanceAnalytics;
use App\Http\Controllers\API\ReviewerController;
use App\Http\Controllers\API\ReviewerDashboardController;
use App\Http\Controllers\API\SocialMediaController;
use App\Http\Controllers\API\SocialMediaServiceController;
use App\Http\Controllers\API\SupportController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\UserLeaderboard;
use App\Http\Controllers\API\UserManagementController;
use App\Http\Controllers\API\WithdrawalController;
use App\Http\Middleware\AdminMiddelware;
use App\Http\Middleware\BrandMiddelware;
use App\Http\Middleware\CommonBrandOrPerformerMiddleware;
use App\Http\Middleware\ReviewerMiddelware;
use App\Http\Middleware\UserMiddelware;
use Illuminate\Support\Facades\Route;

//Route::post('/test-payment', [PaymentController::class, 'testPayment']);
////Route::get('/callback', [PaymentController::class, 'callback']);
//Route::post('/networks', [PaymentController::class, 'getCollectionNetworks']);
Route::get('/callback', [PaymentController::class, 'callbackURL'])->name('korba.callback');
//
//Route::post('card/payment', [PaymentController::class, 'cardCollection']);

Route::get('privacy/policy',[AdminProfile::class, 'privacyRetrive']);
Route::get('terms/condition',[AdminProfile::class, 'termsRetrive']);
Route::get('/ref/{referral_code}', [AppController::class,'getReferrer']);

Route::prefix('notification')->controller(NotificationCenter::class)->group(function () {
    Route::get('center','getNotification');

    Route::post('markAsRead/{id}','markAsRead');

    Route::post('markAllAsRead','markAllAsRead');

    Route::delete('delete/{id}','deleteNotification');

    Route::delete('deleteAllNotification','deleteAllNotifications');
});
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('signup', 'signUp')->name('auth.signup');
    Route::post('signin', 'signIn');
    Route::post('otp/send', 'forgetPasswordOTPSend');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('otp/verify', 'forgetOTPverify');
    Route::put('set-new-password', 'setNewPassword');
    Route::post('verify-otp', 'otpVerify')->name('admin.otp.verify');
    Route::post('verify-phone-otp', 'otpPhoneVerify');
    Route::post('resend-phone-otp', 'resendPhoneOTP');
    Route::post('resend-email-otp', 'resendEmailOTP');
    Route::post('changepassword', 'changePassword');
    Route::post('refreshtoken', 'refreshToken');
    Route::post('signout', 'signOut');
});

Route::middleware('auth:api')->group(function () {
    Route::get('my/profile', [AuthController::class,'profile']);
    Route::post('profile/update', [AuthController::class,'profileUpdate']);
    Route::get('links',[ContentControll::class,'index']);

Route::prefix('app')->group(function () {
    Route::middleware(UserMiddelware::class)->controller(AppController::class)->group(function () {
        Route::controller(TaskController::class)->group(function () {
//            Route::get('taskes','availableTasksForMe');
            Route::get('taskes','availableTasks');
            Route::get('tasks/details/{id}','singleTaskDetails');
            Route::post('savetask','saveTask');
            Route::post('tasksubmited','submitTask');
            Route::get('ongoing/tasks','ongoingTasks');
            Route::get('myperformtask','myPerformTask');
        });
        Route::prefix('withdrawal')->controller(WithdrawalController::class)->group(function(){
            Route::get('walletinfo','myWalletInfo');
            Route::get('dashboardhistory','dashboardHistory');
            Route::put('tokenconvert','tokenConvert');
        });
    });
    Route::controller(UserLeaderboard::class)->group(function () {
        Route::get('leaderboard','performerLeaderboard');
    });
    Route::controller(PaymentController::class)->group(function () {
        Route::post('/performer-payment', 'PayoutToPerformer');
    });
    Route::middleware(BrandMiddelware::class)->group(function () {
        Route::controller(AppController::class)->group(function () {
            Route::get('homepage','brandHomepage');
            Route::get('order/complete','completedTasks');
            Route::get('order/ongoing','ongoingTasks');
            Route::get('order/details/{taskId}','orderDetails');
        });

        Route::controller(PaymentController::class)->group(function () {
            Route::post('/brand-payment', 'GetPaymentFromBrand');
        });
        Route::prefix('task')->controller(TaskController::class)->group(function () {
            Route::post('create','createTask');
            Route::get('social/media','socialMedia');
            Route::get('engagement/type','engagementType');
            Route::get('all','myTask');
            Route::get('details/{id}','myTaskDetails');
            Route::get('who/got/paid','whoGotPaid');
            Route::post('edit/{id}','editTask');
        });
        Route::controller(UserLeaderboard::class)->group(function () {
            Route::get('brand/leaderboard','brandLeaderboard');
        });
    });
    Route::middleware(CommonBrandOrPerformerMiddleware::class)->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::get('my/profile','myProfile');

        });
        Route::controller(AppController::class)->group(function(){
            Route::put('edit-profile', 'updateProfile');
            Route::post('edit-image-profile', 'updateProfileImage');
            Route::delete('delete-profile', 'deleteProfile');
            Route::post('switchRole', 'switchProfile');
            Route::get('allsocial', 'allSocialMedia');
            Route::put('socialverification/{id}', 'verifiedRequest');
            Route::post('/delete/social/account/{id}', 'deleteSocialMedia');
        });
        Route::controller(SupportController::class)->group(function(){
            Route::post('openticket','newticket');
        });
        Route::controller(PaymentController::class)->group(function() {
//            Route::get('/callback', 'callbackURL')->name('korba.callback');
            Route::get('/networks', 'getCollectionNetworks');
            Route::get('/available/banks', 'bankLookup');
            Route::post('/customer/bank/account', 'customerLookup');
        });
    });

});
Route::prefix('reviewer')->middleware(ReviewerMiddelware::class)->group(function () {
    Route::controller(ReviewerDashboardController::class)->prefix('account-verification')->group(function () {
            Route::get('all', 'allVerificationRequest');
            Route::get('view/{id}', 'viewSocialAccountVerify');
            Route::post('approved/{socialId}', 'VerifySocialAccount');
            Route::post('rejected/{socialId}', 'rejectSocialAccount');
    });
    Route::prefix('task')->controller(TaskController::class)->group(function () {
            Route::get('all','allTask');
            Route::put('approved/{id}','approveTask');
            Route::post('rejected/{id}','rejectTask');
            Route::put('adminreview/{id}','adminReview');
    });
    Route::prefix('performed-task')->controller(TaskController::class)->group(function () {
            Route::get('allpallperformedtask','allPerformTask');
            Route::put('approved/{id}','ptapproved');
            Route::put('rejected/{id}','ptrejectTask');
            Route::put('adminreview/{id}','ptadminReview');
    });
    Route::controller(ReviewerController::class)->group(function () {
        Route::get('myProfile','myProfile');
        Route::post('update/profile','updateProfile');
    });
    Route::controller(ReviewerDashboardController::class)->group(function () {
        Route::get('dashboard','dashboardHistory');
    });
    Route::prefix('support')->controller(SupportController::class)->group(function(){
        Route::get('allsupportticket','allPendingTickets');
        Route::put('answerticket/{id}','answerTicket');
        Route::put('assigntoadmin/{id}','moveToAdmin');
    });
});
Route::prefix('admin')->middleware(AdminMiddelware::class)->group(function(){
    Route::controller(CountryController::class)->group(function(){
        Route::post('add-country', 'addNewCountry');
        Route::get('all-country', 'viewAllCountries');
        Route::put('edit-country/{id}', 'editCountry');
        Route::delete('delete-country/{id}', 'deleteCountry');

    });
    Route::prefix('social-media')->group(function () {
        Route::controller(SocialMediaController::class)->group(function(){
            Route::post('/add', 'addPlatform');
            Route::get('/all', 'viewAllPlatforms');
            Route::put('/edit/{id}', 'editPlatform');
            Route::delete('/delete/{id}','deletePlatform');
        });
    });
    Route::prefix('engagements')->group(function () {
        Route::controller(SocialMediaServiceController::class)->group(function(){
            Route::post('/add',  'addEngagement');
            Route::get('/all/{id}', 'viewAllEngagements');
            Route::put('/edit/{id}', 'editEngagement');
            Route::delete('/delete/{id}', 'deleteEngagement');
        });
    });
    Route::prefix('reviewer')->group(function () {
        Route::controller(ReviewerController::class)->group(function(){
            Route::post('/add',  'addReviewer');
            Route::get('/all',  'allReviewer');
            Route::post('/action/{id}',  'actionReviewer');
            Route::get('/view/{id}',  'viewReviewer');
        });
    });
    Route::prefix('support')->group(function(){
        Route::controller(TaskController::class)->group(function(){
            Route::get('allsupporttask','adminSupportTask');
            Route::put('approvedtask/{id}','adminApproveTask');
            Route::put('rejectedtask/{id}','adminRejectedTask');
            Route::get('task/details/{id}','adminTaskDetails');
            Route::get('allsupportpt/{id}','adminSupportPerformTask');
            Route::put('approvedspt/{id}','adminApprovedSPerformTask');
            Route::put('rejectedspt/{id}','adminRejectedSPerformTask');
       });
       Route::controller(SupportController::class)->group(function(){
            Route::get('allusersupport/{id}','allAdminReviewTickets');
            Route::put('answareusersupport/{id}','adminAnswerTicket');
       });
    });
    Route::prefix('management')->controller(UserManagementController::class)->group(function(){
        Route::get('user/list','index');
        Route::get('performer/details/{userId}','performerDetails');
        Route::get('all/referrals/{userId}','allReferrals');
        Route::post('change/status/{userId}','changeStatus');
        Route::post('send/token/{userId}','sendToken');
    });
    Route::prefix('task/management')->controller(TaskController::class)->group(function(){
        Route::get('active/task','taskManagement');
        Route::get('task/details/{taskId}','taskDetails');
        Route::get('all/orders','orderManagement');
        Route::get('order/details/{orderId}','orderDetails');
    });

    Route::prefix('finance')->controller(FinancialController::class)->group(function(){
        Route::get('allList', 'financialList');
        Route::post('search', 'searchUser');
        Route::post('update/{taskPerformer_id}', 'updateFinancial');
    });

    Route::prefix('promo')->controller(ContentControll::class)->group(function(){

        Route::post('links','store');
        Route::delete('links/{id}','delete');
    });

    Route::get('performance',[PerformanceAnalytics::class, 'index']);

    Route::prefix('bulk')->controller(EmailNotificationController::class)->group(function(){
        Route::post('email','bulkEmail');
        Route::post('notification','bulkNotification');
    });

    Route::controller(AdminProfile::class)->group(function(){
        Route::post('my/profile','myProfile');
        Route::post('privacy/policy','privacyPolicy');
//        Route::get('privacy/policy',[AdminProfile::class, 'privacyRetrive']);
        Route::post('privacy/policy/update','privacyPolicyUpdate');

        Route::post('terms/condition','termCondition');
//        Route::get('terms/condition',[AdminProfile::class, 'termsRetrive']);
        Route::post('terms/condition/update','termConditionUpdate');

        Route::get('list','adminList');
        Route::post('store','addAdmin');
    });

    Route::controller(AdminDashboard::class)->group(function(){
        Route::get('dashboard','adminDashboard');
    });
});
});
