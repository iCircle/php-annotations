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
        //Annotation::unRegisterAnnotations();
    }

    /**
     * @dataProvider registryProvider
     */
    /**
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
    }*/


    public function testGetAnnotations(){

        $annotations = Annotation::getAnnotations('icircle\annotations\SampleClass');

        $expected = array(
            "class" => array(
                "package" => "icircle",
                "required" => null,
                "tableName" => "SAMPLE",
            ),

            "properties" => array(
                "property1" => array(
                    "columnName" => "PEROPERT1",
                    "required" => null,
                    "get" => true,
                    "set" => false,
                    "reference" => null,
                ),
                "property2" => array(
                    "columnName" => "PEROPERT2",
                    "required" => null,
                    "get" => true,
                    "set" => false,
                    "reference" => null
                ),
                "property3" => array(
                    "columnName" => "PEROPERT3",
                    "required" => null,
                    "get" => true,
                    "set" => false,
                    "foreignField" => 'icircle\annotations\Annotation->property1'
                )
            ),
            "constants" => array(
                "constant1" => array(
                    "version" => 0,
                    "constantValue" => null
                )
            ),
            "methods" => array(
                "setProperty1" => array(
                    "version" => 1
                ),
                "setProperty2" => array(
                    "version" => 2
                )
            )
        );

        $this->assertArraySubset($annotations,$expected);

    }


}