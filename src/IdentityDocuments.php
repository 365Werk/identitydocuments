<?php

namespace werk365\IdentityDocuments;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use werk365\IdentityDocuments\Filters\MergeFilter;
use werk365\IdentityDocuments\Helpers\IdCheck;
use werk365\IdentityDocuments\Helpers\IdParseRaw;
use werk365\IdentityDocuments\Helpers\IdStr;

class IdentityDocuments
{
    // Expects 1 or 2 image files in POST request, one front_img and one back_img
    public static function parse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'front_img' => 'mimes:jpeg,png,jpg|max:5120',
            'back_img' => 'mimes:jpeg,png,jpg|max:5120',
        ]);
        if ($validator->fails()) {
            return json_encode([
                'error' => $validator->errors()->first(),
                'success' => false,
            ]);
        }

        $front_img = $request->front_img;
        $back_img = $request->back_img;

        $imageAnnotator = new ImageAnnotatorClient(
            ['credentials' => config('google_key')]
        );

        $images = (object) [
            'front_img' => ($front_img) ? file_get_contents($front_img->getRealPath()) : null,
            'back_img' => ($back_img) ? file_get_contents($back_img->getRealPath()) : null,
        ];

        if ($images->front_img && $images->back_img) {
            $base_img = Image::make($images->front_img);
            $full_img = $base_img->filter(new MergeFilter(Image::make($images->back_img)));
        } elseif ($images->front_img) {
            $full_img = Image::make($images->front_img);
        } elseif ($images->back_img) {
            $full_img = Image::make($images->back_img);
        } else {
            return json_encode([
                'error' => 'Missing images',
                'success' => false,
            ]);
        }

        $response = $imageAnnotator->textDetection((string) $full_img->encode());
        $response_text = $response->getTextAnnotations();
        $full_text = $response_text[0]->getDescription();

        // Split string on newlines into array
        $lines = preg_split('/\r\n|\r|\n/', $full_text);

        foreach ($lines as $key => $line) {
            $lines[$key] = preg_replace('/\s+/', '', $line);
        }
        // Get MRZ lines from text
        $document = self::getMRZ($lines);

        // Parse lines to known values
        $document = self::parseMRZ($document);

        $all = [];
        if ($response_text) {
            foreach ($response_text as $text) {
                array_push($all, [
                    'original' => $text->getDescription(),
                    'converted' => IdStr::convert($text->getDescription()),
                ]);
            }
        }

        $document->raw = $all;

        // Validate values with MRZ checkdigits
        if ($e = self::validateMRZ($document)) {
            try {
                $document = self::stripFiller($document);
            } catch (\Exception $exception) {
                $e .= ' and stripFiller failed.';
            }

            $document->error = $e;
            $document->success = false;

            if (! config('identitydocuments.return_all')) {
                unset($document->raw);
            }

            return json_encode($document);
        }

        $document = self::stripFiller($document);

        $document = IdParseRaw::parse($document);

        if (! config('identitydocuments.return_all')) {
            unset($document->raw);
        }

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

            $document->parsed['general'] = IdStr::substrs(
                $document->MRZ[0],
                [
                    [5, 25],
                ]
            );

            $document->parsed['general'] = array_merge($document->parsed['general'], IdStr::substrs(
                $document->MRZ[1],
                [
                    [0, 7],
                    [8, 7],
                    [18, 11],
                ]
            ));
            $document->success = true;
            $document->error = null;
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

            $document->parsed['general'] = IdStr::substrs(
                $document->MRZ[1],
                [
                    [0, 10],
                    [13, 7],
                    [21, 22],
                ]
            );
            $document->success = true;
            $document->error = null;
        }
        $document->parsed = (object) $document->parsed;
        if (isset($document->parsed->general)) {
            $document->parsed->general = implode('', $document->parsed->general);
        }

        if (in_array($document->parsed->country, config('identitydocuments.countries_convert_o_to_zero')) || config('identitydocuments.countries_convert_o_to_zero')) {
            $re = '/o|O/m';
            $subst = '0';
            $document->parsed->document_number = preg_replace($re, $subst, $document->parsed->document_number);
        }

        return $document;
    }

    private static function validateMRZ($document): ?string
    {
        if ($document->type === null) {
            return 'Document not recognized';
        }

        $checks = (object) [
            'document_number' => (object) [
                'error_msg' => 'Document number check failed',
                'document_type' => ['TD1', 'TD3'],
            ],
            'date_of_birth' => (object) [
                'error_msg' => 'Date of birth check failed',
                'document_type' => ['TD1', 'TD3'],
            ],
            'expiration' => (object) [
                'error_msg' => 'Expiration date check failed',
                'document_type' => ['TD1', 'TD3'],
            ],
            'personal_number' => (object) [
                'error_msg' => 'Personal number check failed',
                'document_type' => ['TD3'],
            ],
            'general' => (object) [
                'error_msg' => 'General check failed',
                'document_type' => ['TD1, TD3'],
            ],
        ];

        foreach ($checks as $key => $check) {
            if (in_array($document->type, $check->document_type)) {
                $check->value = $document->parsed->$key ?? null;
                $check_key = $key?"check_" . $key:null;
                $check->check_value = $document->parsed->$check_key ?? null;
                if (! IdCheck::checkDigit(
                    $check->value,
                    $check->check_value
                )) {
                    return $check->error_msg;
                }
            }
        }

        return null;
    }

    private static function stripFiller(
        object $document
    ): object {
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
