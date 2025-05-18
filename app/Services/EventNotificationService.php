<?php

namespace App\Services;

use GuzzleHttp\Client;

class EventNotificationService
{
    protected $device;

    public function __construct($device)
    {
        $this->device = $device;
    }

    /**
     * @param string $protocol 'http' veya 'https'
     * @param string $callbackUrl Abonelik callback URL (event'lerin gönderileceği adres)
     */


    public function subscribeEvent(string $protocol, string $callbackUrl)
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/Event/notification/subscribeEvent";

        $client = new Client([
            'verify' => false,
            'auth' => [$this->device->username, $this->device->password, 'digest'], // burayı digest yap
            'headers' => [
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ],
        ]);

        $xmlBody = <<<XML
<SubscribeEvent>
    <version>1</version>
    <id>1</id>
    <url>{$callbackUrl}</url>
</SubscribeEvent>
XML;

        try {
            $response = $client->post($url, ['body' => $xmlBody]);
            \Log::info("Subscribe response: " . $response->getBody()->getContents());
        } catch (\Exception $e) {
            \Log::error("Subscribe event error: " . $e->getMessage());
        }
    }



    public function listenAlarmStream(string $protocol)
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/Event/notification/alertStream";

        $client = new Client([
            'verify' => false,
            'auth' => [$this->device->username, $this->device->password, 'digest'],
            'stream' => true,
            'timeout' => 0,
        ]);

        try {
            $response = $client->get($url, ['stream' => true]);
            $body = $response->getBody();

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                if ($chunk) {
                    \Log::info("Alarm stream chunk: " . $chunk);
                    // Burada XML event parse edip işle
                }
            }
        } catch (\Exception $e) {
            \Log::error("Alarm stream error: " . $e->getMessage());
        }
    }

    public function unsubscribeEvent(string $protocol)
    {
        $port = $protocol === 'http' ? $this->device->http_port : $this->device->https_port;
        $url = "{$protocol}://{$this->device->ip}:{$port}/ISAPI/Event/notification/unSubscribeEvent";

        $client = new Client([
            'verify' => false,
            'auth' => [$this->device->username, $this->device->password, 'digest'],
            'headers' => [
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ],
        ]);

        // Eğer cihaz unsubscribe için body isterse onu da ekle
        $xmlBody = <<<XML
<UnsubscribeEvent>
    <version>1</version>
    <id>1</id>
</UnsubscribeEvent>
XML;

        try {
            $response = $client->put($url, ['body' => $xmlBody]);
            \Log::info("Unsubscribe response: " . $response->getBody()->getContents());
        } catch (\Exception $e) {
            \Log::error("Unsubscribe event error: " . $e->getMessage());
        }
    }
}
