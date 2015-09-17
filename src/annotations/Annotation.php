<?php

namespace icircle\annotations;

class Annotation{

    /**
     * This method returns the annotations for the specified class or specified member of the class
     * @param string $className : Name of the class
     * @param string|array $propertyName [Optional]: Name of the property(s) in the specified class,
     *                            if null then no annotations is returned for properties
     *                            if "*" or omitted then annotations is returned for all properties
     * @param string|array $constantName [Optional]: Name of the constant(s) in the specified class,
     *                            if null then no annotations is returned for constants
     *                            if "*" or omitted then annotations is returned for all constants
     * @param string|array $methodName [Optional]: Name of the method(s) in the specified class,
     *                            if null then no annotations is returned for methods
     *                            if "*" or omitted then annotations is returned for all methods
     *
     * @throws \Exception if error
     *
     * @returns array of annotations
     *
     *         array("class"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *               "properties"=>array("property1"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                "property2"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                ...),
     *               "constants"=>array("property1"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                "property2"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                ...),
     *               "methods"=>array("property1"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                "property2"=>array("annotation1"=>"value1","annotation2"=>"value2",...),
     *                                ...)
     *               )
     */
    static public function getAnnotations($className,$propertyName="*",$constantName="*",$methodName="*"){

        if(!class_exists($className,true)){
            throw new \Exception("Unable to get annotataions , Class not defined : $className");
        }

        $reflectionClass = new \ReflectionClass($className);
        $propertyNames = array();
        $constantNames = array();
        $methodNames   = array();

        // Validate inputs
        if($propertyName != null) {
            if($propertyName == "*"){
                $props = $reflectionClass->getProperties();
                foreach ($props as $prop) {
                    $propertyNames[] =  $prop->getName();
                }
            }else if(is_string($propertyName)){
                $propertyNames[] = $propertyName;
            }else if(is_array($propertyName)){
                $propertyNames = $propertyName;
            }else{
                throw new \Exception("Invalid Input");
            }
        }

        if($constantName != null) {
            if($constantName == "*"){
                $props = $reflectionClass->getConstants();
                $constantNames = array_keys($props);
            }else if(is_string($constantName)){
                $constantNames[] = $constantName;
            }else if(is_array($constantName)){
                $constantNames = $constantName;
            }else{
                throw new \Exception("Invalid Input");
            }
        }

        if($methodName != null) {
            if($methodName == "*"){
                $props = $reflectionClass->getMethods();
                foreach ($props as $prop) {
                    $methodNames[] =  $prop->getName();
                }
            }else if(is_string($methodName)){
                $methodNames[] = $methodName;
            }else if(is_array($methodName)){
                $methodNames = $methodName;
            }else{
                throw new \Exception("Invalid Input");
            }
        }

        $annotations = array();
        $classDocComment = $reflectionClass->getDocComment();
        $classAnnotations = self::getAnnotationsFromDocComment($classDocComment,"class");

        $annotations["class"] = $classAnnotations;

        //get annotations for properties
        $propertyAnnotations = array();
        $reflectionProperties = $reflectionClass->getProperties();
        foreach($reflectionProperties as $reflectionProperty){
            $_propertyName = $reflectionProperty->getName();
            if(in_array($_propertyName,$propertyNames)){
                $propertyDocComment = $reflectionProperty->getDocComment();
                $_propertyAnnotations = self::getAnnotationsFromDocComment($propertyDocComment,"property");
                if($_propertyAnnotations !== FALSE){
                    $propertyAnnotations[$_propertyName] = $_propertyAnnotations;
                }
            }
        }
        $annotations["properties"] = $propertyAnnotations;

        //get annotations for constants
        $constantAnnotations = array();
        $reflectionConstants = self::getConstDocComments($reflectionClass);
        foreach($reflectionConstants as $_constantName=>$constantDocComment){
            if(in_array($_constantName,$constantNames)){
                $_constantAnnotations = self::getAnnotationsFromDocComment($constantDocComment,"constant");
                if($_constantAnnotations !== FALSE){
                    $constantAnnotations[$_constantName] = $_constantAnnotations;
                }
            }
        }
        $annotations["constants"] = $constantAnnotations;

        //get annotations for methods
        $methodAnnotations = array();
        $reflectionMethods = $reflectionClass->getMethods();
        foreach($reflectionMethods as $reflectionMethod){
            $_methodName = $reflectionMethod->getName();
            if(in_array($_methodName,$methodNames)){
                $methodDocComment = $reflectionMethod->getDocComment();
                $_methodAnnotations = self::getAnnotationsFromDocComment($methodDocComment,"method");
                if($_methodAnnotations !== FALSE){
                    $methodAnnotations[$_methodName] = $_methodAnnotations;
                }
            }
        }
        $annotations["methods"] = $methodAnnotations;

        return $annotations;
    }

    /**
     * @param \ReflectionClass $clazz
     * @return array
     */
    private static function getConstDocComments($clazz){

        $constDocComments = array();

        $content = file_get_contents($clazz->getFileName());
        $tokens = token_get_all($content);

        $doc = null;
        $isConst = false;
        foreach($tokens as $token){

            if(!is_array($token)){
                $token = array($token,'');
            }
            list($tokenType, $tokenValue) = $token;

            switch ($tokenType){
                // ignored tokens
                case T_WHITESPACE:
                case T_COMMENT:
                    break;

                case T_DOC_COMMENT:
                    $doc = $tokenValue;
                    break;

                case T_CONST:
                    $isConst = true;
                    break;

                case T_STRING:
                    if ($isConst){
                        $constDocComments[$tokenValue] = $doc;
                    }
                    $doc = null;
                    $isConst = false;
                    break;

                // all other tokens reset the parser
                default:
                    $doc = null;
                    $isConst = false;
                    break;
            }
        }

        return $constDocComments;
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

        $docCommentLines = preg_split("/[\r]*\n/",$docComment);

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

            if(is_numeric($annotationValue)){
                $annotationValue = 0 + $annotationValue;
            }

            if(strtoupper($annotationValue) == "TRUE"){
                $annotationValue = true;
            }

            if(strtoupper($annotationValue) == "FALSE"){
                $annotationValue = false;
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