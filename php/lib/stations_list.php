<?php

namespace CFR\SLT;

class StationList {
    protected Array $curl_defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Cookie: language=IT; speed=m%2Fs'
        ),
    ];

    public function __construct()
    {
        $x = 1;
    }

    private function parseText(String $text, int $arguments = 4): array
    {
        $lines = explode("\n", $text);
        $lines = array_map(fn ($line) => trim($line, " \r"), $lines);
        $start_line = array_search('//<![CDATA[', $lines) + 1;
        $end_line = array_search('//]]>', $lines) - 1;
        $lines = array_slice($lines, $start_line, $end_line - $start_line + 1);
        $lines = array_filter($lines, fn ($line) => str_starts_with($line, 'VALUES'));

        $values = [];
        foreach ($lines as $line) {
            $stripped = explode(');', explode('Array(', $line, 2)[1], 2)[0];
            $object = explode(',', $stripped, $arguments);
            $object = array_map(fn ($val) => trim($val, ' "'), $object);

            $data = array_slice($object, 5);
            $data = array_map(fn($x)=> strip_tags($x), $data);
            $data = array_map(fn($x)=> (($x==='-') ? NULL : $x), $data);

            $values[] = [
                'id' => $object[0],
                'name' => $object[1],
                'province' => $object[2],
                'zone' => $object[3],
                'altitude' => $object[4],
                'values' => $data
            ];
        }

        return $values;
    }

    private function fetchStations(string $type): array {
        $curl = curl_init();

        curl_setopt_array($curl, $this->curl_defaults);
        curl_setopt($curl, CURLOPT_URL, "https://www.cfr.toscana.it/monitoraggio/stazioni.php?type=$type");

        $response = curl_exec($curl);
        curl_close($curl);

        $stations = $this->parseText($response, 21);
        return $stations;
    }

    public function getAllStationsOfType(string $type): array {
        $stations = $this->fetchStations($type);
        $identifiers = array_map(fn($station)=>$station['id'], $stations);
        return array_values($identifiers);
    }

    public function getStationInfo(string $type, array $id): array {
        $stations = $this->fetchStations($type);
        // print(json_encode($stations, JSON_PRETTY_PRINT));die;
        $matches = [];
        foreach ($stations as $station) {
            // print($station['id'] . "\n");
            if (in_array($station['id'], $id)) {
                $matches[] = $station;
                if (count($matches) >= count($id)) {
                    break;
                }
            }
        }
        return $matches;
    }

}
