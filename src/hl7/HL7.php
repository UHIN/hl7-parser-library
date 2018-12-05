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
        switch ($name)
        {
            case "first_name":
                return $this->getFirstName();
                break;
            case "middle_name":
                return $this->getMiddleName();
                break;
            case "last_name":
                return $this->getLastName();
                break;
            case "message_type":
                return $this->getMessageType();
                break;
            case "patient_type":
                return $this->getPatientType();
                break;
            case "control_number":
                return $this->getControlNumber();
                break;
            case "event_time":
                return $this->getEventTime();
                break;
            case "facility_name":
                return $this->getFacilityName();
                break;
            case "eid":
                return $this->getIdentifier("UHIN");
                break;
            default:
                if(array_key_exists($name,$this->properties))
                {
                    return $this->properties[$name];
                }
                else
                {
                    return null;
                }
                break;
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
            $segments = HL7::explode(PHP_EOL,$this->text,$this->separators->escape_character);

            foreach ($segments as $value)
            {
                $row = HL7::explode($this->separators->segment_separator, $value,$this->separators->escape_character);

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
                        case "DG1":
                        case "OBX":
                        case "PR1":
                        case "NTE":
                        case "AL1":
                        case "ACC":
                        case "IAM":
                        case "GT1":
                        case "ROL":
                        case "IN1":
                        case "IN2":
                            $this->properties[$key][] = new Segment($row,$this->separators);
                            break;
                        default:
                            if(strpos($key,'Z') === 0)
                            {
                                $this->properties[$key][] = new Segment($row,$this->separators);
                            }
                            else
                            {
                                if(array_key_exists($key,$this->properties))
                                {
                                    throw new \Exception("Repeatable Segment found outside of an array. ".$key);
                                }
                                $this->properties[$key] = new Segment($row,$this->separators);
                            }
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
        if(HL7::str_contains($separators->component_separator,$input,$separators->escape_character))
        {
            return true;
        }
        else
        {
            return false;
        }
//        if(preg_match('/(?<!\\'.$separators->escape_character.'.)\\'.$separators->component_separator.'/',$input))
//        {
//            return true;
//        }
//        else
//        {
//            return false;
//        }
    }

    public static function containsRepetitionSeparator($input,Separators $separators)
    {
        if(HL7::str_contains($separators->repetition_separator,$input,$separators->escape_character))
        {
            return true;
        }
        else
        {
            return false;
        }
//        if(preg_match('/(?<!\\'.$separators->escape_character.'.)\\'.$separators->repetition_separator.'/',$input))
//        {
//            return true;
//        }
//        else
//        {
//            return false;
//        }
    }

    public static function containsSubcomponentSeparator($input, Separators $separators)
    {
        if(HL7::str_contains($separators->subcomponent_separator,$input,$separators->escape_character))
        {
            return true;
        }
        else
        {
            return false;
        }
//        if(preg_match('/(?<!\\'.$separators->escape_character.'.)\\'.$separators->subcomponent_separator.'/',$input))
//        {
//            return true;
//        }
//        else
//        {
//            return false;
//        }
    }

    /**
     * @param $search
     * @param $subject
     * @param $escapeCharacter
     * @return bool
     */
    public static function str_contains($search,$subject,$escapeCharacter)
    {
        $str_array = str_split($subject);
        for ($i = 0; $i < count($str_array);$i++)
        {
            if($str_array[$i] == $search)
            {
                /* Look for escape character */
                if($i > 0 && ($str_array[$i-1] == $escapeCharacter))
                {
                    continue;
                }
                else
                {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $delimiter
     * @param $str
     * @param string $escapeChar
     * @return array
     * Stole this code from https://r.je/php-explode-split-with-escape-character.html
     */
    public static function explode($delimiter, $str, $escapeChar = '\\')
    {

        //Just some random placeholders that won't ever appear in the source $str

        $double = "\0\0\0_doub";

        $escaped = "\0\0\0_esc";

        $str = str_replace($escapeChar . $escapeChar, $double, $str);

        $str = str_replace($escapeChar . $delimiter, $escaped, $str);

        $split = explode($delimiter, $str);

        if($escapeChar = '\\')
        {
            $escapeChar = '\\';
        }

        foreach ($split as &$val) $val = str_replace([$double, $escaped], [$escapeChar.$escapeChar, $escapeChar.$delimiter], $val);

        return $split;

    }

    /**
     * @return null
     */
    private function getFirstName()
    {
        if(is_array($this->PID->{'PID.5'}))
        {
            if (isset($this->PID->{'PID.5'}[0]->{'PID.5.2'}))
            {
                return $this->PID->{'PID.5'}[0]->{'PID.5.2'};
            }
        }
        else
        {
            if (isset($this->PID->{'PID.5'}->{'PID.5.2'}))
            {
                return $this->PID->{'PID.5'}->{'PID.5.2'};
            }
        }
        return null;
    }

    public function __isset($name)
    {
        return !is_null($this->__get($name));
    }

    /**
     * @return null
     */
    private function getMiddleName()
    {
        if(is_array($this->PID->{'PID.5'}))
        {
            if (isset($this->PID->{'PID.5'}[0]->{'PID.5.3'}))
            {
                return $this->PID->{'PID.5'}[0]->{'PID.5.3'};
            }
        }
        else
        {
            if (isset($this->PID->{'PID.5'}->{'PID.5.3'}))
            {
                return $this->PID->{'PID.5'}->{'PID.5.3'};
            }
        }
        return null;
    }

    /**
     * @return null
     */
    private function getLastName()
    {
        if(is_array($this->PID->{'PID.5'}))
        {
            if (isset($this->PID->{'PID.5'}[0]->{'PID.5.1'}))
            {
                return $this->PID->{'PID.5'}[0]->{'PID.5.1'};
            }
        }
        else
        {
            if (isset($this->PID->{'PID.5'}->{'PID.5.1'}))
            {
                return $this->PID->{'PID.5'}->{'PID.5.1'};
            }
        }
        return null;
    }

    /**
     * @return null
     */
    private function getFacilityName()
    {
        if(isset($this->MSH->{'MSH.4'}->{'MSH.4.1'}))
        {
            return $this->MSH->{'MSH.4'}->{'MSH.4.1'};
        }
        return null;
    }

    private function getMessageType()
    {
        if(isset($this->MSH->{'MSH.9'}->{'MSH.9.2'}))
        {
            return $this->MSH->{'MSH.9'}->{'MSH.9.2'};
        }
        return null;
    }

    private function getPatientType()
    {
        if(isset($this->PV1->{'PV1.2'}->{'PV1.2.1'}))
        {
            return substr($this->PV1->{'PV1.2'}->{'PV1.2.1'},0,10);
        }
        return null;
    }

    private function getControlNumber()
    {
        if(isset($this->MSH->{'MSH.10'}->{'MSH.10.1'}))
        {
            return $this->MSH->{'MSH.10'}->{'MSH.10.1'};
        }
        return null;
    }

    private function getEventTime()
    {
        if(!isset($this->EVN) || !isset($this->EVN->{'EVN.2'}->{'EVN.2.1'}))
        {
            if(isset($this->MSH->{'MSH.7'}->{'MSH.7.1'}))
            {
                try
                {
                    $time = new \DateTime($this->MSH->{'MSH.7'}->{'MSH.7.1'});
                    return $time->format("Y-m-d H:i:s");
                }
                catch (\Exception $e)
                {
                    return null;
                }
            }
        }
        else if(isset($this->EVN->{'EVN.2'}->{'EVN.2.1'}))
        {
            try
            {
                $time = new \DateTime($this->EVN->{'EVN.2'}->{'EVN.2.1'});
                return $time->format("Y-m-d H:i:s");
            }
            catch (\Exception $e)
            {
                return null;
            }
        }
        return null;
    }

    public function getIdentifier($source)
    {
        if(!isset($this->PID))
        {
            return null;
        }

        /* Try PID.2.1 First */
        if(isset($this->PID->{"PID.2"}))
        {
            if(is_array($this->PID->{"PID.2"}))
            {
                foreach ($this->PID->{"PID.2"} as $PID)
                {
                    if(isset($PID->{"PID.2.4"}))
                    {
                        if(is_object($PID->{"PID.2.4"}))
                        {
                            if(isset($PID->{"PID.2.4"}->{"PID.2.4.1"}))
                            {
                                if(strpos($PID->{"PID.2.4"}->{"PID.2.4.1"}, $source) !== false)
                                {
                                    if(isset($PID->{"PID.2.1"}))
                                    {
                                        return $PID->{"PID.2.1"};
                                    }
                                }
                            }
                        }
                        else if(strpos($PID->{"PID.2.4"}, $source) !== false)
                        {
                            if(isset($PID->{"PID.2.1"}))
                            {
                                return $PID->{"PID.2.1"};
                            }
                        }
                    }
                }
            }
            else
            {
                if (isset($this->PID->{"PID.2"}->{"PID.2.4"}) && strpos($this->PID->{"PID.2"}->{"PID.2.4"},$source) !== false)
                {
                    if (isset($this->PID->{"PID.2"}->{"PID.2.1"}))
                    {
                        return $this->PID->{"PID.2"}->{"PID.2.1"};
                    }
                }
            }
        }

        /* Try the PID.3.1 Next */
        if(isset($this->PID->{"PID.3"}))
        {
            if(is_array($this->PID->{"PID.3"}))
            {
                foreach ($this->PID->{"PID.3"} as $PID)
                {
                    if(isset($PID->{"PID.3.4"}))
                    {
                        if(is_object($PID->{"PID.3.4"}))
                        {
                            if(isset($PID->{"PID.3.4"}->{"PID.3.4.1"}))
                            {
                                if(strpos($PID->{"PID.3.4"}->{"PID.3.4.1"}, $source) !== false)
                                {
                                    if(isset($PID->{"PID.3.1"}))
                                    {
                                        return $PID->{"PID.3.1"};
                                    }
                                }
                            }
                        }
                        else if(strpos($PID->{"PID.3.4"}, $source) !== false)
                        {
                            if(isset($PID->{"PID.3.1"}))
                            {
                                return $PID->{"PID.3.1"};
                            }
                        }
                    }
                }
            }
            else
            {
                if(isset($this->PID->{"PID.3"}->{"PID.3.4"}))
                {
                    if(is_object($this->PID->{"PID.3"}->{"PID.3.4"}))
                    {
                        if(isset($this->PID->{"PID.3"}->{"PID.3.4"}->{"PID.3.4.1"}))
                        {
                            if(strpos($this->PID->{"PID.3"}->{"PID.3.4"}->{"PID.3.4.1"}, $source) !== false)
                            {
                                if(isset($this->PID->{"PID.3"}->{"PID.3.1"}))
                                {
                                    return $this->PID->{"PID.3"}->{"PID.3.1"};
                                }
                            }
                        }
                    }
                    if(strpos($this->PID->{"PID.3"}->{"PID.3.4"}, $source) !== false)
                    {
                        if(isset($this->PID->{"PID.3"}->{"PID.3.1"}))
                        {
                            return $this->PID->{"PID.3"}->{"PID.3.1"};
                        }
                    }
                }
            }
        }
    }

}