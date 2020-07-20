<?php

namespace werk365\IdentityDocuments;

use Exception;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Http\Request;
use werk365\IdentityDocuments\Helpers\IdCheck;
use werk365\IdentityDocuments\Helpers\IdStr;

class IdentityDocuments
{
    // Expexts 1 or 2 image files in POST request, one front_img and one back_img
    public static function parse(Request $request)
    {
        $imageAnnotator = new ImageAnnotatorClient(
            ['credentials' => config('google_key')]
        );

        $images = (object) [
            'front_img' => ($request->front_img) ? file_get_contents($request->front_img->getRealPath()) : null,
            'back_img' => ($request->back_img) ? file_get_contents($request->back_img->getRealPath()) : null,
        ];
        $responses = (object) [
            'front_img' => ($request->front_img) ? $imageAnnotator->textDetection($images->front_img) : null,
            'back_img' => ($request->back_img) ? $imageAnnotator->textDetection($images->back_img) : null,
        ];

        $texts = (object) [
            'front_img' => ($request->front_img) ? $responses->front_img->getTextAnnotations() : null,
            'back_img' => ($request->back_img) ? $responses->back_img->getTextAnnotations() : null,
        ];

        // Combine text found on both images into one string
        $full_text = '';
        $full_text .= ($request->front_img) ? $texts->front_img[0]->getDescription() : '';
        $full_text .= ($request->back_img) ? $texts->back_img[0]->getDescription() : '';

        // Split string on newlines into array
        $lines = preg_split('/\r\n|\r|\n/', $full_text);

        foreach ($lines as $key => $line) {
            $lines[$key] = preg_replace('/\s+/', '', $line);
        }

        // Get MRZ lines from text
        $document = self::getMRZ($lines);

        // Parse lines to known values
        $document = self::parseMRZ($document);

        // Validate values with MRZ checkdigits
        if ($e = self::validateMRZ($document)) {
            throw new Exception("Error validating MRZ, invalid $e.");
        }

        $document = self::stripFiller($document);

        $all = [];
        if ($texts->front_img) {
            foreach ($texts->front_img as $text) {
                array_push($all, $text->getDescription());
            }
        }

        if ($texts->back_img) {
            foreach ($texts->back_img as $text) {
                array_push($all, $text->getDescription());
            }
        }

        $document->raw = $all;

        return json_encode($document);
    }

    private static function getMRZ(array $lines): object
    {
        $document = (object) [
            'type' => null,
            'MRZ' => [],
            'parsed' => (object) [],
        ];
        foreach ($lines as $key => $line) {
            if (strlen($line) === 30 && ($line[0] === 'I' || $line[0] === 'A' || $line[0] === 'C') && strlen($lines[$key + 1]) === 30 && strlen($lines[$key + 2]) === 30) {
                $document->type = 'TD1';
                $document->MRZ[0] = $line;
                $document->MRZ[1] = $lines[$key + 1];
                $document->MRZ[2] = $lines[$key + 2];
                break;
            } elseif (strlen($line) === 44 && ($line[0] === 'P') && strlen($lines[$key + 1]) === 44) {
                $document->type = 'TD3';
                $document->MRZ[0] = $line;
                $document->MRZ[1] = $lines[$key + 1];
                break;
            } elseif (strlen($line) === 36 && ($line[0] === 'V') && strlen($lines[$key + 1]) === 36) {
                $document->type = 'VISA';
                $document->MRZ[0] = $line;
                $document->MRZ[1] = $lines[$key + 1];
                break;
            }
        }

        return $document;
    }

    private static function parseMRZ(object $document): object
    {
        if ($document->type === 'TD1') {
            // Row 1
            $document->parsed = IdStr::substrs(
                $document->MRZ[0],
                [
                    [0, 1, 'document'],
                    [1, 1, 'type'],
                    [2, 3, 'country'],
                    [5, 9, 'document_number'],
                    [14, 1, 'check_document_number'],
                    [15, 15, 'personal_number'],
                ]
            );

            // Row 2
            $document->parsed = array_merge($document->parsed, IdStr::substrs(
                $document->MRZ[1],
                [
                    [0, 6, 'date_of_birth'],
                    [6, 1, 'check_date_of_birth'],
                    [7, 1, 'sex'],
                    [8, 6, 'expiration'],
                    [14, 1, 'check_expiration'],
                    [15, 3, 'nationality'],
                    [18, 11, 'optional'],
                    [29, 1, 'check_general'],
                ]
            ));

            // Row 3
            $document->parsed = array_merge($document->parsed, IdStr::substrs(
                $document->MRZ[2],
                [
                    [0, 30, 'names'],
                ]
            ));
        } elseif ($document->type === 'TD3') {
            // Row 1
            $document->parsed = IdStr::substrs(
                $document->MRZ[0],
                [
                    [0, 1, 'document'],
                    [1, 1, 'type'],
                    [2, 3, 'country'],
                    [5, 39, 'names'],
                ]
            );

            // Row 2
            $document->parsed = array_merge($document->parsed, IdStr::substrs(
                $document->MRZ[1],
                [
                    [0, 9, 'document_number'],
                    [9, 1, 'check_document_number'],
                    [10, 3, 'nationality'],
                    [13, 6, 'date_of_birth'],
                    [19, 1, 'check_date_of_birth'],
                    [20, 1, 'sex'],
                    [21, 6, 'expiration'],
                    [27, 1, 'check_expiration'],
                    [28, 14, 'personal_number'],
                    [42, 1, 'check_personal_number'],
                    [43, 1, 'check_general'],
                ]
            ));
        }
        $document->parsed = (object) $document->parsed;

        return $document;
    }

    private static function validateMRZ($document): ?string
    {
        // Validate MRZ
        if (! IdCheck::checkDigit(
            $document->parsed->document_number,
            $document->parsed->check_document_number
        )) {
            return 'Document number';
        }
        if (! IdCheck::checkDigit(
            $document->parsed->date_of_birth,
            $document->parsed->check_date_of_birth
        )) {
            return 'Date of birth';
        }
        if (! IdCheck::checkDigit(
            $document->parsed->expiration,
            $document->parsed->check_expiration
        )) {
            return 'Expiration date';
        }
        if ($document->type === 'TD3') {
            if (! IdCheck::checkDigit(
                $document->parsed->personal_number,
                $document->parsed->check_personal_number
            )) {
                return 'Personal number';
            }
        }

        return null;
    }

    private static function stripFiller(object $document): object
    {
        $names = explode('<<', $document->parsed->names, 2);
        $document->parsed->surname = trim(str_replace('<', ' ', $names[0]));
        $document->parsed->given_names = trim(str_replace('<', ' ', $names[1]));
        unset($document->parsed->names);
        foreach ($document->parsed as $key => $value) {
            $document->parsed->$key = trim(str_replace('<', ' ', $value));
        }

        return $document;
    }
}
