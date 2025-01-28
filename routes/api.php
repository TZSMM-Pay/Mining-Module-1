use App\Http\Controllers\user\TZSMMPayController;

Route::any('callback/{id}', [TZSMMPayController::class, 'callback'])->name('tzsmmpay.callback');
