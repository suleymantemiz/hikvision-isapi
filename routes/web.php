<?php

use Illuminate\Support\Facades\Route;
use App\Models\Device;
use App\Services\HikvisionDeviceService;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\AlarmController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-device/{id}', function ($id) {
    $device = Device::findOrFail($id);
    $service = new HikvisionDeviceService($device->ip, $device->username, $device->password);

    return [
        'online' => $service->isOnline(),
        'device_time' => $service->getTime()
    ];
});

Route::get('/devices/{id}', [DeviceController::class, 'show']);


Route::get('/alarm/subscribe', [AlarmController::class, 'subscribeAlarm']);
Route::get('/alarm/listen', [AlarmController::class, 'listen']);
Route::get('/alarm/unsubscribe', [AlarmController::class, 'unsubscribe']);
Route::post('/alarm/notification-receiver', [AlarmController::class, 'notificationReceiver'])->name('alarm.notification.receiver');
Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');

