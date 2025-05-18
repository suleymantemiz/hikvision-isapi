<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\HikvisionDeviceService;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/devices",
     *     operationId="getDevices",
     *     tags={"Devices"},
     *     summary="Tüm cihazları listele",
     *     description="Sistemdeki tüm cihazların genel bilgilerini döner",
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı yanıt",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="name", type="string", example="Cihaz 1"),
     *                 @OA\Property(property="ip", type="string", example="192.168.1.10"),
     *                 @OA\Property(property="firmware_version", type="string", example="V4.20.000"),
     *                 @OA\Property(property="device_time", type="string", example="2025-05-18 13:45:00"),
     *                 @OA\Property(property="storage", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="channels", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="status", type="string", example="Online"),
     *                 @OA\Property(property="device_info", type="object"),
     *                 @OA\Property(property="network_status", type="object")
     *             )
     *         )
     *     )
     * )
     */

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


    /**
     * @OA\Get(
     *     path="/api/devices/{id}",
     *     operationId="getDeviceById",
     *     tags={"Devices"},
     *     summary="Tek bir cihaz detayını getir",
     *     description="Belirli bir cihazın durumu, depolama, ağ ve firmware bilgilerini getirir.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cihaz ID'si",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı yanıt",
     *         @OA\JsonContent(
     *             @OA\Property(property="device", type="string", example="Cihaz 1"),
     *             @OA\Property(property="ip", type="string", example="192.168.1.10"),
     *             @OA\Property(property="status", type="string", example="Online"),
     *             @OA\Property(property="device_time", type="string", example="2025-05-18 13:45:00"),
     *             @OA\Property(property="storage", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="channels", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="network_status", type="object"),
     *             @OA\Property(property="device_info", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cihaz bulunamadı"
     *     )
     * )
     */
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
