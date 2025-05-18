<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\HikvisionDeviceService;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::all();

        $data = [];

        foreach ($devices as $device) {
            $service = new HikvisionDeviceService($device);

            $protocol = $device->http_enabled ? 'http' : ($device->https_enabled ? 'https' : 'http');

            try {
                $deviceInfo = $service->getDeviceInfo($protocol);
                $firmwareVersion = $deviceInfo['firmwareVersion'] ?? $deviceInfo['version'] ?? null;
            } catch (\Exception $e) {
                Log::error("getDeviceInfo error for {$device->ip}: " . $e->getMessage());
                $deviceInfo = [];
                $firmwareVersion = null;
            }

            try {
                $deviceTime = $service->getDeviceTime($protocol);
            } catch (\Exception $e) {
                Log::error("getDeviceTime error for {$device->ip}: " . $e->getMessage());
                $deviceTime = null;
            }

            try {
                $storage = $service->getDeviceStorage($protocol);
                foreach ($storage as &$disk) {
                    $disk['capacity'] = $this->formatMBtoTB($disk['capacity']);
                    $disk['free_space'] = $this->formatMBtoTB($disk['free_space']);
                }
                unset($disk);
            } catch (\Exception $e) {
                Log::error("getDeviceStorage error for {$device->ip}: " . $e->getMessage());
                $storage = [];
            }

            try {
                $channels = $service->getVideoInputChannels();
            } catch (\Exception $e) {
                Log::error("getVideoInputChannels error for {$device->ip}: " . $e->getMessage());
                $channels = [];
            }

            $status = $service->checkDevice();
            $network_status = $service->getNetworkStatus($protocol);

            $data[] = [
                'name' => $device->name,
                'ip' => $status['ip'] ?? $device->ip,
                'firmware_version' => $firmwareVersion,
                'device_time' => $deviceTime,
                'storage' => $storage,
                'channels' => $channels,
                'status' => $status['status'] ?? null,
                'device_info' => $deviceInfo,
                'network_status' => $network_status,
            ];
        }


        return view('devices.index', ['devices' => $data]);
    }


    public function show($id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $hikvisionService = new HikvisionDeviceService($device);

        try {
            $result = $hikvisionService->checkDevice();

            $protocol = $device->http_enabled ? 'http' : ($device->https_enabled ? 'https' : 'http');

            // Storage bilgisi al
            $storage = $hikvisionService->getDeviceStorage($protocol);
            $result['storage'] = $storage ?? [];

            // Kanal bilgisi al
            $channels = $hikvisionService->getVideoInputChannels($protocol);
            $result['channels'] = $channels ?: [];

            // Ağ durumu al
            $networkStatus = $hikvisionService->getNetworkStatus($protocol);
            $result['network_status'] = $networkStatus ?: [];

            // Firmware versiyonu al
            $deviceInfo = $hikvisionService->getDeviceInfo($protocol);
            $result['device_info'] = $deviceInfo ?: [];


        } catch (\Exception $e) {
            \Log::error("DeviceController@show error: " . $e->getMessage());

            $result = [
                'device' => $device->name,
                'ip' => $device->ip,
                'status' => 'Offline',
                'device_time' => null,
                'storage' => [],
                'channels' => [],
                'network_status' => [],
            ];
        }

        return response()->json($result);
    }


    function formatMBtoTB(float $mbValue, int $decimals = 2): string
    {
        $tbValue = $mbValue / 1024 / 1024; // MB -> TB (1 TB = 1024 * 1024 MB)
        return number_format($tbValue, $decimals) . ' TB';
    }


}
