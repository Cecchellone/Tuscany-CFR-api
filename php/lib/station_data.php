<?php

namespace CFR\SDT;

class StationData
{
    public String $type;
    public String $id;
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

    public function __construct(String $type, String $id)
    {
        $this->type = $type;
        $this->id = $id;
    }

    private function parseText(String $text, int $arguments = 4, int $window = 1): array
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
            $date = \DateTime::createFromFormat(($window == 1) ? 'd/m/Y H.i' : 'd/m/Y', $object[1]);

            $values[] = [
                'id' => intval($object[0]),
                'timestamp' => $date->getTimestamp(),
                'values' => [
                    $object[2],
                    $object[3]
                ]
            ];
        }

        return $values;
    }

    protected function getStationValues(int $window = 1): array
    {
        $id = $this->id;
        $type = $this->type;
        if ($window == 30) {
            $type .= '_men';
        }

        $curl = curl_init();

        curl_setopt_array($curl, $this->curl_defaults);
        curl_setopt($curl, CURLOPT_URL, "https://www.cfr.toscana.it/monitoraggio/dettaglio.php?id=$id&type=$type");

        $response = curl_exec($curl);
        curl_close($curl);

        return $this->parseText($response, 4, $window);
    }
}

class HYDRO extends StationData
{
    public function __construct(String $id)
    {
        $this->type = 'idro';
        $this->id = $id;
    }

    protected function parseValues(array $values): array
    {
        return [
            'timestamp' => $values['timestamp'],
            'level' => floatval($values['values'][0])
        ];
    }

    public function getStationData():Array {
        $values = parent::getStationValues();
        $values = array_map(fn ($line) => $this->parseValues($line), $values);
        return $values;
    }
}

class PLUVIO extends StationData
{
    public function __construct(String $id)
    {
        $this->type = 'pluvio';
        $this->id = $id;
    }

    protected function parseValues(array $values): array
    {
        return [
            'timestamp' => $values['timestamp'],
            'level' => floatval($values['values'][1]),
            'cumulative' => floatval($values['values'][0])
        ];
    }

    public function getStationData(Int $TimeWindow = 1):Array {
        $values = parent::getStationValues(window:$TimeWindow);
        $values = array_map(fn ($line) => $this->parseValues($line), $values);
        return $values;
    }
}

class THERMO extends StationData
{
    public function __construct(String $id)
    {
        $this->type = 'termo';
        $this->id = $id;
    }

    protected function parseValues(array $values): array
    {
        return [
            'timestamp' => $values['timestamp'],
            'temperature' => floatval($values['values'][0])
        ];
    }

    public function getStationData(Int $TimeWindow = 1):Array {
        $values = parent::getStationValues(window:$TimeWindow);
        $values = array_map(fn ($line) => $this->parseValues($line), $values);
        return $values;
    }
}

class ANEMO extends StationData
{
    public function __construct(String $id, Int $TimeWindow = 1)
    {
        $this->type = 'anemo';
        $this->id = $id;
        $this->window = $TimeWindow;
    }

    protected function parseValues(array $values): array
    {
        $wind_values = explode('/', $values['values'][0], 2);
        return [
            'timestamp' => $values['timestamp'],
            'speed' => floatval($wind_values[0]),
            'burst' => floatval($wind_values[1]),
            'direction' => floatval($values['values'][1])
        ];
    }

    public function getStationData():Array {
        $values = parent::getStationValues(window:1);
        $values = array_map(fn ($line) => $this->parseValues($line), $values);
        return $values;
    }
}

class HYGRO extends StationData
{
    public function __construct(String $id, Int $TimeWindow = 1)
    {
        $this->type = 'igro';
        $this->id = $id;
        $this->window = $TimeWindow;
    }

    private function parseValues(array $values): array
    {
        return [
            'timestamp' => $values['timestamp'],
            'level' => floatval($values['values'][0])
        ];
    }

    public function getStationData():Array {
        $values = parent::getStationValues(window:1);
        $values = array_map(fn ($line) => $this->parseValues($line), $values);
        return $values;
    }
}

class RADIO extends StationData
{
    public function __construct(String $id, Int $TimeWindow = 1)
    {
        $this->type = 'radio';
        $this->id = $id;
        $this->window = $TimeWindow;
    }

    private function parseValues(array $values): array
    {
        return [
            'timestamp' => $values['timestamp'],
            'radiance' => floatval($values['values'][0])
        ];
    }

    public function getStationData():Array {
        $values = parent::getStationValues(window:1);
        $values = array_map(fn ($line) => $this->parseValues($line), $values);
        return $values;
    }
}
