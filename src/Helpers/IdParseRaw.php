<?php

namespace werk365\IdentityDocuments\Helpers;

use DateTime;
use Illuminate\Support\Str;

class IdParseRaw
{
    public static function parse(object $document): object
    {
        $document->matched = (object) [];
        $MRZ_string = implode('', $document->MRZ);
        foreach ($document->raw as $raw) {
            if ($raw['converted'] != '' && ! Str::contains($MRZ_string, $raw['original'])) {
                // Parsed variables to match:
                // document_number
                if ($document->parsed->document_number === $raw['converted']) {
                    $document->matched->document_number = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                }
                // personal_number
                if ($document->parsed->personal_number === $raw['converted']) {
                    $document->matched->personal_number = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                }
                // date_of_birth
                $dob = $document->parsed->date_of_birth;
                $dob_YY = substr($dob, 0, 2);
                $dob_MM = substr($dob, 2, 2);
                $dateObj = DateTime::createFromFormat('!m', $dob_MM);
                $dob_Month = strtoupper($dateObj->format('M'));
                $dob_DD = substr($dob, 4, 2);
                if (
                    Str::is("$dob_DD*$dob_MM*$dob_YY", $raw['converted']) ||
                    Str::is("*$dob_YY*$dob_MM*$dob_DD", $raw['converted']) ||
                    Str::is("$dob_MM*$dob_DD*$dob_YY", $raw['converted']) ||
                    Str::is("$dob_DD*$dob_Month*$dob_YY", $raw['converted']) ||
                    Str::is("*$dob_YY*$dob_Month*$dob_DD", $raw['converted']) ||
                    Str::is("$dob_Month*$dob_DD*$dob_YY", $raw['converted'])
                ) {
                    $document->matched->date_of_birth = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                }
                // sex
                if ($document->parsed->sex === $raw['converted'] || Str::is($document->parsed->sex.$document->parsed->sex, $raw['converted'])) {
                    $document->matched->sex = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                }
                // expiration
                $exp = $document->parsed->expiration;
                $exp_YY = substr($exp, 0, 2);
                $exp_MM = substr($exp, 2, 2);
                $dateObj = DateTime::createFromFormat('!m', $exp_MM);
                $exp_Month = strtoupper($dateObj->format('M'));
                $exp_DD = substr($exp, 4, 2);
                if (
                    Str::is("$exp_DD*$exp_MM*$exp_YY", $raw['converted']) ||
                    Str::is("*$exp_YY*$exp_MM*$exp_DD", $raw['converted']) ||
                    Str::is("$exp_MM*$exp_DD*$exp_YY", $raw['converted']) ||
                    Str::is("$exp_DD*$exp_Month*$exp_YY", $raw['converted']) ||
                    Str::is("*$exp_YY*$exp_Month*$exp_DD", $raw['converted']) ||
                    Str::is("$exp_Month*$exp_DD*$exp_YY", $raw['converted'])
                ) {
                    $document->matched->expiration = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                }
                // nationality
                if ($document->parsed->nationality === $raw['converted']) {
                    $document->matched->nationality = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                }
                // surname
                $surname = $document->parsed->surname;
                $surname_characters = str_split($surname);
                $surname_search = implode('*', $surname_characters).'*';

                if ($surname === $raw['converted']) {
                    $document->matched->surname = [
                        'value' => $raw['original'],
                        'confidence' => 1,
                    ];
                } elseif (Str::startsWith($raw['converted'], $surname)) {
                    if (! isset($document->matched->surname) || strlen($document->matched->surname['value']) > strlen($raw['original'])) {
                        $document->matched->surname = [
                            'value' => $raw['original'],
                            'confidence' => 0.9,
                        ];
                    }
                } elseif (Str::is($surname_search, $raw['converted'])) {
                    if (! isset($document->matched->surname) || strlen($document->matched->surname['value']) > strlen($raw['original'])) {
                        $document->matched->surname = [
                            'value' => $raw['original'],
                            'confidence' => 0.75,
                        ];
                    }
                }
                // given_names
                $given_names = $document->parsed->given_names;
                $given_names_split = explode(' ', $given_names);
                $given_names_search = [];
                foreach ($given_names_split as $key=>$given_name) {
                    $given_names_characters = str_split($given_name);
                    $given_names_search[$key] = '*'.implode('*', $given_names_characters).'*';
                }

                if (! isset($document->matched->given_names)) {
                    $document->matched->given_names = [];
                }
                foreach ($given_names_split as $key=>$given_name) {
                    if ($given_name === $raw['converted']) {
                        $document->matched->given_names[$key] = [
                            'value' => $raw['original'],
                            'confidence' => 1,
                        ];
                    } elseif (Str::startsWith($raw['converted'], $given_name)) {
                        if (! isset($document->matched->given_names[$key]['value']) || strlen($document->matched->given_names[$key]['value']) > strlen($raw['original'])) {
                            $document->matched->given_names[$key] = [
                                'value' => $raw['original'],
                                'confidence' => 0.9,
                            ];
                        }
                    } elseif (Str::is($given_names_search[$key], $raw['converted'])) {
                        if (! isset($document->matched->given_names[$key]['value']) || strlen($document->matched->given_names[$key]['value']) > strlen($raw['original'])) {
                            $document->matched->given_names[$key] = [
                                'value' => $raw['original'],
                                'confidence' => 0.75,
                            ];
                            array_push($bla, $document->matched->given_names[$key]);
                        }
                    } else {
                        $chars_raw = str_split($raw['converted']);
                        $chars_search = implode('*', $chars_raw);
                        if (Str::is($chars_search, $given_name) && (! isset($document->matched->given_names[$key]['value']) || strlen($document->matched->given_names[$key]['value']) > strlen($raw['original']))) {
                            $document->matched->given_names[$key] = [
                                'value' => $raw['original'],
                                'confidence' => 0.75,
                            ];
                        }
                    }
                }
            }
        }
        return $document;
    }
}
