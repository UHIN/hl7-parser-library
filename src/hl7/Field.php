<?php

namespace Uhin\Hl7;

use JsonSerializable;


class Field extends Segment implements JsonSerializable
{
    protected $separators;
    public $properties;
    private $fieldKeyPrefix;

    public function __construct($field,$fieldKeyPrefix,$separators)
    {
        $this->separators = $separators;
        $this->fieldKeyPrefix = $fieldKeyPrefix;
        $this->properties = new \stdClass();

        /* Don't breakdown the Control Characters in the MSH */
        if($this->fieldKeyPrefix == "MSH.2" || !HL7::needsBreakdown($field,$this->separators))
        {
            $this->properties->{$fieldKeyPrefix.'.1'} = $field;
        }
        else
        {
            $this->breakdown($field);
        }
    }

    public function __get($name)
    {
        if(isset($this->properties->{$name}))
        {
            if($this->properties->{$name} instanceof Field)
            {
                return $this->properties->{$name}->{$name};
            }

            return $this->properties->{$name};

        }

        return null;

    }

    public function __set($name, $value)
    {
        // TODO: Implement __set() method.
    }

    protected function breakdown($input)
    {
        if(HL7::containsComponentSeparator($input,$this->separators))
        {
            $values = HL7::explode($this->separators->component_separator,$input,$this->separators->escape_character);

            for ($i=0; $i < count($values); $i++)
            {
                if(HL7::containsSubcomponentSeparator($values[$i],$this->separators))
                {
                    $this->properties->{$this->fieldKeyPrefix.'.'.($i+1)} = new SubField($values[$i],$this->fieldKeyPrefix.'.'.($i+1),$this->separators);
                }
                else
                {
                    $this->properties->{$this->fieldKeyPrefix.'.'.($i+1)} = $values[$i];
                }

            }
        }
        else if(HL7::containsSubcomponentSeparator($input,$this->separators))
        {
            $values = HL7::explode($this->separators->subcomponent_separator,$input,$this->separators->escape_character);
            for ($i=0; $i < count($values); $i++)
            {
                $this->properties->{$this->fieldKeyPrefix.'.'.($i+1)} = new SubField($values[$i],$this->fieldKeyPrefix.'.'.($i+1),$this->separators);
            }
        }
        else
        {
            $this->properties = $input;
        }

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
        $first = true;
        foreach ($this->properties as $key => $property)
        {
            if(is_object($this->properties->{$key}))
            {
                $hl7 = $this->properties->{$key}->glue();
            }
            else
            {
                if(!$first)
                {
                    $hl7 = $hl7.$this->separators->component_separator.$property;
                }
                else
                {
                    $hl7 = $hl7.$property;
                    $first = false;
                }

            }

        }
        return $hl7;
    }
}
