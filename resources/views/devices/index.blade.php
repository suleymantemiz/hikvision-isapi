@extends('layouts.app')

@section('content')
    <div class="container my-4">
        <h2 class="mb-4">Cihazlar</h2>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th>İsim</th>
                    <th>IP</th>
                    <th>Cihaz Bilgisi</th>
                    <th>Tarih/Saat</th>
                    <th>HDD Bilgisi</th>
                    <th>Kanal Bilgileri</th>
                </tr>
                </thead>
                <tbody>
                @foreach($devices as $device)
                    <tr>
                        <td>{{ $device['name'] }}</td>
                        <td> @if(!empty($device['network_status']))
                                @foreach ($device['network_status'] as $network)

                                        <strong>Adres Tipi:</strong> {{ $network['addressingType'] ?? '-' }}<br>
                                        <strong>IP Adres:</strong> {{ $network['ipAddress'] ?? '-' }}<br>
                                        <strong>Subnet Mask:</strong> {{ $network['subnetMask'] ?? '-' }}<br>
                                        <strong>Gateway:</strong> {{ $network['gateway'] ?? '-' }}<br>
                                        <strong>Birincil DNS:</strong> {{ $network['primaryDNS'] ?? '-' }}<br>
                                        <strong>İkincil DNS:</strong> {{ $network['secondaryDNS'] ?? '-' }}<br>
                                        <strong>MAC Adresi:</strong> {{ $network['macAddress'] ?? '-' }}<br>

                                @endforeach
                            @else
                                <span>-</span>
                            @endif</td>
                        <td><li><strong>Model:</strong> {{ $device['device_info']['model'] ?? '-' }}</li>
                            <li><strong>Firmware Versiyonu:</strong> {{ $device['device_info']['firmwareVersion'] ?? '-' }}</li>
                            <li><strong>Seri Numarası:</strong> {{ $device['device_info']['serialNumber'] ?? '-' }}</li>
                            <li><strong>Kısa Seri Numarası:</strong> {{ $device['device_info']['shortSerial'] ?? '-' }}</li>
                            <li><strong>MAC Adresi:</strong> {{ $device['device_info']['macAddress'] ?? '-' }}</li>
                            <li><strong>Cihaz Adı:</strong> {{ $device['device_info']['deviceName'] ?? '-' }}</li>
                            <li><strong>Donanım Versiyonu:</strong> {{ $device['device_info']['hardwareVersion'] ?? '-' }}</li>
                            <li><strong>Cihaz ID:</strong> {{ $device['device_info']['deviceID'] ?? '-' }}</li></td>

                        <td>
                            @if($device['device_time'])
                                {{ \Carbon\Carbon::parse($device['device_time'])->format('Y-m-d H:i:s') }}
                            @else
                                Bilgi Yok
                            @endif
                        </td>

                        <td>
                            @if(!empty($device['storage']))
                                @foreach($device['storage'] as $disk)
                                    @php
                                        $status = strtolower($disk['status'] ?? 'unknown');
                                        $color = 'red'; // default renk

                                        if ($status === 'ok') {
                                            $color = 'green';
                                        } elseif ($status === 'warning' || $status === 'degraded') {
                                            $color = 'orange';
                                        }
                                    @endphp
                                    <div>
                                        <strong>Disk Adı:</strong> {{ $disk['name'] ?? '-' }}<br>
                                        <strong>Tür:</strong> {{ $disk['type'] ?? '-' }}<br>
                                        <strong><span style="color: {{ $color }}"> Durum: {{ ucfirst($status) }}</span></strong><br>
                                        <strong>Toplam:</strong> {{ $disk['capacity'] ?? '-' }}<br>
                                        <strong>Boş:</strong> {{ $disk['free_space'] ?? '-' }}<br>
                                    </div>
                                    <hr>
                                @endforeach
                            @else
                                Bilgi Yok
                            @endif


                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#channels-{{ $loop->index }}" aria-expanded="false" aria-controls="channels-{{ $loop->index }}">
                                Kanalları Göster/Gizle
                            </button>

                            <div class="collapse mt-2" id="channels-{{ $loop->index }}">
                                @if(!empty($device['channels']) && count($device['channels']) > 0)
                                    <ul class="list-group">
                                        @foreach($device['channels'] as $index => $channel)
                                            <div>
                                                Kamera-{{ $index + 1 }}:
                                                @if(strtolower($channel['status'] ?? '') === 'no video')
                                                    Boş
                                                @else
                                                    {{ $channel['status'] ?? '-' }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>Kanallar bilgisi yok</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
