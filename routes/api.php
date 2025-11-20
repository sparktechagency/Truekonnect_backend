<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\UserMiddelware;
use App\Http\Middleware\AdminMiddelware;
use App\Http\Middleware\BrandMiddelware;
use App\Http\Controllers\API\AppController;
use App\Http\Middleware\ReviewerMiddelware;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\SupportController;
use App\Http\Controllers\API\ReviewerController;
use App\Http\Controllers\API\WithdrawalController;
use App\Http\Controllers\API\SocialMediaController;
use App\Http\Controllers\API\ReviewerDashboardController;
use App\Http\Middleware\CommonBrandOrPerformerMiddleware;
use App\Http\Controllers\API\SocialMediaServiceController;

Route::get('/test-payment', [PaymentController::class, 'testPayment']);
Route::get('/callback', [PaymentController::class, 'callback']);
Route::post('/networks', [PaymentController::class, 'getCollectionNetworks']);


Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('signup', 'signUp');
    Route::post('signin', 'signIn');
    Route::post('forgot-password', 'forgotPassword');
    Route::put('set-new-password', 'setNewPassword'); // point to controller method
    Route::post('verify-otp', 'otpVerify');
    Route::post('changepassword', 'changePassword');
    Route::post('refreshtoken', 'refreshToken');
    Route::post('signout', 'signOut');
});
Route::prefix('app')->group(function () {
    Route::middleware(UserMiddelware::class)->controller(AppController::class)->group(function () {
        Route::controller(TaskController::class)->group(function () {
            Route::get('taskes','availableTasksForMe');
            Route::post('savetask','saveTask');
            Route::post('tasksubmited','submitTask');
            Route::get('myperformtask','myPerformTask');
        });
        Route::prefix('withdrawal')->controller(WithdrawalController::class)->group(function(){
            Route::get('walletinfo','myWalletInfo');
            Route::get('dashboardhistory','dashboardHistory');
            Route::put('tokenconvert','tokenConvert');
        });

    });
    Route::middleware(BrandMiddelware::class)->group(function () {
        Route::controller(AppController::class)->group(function () {});
        Route::prefix('task')->controller(TaskController::class)->group(function () {
            Route::post('create','createTask');
            Route::get('all','myTask');
            Route::put('edit/{id}','editTask');
        });

    });
    Route::middleware(CommonBrandOrPerformerMiddleware::class)->group(function () {
        Route::controller(AppController::class)->group(function(){
            Route::put('edit-profile', 'updateProfile');
            Route::post('switchRole', 'switchProfile');
            Route::get('allsocial', 'allSocialMedia');
            Route::put('socialverification/{id}', 'verifiedRequest');
        });
        Route::controller(SupportController::class)->group(function(){
            Route::post('openticket','newticket');
        });
        Route::post('/test-payment', [PaymentController::class, 'GetPaymentFromBrand']);
        Route::post('/performer-payment', [PaymentController::class, 'PayoutToPerformer']);
        Route::get('/callback', [PaymentController::class, 'callback'])->name('korba.callback');
        Route::post('/networks', [PaymentController::class, 'getCollectionNetworks']);
    });

});
Route::prefix('reviewer')->middleware(ReviewerMiddelware::class)->group(function () {
    Route::controller(ReviewerDashboardController::class)->prefix('account-verification')->group(function () {
            Route::get('all', 'allVerificationRequest');
            Route::get('view/{id}', 'viewSocialAccountVerify');
            Route::post('approved', 'VerifySocialAccount');
            Route::post('rejected', 'rejectSocialAccount');
    });
    Route::prefix('task')->controller(TaskController::class)->group(function () {
            Route::get('all','allTask');
            Route::put('approved/{id}','approveTask');
            Route::put('rejected/{id}','rejectTask');
            Route::put('adminreview/{id}','adminReview');

    });
    Route::prefix('performed-task')->controller(TaskController::class)->group(function () {
            Route::get('allpallperformedtask','allPerformTask');
            Route::put('approved/{id}','ptapproved');
            Route::put('rejected/{id}','ptrejectTask');
            Route::put('adminreview/{id}','ptadminReview');

    });
    Route::prefix('support')->controller(SupportController::class)->group(function(){
        Route::get('allsupportticket','allPendingTickets');
        Route::put('answerticket/{id}','answerTicket');
        Route::put('assigntoadmin/{id}','moveToAdmin');
    });
});
Route::prefix('admin')->middleware(AdminMiddelware::class)->group(function(){
    Route::controller(CountryController::class)->group(function(){
        Route::post('add-countrie', 'addNewCountry');
        Route::get('all-countrie', 'viewAllCountries');
        Route::put('edit-countrie/{id}', 'editCountry');
        Route::delete('delete-countrie/{id}', 'deleteCountry');
    });
    Route::prefix('social-media')->group(function () {
        Route::controller(SocialMediaController::class)->group(function(){
            Route::post('/add', 'addPlatform');
            Route::get('/all', 'viewAllPlatforms');
            Route::PUT('/edit/{id}', 'editPlatform');
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
            Route::get('allsupportpt','adminSupportPerformTask');
            Route::put('approvedspt/{id}','adminApprovedSPerformTask');
            Route::put('rejectedspt/{id}','adminRejectedSPerformTask');
       });
       Route::controller(SupportController::class)->group(function(){
            Route::get('allusersupport','allAdminReviewTickets');
            Route::put('answareusersupport/{id}','adminAnswerTicket');
       });
    });
});
