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
}
