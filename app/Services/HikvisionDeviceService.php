<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class HikvisionDeviceService
{
    protected $device;

    public function __construct(Device $device)
    {
        $this->device = $device;
    }

    public function checkDevice(): array
    {
        $status = 'Offline';
        $deviceTime = null;

        // HTTP dene
        if ($this->device->http_enabled) {
            $url = "http://{$this->device->ip}:{$this->device->http_port}/ISAPI/System/status";

            try {
                $response = Http::timeout(3)
                    ->withoutVerifying()
                    ->withBasicAuth($this->device->username, $this->device->password)
                    ->get($url);

                if ($response->successful()) {
                    $status = 'Online';
                    $deviceTime = $this->getDeviceTime('http');
                }
            } catch (\Throwable $e) {
                // logla istersen
            }
        }

        // HTTPS dene (HTTP başarısızsa)
        if ($status === 'Offline' && $this->device->https_enabled) {
            $url = "https://{$this->device->ip}:{$this->device->https_port}/ISAPI/System/status";

            try {
                $response = Http::timeout(3)
                    ->withoutVerifying()
                    ->withBasicAuth($this->device->username, $this->device->password)
                    ->get($url);

                if ($response->successful()) {
                    $status = 'Online';
                    $deviceTime = $this->getDeviceTime('https');
                }
            } catch (\Throwable $e) {
                // logla istersen
            }
        }

        return [
            'device' => $this->device->name,
            'ip' => $this->device->ip,
            'status' => $status,
            'device_time' => $deviceTime,
        ];
    }

    public function getDeviceTime(string $protocol): ?string
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;

        $endpoints = [
            '/ISAPI/System/time',    // birincil endpoint (mevcut çalışan cihazlar)
            '/ISAPI/System/status',  // alternatif endpoint (yeni cihazlar)
        ];

        foreach ($endpoints as $endpoint) {
            $url = "{$protocol}://{$this->device->ip}:{$port}{$endpoint}";

            try {
                $response = Http::timeout(3)
                    ->withoutVerifying()
                    ->withBasicAuth($this->device->username, $this->device->password)
                    ->get($url);

                if ($response->successful()) {
                    $xml = simplexml_load_string($response->body());

                    // System/time endpoint için
                    if (isset($xml->localTime)) {
                        return (string)$xml->localTime;
                    }

                    // System/status endpoint için namespace ile birlikte çek
                    $namespaces = $xml->getNamespaces(true);
                    if (isset($namespaces[''])) {
                        $xml->registerXPathNamespace('ns', $namespaces['']);
                        $time = $xml->xpath('//ns:currentDeviceTime');
                        if ($time && isset($time[0])) {
                            return (string)$time[0];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Hata varsa loglayabilirsin
                continue; // diğer endpoint'e geç
            }
        }

        return null;
    }

    public function getDeviceStorage(string $protocol): array
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/ContentMgmt/Storage/hdd";

        try {
            $response = Http::timeout(3)
                ->withoutVerifying()
                ->withDigestAuth($this->device->username, $this->device->password)
                ->get($url);
            Log::info('HDD Storage: ' . $response);
            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                $xml->registerXPathNamespace('h', 'http://www.hikvision.com/ver20/XMLSchema');

                $disks = [];
                $hdds = $xml->xpath('//h:hdd');

                foreach ($hdds as $disk) {
                    $disks[] = [
                        'id' => (string)$disk->id,
                        'name' => (string)$disk->hddName,
                        'type' => (string)$disk->hddType,
                        'status' => (string)$disk->status,
                        'capacity' => (string)$disk->capacity,
                        'free_space' => (string)$disk->freeSpace,
                        'property' => (string)$disk->property,
                    ];
                }

                return $disks;
            }
        } catch (\Throwable $e) {
            \Log::error("HDD fetch error: " . $e->getMessage());
        }

        return [];
    }

    public function getVideoInputChannels(): array
    {
        $protocol = $this->device->http_enabled ? 'http' : ($this->device->https_enabled ? 'https' : null);
        if (!$protocol) {
            return [];
        }

        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/System/Video/inputs/channels";

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->withDigestAuth($this->device->username, $this->device->password)
                ->get($url);

            $body = $response->body();
            Log::info('Channel response body: ' . $body);

            if ($response->successful()) {
                // XML parse et
                $xml = simplexml_load_string($body);
                if ($xml === false) {
                    Log::error("Failed to parse channel XML response");
                    return [];
                }

                $namespaces = $xml->getNamespaces(true);
                // xmlns var, genelde bunu da belirtmek lazım
                $xml->registerXPathNamespace('h', $namespaces[''] ?? '');

                $channels = [];

                foreach ($xml->xpath('//h:VideoInputChannel') as $channel) {
                    $channels[] = [
                        'id' => (string)$channel->id,
                        'name' => (string)$channel->name,
                        'enabled' => ((string)$channel->videoInputEnabled === 'true'),
                        'status' => (string)$channel->resDesc, // örnek olarak buraya 'resDesc' koydum
                    ];
                }

                return $channels;
            }
        } catch (\Throwable $e) {
            Log::error("Video input channels fetch error: " . $e->getMessage());
        }

        return [];
    }

    public function getNetworkStatus(string $protocol): array
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/System/Network/interfaces";

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->withDigestAuth($this->device->username, $this->device->password)
                ->get($url);
            Log::info('NetworkStatus: ' . $response);
            if ($response->successful()) {
                $xmlString = $response->body();

                // XML parse et
                $xml = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);

                // Namespace ile gelen XML'de düzgün erişim için:
                $namespaces = $xml->getNamespaces(true);
                $xml->registerXPathNamespace('ns', $namespaces['']);

                // XPath ile NetworkInterface elemanlarını al
                $interfaces = $xml->xpath('//ns:NetworkInterface');

                $networkStatus = [];

                foreach ($interfaces as $interface) {
                    $id = (string)$interface->id;
                    $ipAddress = (string)$interface->IPAddress->ipAddress;
                    $addressingType = (string)$interface->IPAddress->addressingType;
                    $subnetMask = (string)$interface->IPAddress->subnetMask;
                    $status = ''; // Status XML'de yok, boş bırakıyoruz
                    $macAddress = (string)$interface->Link->MACAddress;
                    $name = 'eth' . $id; // İsim XML'de yok, id bazlı isim verdik

                    // Yeni eklenen alanlar:
                    $gateway = isset($interface->IPAddress->DefaultGateway->ipAddress) ? (string)$interface->IPAddress->DefaultGateway->ipAddress : null;
                    $primaryDNS = isset($interface->IPAddress->PrimaryDNS->ipAddress) ? (string)$interface->IPAddress->PrimaryDNS->ipAddress : null;
                    $secondaryDNS = isset($interface->IPAddress->SecondaryDNS->ipAddress) ? (string)$interface->IPAddress->SecondaryDNS->ipAddress : null;

                    // UPnP durumu bool/string olarak
                    $upnp = isset($interface->Discovery->UPnP->enabled) ? ((string)$interface->Discovery->UPnP->enabled === 'true') : false;

                    $networkStatus[] = [
                        'id' => $id,
                        'addressingType' => $addressingType,
                        'name' => $name,
                        'ipAddress' => $ipAddress,
                        'subnetMask' => $subnetMask,
                        'status' => $status,
                        'macAddress' => $macAddress,
                        'gateway' => $gateway,
                        'primaryDNS' => $primaryDNS,
                        'secondaryDNS' => $secondaryDNS,
                        'upnp' => $upnp,
                    ];
                }

                return $networkStatus;
            }
        } catch (\Throwable $e) {
            \Log::error("Network status fetch error: " . $e->getMessage());
        }

        return [];
    }

    public function getDeviceInfo(string $protocol)
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/System/deviceInfo";

        $client = new \GuzzleHttp\Client([
            'verify' => false,
            'auth' => [$this->device->username, $this->device->password, 'digest'], // ya da basic ihtiyacına göre
            'headers' => [
                'Accept' => 'application/xml',
            ],
            'timeout' => 5,
        ]);

        try {
            $response = $client->get($url);
            // Cevap gövdesini string olarak al (XML)

            $xmlString = $response->getBody()->getContents();
            // Logla: URL ve cevap gövdesi
            \Log::info('getDeviceInfo response body: ' . $xmlString);


            $xml = simplexml_load_string($xmlString);

            if ($xml === false) {
                \Log::error("Failed parsing deviceInfo XML");
                return null;
            }

            // XML'den istediğin alanları dizi olarak çekiyoruz
            $deviceInfo = [
                'model' => (string)($xml->model ?? ''),
                'firmwareVersion' => (string)($xml->firmwareVersion ?? ''),
                'serialNumber' => (string)($xml->serialNumber ?? ''),
                'shortSerial' => substr((string)($xml->serialNumber ?? ''), -13, 9),
                'macAddress' => (string)($xml->macAddress ?? ''),
                'deviceName' => (string)($xml->deviceName ?? ''),
                'hardwareVersion' => (string)($xml->hardwareVersion ?? ''),
                'deviceID' => (string)($xml->deviceID ?? ''),
            ];

            return $deviceInfo;

        } catch (\Exception $e) {
            \Log::error("Error fetching deviceInfo: " . $e->getMessage());
            return null;
        }
    }


}
