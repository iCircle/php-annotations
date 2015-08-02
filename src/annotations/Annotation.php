<?php

namespace icircle\annotations;

class Annotation{
    private static $_annotations = array();

    const APPLIED_ON = "appliedOn";
    const TYPE       = "type";
    const ALLOW_NULL = "allowNull";
    const VALIDATE   = "validate";
    const DEFAULT_VALUE = "default";

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

        if($type == "NATIVE" || $type == ""){
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

    public static function getAnnotationRegistry(){
        return array_merge(array(),self::$_annotations);
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

        if(is_string($memberName) && $memberName != "*" && $memberName != ""){
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

        if(!isset($memberName) || (is_string($memberName) && ($memberName == "*" || $memberName == ""))){
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
            if(count($memberAnnotations) > 0){
                $annotations["members"] = $memberAnnotations;
            }
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
                !(is_array($tokens[$annotationNameTokenIndex]) && $tokens[$annotationNameTokenIndex][0] == T_STRING)){
                continue;
            }

            $annotationName = $tokens[$annotationNameTokenIndex][1];

            if(!array_key_exists($annotationName,self::$_annotations)){
                continue;
            }
            $annotationDefinition = self::$_annotations[$annotationName];

            // check for appliedOn
            $_appliedOn = $annotationDefinition[self::APPLIED_ON];
            if(isset($_appliedOn)){
                if(is_string($_appliedOn)){
                    if(trim(strtoupper($_appliedOn)) != "ALL" && trim(strtoupper($_appliedOn)) != trim(strtoupper($type))){
                        continue;
                    }
                }else if(is_array($_appliedOn)){
                    if(!in_array($type,$_appliedOn)){
                        continue;
                    }
                }else{
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
                        $token[1] = substr($token[1],1,strlen($token[1])-2);
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

            if(array_key_exists(self::VALIDATE,$annotationDefinition) && is_callable($annotationDefinition[self::VALIDATE])){
                $annotationValue = $annotationDefinition[self::VALIDATE]($annotationValue);
            }else if($annotationValue == null){
                // check for null
                if(array_key_exists(self::DEFAULT_VALUE,$annotationDefinition)){
                    $annotationValue = $annotationDefinition[self::DEFAULT_VALUE];
                }else if(array_key_exists(self::ALLOW_NULL,$annotationDefinition) && ($annotationDefinition[self::ALLOW_NULL] == false || trim(strtoupper($annotationDefinition[self::ALLOW_NULL])) == "FALSE")){
                    continue;
                }
            }
            if(is_string($annotationValue)){
                if(array_key_exists(self::TYPE,$annotationDefinition) && (trim(strtoupper($annotationDefinition[self::TYPE])) != "ANY" && trim(strtoupper($annotationDefinition[self::TYPE])) != "STRING")){
                    continue;
                }
            }else{
                if(array_key_exists(self::TYPE,$annotationDefinition) && (trim(strtoupper($annotationDefinition[self::TYPE])) != "ANY" && trim(strtoupper($annotationDefinition[self::TYPE])) != "ARRAY")){
                    continue;
                }
            }

            $annotations[$annotationName] = $annotationValue;
        }

        if(count($annotations) > 0){
            return $annotations;
        }else{
            return FALSE;
        }
    }







}


?>