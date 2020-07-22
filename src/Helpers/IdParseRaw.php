<?php


namespace werk365\IdentityDocuments\Helpers;


use Illuminate\Support\Str;

class IdParseRaw
{
    public static function parse(object $document): object
    {
        $document->matched = (object) [];
        $MRZ_string = implode("", $document->MRZ);
        foreach($document->raw as $raw){
            if($raw['converted'] != "" && ! Str::contains($MRZ_string,$raw['original'])){
                foreach($document->parsed as $key=>$parsed){
                    if(Str::startsWith($raw['converted'], $parsed)){
                        $document->matched->$key = (object)[];
                        $document->matched->$key->content = $raw['original'];
                        $document->matched->$key->confidence = 1;
                    }
                }
            }
        }

        return $document;
    }
}
