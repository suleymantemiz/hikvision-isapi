<?php

namespace App\Http\Controllers;

use App\Services\EventNotificationService;
use Illuminate\Http\Request;
use App\Models\Device;

class AlarmController extends Controller
{
    public function subscribeAlarm()
    {
        $device = Device::find(2); // veya dinamik olarak istediğin device

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $eventService = new EventNotificationService($device);

        // Callback URL: kendi sunucundaki bildirim alma endpointi
        $callbackUrl = route('alarm.notification.receiver');

        $eventService->subscribeEvent('http', $callbackUrl);

        return response()->json(['message' => 'Subscribe request sent']);
    }


    public function listen(Request $request)
    {
        $device = Device::find(2);

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $eventService = new EventNotificationService($device);
        $eventService->listenAlarmStream('http');

        return response()->json(['message' => 'Listening to alarm stream']);
    }

    public function unsubscribe(Request $request)
    {
        $device = Device::find(1);

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $eventService = new EventNotificationService($device);
        $eventService->unsubscribeEvent('http');

        return response()->json(['message' => 'Unsubscribed from events']);
    }

    public function notificationReceiver(Request $request)
    {
        // Gelen bildirim verisini logla veya işle
        \Log::info('Alarm notification received:', $request->all());

        return response()->json(['status' => 'received']);
    }
}
