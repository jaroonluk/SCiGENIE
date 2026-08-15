<?php

class Csv
{
    public static function parse(string $text): array
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $text);
        rewind($fp);
        $rows = [];
        while (($row = fgetcsv($fp)) !== false) {
            $rows[] = $row;
        }
        fclose($fp);
        return $rows;
    }
}
