<?php

class CFR
{
    public String $type;
    public String $id;
    public Int $window;


    public function __construct(String $type, String $id, Int $TimeWindow = 1)
    {
        $this->type = $type;
        $this->id = $id;
        $this->window = $TimeWindow;
    }

    private function parseText(String $text): array
    {
        $lines = explode("\n", $text);
        $lines = array_map(fn ($line) => trim($line, " \r"), $lines);
        $start_line = array_search('//<![CDATA[', $lines) + 1;
        $end_line = array_search('//]]>', $lines) - 1;
        $lines = array_slice($lines, $start_line, $end_line - $start_line + 1);
        $lines = array_filter($lines, fn ($line) => str_starts_with($line, 'VALUES'));
        return $lines;
    }

    private function parseLine(String $line): array
    {
        $stripped = explode(');', explode('Array(', $line, 2)[1], 2)[0];
        $values = explode(',', $stripped, 4);
        $values = array_map(fn ($val) => trim($val, ' "'), $values);
        $date = DateTime::createFromFormat(($this->window==1) ? 'd/m/Y H.i' : 'd/m/Y', $values[1]);

        return [
            'id' => intval($values[0]),
            'timestamp' => $date->getTimestamp(),
            'values' => [
                $values[2],
                $values[3]
            ]
        ];
    }

    public function getData(): array
    {

        $id = $this->id;
        $type = $this->type;
        if ($this->window == 30) {
            $type .= '_men';
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.cfr.toscana.it/monitoraggio/dettaglio.php?id=$id&type=$type",
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
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $lines = $this->parseText($response);

        $parser = fn ($line) => [];
        switch ($this->type) {
            case 'idro':
                $parser = fn ($line) => $this->parseHydroLine($line);
                break;
            
            case 'pluvio':
                $parser = fn ($line) => $this->parsePluvioLine($line);
                break;

            case 'termo':
                $parser = fn ($line) => $this->parseThermoLine($line);
                break;
            
            case 'anemo':
                $parser = fn ($line) => $this->parseAnemoLine($line);
                break;

            case 'igro':
                $parser = fn ($line) => $this->parseHygroLine($line);
                break;
        }


        $values = array_map(fn ($line) => $parser($line), $lines);
        return $values;
    }

    private function parseHydroLine(String $line): array
    {
        $values = $this->parseLine($line);
        return [
            'timestamp' => $values['timestamp'],
            'level' => floatval($values['values'][0])
        ];
    }

    private function parsePluvioLine(String $line): array
    {
        $values = $this->parseLine($line);
        return [
            'timestamp' => $values['timestamp'],
            'level' => floatval($values['values'][0]),
            'cumulative' => floatval($values['values'][1])
        ];
    }

    private function parseThermoLine(String $line): array
    {
        $values = $this->parseLine($line);
        return [
            'timestamp' => $values['timestamp'],
            'temperature' => floatval($values['values'][0])
        ];
    }

    private function parseAnemoLine(String $line): array
    {
        $values = $this->parseLine($line);
        $wind_values = explode('/', $values['values'][0], 2);
        return [
            'timestamp' => $values['timestamp'],
            'speed' => floatval($wind_values[0]),
            'burst' => floatval($wind_values[1]),
            'direction' => floatval($values['values'][1])
        ];
    }

    private function parseHygroLine(String $line): array
    {
        $values = $this->parseLine($line);
        return [
            'timestamp' => $values['timestamp'],
            'humidity' => floatval($values['values'][0])
        ];
    }
}

$cfr = new CFR('pluvio', 'TOS03002016', 1);
$data = $cfr->getData();
print_r($data);
