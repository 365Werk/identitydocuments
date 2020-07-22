<?php

namespace werk365\IdentityDocuments\Helpers;

class IdStr
{
    public static function substrs(string $string, array $options): array
    {
        $extracts = [];
        foreach ($options as $option) {
            $extract = substr($string, $option[0], $option[1]);
            if (isset($option[2])) {
                $extracts[$option[2]] = $extract;
            } else {
                array_push($extracts, $extract);
            }
        }

        return $extracts;
    }

    public static function convert(string $string): string
    {
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $string = preg_replace("/([ ]|[-])/", "<", $string);
        $string = preg_replace("/\p{P}/u", "", $string);
        $string = strtoupper($string);

        return $string;
    }
}
