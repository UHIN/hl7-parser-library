<?php

namespace Uhin\Hl7;

use JsonSerializable;

class Segment implements JsonSerializable
{
    private $separators;
    public $properties;

    public function __construct($segment,$separators)
    {
        $this->separators = $separators;
        $this->properties = new \stdClass();

        if(!is_array($segment))
        {
            $segment = HL7::explode($this->separators->segment_separator,$segment,$this->separators->escape_character);
        }

        if(count($segment) > 0)
        {
            for ($i=0; $i < count($segment); $i++)
            {
                if($i > 0)
                {
                    $value = $segment[$i];

                    if($value == "")
                    {
                        $this->properties->{$segment[0].'.'.$i} = $value;
                    }
                    /* Check for Repeat Separator */
                    else if(HL7::containsRepetitionSeparator($value,$this->separators))
                    {
                        $values = HL7::explode($this->separators->repetition_separator,$value,$this->separators->escape_character);
                        for ($j = 0; $j < count($values); $j++)
                        {
                            if($values[$j] == "")
                            {
                                $this->properties->{$segment[0].'.'.$i}[] = $values[$j];
                            }
                            else
                            {
                                $this->properties->{$segment[0] . '.' . $i}[] = new Field($values[$j], $segment[0] . '.' . $i, $separators);
                            }
                        }
                    }
                    else
                    {
                        $this->properties->{$segment[0].'.'.$i} = new Field($value,$segment[0].'.'.$i,$separators);
                    }
                }
            }
        }
    }

    public function __get($name)
    {
        if(isset($this->properties->{$name}))
        {
            return $this->properties->{$name};
        }
        else
        {
            return null;
        }
    }

    public function __set($name, $value)
    {
        // TODO: Implement __set() method.
    }

    public function jsonSerialize()
    {
        return $this->properties;
    }

    public function __isset($name)
    {
        return !is_null($this->__get($name));
    }

    public function glue()
    {
        $hl7 = "";
        foreach ($this->properties as $key => $value)
        {
            if(is_object($this->properties->{$key}))
            {
                $hl7 = $hl7.$this->separators->segment_separator.$this->properties->{$key}->glue();
            }
            else
            {
                $hl7 = $hl7.$this->separators->segment_separator.$value;
            }
        }
        return $hl7;
    }




}
