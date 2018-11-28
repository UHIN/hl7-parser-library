<?php

namespace Uhin\HL7;



use JsonSerializable;

class HL7 implements JsonSerializable
{

    public $properties;
    private $text;
    private $separators;

    public function __construct()
    {
        $this->properties = [];
        $this->text = '';
        $this->separators = new Separators();
    }

    public function __get($name)
    {
        if(array_key_exists($name,$this->properties))
        {
            return $this->properties[$name];
        }
        else
        {
            return null;
        }
    }

    public function __set($name, $value)
    {

    }

    public function parse($hl7)
    {
        /* Check that the HL7 starts with a MSH */
        if(substr($hl7,0,3) != "MSH")
        {
            throw new \Exception("Failed to parse an HL7 message from the supplied data. Supplied data does not start with MSH.");
        }
        /* Normalize the input */
        $this->text = self::normalizeLineEndings($hl7);

        /* determine control characters. */
        $this->separators->setControlCharacters($this->text);

        try
        {
            $segments = explode(PHP_EOL,$this->text);

            foreach ($segments as $value)
            {
                $row = explode($this->separators->segment_separator, $value);

                $key = $row[0]; // $this->generateKey($row);

                /* Check to see if we need to turn the property into an array for duplicate key situations.  */
                if(array_key_exists($key,$this->properties))
                {
                    if(!is_array($this->properties[$key]))
                    {
                        $temp = $this->properties[$key];
                        $this->properties[$key] = [];
                        $this->properties[$key][] = $temp;
                    }
                }

                if($key != "")
                {
                    switch ($key)
                    {
                        case "MSH":
                            $this->properties[$key] = new MSH($row,$this->separators);
                            break;
                        /* Repeatable segments go here. Still working on a definitive list. */
                        case "NK1":
                            $this->properties[$key][] = new Segment($row,$this->separators);
                            break;
                        case "DG1":
                            $this->properties[$key][] = new Segment($row,$this->separators);
                            break;
                        case "OBX":
                            $this->properties[$key][] = new Segment($row,$this->separators);
                            break;
                        case "PR1":
                            $this->properties[$key][] = new Segment($row,$this->separators);
                            break;
                        default:
                            if(array_key_exists($key,$this->properties))
                            {
                                throw new \Exception("Repeatable Segment found outside of an array. ".$key);
                            }
                            $this->properties[$key] = new Segment($row,$this->separators);
                            break;
                    }
                }
            }
        }
        catch(\Exception $e)
        {
            throw new \Exception("Error spliting rows. ".$e->getMessage());
        }

    }

    public function glue()
    {

    }

    public function jsonSerialize()
    {
        return $this->properties;
    }

    /**
     * @param $row
     * @return string
     */
    private function generateKey($row)
    {
        if(array_key_exists($row[0],$this->properties))
        {
            /* Create a new key */
            return uniqid($row[0]."_",true);
        }
        else
        {
            return $row[0];
        }
    }

    /**
     * @param $key
     * @return bool|string
     */
    private function sanatizeKey($key)
    {
        if(strpos($key,'_') === false)
        {
            return $key;
        }
        else
        {
            return substr($key,0,strpos($key,'_'));
        }
    }

    /**
     * @param $text
     * @return mixed
     */
    public static function normalizeLineEndings($text)
    {
        $text = str_replace("\r\n", PHP_EOL, $text);

        $text = str_replace("\r", PHP_EOL, $text);

        $text = str_replace("\n", PHP_EOL, $text);

        return $text;
    }

    public static function needsBreakdown($input,Separators $separators)
    {
        if(HL7::containsComponentSeparator($input,$separators))
        {
            return true;
        }

        if(HL7::containsRepetitionSeparator($input,$separators))
        {
            return true;
        }

        if(HL7::containsSubcomponentSeparator($input,$separators))
        {
            return true;
        }
        return false;
    }

    public static function containsComponentSeparator($input,Separators $separators)
    {
        if(strpos($input,$separators->component_separator) === false)
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    public static function containsRepetitionSeparator($input,Separators $separators)
    {
        if(strpos($input,$separators->repetition_separator) === false)
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    public static function containsSubcomponentSeparator($input, Separators $separators)
    {
        if(strpos($input,$separators->subcomponent_separator) === false)
        {
            return false;
        }
        else
        {
            return true;
        }
    }


}