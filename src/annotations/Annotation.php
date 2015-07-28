<?php

namespace icircle\annotations;

class Annotation{
    private static $_annotations = array();

    /**
     * @param $source
     * @param string $type  - type of the $source
     *                        "native" : PHP associative array of annotations
     *                        "json"   : JSON Object of annotations (as a string)
     *                        "path"   : path to json file containing annotation definitions
     */
    public static function registerAnnotations($source, $type="native"){
        $type = strtoupper($type);

        if($type == "PATH"){
            if(file_exists($source)){
                $source = file_get_contents($source);
                $type = "JSON";
            }else{
                throw new \Exception("Error in registering annotations , File Not Found : $source");
            }
        }

        if($type == "JSON"){
            $decodedSource = json_decode($source,true);
            if($decodedSource === NULL){
                throw new \Exception("Error in registering annotations , Unable to Parse JSON : $source");
            }

            $source = $decodedSource;
            $type = "NATIVE";
        }





    }

}


?>