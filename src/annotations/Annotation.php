<?php

namespace icircle\annotations;

class Annotation{
    private static $_annotations = array();

    /**
     * Method to register Annotation Definitions
     * @param $source
     * @param string $type  - type of the $source
     *                        "native" : PHP associative array of annotations
     *                        "json"   : JSON Object of annotations (as a string)
     *                        "path"   : path to json file containing annotation definitions
     *
     * @throws \Exception if Error
     */
    public static function registerAnnotations($source, $type="native"){
        $type = strtoupper(trim($type));

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

        if($type == "NATIVE"){
            self::$_annotations = array_merge(self::$_annotations,$source);
        }else{
            throw new \Exception("Error in registering annotations , Invalid type of source : $type");
        }
    }

    /**
     * Method to unregister annotation definitions
     *
     * @param mixed $annotationNames :
     *                       - array of annotation names to unregister
     *                       - string of single annotation name to unregister
     *                       - If not passed all annotations are unregistered
     *
     * @throws \Exception if Error
     */
    public static function unRegisterAnnotations($annotationNames = array()){
        if(!is_array($annotationNames) && !is_string($annotationNames)){
            throw new \Exception("Error in unregistering annotations , Invalid input $annotationNames");
        }

        if(is_string($annotationNames)){
            $annotationNames = array($annotationNames);
        }

        if(count($annotationNames) == 0){
            self::$_annotations = array();
        }else{
            foreach($annotationNames as $annotationName){
                unset(self::$_annotations[$annotationName]);
            }
        }
    }

    /**
     * This method returns the annotations for the specified class or specified member of the class
     * @param string $className : Name of the class
     * @param string $memberName [Optional]: Name of the property or method in the specified class,
     *                          if "*" then annotations is returned for all properties and methods
     *
     * @throws \Exception if error
     *
     * @returns array of annotations
     *         if $memberName is specified
     *         array("annotation1"=>"value1","annotation2"=>"value2",...)
     *
     *         if $memberName is "*" or null (if null , returned array contains no "members")
     *         array("class"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *               "members"=>array("member1"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                "member2"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                ...)
     *               )
     */
    static public function getAnnotations($className,$memberName=null){

        if(!class_exists($className,true)){
            throw new \Exception("Unable to get annotataions , Class not defined : $className");
        }

        $reflectionClass = new \ReflectionClass($className);

        if(is_string($memberName) && $memberName != "*"){
            $reflectionProperty = null;
            try{
                $reflectionProperty = $reflectionClass->getProperty($memberName);
            }catch (\ReflectionException $re){
                try{
                    $reflectionProperty = $reflectionClass->getMethod($memberName);
                }catch (\ReflectionException $re){
                    throw new \Exception("Unable to get annotataions , Class doesn't contain specified member [$className:$memberName]");
                }
            }

            $propertyDocComment = $reflectionProperty->getDocComment();
            return self::getAnnotationsFromDocComment($propertyDocComment);
        }

        $annotations = array();
        $classDocComment = $reflectionClass->getDocComment();
        $classAnnotations = self::getAnnotationsFromDocComment($classDocComment);

        $annotations["class"] = $classAnnotations;

        if(is_string($memberName) && $memberName == "*"){
            $memberAnnotations = array();
            //get all annotations for members
            $reflectionProperties = $reflectionClass->getProperties();
            foreach($reflectionProperties as $reflectionProperty){
                $propertyDocComment = $reflectionProperty->getDocComment();
                $propertyAnnotations = self::getAnnotationsFromDocComment($propertyDocComment);
                if($propertyAnnotations !== FALSE){
                    $memberAnnotations[$reflectionProperty->getName()] = $propertyAnnotations;
                }
            }

            $reflectionMethods = $reflectionClass->getMethods();
            foreach($reflectionMethods as $reflectionMethod){
                $methodDocComment = $reflectionMethod->getDocComment();
                $methodAnnotations = self::getAnnotationsFromDocComment($methodDocComment);
                if($methodAnnotations !== FALSE){
                    $memberAnnotations[$reflectionMethod->getName()] = $methodAnnotations;
                }
            }
            $annotations["members"] = $memberAnnotations;
        }
        return $annotations;
    }

    static private function getAnnotationsFromDocComment($docComment){
        return array();
    }







}


?>