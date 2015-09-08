<?php

namespace icircle\annotations;

/**
 * Class SampleClass
 * @package icircle\annotations
 * @required
 * @tableName SAMPLE
 */
class SampleClass{
    /**
     * @columnName PEROPERT1
     * @required
     * @get true
     * @set false
     * @reference
     */
    private $property1 = null;

    /**
     * @columnName PEROPERT2
     * @required
     * @get true
     * @set false
     * @reference
     */
    private $property2 = null;

    /**
     * @columnName PEROPERT3
     * @required
     * @get true
     * @set false
     * @foreignField "icircle\\annotations\\Annotation->property1"
     */
    private $property3 = null;

    /**
     * @version 0
     * @constantValue
     */
    const constant1 = 'constant 1 value';

    /**
     * @version 1
     */
    public function setProperty1(){

    }

    /**
     * @version 2
     */
    public function setProperty2(){

    }

}

class AnnotationTest extends \PHPUnit_Framework_TestCase{

    public function setUp(){
        Annotation::unRegisterAnnotations();
    }

    /**
     * @dataProvider registryProvider
     */
    public function testRegisterAnnotations($source,$type,$isPositiveInput){

        $isException = false;
        try{
            Annotation::registerAnnotations($source,$type);
        }catch (\Exception $e){
            $isException = true;
        }
        if($isPositiveInput){
            $this->assertTrue(!$isException);
            echo $isException;
            $registry = "";
            switch($type){
                case "PATH":
                    $source = file_get_contents($source);
                case "JSON":
                    $source = json_decode($source,true);
                case "NATIVE" :
                case null :
                    $registry = $source;
            }

            $this->assertTrue(count(Annotation::getAnnotationRegistry()) == count($registry));
        }else{
            $this->assertTrue($isException);
        }

        $wrongJSON = '{"aaa":"bbbb","ccc"}';
        $isException = false;
        try{
            Annotation::registerAnnotations($wrongJSON,"PATH");
        }catch (\Exception $e){
            $isException = true;
        }
        $this->assertTrue($isException);

    }

    public function registryProvider()
    {
        return array(
            array('wrong/path/to/annotation/registry/file', 'PATH', false),
            array(dirname(__FILE__).'/annotations1.json', 'PATH', true),

            array('{"aaa":"bbbb","ccc"}abcd', 'JSON', false),
            array('{"aaa":"bbbb","ccc":"dddd"}', 'JSON', true),

            array('{"aaa":"bbbb","ccc"}abcd', 'NATIVE', false),
            array(array("aaa"=>"bbbb","ccc"=>"dddd"), 'NATIVE', true),

            array('{"aaa":"bbbb","ccc"}abcd', null, false),
            array(array("aaa"=>"bbbb","ccc"=>"dddd"), null, true)
        );
    }


    /**
     * @dataProvider getAnnotationsDataProvider
     */
    public function testGetAnnotations($registry,$className,$member,$output){

        Annotation::registerAnnotations($registry);

        $annotations = Annotation::getAnnotations($className,$member[0],$member[1],$member[2]);

        $this->assertArraySubset($annotations,$output);
        $this->assertArraySubset($output,$annotations);
    }

    public function getAnnotationsDataProvider(){
        return array(
            array(
                array("tableName"=>array("appliedOn"=>"class","allowNull"=>false,"type"=>"string"),  // registry
                "required"=>array("appliedOn"=>"property","type"=>"string")
                ),
                'icircle\annotations\SampleClass', // className
                array("*",null,null), // memberName
                array("class"=>array("tableName"=>"SAMPLE"), // output
                      "properties"=>array(
                          "property1"=>array("required"=>null),
                          "property2"=>array("required"=>null),
                          "property3"=>array("required"=>null)
                      ),
                      "constants"=>array(),
                      "methods"=>array()
                )
            ),
            array(
                array("tableName"=>array("appliedOn"=>"class","allowNull"=>false,"type"=>"string"),  // registry
                    "required"=>array("appliedOn"=>"property","type"=>"string"),
                    "columnName"=>array("appliedOn"=>"property","allowNull"=>false,"type"=>"string"),
                    "get"=>array("appliedOn"=>"property"),
                    "set"=>array("appliedOn"=>"property"),
                    "reference"=>array("appliedOn"=>"property"),
                    "foreignField"=>array("appliedOn"=>"property","allowNull"=>false),
                    "version"=>array("appliedOn"=>"All"),
                    "constantValue"=>array("appliedOn"=>"constant")
                ),
                'icircle\annotations\SampleClass', // className
                array("*","*","setProperty1"), // memberName
                array("class"=>array("tableName"=>"SAMPLE"), // output
                    "properties"=>array(
                        "property1"=>array( 'columnName' => 'PEROPERT1',
                                            'required' => null,
                                            'get' => 'true',
                                            'set' => 'false',
                                            'reference' => null),
                        "property2"=>array( 'columnName' => 'PEROPERT2',
                                            'required' => null,
                                            'get' => 'true',
                                            'set' => 'false',
                                            'reference' => null),
                        "property3"=>array( 'columnName' => 'PEROPERT3',
                                            'required' => null,
                                            'get' => 'true',
                                            'set' => 'false',
                                            'foreignField' => 'icircle\annotations\Annotation->property1')
                    ),
                    "constants"=>array(
                        "constant1"=>array( 'constantValue'=>null,
                                            'version' => 0)
                    ),
                    "methods"=>array(
                        "setProperty1"=>array('version'=>1)
                    )
                )
            )
        );
    }

}