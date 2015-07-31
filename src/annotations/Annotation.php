<?php

namespace icircle\annotations;

class Annotation{
    private static $_annotations = array();

    const APPLIED_ON = "appliedOn";
    const TYPE       = "type";
    const ALLOW_NULL = "allowNull";
    const VALIDATE   = "validate";

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
            $reflectionPropertyType = null;
            try{
                $reflectionProperty = $reflectionClass->getProperty($memberName);
                $reflectionPropertyType = "property";
            }catch (\ReflectionException $re){
                try{
                    $reflectionProperty = $reflectionClass->getMethod($memberName);
                    $reflectionPropertyType = "method";
                }catch (\ReflectionException $re){
                    throw new \Exception("Unable to get annotataions , Class doesn't contain specified member [$className:$memberName]");
                }
            }

            $propertyDocComment = $reflectionProperty->getDocComment();
            return self::getAnnotationsFromDocComment($propertyDocComment,$reflectionPropertyType);
        }

        $annotations = array();
        $classDocComment = $reflectionClass->getDocComment();
        $classAnnotations = self::getAnnotationsFromDocComment($classDocComment,"class");

        $annotations["class"] = $classAnnotations;

        if(is_string($memberName) && $memberName == "*"){
            $memberAnnotations = array();
            //get all annotations for members
            $reflectionProperties = $reflectionClass->getProperties();
            foreach($reflectionProperties as $reflectionProperty){
                $propertyDocComment = $reflectionProperty->getDocComment();
                $propertyAnnotations = self::getAnnotationsFromDocComment($propertyDocComment,"property");
                if($propertyAnnotations !== FALSE){
                    $memberAnnotations[$reflectionProperty->getName()] = $propertyAnnotations;
                }
            }

            $reflectionMethods = $reflectionClass->getMethods();
            foreach($reflectionMethods as $reflectionMethod){
                $methodDocComment = $reflectionMethod->getDocComment();
                $methodAnnotations = self::getAnnotationsFromDocComment($methodDocComment,"method");
                if($methodAnnotations !== FALSE){
                    $memberAnnotations[$reflectionMethod->getName()] = $methodAnnotations;
                }
            }
            $annotations["members"] = $memberAnnotations;
        }
        return $annotations;
    }

    /**
     * @param $docComment
     * @param $type
     * @return array
     *
     * " * @key"
     * " * @key value "
     * " *@key value  "
     * " * @key value Description"
     * " * @key (value1,...)"
     * " * @key(value1,...)"
     * " * @key "value with spaces" "
     */

    static private function getAnnotationsFromDocComment($docComment,$type){

        $docCommentLines = preg_split("[\r\n]",$docComment);

        $annotations = array();
        foreach($docCommentLines as $docCommentLine){

            if(strpos($docCommentLine,"@") === FALSE){
                continue;
            }

            $tokens = token_get_all("<?php ".$docCommentLine);

            // $tokens will be of form
            // array(array(T_OPEN_TAG ,"<?php",0),
            //       array(T_WHITESPACE," ",0),
            //       "*",
            //       array(T_WHITESPACE," ",0),  // this token is optional
            //       "@",
            //       array(T_STRING,{annotationName},0),
            //       array(T_WHITESPACE," ",0),  // this token is optional
            //       {annotationValue} | array(T_STRING,{annotationValue},0) | array(T_CONSTANT_ENCAPSED_STRING ,{annotationValue},0) | array(T_LNUMBER ,{annotationValue},0) | array(T_DNUMBER ,{annotationValue},0) | "(",
            //           {annotationValue} | array(T_STRING,{annotationValue},0) | array(T_CONSTANT_ENCAPSED_STRING ,{annotationValue},0) | array(T_LNUMBER ,{annotationValue},0) | array(T_DNUMBER ,{annotationValue},0) | ","  // if previous token is "("
            //       ")",
            //       // rest of the tokens can be ignored
            //   )

            if($tokens[2] !== "*"){
                continue;
            }

            $annotationNameTokenIndex = 4;
            if(is_array($tokens[3]) && $tokens[3][0] == T_WHITESPACE){
                $annotationNameTokenIndex = 5;
            }

            // @ is not the prefix of annotationName , OR
            // if annotationName is not a string , OR
            // if count of tokens is less , then this line does not contain annotations
            if($tokens[$annotationNameTokenIndex-1] != "@" ||
                !(is_array($tokens[$annotationNameTokenIndex] && $tokens[$annotationNameTokenIndex][0] == T_STRING))){
                continue;
            }

            $annotationName = $tokens[$annotationNameTokenIndex][1];

            $annotationDefinition = self::$_annotations[$annotationName];

            if(!isset($annotationDefinition)){
                continue;
            }

            // check for appliedOn
            $_appliedOn = $annotationDefinition[self::APPLIED_ON];
            if(isset($_appliedOn)){
                if(trim(strtoupper($_appliedOn)) != "ALL" && trim(strtoupper($_appliedOn)) != trim(strtoupper($type))){
                    continue;
                }
            }

            $annotationValue = null;
            $hasSpaceBetweenNameAndValue = false;
            $errorInParsing = false;

            for($i = $annotationNameTokenIndex+1 ; $i<count($tokens);$i++){
                $token = $tokens[$i];

                if(is_string($token)){
                    if($annotationValue == null){
                        if($token == "("){
                            $annotationValue = array();
                            continue;
                        }else{
                            if($hasSpaceBetweenNameAndValue){
                                $annotationValue = $token;
                                break;
                            }else{
                                //error
                                $errorInParsing = true; break;
                            }
                        }
                    }else{
                        if($token == "," && count($annotationValue) > 0){
                            continue;
                        }else if($token == ")"){
                            break;
                        }else{
                            //error
                            $errorInParsing = true; break;
                        }
                    }
                }else{
                    if($token[0] == T_CONSTANT_ENCAPSED_STRING){
                        $token[1] = stripcslashes($token[1]);
                    }

                    if($annotationValue == null){
                        if($token[0] == T_WHITESPACE){
                            $hasSpaceBetweenNameAndValue = true;
                            continue;
                        }else{
                            if($hasSpaceBetweenNameAndValue){
                                $annotationValue = $token[1];
                                break;
                            }else{
                                //error
                                $errorInParsing = true; break;
                            }
                        }
                    }else{
                        if($token[0] == T_WHITESPACE){
                            continue;
                        }else{
                            $annotationValue[] = $token[1];
                        }
                    }

                }
            }

            if($errorInParsing){
                continue;
            }

            $_annotationValidationFunction = $annotationDefinition[self::VALIDATE];
            if(isset($_annotationValidationFunction) && is_callable($_annotationValidationFunction)){
                $annotationValue = $_annotationValidationFunction($annotationValue);
            }else if($annotationValue == null){
                // check for null
                $_allowNull = $annotationDefinition[self::ALLOW_NULL];
                if(isset($_allowNull) && ($_allowNull == false || trim(strtoupper($_allowNull)) == "FALSE")){
                    continue;
                }
            }else if(is_string($annotationValue)){
                $_type = $annotationDefinition[self::TYPE];
                if(isset($_type) && (trim(strtoupper($_type)) != "ANY" && trim(strtoupper($_type)) != "STRING")){
                    continue;
                }
            }else{
                $_type = $annotationDefinition[self::TYPE];
                if(isset($_type) && (trim(strtoupper($_type)) != "ANY" && trim(strtoupper($_type)) != "ARRAY")){
                    continue;
                }
            }

            $annotations[$annotationName] = $annotationValue;
        }

        return $annotations;
    }







}


?>